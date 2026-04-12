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
 * External web service: get_course_summary
 * Returns per-course metrics for the teacher dashboard metrics bar.
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
 * Get_course_summary.
 */
class get_course_summary extends external_api {
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
        global $CFG;
        require_once($CFG->dirroot . '/local/saipa/lib.php');
        global $DB;

        $params  = self::validate_parameters(self::execute_parameters(), ['course_id' => $courseid]);
        $cid     = $params['course_id'];
        $context = \context_course::instance($cid);
        self::validate_context($context);
        require_capability('local/saipa:view', $context);

        // ── Enrolled students ────────────────────────────────────────────────
        $allchatters = get_enrolled_users($context, 'local/saipa:chat', 0, 'u.id');
        $teacherids  = array_keys(get_enrolled_users($context, 'local/saipa:view', 0, 'u.id'));
        $students     = array_filter($allchatters, fn($u) => !in_array($u->id, $teacherids));
        $enrolled     = count($students);

        // ── Active SAIPA users (at least 1 message sent) ─────────────────────
        $activeusers = (int) $DB->count_records_sql(
            'SELECT COUNT(DISTINCT s.userid)
               FROM {saipa_messages} m
               JOIN {saipa_sessions} s ON s.id = m.sessionid
              WHERE s.courseid = :cid AND m.role = :role',
            ['cid' => $cid, 'role' => 'user']
        );
        $adoptionrate = $enrolled > 0 ? round($activeusers / $enrolled, 4) : 0.0;

        // ── Message counts ────────────────────────────────────────────────────
        $msgcounts = $DB->get_record_sql(
            'SELECT COUNT(*) AS total,
                    SUM(CASE WHEN m.role = :user THEN 1 ELSE 0 END) AS user_msgs,
                    SUM(CASE WHEN m.role = :assistant THEN 1 ELSE 0 END) AS asst_msgs
               FROM {saipa_messages} m
               JOIN {saipa_sessions} s ON s.id = m.sessionid
              WHERE s.courseid = :cid',
            ['cid' => $cid, 'user' => 'user', 'assistant' => 'assistant']
        );
        $totalmessages     = (int) ($msgcounts->total ?? 0);
        $usermessages      = (int) ($msgcounts->user_msgs ?? 0);
        $assistantmessages = (int) ($msgcounts->asst_msgs ?? 0);

        // ── Feedback ─────────────────────────────────────────────────────────
        $fb = $DB->get_record_sql(
            'SELECT SUM(CASE WHEN f.rating > 0 THEN 1 ELSE 0 END) AS pos,
                    SUM(CASE WHEN f.rating < 0 THEN 1 ELSE 0 END) AS neg
               FROM {saipa_feedback} f
               JOIN {saipa_messages} m ON m.id = f.messageid
               JOIN {saipa_sessions} s ON s.id = m.sessionid
              WHERE s.courseid = :cid',
            ['cid' => $cid]
        );
        $positivefeedback = (int) ($fb->pos ?? 0);
        $negativefeedback = (int) ($fb->neg ?? 0);
        $fbtotal          = $positivefeedback + $negativefeedback;
        $feedbackratio    = $fbtotal > 0 ? round($positivefeedback / $fbtotal, 4) : -1.0;

        // ── Risk scores ───────────────────────────────────────────────────────
        $risk = $DB->get_record_sql(
            'SELECT SUM(CASE WHEN risk_level = :h THEN 1 ELSE 0 END) AS high_cnt,
                    SUM(CASE WHEN risk_level = :m THEN 1 ELSE 0 END) AS med_cnt,
                    SUM(CASE WHEN risk_level = :l THEN 1 ELSE 0 END) AS low_cnt,
                    MAX(timecomputed) AS last_computed
               FROM {saipa_risk_scores}
              WHERE courseid = :cid',
            ['cid' => $cid, 'h' => 'high', 'm' => 'medium', 'l' => 'low']
        );
        $highriskcount   = (int) ($risk->high_cnt ?? 0);
        $mediumriskcount = (int) ($risk->med_cnt ?? 0);
        $lowriskcount    = (int) ($risk->low_cnt ?? 0);
        $lastriskcomputed = (int) ($risk->last_computed ?? 0);

