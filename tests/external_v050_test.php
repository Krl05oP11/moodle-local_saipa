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
 * PHPUnit tests for the 9 web services added in local_saipa v0.5.0.
 *
 * Covers: get_course_summary, get_my_courses, get_institution_summary,
 * get_risk_dashboard, get_engagement_stats, get_course_settings,
 * set_course_settings, save_institution_config, admin_chat.
 *
 * All WS are DB-only except admin_chat (which calls the engine).
 * Engine is not running during tests — admin_chat exercises the fallback path.
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
 * Tests for the v0.5.0 web services.
 */
final class external_v050_test extends \advanced_testcase {
    /** @var \stdClass Course used in tests. */
    private \stdClass $course;

    /** @var \stdClass Teacher user (editingteacher in $course). */
    private \stdClass $teacher;

    /** @var \stdClass Student user (student in $course). */
    private \stdClass $student;

    /** @var \stdClass Manager user with system-level advisor/viewall/manage caps. */
    private \stdClass $manager;

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest(true);

        $gen = $this->getDataGenerator();

        $this->course  = $gen->create_course();
        $this->teacher = $gen->create_user();
        $this->student = $gen->create_user();
        $this->manager = $gen->create_user();

        $gen->enrol_user($this->teacher->id, $this->course->id, 'editingteacher');
        $gen->enrol_user($this->student->id, $this->course->id, 'student');

        // Assign system-level capabilities to manager via a custom role.
        $syscontext = \context_system::instance();
        $roleid = $gen->create_role(['shortname' => 'saipamanager']);
        assign_capability('local/saipa:advisor', CAP_ALLOW, $roleid, $syscontext);
        assign_capability('local/saipa:viewall', CAP_ALLOW, $roleid, $syscontext);
        assign_capability('local/saipa:manage', CAP_ALLOW, $roleid, $syscontext);
        assign_capability('local/saipa:view', CAP_ALLOW, $roleid, $syscontext);
        role_assign($roleid, $this->manager->id, $syscontext->id);

        // Also enrol manager in the course for get_course_summary tests.
        $gen->enrol_user($this->manager->id, $this->course->id, 'editingteacher');

        // No engine — admin_chat will exercise fallback path.
        set_config('engine_url', '', 'local_saipa');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Insert a SAIPA session row and return its ID.
     */
    private function create_session(int $userid, int $courseid): int {
        global $DB;
        return (int) $DB->insert_record('saipa_sessions', (object) [
            'userid'      => $userid,
            'courseid'    => $courseid,
            'timecreated' => time(),
        ]);
    }

