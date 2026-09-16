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
 * PHPUnit tests for the three server-to-server Telegram bridge endpoints:
 * mark_alert_responded, telegram_get_session, telegram_unlink_by_id.
 *
 * These calls have no Moodle browser session -- saipa-engine holds a
 * webservice token bound to a dedicated account and calls them for
 * arbitrary telegram_id values. Before this test/fix, none of the three
 * checked any capability at all: any account holding a token for
 * saipa_service (including, e.g., an admin's own token, or a leaked
 * engine token) could enumerate any user's PII by telegram_id, forge
 * alert-response state, or unlink any user's Telegram account.
 *
 * Run from the Moodle root:
 *   vendor/bin/phpunit --filter local_saipa local/saipa/tests/external_telegram_bridge_test.php
 *
 * @package    local_saipa
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_saipa;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/saipa/lib.php');

/**
 * Tests for the engine-bridge Telegram web services.
 *
 * @covers \local_saipa\external\mark_alert_responded
 * @covers \local_saipa\external\telegram_get_session
 * @covers \local_saipa\external\telegram_unlink_by_id
 */
final class external_telegram_bridge_test extends \advanced_testcase {
    /** @var \stdClass $student the linked Telegram user whose data the bridge calls touch */
    private \stdClass $student;
    /** @var \stdClass $plainuser a user with no special role or capability */
    private \stdClass $plainuser;
    /** @var \stdClass $manager a manager who has NOT been explicitly granted the bridge capability */
    private \stdClass $manager;
    /** @var \stdClass $enginebridge the dedicated account holding local/saipa:enginebridge at system context */
    private \stdClass $enginebridge;
    /** @var int telegram chat_id used by the linked student fixture */
    private int $telegramid = 555000111;

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void {
        global $DB;

        parent::setUp();
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();

        $this->student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->student->id, $course->id, 'student');

        $this->plainuser = $this->getDataGenerator()->create_user();

        $this->manager = $this->getDataGenerator()->create_user();
        $managerarchetyperoleid = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        role_assign($managerarchetyperoleid, $this->manager->id, \context_system::instance()->id);

        $this->enginebridge = $this->getDataGenerator()->create_user();
        $bridgeroleid = $this->getDataGenerator()->create_role(['shortname' => 'saipa_enginebridge']);
        assign_capability(
            'local/saipa:enginebridge',
            CAP_ALLOW,
            $bridgeroleid,
            \context_system::instance()
        );
        role_assign($bridgeroleid, $this->enginebridge->id, \context_system::instance()->id);

        // Confirmed Telegram link for the student, as if /start <token> already succeeded.
        $now = time();
        $DB->insert_record('local_saipa_telegram_links', (object) [
            'userid'            => $this->student->id,
            'telegram_id'       => $this->telegramid,
            'telegram_username' => 'estudiante_test',
            'link_token'        => null,
            'token_expires'     => null,
            'confirmed'         => 1,
            'timecreated'       => $now,
            'timemodified'      => $now,
        ]);

        // A pending (unresponded) teacher_alert for that student, matching
        // what mark_alert_responded's own query expects.
        $DB->insert_record('local_saipa_notifications', (object) [
            'userid'         => $this->student->id,
            'template'       => 'teacher_alert',
            'payload'        => json_encode(['course_id' => $course->id]),
            'status'         => 'sent',
            'provider_msgid' => null,
            'timesent'       => $now - 3600,
            'responded_at'   => null,
        ]);
    }

    // Mark_alert_responded.

    /**
     * A caller with no engine-bridge capability must not be able to forge
     * alert-responded state for an arbitrary telegram_id.
     */
    public function test_mark_alert_responded_denied_without_capability(): void {
        $this->setUser($this->plainuser);
        $this->expectException(\required_capability_exception::class);
        \local_saipa\external\mark_alert_responded::execute($this->telegramid);
    }

    /**
     * A manager does not get the engine-bridge capability just from the
     * default archetype grants -- it must be assigned explicitly, so a
     * leaked engine token can't reach beyond the four bridge functions
     * even if it were somehow bound to an admin-ish account.
     */
    public function test_mark_alert_responded_denied_for_manager_without_explicit_grant(): void {
        $this->setUser($this->manager);
        $this->expectException(\required_capability_exception::class);
        \local_saipa\external\mark_alert_responded::execute($this->telegramid);
    }

    /**
     * The dedicated engine-bridge account can mark a pending alert responded.
     */
    public function test_mark_alert_responded_allowed_with_capability(): void {
        $this->setUser($this->enginebridge);
        $result = \local_saipa\external\mark_alert_responded::execute($this->telegramid, time());
        $this->assertTrue($result['found']);
    }

    /**
     * No Moodle session at all must fail the same way the rest of the
     * plugin's external functions do.
     */
    public function test_mark_alert_responded_unauthenticated_fails(): void {
        $this->setUser(null);
        $this->expectException(\require_login_exception::class);
        \local_saipa\external\mark_alert_responded::execute($this->telegramid);
    }

    // Telegram_get_session.

    /**
     * A caller with no engine-bridge capability must not be able to
     * enumerate a user's PII (fullname, user_id, course_id) by telegram_id.
     */
    public function test_telegram_get_session_denied_without_capability(): void {
        $this->setUser($this->plainuser);
        $this->expectException(\required_capability_exception::class);
        \local_saipa\external\telegram_get_session::execute($this->telegramid);
    }

    /**
     * The dedicated engine-bridge account can resolve a linked telegram_id.
     */
    public function test_telegram_get_session_allowed_with_capability(): void {
        $this->setUser($this->enginebridge);
        $result = \local_saipa\external\telegram_get_session::execute($this->telegramid);
        $this->assertTrue($result['linked']);
        $this->assertEquals($this->student->id, $result['user_id']);
    }

    /**
     * No Moodle session at all must fail.
     */
    public function test_telegram_get_session_unauthenticated_fails(): void {
        $this->setUser(null);
        $this->expectException(\require_login_exception::class);
        \local_saipa\external\telegram_get_session::execute($this->telegramid);
    }

    // Telegram_unlink_by_id.

    /**
     * A caller with no engine-bridge capability must not be able to
     * unlink an arbitrary user's Telegram account.
     */
    public function test_telegram_unlink_by_id_denied_without_capability(): void {
        global $DB;
        $this->setUser($this->plainuser);
        $this->expectException(\required_capability_exception::class);
        \local_saipa\external\telegram_unlink_by_id::execute($this->telegramid);
        $this->assertTrue($DB->record_exists('local_saipa_telegram_links', ['telegram_id' => $this->telegramid]));
    }

    /**
     * The dedicated engine-bridge account can unlink a Telegram id.
     */
    public function test_telegram_unlink_by_id_allowed_with_capability(): void {
        global $DB;
        $this->setUser($this->enginebridge);
        $result = \local_saipa\external\telegram_unlink_by_id::execute($this->telegramid);
        $this->assertTrue($result['success']);
        $this->assertFalse($DB->record_exists('local_saipa_telegram_links', ['telegram_id' => $this->telegramid]));
    }

    /**
     * No Moodle session at all must fail.
     */
    public function test_telegram_unlink_by_id_unauthenticated_fails(): void {
        $this->setUser(null);
        $this->expectException(\require_login_exception::class);
        \local_saipa\external\telegram_unlink_by_id::execute($this->telegramid);
    }
}
