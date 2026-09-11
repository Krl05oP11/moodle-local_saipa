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
 * PHPUnit tests for local_saipa\engine_security_helper.
 *
 * Run from the Moodle root:
 *   vendor/bin/phpunit --filter local_saipa local/saipa/tests/engine_security_helper_test.php
 *
 * This class exists to narrow, not widen, Moodle's SSRF protection — the
 * whole point is that it allows exactly one extra host and nothing else.
 * These tests exist to prove that, not just that the happy path works.
 *
 * @package    local_saipa
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_saipa\tests;

defined('MOODLE_INTERNAL') || die();

use local_saipa\engine_security_helper;

/**
 * Tests for engine_security_helper.
 *
 * @covers \local_saipa\engine_security_helper
 */
final class engine_security_helper_test extends \advanced_testcase {
    /**
     * Set up test fixtures.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        // These tests are meaningless unless Moodle's default private-range
        // blocklist is actually active, so pin it explicitly rather than
        // trusting whatever the test site happened to install with.
        set_config(
            'curlsecurityblockedhosts',
            "127.0.0.0/8\n192.168.0.0/16\n10.0.0.0/8\n172.16.0.0/12\n0.0.0.0\nlocalhost\n169.254.169.254\n0000::1"
        );
        set_config('curlsecurityallowedport', "443\n80\n8052");
    }

    /**
     * The exact configured engine host must be allowed even though it is
     * inside a blocked private range.
     */
    public function test_configured_engine_host_is_allowed(): void {
        $helper = new engine_security_helper('http://10.20.30.40:8052');
        $this->assertFalse($helper->url_is_blocked('http://10.20.30.40:8052/health'));
    }

    /**
     * A Docker-style hostname engine URL (the real deployment shape) must
     * also be allowed for itself — host comparison, not just IP literals.
     */
    public function test_configured_engine_hostname_is_allowed(): void {
        $helper = new engine_security_helper('http://saipa-saipa-engine:8052');
        $this->assertFalse($helper->url_is_blocked('http://saipa-saipa-engine:8052/health'));
    }

    /**
     * A DIFFERENT private-range host — not the configured engine — must
     * still be blocked. This is the test that proves the helper narrows
     * rather than opens Moodle's protection.
     */
    public function test_different_private_host_is_still_blocked(): void {
        $helper = new engine_security_helper('http://10.20.30.40:8052');
        $this->assertTrue($helper->url_is_blocked('http://10.20.30.99:9999/anything'));
        $this->assertTrue($helper->url_is_blocked('http://192.168.1.1/'));
        $this->assertTrue($helper->url_is_blocked('http://169.254.169.254/latest/meta-data/'));
    }

    /**
     * Same host, different port than the configured engine: still allowed —
     * this class compares host only, by design (the engine's port can
     * change without breaking the trust relationship).
     */
    public function test_same_host_different_port_is_allowed(): void {
        $helper = new engine_security_helper('http://10.20.30.40:8052');
        $this->assertFalse($helper->url_is_blocked('http://10.20.30.40:9999/health'));
    }

    /**
     * An unparsable URL must still be blocked (strict default), both when
     * constructing the helper and when checking a request against it.
     */
    public function test_unparsable_configured_url_trusts_nothing(): void {
        $helper = new engine_security_helper('not a url at all');
        $this->assertTrue($helper->url_is_blocked('http://10.20.30.40:8052/health'));
        $this->assertTrue($helper->url_is_blocked('not a url either'));
    }

    /**
     * An unparsable request URL is blocked even with a validly-configured helper.
     */
    public function test_unparsable_request_url_is_blocked(): void {
        $helper = new engine_security_helper('http://10.20.30.40:8052');
        $this->assertTrue($helper->url_is_blocked('not a url either'));
    }

    /**
     * A public host, not in any blocklist, must pass through to the stock
     * behaviour (allowed) exactly as it would without this helper.
     */
    public function test_public_host_falls_through_to_stock_behaviour(): void {
        $helper = new engine_security_helper('http://10.20.30.40:8052');
        $this->assertFalse($helper->url_is_blocked('https://example.com/'));
    }
}