    /**
     * Insert a SAIPA message row and return its ID.
     */
    private function create_message(int $sessionid, string $role, string $content): int {
        global $DB;
        return (int) $DB->insert_record('saipa_messages', (object) [
            'sessionid'   => $sessionid,
            'role'        => $role,
            'content'     => $content,
            'timecreated' => time(),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 1. get_course_summary
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Empty course returns zeroed metrics.
     *
     * @covers \local_saipa\external\get_course_summary::execute
     */
    public function test_get_course_summary_empty_course(): void {
        $this->setUser($this->teacher);
        $r = \local_saipa\external\get_course_summary::execute($this->course->id);

        $this->assertSame(0, $r['active_saipa_users']);
        $this->assertSame(0, $r['total_messages']);
        $this->assertSame(0, $r['user_messages']);
        $this->assertSame(0, $r['assistant_messages']);
        $this->assertSame(0, $r['high_risk_count']);
        $this->assertSame(0, $r['medium_risk_count']);
        $this->assertSame(0, $r['low_risk_count']);
        $this->assertSame('pending', $r['index_status']);
        $this->assertEqualsWithDelta(-1.0, $r['feedback_ratio'], 0.001);
    }

    /**
     * With sessions and messages, summary reflects real data.
     *
     * @covers \local_saipa\external\get_course_summary::execute
     */
    public function test_get_course_summary_with_data(): void {
        global $DB;
        $this->setUser($this->teacher);

        $sid = $this->create_session($this->student->id, $this->course->id);
        $this->create_message($sid, 'user', 'Hola');
        $this->create_message($sid, 'assistant', 'Hola, en qué puedo ayudarte?');

        $r = \local_saipa\external\get_course_summary::execute($this->course->id);

        $this->assertSame(1, $r['active_saipa_users']);
        $this->assertSame(2, $r['total_messages']);
        $this->assertSame(1, $r['user_messages']);
        $this->assertSame(1, $r['assistant_messages']);
    }

    /**
     * Student without view capability is rejected.
     *
     * @covers \local_saipa\external\get_course_summary::execute
     */
    public function test_get_course_summary_requires_view_capability(): void {
        $this->setUser($this->student);
        $this->expectException(\required_capability_exception::class);
        \local_saipa\external\get_course_summary::execute($this->course->id);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 2. get_my_courses
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Manager sees courses that have SAIPA sessions.
     *
     * @covers \local_saipa\external\get_my_courses::execute
     */
    public function test_get_my_courses_returns_active_courses(): void {
        $this->setUser($this->manager);

        // Create a SAIPA session so the course appears.
        $this->create_session($this->student->id, $this->course->id);

        $r = \local_saipa\external\get_my_courses::execute();

        $this->assertArrayHasKey('courses', $r);
        $this->assertGreaterThanOrEqual(1, count($r['courses']));

        $found = false;
        foreach ($r['courses'] as $c) {
            if ((int) $c['courseid'] === (int) $this->course->id) {
                $found = true;
                $this->assertSame('pending', $c['index_status']);
                break;
            }
        }
        $this->assertTrue($found, 'Course with SAIPA session must appear in result');
    }

    /**
     * Course without SAIPA sessions does not appear (for admin).
     *
     * @covers \local_saipa\external\get_my_courses::execute
     */
    public function test_get_my_courses_excludes_inactive_courses(): void {
        $this->setUser($this->manager);

        // No sessions created — course should not appear.
        $r = \local_saipa\external\get_my_courses::execute();

        $found = false;
        foreach ($r['courses'] as $c) {
            if ((int) $c['courseid'] === (int) $this->course->id) {
                $found = true;
            }
        }
        $this->assertFalse($found, 'Course with no SAIPA sessions must NOT appear');
    }

    /**
     * Student without viewall capability is rejected.
     *
     * @covers \local_saipa\external\get_my_courses::execute
     */
    public function test_get_my_courses_requires_viewall_capability(): void {
        $this->setUser($this->student);
        $this->expectException(\required_capability_exception::class);
        \local_saipa\external\get_my_courses::execute();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 3. get_institution_summary
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Empty institution returns zeroed metrics.
     *
     * @covers \local_saipa\external\get_institution_summary::execute
     */
    public function test_get_institution_summary_empty(): void {
        $this->setUser($this->manager);
        $r = \local_saipa\external\get_institution_summary::execute('30d');

        $this->assertSame(0, $r['active_courses']);
        $this->assertSame(0, $r['total_saipa_users']);
        $this->assertSame(0, $r['total_messages']);
        $this->assertEqualsWithDelta(0.0, $r['adoption_rate'], 0.001);
        $this->assertEqualsWithDelta(0.0, $r['positive_feedback_pct'], 0.001);
        $this->assertEmpty($r['trend_messages']);
        $this->assertEmpty($r['trend_new_users']);
    }

    /**
     * Period parameter is accepted.
     *
     * @covers \local_saipa\external\get_institution_summary::execute
     */
    public function test_get_institution_summary_period_7d(): void {
        $this->setUser($this->manager);
        $r = \local_saipa\external\get_institution_summary::execute('7d');

        // Just verify it returns without error and has expected keys.
        $this->assertArrayHasKey('active_courses', $r);
        $this->assertArrayHasKey('total_messages', $r);
        $this->assertArrayHasKey('trend_messages', $r);
    }

    /**
     * Student without advisor capability is rejected.
     *
     * @covers \local_saipa\external\get_institution_summary::execute
     */
    public function test_get_institution_summary_requires_advisor(): void {
        $this->setUser($this->student);
        $this->expectException(\required_capability_exception::class);
        \local_saipa\external\get_institution_summary::execute();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 4. get_risk_dashboard
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Empty funnel returns all zeros.
     *
     * @covers \local_saipa\external\get_risk_dashboard::execute
     */
    public function test_get_risk_dashboard_empty_funnel(): void {
        $this->setUser($this->manager);
        $r = \local_saipa\external\get_risk_dashboard::execute('30d', 0);

        $this->assertSame(0, $r['funnel']['evaluated']);
        $this->assertSame(0, $r['funnel']['identified_high']);
        $this->assertSame(0, $r['funnel']['received_alert']);
        $this->assertSame(0, $r['funnel']['responded_alert']);
        $this->assertSame(0, $r['funnel']['accessed_after']);
        $this->assertEmpty($r['risk_trend']);
        $this->assertEmpty($r['intervention_effectiveness']);
    }

    /**
     * courseid=0 aggregates across all courses.
     *
     * @covers \local_saipa\external\get_risk_dashboard::execute
     */
    public function test_get_risk_dashboard_all_courses(): void {
        global $DB;
        $this->setUser($this->manager);

        // Insert a risk score.
        $DB->insert_record('saipa_risk_scores', (object) [
            'userid'       => $this->student->id,
            'courseid'     => $this->course->id,
            'score'        => 0.85,
            'risk_level'   => 'high',
            'factors_json' => '{}',
            'timecomputed' => time(),
        ]);

        $r = \local_saipa\external\get_risk_dashboard::execute('all', 0);

        $this->assertSame(1, $r['funnel']['evaluated']);
        $this->assertSame(1, $r['funnel']['identified_high']);
    }

    /**
     * Student without advisor capability is rejected.
     *
     * @covers \local_saipa\external\get_risk_dashboard::execute
     */
    public function test_get_risk_dashboard_requires_advisor(): void {
        $this->setUser($this->student);
        $this->expectException(\required_capability_exception::class);
        \local_saipa\external\get_risk_dashboard::execute();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 5. get_engagement_stats
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Empty data returns expected structure.
     *
     * @covers \local_saipa\external\get_engagement_stats::execute
     */
    public function test_get_engagement_stats_empty(): void {
        $this->setUser($this->manager);
        $r = \local_saipa\external\get_engagement_stats::execute('30d');

        $this->assertArrayHasKey('course_usage', $r);
        $this->assertArrayHasKey('hourly_heatmap', $r);
        $this->assertArrayHasKey('session_depth', $r);
        $this->assertEmpty($r['course_usage']);
        $this->assertEmpty($r['hourly_heatmap']);
        $this->assertCount(3, $r['session_depth']);
        $this->assertSame('1-3', $r['session_depth'][0]['bucket']);
        $this->assertSame('4-10', $r['session_depth'][1]['bucket']);
        $this->assertSame('11+', $r['session_depth'][2]['bucket']);
    }

    /**
     * With messages, course_usage and session_depth reflect data.
     *
     * @covers \local_saipa\external\get_engagement_stats::execute
     */
    public function test_get_engagement_stats_with_data(): void {
        $this->setUser($this->manager);

        $sid = $this->create_session($this->student->id, $this->course->id);
        $this->create_message($sid, 'user', 'Hola');
        $this->create_message($sid, 'assistant', 'Respuesta');

        $r = \local_saipa\external\get_engagement_stats::execute('all');

        $this->assertCount(1, $r['course_usage']);
        $this->assertSame((int) $this->course->id, $r['course_usage'][0]['courseid']);
        $this->assertSame(2, $r['course_usage'][0]['message_count']);
        $this->assertSame(1, $r['course_usage'][0]['unique_users']);

        // 2 messages in one session → bucket '1-3'.
        $this->assertSame(1, $r['session_depth'][0]['count']);
    }

    /**
     * Student without advisor capability is rejected.
     *
     * @covers \local_saipa\external\get_engagement_stats::execute
     */
    public function test_get_engagement_stats_requires_advisor(): void {
        $this->setUser($this->student);
        $this->expectException(\required_capability_exception::class);
        \local_saipa\external\get_engagement_stats::execute();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 6. get_course_settings
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Without settings row, defaults are all true.
     *
     * @covers \local_saipa\external\get_course_settings::execute
     */
    public function test_get_course_settings_defaults(): void {
        $this->setUser($this->manager);

        // Need a session so the course appears.
        $this->create_session($this->student->id, $this->course->id);

        $r = \local_saipa\external\get_course_settings::execute();

        $this->assertArrayHasKey('courses', $r);
        $found = false;
        foreach ($r['courses'] as $c) {
            if ((int) $c['courseid'] === (int) $this->course->id) {
                $found = true;
                $this->assertTrue($c['saipa_enabled']);
                $this->assertTrue($c['chat_enabled']);
                $this->assertTrue($c['risk_enabled']);
                $this->assertTrue($c['alerts_enabled']);
                $this->assertTrue($c['rag_enabled']);
                $this->assertSame('pending', $c['index_status']);
                break;
            }
        }
        $this->assertTrue($found, 'Course must appear in settings list');
    }

    /**
     * With saved settings, values reflect what was stored.
     *
     * @covers \local_saipa\external\get_course_settings::execute
     */
    public function test_get_course_settings_with_saved_values(): void {
        global $DB;
        $this->setUser($this->manager);

        $this->create_session($this->student->id, $this->course->id);

        // Insert custom settings.
        $DB->insert_record('saipa_course_settings', (object) [
            'courseid'       => $this->course->id,
            'saipa_enabled'  => 1,
            'chat_enabled'   => 0,
            'risk_enabled'   => 1,
            'alerts_enabled' => 0,
            'rag_enabled'    => 1,
            'timecreated'    => time(),
            'timemodified'   => time(),
        ]);

        $r = \local_saipa\external\get_course_settings::execute();

        foreach ($r['courses'] as $c) {
            if ((int) $c['courseid'] === (int) $this->course->id) {
                $this->assertTrue($c['saipa_enabled']);
                $this->assertFalse($c['chat_enabled']);
                $this->assertTrue($c['risk_enabled']);
                $this->assertFalse($c['alerts_enabled']);
                $this->assertTrue($c['rag_enabled']);
                break;
            }
        }
    }

    /**
     * Student without advisor capability is rejected.
     *
     * @covers \local_saipa\external\get_course_settings::execute
     */
    public function test_get_course_settings_requires_advisor(): void {
        $this->setUser($this->student);
        $this->expectException(\required_capability_exception::class);
        \local_saipa\external\get_course_settings::execute();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 7. set_course_settings
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * First save inserts a new settings row.
     *
     * @covers \local_saipa\external\set_course_settings::execute
     */
    public function test_set_course_settings_insert(): void {
        global $DB;
        $this->setUser($this->manager);

        $r = \local_saipa\external\set_course_settings::execute(
            $this->course->id,
            false  // saipa_enabled = false
        );

        $this->assertTrue($r['success']);

        $row = $DB->get_record('saipa_course_settings', ['courseid' => $this->course->id]);
        $this->assertNotFalse($row);
        $this->assertEquals(0, (int) $row->saipa_enabled);
        // Other flags default to true on insert.
        $this->assertEquals(1, (int) $row->chat_enabled);
        $this->assertEquals(1, (int) $row->risk_enabled);
    }

    /**
     * Partial update preserves existing values.
     *
     * @covers \local_saipa\external\set_course_settings::execute
     */
    public function test_set_course_settings_partial_update(): void {
        global $DB;
        $this->setUser($this->manager);

        // Insert initial row.
        \local_saipa\external\set_course_settings::execute($this->course->id, true, true, true, true, true);

        // Now update only chat_enabled to false.
        $r = \local_saipa\external\set_course_settings::execute($this->course->id, null, false);
        $this->assertTrue($r['success']);

        $row = $DB->get_record('saipa_course_settings', ['courseid' => $this->course->id]);
        $this->assertEquals(1, (int) $row->saipa_enabled, 'saipa_enabled must be preserved');
        $this->assertEquals(0, (int) $row->chat_enabled, 'chat_enabled must be updated to false');
        $this->assertEquals(1, (int) $row->risk_enabled, 'risk_enabled must be preserved');
    }

    /**
     * Student without advisor capability is rejected.
     *
     * @covers \local_saipa\external\set_course_settings::execute
     */
    public function test_set_course_settings_requires_advisor(): void {
        $this->setUser($this->student);
        $this->expectException(\required_capability_exception::class);
        \local_saipa\external\set_course_settings::execute($this->course->id, true);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 8. save_institution_config
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Valid thresholds are saved successfully.
     *
     * @covers \local_saipa\external\save_institution_config::execute
     */
    public function test_save_institution_config_valid_thresholds(): void {
        $this->setUser($this->manager);

        $r = \local_saipa\external\save_institution_config::execute(0.3, 0.7);

        $this->assertTrue($r['success']);
        $this->assertEmpty($r['message']);
        $this->assertSame('0.3', get_config('local_saipa', 'risk_threshold_medium'));
        $this->assertSame('0.7', get_config('local_saipa', 'risk_threshold_high'));
    }

    /**
     * Medium >= high returns error.
     *
     * @covers \local_saipa\external\save_institution_config::execute
     */
    public function test_save_institution_config_medium_ge_high(): void {
        $this->setUser($this->manager);

        $r = \local_saipa\external\save_institution_config::execute(0.7, 0.3);

        $this->assertFalse($r['success']);
        $this->assertStringContainsString('must be less than', $r['message']);
    }

    /**
     * Threshold out of range returns error.
     *
     * @covers \local_saipa\external\save_institution_config::execute
     */
    public function test_save_institution_config_out_of_range(): void {
        $this->setUser($this->manager);

        $r = \local_saipa\external\save_institution_config::execute(1.5);

        $this->assertFalse($r['success']);
        $this->assertStringContainsString('between 0', $r['message']);
    }

    /**
     * Partial save only updates provided values.
     *
     * @covers \local_saipa\external\save_institution_config::execute
     */
    public function test_save_institution_config_partial(): void {
        $this->setUser($this->manager);

        // Set initial value.
        set_config('alert_cooldown_hours', '12', 'local_saipa');

        // Save only data_retention_days.
        $r = \local_saipa\external\save_institution_config::execute(null, null, null, 365);
        $this->assertTrue($r['success']);

        // alert_cooldown_hours must be preserved.
        $this->assertSame('12', get_config('local_saipa', 'alert_cooldown_hours'));
        $this->assertSame('365', get_config('local_saipa', 'data_retention_days'));
    }

    /**
     * Student without manage capability is rejected.
     *
     * @covers \local_saipa\external\save_institution_config::execute
     */
    public function test_save_institution_config_requires_manage(): void {
        $this->setUser($this->student);
        $this->expectException(\required_capability_exception::class);
        \local_saipa\external\save_institution_config::execute(0.3, 0.7);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // 9. admin_chat
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * With no engine URL configured, returns fallback message.
     *
     * @covers \local_saipa\external\admin_chat::execute
     */
    public function test_admin_chat_no_engine_returns_fallback(): void {
        $this->setUser($this->manager);
        set_config('engine_url', '', 'local_saipa');

        $r = \local_saipa\external\admin_chat::execute('Hola');

        $this->assertArrayHasKey('reply', $r);
        $this->assertNotEmpty($r['reply']);
        $this->assertStringContainsString('asistente', $r['reply']);
    }

    /**
     * With unreachable engine, returns fallback without exception.
     *
     * @covers \local_saipa\external\admin_chat::execute
     */
    public function test_admin_chat_unreachable_engine_returns_fallback(): void {
        $this->setUser($this->manager);
        set_config('engine_url', 'http://127.0.0.1:19999', 'local_saipa');

        $r = \local_saipa\external\admin_chat::execute('Hola', 'advisor');

        $this->assertArrayHasKey('reply', $r);
        $this->assertNotEmpty($r['reply']);
    }

    /**
     * Student without view capability at system level is rejected.
     *
     * @covers \local_saipa\external\admin_chat::execute
     */
    public function test_admin_chat_requires_view_capability(): void {
        $this->setUser($this->student);
        $this->expectException(\required_capability_exception::class);
        \local_saipa\external\admin_chat::execute('Hola');
    }
}
