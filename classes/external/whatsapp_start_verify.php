<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * External web service: whatsapp_start_verify
 * Generates an OTP && asks the engine to send it via WhatsApp.
 * Called via AJAX from block_saipa when the student enters their phone number.
 *
 * @package    local_saipa
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_saipa\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;



/**
 * Whatsapp_start_verify.
 */
class whatsapp_start_verify extends external_api {
    /**
     * Define the parameters for this web service.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'phone' => new external_value(PARAM_TEXT, 'Phone number in international format (e.g. 5491112345678)'),
        ]);
    }

    /**
     * Execute the web service.
     */
    public static function execute(string $phone): array {
        global $CFG;
        require_once($CFG->dirroot . '/local/saipa/lib.php');
        global $USER, $DB;

        require_login();

        $params = self::validate_parameters(self::execute_parameters(), ['phone' => $phone]);

        // Sanitise phone: keep only digits.
        $phoneclean = preg_replace('/\D/', '', $params['phone']);
        if (strlen($phoneclean) < 7 || strlen($phoneclean) > 20) {
            throw new \invalid_parameter_exception('Invalid phone number format.');
        }

        $now     = time();
        $expires = $now + (10 * MINSECS);   // 10-minute OTP window
        $otp     = (string) random_int(100000, 999999);

        // Upsert into saipa_phone_verify (one row per user).
        $existing = $DB->get_record('saipa_phone_verify', ['userid' => (int) $USER->id]);
        if ($existing) {
            $DB->update_record('saipa_phone_verify', (object) [
                'id'          => $existing->id,
                'phone'       => $phoneclean,
                'otp'         => $otp,
                'verified'    => 0,
                'timeexpires' => $expires,
            ]);
        } else {
            $DB->insert_record('saipa_phone_verify', (object) [
                'userid'      => (int) $USER->id,
                'phone'       => $phoneclean,
                'otp'         => $otp,
                'verified'    => 0,
                'timecreated' => $now,
                'timeexpires' => $expires,
            ]);
        }

        // Ask saipa-engine to send the OTP via WhatsApp.
        $resp = local_saipa_engine_request('/whatsapp/send_otp', [
            'phone' => $phoneclean,
            'otp'   => $otp,
        ], 15);

        $sent = !empty($resp['sent']);

        return [
            'sent'    => $sent,
            'expires' => $expires,
            'detail'  => $sent ? '' : 'WhatsApp message could not be sent. Check engine WhatsApp configuration.',
        ];
    }

    /**
     * Define the return structure for this web service.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'sent'    => new external_value(PARAM_BOOL, 'True if the WhatsApp OTP message was sent successfully'),
            'expires' => new external_value(PARAM_INT, 'Unix timestamp when the OTP expires'),
            'detail'  => new external_value(PARAM_TEXT, 'Error detail if sending failed'),
        ]);
    }
}
