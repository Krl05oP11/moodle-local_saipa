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
 * External web service: get_risk_dashboard
 * Returns risk ROI data: alert funnel, trend, && intervention effectiveness.
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
 * Get_risk_dashboard.
 */
class get_risk_dashboard extends external_api {
    /**
     * Define the parameters for this web service.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'period'   => new external_value(PARAM_ALPHANUMEXT, 'Period: 7d, 30d, semester, all', VALUE_DEFAULT, '30d'),
            'courseid' => new external_value(PARAM_INT, 'Course ID; 0 = all courses', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute the web service.
     */
    public static function execute(string $period = '30d', int $courseid = 0): array {
        global $DB;

        $params  = self::validate_parameters(
            self::execute_parameters(),
            ['period' => $period, 'courseid' => $courseid]
        );
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/saipa:advisor', $context);

        $since = self::period_to_since($params['period']);
        $cid   = (int) $params['courseid'];

        $coursefilter       = $cid > 0 ? 'AND s.courseid = :cid' : '';
        $coursefilternotif = $cid > 0
            ? 'AND n.userid IN (SELECT DISTINCT userid FROM {saipa_sessions} WHERE courseid = :cid)'
            : '';
        $paramscid = $cid > 0 ? ['cid' => $cid] : [];

        // ── Alert funnel ──────────────────────────────────────────────────────
        // Students evaluated (have at least one risk score).
        $evaluated = (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT userid) FROM {saipa_risk_scores}" .
            ($cid > 0 ? ' WHERE courseid = :cid' : ''),
            $cid > 0 ? ['cid' => $cid] : []
        );

