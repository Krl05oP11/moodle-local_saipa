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
 * PHPUnit tests for local_saipa\task\risk_evaluation.
 *
 * Run:
 *   vendor/bin/phpunit --filter local_saipa local/saipa/tests/task_risk_evaluation_test.php
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
 * Tests for risk_evaluation scheduled task.
 *
 * These tests use a mock of local_saipa_engine_request() to avoid needing
 * a live saipa-engine instance.
 *
 * @covers \local_saipa\task\risk_evaluation
 */
final class task_risk_evaluation_test extends \advanced_testcase {
    private \stdClass $course;
    private \stdClass $teacher;
    private \stdClass $student;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();

        $this->course  = $this->getDataGenerator()->create_course();
        $this->teacher = $this->getDataGenerator()->create_user();
        $this->student = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user(
            $this->teacher->id,
            $this->course->id,
            'editingteacher'
        );
        $this->getDataGenerator()->enrol_user(
            $this->student->id,
            $this->course->id,
            'student'
        );

        // Seed a SAIPA session so the task finds this course.
        $DB->insert_record('saipa_sessions', (object) [
            'userid'       => $this->student->id,
            'courseid'     => $this->course->id,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);
    }

    // ── Task metadata ─────────────────────────────────────────────────────────

    public function test_task_has_name(): void {
        $task = new \local_saipa\task\risk_evaluation();
        $this->assertNotEmpty($task->get_name());
    }

    public function test_task_is_registered(): void {
        $tasks = \core\task\manager::get_all_scheduled_tasks();
        $found = false;
        foreach ($tasks as $t) {
            if ($t instanceof \local_saipa\task\risk_evaluation) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'risk_evaluation must be registered in db/tasks.php');
    }

    // ── No-activity guard ─────────────────────────────────────────────────────

    /**
     * Task must exit cleanly when no SAIPA sessions exist.
     */
    public function test_execute_with_no_sessions(): void {
        global $DB;
        $DB->delete_records('saipa_sessions');

        $task = new \local_saipa\task\risk_evaluation();
        // Should not throw.
        ob_start();
        $task->execute();
        $output = ob_get_clean();

        $this->assertStringContainsString('nothing to evaluate', $output);
    }

    // ── Score persistence ─────────────────────────────────────────────────────

    /**
     * After a successful evaluation, saipa_risk_scores must contain one row
     * per evaluated student in the course.
     *
     * This test injects a pre-existing score to verify the upsert path.
     */
    public function test_existing_score_is_updated(): void {
        global $DB;

        // Seed a previous score.
        $existing_id = $DB->insert_record('saipa_risk_scores', (object) [
            'userid'       => $this->student->id,
            'courseid'     => $this->course->id,
            'score'        => 0.3,
            'risk_level'   => 'low',
            'factors'      => '{}',
            'timecomputed' => time() - 86400,
        ]);

        // Simulate engine call by directly calling upsert via reflection.
        $task = new \local_saipa\task\risk_evaluation();
        $method = new \ReflectionMethod($task, 'upsert_score');
        $method->setAccessible(true);
        $method->invoke(
            $task,
            $this->student->id,
            $this->course->id,
            0.9,
            'high',
            '{"last_access_days":0.4}',
            time()
        );

        $row = $DB->get_record(
            'saipa_risk_scores',
            ['userid' => $this->student->id, 'courseid' => $this->course->id]
        );

        $this->assertEquals($existing_id, $row->id, 'Must UPDATE existing row, not INSERT new one');
        $this->assertEquals('high', $row->risk_level);
        $this->assertEqualsWithDelta(0.9, (float) $row->score, 0.001);
    }

    /**
     * When no previous score exists, upsert_score must INSERT.
     */
    public function test_new_score_is_inserted(): void {
        global $DB;

        $before = $DB->count_records('saipa_risk_scores');

        $task = new \local_saipa\task\risk_evaluation();
        $method = new \ReflectionMethod($task, 'upsert_score');
        $method->setAccessible(true);
        $method->invoke(
            $task,
            $this->student->id,
            $this->course->id,
            0.75,
            'high',
            '{"submission_rate":0.25}',
            time()
        );

        $after = $DB->count_records('saipa_risk_scores');
        $this->assertEquals($before + 1, $after, 'Must INSERT one new row');
    }

    // ── Escalation detection ──────────────────────────────────────────────────

    /**
     * get_previous_levels returns the correct prior risk level.
     */
    public function test_get_previous_levels(): void {
        global $DB;

        $DB->insert_record('saipa_risk_scores', (object) [
            'userid'       => $this->student->id,
            'courseid'     => $this->course->id,
            'score'        => 0.4,
            'risk_level'   => 'medium',
            'factors'      => '{}',
            'timecomputed' => time(),
        ]);

        $task   = new \local_saipa\task\risk_evaluation();
        $method = new \ReflectionMethod($task, 'get_previous_levels');
        $method->setAccessible(true);
        $levels = $method->invoke($task, $this->course->id, [$this->student->id]);

        $this->assertArrayHasKey($this->student->id, $levels);
        $this->assertEquals('medium', $levels[$this->student->id]);
    }

    /**
     * get_previous_levels returns empty array for course with no scores yet.
     */
    public function test_get_previous_levels_empty(): void {
        $task   = new \local_saipa\task\risk_evaluation();
        $method = new \ReflectionMethod($task, 'get_previous_levels');
        $method->setAccessible(true);
        $levels = $method->invoke($task, $this->course->id, [$this->student->id]);

        $this->assertEmpty($levels);
    }
}
