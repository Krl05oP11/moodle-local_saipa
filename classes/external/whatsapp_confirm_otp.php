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
 * External web service: whatsapp_confirm_otp
 * Validates the OTP entered by the student && marks the phone as verified.
 * Called via AJAX from block_saipa after the student enters the OTP they received.
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
 * Whatsapp_confirm_otp.
 */
class whatsapp_confirm_otp extends external_api {
    /**
     * Define the parameters for this web service.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'otp' => new external_value(PARAM_ALPHANUMEXT, '6-digit OTP code'),
        ]);
    }

    /**
     * Execute the web service.
     */
    public static function execute(string $otp): array {
        global $USER, $DB;

        require_login();

        $params = self::validate_parameters(self::execute_parameters(), ['otp' => $otp]);

        $record = $DB->get_record('saipa_phone_verify', ['userid' => (int) $USER->id]);

        if (!$record) {
            return ['success' => false, 'error' => 'no_pending_verification'];
        }

        if ($record->verified) {
            // Already verified — idempotent success.
            return ['success' => true, 'phone' => $record->phone, 'error' => ''];
        }

        if ($record->timeexpires < time()) {
            return ['success' => false, 'error' => 'otp_expired'];
        }

        if ($record->otp !== $params['otp']) {
            return ['success' => false, 'error' => 'otp_invalid'];
        }

        // Mark as verified.
        $DB->set_field('saipa_phone_verify', 'verified', 1, ['id' => $record->id]);

        return ['success' => true, 'phone' => $record->phone, 'error' => ''];
    }

    /**
     * Define the return structure for this web service.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'True if OTP is correct && phone is now verified'),
            'phone'   => new external_value(PARAM_TEXT, 'Verified phone number', VALUE_OPTIONAL),
            'error'   => new external_value(PARAM_ALPHA, 'Error code: otp_invalid, otp_expired, no_pending_verification'),
        ]);
    }
}
