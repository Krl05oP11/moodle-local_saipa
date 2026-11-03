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
 * Adhoc task: index one course module's content into the SAIPA chat.
 *
 * @package    local_saipa
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_saipa\task;

/**
 * Sends the content of a created or updated module to the engine.
 *
 * Queued by \local_saipa\event\observer, so that saving an activity does not wait for
 * the engine (one HTTP request per page or book chapter).
 */
class index_module extends \core\task\adhoc_task {
    /**
     * Indexes the module, if it still exists and students can see it.
     */
    public function execute() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/saipa/lib.php');

        $data = $this->get_custom_data();
        $courseid = (int) $data->courseid;
        if (!$DB->record_exists('course', ['id' => $courseid])) {
            return;
        }
        $cm = get_fast_modinfo($courseid)->get_cms()[(int) $data->cmid] ?? null;
        if (!$cm || !local_saipa_cm_is_indexable($cm)) {
            mtrace('SAIPA: module ' . (int) $data->cmid . ' is gone or hidden from students, not indexed.');
            return;
        }

        $items = local_saipa_items_for_module($cm->modname, (int) $cm->instance, $courseid);
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
                mtrace('SAIPA auto-index error [' . $item['source'] . ']: ' . $response['error']);
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
        $record = $DB->get_record('local_saipa_course_index', ['courseid' => $courseid]);
        if ($record) {
            $record->last_indexed  = $now;
            $record->chunk_count   = $record->chunk_count + $totalchunks;
            $record->status        = 'ready';
            $record->timemodified  = $now;
            $DB->update_record('local_saipa_course_index', $record);
        } else {
            $DB->insert_record('local_saipa_course_index', (object) [
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
