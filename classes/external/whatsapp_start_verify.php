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
 * Generates an OTP and asks the engine to send it via WhatsApp.
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
    /** @var int Seconds an account waits between two codes. */
    public const MIN_INTERVAL = 60;
    /** @var int Codes one account can request per hour. */
    public const MAX_PER_ACCOUNT = 5;
    /** @var int Codes one phone number can receive per hour, whoever asks. */
    public const MAX_PER_NUMBER = 3;

    /**
     * Define the parameters for this web service.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'phone'     => new external_value(PARAM_TEXT, 'Phone number in international format (e.g. 5491112345678)'),
            'course_id' => new external_value(PARAM_INT, 'Course the call comes from (0 = none)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute the web service.
     *
     * @param string $phone Phone number in international format (e.g. 5491112345678).
     * @param int $courseid Course the call comes from (0 = none).
     */
    public static function execute(string $phone, int $courseid = 0): array {
        global $CFG;
        require_once($CFG->dirroot . '/local/saipa/lib.php');
        global $USER, $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['phone' => $phone, 'course_id' => $courseid]);
        \local_saipa\local\access::require_chat_user($params['course_id']);

        // Sanitise phone: keep only digits.
        $phoneclean = preg_replace('/\D/', '', $params['phone']);
        if (strlen($phoneclean) < 7 || strlen($phoneclean) > 20) {
            throw new \invalid_parameter_exception('Invalid phone number format.');
        }

        $now = time();

        // Sending limits: each code is a WhatsApp message paid by the institution and received by
        // whoever owns the number, so an account and a number get only a few codes per hour.
        $cache = \cache::make('local_saipa', 'otp');
        $accountkey = 'send_u' . $USER->id;
        $numberkey = 'send_p' . sha1($phoneclean);
        $recent = fn($times) => array_values(array_filter($times ?: [], fn($t) => $t > $now - HOURSECS));
        $accountsends = $recent($cache->get($accountkey));
        $numbersends = $recent($cache->get($numberkey));
        $toosoon = $accountsends && max($accountsends) > $now - self::MIN_INTERVAL;
        if ($toosoon || count($accountsends) >= self::MAX_PER_ACCOUNT || count($numbersends) >= self::MAX_PER_NUMBER) {
            return ['sent' => false, 'expires' => 0, 'detail' => get_string('whatsapp_otp_rate_limited', 'local_saipa')];
        }
        $accountsends[] = $now;
        $numbersends[] = $now;
        $cache->set($accountkey, $accountsends);
        $cache->set($numberkey, $numbersends);
        $cache->delete('attempts_u' . $USER->id);   // A new code gets a fresh set of attempts.

        $expires = $now + (10 * MINSECS);   // 10-minute OTP window
        $otp     = (string) random_int(100000, 999999);

        // Upsert into saipa_phone_verify (one row per user).
        $existing = $DB->get_record('local_saipa_phone_verify', ['userid' => (int) $USER->id]);
        if ($existing) {
            $DB->update_record('local_saipa_phone_verify', (object) [
                'id'          => $existing->id,
                'phone'       => $phoneclean,
                'otp'         => $otp,
                'verified'    => 0,
                'timeexpires' => $expires,
            ]);
        } else {
            $DB->insert_record('local_saipa_phone_verify', (object) [
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
            'detail'  => $sent ? '' : get_string('whatsapp_otp_send_failed', 'local_saipa'),
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
