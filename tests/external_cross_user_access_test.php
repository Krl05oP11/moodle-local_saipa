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
 * PHPUnit tests: course-level services must not reach users outside the course or the caller's groups.
 *
 * Marketplace review MMR-462, issues 4 and 5: get_student_features and
 * send_telegram_alert only checked local/saipa:view in the course passed in,
 * so a teacher of any course could read or message any user of the site.
 *
 * Run from the Moodle root:
 *   vendor/bin/phpunit local/saipa/tests/external_cross_user_access_test.php
 *
 * @package    local_saipa
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_saipa;

use local_saipa\external\get_course_risk;
use local_saipa\external\get_student_features;
use local_saipa\external\send_telegram_alert;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/saipa/lib.php');

/**
 * Tests for the access checks of services that take another user's id.
 *
 * @covers \local_saipa\local\access
 * @covers \local_saipa\external\get_student_features
 * @covers \local_saipa\external\send_telegram_alert
 * @covers \local_saipa\external\get_course_risk
 */
final class external_cross_user_access_test extends \advanced_testcase {
    /** @var \stdClass Course taught by $teacher. */
    private \stdClass $course;
    /** @var \stdClass Editing teacher of $course only. */
    private \stdClass $teacher;
    /** @var \stdClass Student enrolled in $course. */
    private \stdClass $student;
    /** @var \stdClass Student enrolled only in another course. */
    private \stdClass $outsider;

    /**
     * Two courses, a teacher in the first and a student in each.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();

        $this->course = $gen->create_course();
        $other = $gen->create_course();
        $this->teacher = $gen->create_user();
        $this->student = $gen->create_user();
        $this->outsider = $gen->create_user();
        $gen->enrol_user($this->teacher->id, $this->course->id, 'editingteacher');
        $gen->enrol_user($this->student->id, $this->course->id, 'student');
        $gen->enrol_user($this->outsider->id, $other->id, 'student');
    }

    /**
     * A course in separate-groups mode with a non-editing teacher and one student in each of two groups.
     *
     * @return array [teacher, student in the teacher's group, student in the other group, course]
     */
    private function separate_groups_course(): array {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $teacher = $gen->create_user();
        $mine = $gen->create_user();
        $theirs = $gen->create_user();
        $gen->enrol_user($teacher->id, $course->id, 'teacher');
        $gen->enrol_user($mine->id, $course->id, 'student');
        $gen->enrol_user($theirs->id, $course->id, 'student');
        $g1 = $gen->create_group(['courseid' => $course->id]);
        $g2 = $gen->create_group(['courseid' => $course->id]);
        $gen->create_group_member(['groupid' => $g1->id, 'userid' => $teacher->id]);
        $gen->create_group_member(['groupid' => $g1->id, 'userid' => $mine->id]);
        $gen->create_group_member(['groupid' => $g2->id, 'userid' => $theirs->id]);
        return [$teacher, $mine, $theirs, $course];
    }

    /**
     * Inserts a standard log row as if the event had happened.
     *
     * @param int $userid User the event belongs to.
     * @param int $courseid Course of the event (SITEID for logins).
     * @param string $eventname Fully qualified event class name.
     * @param string $action Event action ('viewed', 'loggedin').
     */
    private function log_event(int $userid, int $courseid, string $eventname, string $action): void {
        global $DB;
        $context = $courseid == SITEID ? \context_system::instance() : \context_course::instance($courseid);
        $DB->insert_record('logstore_standard_log', (object) [
            'eventname' => $eventname, 'component' => 'core', 'action' => $action, 'target' => 'course',
            'crud' => 'r', 'edulevel' => 0, 'contextid' => $context->id, 'contextlevel' => $context->contextlevel,
            'contextinstanceid' => $context->instanceid, 'userid' => $userid, 'courseid' => $courseid,
            'anonymous' => 0, 'timecreated' => time() - 60,
        ]);
    }

