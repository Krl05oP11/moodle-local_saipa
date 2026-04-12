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
 * External web service: save_institution_config
 * Saves global institution-wide settings via Moodle config API.
 * All parameters are optional — only the provided ones are updated.
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
 * Save_institution_config.
 */
class save_institution_config extends external_api {
    /**
     * Define the parameters for this web service.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'risk_threshold_medium'  => new external_value(PARAM_FLOAT, 'Medium risk threshold (0-1)', VALUE_DEFAULT, null),
            'risk_threshold_high'    => new external_value(PARAM_FLOAT, 'High risk threshold (0-1)', VALUE_DEFAULT, null),
            'alert_cooldown_hours'   => new external_value(PARAM_INT, 'Min hours between alerts to same student', VALUE_DEFAULT, null),
            'data_retention_days'    => new external_value(PARAM_INT, 'Days to keep messages/scores (GDPR)', VALUE_DEFAULT, null),
            'default_alert_template' => new external_value(PARAM_TEXT, 'Default alert message template', VALUE_DEFAULT, null),
            'risk_eval_enabled'      => new external_value(PARAM_BOOL, 'Risk evaluation globally on/off', VALUE_DEFAULT, null),
            'rag_global_enabled'     => new external_value(PARAM_BOOL, 'RAG globally on/off', VALUE_DEFAULT, null),
        ]);
    }

    /**
     * Execute the web service.
     */
    public static function execute(
        ?float $riskthresholdmedium = null,
        ?float $riskthresholdhigh = null,
        ?int $alertcooldownhours = null,
        ?int $dataretentiondays = null,
        ?string $defaultalerttemplate = null,
        ?bool $riskevalenabled = null,
        ?bool $ragglobalenabled = null
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'risk_threshold_medium'  => $riskthresholdmedium,
            'risk_threshold_high'    => $riskthresholdhigh,
            'alert_cooldown_hours'   => $alertcooldownhours,
            'data_retention_days'    => $dataretentiondays,
            'default_alert_template' => $defaultalerttemplate,
            'risk_eval_enabled'      => $riskevalenabled,
            'rag_global_enabled'     => $ragglobalenabled,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/saipa:manage', $context);

        // Validate thresholds are in [0, 1] range && medium < high.
        if ($params['risk_threshold_medium'] !== null) {
            $med = (float) $params['risk_threshold_medium'];
            if ($med < 0.0 || $med > 1.0) {
                return ['success' => false, 'message' => 'risk_threshold_medium must be between 0 && 1'];
            }
        }
        if ($params['risk_threshold_high'] !== null) {
            $high = (float) $params['risk_threshold_high'];
            if ($high < 0.0 || $high > 1.0) {
                return ['success' => false, 'message' => 'risk_threshold_high must be between 0 && 1'];
            }
        }
        if ($params['risk_threshold_medium'] !== null && $params['risk_threshold_high'] !== null) {
            if ((float) $params['risk_threshold_medium'] >= (float) $params['risk_threshold_high']) {
                return ['success' => false, 'message' => 'risk_threshold_medium must be less than risk_threshold_high'];
            }
        }

        // Save only the values that were explicitly provided.
        $map = [
            'risk_threshold_medium'  => 'risk_threshold_medium',
            'risk_threshold_high'    => 'risk_threshold_high',
            'alert_cooldown_hours'   => 'alert_cooldown_hours',
            'data_retention_days'    => 'data_retention_days',
            'default_alert_template' => 'default_alert_template',
            'risk_eval_enabled'      => 'risk_eval_enabled',
            'rag_global_enabled'     => 'rag_global_enabled',
        ];

        foreach ($map as $paramkey => $configkey) {
            if ($params[$paramkey] !== null) {
                set_config($configkey, $params[$paramkey], 'local_saipa');
            }
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
