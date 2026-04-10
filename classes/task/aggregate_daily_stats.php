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
 * Scheduled task: aggregate yesterday's activity into saipa_daily_stats.
 *
 * Runs at 03:00 daily (after risk_evaluation at 02:00).
 * Produces one row per (courseid, stat_date) in saipa_daily_stats.
 * This pre-aggregation ensures the advisor dashboard loads fast even
 * with hundreds of courses and thousands of messages.
 *
 * Enable via Site admin → Server → Scheduled tasks → SAIPA Aggregate Daily Stats.
 *
 * @package    local_saipa
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_saipa\task;

defined('MOODLE_INTERNAL') || die();

class aggregate_daily_stats extends \core\task\scheduled_task {
    public function get_name(): string {
        return get_string('pluginname', 'local_saipa') . ' — Aggregate Daily Stats';
    }

    public function execute(): void {
        global $DB;

        $start = time();
        mtrace('SAIPA: Starting daily stats aggregation…');

        // Aggregate for yesterday (midnight → midnight UTC).
        $yesterday_start = mktime(0, 0, 0, (int) date('n'), (int) date('j') - 1);
        $yesterday_end   = $yesterday_start + 86400;

        $course_ids = $DB->get_fieldset_sql('SELECT DISTINCT courseid FROM {saipa_sessions}');

        if (empty($course_ids)) {
            mtrace('SAIPA: No active courses, nothing to aggregate.');
            return;
        }

        $processed = 0;
        foreach ($course_ids as $cid) {
            try {
                $this->aggregate_course((int) $cid, $yesterday_start, $yesterday_end);
                $processed++;
            } catch (\Throwable $e) {
                mtrace(sprintf('SAIPA: ERROR aggregating course %d — %s', $cid, $e->getMessage()));
            }
        }

        $elapsed = time() - $start;
        mtrace(sprintf(
            'SAIPA: Daily stats aggregation complete. Courses processed: %d | Time: %ds',
            $processed,
            $elapsed
        ));
    }

