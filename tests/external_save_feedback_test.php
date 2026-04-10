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
 * PHPUnit tests for local_saipa\external\save_feedback.
 *
 * Run:
 *   vendor/bin/phpunit --filter local_saipa local/saipa/tests/external_save_feedback_test.php
 *
 * @package    local_saipa
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_saipa\tests;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for save_feedback web service.
 *
 * @covers \local_saipa\external\save_feedback
 */
final class external_save_feedback_test extends \advanced_testcase {
    private \stdClass $course;
    private \stdClass $student;
    private int $session_id;
    private int $message_id;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();

        $this->course  = $this->getDataGenerator()->create_course();
        $this->student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user(
            $this->student->id,
            $this->course->id,
            'student'
        );

        // Seed a session and an assistant message.
        $this->session_id = $DB->insert_record('saipa_sessions', (object) [
            'userid'       => $this->student->id,
            'courseid'     => $this->course->id,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);

        $this->message_id = $DB->insert_record('saipa_messages', (object) [
            'sessionid'   => $this->session_id,
            'role'        => 'assistant',
            'content'     => 'Test assistant response',
            'timecreated' => time(),
        ]);
    }

    // ── Happy path ────────────────────────────────────────────────────────────

    public function test_thumbs_up_saves_rating_1(): void {
        global $DB;
        $this->setUser($this->student);

        $result = \local_saipa\external\save_feedback::execute(
            $this->course->id,
            $this->message_id,
            1
        );

        $this->assertTrue($result['success']);
        $row = $DB->get_record(
            'saipa_feedback',
            ['userid' => $this->student->id, 'messageid' => $this->message_id]
        );
        $this->assertNotFalse($row);
        $this->assertEquals(1, (int) $row->rating);
    }

    public function test_thumbs_down_saves_rating_minus1(): void {
        global $DB;
        $this->setUser($this->student);

        \local_saipa\external\save_feedback::execute(
            $this->course->id,
            $this->message_id,
            -1
        );

        $row = $DB->get_record(
            'saipa_feedback',
            ['userid' => $this->student->id, 'messageid' => $this->message_id]
        );
        $this->assertEquals(-1, (int) $row->rating);
    }

    /**
     * Calling save_feedback twice on the same message should UPDATE, not INSERT.
     * Only one row must exist for (userid, messageid).
     */
    public function test_second_call_upserts(): void {
        global $DB;
        $this->setUser($this->student);

        \local_saipa\external\save_feedback::execute(
            $this->course->id,
            $this->message_id,
            1
        );
        \local_saipa\external\save_feedback::execute(
            $this->course->id,
            $this->message_id,
            -1
        );

        $count = $DB->count_records('saipa_feedback', [
            'userid'    => $this->student->id,
            'messageid' => $this->message_id,
        ]);
        $this->assertEquals(1, $count, 'Should be exactly one row after two calls');

        $row = $DB->get_record(
            'saipa_feedback',
            ['userid' => $this->student->id, 'messageid' => $this->message_id]
        );
        $this->assertEquals(-1, (int) $row->rating, 'Rating should reflect last call');
    }

    // ── Validation ────────────────────────────────────────────────────────────

    /**
     * Rating values other than 1 or -1 must be rejected.
     */
    public function test_invalid_rating_rejected(): void {
        $this->setUser($this->student);
        $this->expectException(\invalid_parameter_exception::class);
        \local_saipa\external\save_feedback::execute(
            $this->course->id,
            $this->message_id,
            0
        );
    }

    /**
     * Student cannot rate a message that belongs to a different user's session.
     */
    public function test_cannot_rate_other_users_message(): void {
        global $DB;
        $this->setUser($this->student);

        // Create another student with their own session and message.
        $other = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($other->id, $this->course->id, 'student');

        $other_session = $DB->insert_record('saipa_sessions', (object) [
            'userid' => $other->id, 'courseid' => $this->course->id,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $other_message = $DB->insert_record('saipa_messages', (object) [
            'sessionid' => $other_session, 'role' => 'assistant',
            'content' => 'Other response', 'timecreated' => time(),
        ]);

        $this->expectException(\moodle_exception::class);
        \local_saipa\external\save_feedback::execute(
            $this->course->id,
            $other_message,
            1
        );
    }

    /**
     * Unenrolled user cannot call save_feedback.
     */
    public function test_unenrolled_user_rejected(): void {
        $stranger = $this->getDataGenerator()->create_user();
        $this->setUser($stranger);
        $this->expectException(\required_capability_exception::class);
        \local_saipa\external\save_feedback::execute(
            $this->course->id,
            $this->message_id,
            1
        );
    }
}
