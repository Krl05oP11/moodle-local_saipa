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
 * External web service: get_my_courses
 * Returns summary metrics for all courses taught by the current user (or all
 * courses if user has local/saipa:manage at system context).
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
 * Get_my_courses.
 */
class get_my_courses extends external_api {
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
        global $DB, $USER;

        $syscontext = \context_system::instance();
        self::validate_context($syscontext);
        require_capability('local/saipa:viewall', $syscontext);

        $isadmin = has_capability('local/saipa:manage', $syscontext);

        // Get courses to display: all SAIPA-active courses (admin) or only user's teaching courses.
        if ($isadmin) {
            $sql = 'SELECT DISTINCT c.id, c.fullname, c.shortname
                      FROM {course} c
                      JOIN {saipa_sessions} s ON s.courseid = c.id
                     ORDER BY c.fullname';
            $courses = $DB->get_records_sql($sql);
        } else {
            // Courses where $USER has local/saipa:view capability.
            $courses = [];
            $enrolled = enrol_get_users_courses($USER->id, true, ['id', 'fullname', 'shortname']);
            foreach ($enrolled as $c) {
                $ctx = \context_course::instance($c->id);
                if (has_capability('local/saipa:view', $ctx)) {
                    $courses[$c->id] = $c;
                }
            }
        }

        // Prefetch settings && index rows.
        $settingsmap = [];
        foreach ($DB->get_records('saipa_course_settings') as $row) {
            $settingsmap[(int) $row->courseid] = $row;
        }
        $indexmap = [];
        foreach ($DB->get_records('saipa_course_index') as $row) {
            $indexmap[(int) $row->courseid] = $row;
        }

        $result = [];
        foreach ($courses as $course) {
            $cid = (int) $course->id;
            $ctx = \context_course::instance($cid);

            // Enrolled students.
            $allchatters = get_enrolled_users($ctx, 'local/saipa:chat', 0, 'u.id');
            $teacherids  = array_keys(get_enrolled_users($ctx, 'local/saipa:view', 0, 'u.id'));
            $students     = array_filter($allchatters, fn($u) => !in_array($u->id, $teacherids));
            $enrolledcnt = count($students);

            // Active SAIPA users.
            $active = (int) $DB->count_records_sql(
                'SELECT COUNT(DISTINCT s.userid)
                   FROM {saipa_messages} m
                   JOIN {saipa_sessions} s ON s.id = m.sessionid
                  WHERE s.courseid = :cid AND m.role = :role',
                ['cid' => $cid, 'role' => 'user']
            );
            $adoption = $enrolledcnt > 0 ? round($active / $enrolledcnt, 4) : 0.0;

            // Messages last 7 days.
            $since7d    = time() - (7 * 86400);
            $msgs7d    = (int) $DB->count_records_sql(
                'SELECT COUNT(*)
                   FROM {saipa_messages} m
                   JOIN {saipa_sessions} s ON s.id = m.sessionid
                  WHERE s.courseid = :cid AND m.timecreated >= :since',
                ['cid' => $cid, 'since' => $since7d]
            );

            // Risk distribution.
            $risk = $DB->get_record_sql(
                'SELECT SUM(CASE WHEN risk_level = :h THEN 1 ELSE 0 END) AS hi,
                        SUM(CASE WHEN risk_level = :m THEN 1 ELSE 0 END) AS me,
                        SUM(CASE WHEN risk_level = :l THEN 1 ELSE 0 END) AS lo
                   FROM {saipa_risk_scores}
                  WHERE courseid = :cid',
                ['cid' => $cid, 'h' => 'high', 'm' => 'medium', 'l' => 'low']
            );

            $s   = $settingsmap[$cid] ?? null;
            $idx = $indexmap[$cid] ?? null;

            $result[] = [
                'courseid'        => $cid,
                'coursename'      => $course->fullname,
                'shortname'       => $course->shortname,
                'enrolled_students' => $enrolledcnt,
                'active_saipa_users' => $active,
                'adoption_rate'   => $adoption,
                'messages_7d'     => $msgs7d,
                'high_risk'       => (int) ($risk->hi ?? 0),
                'medium_risk'     => (int) ($risk->me ?? 0),
                'low_risk'        => (int) ($risk->lo ?? 0),
                'index_status'    => $idx ? $idx->status : 'pending',
                'saipa_enabled'   => $s ? (bool) $s->saipa_enabled : true,
            ];
        }

        return ['courses' => $result];
    }

    /**
     * Define the return structure for this web service.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courses' => new external_multiple_structure(
                new external_single_structure([
                    'courseid'          => new external_value(PARAM_INT, 'Course ID'),
                    'coursename'        => new external_value(PARAM_TEXT, 'Full course name'),
                    'shortname'         => new external_value(PARAM_TEXT, 'Short course name'),
                    'enrolled_students' => new external_value(PARAM_INT, 'Total enrolled students'),
                    'active_saipa_users' => new external_value(PARAM_INT, 'Students with at least 1 message'),
                    'adoption_rate'     => new external_value(PARAM_FLOAT, 'active/enrolled ratio'),
                    'messages_7d'       => new external_value(PARAM_INT, 'Messages in last 7 days'),
                    'high_risk'         => new external_value(PARAM_INT, 'Students at high risk'),
                    'medium_risk'       => new external_value(PARAM_INT, 'Students at medium risk'),
                    'low_risk'          => new external_value(PARAM_INT, 'Students at low risk'),
                    'index_status'      => new external_value(PARAM_TEXT, 'RAG index status'),
                    'saipa_enabled'     => new external_value(PARAM_BOOL, 'Whether SAIPA is enabled'),
                ])
            ),
        ]);
    }
}