    /**
     * Aggregates one course's data for the given day window and upserts into saipa_daily_stats.
     *
     * @param int $cid            Course ID.
     * @param int $day_start      Unix timestamp of day start (midnight UTC).
     * @param int $day_end        Unix timestamp of day end (midnight UTC + 86400).
     */
    private function aggregate_course(int $cid, int $day_start, int $day_end): void {
        global $DB;

        // ── Active users: distinct users who sent at least 1 message ──────────
        $active_users = (int) $DB->count_records_sql(
            'SELECT COUNT(DISTINCT m.sessionid) FROM {saipa_messages} m
               JOIN {saipa_sessions} s ON s.id = m.sessionid
              WHERE s.courseid = :cid AND m.role = :role
                AND m.timecreated >= :ts AND m.timecreated < :te',
            ['cid' => $cid, 'role' => 'user', 'ts' => $day_start, 'te' => $day_end]
        );
        // Actually count distinct userids for active_users.
        $active_users = (int) $DB->count_records_sql(
            'SELECT COUNT(DISTINCT s.userid) FROM {saipa_messages} m
               JOIN {saipa_sessions} s ON s.id = m.sessionid
              WHERE s.courseid = :cid AND m.role = :role
                AND m.timecreated >= :ts AND m.timecreated < :te',
            ['cid' => $cid, 'role' => 'user', 'ts' => $day_start, 'te' => $day_end]
        );

        // ── New sessions created yesterday ────────────────────────────────────
        $new_sessions = (int) $DB->count_records_select(
            'saipa_sessions',
            'courseid = :cid AND timecreated >= :ts AND timecreated < :te',
            ['cid' => $cid, 'ts' => $day_start, 'te' => $day_end]
        );

        // ── Message counts ────────────────────────────────────────────────────
        $msg_counts = $DB->get_record_sql(
            'SELECT
               COUNT(*) AS total,
               SUM(CASE WHEN m.role = :user THEN 1 ELSE 0 END) AS user_msgs,
               SUM(CASE WHEN m.role = :assistant THEN 1 ELSE 0 END) AS asst_msgs
             FROM {saipa_messages} m
               JOIN {saipa_sessions} s ON s.id = m.sessionid
             WHERE s.courseid = :cid AND m.timecreated >= :ts AND m.timecreated < :te',
            ['cid' => $cid, 'user' => 'user', 'assistant' => 'assistant',
             'ts' => $day_start, 'te' => $day_end]
        );
        $total_messages     = (int) ($msg_counts->total ?? 0);
        $user_messages      = (int) ($msg_counts->user_msgs ?? 0);
        $assistant_messages = (int) ($msg_counts->asst_msgs ?? 0);

        // ── Feedback ─────────────────────────────────────────────────────────
        $fb_counts = $DB->get_record_sql(
            'SELECT
               SUM(CASE WHEN f.rating > 0 THEN 1 ELSE 0 END) AS pos,
               SUM(CASE WHEN f.rating < 0 THEN 1 ELSE 0 END) AS neg
             FROM {saipa_feedback} f
               JOIN {saipa_messages} m ON m.id = f.messageid
               JOIN {saipa_sessions} s ON s.id = m.sessionid
             WHERE s.courseid = :cid AND f.timecreated >= :ts AND f.timecreated < :te',
            ['cid' => $cid, 'ts' => $day_start, 'te' => $day_end]
        );
        $positive_feedback = (int) ($fb_counts->pos ?? 0);
        $negative_feedback = (int) ($fb_counts->neg ?? 0);

        // ── Alerts sent and responded ─────────────────────────────────────────
        $alert_counts = $DB->get_record_sql(
            'SELECT
               COUNT(*) AS sent,
               SUM(CASE WHEN n.responded_at > 0 THEN 1 ELSE 0 END) AS responded
             FROM {saipa_notifications} n
             WHERE n.userid IN (
               SELECT DISTINCT userid FROM {saipa_sessions} WHERE courseid = :cid
             )
             AND n.timesent >= :ts AND n.timesent < :te',
            ['cid' => $cid, 'ts' => $day_start, 'te' => $day_end]
        );
        $alerts_sent      = (int) ($alert_counts->sent ?? 0);
        $alerts_responded = (int) ($alert_counts->responded ?? 0);

        // ── Risk level snapshot (most recent per student as of day_end) ───────
        $risk_snap = $DB->get_records_sql(
            'SELECT risk_level, COUNT(*) AS cnt
               FROM {saipa_risk_scores}
              WHERE courseid = :cid
              GROUP BY risk_level',
            ['cid' => $cid]
        );
        $students_high_risk   = 0;
        $students_medium_risk = 0;
        $students_low_risk    = 0;
        foreach ($risk_snap as $row) {
            if ($row->risk_level === 'high') {
                $students_high_risk = (int) $row->cnt;
            } else if ($row->risk_level === 'medium') {
                $students_medium_risk = (int) $row->cnt;
            } else if ($row->risk_level === 'low') {
                $students_low_risk = (int) $row->cnt;
            }
        }

        // ── Avg alert response delay (in minutes) ─────────────────────────────
        $delay_row = $DB->get_record_sql(
            'SELECT AVG((n.responded_at - n.timesent) / 60.0) AS avg_delay
               FROM {saipa_notifications} n
              WHERE n.userid IN (
                SELECT DISTINCT userid FROM {saipa_sessions} WHERE courseid = :cid
              )
              AND n.timesent >= :ts AND n.timesent < :te
              AND n.responded_at > 0',
            ['cid' => $cid, 'ts' => $day_start, 'te' => $day_end]
        );
        $avg_response_delay_min = round((float) ($delay_row->avg_delay ?? 0), 2);

        // ── Upsert into saipa_daily_stats ─────────────────────────────────────
        $existing = $DB->get_record('saipa_daily_stats',
            ['courseid' => $cid, 'stat_date' => $day_start]);

        $record = (object) [
            'courseid'               => $cid,
            'stat_date'              => $day_start,
            'active_users'           => $active_users,
            'new_sessions'           => $new_sessions,
            'total_messages'         => $total_messages,
            'user_messages'          => $user_messages,
            'assistant_messages'     => $assistant_messages,
            'positive_feedback'      => $positive_feedback,
            'negative_feedback'      => $negative_feedback,
            'alerts_sent'            => $alerts_sent,
            'alerts_responded'       => $alerts_responded,
            'students_high_risk'     => $students_high_risk,
            'students_medium_risk'   => $students_medium_risk,
            'students_low_risk'      => $students_low_risk,
            'avg_response_delay_min' => $avg_response_delay_min,
            'timecreated'            => time(),
        ];

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('saipa_daily_stats', $record);
        } else {
            $DB->insert_record('saipa_daily_stats', $record);
        }

        mtrace(sprintf(
            '  Course %d: users=%d sessions=%d msgs=%d alerts=%d/%d high=%d med=%d low=%d',
            $cid, $active_users, $new_sessions, $total_messages,
            $alerts_responded, $alerts_sent,
            $students_high_risk, $students_medium_risk, $students_low_risk
        ));
    }
}
