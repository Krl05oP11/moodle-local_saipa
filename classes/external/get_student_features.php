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
 * External web service: get_student_features
 * Aggregates 11 dropout-risk signals for a student in a course.
 * Called by saipa-engine (via moodle_token) to build feature vectors.
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
 * Get_student_features.
 */
class get_student_features extends external_api {
    /**
     * Define the parameters for this web service.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'user_id'   => new external_value(PARAM_INT, 'Student user ID'),
            'course_id' => new external_value(PARAM_INT, 'Course ID'),
        ]);
    }

    /**
     * Execute the web service.
     */
    public static function execute(int $userid, int $courseid): array {
        global $DB;

        $params  = self::validate_parameters(self::execute_parameters(), [
            'user_id'   => $userid,
            'course_id' => $courseid,
        ]);
        $uid = $params['user_id'];
        $cid = $params['course_id'];

        $context = \context_course::instance($cid);
        self::validate_context($context);
        require_capability('local/saipa:view', $context);

        $now  = time();
        $day  = 86400;

        // ── 1. last_access_days ─────────────────────────────────────────────
        $lastaccess = $DB->get_field(
            'user_lastaccess',
            'timeaccess',
            ['userid' => $uid, 'courseid' => $cid]
        );
        $lastaccessdays = $lastaccess
            ? round(($now - $lastaccess) / $day, 1)
            : 90.0;

        // ── 2. submission_rate ──────────────────────────────────────────────
        $totalassigns = (int) $DB->count_records('assign', ['course' => $cid]);
        if ($totalassigns > 0) {
            $sql = "SELECT COUNT(DISTINCT s.assignment)
                      FROM {assign_submission} s
                      JOIN {assign} a ON a.id = s.assignment
                     WHERE a.course = :cid
                       AND s.userid = :uid
                       AND s.status = 'submitted'";
            $submitted = (int) $DB->get_field_sql($sql, ['cid' => $cid, 'uid' => $uid]);
            $submissionrate = round($submitted / $totalassigns, 4);
        } else {
            $submissionrate = 1.0;   // no assignments → neutral
        }

        // ── 3 & 4. login counts ─────────────────────────────────────────────
        $since7  = $now - 7 * $day;
        $since30 = $now - 30 * $day;

        $logincount7d  = (int) $DB->count_records_select(
            'logstore_standard_log',
            "userid = :uid AND action = 'loggedin' AND timecreated >= :since",
            ['uid' => $uid, 'since' => $since7]
        );
        $logincount30d = (int) $DB->count_records_select(
            'logstore_standard_log',
            "userid = :uid AND action = 'loggedin' AND timecreated >= :since",
            ['uid' => $uid, 'since' => $since30]
        );

        // ── 5 & 6. SAIPA chat activity ──────────────────────────────────────
        $session = $DB->get_record(
            'saipa_sessions',
            ['userid' => $uid, 'courseid' => $cid]
        );

        if ($session) {
            $saipamsgcount = (int) $DB->count_records(
                'saipa_messages',
                ['sessionid' => $session->id, 'role' => 'user']
            );
            $lastchatts    = $DB->get_field_sql(
                "SELECT MAX(timecreated) FROM {saipa_messages}
                  WHERE sessionid = :sid AND role = 'user'",
                ['sid' => $session->id]
            );
            $saipadayssincelastchat = $lastchatts
                ? round(($now - $lastchatts) / $day, 1)
                : 99.0;
        } else {
            $saipamsgcount            = 0;
            $saipadayssincelastchat = 99.0;
        }

        // ── 7 & 8. quiz scores ──────────────────────────────────────────────
        $sql = "SELECT qg.grade, q.grade AS maxgrade
                  FROM {quiz_grades} qg
                  JOIN {quiz} q ON q.id = qg.quiz
                 WHERE q.course = :cid
                   AND qg.userid = :uid";
        $quizrows = $DB->get_records_sql($sql, ['cid' => $cid, 'uid' => $uid]);
        $quizattemptcount = count($quizrows);
        if ($quizattemptcount > 0) {
            $pcts = [];
            foreach ($quizrows as $r) {
                $max    = (float) $r->maxgrade;
                $pcts[] = $max > 0 ? min(100.0, round((float) $r->grade / $max * 100, 2)) : 0;
            }
            $quizavgscore = round(array_sum($pcts) / count($pcts), 2);
        } else {
            $quizavgscore = 50.0;   // neutral default
        }

        // ── 9. forum posts ──────────────────────────────────────────────────
        $sql = "SELECT COUNT(fp.id)
                  FROM {forum_posts} fp
                  JOIN {forum_discussions} fd ON fd.id = fp.discussion
                  JOIN {forum} f ON f.id = fd.forum
                 WHERE f.course = :cid
                   AND fp.userid = :uid";
        $forumpostcount = (int) $DB->get_field_sql($sql, ['cid' => $cid, 'uid' => $uid]);

        // ── 10. positive_feedback_ratio ─────────────────────────────────────
        $totalfb = (int) $DB->count_records('saipa_feedback', ['userid' => $uid]);
        if ($totalfb > 0) {
            $posfb = (int) $DB->count_records(
                'saipa_feedback',
                ['userid' => $uid, 'rating' => 1]
            );
            $positivefeedbackratio = round($posfb / $totalfb, 4);
        } else {
            $positivefeedbackratio = 0.5;   // neutral
        }

        // ── 11. completion_rate ─────────────────────────────────────────────
        $sqltotal = "SELECT COUNT(*) FROM {course_modules}
                       WHERE course = :cid AND completion > 0";
        $totalcompletable = (int) $DB->get_field_sql($sqltotal, ['cid' => $cid]);

        if ($totalcompletable > 0) {
            $sqldone = "SELECT COUNT(cmc.id)
                           FROM {course_modules_completion} cmc
                           JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                          WHERE cm.course = :cid
                            AND cmc.userid = :uid
                            AND cmc.completionstate > 0";
            $done           = (int) $DB->get_field_sql($sqldone, ['cid' => $cid, 'uid' => $uid]);
            $completionrate = round($done / $totalcompletable, 4);
        } else {
            $completionrate = 1.0;   // no tracked activities → neutral
        }

        return [
            'user_id'                    => $uid,
            'course_id'                  => $cid,
            'last_access_days'           => (float) $lastaccessdays,
            'submission_rate'            => (float) $submissionrate,
            'login_count_7d'             => $logincount7d,
            'login_count_30d'            => $logincount30d,
            'saipa_days_since_last_chat' => (float) $saipadayssincelastchat,
            'saipa_message_count'        => $saipamsgcount,
            'quiz_avg_score'             => (float) $quizavgscore,
            'quiz_attempt_count'         => $quizattemptcount,
            'forum_post_count'           => $forumpostcount,
            'positive_feedback_ratio'    => (float) $positivefeedbackratio,
            'completion_rate'            => (float) $completionrate,
        ];
    }

