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
 * with hundreds of courses && thousands of messages.
 *
 * Enable via Site admin → Server → Scheduled tasks → SAIPA Aggregate Daily Stats.
 *
 * @package    local_saipa
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_saipa\task;


/**
 * Aggregate_daily_stats.
 */
class aggregate_daily_stats extends \core\task\scheduled_task {
    /**
     * Get name.
     */
    public function get_name(): string {
        return get_string('pluginname', 'local_saipa') . ' — Aggregate Daily Stats';
    }

    /**
     * Execute the web service.
     */
    public function execute(): void {
        global $DB;

        $start = time();
        mtrace('SAIPA: Starting daily stats aggregation…');

        // Aggregate for yesterday (midnight → midnight UTC).
        $yesterdaystart = mktime(0, 0, 0, (int) date('n'), (int) date('j') - 1);
        $yesterdayend   = $yesterdaystart + 86400;

        $courseids = $DB->get_fieldset_sql('SELECT DISTINCT courseid FROM {saipa_sessions}');

        if (empty($courseids)) {
            mtrace('SAIPA: No active courses, nothing to aggregate.');
            return;
        }

        $processed = 0;
        foreach ($courseids as $cid) {
            try {
                $this->aggregate_course((int) $cid, $yesterdaystart, $yesterdayend);
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
     * Aggregates one course's data for the given day window && upserts into saipa_daily_stats.
     *
     * @param int $cid            Course ID.
     * @param int $daystart      Unix timestamp of day start (midnight UTC).
     * @param int $dayend        Unix timestamp of day end (midnight UTC + 86400).
     */
    private function aggregate_course(int $cid, int $daystart, int $dayend): void {
        global $DB;

        // ── Active users: distinct users who sent at least 1 message ──────────
        $activeusers = (int) $DB->count_records_sql(
            'SELECT COUNT(DISTINCT m.sessionid) FROM {saipa_messages} m
               JOIN {saipa_sessions} s ON s.id = m.sessionid
              WHERE s.courseid = :cid AND m.role = :role
                AND m.timecreated >= :ts AND m.timecreated < :te',
            ['cid' => $cid, 'role' => 'user', 'ts' => $daystart, 'te' => $dayend]
        );
        // Actually count distinct userids for active_users.
        $activeusers = (int) $DB->count_records_sql(
            'SELECT COUNT(DISTINCT s.userid) FROM {saipa_messages} m
               JOIN {saipa_sessions} s ON s.id = m.sessionid
              WHERE s.courseid = :cid AND m.role = :role
                AND m.timecreated >= :ts AND m.timecreated < :te',
            ['cid' => $cid, 'role' => 'user', 'ts' => $daystart, 'te' => $dayend]
        );

        // ── New sessions created yesterday ────────────────────────────────────
        $newsessions = (int) $DB->count_records_select(
            'saipa_sessions',
            'courseid = :cid AND timecreated >= :ts AND timecreated < :te',
            ['cid' => $cid, 'ts' => $daystart, 'te' => $dayend]
        );

        // ── Message counts ────────────────────────────────────────────────────
        $msgcounts = $DB->get_record_sql(
            'SELECT
               COUNT(*) AS total,
               SUM(CASE WHEN m.role = :user THEN 1 ELSE 0 END) AS user_msgs,
               SUM(CASE WHEN m.role = :assistant THEN 1 ELSE 0 END) AS asst_msgs
             FROM {saipa_messages} m
               JOIN {saipa_sessions} s ON s.id = m.sessionid
             WHERE s.courseid = :cid AND m.timecreated >= :ts AND m.timecreated < :te',
            ['cid' => $cid, 'user' => 'user', 'assistant' => 'assistant',
             'ts' => $daystart, 'te' => $dayend]
        );
        $totalmessages     = (int) ($msgcounts->total ?? 0);
        $usermessages      = (int) ($msgcounts->user_msgs ?? 0);
        $assistantmessages = (int) ($msgcounts->asst_msgs ?? 0);

        // ── Feedback ─────────────────────────────────────────────────────────
        $fbcounts = $DB->get_record_sql(
            'SELECT
               SUM(CASE WHEN f.rating > 0 THEN 1 ELSE 0 END) AS pos,
               SUM(CASE WHEN f.rating < 0 THEN 1 ELSE 0 END) AS neg
             FROM {saipa_feedback} f
               JOIN {saipa_messages} m ON m.id = f.messageid
               JOIN {saipa_sessions} s ON s.id = m.sessionid
             WHERE s.courseid = :cid AND f.timecreated >= :ts AND f.timecreated < :te',
            ['cid' => $cid, 'ts' => $daystart, 'te' => $dayend]
        );
        $positivefeedback = (int) ($fbcounts->pos ?? 0);
        $negativefeedback = (int) ($fbcounts->neg ?? 0);

        // ── Alerts sent && responded ─────────────────────────────────────────
        $alertcounts = $DB->get_record_sql(
            'SELECT
               COUNT(*) AS sent,
               SUM(CASE WHEN n.responded_at > 0 THEN 1 ELSE 0 END) AS responded
             FROM {saipa_notifications} n
             WHERE n.userid IN (
               SELECT DISTINCT userid FROM {saipa_sessions} WHERE courseid = :cid
             )
             AND n.timesent >= :ts AND n.timesent < :te',
            ['cid' => $cid, 'ts' => $daystart, 'te' => $dayend]
        );
        $alertssent      = (int) ($alertcounts->sent ?? 0);
        $alertsresponded = (int) ($alertcounts->responded ?? 0);

        // ── Risk level snapshot (most recent per student as of day_end) ───────
        $risksnap = $DB->get_records_sql(
            'SELECT risk_level, COUNT(*) AS cnt
               FROM {saipa_risk_scores}
              WHERE courseid = :cid
              GROUP BY risk_level',
            ['cid' => $cid]
        );
        $studentshighrisk   = 0;
        $studentsmediumrisk = 0;
        $studentslowrisk    = 0;
        foreach ($risksnap as $row) {
            if ($row->risk_level === 'high') {
                $studentshighrisk = (int) $row->cnt;
            } else if ($row->risk_level === 'medium') {
                $studentsmediumrisk = (int) $row->cnt;
            } else if ($row->risk_level === 'low') {
                $studentslowrisk = (int) $row->cnt;
            }
        }

        // ── Avg alert response delay (in minutes) ─────────────────────────────
        $delayrow = $DB->get_record_sql(
            'SELECT AVG((n.responded_at - n.timesent) / 60.0) AS avg_delay
               FROM {saipa_notifications} n
              WHERE n.userid IN (
                SELECT DISTINCT userid FROM {saipa_sessions} WHERE courseid = :cid
              )
              AND n.timesent >= :ts AND n.timesent < :te
              AND n.responded_at > 0',
            ['cid' => $cid, 'ts' => $daystart, 'te' => $dayend]
        );
        $avgresponsedelaymin = round((float) ($delayrow->avg_delay ?? 0), 2);

        // ── Upsert into saipa_daily_stats ─────────────────────────────────────
        $existing = $DB->get_record(
            'saipa_daily_stats',
            ['courseid' => $cid, 'stat_date' => $daystart]
        );

        $record = (object) [
            'courseid'               => $cid,
            'stat_date'              => $daystart,
            'active_users'           => $activeusers,
            'new_sessions'           => $newsessions,
            'total_messages'         => $totalmessages,
            'user_messages'          => $usermessages,
            'assistant_messages'     => $assistantmessages,
            'positive_feedback'      => $positivefeedback,
            'negative_feedback'      => $negativefeedback,
            'alerts_sent'            => $alertssent,
            'alerts_responded'       => $alertsresponded,
            'students_high_risk'     => $studentshighrisk,
            'students_medium_risk'   => $studentsmediumrisk,
            'students_low_risk'      => $studentslowrisk,
            'avg_response_delay_min' => $avgresponsedelaymin,
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
            $cid,
            $activeusers,
            $newsessions,
            $totalmessages,
            $alertsresponded,
            $alertssent,
            $studentshighrisk,
            $studentsmediumrisk,
            $studentslowrisk
        ));
    }
}
