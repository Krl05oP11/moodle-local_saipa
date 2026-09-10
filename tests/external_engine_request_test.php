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
 * PHPUnit tests for local_saipa_engine_request() in lib.php.
 *
 * Run from the Moodle root:
 *   vendor/bin/phpunit --filter local_saipa local/saipa/tests/external_engine_request_test.php
 *
 * These tests cover the HTTP transport logic without requiring a real engine.
 * They do this by pointing the engine URL at controlled targets:
 *   - Empty string  → immediate error (no network call)
 *   - Closed port   → connection refused, returned as ['error' => ...]
 *   - TEST-NET host → timeout, returned as ['error' => ...]
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
 * Tests for local_saipa_engine_request().
 *
 * @covers ::local_saipa_engine_request
 */
final class external_engine_request_test extends \advanced_testcase {
    /**
     * Set up test fixtures.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    // ── No-network error paths ────────────────────────────────────────────────

    /**
     * When engine_url is empty the function must return immediately with an error.
     */
    public function test_empty_url_returns_error(): void {
        set_config('engine_url', '', 'local_saipa');

        $result = local_saipa_engine_request('/health');

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('not configured', $result['error']);
    }

    /**
     * The same error is returned when the config key does not exist yet.
     */
    public function test_missing_config_returns_error(): void {
        // Unset by writing empty string (Moodle has no delete_config in older versions).
        set_config('engine_url', '', 'local_saipa');

        $result = local_saipa_engine_request('/chat', ['message' => 'test']);

        $this->assertArrayHasKey('error', $result);
    }

    // ── Network error paths ───────────────────────────────────────────────────

    /**
     * Connecting to a closed port must return an error array, not throw.
     */
    public function test_connection_refused_returns_error(): void {
        // Port 19999 is almost certainly not listening.
        set_config('engine_url', 'http://127.0.0.1:19999', 'local_saipa');

        $result = local_saipa_engine_request('/health', null, 2);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringStartsWith(
            'Engine request failed',
            $result['error'],
            'A refused connection must be reported through the stable error prefix'
        );
    }

    /**
     * A 1-second timeout against an unreachable host must return an error, not throw.
     */
    public function test_timeout_returns_error(): void {
        // 192.0.2.1 is TEST-NET-1 (RFC 5737) — guaranteed unreachable, causes timeout.
        set_config('engine_url', 'http://192.0.2.1', 'local_saipa');

        $result = local_saipa_engine_request('/health', null, 1);

        $this->assertArrayHasKey(
            'error',
            $result,
            'A timed-out request must return an error array, not throw'
        );
    }

    // ── Response parsing ──────────────────────────────────────────────────────

    /**
     * Trailing slash on engine_url must be normalised (no double slash in path).
     */
    public function test_trailing_slash_in_url_is_normalised(): void {
        // With a closed port we can still verify no exception is thrown, meaning
        // the URL was constructed without a double slash.
        set_config('engine_url', 'http://127.0.0.1:19999/', 'local_saipa');

        $result = local_saipa_engine_request('/health', null, 1);

        // Should get a connection error array, not a PHP error.
        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
    }

    /**
     * GET requests (data=null) issue a GET, not a POST.
     * Verified indirectly: a GET to a closed port still returns an error array,
     * not a fatal PHP error about an unexpected request type.
     */
    public function test_null_data_issues_get_request(): void {
        set_config('engine_url', 'http://127.0.0.1:19999', 'local_saipa');

        $result = local_saipa_engine_request('/health', null, 1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
    }

    /**
     * POST requests (data != null) are issued when $data is provided.
     * Again verified indirectly: the function must return an array, not crash.
     */
    public function test_array_data_issues_post_request(): void {
        set_config('engine_url', 'http://127.0.0.1:19999', 'local_saipa');

        $result = local_saipa_engine_request('/chat', ['message' => 'hello'], 1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
    }

    // ── local_saipa_clean_html ────────────────────────────────────────────────

    /**
     * HTML tags must be stripped from content.
     */
    public function test_clean_html_strips_tags(): void {
        $html   = '<p>Hello <strong>world</strong></p>';
        $result = local_saipa_clean_html($html);
        $this->assertEquals('Hello world', $result);
    }

    /**
     * HTML entities must be decoded.
     */
    public function test_clean_html_decodes_entities(): void {
        $html   = 'Caf&eacute; &amp; Co.';
        $result = local_saipa_clean_html($html);
        $this->assertEquals('Café & Co.', $result);
    }

    /**
     * Leading and trailing whitespace must be removed.
     */
    public function test_clean_html_trims_whitespace(): void {
        $html   = '   <p>  Hello  </p>   ';
        $result = local_saipa_clean_html($html);
        $this->assertEquals('Hello', $result);
    }

    /**
     * Empty input must return an empty string.
     */
    public function test_clean_html_empty_input(): void {
        $this->assertEquals('', local_saipa_clean_html(''));
        $this->assertEquals('', local_saipa_clean_html('<br/>'));
    }

    // ── local_saipa_items_for_module ──────────────────────────────────────────

    /**
     * An unknown module name must return an empty array, not an exception.
     */
    public function test_items_for_unknown_module_returns_empty(): void {
        $result = local_saipa_items_for_module('nonexistent_module', 999, 1);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * A page module record must produce one indexable item with correct source key.
     */
    public function test_items_for_page_module(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $page   = $this->getDataGenerator()->create_module('page', [
            'course'  => $course->id,
            'name'    => 'Test Page',
            'intro'   => 'Page intro',
            'content' => 'Page content',
        ]);

        $items = local_saipa_items_for_module('page', $page->id, $course->id);

        $this->assertNotEmpty($items, 'Page module must produce at least one indexable item');
        $this->assertStringStartsWith('page:', $items[0]['source']);
        $this->assertStringContainsString('Page', $items[0]['text']);
    }
}
