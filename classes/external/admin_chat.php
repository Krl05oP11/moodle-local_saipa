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
 * External web service: admin_chat
 * Contextual chat for advisors and admins — explains dashboard metrics and SAIPA procedures.
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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/saipa/lib.php');

class admin_chat extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'message' => new external_value(PARAM_TEXT, 'User message to the assistant'),
            'context' => new external_value(PARAM_ALPHANUMEXT, 'Context: advisor or teacher', VALUE_DEFAULT, 'advisor'),
        ]);
    }

    public static function execute(string $message, string $context = 'advisor'): array {
        global $CFG;

        $params = self::validate_parameters(self::execute_parameters(), [
            'message' => $message,
            'context' => $context,
        ]);

        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/saipa:view', $ctx);

        $payload = [
            'message' => $params['message'],
            'context' => $params['context'],
            'history' => [],
        ];

        $result = local_saipa_engine_request('/chat/advisor', $payload, 30);

        if ($result === false || !isset($result['reply'])) {
            return ['reply' => 'El asistente no está disponible en este momento. Verificá que el motor de SAIPA esté en funcionamiento.'];
        }

        return ['reply' => (string) $result['reply']];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'reply' => new external_value(PARAM_RAW, 'Assistant reply text'),
        ]);
    }
}
