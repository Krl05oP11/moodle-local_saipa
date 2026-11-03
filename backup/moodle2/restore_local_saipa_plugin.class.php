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
 * Restore of SAIPA's course data.
 *
 * @package    local_saipa
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restores what backup_local_saipa_plugin saved into the target course.
 *
 * Users are mapped to the restored ones; data of users that are not restored is skipped.
 * When restoring into an existing course, the course's own settings, sessions and current
 * risk scores win: settings are kept, messages join the user's existing session, and a
 * user's existing risk score is not replaced.
 */
class restore_local_saipa_plugin extends restore_local_plugin {
    /**
     * Declares the paths to process inside the course.
     *
     * @return restore_path_element[]
     */
    protected function define_course_plugin_structure() {
        $paths = [new restore_path_element($this->get_namefor('course_settings'), $this->get_pathfor('/course_settings'))];
        if ($this->get_setting_value('users')) {
            $paths[] = new restore_path_element($this->get_namefor('session'), $this->get_pathfor('/sessions/session'));
            $paths[] = new restore_path_element(
                $this->get_namefor('message'),
                $this->get_pathfor('/sessions/session/messages/message')
            );
            $paths[] = new restore_path_element(
                $this->get_namefor('feedback'),
                $this->get_pathfor('/sessions/session/messages/message/feedbacks/feedback')
            );
            $paths[] = new restore_path_element($this->get_namefor('risk_score'), $this->get_pathfor('/risk_scores/risk_score'));
            $paths[] = new restore_path_element($this->get_namefor('risk_entry'), $this->get_pathfor('/risk_history/risk_entry'));
        }
        return $paths;
    }

    /**
     * The course's SAIPA switches, unless the target course already has its own.
     *
     * @param array $data Element data.
     */
    public function process_local_saipa_course_settings($data) {
        global $DB;
        $data = (object) $data;
        $data->courseid = $this->task->get_courseid();
        if (!$DB->record_exists('local_saipa_course_settings', ['courseid' => $data->courseid])) {
            unset($data->id);
            $DB->insert_record('local_saipa_course_settings', $data);
        }
    }

    /**
     * A user's chat session in the course.
     *
     * @param array $data Element data.
     */
    public function process_local_saipa_session($data) {
        global $DB;
        $data = (object) $data;
        $oldid = $data->id;
        $userid = $this->get_mappingid('user', $data->userid);
        if (!$userid) {
            return;
        }
        $courseid = $this->task->get_courseid();
        $existing = $DB->get_field('local_saipa_sessions', 'id', ['userid' => $userid, 'courseid' => $courseid], IGNORE_MULTIPLE);
        if ($existing) {
            $newid = $existing;
        } else {
            $newid = $DB->insert_record('local_saipa_sessions', (object) [
                'userid'       => $userid,
                'courseid'     => $courseid,
                'contextid'    => context_course::instance($courseid)->id,
                'timecreated'  => $data->timecreated,
                'timemodified' => $data->timemodified,
            ]);
        }
        $this->set_mapping('local_saipa_session', $oldid, $newid);
    }

    /**
     * A message of a restored session.
     *
     * @param array $data Element data.
     */
    public function process_local_saipa_message($data) {
        global $DB;
        $data = (object) $data;
        $oldid = $data->id;
        $sessionid = $this->get_new_parentid('local_saipa_session');
        if (!$sessionid) {
            return;
        }
        $newid = $DB->insert_record('local_saipa_messages', (object) [
            'sessionid'   => $sessionid,
            'role'        => $data->role,
            'content'     => $data->content,
            'timecreated' => $data->timecreated,
        ]);
        $this->set_mapping('local_saipa_message', $oldid, $newid);
    }

    /**
     * A rating of a restored message.
     *
     * @param array $data Element data.
     */
    public function process_local_saipa_feedback($data) {
        global $DB;
        $data = (object) $data;
        $messageid = $this->get_new_parentid('local_saipa_message');
        $userid = $this->get_mappingid('user', $data->userid);
        if (!$messageid || !$userid) {
            return;
        }
        $DB->insert_record('local_saipa_feedback', (object) [
            'messageid'   => $messageid,
            'userid'      => $userid,
            'rating'      => $data->rating,
            'comment'     => $data->comment,
            'timecreated' => $data->timecreated,
        ]);
    }

    /**
     * A user's current risk score, unless the target course already has one for that user.
     *
     * @param array $data Element data.
     */
    public function process_local_saipa_risk_score($data) {
        global $DB;
        $data = (object) $data;
        $userid = $this->get_mappingid('user', $data->userid);
        $courseid = $this->task->get_courseid();
        if (!$userid || $DB->record_exists('local_saipa_risk_scores', ['userid' => $userid, 'courseid' => $courseid])) {
            return;
        }
        $DB->insert_record('local_saipa_risk_scores', (object) [
            'userid'       => $userid,
            'courseid'     => $courseid,
            'score'        => $data->score,
            'risk_level'   => $data->risk_level,
            'factors'      => $data->factors,
            'timecomputed' => $data->timecomputed,
        ]);
    }

    /**
     * A past risk evaluation of a user.
     *
     * @param array $data Element data.
     */
    public function process_local_saipa_risk_entry($data) {
        global $DB;
        $data = (object) $data;
        $userid = $this->get_mappingid('user', $data->userid);
        if (!$userid) {
            return;
        }
        $DB->insert_record('local_saipa_risk_history', (object) [
            'userid'       => $userid,
            'courseid'     => $this->task->get_courseid(),
            'score'        => $data->score,
            'risk_level'   => $data->risk_level,
            'timecomputed' => $data->timecomputed,
        ]);
    }
}
