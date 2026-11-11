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
 * SAIPA Setup Wizard — post-installation configuration guide.
 *
 * Steps:
 *   1 — Welcome & feature overview
 *   2 — Minimum requirements & AI provisioning (CRITICAL)
 *   3 — AI service mode selection
 *   4 — Engine connection details
 *   5 — Telegram Bot (optional but recommended)
 *   6 — Real-time health check
 *   7 — Done
 *
 * @package    local_saipa
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/saipa/lib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/saipa/setup.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('wizard_page_title', 'local_saipa'));
$PAGE->set_heading(get_string('wizard_page_heading', 'local_saipa'));

$action = optional_param('action', '', PARAM_ALPHA);

// AJAX: engine health check.
if ($action === 'health') {
    require_sesskey();
    header('Content-Type: application/json');
    header('X-Content-Type-Options: nosniff');

    $engineurl   = optional_param('engine_url', '', PARAM_URL);
    $enginetoken = optional_param('engine_token', '', PARAM_RAW);

    if (empty($engineurl)) {
        echo json_encode(['error' => get_string('wizard_err_url_required', 'local_saipa')]);
        die();
    }

    $url = rtrim($engineurl, '/') . '/health';
    try {
        // Securityhelper: see local_saipa\engine_security_helper docblock —
        // narrows Moodle's default private-IP block to exactly this host
        // instead of widening the site-wide blocklist.
        $client   = new \core\http_client([
            'timeout'        => 10,
            'securityhelper' => new \local_saipa\engine_security_helper($engineurl),
        ]);
        $response = $client->get($url, [
            'headers' => [
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $enginetoken,
            ],
            'http_errors' => false,
        ]);
    } catch (\GuzzleHttp\Exception\GuzzleException $e) {
        echo json_encode(['error' => get_string('wizard_err_connection', 'local_saipa', $e->getMessage())]);
        die();
    }
    $http = $response->getStatusCode();
    $resp = (string) $response->getBody();

    if ($http !== 200) {
        echo json_encode(['error' => get_string('wizard_err_http', 'local_saipa', $http)]);
        die();
    }
    $decoded = json_decode($resp, true);
    if (!$decoded || ($decoded['status'] ?? '') !== 'ok') {
        echo json_encode(['error' => get_string('wizard_err_unexpected_response', 'local_saipa', substr($resp, 0, 200))]);
        die();
    }
    echo json_encode([
        'ok'      => true,
        'version' => $decoded['version'] ?? '?',
        'uptime'  => isset($decoded['uptime_seconds'])
            ? round($decoded['uptime_seconds'] / 60, 1) . ' min'
            : '?',
        'message' => $decoded['message'] ?? 'Running',
    ]);
    die();
}

// AJAX: Telegram bot token validation.
if ($action === 'testbot') {
    require_sesskey();
    header('Content-Type: application/json');
    header('X-Content-Type-Options: nosniff');

    $bottoken = optional_param('bot_token', '', PARAM_RAW);
    if (empty($bottoken)) {
        echo json_encode(['error' => get_string('wizard_err_no_bot_token', 'local_saipa')]);
        die();
    }

    // Call Telegram's getMe to validate the token (no data stored, read-only).
    $tgurl = 'https://api.telegram.org/bot' . urlencode($bottoken) . '/getMe';
    try {
        $client   = new \core\http_client(['timeout' => 8]);
        $response = $client->get($tgurl, [
            'headers'     => ['Accept' => 'application/json'],
            'http_errors' => false,
        ]);
    } catch (\GuzzleHttp\Exception\GuzzleException $e) {
        echo json_encode(['error' => get_string('wizard_err_telegram_unreachable', 'local_saipa', $e->getMessage())]);
        die();
    }
    $resp = (string) $response->getBody();
    $tg = json_decode($resp, true);
    if (!$tg || empty($tg['ok'])) {
        $desc = $tg['description'] ?? get_string('wizard_err_invalid_response', 'local_saipa');
        echo json_encode(['error' => get_string('wizard_err_telegram_api', 'local_saipa', $desc)]);
        die();
    }
    echo json_encode([
        'ok'       => true,
        'username' => $tg['result']['username'] ?? '',
        'name'     => ($tg['result']['first_name'] ?? '') . ' ' . ($tg['result']['last_name'] ?? ''),
    ]);
    die();
}

