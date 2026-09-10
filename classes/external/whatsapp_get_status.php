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
 * External web service: whatsapp_get_status
 * Returns the WhatsApp verification status for the current user.
 * Called via AJAX from block_saipa.
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
 * Whatsapp_get_status.
 */
class whatsapp_get_status extends external_api {
    /**
     * Define the parameters for this web service.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Execute the web service.
     */
    public static function execute(): array {
        global $USER, $DB;

        require_login();

        $record = $DB->get_record('saipa_phone_verify', ['userid' => (int) $USER->id]);

        if (!$record || !$record->verified) {
            return ['verified' => false, 'phone' => ''];
        }

        // Mask phone for display: show last 4 digits only.
        $phonedisplay = '****' . substr($record->phone, -4);

        return ['verified' => true, 'phone' => $phonedisplay];
    }

    /**
     * Define the return structure for this web service.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'verified' => new external_value(PARAM_BOOL, 'True if WhatsApp phone is verified'),
            'phone'    => new external_value(PARAM_TEXT, 'Masked phone number for display'),
        ]);
    }
}
