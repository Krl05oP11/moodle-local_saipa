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
 * External web service: get_course_settings
 * Returns per-course feature flag settings for all SAIPA courses.
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

class get_course_settings extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    public static function execute(): array {
        global $DB;

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/saipa:advisor', $context);

        // Get all courses that have SAIPA activity.
        $sql = 'SELECT DISTINCT c.id AS courseid, c.fullname, c.shortname
                  FROM {course} c
                  JOIN {saipa_sessions} s ON s.courseid = c.id
                 ORDER BY c.fullname';
        $courses = $DB->get_records_sql($sql);

        // Get existing settings rows.
        $settings_map = [];
        $rows = $DB->get_records('saipa_course_settings');
        foreach ($rows as $row) {
            $settings_map[(int) $row->courseid] = $row;
        }

        $result = [];
        foreach ($courses as $course) {
            $cid = (int) $course->courseid;
            $s   = $settings_map[$cid] ?? null;

            // Get index status.
            $idx = $DB->get_record('saipa_course_index', ['courseid' => $cid]);

            $result[] = [
                'courseid'       => $cid,
                'coursename'     => $course->fullname,
                'shortname'      => $course->shortname,
                'saipa_enabled'  => $s ? (bool) $s->saipa_enabled  : true,
                'chat_enabled'   => $s ? (bool) $s->chat_enabled   : true,
                'risk_enabled'   => $s ? (bool) $s->risk_enabled   : true,
                'alerts_enabled' => $s ? (bool) $s->alerts_enabled : true,
                'rag_enabled'    => $s ? (bool) $s->rag_enabled    : true,
                'index_status'   => $idx ? $idx->status : 'pending',
                'last_indexed'   => $idx ? (int) $idx->last_indexed : 0,
            ];
        }

        return ['courses' => $result];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courses' => new external_multiple_structure(
                new external_single_structure([
                    'courseid'       => new external_value(PARAM_INT,  'Course ID'),
                    'coursename'     => new external_value(PARAM_TEXT, 'Course full name'),
                    'shortname'      => new external_value(PARAM_TEXT, 'Course short name'),
                    'saipa_enabled'  => new external_value(PARAM_BOOL, 'SAIPA enabled for course'),
                    'chat_enabled'   => new external_value(PARAM_BOOL, 'Chat widget enabled'),
                    'risk_enabled'   => new external_value(PARAM_BOOL, 'Risk evaluation enabled'),
                    'alerts_enabled' => new external_value(PARAM_BOOL, 'Alerts enabled'),
                    'rag_enabled'    => new external_value(PARAM_BOOL, 'RAG enabled'),
                    'index_status'   => new external_value(PARAM_TEXT, 'pending|indexing|ready|error'),
                    'last_indexed'   => new external_value(PARAM_INT,  'Unix timestamp of last index'),
                ])
            ),
        ]);
    }
}
