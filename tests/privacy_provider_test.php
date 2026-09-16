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
 * PHPUnit tests for the local_saipa Privacy API provider.
 *
 * Focus: a student can have risk_scores/risk_history data in a course
 * without ever having a chat session there (e.g. a teacher-triggered risk
 * evaluation), and get_contexts_for_userid()/export_course_data() used to
 * miss that entirely -- verified fixed here, not just asserted.
 *
 * @package    local_saipa
 * @category   test
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_saipa;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_saipa\privacy\provider;

/**
 * Privacy provider tests for local_saipa.
 */
final class privacy_provider_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;

    /** @var \stdClass Teacher user. */
    private \stdClass $teacher;

    /** @var \stdClass Has a risk_scores row only, no session, no risk_history. */
    private \stdClass $scoresonly;

    /** @var \stdClass Has a risk_history row only, no session, no risk_scores. */
    private \stdClass $historyonly;

    /** @var \stdClass Has a chat session only, no risk data. */
    private \stdClass $sessiononly;

    /** @var \stdClass Has a session AND risk_scores AND risk_history in the same course. */
    private \stdClass $everything;

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);

        $gen = $this->getDataGenerator();

        $this->course      = $gen->create_course();
        $this->teacher      = $gen->create_user();
        $this->scoresonly   = $gen->create_user();
        $this->historyonly  = $gen->create_user();
        $this->sessiononly  = $gen->create_user();
        $this->everything   = $gen->create_user();

        $users = [
            $this->teacher, $this->scoresonly, $this->historyonly,
            $this->sessiononly, $this->everything,
        ];
        foreach ($users as $u) {
            $role = ($u === $this->teacher) ? 'editingteacher' : 'student';
            $gen->enrol_user($u->id, $this->course->id, $role);
        }

        $this->create_base_records();
    }

    /**
     * Insert the fixture rows described by the property docblocks above.
     */
    private function create_base_records(): void {
        global $DB;
        $now = time();
        $courseid = $this->course->id;
        $contextid = \context_course::instance($courseid)->id;

        $DB->insert_record('local_saipa_risk_scores', (object) [
            'userid'       => $this->scoresonly->id,
            'courseid'     => $courseid,
            'score'        => 0.85,
            'risk_level'   => 'high',
            'factors'      => '{}',
            'timecomputed' => $now,
        ]);

        $DB->insert_record('local_saipa_risk_history', (object) [
            'userid'       => $this->historyonly->id,
            'courseid'     => $courseid,
            'score'        => 0.42,
            'risk_level'   => 'medium',
            'timecomputed' => $now,
        ]);

        $sessionid = $DB->insert_record('local_saipa_sessions', (object) [
            'userid'       => $this->sessiononly->id,
            'courseid'     => $courseid,
            'contextid'    => $contextid,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
        $DB->insert_record('local_saipa_messages', (object) [
            'sessionid'   => $sessionid,
            'role'        => 'user',
            'content'     => 'Privacy test message',
            'timecreated' => $now,
        ]);

        $everysessionid = $DB->insert_record('local_saipa_sessions', (object) [
            'userid'       => $this->everything->id,
            'courseid'     => $courseid,
            'contextid'    => $contextid,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
        $DB->insert_record('local_saipa_risk_scores', (object) [
            'userid'       => $this->everything->id,
            'courseid'     => $courseid,
            'score'        => 0.20,
            'risk_level'   => 'low',
            'factors'      => '{}',
            'timecomputed' => $now,
        ]);
        $DB->insert_record('local_saipa_risk_history', (object) [
            'userid'       => $this->everything->id,
            'courseid'     => $courseid,
            'score'        => 0.20,
            'risk_level'   => 'low',
            'timecomputed' => $now,
        ]);
        unset($everysessionid);

        // System-context data, on the scoresonly user, to exercise that branch too.
        $DB->insert_record('local_saipa_phone_verify', (object) [
            'userid'      => $this->scoresonly->id,
            'phone'       => '+5491100000000',
            'otp'         => '123456',
            'verified'    => 1,
            'timecreated' => $now,
            'timeexpires' => $now + 900,
        ]);
        $DB->insert_record('local_saipa_telegram_links', (object) [
            'userid'            => $this->scoresonly->id,
            'telegram_id'       => 999999,
            'telegram_username' => 'privacytest',
            'confirmed'         => 1,
            'timecreated'       => $now,
            'timemodified'      => $now,
        ]);
    }

    // Metadata.

    /**
     * Metadata declares the personal-data tables, including risk_history.
     *
     * @covers \local_saipa\privacy\provider::get_metadata
     */
    public function test_get_metadata(): void {
        $collection = new collection('local_saipa');
        $collection = provider::get_metadata($collection);

        $names = [];
        foreach ($collection->get_collection() as $item) {
            $names[] = $item->get_name();
        }

        $this->assertContains('local_saipa_sessions', $names);
        $this->assertContains('local_saipa_messages', $names);
        $this->assertContains('local_saipa_risk_scores', $names);
        $this->assertContains('local_saipa_risk_history', $names);
        $this->assertContains('local_saipa_telegram_links', $names);
    }

    // Context discovery -- this is where the real bug lived.

    /**
     * User with no data returns an empty context list.
     *
     * @covers \local_saipa\privacy\provider::get_contexts_for_userid
     */
    public function test_get_contexts_for_userid_no_data(): void {
        $other = $this->getDataGenerator()->create_user();
        $contextlist = provider::get_contexts_for_userid($other->id);
        // The system context is always added, but no course context should be.
        $courseids = array_map('intval', $contextlist->get_contextids());
        $this->assertNotContains((int) \context_course::instance($this->course->id)->id, $courseids);
    }

    /**
     * Regression guard: the pre-existing session-based discovery still works.
     *
     * @covers \local_saipa\privacy\provider::get_contexts_for_userid
     */
    public function test_get_contexts_for_userid_session_only(): void {
        $contextlist = provider::get_contexts_for_userid($this->sessiononly->id);
        $contextids  = array_map('intval', $contextlist->get_contextids());
        $this->assertContains((int) \context_course::instance($this->course->id)->id, $contextids);
    }

    /**
     * The bug: a user with only a risk_scores row (no session) must still
     * have their course context discovered.
     *
     * @covers \local_saipa\privacy\provider::get_contexts_for_userid
     */
    public function test_get_contexts_for_userid_risk_scores_only(): void {
        $contextlist = provider::get_contexts_for_userid($this->scoresonly->id);
        $contextids  = array_map('intval', $contextlist->get_contextids());
        $this->assertContains((int) \context_course::instance($this->course->id)->id, $contextids);
    }

    /**
     * Same bug, risk_history side: a user with only a risk_history row (no
     * session, no risk_scores) must still have their course context found.
     *
     * @covers \local_saipa\privacy\provider::get_contexts_for_userid
     */
    public function test_get_contexts_for_userid_risk_history_only(): void {
        $contextlist = provider::get_contexts_for_userid($this->historyonly->id);
        $contextids  = array_map('intval', $contextlist->get_contextids());
        $this->assertContains((int) \context_course::instance($this->course->id)->id, $contextids);
    }

    /**
     * A user matched by all three source queries (session + risk_scores +
     * risk_history) in the same course must still resolve to ONE context,
     * not three -- contextlist must de-duplicate across add_from_sql() calls.
     *
     * @covers \local_saipa\privacy\provider::get_contexts_for_userid
     */
    public function test_get_contexts_for_userid_no_duplicate_when_multiple_sources(): void {
        $contextlist = provider::get_contexts_for_userid($this->everything->id);
        $courseid = (int) \context_course::instance($this->course->id)->id;
        $matches = array_filter($contextlist->get_contextids(), fn ($id) => (int) $id === $courseid);
        $this->assertCount(1, $matches);
    }

    // User listing.

    /**
     * The course context lists every user with data there, including the
     * risk_scores-only and risk_history-only users.
     *
     * @covers \local_saipa\privacy\provider::get_users_in_context
     */
    public function test_get_users_in_context_course(): void {
        $context  = \context_course::instance($this->course->id);
        $userlist = new userlist($context, 'local_saipa');
        provider::get_users_in_context($userlist);

        $userids = array_map('intval', $userlist->get_userids());
        $this->assertContains((int) $this->scoresonly->id, $userids);
        $this->assertContains((int) $this->historyonly->id, $userids);
        $this->assertContains((int) $this->sessiononly->id, $userids);
        $this->assertContains((int) $this->everything->id, $userids);
    }

    /**
     * The system context lists users with phone_verify/telegram_links data.
     *
     * @covers \local_saipa\privacy\provider::get_users_in_context
     */
    public function test_get_users_in_context_system(): void {
        $context  = \context_system::instance();
        $userlist = new userlist($context, 'local_saipa');
        provider::get_users_in_context($userlist);

        $userids = array_map('intval', $userlist->get_userids());
        $this->assertContains((int) $this->scoresonly->id, $userids);
    }

    // Export -- the second half of the bug: risk_history was never exported.

    /**
     * A user discovered only via risk_history must have that history
     * actually written by export_user_data(), not just an empty course
     * export from a context that happens to be included.
     *
     * @covers \local_saipa\privacy\provider::export_user_data
     */
    public function test_export_user_data_includes_risk_history(): void {
        $contextlist = provider::get_contexts_for_userid($this->historyonly->id);
        $user = \core_user::get_user($this->historyonly->id);
        $approved = new approved_contextlist($user, 'local_saipa', $contextlist->get_contextids());

        writer::reset();
        writer::with_context(\context_system::instance());
        $this->setUser($this->historyonly);

        provider::export_user_data($approved);

        $context = \context_course::instance($this->course->id);
        $data = writer::with_context($context)->get_data([
            get_string('pluginname', 'local_saipa'),
            'risk_history',
        ]);

        $this->assertNotEmpty($data);
        $this->assertNotEmpty($data->history);
        $this->assertSame('medium', $data->history[0]->risk_level);
    }

    // Deletion.

    /**
     * delete_data_for_user removes session, risk_scores AND risk_history for
     * that user only, leaving other users' data in the same course intact.
     *
     * @covers \local_saipa\privacy\provider::delete_data_for_user
     */
    public function test_delete_data_for_user(): void {
        global $DB;

        $contextlist = provider::get_contexts_for_userid($this->everything->id);
        $user = \core_user::get_user($this->everything->id);
        $approved = new approved_contextlist($user, 'local_saipa', $contextlist->get_contextids());

        provider::delete_data_for_user($approved);

        $this->assertEmpty($DB->get_records(
            'local_saipa_sessions',
            ['userid' => $this->everything->id, 'courseid' => $this->course->id]
        ));
        $this->assertEmpty($DB->get_records(
            'local_saipa_risk_scores',
            ['userid' => $this->everything->id, 'courseid' => $this->course->id]
        ));
        $this->assertEmpty($DB->get_records(
            'local_saipa_risk_history',
            ['userid' => $this->everything->id, 'courseid' => $this->course->id]
        ));

        // Untouched.
        $this->assertNotEmpty($DB->get_records(
            'local_saipa_risk_scores',
            ['userid' => $this->scoresonly->id, 'courseid' => $this->course->id]
        ));
    }

    /**
     * delete_data_for_all_users_in_context wipes every table for everyone
     * in the course, including risk_history.
     *
     * @covers \local_saipa\privacy\provider::delete_data_for_all_users_in_context
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;

        $context = \context_course::instance($this->course->id);
        provider::delete_data_for_all_users_in_context($context);

        $this->assertSame(0, $DB->count_records('local_saipa_sessions', ['courseid' => $this->course->id]));
        $this->assertSame(0, $DB->count_records('local_saipa_risk_scores', ['courseid' => $this->course->id]));
        $this->assertSame(0, $DB->count_records('local_saipa_risk_history', ['courseid' => $this->course->id]));
    }

    /**
     * delete_data_for_users (bulk) removes data only for the approved users.
     *
     * @covers \local_saipa\privacy\provider::delete_data_for_users
     */
    public function test_delete_data_for_users(): void {
        global $DB;

        $context = \context_course::instance($this->course->id);
        $approved = new approved_userlist($context, 'local_saipa', [$this->historyonly->id]);
        provider::delete_data_for_users($approved);

        $this->assertSame(0, $DB->count_records(
            'local_saipa_risk_history',
            ['userid' => $this->historyonly->id, 'courseid' => $this->course->id]
        ));
        $this->assertSame(1, $DB->count_records(
            'local_saipa_risk_scores',
            ['userid' => $this->scoresonly->id, 'courseid' => $this->course->id]
        ));
    }
}
