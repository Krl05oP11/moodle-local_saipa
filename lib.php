<?php
// This file is part of Moodle - http://moodle.org/
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
 * Library functions for local_saipa.
 *
 * @package    local_saipa
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extends the navigation bar. Used in Fase 1 to add a Teacher Dashboard link.
 *
 * @param global_navigation $navigation
 */
function local_saipa_extend_navigation(global_navigation $navigation): void {
    // Fase 1: add course-level navigation nodes for the teacher dashboard.
}

/**
 * Makes a GET/POST request to saipa-engine.
 *
 * @param  string $endpoint  Path relative to engine root, e.g. '/health'
 * @param  array  $data      POST body as associative array (null for GET)
 * @return array             Decoded JSON response or ['error' => message]
 */
function local_saipa_engine_request(string $endpoint, ?array $data = null, int $timeout = 10): array {
    $engine_url = get_config('local_saipa', 'engine_url');
    $token      = get_config('local_saipa', 'engine_token');

    if (empty($engine_url)) {
        return ['error' => 'SAIPA engine URL not configured'];
    }

    $url = rtrim($engine_url, '/') . $endpoint;

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . ($token ?? ''),
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_HTTPHEADER     => $headers,
    ]);

    if ($data !== null) {
        $body = json_encode($data);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $response_str = curl_exec($ch);
    $errno        = curl_errno($ch);
    $error        = curl_error($ch);
    curl_close($ch);

    if ($errno) {
        return ['error' => 'cURL error (' . $errno . '): ' . $error];
    }

    if ($response_str === false || $response_str === '') {
        return ['error' => 'Empty response from engine'];
    }

    $decoded = json_decode($response_str, true);
    if ($decoded === null) {
        return ['error' => 'Invalid JSON from engine: ' . substr($response_str, 0, 300)];
    }

    return $decoded;
}

/**
 * Strips HTML tags and decodes entities for plain-text indexing.
 *
 * @param  string $html  Raw HTML content from Moodle
 * @return string        Clean plain text
 */
function local_saipa_clean_html(string $html): string {
    return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

/**
 * Extracts indexable items for a single module instance.
 * Returns array of ['source' => string, 'text' => string].
 *
 * Supported types: page, assign, label, forum, book (+ book chapters).
 *
 * @param  string $modulename   Moodle module name ('page', 'assign', etc.)
 * @param  int    $instance_id  Primary key in the module's own table
 * @param  int    $course_id    Course ID (unused for most types but kept for consistency)
 * @return array                Flat list of indexable items
 */
function local_saipa_items_for_module(string $modulename, int $instance_id, int $course_id): array {
    global $DB;
    $items = [];

    switch ($modulename) {
        case 'page':
            $rec = $DB->get_record('page', ['id' => $instance_id], 'id,name,intro,content', IGNORE_MISSING);
            if ($rec) {
                $text = local_saipa_clean_html($rec->intro . ' ' . $rec->content);
                if ($text !== '') {
                    $items[] = ['source' => 'page:' . $rec->id . ':' . $rec->name, 'text' => $text];
                }
            }
            break;

        case 'assign':
            $rec = $DB->get_record('assign', ['id' => $instance_id], 'id,name,intro,activity', IGNORE_MISSING);
            if ($rec) {
                $text = local_saipa_clean_html($rec->intro . ' ' . ($rec->activity ?? ''));
                if ($text !== '') {
                    $items[] = ['source' => 'assign:' . $rec->id . ':' . $rec->name, 'text' => $text];
                }
            }
            break;

        case 'label':
            $rec = $DB->get_record('label', ['id' => $instance_id], 'id,name,intro', IGNORE_MISSING);
            if ($rec) {
                $text = local_saipa_clean_html(($rec->name ?: '') . ' ' . $rec->intro);
                if ($text !== '') {
                    $items[] = ['source' => 'label:' . $rec->id . ':' . ($rec->name ?: 'label'), 'text' => $text];
                }
            }
            break;

        case 'forum':
            $rec = $DB->get_record('forum', ['id' => $instance_id], 'id,name,intro', IGNORE_MISSING);
            if ($rec) {
                $text = local_saipa_clean_html($rec->name . ' ' . $rec->intro);
                if ($text !== '') {
                    $items[] = ['source' => 'forum:' . $rec->id . ':' . $rec->name, 'text' => $text];
                }
            }
            break;

        case 'book':
            $book = $DB->get_record('book', ['id' => $instance_id], 'id,name,intro', IGNORE_MISSING);
            if ($book) {
                $intro_text = local_saipa_clean_html($book->intro);
                if ($intro_text !== '') {
                    $items[] = ['source' => 'book:' . $book->id . ':' . $book->name, 'text' => $intro_text];
                }
                $chapters = $DB->get_records('book_chapters', ['bookid' => $book->id], 'pagenum ASC', 'id,title,content');
                foreach ($chapters as $ch) {
                    $ch_text = local_saipa_clean_html($ch->title . ' ' . $ch->content);
                    if ($ch_text !== '') {
                        $items[] = ['source' => 'book_chapter:' . $ch->id . ':' . $ch->title, 'text' => $ch_text];
                    }
                }
            }
            break;
    }

    return $items;
}

/**
 * Extracts all indexable items for an entire course.
 * Includes the course summary and all supported module types.
 *
 * @param  int   $course_id
 * @return array Flat list of ['source' => string, 'text' => string]
 */
function local_saipa_items_for_course(int $course_id): array {
    global $DB;
    $items = [];

    // Course summary.
    $course = $DB->get_record('course', ['id' => $course_id], 'id,shortname,summary', IGNORE_MISSING);
    if ($course) {
        $text = local_saipa_clean_html($course->summary);
        if ($text !== '') {
            $items[] = ['source' => 'course:' . $course->id . ':' . $course->shortname, 'text' => $text];
        }
    }

    // All supported module types.
    foreach (['page', 'assign', 'label', 'forum', 'book'] as $modname) {
        $records = $DB->get_records($modname, ['course' => $course_id], '', 'id');
        foreach ($records as $rec) {
            $items = array_merge($items, local_saipa_items_for_module($modname, $rec->id, $course_id));
        }
    }

    return $items;
}
