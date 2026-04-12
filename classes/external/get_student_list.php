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
 * External web service: get_student_list
 * Returns enrolled students with chat activity for a course (teacher only).
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
 * Get_student_list.
 */
class get_student_list extends external_api {
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
        global $DB;

        $params  = self::validate_parameters(self::execute_parameters(), ['course_id' => $courseid]);
        $context = \context_course::instance($params['course_id']);
        self::validate_context($context);
        require_capability('local/saipa:view', $context);

        // Get all enrolled users who can chat (students + teachers).
        $allchatters = get_enrolled_users($context, 'local/saipa:chat', 0, 'u.id, u.firstname, u.lastname, u.email');
        // Exclude those with :view (teachers).
        $teacherids = array_keys(get_enrolled_users($context, 'local/saipa:view', 0, 'u.id'));
        $students = array_filter($allchatters, fn($u) => !in_array($u->id, $teacherids));

        if (empty($students)) {
            return ['students' => []];
        }

        $studentids = array_keys($students);
        [$insql, $inparams] = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED, 'uid');

        $sql = "SELECT s.userid,
                       COUNT(m.id)        AS message_count,
                       MAX(m.timecreated) AS last_message,
                       s.id               AS session_id
                  FROM {saipa_sessions} s
             LEFT JOIN {saipa_messages} m ON m.sessionid = s.id AND m.role = 'user'
                 WHERE s.courseid = :courseid
                   AND s.userid $insql
              GROUP BY s.userid, s.id";

        $rows = $DB->get_records_sql($sql, array_merge(['courseid' => $params['course_id']], $inparams));

        $activity = [];
        foreach ($rows as $row) {
            $activity[$row->userid] = $row;
        }

        // Fetch Telegram link status for all students in one query.
        $tglinks = [];
        if (!empty($studentids)) {
            $tgrows = $DB->get_records_select(
                'saipa_telegram_links',
                "userid $insql AND confirmed = 1",
                $inparams,
                '',
                'userid,telegram_id'
            );
            foreach ($tgrows as $tg) {
                $tglinks[$tg->userid] = (int) $tg->telegram_id;
            }
        }

        // Fetch latest alert engagement per student (last 30 days).
        $alertdata = [];
        $since30d  = time() - (30 * DAYSECS);
        if (!empty($studentids)) {
            // Get most recent teacher_alert per student.
            $alertrows = $DB->get_records_select(
                'saipa_notifications',
                "userid $insql AND template = 'teacher_alert' AND timesent >= :since",
                array_merge($inparams, ['since' => $since30d]),
                'timesent DESC',
                'userid,timesent,responded_at,status,payload'
            );
            foreach ($alertrows as $row) {
                // Keep only the most recent per user (results ordered DESC).
                if (!isset($alertdata[$row->userid])) {
                    $payload = json_decode($row->payload ?? '{}', true);
                    $alertdata[$row->userid] = [
                        'last_alert_sent'          => (int) $row->timesent,
                        'alert_responded'          => ($row->status === 'responded'),
                        'alert_response_delay_min' => (int) ($payload['delay_minutes'] ?? 0),
                        'moodle_accessed_after_alert' => !empty($payload['moodle_accessed']),
                    ];
                }
            }
        }

        $result = [];
        foreach ($students as $u) {
            $act   = $activity[$u->id] ?? null;
            $alert = $alertdata[$u->id] ?? null;
            $result[] = [
                'userid'                      => (int) $u->id,
                'fullname'                    => fullname($u),
                'email'                       => $u->email,
                'message_count'               => $act ? (int) $act->message_count : 0,
                'last_message'                => $act ? (int) $act->last_message : 0,
                'has_session'                 => $act !== null,
                'telegram_linked'             => isset($tglinks[$u->id]),
                'last_alert_sent'             => $alert ? $alert['last_alert_sent'] : 0,
                'alert_responded'             => $alert ? $alert['alert_responded'] : false,
                'alert_response_delay_min'    => $alert ? $alert['alert_response_delay_min'] : 0,
                'moodle_accessed_after_alert' => $alert ? $alert['moodle_accessed_after_alert'] : false,
            ];
        }

        usort($result, function ($a, $b) {
            if ($b['last_message'] !== $a['last_message']) {
                return $b['last_message'] <=> $a['last_message'];
            }
            return strcmp($a['fullname'], $b['fullname']);
        });

        return ['students' => $result];
    }

    /**
     * Define the return structure for this web service.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'students' => new external_multiple_structure(
                new external_single_structure([
                    'userid'        => new external_value(PARAM_INT, 'Student user ID'),
                    'fullname'      => new external_value(PARAM_TEXT, 'Full name'),
                    'email'         => new external_value(PARAM_TEXT, 'Email address'),
                    'message_count' => new external_value(PARAM_INT, 'Number of messages sent by student'),
                    'last_message'  => new external_value(PARAM_INT, 'Timestamp of last message (0 if none)'),
                    'has_session'                 => new external_value(PARAM_BOOL, 'Whether student has started a chat'),
                    'telegram_linked'             => new external_value(PARAM_BOOL, 'Whether student has a confirmed Telegram account linked'),
                    'last_alert_sent'             => new external_value(PARAM_INT, 'Unix timestamp of last teacher alert sent (0 if none in last 30 days)'),
                    'alert_responded'             => new external_value(PARAM_BOOL, 'True if student replied to bot after last alert'),
                    'alert_response_delay_min'    => new external_value(PARAM_INT, 'Minutes between alert && first student response (0 if not yet responded)'),
                    'moodle_accessed_after_alert' => new external_value(PARAM_BOOL, 'True if student accessed Moodle after the last alert'),
                ])
            ),
        ]);
    }
}