        // ── Course index status ───────────────────────────────────────────────
        $idx = $DB->get_record('saipa_course_index', ['courseid' => $cid]);
        $indexstatus  = $idx ? $idx->status : 'pending';
        $lastindexed  = $idx ? (int) $idx->last_indexed : 0;
        $chunkcount   = $idx ? (int) $idx->chunk_count : 0;

        // ── Alerts last 30 days ───────────────────────────────────────────────
        $since30d = time() - (30 * 86400);
        $alerts = $DB->get_record_sql(
            'SELECT COUNT(*) AS sent,
                    SUM(CASE WHEN n.responded_at > 0 THEN 1 ELSE 0 END) AS responded
               FROM {saipa_notifications} n
              WHERE n.userid IN (
                SELECT DISTINCT userid FROM {saipa_sessions} WHERE courseid = :cid
              )
              AND n.timesent >= :since',
            ['cid' => $cid, 'since' => $since30d]
        );
        $alertssent30d      = (int) ($alerts->sent ?? 0);
        $alertsresponded30d = (int) ($alerts->responded ?? 0);
        $alertresponserate  = $alertssent30d > 0
            ? round($alertsresponded30d / $alertssent30d, 4) : 0.0;

        return [
            'enrolled_students'     => $enrolled,
            'active_saipa_users'    => $activeusers,
            'adoption_rate'         => $adoptionrate,
            'total_messages'        => $totalmessages,
            'user_messages'         => $usermessages,
            'assistant_messages'    => $assistantmessages,
            'positive_feedback'     => $positivefeedback,
            'negative_feedback'     => $negativefeedback,
            'feedback_ratio'        => $feedbackratio,
            'high_risk_count'       => $highriskcount,
            'medium_risk_count'     => $mediumriskcount,
            'low_risk_count'        => $lowriskcount,
            'last_risk_computed'    => $lastriskcomputed,
            'index_status'          => $indexstatus,
            'last_indexed'          => $lastindexed,
            'chunk_count'           => $chunkcount,
            'alerts_sent_30d'       => $alertssent30d,
            'alerts_responded_30d'  => $alertsresponded30d,
            'alert_response_rate'   => $alertresponserate,
        ];
    }

    /**
     * Define the return structure for this web service.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'enrolled_students'     => new external_value(PARAM_INT, 'Total enrolled students'),
            'active_saipa_users'    => new external_value(PARAM_INT, 'Students who sent at least 1 message'),
            'adoption_rate'         => new external_value(PARAM_FLOAT, 'active/enrolled ratio (0-1)'),
            'total_messages'        => new external_value(PARAM_INT, 'All messages ever in this course'),
            'user_messages'         => new external_value(PARAM_INT, 'Messages sent by students'),
            'assistant_messages'    => new external_value(PARAM_INT, 'Messages sent by assistant'),
            'positive_feedback'     => new external_value(PARAM_INT, 'Thumbs-up count'),
            'negative_feedback'     => new external_value(PARAM_INT, 'Thumbs-down count'),
            'feedback_ratio'        => new external_value(PARAM_FLOAT, 'positive/(pos+neg); -1 if no feedback'),
            'high_risk_count'       => new external_value(PARAM_INT, 'Students at high risk'),
            'medium_risk_count'     => new external_value(PARAM_INT, 'Students at medium risk'),
            'low_risk_count'        => new external_value(PARAM_INT, 'Students at low risk'),
            'last_risk_computed'    => new external_value(PARAM_INT, 'Unix timestamp of last risk evaluation'),
            'index_status'          => new external_value(PARAM_TEXT, 'pending|indexing|ready|error'),
            'last_indexed'          => new external_value(PARAM_INT, 'Unix timestamp of last index'),
            'chunk_count'           => new external_value(PARAM_INT, 'Number of indexed chunks'),
            'alerts_sent_30d'       => new external_value(PARAM_INT, 'Alerts sent in last 30 days'),
            'alerts_responded_30d'  => new external_value(PARAM_INT, 'Alerts responded in last 30 days'),
            'alert_response_rate'   => new external_value(PARAM_FLOAT, 'responded/sent ratio (0-1)'),
        ]);
    }
}
