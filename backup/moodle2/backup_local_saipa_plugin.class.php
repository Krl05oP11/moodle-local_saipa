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
 * Backup of SAIPA's course data.
 *
 * @package    local_saipa
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds SAIPA's course data to course backups.
 *
 * Always: the course's SAIPA switches (local_saipa_course_settings).
 * With user data: chat sessions with their messages and ratings, risk scores and risk history.
 * Left out on purpose: the course index status (it describes the engine's index of the original
 * course, which a restored course does not have: the teacher indexes it again) and the daily
 * statistics (institution reports about the original course).
 */
class backup_local_saipa_plugin extends backup_local_plugin {
    /**
     * Defines the elements attached to the course.
     *
     * @return backup_plugin_element
     */
    protected function define_course_plugin_structure() {
        $plugin = $this->get_plugin_element();
        $saipa = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($saipa);

        $settings = new backup_nested_element('course_settings', ['id'], [
            'saipa_enabled', 'chat_enabled', 'risk_enabled', 'alerts_enabled', 'rag_enabled',
            'timecreated', 'timemodified',
        ]);
        $saipa->add_child($settings);
        $settings->set_source_table('local_saipa_course_settings', ['courseid' => backup::VAR_COURSEID]);

        if (!$this->get_setting_value('users')) {
            return $plugin;
        }

        $sessions = new backup_nested_element('sessions');
        $session = new backup_nested_element('session', ['id'], ['userid', 'timecreated', 'timemodified']);
        $messages = new backup_nested_element('messages');
        $message = new backup_nested_element('message', ['id'], ['role', 'content', 'timecreated']);
        $feedbacks = new backup_nested_element('feedbacks');
        $feedback = new backup_nested_element('feedback', ['id'], ['userid', 'rating', 'comment', 'timecreated']);
        $scores = new backup_nested_element('risk_scores');
        $score = new backup_nested_element('risk_score', ['id'], ['userid', 'score', 'risk_level', 'factors', 'timecomputed']);
        $history = new backup_nested_element('risk_history');
        $entry = new backup_nested_element('risk_entry', ['id'], ['userid', 'score', 'risk_level', 'timecomputed']);

        $saipa->add_child($sessions);
        $sessions->add_child($session);
        $session->add_child($messages);
        $messages->add_child($message);
        $message->add_child($feedbacks);
        $feedbacks->add_child($feedback);
        $saipa->add_child($scores);
        $scores->add_child($score);
        $saipa->add_child($history);
        $history->add_child($entry);

        $session->set_source_table('local_saipa_sessions', ['courseid' => backup::VAR_COURSEID]);
        $message->set_source_table('local_saipa_messages', ['sessionid' => backup::VAR_PARENTID], 'id ASC');
        $feedback->set_source_table('local_saipa_feedback', ['messageid' => backup::VAR_PARENTID]);
        $score->set_source_table('local_saipa_risk_scores', ['courseid' => backup::VAR_COURSEID]);
        $entry->set_source_table('local_saipa_risk_history', ['courseid' => backup::VAR_COURSEID]);

        $session->annotate_ids('user', 'userid');
        $feedback->annotate_ids('user', 'userid');
        $score->annotate_ids('user', 'userid');
        $entry->annotate_ids('user', 'userid');

        return $plugin;
    }
}
