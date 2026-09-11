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
 * Curl security helper that additionally trusts the configured SAIPA engine host.
 *
 * @package    local_saipa
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_saipa;

/**
 * Extends Moodle's default curl_security_helper to allow exactly one extra
 * host: the SAIPA engine the site administrator configured in this plugin's
 * own settings (a page gated by moodle/site:config). Everything else —
 * including any other private/internal address — is still handled by the
 * stock curlsecurityblockedhosts list, completely unmodified.
 *
 * Why this class exists at all: Moodle blocks RFC1918/loopback ranges by
 * default ('curlsecurityblockedhosts', admin/settings/security.php — this is
 * Moodle's own out-of-the-box default, not site-specific hardening). The
 * engine is meant to run on such an address (unreachable from outside the
 * Moodle server), so every deployment following the recommended topology
 * hits that block. Widening the site-wide blocklist to work around it would
 * also expose Moodle's own URL downloader and repository plugins to that
 * same range; this class narrows the exception to exactly the one host the
 * admin already opted into trusting by typing it into engine_url.
 */
final class engine_security_helper extends \core\files\curl_security_helper {
    /** @var string Lower-cased host that is explicitly trusted, or '' if unparsable. */
    private $trustedhost;

    /**
     * Remembers which host is trusted, parsed once from the engine URL.
     *
     * @param string $engineurl The configured engine URL (scheme://host[:port][/path]).
     */
    public function __construct(string $engineurl) {
        $this->trustedhost = self::extract_host($engineurl);
    }

    /**
     * Parses the host out of a URL, lower-cased. Returns '' if unparsable —
     * never throws, so a malformed $engineurl just means "nothing is trusted"
     * rather than a fatal in the caller.
     *
     * @param string $urlstring
     * @return string
     */
    private static function extract_host(string $urlstring): string {
        try {
            return strtolower((string) (new \moodle_url($urlstring))->get_host());
        } catch (\moodle_exception $e) {
            return '';
        }
    }

    /**
     * Checks a URL against the trusted engine host first, then the stock helper.
     *
     * @param string $urlstring
     * @param int|null $notused
     * @return bool true if blocked, false if allowed.
     */
    public function url_is_blocked($urlstring, $notused = null) {
        if ($this->trustedhost !== '' && self::extract_host($urlstring) === $this->trustedhost) {
            return false;
        }
        return parent::url_is_blocked($urlstring, $notused);
    }
}
