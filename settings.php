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
 * Admin settings page for local_saipa.
 *
 * @package    local_saipa
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_saipa',
        get_string('pluginname', 'local_saipa')
    );

    $ADMIN->add('localplugins', $settings);

    // === Installation Status page link ===
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_saipa_status',
        get_string('settings:status_page', 'local_saipa'),
        new moodle_url('/local/saipa/status.php')
    ));

    // === Advisor Dashboard page link ===
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_saipa_advisor',
        get_string('advisor_title', 'local_saipa'),
        new moodle_url('/local/saipa/advisor.php'),
        'local/saipa:advisor'
    ));

    // === Section: Engine Connection ===
    $settings->add(new admin_setting_heading(
        'local_saipa/heading_engine',
        get_string('settings:heading_engine', 'local_saipa'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_saipa/engine_url',
        get_string('settings:engine_url', 'local_saipa'),
        get_string('settings:engine_url_desc', 'local_saipa'),
        'http://host.docker.internal:8052',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_saipa/engine_token',
        get_string('settings:engine_token', 'local_saipa'),
        get_string('settings:engine_token_desc', 'local_saipa'),
        ''
    ));

    // === Section: Messaging Channels ===
    $settings->add(new admin_setting_heading(
        'local_saipa/heading_messaging',
        get_string('settings:heading_messaging', 'local_saipa'),
        get_string('settings:heading_messaging_desc', 'local_saipa')
    ));

    $settings->add(new admin_setting_configselect(
        'local_saipa/messaging_channel',
        get_string('settings:messaging_channel', 'local_saipa'),
        get_string('settings:messaging_channel_desc', 'local_saipa'),
        'none',
        [
            'none'      => get_string('settings:channel_none', 'local_saipa'),
            'telegram'  => get_string('settings:channel_telegram', 'local_saipa'),
            'whatsapp'  => get_string('settings:channel_whatsapp', 'local_saipa'),
            'both'      => get_string('settings:channel_both', 'local_saipa'),
        ]
    ));

    $settings->add(new admin_setting_configtext(
        'local_saipa/telegram_bot_username',
        get_string('settings:telegram_bot_username', 'local_saipa'),
        get_string('settings:telegram_bot_username_desc', 'local_saipa'),
        'saipa_bot',
        PARAM_ALPHANUMEXT
    ));

    // === Section: WhatsApp ===
    $settings->add(new admin_setting_heading(
        'local_saipa/heading_whatsapp',
        get_string('settings:heading_whatsapp', 'local_saipa'),
        ''
    ));

    $settings->add(new admin_setting_configselect(
        'local_saipa/whatsapp_provider',
        get_string('settings:whatsapp_provider', 'local_saipa'),
        get_string('settings:whatsapp_provider_desc', 'local_saipa'),
        'twilio',
        ['twilio' => 'Twilio', 'meta' => 'Meta Cloud API']
    ));

    $settings->add(new admin_setting_configtext(
        'local_saipa/twilio_sid',
        get_string('settings:twilio_sid', 'local_saipa'),
        '',
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_saipa/twilio_token',
        get_string('settings:twilio_token', 'local_saipa'),
        '',
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_saipa/twilio_from',
        get_string('settings:twilio_from', 'local_saipa'),
        '',
        'whatsapp:+14155238886',
        PARAM_TEXT
    ));

    // === Section: Risk Thresholds ===
    $settings->add(new admin_setting_heading(
        'local_saipa/heading_thresholds',
        get_string('cfg_heading_thresholds', 'local_saipa'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_saipa/risk_threshold_medium',
        get_string('cfg_threshold_medium', 'local_saipa'),
        '',
        '0.40',
        PARAM_FLOAT
    ));

    $settings->add(new admin_setting_configtext(
        'local_saipa/risk_threshold_high',
        get_string('cfg_threshold_high', 'local_saipa'),
        '',
        '0.75',
        PARAM_FLOAT
    ));

    // === Section: Alerts ===
    $settings->add(new admin_setting_heading(
        'local_saipa/heading_alerts',
        get_string('cfg_heading_alerts', 'local_saipa'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_saipa/alert_cooldown_hours',
        get_string('cfg_cooldown_hours', 'local_saipa'),
        '',
        '24',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtextarea(
        'local_saipa/default_alert_template',
        get_string('cfg_alert_template', 'local_saipa'),
        get_string('cfg_template_placeholders', 'local_saipa'),
        ''
    ));

    // === Section: GDPR / Data Retention ===
    $settings->add(new admin_setting_heading(
        'local_saipa/heading_gdpr',
        get_string('cfg_heading_gdpr', 'local_saipa'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_saipa/data_retention_days',
        get_string('cfg_retention_days', 'local_saipa'),
        '',
        '730',
        PARAM_INT
    ));

    // === Section: Feature Flags ===
    $settings->add(new admin_setting_heading(
        'local_saipa/heading_features',
        get_string('cfg_heading_features', 'local_saipa'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_saipa/risk_eval_enabled',
        get_string('cfg_risk_eval', 'local_saipa'),
        '',
        '1'
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_saipa/rag_global_enabled',
        get_string('cfg_rag_global', 'local_saipa'),
        '',
        '1'
    ));
}
