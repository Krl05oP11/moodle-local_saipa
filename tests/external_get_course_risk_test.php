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
 * PHPUnit tests for local_saipa\external\get_course_risk.
 *
 * Run from the Moodle root:
 *   vendor/bin/phpunit --filter local_saipa local/saipa/tests/external_get_course_risk_test.php
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
 * Tests for get_course_risk web service.
 *
 * @covers \local_saipa\external\get_course_risk
 */
final class external_get_course_risk_test extends \advanced_testcase {
    private \stdClass $course;
    private \stdClass $teacher;
    private array $students = [];

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        // Create a course.
        $this->course  = $this->getDataGenerator()->create_course();

        // Create teacher and enrol with editingteacher role.
        $this->teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user(
            $this->teacher->id,
            $this->course->id,
            'editingteacher'
        );

        // Create 4 students.
        for ($i = 0; $i < 4; $i++) {
            $s = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user(
                $s->id,
                $this->course->id,
                'student'
            );
            $this->students[] = $s;
        }

        // Assign capabilities so the plugin can distinguish roles.
        $context = \context_course::instance($this->course->id);
        assign_capability(
            'local/saipa:view',
            CAP_ALLOW,
            $this->getDataGenerator()->create_role(['shortname' => 'saipa_teacher']),
            $context
        );
        assign_capability(
            'local/saipa:chat',
            CAP_ALLOW,
            $this->getDataGenerator()->create_role(['shortname' => 'saipa_student']),
            $context
        );
    }

    // ── Demo mode ─────────────────────────────────────────────────────────────

    /**
     * Demo mode must return one result per enrolled student without calling the engine.
     */
    public function test_demo_returns_result_for_each_student(): void {
        $this->setUser($this->teacher);

        $result = \local_saipa\external\get_course_risk::execute($this->course->id, true);

        // Must have results array.
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertEmpty($result['errors']);

        // One result per student (teacher excluded).
        $student_ids = array_map(fn($s) => $s->id, $this->students);
        $result_ids  = array_column($result['results'], 'userid');
        sort($student_ids);
        sort($result_ids);
        $this->assertEquals($student_ids, $result_ids);
    }

    /**
     * Demo results must have valid risk_level values.
     */
    public function test_demo_risk_levels_are_valid(): void {
        $this->setUser($this->teacher);
        $result = \local_saipa\external\get_course_risk::execute($this->course->id, true);

        $valid = ['low', 'medium', 'high'];
        foreach ($result['results'] as $r) {
            $this->assertContains(
                $r['risk_level'],
                $valid,
                "Invalid risk_level for user {$r['userid']}"
            );
            $this->assertGreaterThanOrEqual(0.0, $r['score']);
            $this->assertLessThanOrEqual(1.0, $r['score']);
        }
    }

    /**
     * Demo mode covers all 3 risk levels when there are >= 3 students.
     */
    public function test_demo_covers_all_risk_levels(): void {
        $this->setUser($this->teacher);
        // Add 2 more students so we cycle through all 6 demo profiles.
        for ($i = 0; $i < 2; $i++) {
            $s = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($s->id, $this->course->id, 'student');
        }

        $result = \local_saipa\external\get_course_risk::execute($this->course->id, true);
        $levels = array_unique(array_column($result['results'], 'risk_level'));
        sort($levels);
        $this->assertEquals(
            ['high', 'low', 'medium'],
            $levels,
            'Demo mode should produce all three risk levels'
        );
    }

    /**
     * Demo mode must NOT persist to saipa_risk_scores.
     */
    public function test_demo_does_not_persist(): void {
        global $DB;
        $this->setUser($this->teacher);

        $before = $DB->count_records('saipa_risk_scores', ['courseid' => $this->course->id]);
        \local_saipa\external\get_course_risk::execute($this->course->id, true);
        $after  = $DB->count_records('saipa_risk_scores', ['courseid' => $this->course->id]);

        $this->assertEquals(
            $before,
            $after,
            'Demo mode must not write to saipa_risk_scores'
        );
    }

    /**
     * Demo results must include factors as valid JSON.
     */
    public function test_demo_factors_are_valid_json(): void {
        $this->setUser($this->teacher);
        $result = \local_saipa\external\get_course_risk::execute($this->course->id, true);

        foreach ($result['results'] as $r) {
            $decoded = json_decode($r['factors'], true);
            $this->assertIsArray(
                $decoded,
                "factors must be valid JSON for user {$r['userid']}"
            );
            $this->assertNotEmpty(
                $decoded,
                "factors must not be empty for user {$r['userid']}"
            );
        }
    }

    /**
     * Calling demo mode with no enrolled students returns empty arrays.
     */
    public function test_demo_empty_course(): void {
        $this->setUser($this->teacher);
        $empty_course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user(
            $this->teacher->id,
            $empty_course->id,
            'editingteacher'
        );

        $result = \local_saipa\external\get_course_risk::execute($empty_course->id, true);
        $this->assertEmpty($result['results']);
        $this->assertEmpty($result['errors']);
    }

    // ── Access control ────────────────────────────────────────────────────────

    /**
     * A student must not be able to call get_course_risk.
     */
    public function test_student_cannot_call(): void {
        $this->setUser($this->students[0]);
        $this->expectException(\required_capability_exception::class);
        \local_saipa\external\get_course_risk::execute($this->course->id, true);
    }

    /**
     * Unauthenticated call must fail.
     */
    public function test_unauthenticated_fails(): void {
        $this->setUser(null);
        $this->expectException(\require_login_exception::class);
        \local_saipa\external\get_course_risk::execute($this->course->id, true);
    }
}