// POST: save all configuration.
if ($action === 'save' && data_submitted()) {
    require_sesskey();

    $mode            = required_param('engine_mode', PARAM_ALPHANUMEXT);
    $engineurl      = required_param('engine_url', PARAM_URL);
    $enginetoken    = optional_param('engine_token', '', PARAM_RAW);
    $msgchannel     = optional_param('messaging_channel', 'none', PARAM_ALPHA);
    $tgusername     = optional_param('telegram_bot_username', '', PARAM_ALPHANUMEXT);
    $riskmed        = optional_param('risk_threshold_medium', '0.40', PARAM_FLOAT);
    $riskhigh       = optional_param('risk_threshold_high', '0.75', PARAM_FLOAT);
    $cooldown        = optional_param('alert_cooldown_hours', 24, PARAM_INT);
    $riskenabled    = optional_param('risk_eval_enabled', 1, PARAM_INT);
    $ragenabled     = optional_param('rag_global_enabled', 1, PARAM_INT);

    $validmodes = ['local_ollama', 'cloud_api', 'custom'];
    if (!in_array($mode, $validmodes)) {
        $mode = 'custom';
    }
    $validchannels = ['none', 'telegram', 'whatsapp', 'both'];
    if (!in_array($msgchannel, $validchannels)) {
        $msgchannel = 'none';
    }

    set_config('engine_mode', $mode, 'local_saipa');
    set_config('engine_url', $engineurl, 'local_saipa');
    set_config('engine_token', $enginetoken, 'local_saipa');
    set_config('messaging_channel', $msgchannel, 'local_saipa');
    set_config('telegram_bot_username', $tgusername, 'local_saipa');
    set_config('risk_threshold_medium', $riskmed, 'local_saipa');
    set_config('risk_threshold_high', $riskhigh, 'local_saipa');
    set_config('alert_cooldown_hours', $cooldown, 'local_saipa');
    set_config('risk_eval_enabled', $riskenabled, 'local_saipa');
    set_config('rag_global_enabled', $ragenabled, 'local_saipa');
    set_config('setup_complete', 1, 'local_saipa');

    redirect(
        new moodle_url('/local/saipa/setup.php', ['done' => 1]),
        get_string('wizard_save_success', 'local_saipa'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Page render setup.
$done         = optional_param('done', 0, PARAM_INT);
$sesskey      = sesskey();
$settingsurl = (new moodle_url('/admin/settings.php', ['section' => 'local_saipa']))->out(false);
$teacherurl  = (new moodle_url('/local/saipa/teacher.php'))->out(false);

// Pre-fill from existing config (wizard re-run).
$cfgmode    = get_config('local_saipa', 'engine_mode') ?: 'local_ollama';
$cfgurl     = get_config('local_saipa', 'engine_url') ?: 'http://localhost:8052';
$cfgtoken   = get_config('local_saipa', 'engine_token') ?: '';
$cfgchannel = get_config('local_saipa', 'messaging_channel') ?: 'none';
$cfgtguser = get_config('local_saipa', 'telegram_bot_username') ?: '';
$cfgriskmed  = get_config('local_saipa', 'risk_threshold_medium') ?: '0.40';
$cfgriskhigh = get_config('local_saipa', 'risk_threshold_high') ?: '0.75';
$cfgcooldown  = get_config('local_saipa', 'alert_cooldown_hours') ?: '24';
$cfgriskon   = get_config('local_saipa', 'risk_eval_enabled') ?? '1';
$cfgragon    = get_config('local_saipa', 'rag_global_enabled') ?? '1';

// Strings and labels for amd/src/setup_wizard.js, passed in the wizard's data-config attribute.
$wizardconfig = [
    'url'      => (new moodle_url('/local/saipa/setup.php'))->out(false),
    'strings'  => [
        'enter_token'       => get_string('wizard_js_enter_token', 'local_saipa'),
        'validating'        => get_string('wizard_js_validating', 'local_saipa'),
        'bot_verified'      => get_string('wizard_js_bot_verified', 'local_saipa'),
        'username_autofill' => get_string('wizard_js_username_autofill', 'local_saipa'),
        'error'             => get_string('wizard_js_error', 'local_saipa'),
        'invalid_token'     => get_string('wizard_js_invalid_token', 'local_saipa'),
        'network_error'     => get_string('wizard_js_network_error', 'local_saipa'),
        'connecting'        => get_string('wizard_js_connecting', 'local_saipa'),
        'url_empty'         => get_string('wizard_js_url_empty', 'local_saipa'),
        'engine_reachable'  => get_string('wizard_js_engine_reachable', 'local_saipa'),
        'engine_version'    => get_string('wizard_js_engine_version', 'local_saipa'),
        'uptime'            => get_string('wizard_js_uptime', 'local_saipa'),
        'success'           => get_string('wizard_js_success', 'local_saipa'),
        'not_configured'    => get_string('wizard_js_not_configured', 'local_saipa'),
        'connection_failed' => get_string('wizard_js_connection_failed', 'local_saipa'),
        'unknown_error'     => get_string('wizard_js_unknown_error', 'local_saipa'),
        'troubleshooting'   => get_string('wizard_js_troubleshooting', 'local_saipa'),
        'ts_running'        => get_string('wizard_js_ts_running', 'local_saipa'),
        'ts_url'            => get_string('wizard_js_ts_url', 'local_saipa'),
        'ts_docker'         => get_string('wizard_js_ts_docker', 'local_saipa'),
        'ts_token'          => get_string('wizard_js_ts_token', 'local_saipa'),
    ],
    'modes'    => [
        'local_ollama' => get_string('wizard_js_mode_local', 'local_saipa'),
        'cloud_api'    => get_string('wizard_js_mode_cloud', 'local_saipa'),
        'custom'       => get_string('wizard_js_mode_custom', 'local_saipa'),
    ],
    'channels' => [
        'none'     => get_string('wizard_js_ch_none', 'local_saipa'),
        'telegram' => get_string('wizard_js_ch_telegram', 'local_saipa'),
        'whatsapp' => get_string('wizard_js_ch_whatsapp', 'local_saipa'),
        'both'     => get_string('wizard_js_ch_both', 'local_saipa'),
    ],
];

// Detect server environment.
$phpverok    = version_compare(PHP_VERSION, '8.1.0', '>=');
$moodleverok = ($CFG->version >= 2024042200);
$curlok       = function_exists('curl_init');
$phpverstr   = PHP_VERSION;
$moodleverstr = $CFG->release ?? 'unknown';

$blockinstalled = get_config('block_saipa', 'version') !== false;

// The page is a Mustache template (templates/setup_wizard.mustache); values are escaped there.
$templatecontext = [
    'done'           => (bool) $done,
    'wizardconfig'   => json_encode($wizardconfig, JSON_UNESCAPED_UNICODE),
    'settingsurl'    => $settingsurl,
    'teacherurl'     => $teacherurl,
    'coursesurl'     => (new moodle_url('/course/index.php'))->out(false),
    'saveurl'        => (new moodle_url('/local/saipa/setup.php', ['action' => 'save']))->out(false),
    'adminurl'       => (new moodle_url('/admin/index.php'))->out(false),
    'sesskey'        => $sesskey,
    'moodleverok'    => $moodleverok,
    'phpverok'       => $phpverok,
    'curlok'         => $curlok,
    'blockinstalled' => $blockinstalled,
    'moodleverstr'   => $moodleverstr,
    'phpverstr'      => $phpverstr,
    'cfgurl'         => $cfgurl,
    'cfgtoken'       => $cfgtoken,
    'cfgtguser'      => $cfgtguser,
    'cfgriskmed'     => $cfgriskmed,
    'cfgriskhigh'    => $cfgriskhigh,
    'cfgcooldown'    => $cfgcooldown,
    'cfgriskon'      => $cfgriskon,
    'cfgragon'       => $cfgragon,
    'riskon'         => (bool) (int) $cfgriskon,
    'ragon'          => (bool) (int) $cfgragon,
    'modelocal'      => $cfgmode === 'local_ollama',
    'modecloud'      => $cfgmode === 'cloud_api',
    'modecustom'     => $cfgmode === 'custom',
    'channelnone'    => $cfgchannel === 'none',
    'channeltelegram' => $cfgchannel === 'telegram',
    'channelwhatsapp' => $cfgchannel === 'whatsapp',
    'channelboth'    => $cfgchannel === 'both',
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_saipa/setup_wizard', $templatecontext);
$PAGE->requires->js_call_amd('local_saipa/setup_wizard', 'init');
echo $OUTPUT->footer();
