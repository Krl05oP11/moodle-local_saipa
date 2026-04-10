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
 * Scheduled task: nightly risk evaluation for all active SAIPA courses.
 *
 * For each course that has SAIPA activity:
 *   1. Collect enrolled students.
 *   2. Extract 11 activity features per student (via get_student_features).
 *   3. Call saipa-engine /analytics/risk/batch.
 *   4. Persist results in saipa_risk_scores.
 *   5. Send a Moodle notification to teachers for any student whose risk
 *      level escalated TO "high" since the previous evaluation.
 *
 * Runs at 02:00 daily (configured in db/tasks.php).
 * Enable via Site admin → Server → Scheduled tasks → SAIPA Risk Evaluation.
 *
 * @package    local_saipa
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_saipa\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/saipa/lib.php');

class risk_evaluation extends \core\task\scheduled_task {
    public function get_name(): string {
        return get_string('pluginname', 'local_saipa') . ' — Risk Evaluation';
    }

    public function execute(): void {
        global $DB;

        $start = time();
        mtrace('SAIPA: Starting nightly risk evaluation…');

        // ── 1. Find active courses (have at least one SAIPA session) ──────────
        $sql = "SELECT DISTINCT courseid FROM {saipa_sessions}";
        $course_ids = $DB->get_fieldset_sql($sql);

        if (empty($course_ids)) {
            mtrace('SAIPA: No courses with SAIPA activity — nothing to evaluate.');
            return;
        }

        mtrace(sprintf(
            'SAIPA: Evaluating %d course(s): %s',
            count($course_ids),
            implode(', ', $course_ids)
        ));

        $total_evaluated = 0;
        $total_escalated = 0;
        $total_errors    = 0;

        foreach ($course_ids as $cid) {
            try {
                [$evaluated, $escalated] = $this->evaluate_course((int) $cid);
                $total_evaluated += $evaluated;
                $total_escalated += $escalated;
            } catch (\Throwable $e) {
                $total_errors++;
                mtrace(sprintf('SAIPA: ERROR evaluating course %d — %s', $cid, $e->getMessage()));
            }
        }

        $elapsed = time() - $start;
        mtrace(sprintf(
            'SAIPA: Risk evaluation complete. Students evaluated: %d | Escalations to high: %d | Errors: %d | Time: %ds',
            $total_evaluated,
            $total_escalated,
            $total_errors,
            $elapsed
        ));
    }

    // ── Per-course logic ──────────────────────────────────────────────────────

