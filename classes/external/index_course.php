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
 * External web service: index_course
 * Indexes all mod_page content from a course into the RAG vector store.
 *
 * @package    local_saipa
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_saipa\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;



/**
 * Index_course.
 */
class index_course extends external_api {
    /**
     * Define the parameters for this web service.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'course_id' => new external_value(PARAM_INT, 'Course ID'),
        ]);
    }

    /**
     * Execute the web service.
     */
    public static function execute(int $courseid): array {
        global $CFG;
        require_once($CFG->dirroot . '/local/saipa/lib.php');
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'course_id' => $courseid,
        ]);

        $context = \context_course::instance($params['course_id']);
        self::validate_context($context);
        require_capability('local/saipa:view', $context);

        $items = local_saipa_items_for_course($params['course_id']);

        $indexedcount = 0;
        $chunkcount   = 0;

        foreach ($items as $item) {
            $response = local_saipa_engine_request('/index', [
                'course_id' => $params['course_id'],
                'content'   => $item['text'],
                'source'    => $item['source'],
            ], 120);

            if (!isset($response['error'])) {
                $indexedcount++;
                $chunkcount += (int) ($response['chunk_count'] ?? 1);
            }
        }

        // Upsert saipa_course_index record.
        $now    = time();
        $record = $DB->get_record('saipa_course_index', ['courseid' => $params['course_id']]);
        if ($record) {
            $record->last_indexed  = $now;
            $record->chunk_count   = $chunkcount;
            $record->status        = 'ready';
            $record->timemodified  = $now;
            $DB->update_record('saipa_course_index', $record);
        } else {
            $DB->insert_record('saipa_course_index', (object) [
                'courseid'     => $params['course_id'],
                'last_indexed' => $now,
                'chunk_count'  => $chunkcount,
                'status'       => 'ready',
                'timecreated'  => $now,
                'timemodified' => $now,
            ]);
        }

        return [
            'indexed_count' => $indexedcount,
            'chunk_count'   => $chunkcount,
            'status'        => 'ready',
        ];
    }

    /**
     * Define the return structure for this web service.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'indexed_count' => new external_value(PARAM_INT, 'Number of content items indexed'),
            'chunk_count'   => new external_value(PARAM_INT, 'Total document chunks stored'),
            'status'        => new external_value(PARAM_TEXT, 'Indexing status'),
        ]);
    }
}
