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

class observer {
    /**
     * Handles course_module_created and course_module_updated events.
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
        $course_id = (int) $event->courseid;

        $cm = $DB->get_record('course_modules', ['id' => $cmid], 'instance', IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        $items = local_saipa_items_for_module($modulename, (int) $cm->instance, $course_id);
        if (empty($items)) {
            return;
        }

        $total_chunks = 0;
        $has_error    = false;

        foreach ($items as $item) {
            $response = local_saipa_engine_request('/index', [
                'course_id' => $course_id,
                'content'   => $item['text'],
                'source'    => $item['source'],
            ], 120);

            if (isset($response['error'])) {
                debugging('SAIPA auto-index error [' . $item['source'] . ']: ' . $response['error'], DEBUG_DEVELOPER);
                $has_error = true;
            } else {
                $total_chunks += (int) ($response['chunk_count'] ?? 1);
            }
        }

        if ($has_error && $total_chunks === 0) {
            return;
        }

        // Upsert saipa_course_index.
        $now    = time();
        $record = $DB->get_record('saipa_course_index', ['courseid' => $course_id]);
        if ($record) {
            $record->last_indexed  = $now;
            $record->chunk_count   = $record->chunk_count + $total_chunks;
            $record->status        = 'ready';
            $record->timemodified  = $now;
            $DB->update_record('saipa_course_index', $record);
        } else {
            $DB->insert_record('saipa_course_index', (object) [
                'courseid'     => $course_id,
                'last_indexed' => $now,
                'chunk_count'  => $total_chunks,
                'status'       => 'ready',
                'timecreated'  => $now,
                'timemodified' => $now,
            ]);
        }
    }
}
