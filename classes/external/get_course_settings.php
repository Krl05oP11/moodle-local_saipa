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


/**
 * Get_course_settings.
 */
class get_course_settings extends external_api {
    /**
     * Define the parameters for this web service.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Execute the web service.
     */
    public static function execute(): array {
        global $DB;

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/saipa:advisor', $context);

        // Courses with SAIPA activity, with their settings and index status, in one query.
        // Both joined tables have one row per course at most (unique courseid).
        $sql = "SELECT c.id AS courseid, c.fullname, c.shortname,
                       cs.id AS settingsid, cs.saipa_enabled, cs.chat_enabled, cs.risk_enabled,
                       cs.alerts_enabled, cs.rag_enabled,
                       ci.status AS index_status, ci.last_indexed
                  FROM {course} c
             LEFT JOIN {local_saipa_course_settings} cs ON cs.courseid = c.id
             LEFT JOIN {local_saipa_course_index} ci ON ci.courseid = c.id
                 WHERE EXISTS (SELECT 1 FROM {local_saipa_sessions} s WHERE s.courseid = c.id)
              ORDER BY c.fullname";
        $rs = $DB->get_recordset_sql($sql);

        $result = [];
        foreach ($rs as $row) {
            $hassettings = !empty($row->settingsid);
            $result[] = [
                'courseid'       => (int) $row->courseid,
                'coursename'     => $row->fullname,
                'shortname'      => $row->shortname,
                'saipa_enabled'  => $hassettings ? (bool) $row->saipa_enabled : true,
                'chat_enabled'   => $hassettings ? (bool) $row->chat_enabled : true,
                'risk_enabled'   => $hassettings ? (bool) $row->risk_enabled : true,
                'alerts_enabled' => $hassettings ? (bool) $row->alerts_enabled : true,
                'rag_enabled'    => $hassettings ? (bool) $row->rag_enabled : true,
                'index_status'   => $row->index_status ?? 'pending',
                'last_indexed'   => (int) ($row->last_indexed ?? 0),
            ];
        }
        $rs->close();

        return ['courses' => $result];
    }

    /**
     * Define the return structure for this web service.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courses' => new external_multiple_structure(
                new external_single_structure([
                    'courseid'       => new external_value(PARAM_INT, 'Course ID'),
                    'coursename'     => new external_value(PARAM_TEXT, 'Course full name'),
                    'shortname'      => new external_value(PARAM_TEXT, 'Course short name'),
                    'saipa_enabled'  => new external_value(PARAM_BOOL, 'SAIPA enabled for course'),
                    'chat_enabled'   => new external_value(PARAM_BOOL, 'Chat widget enabled'),
                    'risk_enabled'   => new external_value(PARAM_BOOL, 'Risk evaluation enabled'),
                    'alerts_enabled' => new external_value(PARAM_BOOL, 'Alerts enabled'),
                    'rag_enabled'    => new external_value(PARAM_BOOL, 'RAG enabled'),
                    'index_status'   => new external_value(PARAM_TEXT, 'pending|indexing|ready|error'),
                    'last_indexed'   => new external_value(PARAM_INT, 'Unix timestamp of last index'),
                ])
            ),
        ]);
    }
}
