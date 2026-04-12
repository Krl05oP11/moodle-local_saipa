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
 * External web service: telegram_unlink_by_id
 * Removes the Telegram link for a given telegram_id (chat_id).
 * Called server-to-server by saipa-engine when the student sends /desconectar.
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
 * Telegram_unlink_by_id.
 */
class telegram_unlink_by_id extends external_api {
    /**
     * Define the parameters for this web service.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'telegram_id' => new external_value(PARAM_INT, 'Telegram chat_id to unlink'),
        ]);
    }

    /**
     * Execute the web service.
     */
    public static function execute(int $telegramid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['telegram_id' => $telegramid]);

        $DB->delete_records('saipa_telegram_links', ['telegram_id' => $params['telegram_id']]);

        return ['success' => true];
    }

    /**
     * Define the return structure for this web service.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'True if the link was removed'),
        ]);
    }
}
