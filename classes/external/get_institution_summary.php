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
 * External web service: get_institution_summary
 * Returns institution-wide KPIs and trend sparklines for the advisor dashboard.
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

class get_institution_summary extends external_api {
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

        // ── Active courses ────────────────────────────────────────────────────
        $active_courses = (int) $DB->count_records_sql(
            'SELECT COUNT(DISTINCT courseid) FROM {saipa_sessions}
              WHERE timecreated >= :since',
            ['since' => $since]
        );

        // ── Total enrolled students across SAIPA-enabled courses ─────────────
        $total_enrolled = (int) $DB->count_records_sql(
            'SELECT COUNT(DISTINCT ue.userid)
               FROM {user_enrolments} ue
               JOIN {enrol} e ON ue.enrolid = e.id
               JOIN {saipa_course_settings} scs ON scs.courseid = e.courseid
               JOIN {role_assignments} ra ON ra.userid = ue.userid
               JOIN {context} ctx ON ctx.id = ra.contextid
                    AND ctx.contextlevel = 50 AND ctx.instanceid = e.courseid
               JOIN {role} r ON r.id = ra.roleid AND r.shortname = :role
              WHERE scs.saipa_enabled = 1 AND ue.status = 0',
            ['role' => 'student']
        );

        // ── Unique SAIPA users who sent at least 1 message ───────────────────
        $total_saipa_users = (int) $DB->count_records_sql(
            'SELECT COUNT(DISTINCT s.userid)
               FROM {saipa_messages} m
               JOIN {saipa_sessions} s ON s.id = m.sessionid
              WHERE m.role = :role AND m.timecreated >= :since',
            ['role' => 'user', 'since' => $since]
        );
        $adoption_rate = $total_enrolled > 0
            ? round($total_saipa_users / $total_enrolled, 4) : 0.0;

        // ── Total messages ────────────────────────────────────────────────────
        $total_messages = (int) $DB->count_records_select(
            'saipa_messages', 'timecreated >= :since', ['since' => $since]
        );

        // ── Feedback ratio ────────────────────────────────────────────────────
        $fb = $DB->get_record_sql(
            'SELECT SUM(CASE WHEN rating > 0 THEN 1 ELSE 0 END) AS pos,
                    SUM(CASE WHEN rating < 0 THEN 1 ELSE 0 END) AS neg
               FROM {saipa_feedback}
              WHERE timecreated >= :since',
            ['since' => $since]
        );
        $pos_fb = (int) ($fb->pos ?? 0);
        $neg_fb = (int) ($fb->neg ?? 0);
        $positive_feedback_pct = ($pos_fb + $neg_fb) > 0
            ? round($pos_fb / ($pos_fb + $neg_fb), 4) : 0.0;

        // ── Alerts ────────────────────────────────────────────────────────────
        $alerts = $DB->get_record_sql(
            'SELECT COUNT(*) AS sent,
                    SUM(CASE WHEN responded_at > 0 THEN 1 ELSE 0 END) AS responded
               FROM {saipa_notifications}
              WHERE timesent >= :since',
            ['since' => $since]
        );
        $alerts_sent      = (int) ($alerts->sent ?? 0);
        $alerts_responded = (int) ($alerts->responded ?? 0);
        $alert_response_rate = $alerts_sent > 0
            ? round($alerts_responded / $alerts_sent, 4) : 0.0;

        // ── Trend sparklines (last 30 days from saipa_daily_stats) ───────────
        $trend_since  = time() - (30 * 86400);
        $trend_rows   = $DB->get_records_sql(
            'SELECT stat_date,
                    SUM(total_messages) AS msgs,
                    SUM(active_users) AS users
               FROM {saipa_daily_stats}
              WHERE stat_date >= :since
              GROUP BY stat_date
              ORDER BY stat_date',
            ['since' => $trend_since]
        );

        $trend_messages  = [];
        $trend_new_users = [];
        foreach ($trend_rows as $row) {
            $trend_messages[]  = ['date' => (int) $row->stat_date, 'count' => (int) $row->msgs];
            $trend_new_users[] = ['date' => (int) $row->stat_date, 'count' => (int) $row->users];
        }

        return [
            'active_courses'         => $active_courses,
            'total_enrolled'         => $total_enrolled,
            'total_saipa_users'      => $total_saipa_users,
            'adoption_rate'          => $adoption_rate,
            'total_messages'         => $total_messages,
            'positive_feedback_pct'  => $positive_feedback_pct,
            'alerts_sent'            => $alerts_sent,
            'alerts_responded'       => $alerts_responded,
            'alert_response_rate'    => $alert_response_rate,
            'trend_messages'         => $trend_messages,
            'trend_new_users'        => $trend_new_users,
        ];
    }

    /**
     * Converts a period string to a unix timestamp (start of period).
     */
    private static function period_to_since(string $period): int {
        $now = time();
        switch ($period) {
            case '7d':
                return $now - (7 * 86400);
            case 'semester':
                return $now - (120 * 86400);
            case 'all':
                return 0;
            case '30d':
            default:
                return $now - (30 * 86400);
        }
    }

    public static function execute_returns(): external_single_structure {
        $trend_item = new external_single_structure([
            'date'  => new external_value(PARAM_INT, 'Unix timestamp midnight UTC'),
            'count' => new external_value(PARAM_INT, 'Count for that day'),
        ]);

        return new external_single_structure([
            'active_courses'         => new external_value(PARAM_INT,   'Courses with SAIPA activity in period'),
            'total_enrolled'         => new external_value(PARAM_INT,   'Unique students across all SAIPA courses'),
            'total_saipa_users'      => new external_value(PARAM_INT,   'Unique users who sent at least 1 message'),
            'adoption_rate'          => new external_value(PARAM_FLOAT, 'saipa_users/enrolled ratio'),
            'total_messages'         => new external_value(PARAM_INT,   'Total messages in period'),
            'positive_feedback_pct'  => new external_value(PARAM_FLOAT, 'Fraction of positive feedback'),
            'alerts_sent'            => new external_value(PARAM_INT,   'Alerts sent in period'),
            'alerts_responded'       => new external_value(PARAM_INT,   'Alerts responded in period'),
            'alert_response_rate'    => new external_value(PARAM_FLOAT, 'responded/sent ratio'),
            'trend_messages'         => new external_multiple_structure($trend_item, 'Daily message counts last 30 days'),
            'trend_new_users'        => new external_multiple_structure($trend_item, 'Daily active user counts last 30 days'),
        ]);
    }
}
