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
 * External web service: telegram_get_status
 * Returns the Telegram link status for the currently logged-in Moodle user.
 * Called via AJAX polling from block_saipa after the user clicks "Link Telegram".
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

class telegram_get_status extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    public static function execute(): array {
        global $USER, $DB;

        require_login();

        $record = $DB->get_record('saipa_telegram_links', ['userid' => (int) $USER->id]);

        if (!$record) {
            return ['linked' => false, 'telegram_username' => ''];
        }

        return [
            'linked'            => (bool) $record->confirmed,
            'telegram_username' => (string) ($record->telegram_username ?? ''),
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'linked'            => new external_value(PARAM_BOOL, 'True if the Telegram account is confirmed'),
            'telegram_username' => new external_value(PARAM_TEXT, 'Telegram @username (if linked)'),
        ]);
    }
}
