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
 * Event observers for local_saipa.
 * Queues the indexing of a module's content when the module is created or updated.
 *
 * @package    local_saipa
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_saipa\event;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/local/saipa/lib.php');

/**
 * Observer.
 */
class observer {
    /**
     * Handles course_module_created and course_module_updated events.
     *
     * Observers run while the teacher saves the activity, so this only queues
     * \local_saipa\task\index_module; the task reads the content and calls the engine.
     *
     * @param \core\event\base $event The triggered event.
     */
    public static function course_module_updated(\core\event\base $event): void {
        if (!in_array($event->other['modulename'] ?? '', local_saipa_indexed_modules(), true)) {
            return;
        }
        $task = new \local_saipa\task\index_module();
        $task->set_custom_data(['cmid' => (int) $event->objectid, 'courseid' => (int) $event->courseid]);
        // The same module saved twice before cron runs is indexed once.
        \core\task\manager::queue_adhoc_task($task, true);
    }
}
