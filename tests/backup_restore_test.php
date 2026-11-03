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
 * PHPUnit tests: SAIPA's course data survives a course backup and restore.
 *
 * Marketplace review MMR-462, issue 3.
 *
 * Run from the Moodle root:
 *   vendor/bin/phpunit local/saipa/tests/backup_restore_test.php
 *
 * @package    local_saipa
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_saipa;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Tests for backup_local_saipa_plugin and restore_local_saipa_plugin.
 *
 * @covers \backup_local_saipa_plugin
 * @covers \restore_local_saipa_plugin
 */
final class backup_restore_test extends \advanced_testcase {
    /** @var \stdClass Course with SAIPA data. */
    private \stdClass $course;
    /** @var \stdClass Student with a chat session, a rating and risk data. */
    private \stdClass $student;

    /**
     * A course with settings, one student's chat (two messages, one rating) and risk data.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course();
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $now = time();
        $DB->insert_record('local_saipa_course_settings', (object) [
            'courseid' => $this->course->id, 'saipa_enabled' => 1, 'chat_enabled' => 0, 'risk_enabled' => 1,
            'alerts_enabled' => 0, 'rag_enabled' => 1, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $sessionid = $DB->insert_record('local_saipa_sessions', (object) [
            'userid' => $this->student->id, 'courseid' => $this->course->id,
            'contextid' => \context_course::instance($this->course->id)->id, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('local_saipa_messages', (object) [
            'sessionid' => $sessionid, 'role' => 'user', 'content' => 'What is entropy?', 'timecreated' => $now,
        ]);
        $answerid = $DB->insert_record('local_saipa_messages', (object) [
            'sessionid' => $sessionid, 'role' => 'assistant', 'content' => 'A measure of disorder.', 'timecreated' => $now + 1,
        ]);
        $DB->insert_record('local_saipa_feedback', (object) [
            'messageid' => $answerid, 'userid' => $this->student->id, 'rating' => 1, 'timecreated' => $now + 2,
        ]);
        $DB->insert_record('local_saipa_risk_scores', (object) [
            'userid' => $this->student->id, 'courseid' => $this->course->id, 'score' => 0.82, 'risk_level' => 'high',
            'factors' => '{"last_access_days":0.3}', 'timecomputed' => $now,
        ]);
        $DB->insert_record('local_saipa_risk_history', (object) [
            'userid' => $this->student->id, 'courseid' => $this->course->id, 'score' => 0.82, 'risk_level' => 'high',
            'timecomputed' => $now,
        ]);
    }

    /**
     * Backs up the course and restores it into a new course.
     *
     * @param bool $users Whether the backup includes user data.
     * @param int|null $targetcourseid Restore into this course after deleting its content, instead of a new course.
     * @return int Id of the course restored into.
     */
    private function backup_and_restore(bool $users, ?int $targetcourseid = null): int {
        global $CFG, $USER;
        $CFG->keeptempdirectoriesonbackup = true;

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $this->course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value($users);
        $bc->execute_plan();
        $backupid = $bc->get_backupid();
        $bc->destroy();

        if ($targetcourseid) {
            // Restore into this course deleting its contents first: the controller deletes the content.
            $newcourseid = $targetcourseid;
        } else {
            $newcourseid = \restore_dbops::create_new_course('Restored', 'restored' . (int) $users, $this->course->category);
        }
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            $targetcourseid ? \backup::TARGET_EXISTING_DELETING : \backup::TARGET_NEW_COURSE
        );
        if ($targetcourseid) {
            // Into an existing course, Moodle restores course-level plugin data (the course
            // structure step) only with "Overwrite course configuration".
            $rc->get_plan()->get_setting('overwrite_conf')->set_value(true);
        }
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();
        return $newcourseid;
    }

    /**
     * With user data, settings, the chat with its rating and the risk data reach the new course.
     */
    public function test_restore_with_user_data(): void {
        global $DB;
        $newcourseid = $this->backup_and_restore(true);

        $settings = $DB->get_record('local_saipa_course_settings', ['courseid' => $newcourseid], '*', MUST_EXIST);
        $this->assertEquals(0, $settings->chat_enabled);
        $this->assertEquals(0, $settings->alerts_enabled);

        $session = $DB->get_record('local_saipa_sessions', ['courseid' => $newcourseid], '*', MUST_EXIST);
        $this->assertEquals($this->student->id, $session->userid);
        $this->assertEquals(\context_course::instance($newcourseid)->id, $session->contextid);
        $messages = array_values($DB->get_records('local_saipa_messages', ['sessionid' => $session->id], 'id ASC'));
        $this->assertSame(['What is entropy?', 'A measure of disorder.'], array_column($messages, 'content'));
        $feedback = $DB->get_record('local_saipa_feedback', ['messageid' => $messages[1]->id], '*', MUST_EXIST);
        $this->assertEquals(1, $feedback->rating);

        $score = $DB->get_record('local_saipa_risk_scores', ['courseid' => $newcourseid], '*', MUST_EXIST);
        $this->assertSame('high', $score->risk_level);
        $this->assertEquals(1, $DB->count_records('local_saipa_risk_history', ['courseid' => $newcourseid]));

        // The original course keeps its own data.
        $originalmessages = $DB->count_records_sql(
            'SELECT COUNT(m.id) FROM {local_saipa_messages} m JOIN {local_saipa_sessions} s ON s.id = m.sessionid
              WHERE s.courseid = ?',
            [$this->course->id]
        );
        $this->assertEquals(2, $originalmessages);
    }

    /**
     * Without user data, only the course settings are restored.
     */
    public function test_restore_without_user_data(): void {
        global $DB;
        $newcourseid = $this->backup_and_restore(false);

        $this->assertTrue($DB->record_exists('local_saipa_course_settings', ['courseid' => $newcourseid]));
        $this->assertFalse($DB->record_exists('local_saipa_sessions', ['courseid' => $newcourseid]));
        $this->assertFalse($DB->record_exists('local_saipa_risk_scores', ['courseid' => $newcourseid]));
        $this->assertFalse($DB->record_exists('local_saipa_risk_history', ['courseid' => $newcourseid]));
    }

    /**
     * Deleting a course's content removes SAIPA's data about it, and only about it.
     */
    public function test_content_deletion_removes_course_data(): void {
        global $DB;
        $other = $this->getDataGenerator()->create_course();
        $DB->insert_record('local_saipa_risk_scores', (object) [
            'userid' => $this->student->id, 'courseid' => $other->id, 'score' => 0.1, 'risk_level' => 'low',
            'timecomputed' => time(),
        ]);

        remove_course_contents($this->course->id, false);

        foreach (
            ['local_saipa_sessions', 'local_saipa_risk_scores', 'local_saipa_risk_history',
                'local_saipa_course_settings'] as $table
        ) {
            $this->assertFalse($DB->record_exists($table, ['courseid' => $this->course->id]), $table);
        }
        $this->assertEquals(0, $DB->count_records('local_saipa_messages'));
        $this->assertEquals(0, $DB->count_records('local_saipa_feedback'));
        $this->assertTrue($DB->record_exists('local_saipa_risk_scores', ['courseid' => $other->id]));
    }

    /**
     * Restoring into the same course after deleting its content leaves the data once, not twice.
     */
    public function test_restore_into_course_deleting_content(): void {
        global $DB;
        $this->backup_and_restore(true, $this->course->id);

        $this->assertEquals(1, $DB->count_records('local_saipa_sessions', ['courseid' => $this->course->id]));
        $this->assertEquals(2, $DB->count_records('local_saipa_messages'));
        $this->assertEquals(1, $DB->count_records('local_saipa_course_settings', ['courseid' => $this->course->id]));
        $this->assertEquals(1, $DB->count_records('local_saipa_risk_history', ['courseid' => $this->course->id]));
    }
}