    /**
     * Issue 4: a teacher cannot read the features of a user who is not in the course.
     */
    public function test_get_student_features_rejects_user_outside_course(): void {
        $this->setUser($this->teacher);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('notenrolled', 'local_saipa'));
        get_student_features::execute($this->outsider->id, $this->course->id);
    }

    /**
     * Issue 4: the visit counts come from this course's log rows, not from site logins or other courses.
     */
    public function test_get_student_features_counts_only_this_course(): void {
        $this->log_event($this->student->id, $this->course->id, '\core\event\course_viewed', 'viewed');
        $this->log_event($this->student->id, $this->course->id, '\core\event\course_viewed', 'viewed');
        $this->log_event($this->student->id, SITEID, '\core\event\user_loggedin', 'loggedin');
        $other = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($this->student->id, $other->id, 'student');
        $this->log_event($this->student->id, $other->id, '\core\event\course_viewed', 'viewed');

        $this->setUser($this->teacher);
        $features = get_student_features::execute($this->student->id, $this->course->id);
        $this->assertSame(2, $features['login_count_7d']);
        $this->assertSame(2, $features['login_count_30d']);
    }

    /**
     * In separate groups, a teacher without accessallgroups reaches only the students of their groups.
     */
    public function test_get_student_features_respects_separate_groups(): void {
        [$teacher, $mine, $theirs, $course] = $this->separate_groups_course();
        $this->setUser($teacher);
        $this->assertSame((int) $mine->id, get_student_features::execute($mine->id, $course->id)['user_id']);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('notsamegroup', 'local_saipa'));
        get_student_features::execute($theirs->id, $course->id);
    }

    /**
     * Issue 5: a teacher cannot message a linked user who is not in the course.
     */
    public function test_send_telegram_alert_rejects_user_outside_course(): void {
        global $DB;
        $DB->insert_record('local_saipa_telegram_links', (object) [
            'userid' => $this->outsider->id, 'telegram_id' => 123456, 'confirmed' => 1,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $this->setUser($this->teacher);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('notenrolled', 'local_saipa'));
        send_telegram_alert::execute($this->outsider->id, $this->course->id, 'Hello');
    }

    /**
     * Issue 5: a teacher in separate groups cannot message a student of another group.
     */
    public function test_send_telegram_alert_respects_separate_groups(): void {
        [$teacher, , $theirs, $course] = $this->separate_groups_course();
        $this->setUser($teacher);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('notsamegroup', 'local_saipa'));
        send_telegram_alert::execute($theirs->id, $course->id, 'Hello');
    }

    /**
     * Issue 5: the free text has a length limit, checked before anything is sent.
     */
    public function test_send_telegram_alert_rejects_long_message(): void {
        $this->setUser($this->teacher);
        $long = str_repeat('a', send_telegram_alert::MAX_MESSAGE_LENGTH + 1);
        $result = send_telegram_alert::execute($this->student->id, $this->course->id, $long);
        $this->assertSame(['sent' => false, 'error' => 'message_too_long'], $result);
    }

    /**
     * Issue 5: the alert cooldown setting stops a second alert to the same student.
     */
    public function test_send_telegram_alert_applies_cooldown(): void {
        global $DB;
        set_config('alert_cooldown_hours', 24, 'local_saipa');
        $DB->insert_record('local_saipa_notifications', (object) [
            'userid' => $this->student->id, 'template' => 'teacher_alert',
            'payload' => json_encode(['course_id' => $this->course->id]), 'status' => 'sent',
            'timesent' => time() - HOURSECS,
        ]);
        $this->setUser($this->teacher);
        $result = send_telegram_alert::execute($this->student->id, $this->course->id, 'Hello');
        $this->assertSame(['sent' => false, 'error' => 'cooldown'], $result);
    }

    /**
     * The course risk list only covers the students the teacher can see.
     */
    public function test_get_course_risk_lists_only_visible_students(): void {
        [$teacher, $mine, , $course] = $this->separate_groups_course();
        $this->setUser($teacher);
        $result = get_course_risk::execute($course->id, true);
        $this->assertSame([(int) $mine->id], array_column($result['results'], 'userid'));
    }
}
