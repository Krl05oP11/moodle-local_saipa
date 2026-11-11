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
 * The course controls of the advisor dashboard take effect.
 *
 * set_course_settings stores, per course, a master switch and switches for the chat, risk evaluation,
 * alerts and RAG. These tests check that the services follow them.
 *
 * @package    local_saipa
 * @category   test
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_saipa;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/saipa/lib.php');

/**
 * Course controls tests.
 */
final class course_controls_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass */
    private \stdClass $teacher;

    /** @var \stdClass */
    private \stdClass $student;

    /**
     * Course with a teacher and a student; no engine configured.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $this->teacher = $gen->create_and_enrol($this->course, 'editingteacher');
        $this->student = $gen->create_and_enrol($this->course, 'student');
        set_config('engine_url', '', 'local_saipa');
    }

    /**
     * Stores the course controls of a course.
     *
     * @param int $courseid Course ID.
     * @param array $flags Values for saipa, chat, risk, alerts and rag (1 when not given).
     */
    private function set_controls(int $courseid, array $flags): void {
        global $DB;
        $record = (object) ['courseid' => $courseid, 'timecreated' => time(), 'timemodified' => time()];
        foreach (['saipa', 'chat', 'risk', 'alerts', 'rag'] as $f) {
            $record->{$f . '_enabled'} = $flags[$f] ?? 1;
        }
        $DB->insert_record('local_saipa_course_settings', $record);
    }

    /**
     * Without settings everything is on; the master switch turns every feature off.
     *
     * @covers ::local_saipa_course_feature_enabled
     */
    public function test_feature_enabled(): void {
        $this->assertTrue(local_saipa_course_feature_enabled($this->course->id, 'chat'));

        $this->set_controls($this->course->id, ['chat' => 0]);
        $this->assertFalse(local_saipa_course_feature_enabled($this->course->id, 'chat'));
        $this->assertTrue(local_saipa_course_feature_enabled($this->course->id, 'risk'));
        $this->assertTrue(local_saipa_course_feature_enabled($this->course->id));

        $other = $this->getDataGenerator()->create_course();
        $this->set_controls($other->id, ['saipa' => 0]);
        foreach (['', 'chat', 'risk', 'alerts', 'rag'] as $f) {
            $this->assertFalse(local_saipa_course_feature_enabled($other->id, $f), $f);
        }
    }

    /**
     * With the chat off, the chat service answers with a notice and stores nothing.
     *
     * @covers \local_saipa\external\chat::execute
     */
    public function test_chat_off_answers_with_notice(): void {
        global $DB;
        $this->set_controls($this->course->id, ['chat' => 0]);
        $this->setUser($this->student);

        $result = \local_saipa\external\chat::execute($this->course->id, 'Hello', 0);

        $this->assertSame(get_string('feature_disabled_chat', 'local_saipa'), $result['reply']);
        $this->assertSame(0, $DB->count_records('local_saipa_messages'));
    }

    /**
     * With risk evaluation off, the teacher cannot compute risk for the course.
     *
     * @covers \local_saipa\external\get_course_risk::execute
     */
    public function test_risk_off_refuses(): void {
        $this->set_controls($this->course->id, ['risk' => 0]);
        $this->setUser($this->teacher);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('feature_disabled_risk', 'local_saipa'));
        \local_saipa\external\get_course_risk::execute($this->course->id);
    }

    /**
     * With alerts off, no Telegram alert is sent.
     *
     * @covers \local_saipa\external\send_telegram_alert::execute
     */
    public function test_alerts_off_refuses(): void {
        $this->set_controls($this->course->id, ['alerts' => 0]);
        $this->setUser($this->teacher);

        $result = \local_saipa\external\send_telegram_alert::execute($this->student->id, $this->course->id, 'Hi');

        $this->assertFalse($result['sent']);
        $this->assertSame(get_string('feature_disabled_alerts', 'local_saipa'), $result['error']);
    }

    /**
     * The Telegram chat skips courses with the chat off and reports whether RAG is on.
     *
     * @covers \local_saipa\external\telegram_get_session::execute
     */
    public function test_telegram_session_follows_controls(): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $second = $gen->create_course();
        $gen->enrol_user($this->student->id, $second->id, 'student');
        $now = time();
        $DB->insert_record('local_saipa_telegram_links', (object) [
            'userid' => $this->student->id, 'telegram_id' => 4242, 'telegram_username' => 'st', 'confirmed' => 1,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        // The most recent session is in $this->course.
        foreach ([[$second->id, $now - 100], [$this->course->id, $now]] as [$cid, $time]) {
            $DB->insert_record('local_saipa_sessions', (object) [
                'userid' => $this->student->id, 'courseid' => $cid,
                'contextid' => \context_course::instance($cid)->id, 'timecreated' => $time, 'timemodified' => $time,
            ]);
        }
        $this->set_controls($this->course->id, ['chat' => 0]);
        $this->set_controls($second->id, ['rag' => 0]);

        $bridge = $gen->create_user();
        $roleid = $gen->create_role();
        assign_capability('local/saipa:enginebridge', CAP_ALLOW, $roleid, \context_system::instance());
        role_assign($roleid, $bridge->id, \context_system::instance()->id);
        $this->setUser($bridge);

        $result = \local_saipa\external\telegram_get_session::execute(4242);

        $this->assertSame((int) $second->id, $result['course_id']);
        $this->assertFalse($result['use_rag']);
    }
}
