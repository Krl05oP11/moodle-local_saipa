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
 * External web service: get_history
 * Returns the conversation history for the current user in a course.
 *
 * @package    local_saipa
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_saipa\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;


/**
 * Get_history.
 */
class get_history extends external_api {
    /**
     * Define the parameters for this web service.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'course_id' => new external_value(PARAM_INT, 'Course ID'),
        ]);
    }

    /**
     * Execute the web service.
     */
    public static function execute(int $courseid): array {
        global $USER, $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'course_id' => $courseid,
        ]);

        $context = \context_course::instance($params['course_id']);
        self::validate_context($context);
        require_capability('local/saipa:chat', $context);

        $now = time();

        // Find or create session for this user+course.
        $session = $DB->get_record('saipa_sessions', [
            'userid'   => (int) $USER->id,
            'courseid' => $params['course_id'],
        ]);

        if (!$session) {
            $session = (object) [
                'userid'       => (int) $USER->id,
                'courseid'     => $params['course_id'],
                'contextid'    => $context->id,
                'timecreated'  => $now,
                'timemodified' => $now,
            ];
            $session->id = $DB->insert_record('saipa_sessions', $session);
        }

        // Fetch last 20 messages ordered chronologically.
        $rows = $DB->get_records(
            'saipa_messages',
            ['sessionid' => $session->id],
            'timecreated ASC',
            'role,content',
            0,
            20
        );

        $messages = array_values(array_map(fn($r) => [
            'role'    => $r->role,
            'content' => $r->content,
        ], $rows));

        return [
            'session_id' => $session->id,
            'messages'   => $messages,
        ];
    }

    /**
     * Define the return structure for this web service.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'session_id' => new external_value(PARAM_INT, 'Session ID'),
            'messages'   => new external_multiple_structure(
                new external_single_structure([
                    'role'    => new external_value(PARAM_TEXT, 'user || assistant'),
                    'content' => new external_value(PARAM_RAW, 'Message content'),
                ]),
                'Previous messages in this session'
            ),
        ]);
    }
}
