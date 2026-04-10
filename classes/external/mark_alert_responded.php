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
 * External web service: mark_alert_responded
 * Called by saipa-engine when a student sends any message to the Telegram bot.
 * Finds the most recent pending teacher_alert for this student and marks it
 * as responded, recording the delay since the alert was sent.
 *
 * Also checks if the student re-accessed Moodle after the alert (using
 * user_lastaccess) and stores that in the payload.
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

defined('MOODLE_INTERNAL') || die();

class mark_alert_responded extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'telegram_id'   => new external_value(PARAM_INT, 'Telegram chat_id of the student'),
            'timereceived'  => new external_value(PARAM_INT, 'Unix timestamp when the message was received', VALUE_DEFAULT, 0),
        ]);
    }

    public static function execute(int $telegram_id, int $timereceived = 0): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'telegram_id'  => $telegram_id,
            'timereceived' => $timereceived,
        ]);

        if ($params['timereceived'] <= 0) {
            $params['timereceived'] = time();
        }

        // Resolve Moodle user from Telegram ID.
        $link = $DB->get_record('saipa_telegram_links', [
            'telegram_id' => $params['telegram_id'],
            'confirmed'   => 1,
        ]);

        if (!$link) {
            return ['found' => false, 'delay_minutes' => 0, 'moodle_accessed' => false];
        }

        $userid = (int) $link->userid;

        // Find the most recent unresponded teacher_alert sent in the last 7 days.
        $since = time() - (7 * DAYSECS);
        $alert = $DB->get_record_select(
            'saipa_notifications',
            'userid = :uid AND template = :tpl AND responded_at IS NULL AND timesent >= :since',
            ['uid' => $userid, 'tpl' => 'teacher_alert', 'since' => $since],
            '*',
            IGNORE_MULTIPLE
        );

        if (!$alert) {
            return ['found' => false, 'delay_minutes' => 0, 'moodle_accessed' => false];
        }

        $delay_minutes = (int) round(($params['timereceived'] - $alert->timesent) / 60);

        // Decode payload to get course_id.
        $payload = json_decode($alert->payload ?? '{}', true);
        $course_id = (int) ($payload['course_id'] ?? 0);

        // Check Moodle re-access after alert.
        $moodle_accessed = false;
        if ($course_id > 0) {
            $access = $DB->get_record('user_lastaccess', ['userid' => $userid, 'courseid' => $course_id]);
            $moodle_accessed = $access && ((int) $access->timeaccess > (int) $alert->timesent);
        }

        // Update the alert record.
        $payload['delay_minutes']   = $delay_minutes;
        $payload['moodle_accessed'] = $moodle_accessed;

        $DB->update_record('saipa_notifications', (object) [
            'id'           => $alert->id,
            'status'       => 'responded',
            'responded_at' => $params['timereceived'],
            'payload'      => json_encode($payload),
        ]);

        return [
            'found'          => true,
            'delay_minutes'  => $delay_minutes,
            'moodle_accessed' => $moodle_accessed,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'found'           => new external_value(PARAM_BOOL, 'True if a pending alert was found and marked'),
            'delay_minutes'   => new external_value(PARAM_INT, 'Minutes between alert and student response'),
            'moodle_accessed' => new external_value(PARAM_BOOL, 'True if student re-accessed Moodle after the alert'),
        ]);
    }
}
