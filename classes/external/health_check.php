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
 * External web service: health_check
 * Tests connectivity from Moodle to saipa-engine.
 *
 * @package    local_saipa
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_saipa\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/saipa/lib.php');

class health_check extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    public static function execute(): array {
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/saipa:manage', $context);

        $response = local_saipa_engine_request('/health');

        if (isset($response['error'])) {
            return [
                'reachable' => false,
                'message'   => $response['error'],
                'version'   => null,
            ];
        }

        return [
            'reachable' => true,
            'message'   => $response['message'] ?? 'OK',
            'version'   => $response['version'] ?? null,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'reachable' => new external_value(PARAM_BOOL, 'Whether the engine is reachable'),
            'message'   => new external_value(PARAM_TEXT, 'Status message'),
            'version'   => new external_value(PARAM_TEXT, 'Engine version', VALUE_OPTIONAL),
        ]);
    }
}