    /**
     * Define the return structure for this web service.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'user_id'                    => new external_value(PARAM_INT, 'User ID'),
            'course_id'                  => new external_value(PARAM_INT, 'Course ID'),
            'last_access_days'           => new external_value(PARAM_FLOAT, 'Days since last course access'),
            'submission_rate'            => new external_value(PARAM_FLOAT, 'Assignment submission rate 0-1'),
            'login_count_7d'             => new external_value(PARAM_INT, 'Moodle logins in last 7 days'),
            'login_count_30d'            => new external_value(PARAM_INT, 'Moodle logins in last 30 days'),
            'saipa_days_since_last_chat' => new external_value(PARAM_FLOAT, 'Days since last SAIPA message'),
            'saipa_message_count'        => new external_value(PARAM_INT, 'Total SAIPA messages by student'),
            'quiz_avg_score'             => new external_value(PARAM_FLOAT, 'Average quiz score 0-100'),
            'quiz_attempt_count'         => new external_value(PARAM_INT, 'Number of quiz attempts'),
            'forum_post_count'           => new external_value(PARAM_INT, 'Forum posts in this course'),
            'positive_feedback_ratio'    => new external_value(PARAM_FLOAT, 'Ratio of positive SAIPA feedback'),
            'completion_rate'            => new external_value(PARAM_FLOAT, 'Activity completion rate 0-1'),
        ]);
    }
}
