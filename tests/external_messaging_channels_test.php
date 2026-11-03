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
 * PHPUnit tests: the user's own Telegram and WhatsApp services check context, capability and limits.
 *
 * Marketplace review MMR-462, issue 7 (context and capability checks) and
 * issue 13 (WhatsApp verification codes could be requested without limit).
 * No engine is configured in these tests, so every WhatsApp send fails after
 * the limits have been applied, which is what they check.
 *
 * Run from the Moodle root:
 *   vendor/bin/phpunit local/saipa/tests/external_messaging_channels_test.php
 *
 * @package    local_saipa
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_saipa;

use local_saipa\external\telegram_confirm_link;
use local_saipa\external\telegram_generate_link;
use local_saipa\external\telegram_unlink;
use local_saipa\external\whatsapp_confirm_otp;
use local_saipa\external\whatsapp_start_verify;

/**
 * Tests for the messaging channel services a student calls from block_saipa.
 *
 * @covers \local_saipa\local\access::require_chat_user
 * @covers \local_saipa\external\whatsapp_start_verify
 * @covers \local_saipa\external\whatsapp_confirm_otp
 * @covers \local_saipa\external\telegram_generate_link
 * @covers \local_saipa\external\telegram_unlink
 * @covers \local_saipa\external\telegram_confirm_link
 */
final class external_messaging_channels_test extends \advanced_testcase {
    /** @var \stdClass Course with SAIPA. */
    private \stdClass $course;
    /** @var \stdClass Student of $course. */
    private \stdClass $student;

    /**
     * A course and one student.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
    }

    /**
     * A user who is not in the course cannot start a WhatsApp verification from it.
     */
    public function test_start_verify_requires_course_access(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\require_login_exception::class);
        whatsapp_start_verify::execute('5491112345678', $this->course->id);
    }

    /**
     * Without a course id, the user must be able to use the SAIPA chat in some course.
     */
    public function test_start_verify_without_course_requires_chat_somewhere(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\required_capability_exception::class);
        whatsapp_start_verify::execute('5491112345678');
    }

    /**
     * A second code right after the first one is refused.
     */
    public function test_start_verify_waits_between_codes(): void {
        $this->setUser($this->student);
        $first = whatsapp_start_verify::execute('5491112345678', $this->course->id);
        $this->assertSame(get_string('whatsapp_otp_send_failed', 'local_saipa'), $first['detail']);
        $second = whatsapp_start_verify::execute('5491112345678', $this->course->id);
        $this->assertFalse($second['sent']);
        $this->assertSame(get_string('whatsapp_otp_rate_limited', 'local_saipa'), $second['detail']);
    }

    /**
     * One number gets at most MAX_PER_NUMBER codes per hour, whoever asks for them.
     */
    public function test_start_verify_limits_codes_per_number(): void {
        $limited = get_string('whatsapp_otp_rate_limited', 'local_saipa');
        for ($i = 0; $i < whatsapp_start_verify::MAX_PER_NUMBER; $i++) {
            $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'student'));
            $result = whatsapp_start_verify::execute('5491112345678', $this->course->id);
            $this->assertNotSame($limited, $result['detail']);
        }
        $this->setUser($this->student);
        $this->assertSame($limited, whatsapp_start_verify::execute('5491112345678', $this->course->id)['detail']);
        $this->assertNotSame($limited, whatsapp_start_verify::execute('5491187654321', $this->course->id)['detail']);
    }

    /**
     * Wrong codes cancel the current code after MAX_ATTEMPTS tries.
     */
    public function test_confirm_otp_cancels_code_after_too_many_attempts(): void {
        global $DB;
        $DB->insert_record('local_saipa_phone_verify', (object) [
            'userid' => $this->student->id, 'phone' => '5491112345678', 'otp' => '123456',
            'verified' => 0, 'timecreated' => time(), 'timeexpires' => time() + 600,
        ]);
        $this->setUser($this->student);
        for ($i = 1; $i < whatsapp_confirm_otp::MAX_ATTEMPTS; $i++) {
            $this->assertSame('otp_invalid', whatsapp_confirm_otp::execute('000000', $this->course->id)['error']);
        }
        $this->assertSame('otp_expired', whatsapp_confirm_otp::execute('000000', $this->course->id)['error']);
        // The right code no longer works either: a new one has to be requested.
        $this->assertSame('otp_expired', whatsapp_confirm_otp::execute('123456', $this->course->id)['error']);
    }

    /**
     * Generating a Telegram link from a course requires the chat capability there.
     */
    public function test_generate_link_requires_chat_capability(): void {
        $teacherrole = $this->getDataGenerator()->create_role();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $this->course->id, $teacherrole);
        $this->setUser($user);
        $this->expectException(\required_capability_exception::class);
        telegram_generate_link::execute($this->course->id);
    }

    /**
     * A user who no longer uses SAIPA anywhere can still remove their own Telegram link.
     */
    public function test_unlink_needs_no_capability(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $DB->insert_record('local_saipa_telegram_links', (object) [
            'userid' => $user->id, 'telegram_id' => 42, 'confirmed' => 1, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $this->setUser($user);
        $this->assertTrue(telegram_unlink::execute()['success']);
        $this->assertFalse($DB->record_exists('local_saipa_telegram_links', ['userid' => $user->id]));
    }

    /**
     * Confirming a Telegram link is an engine-bridge call: a student's own session cannot make it.
     */
    public function test_confirm_link_requires_engine_bridge(): void {
        $this->setUser($this->student);
        $this->expectException(\required_capability_exception::class);
        telegram_confirm_link::execute('token', 42);
    }
}
