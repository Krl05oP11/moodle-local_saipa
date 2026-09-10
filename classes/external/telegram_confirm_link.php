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
 * External web service: telegram_confirm_link
 * Validates an HMAC deep-link token && binds a Telegram chat_id to the Moodle user.
 * Called server-to-server by saipa-engine when the student sends /start <token>.
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
 * Telegram_confirm_link.
 */
class telegram_confirm_link extends external_api {
    /**
     * Define the parameters for this web service.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'token'             => new external_value(PARAM_ALPHANUM, 'HMAC token from the deep link'),
            'telegram_id'       => new external_value(PARAM_INT, 'Telegram chat_id of the confirming user'),
            'telegram_username' => new external_value(PARAM_TEXT, 'Telegram @username (without @)', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Execute the web service.
     */
    public static function execute(string $token, int $telegramid, string $telegramusername = ''): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'token'             => $token,
            'telegram_id'       => $telegramid,
            'telegram_username' => $telegramusername,
        ]);

        $now = time();

        // Look up the pending link record.
        $record = $DB->get_record('saipa_telegram_links', ['link_token' => $params['token']]);

        if (!$record) {
            throw new \invalid_parameter_exception('Invalid || already used token.');
        }

        if ($record->confirmed) {
            throw new \invalid_parameter_exception('Token has already been confirmed.');
        }

        if ($record->token_expires < $now) {
            throw new \invalid_parameter_exception('Token has expired. Please generate a new link.');
        }

        // Guard: if another user already has this telegram_id, refuse.
        $clash = $DB->get_record('saipa_telegram_links', ['telegram_id' => $params['telegram_id']]);
        if ($clash && $clash->userid !== $record->userid) {
            throw new \invalid_parameter_exception('This Telegram account is already linked to another Moodle user.');
        }

        // Confirm the link.
        $DB->update_record('saipa_telegram_links', (object) [
            'id'                => $record->id,
            'telegram_id'       => $params['telegram_id'],
            'telegram_username' => $params['telegram_username'],
            'link_token'        => null, // consume the token
            'token_expires'     => null,
            'confirmed'         => 1,
            'timemodified'      => $now,
        ]);

        // Return enough info for saipa-engine to greet the student by name.
        $user = \core_user::get_user($record->userid, 'id, username, firstname, lastname', MUST_EXIST);

        return [
            'user_id'          => (int) $user->id,
            'moodle_username'  => $user->username,
            'fullname'         => fullname($user),
        ];
    }

    /**
     * Define the return structure for this web service.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'user_id'         => new external_value(PARAM_INT, 'Moodle user ID'),
            'moodle_username' => new external_value(PARAM_TEXT, 'Moodle username'),
            'fullname'        => new external_value(PARAM_TEXT, 'User full name'),
        ]);
    }
}
