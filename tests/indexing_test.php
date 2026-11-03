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
 * PHPUnit tests: what goes into the chat index, and when.
 *
 * Every student of a course can query its chat index, so hidden content must
 * stay out. Saving an activity only queues the indexing (Marketplace review
 * MMR-462, issue 11: the observer used to call the engine while the teacher
 * waited).
 *
 * Run from the Moodle root:
 *   vendor/bin/phpunit local/saipa/tests/indexing_test.php
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
 * Tests for local_saipa_items_for_course(), the observer and the index_module task.
 *
 * @covers ::local_saipa_items_for_course
 * @covers ::local_saipa_cm_is_indexable
 * @covers \local_saipa\event\observer
 * @covers \local_saipa\task\index_module
 */
final class indexing_test extends \advanced_testcase {
    /**
     * Sources of the items extracted for a course.
     *
     * @param int $courseid Course id.
     * @return string[]
     */
    private function sources(int $courseid): array {
        return array_column(local_saipa_items_for_course($courseid), 'source');
    }

    /**
     * Hidden modules, modules in hidden sections and hidden book chapters stay out of the index.
     */
    public function test_items_for_course_skips_hidden_content(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['numsections' => 2]);
        $visible = $gen->create_module('page', ['course' => $course->id, 'name' => 'Visible', 'section' => 1]);
        $hidden = $gen->create_module('page', ['course' => $course->id, 'name' => 'Hidden', 'visible' => 0]);
        $insection = $gen->create_module('page', ['course' => $course->id, 'name' => 'Insection', 'section' => 2]);
        set_section_visible($course->id, 2, 0);
        $book = $gen->create_module('book', ['course' => $course->id, 'name' => 'Book', 'intro' => 'Book intro']);
        $bookgen = $gen->get_plugin_generator('mod_book');
        $shown = $bookgen->create_chapter(['bookid' => $book->id, 'title' => 'Shown', 'content' => 'Shown text']);
        $secret = $bookgen->create_chapter(['bookid' => $book->id, 'title' => 'Secret', 'content' => 'Secret text']);
        $DB->set_field('book_chapters', 'hidden', 1, ['id' => $secret->id]);
        rebuild_course_cache($course->id, true);

        $sources = $this->sources($course->id);
        $this->assertContains('page:' . $visible->id . ':Visible', $sources);
        $this->assertNotContains('page:' . $hidden->id . ':Hidden', $sources);
        $this->assertNotContains('page:' . $insection->id . ':Insection', $sources);
        $this->assertContains('book_chapter:' . $shown->id . ':Shown', $sources);
        $this->assertNotContains('book_chapter:' . $secret->id . ':Secret', $sources);
    }

    /**
     * Updating a page queues one index_module task instead of calling the engine.
     */
    public function test_observer_queues_task(): void {
        $this->resetAfterTest();
        // The observer is not internal: Moodle runs it once no transaction is open, and by
        // default each test runs inside one.
        $this->preventResetByRollback();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $cm = get_coursemodule_from_id('page', $page->cmid);

        \core\event\course_module_updated::create_from_cm($cm)->trigger();
        \core\event\course_module_updated::create_from_cm($cm)->trigger();

        $tasks = \core\task\manager::get_adhoc_tasks('\\' . task\index_module::class);
        $this->assertCount(1, $tasks, 'Two saves before cron run queue the module once.');
        $data = reset($tasks)->get_custom_data();
        $this->assertEquals($page->cmid, $data->cmid);
        $this->assertEquals($course->id, $data->courseid);
    }

    /**
     * The task skips a module that was hidden after it was queued.
     */
    public function test_task_skips_module_hidden_since_queued(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        set_coursemodule_visible($page->cmid, 0);

        $task = new task\index_module();
        $task->set_custom_data(['cmid' => $page->cmid, 'courseid' => $course->id]);
        $this->expectOutputRegex('/is gone or hidden from students/');
        $task->execute();
    }
}
