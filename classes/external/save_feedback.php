<?php
// This file is part of Moodle - http://moodle.org/
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
 * External web service: save_feedback
 * Saves a 👍/👎 rating for an assistant message.
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

class save_feedback extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'course_id'  => new external_value(PARAM_INT, 'Course ID'),
            'message_id' => new external_value(PARAM_INT, 'ID of the assistant message being rated'),
            'rating'     => new external_value(PARAM_INT, '1 = thumbs up, -1 = thumbs down'),
        ]);
    }

    public static function execute(int $course_id, int $message_id, int $rating): array {
        global $USER, $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'course_id'  => $course_id,
            'message_id' => $message_id,
            'rating'     => $rating,
        ]);

        $context = \context_course::instance($params['course_id']);
        self::validate_context($context);
        require_capability('local/saipa:chat', $context);

        if (!in_array($params['rating'], [1, -1], true)) {
            throw new \invalid_parameter_exception('rating must be 1 or -1');
        }

        // Verify the message belongs to a session owned by this user in this course.
        $message = $DB->get_record('saipa_messages', ['id' => $params['message_id'], 'role' => 'assistant'], 'id,sessionid', IGNORE_MISSING);
        if (!$message) {
            throw new \invalid_parameter_exception('message not found');
        }
        $session = $DB->get_record('saipa_sessions', [
            'id'       => $message->sessionid,
            'userid'   => (int) $USER->id,
            'courseid' => $params['course_id'],
        ], 'id', IGNORE_MISSING);
        if (!$session) {
            throw new \required_capability_exception($context, 'local/saipa:chat', 'nopermissions', '');
        }

        // Upsert: one rating per user per message.
        $existing = $DB->get_record('saipa_feedback', [
            'messageid' => $params['message_id'],
            'userid'    => (int) $USER->id,
        ]);
        if ($existing) {
            $existing->rating      = $params['rating'];
            $existing->timecreated = time();
            $DB->update_record('saipa_feedback', $existing);
        } else {
            $DB->insert_record('saipa_feedback', (object) [
                'messageid'   => $params['message_id'],
                'userid'      => (int) $USER->id,
                'rating'      => $params['rating'],
                'comment'     => '',
                'timecreated' => time(),
            ]);
        }

        return ['status' => 'ok'];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_TEXT, 'ok'),
        ]);
    }
}
