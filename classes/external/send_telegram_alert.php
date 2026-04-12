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
 * External web service: send_telegram_alert
 * Sends a proactive Telegram message to a student on behalf of a teacher.
 * Only available to users with local/saipa:view capability (teachers/admins).
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
 * Send_telegram_alert.
 */
class send_telegram_alert extends external_api {
    /** @var mixed Human-readable Spanish labels for risk factor keys returned by the engine. */
    private static $factorlabels = [
        'last_access_days'   => 'días sin ingresar al curso',
        'login_count_7d'     => 'accesos en los últimos 7 días',
        'login_count_30d'    => 'accesos en los últimos 30 días',
        'submission_rate'    => 'tasa de entregas completadas',
        'quiz_avg_score'     => 'promedio en cuestionarios',
        'completion_rate'    => 'progreso general del curso',
        'forum_posts'        => 'participaciones en foros',
        'resource_views'     => 'materiales vistos',
        'saipa_sessions'     => 'consultas a SAIPA',
        'grade_avg'          => 'promedio de calificaciones',
        'days_enrolled'      => 'días inscripto',
    ];

    /**
     * Builds a human-readable risk summary from a saipa_risk_scores record.
     */
    private static function build_risk_detail(?\stdClass $risk): string {
        if (!$risk) {
            return '📊 Tu nivel de participación en el curso merece atención.';
        }

        $levelmap = [
            'high'   => '🔴 *Riesgo ALTO* de abandono',
            'medium' => '🟡 *Riesgo MEDIO* de abandono',
            'low'    => '🟢 *Riesgo BAJO* de abandono',
        ];
        $levellabel = $levelmap[$risk->risk_level] ?? '📊 Nivel de riesgo detectado';
        $scorepct   = round((float) $risk->score * 100);

        $lines   = [$levellabel . " ({$scorepct}%)"];
        $factors = json_decode($risk->factors ?? '{}', true);

        // Pick the top 2 factors by weight && translate them.
        arsort($factors);
        $top = array_slice($factors, 0, 2, true);
        foreach ($top as $key => $weight) {
            $label   = self::$factorlabels[$key] ?? $key;
            $lines[] = "• Indicador destacado: {$label}";
        }

        return implode("\n", $lines);
    }

    /**
     * Define the parameters for this web service.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'student_id' => new external_value(PARAM_INT, 'Student user ID to alert'),
            'course_id'  => new external_value(PARAM_INT, 'Course context'),
            'message'    => new external_value(PARAM_TEXT, 'Message to send (leave empty for default)', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Execute the web service.
     */
    public static function execute(int $studentid, int $courseid, string $message = ''): array {
        global $CFG;
        require_once($CFG->dirroot . '/local/saipa/lib.php');
        global $DB, $USER;

        $params  = self::validate_parameters(self::execute_parameters(), [
            'student_id' => $studentid,
            'course_id'  => $courseid,
            'message'    => $message,
        ]);

        $context = \context_course::instance($params['course_id']);
        self::validate_context($context);
        require_capability('local/saipa:view', $context);

        // Look up student's confirmed Telegram link.
        $link = $DB->get_record('saipa_telegram_links', [
            'userid'    => $params['student_id'],
            'confirmed' => 1,
        ]);

        if (!$link || empty($link->telegram_id)) {
            return ['sent' => false, 'error' => 'student_not_linked'];
        }

        // Build message.
        $student = $DB->get_record('user', ['id' => $params['student_id']], 'firstname,lastname');
        $course  = $DB->get_record('course', ['id' => $params['course_id']], 'fullname');
        $teacher = fullname($USER);

        $text = trim($params['message']);
        if ($text === '') {
            // Look up last risk score for contextualised default message.
            $risk = $DB->get_record('saipa_risk_scores', [
                'userid'   => $params['student_id'],
                'courseid' => $params['course_id'],
            ]);

            $riskdetail = self::build_risk_detail($risk);

            $text = get_string('alert_default_message', 'local_saipa', (object) [
                'student'     => $student ? $student->firstname : '',
                'course'      => $course ? format_string($course->fullname) : '',
                'teacher'     => $teacher,
                'risk_detail' => $riskdetail,
            ]);
        }

        // Call engine /telegram/notify.
        $response = local_saipa_engine_request('/telegram/notify', [
            'telegram_id' => (int) $link->telegram_id,
            'message'     => $text,
        ], 15);

        if (isset($response['error'])) {
            return ['sent' => false, 'error' => $response['error']];
        }

        $sent = !empty($response['sent']);

        // Log the notification.
        if ($sent) {
            $DB->insert_record('saipa_notifications', (object) [
                'userid'    => $params['student_id'],
                'template'  => 'teacher_alert',
                'payload'   => json_encode([
                    'course_id' => $params['course_id'],
                    'sender_id' => $USER->id,
                    'message'   => $text,
                ]),
                'status'    => 'sent',
                'timesent'  => time(),
            ]);
        }

        return ['sent' => $sent, 'error' => $sent ? '' : 'engine_failed'];
    }

    /**
     * Define the return structure for this web service.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'sent'  => new external_value(PARAM_BOOL, 'True if message was delivered'),
            'error' => new external_value(PARAM_TEXT, 'Error code if not sent, empty string on success'),
        ]);
    }
}
