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
 * External web service: get_student_history
 * Returns full conversation history for a specific student (teacher only).
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

defined('MOODLE_INTERNAL') || die();

class get_student_history extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'course_id'  => new external_value(PARAM_INT, 'Course ID'),
            'student_id' => new external_value(PARAM_INT, 'Student user ID'),
        ]);
    }

    public static function execute(int $course_id, int $student_id): array {
        global $DB;

        $params  = self::validate_parameters(self::execute_parameters(), [
            'course_id'  => $course_id,
            'student_id' => $student_id,
        ]);
        $context = \context_course::instance($params['course_id']);
        self::validate_context($context);
        require_capability('local/saipa:view', $context);

        if (!is_enrolled($context, $params['student_id'])) {
            throw new \moodle_exception('notenrolled', 'local_saipa');
        }

        $session = $DB->get_record('saipa_sessions', [
            'userid'   => $params['student_id'],
            'courseid' => $params['course_id'],
        ]);

        if (!$session) {
            return ['session_id' => 0, 'student_name' => '', 'messages' => []];
        }

        $user = $DB->get_record('user', ['id' => $params['student_id']], 'id,firstname,lastname');

        $rows = $DB->get_records(
            'saipa_messages',
            ['sessionid' => $session->id],
            'timecreated ASC',
            'role,content,timecreated'
        );

        $messages = array_values(array_map(fn($r) => [
            'role'        => $r->role,
            'content'     => $r->content,
            'timecreated' => (int) $r->timecreated,
        ], $rows));

        return [
            'session_id'   => (int) $session->id,
            'student_name' => fullname($user),
            'messages'     => $messages,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'session_id'   => new external_value(PARAM_INT, 'Session ID (0 if none)'),
            'student_name' => new external_value(PARAM_TEXT, 'Student full name'),
            'messages'     => new external_multiple_structure(
                new external_single_structure([
                    'role'        => new external_value(PARAM_TEXT, 'user or assistant'),
                    'content'     => new external_value(PARAM_RAW, 'Message content'),
                    'timecreated' => new external_value(PARAM_INT, 'Timestamp'),
                ])
            ),
        ]);
    }
}
