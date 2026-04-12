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
 * External web service: telegram_get_session
 * Resolves a Telegram chat_id to a Moodle user + most-recent active course.
 * Called server-to-server by saipa-engine on every incoming Telegram message.
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
 * Telegram_get_session.
 */
class telegram_get_session extends external_api {
    /**
     * Define the parameters for this web service.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'telegram_id' => new external_value(PARAM_INT, 'Telegram chat_id'),
        ]);
    }

    /**
     * Execute the web service.
     */
    public static function execute(int $telegramid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['telegram_id' => $telegramid]);

        $link = $DB->get_record('saipa_telegram_links', [
            'telegram_id' => $params['telegram_id'],
            'confirmed'   => 1,
        ]);

        if (!$link) {
            return ['linked' => false, 'user_id' => 0, 'course_id' => 0, 'fullname' => ''];
        }

        $user     = \core_user::get_user($link->userid, 'id, firstname, lastname', MUST_EXIST);
        $fullname = fullname($user);

        // Find the most recently active SAIPA session for this user.
        $session = $DB->get_record_sql(
            "SELECT s.courseid FROM {saipa_sessions} s
              WHERE s.userid = :uid
              ORDER BY s.timemodified DESC",
            ['uid' => $link->userid],
            0, // limitfrom
            1   // limitnum
        );

        $courseid = $session ? (int) $session->courseid : 0;

        return [
            'linked'    => true,
            'user_id'   => (int) $link->userid,
            'course_id' => $courseid,
            'fullname'  => $fullname,
        ];
    }

    /**
     * Define the return structure for this web service.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'linked'    => new external_value(PARAM_BOOL, 'True if this telegram_id is linked to a Moodle user'),
            'user_id'   => new external_value(PARAM_INT, 'Moodle user ID (0 if not linked)'),
            'course_id' => new external_value(PARAM_INT, 'Most-recently active course ID (0 if none)'),
            'fullname'  => new external_value(PARAM_TEXT, 'User full name'),
        ]);
    }
}