    /**
     * Evaluates risk for all students in one course.
     *
     * @return array [int $evaluated, int $escalated]
     */
    private function evaluate_course(int $cid): array {
        global $DB;

        $course = $DB->get_record('course', ['id' => $cid]);
        if (!$course) {
            mtrace("  Course {$cid}: not found, skipping.");
            return [0, 0];
        }

        $context = \context_course::instance($cid);

        // Collect students (chat capability but NOT view/teacher capability).
        $all_chatters = get_enrolled_users($context, 'local/saipa:chat', 0, 'u.id, u.firstname, u.lastname');
        $teacher_ids  = array_keys(get_enrolled_users($context, 'local/saipa:view', 0, 'u.id'));
        $students     = array_filter($all_chatters, fn($u) => !in_array($u->id, $teacher_ids));

        if (empty($students)) {
            mtrace("  Course {$cid} ({$course->shortname}): no students, skipping.");
            return [0, 0];
        }

        $user_ids = array_values(array_map(fn($u) => (int) $u->id, $students));
        mtrace(sprintf('  Course %d (%s): %d students', $cid, $course->shortname, count($user_ids)));

        // Snapshot previous risk levels for escalation detection.
        $previous = $this->get_previous_levels($cid, $user_ids);

        // Extract features for each student.
        $features_map = $this->extract_features($cid, $students);

        if (empty($features_map)) {
            mtrace("  Course {$cid}: feature extraction returned nothing, skipping.");
            return [0, 0];
        }

        // Call engine.
        $response = local_saipa_engine_request('/analytics/risk/batch', [
            'course_id'    => $cid,
            'user_ids'     => array_map('intval', array_keys($features_map)),
            'features_map' => $features_map,
        ], 120);

        if (isset($response['error'])) {
            throw new \moodle_exception('engine_error', 'local_saipa', '', $response['error']);
        }

        // Log engine-side errors.
        foreach ($response['errors'] ?? [] as $err) {
            mtrace(sprintf('    user %d: engine error — %s', $err['user_id'], $err['error']));
        }

        // Persist results and detect escalations.
        $now       = time();
        $evaluated = 0;
        $escalated = 0;
        $new_high  = [];   // [userid => fullname]

        foreach ($response['results'] ?? [] as $r) {
            $uid        = (int) $r['user_id'];
            $score      = (float) $r['score'];
            $risk_level = (string) $r['risk_level'];
            $factors    = json_encode($r['factors'] ?? []);

            $this->upsert_score($uid, $cid, $score, $risk_level, $factors, $now);
            $evaluated++;

            // Escalation: wasn't "high" before, is "high" now.
            $prev = $previous[$uid] ?? null;
            if ($risk_level === 'high' && $prev !== 'high') {
                $escalated++;
                $u = $students[$uid] ?? null;
                $name = $u ? fullname($u) : "user {$uid}";
                $new_high[$uid] = $name;
                mtrace(sprintf(
                    '    ⚠ %s (id=%d): escalated to HIGH (was: %s)',
                    $name,
                    $uid,
                    $prev ?? 'unknown'
                ));
            }
        }

        // Send notifications for escalations.
        if (!empty($new_high)) {
            $this->notify_teachers($cid, $course, $context, $new_high);
            $this->notify_students_telegram($course, $new_high);
        }

        return [$evaluated, $escalated];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Returns [userid => risk_level] for existing scores in this course. */
    private function get_previous_levels(int $cid, array $user_ids): array {
        global $DB;

        if (empty($user_ids)) {
            return [];
        }

        [$in_sql, $in_params] = $DB->get_in_or_equal($user_ids, SQL_PARAMS_NAMED, 'uid');
        $rows = $DB->get_records_select(
            'saipa_risk_scores',
            "courseid = :cid AND userid {$in_sql}",
            array_merge(['cid' => $cid], $in_params),
            '',
            'userid, risk_level'
        );

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row->userid] = $row->risk_level;
        }
        return $result;
    }

    /** Calls get_student_features::execute() for each student; silently skips failures. */
    private function extract_features(int $cid, array $students): array {
        $features_map = [];
        foreach ($students as $u) {
            try {
                $features_map[(string) $u->id] =
                    \local_saipa\external\get_student_features::execute($u->id, $cid);
            } catch (\Throwable $e) {
                mtrace(sprintf('    user %d: feature extraction failed — %s', $u->id, $e->getMessage()));
            }
        }
        return $features_map;
    }

    /** Upserts one row in saipa_risk_scores and appends to saipa_risk_history. */
    private function upsert_score(
        int $uid,
        int $cid,
        float $score,
        string $risk_level,
        string $factors,
        int $now
    ): void {
        global $DB;

        $existing = $DB->get_record('saipa_risk_scores', ['userid' => $uid, 'courseid' => $cid]);

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

        // Append to saipa_risk_history for longitudinal trend analysis.
        $DB->insert_record('saipa_risk_history', (object) [
            'userid'       => $uid,
            'courseid'     => $cid,
            'score'        => $score,
            'risk_level'   => $risk_level,
            'timecomputed' => $now,
        ]);
    }

    /**
     * Sends a proactive Telegram message to each escalated student who has
     * a confirmed Telegram link, via the saipa-engine /telegram/notify endpoint.
     *
     * @param object $course   Course DB record
     * @param array  $new_high [userid => fullname]
     */
    private function notify_students_telegram(object $course, array $new_high): void {
        global $DB;

        $course_name = format_string($course->fullname);

        foreach ($new_high as $uid => $name) {
            $link = $DB->get_record('saipa_telegram_links', ['userid' => $uid, 'confirmed' => 1]);
            if (!$link || !$link->telegram_id) {
                continue;
            }

            // Use first name only for a warmer, more personal tone.
            $first_name = explode(' ', trim($name))[0];

            $message =
                "🤖 Hola {$first_name}! SAIPA detectó que podrías necesitar un poco de apoyo " .
                "en tu cursada de *{$course_name}*.\n\n" .
                "Tu docente ya está al tanto y puede ayudarte. " .
                "Si querés charlar o tenés alguna consulta sobre el curso, ¡escribime aquí! " .
                "No estás solo/a. 💪";

            $response = local_saipa_engine_request('/telegram/notify', [
                'telegram_id' => (int) $link->telegram_id,
                'message'     => $message,
            ]);

            if (isset($response['error'])) {
                mtrace(sprintf(
                    '    ⚠ Telegram notify failed for user %d (%s): %s',
                    $uid,
                    $name,
                    $response['error']
                ));
            } else if (!empty($response['sent'])) {
                mtrace(sprintf(
                    '    📱 Telegram alerta enviada a %s (chat_id=%d)',
                    $name,
                    $link->telegram_id
                ));
            }
        }
    }

    /**
     * Sends a Moodle message to every teacher in the course for each student
     * who escalated to high risk.
     *
     * @param int    $cid      Course id
     * @param object $course   Course DB record
     * @param object $context  Course context
     * @param array  $new_high [userid => fullname]
     */
    private function notify_teachers(
        int $cid,
        object $course,
        object $context,
        array $new_high
    ): void {
        global $DB, $CFG;

        $teachers = get_enrolled_users(
            $context,
            'local/saipa:view',
            0,
            'u.id, u.firstname, u.lastname, u.email'
        );

        if (empty($teachers)) {
            return;
        }

        // Build the student list for the message body.
        $student_lines = [];
        foreach ($new_high as $uid => $name) {
            $student_lines[] = "  • {$name}";
        }
        $student_list = implode("\n", $student_lines);

        $course_url = (new \moodle_url(
            '/local/saipa/teacher.php',
            ['courseid' => $cid]
        ))->out(false);

        $subject = sprintf('[SAIPA] Alerta de riesgo — %s', format_string($course->fullname));

        $body_text = sprintf(
            "SAIPA detectó que los siguientes estudiantes escalaron a RIESGO ALTO en el curso \"%s\":\n\n%s\n\n" .
            "Accedé al panel del docente para ver los detalles:\n%s\n\n" .
            "Este mensaje fue generado automáticamente por SAIPA.",
            format_string($course->fullname),
            $student_list,
            $course_url
        );

        $body_html = sprintf(
            '<p>SAIPA detectó que los siguientes estudiantes escalaron a <strong>🔴 RIESGO ALTO</strong> ' .
            'en el curso <em>%s</em>:</p><ul>%s</ul>' .
            '<p><a href="%s">Ver panel del docente →</a></p>' .
            '<p style="color:#888;font-size:0.9em;">Mensaje generado automáticamente por SAIPA.</p>',
            format_string($course->fullname),
            implode('', array_map(fn($n) => "<li>{$n}</li>", $new_high)),
            $course_url
        );

        // Use a system user as sender (noreply).
        $sender = \core_user::get_noreply_user();

        foreach ($teachers as $teacher) {
            $msg                     = new \core\message\message();
            $msg->component          = 'local_saipa';
            $msg->name               = 'risk_alert';
            $msg->userfrom           = $sender;
            $msg->userto             = $teacher;
            $msg->subject            = $subject;
            $msg->fullmessage        = $body_text;
            $msg->fullmessageformat  = FORMAT_PLAIN;
            $msg->fullmessagehtml    = $body_html;
            $msg->smallmessage       = sprintf(
                'SAIPA: %d estudiante(s) en riesgo alto en %s',
                count($new_high),
                format_string($course->fullname)
            );
            $msg->notification       = 1;
            $msg->contexturl         = $course_url;
            $msg->contexturlname     = 'Panel del docente';

            message_send($msg);

            mtrace(sprintf('    📧 Notificación enviada a %s', fullname($teacher)));
        }
    }
}
