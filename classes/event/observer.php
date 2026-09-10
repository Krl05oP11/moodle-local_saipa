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
 * Auto-indexes mod_page content when a page is created or updated.
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
     * Handles course_module_created && course_module_updated events.
     * Indexes content for supported module types: page, assign, label, forum, book.
     */
    public static function course_module_updated(\core\event\base $event): void {
        global $DB;

        $supported = ['page', 'assign', 'label', 'forum', 'book'];
        $modulename = $event->other['modulename'] ?? '';

        if (!in_array($modulename, $supported, true)) {
            return;
        }

        $cmid      = $event->objectid;
        $courseid = (int) $event->courseid;

        $cm = $DB->get_record('course_modules', ['id' => $cmid], 'instance', IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        $items = local_saipa_items_for_module($modulename, (int) $cm->instance, $courseid);
        if (empty($items)) {
            return;
        }

        $totalchunks = 0;
        $haserror    = false;

        foreach ($items as $item) {
            $response = local_saipa_engine_request('/index', [
                'course_id' => $courseid,
                'content'   => $item['text'],
                'source'    => $item['source'],
            ], 120);

            if (isset($response['error'])) {
                debugging('SAIPA auto-index error [' . $item['source'] . ']: ' . $response['error'], DEBUG_DEVELOPER);
                $haserror = true;
            } else {
                $totalchunks += (int) ($response['chunk_count'] ?? 1);
            }
        }

        if ($haserror && $totalchunks === 0) {
            return;
        }

        // Upsert saipa_course_index.
        $now    = time();
        $record = $DB->get_record('saipa_course_index', ['courseid' => $courseid]);
        if ($record) {
            $record->last_indexed  = $now;
            $record->chunk_count   = $record->chunk_count + $totalchunks;
            $record->status        = 'ready';
            $record->timemodified  = $now;
            $DB->update_record('saipa_course_index', $record);
        } else {
            $DB->insert_record('saipa_course_index', (object) [
                'courseid'     => $courseid,
                'last_indexed' => $now,
                'chunk_count'  => $totalchunks,
                'status'       => 'ready',
                'timecreated'  => $now,
                'timemodified' => $now,
            ]);
        }
    }
}
