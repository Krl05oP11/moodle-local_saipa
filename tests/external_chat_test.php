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
 * PHPUnit tests for local_saipa\external\chat.
 *
 * Run from the Moodle root:
 *   vendor/bin/phpunit --filter local_saipa local/saipa/tests/external_chat_test.php
 *
 * @package    local_saipa
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_saipa\tests;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/saipa/lib.php');

/**
 * Tests for the chat web service.
 *
 * The engine is not running during unit tests, so the test engine URL is left
 * unconfigured or set to an unreachable host. This exercises the error-handling
 * paths and, more importantly, the DB interaction (sessions, messages) that
 * happens regardless of whether the engine responds.
 *
 * @covers \local_saipa\external\chat
 */
final class external_chat_test extends \advanced_testcase {
    private \stdClass $course;
    private \stdClass $student;
    private \stdClass $teacher;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->course  = $this->getDataGenerator()->create_course();
        $this->student = $this->getDataGenerator()->create_user();
        $this->teacher = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($this->student->id, $this->course->id, 'student');
        $this->getDataGenerator()->enrol_user($this->teacher->id, $this->course->id, 'editingteacher');

        // No engine URL set — calls will fail gracefully with an error reply.
        set_config('engine_url', '', 'local_saipa');
    }

    // ── Session handling ──────────────────────────────────────────────────────

    /**
     * A new session row must be created for a fresh student/course combination.
     */
    public function test_new_session_is_created(): void {
        global $DB;
        $this->setUser($this->student);

        $before = $DB->count_records('saipa_sessions', [
            'userid'   => $this->student->id,
            'courseid' => $this->course->id,
        ]);
        $this->assertEquals(0, $before);

        \local_saipa\external\chat::execute($this->course->id, 'Hola', 0);

        $after = $DB->count_records('saipa_sessions', [
            'userid'   => $this->student->id,
            'courseid' => $this->course->id,
        ]);
        $this->assertEquals(1, $after, 'A new session must be created on first chat call');
    }

    /**
     * A second call reuses the existing session — no duplicate row.
     */
    public function test_existing_session_is_reused(): void {
        global $DB;
        $this->setUser($this->student);

        \local_saipa\external\chat::execute($this->course->id, 'Primero', 0);
        \local_saipa\external\chat::execute($this->course->id, 'Segundo', 0);

        $count = $DB->count_records('saipa_sessions', [
            'userid'   => $this->student->id,
            'courseid' => $this->course->id,
        ]);
        $this->assertEquals(1, $count, 'Second call must reuse the existing session');
    }

    /**
     * Passing an explicit session_id reuses that session row.
     */
    public function test_explicit_session_id_is_used(): void {
        global $DB;
        $this->setUser($this->student);

        $first  = \local_saipa\external\chat::execute($this->course->id, 'Mensaje 1', 0);
        $second = \local_saipa\external\chat::execute($this->course->id, 'Mensaje 2', $first['session_id']);

        $this->assertEquals(
            $first['session_id'],
            $second['session_id'],
            'Explicit session_id must be honoured'
        );
    }

    // ── Message persistence ───────────────────────────────────────────────────

    /**
     * The user message must be persisted to saipa_messages before the engine
     * is called (so history is available even when the engine fails).
     */
    public function test_user_message_is_persisted(): void {
        global $DB;
        $this->setUser($this->student);

        $result = \local_saipa\external\chat::execute($this->course->id, 'Test message', 0);

        $session = $DB->get_record('saipa_sessions', [
            'userid'   => $this->student->id,
            'courseid' => $this->course->id,
        ]);
        $this->assertNotFalse($session);

        $user_msgs = $DB->get_records('saipa_messages', [
            'sessionid' => $session->id,
            'role'      => 'user',
        ]);
        $this->assertCount(1, $user_msgs);
        $this->assertEquals('Test message', reset($user_msgs)->content);
    }

    /**
     * Two consecutive messages result in two user-role rows in saipa_messages.
     */
    public function test_two_messages_produce_two_rows(): void {
        global $DB;
        $this->setUser($this->student);

        $first = \local_saipa\external\chat::execute($this->course->id, 'Uno', 0);
        \local_saipa\external\chat::execute($this->course->id, 'Dos', $first['session_id']);

        $session = $DB->get_record('saipa_sessions', [
            'userid'   => $this->student->id,
            'courseid' => $this->course->id,
        ]);
        $count = $DB->count_records('saipa_messages', [
            'sessionid' => $session->id,
            'role'      => 'user',
        ]);
        $this->assertEquals(2, $count);
    }

    // ── Engine error handling ─────────────────────────────────────────────────

    /**
     * When the engine URL is empty the WS must return a graceful error reply,
     * not an exception.
     */
    public function test_unconfigured_engine_returns_error_reply(): void {
        $this->setUser($this->student);
        set_config('engine_url', '', 'local_saipa');

        $result = \local_saipa\external\chat::execute($this->course->id, 'Hola', 0);

        $this->assertArrayHasKey('reply', $result);
        $this->assertNotEmpty($result['reply'], 'Error reply must not be empty');
        $this->assertArrayHasKey('session_id', $result);
        $this->assertGreaterThan(
            0,
            $result['session_id'],
            'Session must still be created even when engine is unreachable'
        );
    }

    /**
     * When the engine URL points to an unreachable host, the WS still returns
     * without throwing an exception.
     */
    public function test_unreachable_engine_returns_error_reply(): void {
        $this->setUser($this->student);
        set_config('engine_url', 'http://127.0.0.1:19999', 'local_saipa');

        $result = \local_saipa\external\chat::execute($this->course->id, 'Hola', 0);

        $this->assertArrayHasKey('reply', $result);
        $this->assertArrayHasKey('session_id', $result);
    }

    // ── Access control ────────────────────────────────────────────────────────

    /**
     * Unauthenticated access must be rejected.
     */
    public function test_unauthenticated_fails(): void {
        $this->setUser(null);
        $this->expectException(\require_login_exception::class);
        \local_saipa\external\chat::execute($this->course->id, 'Hola', 0);
    }

    /**
     * A user with no saipa:chat capability must be rejected.
     */
    public function test_user_without_capability_fails(): void {
        // Create a user with no roles.
        $noncap = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($noncap->id, $this->course->id, 'student');

        // Revoke chat capability from the student role in this course.
        $context = \context_course::instance($this->course->id);
        assign_capability(
            'local/saipa:chat',
            CAP_PROHIBIT,
            $this->getDataGenerator()->create_role(['shortname' => 'nochat']),
            $context
        );
        role_assign(
            $this->getDataGenerator()->create_role(['shortname' => 'nochat2']),
            $noncap->id,
            $context
        );

        $this->setUser($noncap);
        $this->expectException(\required_capability_exception::class);
        \local_saipa\external\chat::execute($this->course->id, 'Hola', 0);
    }

    /**
     * Teachers enrolled in the course can call the chat WS.
     */
    public function test_teacher_can_call_chat(): void {
        $this->setUser($this->teacher);
        $result = \local_saipa\external\chat::execute($this->course->id, 'Pregunta docente', 0);
        $this->assertArrayHasKey('reply', $result);
        $this->assertArrayHasKey('session_id', $result);
    }
}
