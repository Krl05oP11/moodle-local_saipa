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
 * External web service: get_engagement_stats
 * Returns engagement metrics: per-course usage, hourly heatmap, session depth.
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

class get_engagement_stats extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'period' => new external_value(PARAM_ALPHANUMEXT, 'Period: 7d, 30d, semester, all', VALUE_DEFAULT, '30d'),
        ]);
    }

    public static function execute(string $period = '30d'): array {
        global $DB;

        $params  = self::validate_parameters(self::execute_parameters(), ['period' => $period]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/saipa:advisor', $context);

        $since = self::period_to_since($params['period']);

        // ── Per-course usage table ─────────────────────────────────────────────
        $course_rows = $DB->get_records_sql(
            'SELECT s.courseid,
                    COUNT(m.id) AS msg_count,
                    COUNT(DISTINCT s.userid) AS unique_users,
                    COUNT(DISTINCT s.id) AS session_count
               FROM {saipa_messages} m
               JOIN {saipa_sessions} s ON s.id = m.sessionid
              WHERE m.timecreated >= :since
              GROUP BY s.courseid',
            ['since' => $since]
        );

        $course_usage = [];
        foreach ($course_rows as $row) {
            $cid = (int) $row->courseid;
            $course = $DB->get_record('course', ['id' => $cid], 'id, fullname');

            // Feedback ratio for this course.
            $fb = $DB->get_record_sql(
                'SELECT SUM(CASE WHEN f.rating > 0 THEN 1 ELSE 0 END) AS pos,
                        SUM(CASE WHEN f.rating < 0 THEN 1 ELSE 0 END) AS neg
                   FROM {saipa_feedback} f
                   JOIN {saipa_messages} m ON m.id = f.messageid
                   JOIN {saipa_sessions} s ON s.id = m.sessionid
                  WHERE s.courseid = :cid AND f.timecreated >= :since',
                ['cid' => $cid, 'since' => $since]
            );
            $pos = (int) ($fb->pos ?? 0);
            $neg = (int) ($fb->neg ?? 0);
            $fb_ratio = ($pos + $neg) > 0 ? round($pos / ($pos + $neg), 4) : -1.0;

            $sessions   = max(1, (int) $row->session_count);
            $avg_depth  = round((int) $row->msg_count / $sessions, 2);

            $course_usage[] = [
                'courseid'          => $cid,
                'coursename'        => $course ? $course->fullname : "Course {$cid}",
                'message_count'     => (int) $row->msg_count,
                'unique_users'      => (int) $row->unique_users,
                'avg_session_msgs'  => $avg_depth,
                'feedback_ratio'    => $fb_ratio,
            ];
        }

        // ── Hourly heatmap (7 days of week × 24 hours) ────────────────────────
        // Returns 168 cells: {hour: 0-23, day_of_week: 0-6, count: N}.
        // day_of_week: 0=Monday … 6=Sunday (ISO standard).
        $heatmap_rows = $DB->get_records_sql(
            "SELECT
               EXTRACT(HOUR FROM TO_TIMESTAMP(m.timecreated)) AS hour,
               EXTRACT(ISODOW FROM TO_TIMESTAMP(m.timecreated)) - 1 AS dow,
               COUNT(*) AS cnt
             FROM {saipa_messages} m
            WHERE m.timecreated >= :since AND m.role = :role
            GROUP BY hour, dow
            ORDER BY dow, hour",
            ['since' => $since, 'role' => 'user']
        );

        $hourly_heatmap = [];
        foreach ($heatmap_rows as $row) {
            $hourly_heatmap[] = [
                'hour'        => (int) $row->hour,
                'day_of_week' => (int) $row->dow,
                'count'       => (int) $row->cnt,
            ];
        }

        // ── Session depth histogram ────────────────────────────────────────────
        // Buckets: 1-3, 4-10, 11+
        $depth_rows = $DB->get_records_sql(
            'SELECT s.id, COUNT(m.id) AS msg_count
               FROM {saipa_sessions} s
               JOIN {saipa_messages} m ON m.sessionid = s.id
              WHERE m.timecreated >= :since
              GROUP BY s.id',
            ['since' => $since]
        );

        $bucket_shallow  = 0; // 1-3
        $bucket_medium   = 0; // 4-10
        $bucket_deep     = 0; // 11+
        foreach ($depth_rows as $row) {
            $n = (int) $row->msg_count;
            if ($n <= 3) {
                $bucket_shallow++;
            } else if ($n <= 10) {
                $bucket_medium++;
            } else {
                $bucket_deep++;
            }
        }

        $session_depth = [
            ['bucket' => '1-3',  'count' => $bucket_shallow],
            ['bucket' => '4-10', 'count' => $bucket_medium],
            ['bucket' => '11+',  'count' => $bucket_deep],
        ];

        return [
            'course_usage'    => $course_usage,
            'hourly_heatmap'  => $hourly_heatmap,
            'session_depth'   => $session_depth,
        ];
    }

    private static function period_to_since(string $period): int {
        $now = time();
        switch ($period) {
            case '7d':       return $now - (7 * 86400);
            case 'semester': return $now - (120 * 86400);
            case 'all':      return 0;
            default:         return $now - (30 * 86400);
        }
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'course_usage' => new external_multiple_structure(
                new external_single_structure([
                    'courseid'         => new external_value(PARAM_INT,   'Course ID'),
                    'coursename'       => new external_value(PARAM_TEXT,  'Course full name'),
                    'message_count'    => new external_value(PARAM_INT,   'Total messages in period'),
                    'unique_users'     => new external_value(PARAM_INT,   'Unique users who chatted'),
                    'avg_session_msgs' => new external_value(PARAM_FLOAT, 'Average messages per session'),
                    'feedback_ratio'   => new external_value(PARAM_FLOAT, 'Positive feedback ratio; -1 if none'),
                ])
            ),
            'hourly_heatmap' => new external_multiple_structure(
                new external_single_structure([
                    'hour'        => new external_value(PARAM_INT, 'Hour 0-23'),
                    'day_of_week' => new external_value(PARAM_INT, 'Day 0=Mon … 6=Sun'),
                    'count'       => new external_value(PARAM_INT, 'Message count'),
                ])
            ),
            'session_depth' => new external_multiple_structure(
                new external_single_structure([
                    'bucket' => new external_value(PARAM_TEXT, '1-3, 4-10, or 11+'),
                    'count'  => new external_value(PARAM_INT,  'Number of sessions in this bucket'),
                ])
            ),
        ]);
    }
}
