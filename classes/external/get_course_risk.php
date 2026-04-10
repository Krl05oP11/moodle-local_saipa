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
 * External web service: get_course_risk
 * Computes dropout risk for all enrolled students in a course.
 * Calls saipa-engine /analytics/risk/batch, which in turn calls
 * local_saipa_get_student_features for each student.
 * Saves results to saipa_risk_scores and returns them.
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

require_once($CFG->dirroot . '/local/saipa/lib.php');

class get_course_risk extends external_api {
    /**
     * Predefined demo results — bypasses the engine to guarantee a visible spread
     * of 🟢 low / 🟡 medium / 🔴 high risk levels.
     * Factors are the keys that most explain the score in each scenario.
     * Ordered to cycle across risk levels as we iterate over enrolled students.
     */
    private static function demo_results(): array {
        return [
            // 0: 🟢 low — estudiante muy activo
            ['score' => 0.08, 'risk_level' => 'low',
             'factors' => ['last_access_days' => 0.007, 'submission_rate' => 0.028, 'quiz_avg_score' => 0.013]],
            // 1: 🟢 low — activo, sin usar SAIPA aún
            ['score' => 0.14, 'risk_level' => 'low',
             'factors' => ['submission_rate' => 0.041, 'login_count_7d' => 0.019, 'completion_rate' => 0.011]],
            // 2: 🟡 medium — actividad decreciente
            ['score' => 0.51, 'risk_level' => 'medium',
             'factors' => ['last_access_days' => 0.124, 'login_count_7d' => 0.076, 'submission_rate' => 0.058]],
            // 3: 🟡 medium — pocas entregas
            ['score' => 0.63, 'risk_level' => 'medium',
             'factors' => ['submission_rate' => 0.143, 'last_access_days' => 0.098, 'quiz_avg_score' => 0.065]],
            // 4: 🔴 high — ausente hace 3 semanas
            ['score' => 0.87, 'risk_level' => 'high',
             'factors' => ['last_access_days' => 0.321, 'submission_rate' => 0.248, 'login_count_7d' => 0.076]],
            // 5: 🔴 high — sin actividad en el curso
            ['score' => 0.96, 'risk_level' => 'high',
             'factors' => ['last_access_days' => 0.396, 'submission_rate' => 0.276, 'completion_rate' => 0.043]],
        ];
    }

    /**
     * Build demo risk result for one student using deterministic round-robin profiles.
     */
    private static function demo_result_for(int $index, int $uid): array {
        $profiles = self::demo_results();
        $p = $profiles[$index % count($profiles)];
        return [
            'userid'     => $uid,
            'score'      => $p['score'],
            'risk_level' => $p['risk_level'],
            'factors'    => json_encode($p['factors']),
        ];
    }

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'course_id' => new external_value(PARAM_INT, 'Course ID'),
            'demo'      => new external_value(PARAM_BOOL, 'Use synthetic demo data instead of real features', VALUE_DEFAULT, false),
        ]);
    }

    public static function execute(int $course_id, bool $demo = false): array {
        global $DB;

        $params  = self::validate_parameters(self::execute_parameters(), [
            'course_id' => $course_id,
            'demo'      => $demo,
        ]);
        $cid     = $params['course_id'];
        $context = \context_course::instance($cid);
        self::validate_context($context);
        require_capability('local/saipa:view', $context);

        // Collect enrolled students (users who can chat but NOT view = not teachers).
        $all_chatters = get_enrolled_users($context, 'local/saipa:chat', 0, 'u.id');
        $teacher_ids  = array_keys(get_enrolled_users($context, 'local/saipa:view', 0, 'u.id'));
        $students     = array_filter($all_chatters, fn($u) => !in_array($u->id, $teacher_ids));

        if (empty($students)) {
            return ['results' => [], 'errors' => []];
        }

        $user_ids = array_values(array_map(fn($u) => (int) $u->id, $students));

        $now     = time();
        $results = [];
        $errors  = [];

        if ($params['demo']) {
            // Demo mode: return predefined results without touching the engine or real DB.
            // Results are NOT persisted to saipa_risk_scores.
            foreach (array_values($user_ids) as $i => $uid) {
                $results[] = self::demo_result_for($i, $uid);
            }
            return ['results' => $results, 'errors' => $errors];
        }

        // Real mode: compute features in PHP, send to engine.
        $features_map = [];
        foreach ($user_ids as $uid) {
            try {
                $features_map[(string) $uid] = get_student_features::execute($uid, $cid);
            } catch (\Throwable $e) {
                // Skip students whose features cannot be extracted.
            }
        }

        $response = local_saipa_engine_request('/analytics/risk/batch', [
            'course_id'    => $cid,
            'user_ids'     => array_map('intval', array_keys($features_map)),
            'features_map' => $features_map,
        ], 120);

        if (isset($response['error'])) {
            throw new \moodle_exception('engine_error', 'local_saipa', '', $response['error']);
        }

        foreach ($response['results'] ?? [] as $r) {
            $uid        = (int) $r['user_id'];
            $score      = (float) $r['score'];
            $risk_level = (string) $r['risk_level'];
            $factors    = json_encode($r['factors'] ?? []);

            $existing = $DB->get_record(
                'saipa_risk_scores',
                ['userid' => $uid, 'courseid' => $cid]
            );

            if ($existing) {
                $DB->update_record('saipa_risk_scores', (object) [
                    'id'           => $existing->id,
                    'score'        => $score,
                    'risk_level'   => $risk_level,
                    'factors'      => $factors,
                    'timecomputed' => $now,
                ]);
            } else {
                $DB->insert_record('saipa_risk_scores', (object) [
                    'userid'       => $uid,
                    'courseid'     => $cid,
                    'score'        => $score,
                    'risk_level'   => $risk_level,
                    'factors'      => $factors,
                    'timecomputed' => $now,
                ]);
            }

            $results[] = [
                'userid'     => $uid,
                'score'      => $score,
                'risk_level' => $risk_level,
                'factors'    => $factors,
            ];
        }

        foreach ($response['errors'] ?? [] as $e) {
            $errors[] = [
                'userid'  => (int) $e['user_id'],
                'message' => (string) $e['error'],
            ];
        }

        return ['results' => $results, 'errors' => $errors];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'results' => new external_multiple_structure(
                new external_single_structure([
                    'userid'     => new external_value(PARAM_INT, 'Student user ID'),
                    'score'      => new external_value(PARAM_FLOAT, 'Dropout risk 0.0–1.0'),
                    'risk_level' => new external_value(PARAM_TEXT, '"low", "medium" or "high"'),
                    'factors'    => new external_value(PARAM_RAW, 'JSON object of top contributing features'),
                ])
            ),
            'errors' => new external_multiple_structure(
                new external_single_structure([
                    'userid'  => new external_value(PARAM_INT, 'Student user ID'),
                    'message' => new external_value(PARAM_TEXT, 'Error description'),
                ]),
                'Students whose risk could not be computed',
                VALUE_OPTIONAL
            ),
        ]);
    }
}
