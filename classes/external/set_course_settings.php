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
 * External web service: set_course_settings
 * Upserts per-course feature flags in saipa_course_settings.
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
 * Set_course_settings.
 */
class set_course_settings extends external_api {
    /**
     * Define the parameters for this web service.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid'       => new external_value(PARAM_INT, 'Course ID'),
            'saipa_enabled'  => new external_value(PARAM_BOOL, 'SAIPA enabled', VALUE_DEFAULT, null),
            'chat_enabled'   => new external_value(PARAM_BOOL, 'Chat enabled', VALUE_DEFAULT, null),
            'risk_enabled'   => new external_value(PARAM_BOOL, 'Risk enabled', VALUE_DEFAULT, null),
            'alerts_enabled' => new external_value(PARAM_BOOL, 'Alerts enabled', VALUE_DEFAULT, null),
            'rag_enabled'    => new external_value(PARAM_BOOL, 'RAG enabled', VALUE_DEFAULT, null),
        ]);
    }

    /**
     * Execute the web service.
     */
    public static function execute(
        int $courseid,
        ?bool $saipaenabled = null,
        ?bool $chatenabled = null,
        ?bool $riskenabled = null,
        ?bool $alertsenabled = null,
        ?bool $ragenabled = null
    ): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid'       => $courseid,
            'saipa_enabled'  => $saipaenabled,
            'chat_enabled'   => $chatenabled,
            'risk_enabled'   => $riskenabled,
            'alerts_enabled' => $alertsenabled,
            'rag_enabled'    => $ragenabled,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/saipa:advisor', $context);

        $cid      = (int) $params['courseid'];
        $now      = time();
        $existing = $DB->get_record('saipa_course_settings', ['courseid' => $cid]);

        if ($existing) {
            $record = clone $existing;
            $record->timemodified = $now;
            if ($params['saipa_enabled'] !== null) {
                $record->saipa_enabled  = (int) $params['saipa_enabled'];
            }
            if ($params['chat_enabled'] !== null) {
                $record->chat_enabled   = (int) $params['chat_enabled'];
            }
            if ($params['risk_enabled'] !== null) {
                $record->risk_enabled   = (int) $params['risk_enabled'];
            }
            if ($params['alerts_enabled'] !== null) {
                $record->alerts_enabled = (int) $params['alerts_enabled'];
            }
            if ($params['rag_enabled'] !== null) {
                $record->rag_enabled    = (int) $params['rag_enabled'];
            }
            $DB->update_record('saipa_course_settings', $record);
        } else {
            $DB->insert_record('saipa_course_settings', (object) [
                'courseid'       => $cid,
                'saipa_enabled'  => (int) ($params['saipa_enabled'] ?? true),
                'chat_enabled'   => (int) ($params['chat_enabled'] ?? true),
                'risk_enabled'   => (int) ($params['risk_enabled'] ?? true),
                'alerts_enabled' => (int) ($params['alerts_enabled'] ?? true),
                'rag_enabled'    => (int) ($params['rag_enabled'] ?? true),
                'timecreated'    => $now,
                'timemodified'   => $now,
            ]);
        }

        return ['success' => true, 'message' => ''];
    }

    /**
     * Define the return structure for this web service.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the save succeeded'),
            'message' => new external_value(PARAM_TEXT, 'Error message if any'),
        ]);
    }
}
