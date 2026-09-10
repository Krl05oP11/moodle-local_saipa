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
    $engineurl = get_config('local_saipa', 'engine_url');
    $token      = get_config('local_saipa', 'engine_token');

    if (empty($engineurl)) {
        return ['error' => 'SAIPA engine URL not configured'];
    }

    $url = rtrim($engineurl, '/') . $endpoint;

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

    $responsestr = curl_exec($ch);
    $errno        = curl_errno($ch);
    $error        = curl_error($ch);
    curl_close($ch);

    if ($errno) {
        return ['error' => 'cURL error (' . $errno . '): ' . $error];
    }

    if ($responsestr === false || $responsestr === '') {
        return ['error' => 'Empty response from engine'];
    }

    $decoded = json_decode($responsestr, true);
    if ($decoded === null) {
        return ['error' => 'Invalid JSON from engine: ' . substr($responsestr, 0, 300)];
    }

    return $decoded;
}

/**
 * The site's configured dropout-risk thresholds.
 *
 * Returned as [medium, high] and forwarded to saipa-engine so the risk_level
 * label it returns matches this site's settings. Falls back to the engine
 * defaults (medium 0.40, high 0.75) when the admin has not set them, or when
 * the stored pair is misconfigured (out of [0, 1] or medium >= high, which
 * would make one band unreachable — the settings UI does not range-check).
 *
 * @return array{0: float, 1: float}
 */
function local_saipa_risk_thresholds(): array {
    $defaults = [0.40, 0.75];

    $medium = get_config('local_saipa', 'risk_threshold_medium');
    $high   = get_config('local_saipa', 'risk_threshold_high');

    $medium = ($medium !== false && $medium !== '') ? (float) $medium : $defaults[0];
    $high   = ($high   !== false && $high   !== '') ? (float) $high   : $defaults[1];

    if ($medium <= 0.0 || $high > 1.0 || $medium >= $high) {
        return $defaults;
    }

    return [$medium, $high];
}

/**
 * Classifies a dropout-risk score (0.0–1.0) into 'low' | 'medium' | 'high'
 * using {@see local_saipa_risk_thresholds()}. Mirrors the engine's own banding
 * so demo mode and real mode agree under any threshold configuration.
 *
 * @param  float $score
 * @return string
 */
function local_saipa_risk_level(float $score): string {
    [$medium, $high] = local_saipa_risk_thresholds();
    if ($score >= $high) {
        return 'high';
    }
    if ($score >= $medium) {
        return 'medium';
    }
    return 'low';
}

/**
 * Strips HTML tags && decodes entities for plain-text indexing.
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
 * @param  int    $instanceid  Primary key in the module's own table
 * @param  int    $courseid    Course ID (unused for most types but kept for consistency)
 * @return array                Flat list of indexable items
 */
function local_saipa_items_for_module(string $modulename, int $instanceid, int $courseid): array {
    global $DB;
    $items = [];

    switch ($modulename) {
        case 'page':
            $rec = $DB->get_record('page', ['id' => $instanceid], 'id,name,intro,content', IGNORE_MISSING);
            if ($rec) {
                $text = local_saipa_clean_html($rec->intro . ' ' . $rec->content);
                if ($text !== '') {
                    $items[] = ['source' => 'page:' . $rec->id . ':' . $rec->name, 'text' => $text];
                }
            }
            break;

        case 'assign':
            $rec = $DB->get_record('assign', ['id' => $instanceid], 'id,name,intro,activity', IGNORE_MISSING);
            if ($rec) {
                $text = local_saipa_clean_html($rec->intro . ' ' . ($rec->activity ?? ''));
                if ($text !== '') {
                    $items[] = ['source' => 'assign:' . $rec->id . ':' . $rec->name, 'text' => $text];
                }
            }
            break;

        case 'label':
            $rec = $DB->get_record('label', ['id' => $instanceid], 'id,name,intro', IGNORE_MISSING);
            if ($rec) {
                $text = local_saipa_clean_html(($rec->name ?: '') . ' ' . $rec->intro);
                if ($text !== '') {
                    $items[] = ['source' => 'label:' . $rec->id . ':' . ($rec->name ?: 'label'), 'text' => $text];
                }
            }
            break;

        case 'forum':
            $rec = $DB->get_record('forum', ['id' => $instanceid], 'id,name,intro', IGNORE_MISSING);
            if ($rec) {
                $text = local_saipa_clean_html($rec->name . ' ' . $rec->intro);
                if ($text !== '') {
                    $items[] = ['source' => 'forum:' . $rec->id . ':' . $rec->name, 'text' => $text];
                }
            }
            break;

        case 'book':
            $book = $DB->get_record('book', ['id' => $instanceid], 'id,name,intro', IGNORE_MISSING);
            if ($book) {
                $introtext = local_saipa_clean_html($book->intro);
                if ($introtext !== '') {
                    $items[] = ['source' => 'book:' . $book->id . ':' . $book->name, 'text' => $introtext];
                }
                $chapters = $DB->get_records('book_chapters', ['bookid' => $book->id], 'pagenum ASC', 'id,title,content');
                foreach ($chapters as $ch) {
                    $chtext = local_saipa_clean_html($ch->title . ' ' . $ch->content);
                    if ($chtext !== '') {
                        $items[] = ['source' => 'book_chapter:' . $ch->id . ':' . $ch->title, 'text' => $chtext];
                    }
                }
            }
            break;
    }

    return $items;
}

/**
 * Extracts all indexable items for an entire course.
 * Includes the course summary && all supported module types.
 *
 * @param  int   $courseid
 * @return array Flat list of ['source' => string, 'text' => string]
 */
function local_saipa_items_for_course(int $courseid): array {
    global $DB;
    $items = [];

    // Course summary.
    $course = $DB->get_record('course', ['id' => $courseid], 'id,shortname,summary', IGNORE_MISSING);
    if ($course) {
        $text = local_saipa_clean_html($course->summary);
        if ($text !== '') {
            $items[] = ['source' => 'course:' . $course->id . ':' . $course->shortname, 'text' => $text];
        }
    }

    // All supported module types.
    foreach (['page', 'assign', 'label', 'forum', 'book'] as $modname) {
        $records = $DB->get_records($modname, ['course' => $courseid], '', 'id');
        foreach ($records as $rec) {
            $items = array_merge($items, local_saipa_items_for_module($modname, $rec->id, $courseid));
        }
    }

    return $items;
}
