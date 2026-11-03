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
 * External web service: telegram_generate_link
 * Generates a signed HMAC deep-link token for Telegram account linking.
 * Called via AJAX from the block_saipa chat widget.
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
 * Telegram_generate_link.
 */
class telegram_generate_link extends external_api {
    /**
     * Define the parameters for this web service.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'course_id' => new external_value(PARAM_INT, 'Course ID for session context', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute the web service.
     *
     * @param int $courseid Course ID for session context.
     */
    public static function execute(int $courseid = 0): array {
        global $USER, $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['course_id' => $courseid]);

        // Linking a Telegram account is for users who can use the SAIPA chat.
        \local_saipa\local\access::require_chat_user($params['course_id']);

        $now     = time();
        $expires = $now + (15 * MINSECS);   // 15-minute window

        // Single-use random token (64 hex characters, the most a Telegram /start payload takes).
        // It used to be an HMAC of the user id and the expiry time, which is guessable when the
        // site has no telegram_hmac_secret (set only by upgrade.php, never on a fresh install).
        $token = bin2hex(random_bytes(32));

        // Upsert the link record (one row per user).
        $existing = $DB->get_record('local_saipa_telegram_links', ['userid' => (int) $USER->id]);
        if ($existing) {
            // If already confirmed, return current status — don't overwrite.
            if ($existing->confirmed) {
                return [
                    'already_linked'    => true,
                    'telegram_username' => (string) ($existing->telegram_username ?? ''),
                    'deep_link'         => '',
                    'bot_username'      => get_config('local_saipa', 'telegram_bot_username') ?: 'saipa_bot',
                    'token'             => '',
                    'expires'           => 0,
                ];
            }
            // Update pending token.
            $DB->update_record('local_saipa_telegram_links', (object) [
                'id'           => $existing->id,
                'link_token'   => $token,
                'token_expires' => $expires,
                'timemodified' => $now,
            ]);
        } else {
            $DB->insert_record('local_saipa_telegram_links', (object) [
                'userid'            => (int) $USER->id,
                'telegram_id'       => null,
                'telegram_username' => null,
                'link_token'        => $token,
                'token_expires'     => $expires,
                'confirmed'         => 0,
                'timecreated'       => $now,
                'timemodified'      => $now,
            ]);
        }

        $botusername = get_config('local_saipa', 'telegram_bot_username') ?: 'saipa_bot';
        $deeplink    = "https://t.me/{$botusername}?start={$token}";

        return [
            'already_linked'    => false,
            'telegram_username' => '',
            'deep_link'         => $deeplink,
            'bot_username'      => $botusername,
            'token'             => $token,
            'expires'           => $expires,
        ];
    }

    /**
     * Define the return structure for this web service.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'already_linked'    => new external_value(PARAM_BOOL, 'True if this user already has a confirmed Telegram link'),
            'telegram_username' => new external_value(PARAM_TEXT, 'Linked Telegram @username (if already linked)'),
            'deep_link'         => new external_value(PARAM_URL, 'Telegram deep link to open in the app'),
            'bot_username'      => new external_value(PARAM_TEXT, 'Bot @username without @'),
            'token'             => new external_value(PARAM_ALPHANUM, 'Raw HMAC token (hex)'),
            'expires'           => new external_value(PARAM_INT, 'Unix timestamp when the token expires'),
        ]);
    }
}