        // Identified high risk (current level).
        $identifiedhigh = (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT userid) FROM {saipa_risk_scores}
              WHERE risk_level = :level" . ($cid > 0 ? ' AND courseid = :cid' : ''),
            array_merge(['level' => 'high'], $cid > 0 ? ['cid' => $cid] : [])
        );

        // Received alert (sent since period start, scoped to course if requested).
        $receivedalert = (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT n.userid) FROM {saipa_notifications} n
              WHERE n.timesent >= :since {$coursefilternotif}",
            array_merge(['since' => $since], $paramscid)
        );

        // Responded to alert.
        $respondedalert = (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT n.userid) FROM {saipa_notifications} n
              WHERE n.timesent >= :since AND n.responded_at > 0 {$coursefilternotif}",
            array_merge(['since' => $since], $paramscid)
        );

        // Accessed Moodle after alert (had a new SAIPA session after responded_at).
        $accessedafter = (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT s.userid)
               FROM {saipa_sessions} s
               JOIN {saipa_notifications} n ON n.userid = s.userid
              WHERE n.responded_at > 0
                AND s.timecreated > n.responded_at
                AND n.timesent >= :since {$coursefilter}",
            array_merge(['since' => $since], $paramscid)
        );

        // ── Risk trend by week (from saipa_risk_history) ──────────────────────
        $weekseconds = 7 * 86400;
        $trendrows = $DB->get_records_sql(
            "SELECT FLOOR(timecomputed / :wk) AS week_bucket,
                    SUM(CASE WHEN risk_level = :h THEN 1 ELSE 0 END) AS high_cnt,
                    SUM(CASE WHEN risk_level = :m THEN 1 ELSE 0 END) AS med_cnt,
                    SUM(CASE WHEN risk_level = :l THEN 1 ELSE 0 END) AS low_cnt,
                    COUNT(*) AS total
               FROM {saipa_risk_history}
              WHERE timecomputed >= :since" .
            ($cid > 0 ? ' AND courseid = :cid' : '') .
            " GROUP BY week_bucket ORDER BY week_bucket",
            array_merge(['wk' => $weekseconds, 'h' => 'high', 'm' => 'medium',
                'l' => 'low', 'since' => $since], $paramscid)
        );

        $risktrend = [];
        foreach ($trendrows as $row) {
            $total = max(1, (int) $row->total);
            $risktrend[] = [
                'week'       => (int) ($row->week_bucket * $weekseconds),
                'pct_high'   => round((int) $row->high_cnt / $total, 4),
                'pct_medium' => round((int) $row->med_cnt / $total, 4),
                'pct_low'    => round((int) $row->low_cnt / $total, 4),
            ];
        }

        // ── Intervention effectiveness ─────────────────────────────────────────
        // For each student who received a high-risk alert, compare risk score
        // at alert time vs 14 days later using saipa_risk_history.
        $effectiveness = [];
        $alertrows = $DB->get_records_sql(
            "SELECT DISTINCT n.userid, n.timesent, s.courseid
               FROM {saipa_notifications} n
               JOIN {saipa_sessions} s ON s.userid = n.userid
              WHERE n.timesent >= :since {$coursefilter}
                AND EXISTS (
                    SELECT 1 FROM {saipa_risk_history} rh
                     WHERE rh.userid = n.userid AND rh.risk_level = :level
                       AND rh.timecomputed BETWEEN n.timesent - 86400 AND n.timesent + 86400
                )
              ORDER BY n.timesent",
            array_merge(['since' => $since, 'level' => 'high'], $paramscid),
            0,
            50  // Limit to 50 for performance.
        );

        foreach ($alertrows as $arow) {
            $uid      = (int) $arow->userid;
            $acid     = (int) $arow->courseid;
            $alertts = (int) $arow->timesent;
            $afterts = $alertts + (14 * 86400);

            // Score closest to alert time (before).
            $before = $DB->get_record_sql(
                'SELECT score FROM {saipa_risk_history}
                  WHERE userid = :uid AND courseid = :cid
                    AND timecomputed <= :ts
                  ORDER BY timecomputed DESC LIMIT 1',
                ['uid' => $uid, 'cid' => $acid, 'ts' => $alertts + 86400]
            );

            // Score closest to 14 days after.
            $after = $DB->get_record_sql(
                'SELECT score FROM {saipa_risk_history}
                  WHERE userid = :uid AND courseid = :cid
                    AND timecomputed >= :ts
                  ORDER BY timecomputed ASC LIMIT 1',
                ['uid' => $uid, 'cid' => $acid, 'ts' => $afterts - 86400]
            );

            if ($before && $after) {
                $scorebefore = (float) $before->score;
                $scoreafter  = (float) $after->score;
                $effectiveness[] = [
                    'userid'        => $uid,
                    'courseid'      => $acid,
                    'alert_time'    => $alertts,
                    'risk_at_alert' => $scorebefore,
                    'risk_14d_later' => $scoreafter,
                    'improved'      => $scoreafter < $scorebefore,
                ];
            }
        }

        return [
            'funnel' => [
                'evaluated'       => $evaluated,
                'identified_high' => $identifiedhigh,
                'received_alert'  => $receivedalert,
                'responded_alert' => $respondedalert,
                'accessed_after'  => $accessedafter,
            ],
            'risk_trend'              => $risktrend,
            'intervention_effectiveness' => $effectiveness,
        ];
    }

    /**
     * Convert a period string to a Unix timestamp.
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
            default:
                return $now - (30 * 86400);
        }
    }

    /**
     * Define the return structure for this web service.
     */
    public static function execute_returns(): external_single_structure {
        $trenditem = new external_single_structure([
            'week'       => new external_value(PARAM_INT, 'Week start unix timestamp'),
            'pct_high'   => new external_value(PARAM_FLOAT, 'Fraction at high risk'),
            'pct_medium' => new external_value(PARAM_FLOAT, 'Fraction at medium risk'),
            'pct_low'    => new external_value(PARAM_FLOAT, 'Fraction at low risk'),
        ]);

        $effitem = new external_single_structure([
            'userid'          => new external_value(PARAM_INT, 'User ID'),
            'courseid'        => new external_value(PARAM_INT, 'Course ID'),
            'alert_time'      => new external_value(PARAM_INT, 'Unix timestamp of alert'),
            'risk_at_alert'   => new external_value(PARAM_FLOAT, 'Risk score at alert time'),
            'risk_14d_later'  => new external_value(PARAM_FLOAT, 'Risk score 14 days later'),
            'improved'        => new external_value(PARAM_BOOL, 'True if risk decreased'),
        ]);

        return new external_single_structure([
            'funnel' => new external_single_structure([
                'evaluated'       => new external_value(PARAM_INT, 'Students evaluated'),
                'identified_high' => new external_value(PARAM_INT, 'Students at high risk'),
                'received_alert'  => new external_value(PARAM_INT, 'Students who received alert'),
                'responded_alert' => new external_value(PARAM_INT, 'Students who responded'),
                'accessed_after'  => new external_value(PARAM_INT, 'Students who accessed Moodle after alert'),
            ]),
            'risk_trend'                 => new external_multiple_structure($trenditem),
            'intervention_effectiveness' => new external_multiple_structure($effitem),
        ]);
    }
}
