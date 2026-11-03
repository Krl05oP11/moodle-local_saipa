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
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
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

echo $OUTPUT->header();

// phpcs:disable moodle.Commenting.MissingDocblock.File -- False positive: this sniff
// Re-fires on every reopened PHP tag in the HTML template below, although the
// file docblock is present at the top of the file. Re-enabled at end of file.
?>

<div class="spwiz mt-4" data-config="<?= s(json_encode($wizardconfig, JSON_UNESCAPED_UNICODE)) ?>">

<?php if ($done) : ?>
  <!-- Completion screen (after save + redirect) -->
  <div class="card shadow-sm">
    <div class="card-body done-card">
      <div class="done-icon">🎉</div>
      <h3><?= get_string('wizard_done_title', 'local_saipa') ?></h3>
      <p><?= get_string('wizard_done_desc', 'local_saipa') ?></p>
      <div class="d-flex gap-3 justify-content-center flex-wrap">
        <a href="<?= s($settingsurl) ?>" class="btn btn-outline-secondary"><?= get_string('wizard_btn_admin', 'local_saipa') ?></a>
        <a href="<?= s($teacherurl) ?>" class="btn btn-outline-primary"><?= get_string('wizard_btn_teacher', 'local_saipa') ?></a>
        <a href="<?= (new moodle_url('/course/index.php'))->out() ?>" class="btn btn-primary btn-lg px-5">
          <?= get_string('wizard_btn_courses', 'local_saipa') ?>
        </a>
      </div>
    </div>
  </div>

<?php else : ?>
  <!-- Progress bar -->
  <div class="spwiz-progress" id="spwiz-progress">
    <div class="step active" data-step="1">
      <div class="step-circle">1</div>
      <div class="step-label"><?= get_string('wizard_step_welcome', 'local_saipa') ?></div>
    </div>
    <div class="step" data-step="2">
      <div class="step-circle">2</div>
      <div class="step-label"><?= get_string('wizard_step_requirements', 'local_saipa') ?></div>
    </div>
    <div class="step" data-step="3">
      <div class="step-circle">3</div>
      <div class="step-label"><?= get_string('wizard_step_ai_mode', 'local_saipa') ?></div>
    </div>
    <div class="step" data-step="4">
      <div class="step-circle">4</div>
      <div class="step-label"><?= get_string('wizard_step_engine', 'local_saipa') ?></div>
    </div>
    <div class="step" data-step="5">
      <div class="step-circle">5</div>
      <div class="step-label"><?= get_string('wizard_step_telegram', 'local_saipa') ?></div>
    </div>
    <div class="step" data-step="6">
      <div class="step-circle">6</div>
      <div class="step-label"><?= get_string('wizard_step_test', 'local_saipa') ?></div>
    </div>
    <div class="step" data-step="7">
      <div class="step-circle">7</div>
      <div class="step-label"><?= get_string('wizard_step_done', 'local_saipa') ?></div>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body p-4">


    <!-- ══════════════════════════════════════════
         STEP 1 — Welcome
         ══════════════════════════════════════════ -->
    <div class="spwiz-step active" id="spwiz-step-1">

      <div class="d-flex align-items-center gap-3 mb-3">
        <div style="font-size:2.8rem;line-height:1">🤖</div>
        <div>
          <h3 class="mb-1"><?= get_string('wizard_welcome_title', 'local_saipa') ?></h3>
          <p class="text-muted mb-0">
            <?= get_string('wizard_welcome_intro', 'local_saipa') ?>
          </p>
        </div>
      </div>
      <hr class="my-3">

      <p class="mb-3" style="font-size:.9rem;">
        <?= get_string('wizard_welcome_about', 'local_saipa') ?>
      </p>

      <div class="feature-grid mb-4">
        <div class="feature-item">
          <div class="fi-icon">🔴</div>
          <div><h6><?= get_string('wizard_feat_dropout_title', 'local_saipa') ?></h6>
            <p><?= get_string('wizard_feat_dropout_desc', 'local_saipa') ?></p></div>
        </div>
        <div class="feature-item">
          <div class="fi-icon">💬</div>
          <div><h6><?= get_string('wizard_feat_chat_title', 'local_saipa') ?></h6>
            <p><?= get_string('wizard_feat_chat_desc', 'local_saipa') ?></p></div>
        </div>
        <div class="feature-item">
          <div class="fi-icon">📲</div>
          <div><h6><?= get_string('wizard_feat_alerts_title', 'local_saipa') ?></h6>
            <p><?= get_string('wizard_feat_alerts_desc', 'local_saipa') ?></p></div>
        </div>
        <div class="feature-item">
          <div class="fi-icon">📊</div>
          <div><h6><?= get_string('wizard_feat_advisor_title', 'local_saipa') ?></h6>
            <p><?= get_string('wizard_feat_advisor_desc', 'local_saipa') ?></p></div>
        </div>
        <div class="feature-item">
          <div class="fi-icon">📚</div>
          <div><h6><?= get_string('wizard_feat_index_title', 'local_saipa') ?></h6>
            <p><?= get_string('wizard_feat_index_desc', 'local_saipa') ?></p></div>
        </div>
        <div class="feature-item">
          <div class="fi-icon">🤝</div>
          <div><h6><?= get_string('wizard_feat_evalia_title', 'local_saipa') ?></h6>
            <p><?= get_string('wizard_feat_evalia_desc', 'local_saipa') ?></p></div>
        </div>
      </div>

      <div class="d-flex justify-content-end">
        <button class="btn btn-primary px-5" data-spwiz-goto="2"><?= get_string('wizard_btn_next', 'local_saipa') ?></button>
      </div>
    </div><!-- /step 1 -->


    <!-- ══════════════════════════════════════════
         STEP 2 — Requirements (CRITICAL)
         ══════════════════════════════════════════ -->
    <div class="spwiz-step" id="spwiz-step-2">

      <h4 class="mb-1"><?= get_string('wizard_req_title', 'local_saipa') ?></h4>
      <p class="text-muted mb-4" style="font-size:.88rem;">
        <?= get_string('wizard_req_intro', 'local_saipa') ?>
      </p>

      <!-- Platform -->
      <div class="req-section">
        <div class="req-section-header" style="background:#f8f9fa;"><?= get_string('wizard_req_platform', 'local_saipa') ?></div>
        <div class="req-section-body">

          <div class="req-row">
            <div class="req-status"><?= $moodleverok ? '✅' : '❌' ?></div>
            <div class="req-label">
              <strong><?= get_string('wizard_req_moodle_title', 'local_saipa') ?></strong>
              <span><?= get_string('wizard_req_moodle_desc', 'local_saipa') ?></span>
            </div>
            <div class="req-value"><?= s($moodleverstr) ?></div>
          </div>

          <div class="req-row">
            <div class="req-status"><?= $phpverok ? '✅' : '❌' ?></div>
            <div class="req-label">
              <strong><?= get_string('wizard_req_php_title', 'local_saipa') ?></strong>
              <span><?= get_string('wizard_req_php_desc', 'local_saipa') ?></span>
            </div>
            <div class="req-value"><?= s($phpverstr) ?></div>
          </div>

          <div class="req-row">
            <div class="req-status"><?= $curlok ? '✅' : '❌' ?></div>
            <div class="req-label">
              <strong><?= get_string('wizard_req_curl_title', 'local_saipa') ?></strong>
              <span><?= get_string('wizard_req_curl_desc', 'local_saipa') ?></span>
            </div>
            <div class="req-value">
              <?php
                echo $curlok
                  ? get_string('wizard_req_curl_enabled', 'local_saipa')
                  : '<span class="text-danger">' .
                    get_string('wizard_req_curl_missing', 'local_saipa') . '</span>';
              ?>
            </div>
          </div>

          <div class="req-row">
            <div class="req-status">ℹ️</div>
            <div class="req-label">
              <strong><?= get_string('wizard_req_block_title', 'local_saipa') ?></strong>
              <span><?= get_string('wizard_req_block_desc', 'local_saipa') ?></span>
            </div>
            <div class="req-value">
              <?php
                $blockinstalled = get_config('block_saipa', 'version') !== false;
                echo $blockinstalled
                  ? '<span class="text-success">' . get_string('wizard_req_block_installed', 'local_saipa') . '</span>'
                  : '<span class="text-warning">' .
                    get_string('wizard_req_block_missing', 'local_saipa', (new moodle_url('/admin/index.php'))->out()) .
                    '</span>';
              ?>
            </div>
          </div>

        </div>
      </div>

      <!-- AI Engine -->
      <div class="req-section" style="border-color:#f0ad4e;">
        <div class="req-section-header" style="background:#fff8e1;color:#856404;border-bottom:1px solid #f0e0a0;">
          <?= get_string('wizard_req_ai_heading', 'local_saipa') ?>
        </div>
        <div class="req-section-body">

          <p style="font-size:.87rem;margin-bottom:14px;">
            <?= get_string('wizard_req_ai_intro', 'local_saipa') ?>
          </p>

          <div class="req-row">
            <div class="req-status">🐍</div>
            <div class="req-label">
              <strong><?= get_string('wizard_req_enginepy_title', 'local_saipa') ?></strong>
              <span><?= get_string('wizard_req_enginepy_desc', 'local_saipa') ?></span>
            </div>
            <div class="req-value" style="white-space:normal;max-width:200px;text-align:right;">
              <span class="badge bg-warning text-dark" style="font-size:.72rem;">
                <?= get_string('wizard_req_enginepy_value', 'local_saipa') ?>
              </span>
            </div>
          </div>

          <div class="req-row">
            <div class="req-status">🗄️</div>
            <div class="req-label">
              <strong><?= get_string('wizard_req_chroma_title', 'local_saipa') ?></strong>
              <span><?= get_string('wizard_req_chroma_desc', 'local_saipa') ?></span>
            </div>
            <div class="req-value"><?= get_string('wizard_req_included', 'local_saipa') ?></div>
          </div>

          <div class="req-row">
            <div class="req-status">🔤</div>
            <div class="req-label">
              <strong><?= get_string('wizard_req_llm_title', 'local_saipa') ?></strong>
              <span><?= get_string('wizard_req_llm_desc', 'local_saipa') ?></span>
            </div>
            <div class="req-value" style="white-space:normal;max-width:200px;text-align:right;">
              <span class="badge bg-danger" style="font-size:.72rem;">
                <?= get_string('wizard_req_llm_value', 'local_saipa') ?>
              </span>
            </div>
          </div>

          <div class="req-row">
            <div class="req-status">📊</div>
            <div class="req-label">
              <strong><?= get_string('wizard_req_xgb_title', 'local_saipa') ?></strong>
              <span><?= get_string('wizard_req_xgb_desc', 'local_saipa') ?></span>
            </div>
            <div class="req-value"><?= get_string('wizard_req_included', 'local_saipa') ?></div>
          </div>

        </div>
      </div>

      <!-- AI Provisioning options -->
      <div class="req-section" style="border-color:#0d6efd;">
        <div class="req-section-header" style="background:#e7f1ff;color:#084298;border-bottom:1px solid #b6d4fe;">
          <?= get_string('wizard_prov_heading', 'local_saipa') ?>
        </div>
        <div class="req-section-body">

          <p style="font-size:.85rem;margin-bottom:14px;color:#495057;">
            <?= get_string('wizard_prov_intro', 'local_saipa') ?>
          </p>

          <div class="ai-provision-cards">

            <div class="ai-pcard pc-local">
              <div class="pc-head"><div class="pc-icon">🖥️</div>
                <h6>
                  <?= get_string('wizard_prov_local_title', 'local_saipa') ?>
                  <span class="mode-badge badge-local"><?= get_string('wizard_prov_local_badge', 'local_saipa') ?></span>
                </h6></div>
              <p><?= get_string('wizard_prov_local_desc', 'local_saipa') ?></p>
              <ul>
                <li><?= get_string('wizard_prov_local_i1', 'local_saipa') ?></li>
                <li><?= get_string('wizard_prov_local_i2', 'local_saipa') ?></li>
                <li><?= get_string('wizard_prov_local_i3', 'local_saipa') ?></li>
              </ul>
            </div>

            <div class="ai-pcard pc-cloud">
              <div class="pc-head"><div class="pc-icon">☁️</div>
                <h6>
                  <?= get_string('wizard_prov_cloud_title', 'local_saipa') ?>
                  <span class="mode-badge badge-cloud"><?= get_string('wizard_prov_cloud_badge', 'local_saipa') ?></span>
                </h6></div>
              <p><?= get_string('wizard_prov_cloud_desc', 'local_saipa') ?></p>
              <ul>
                <li><?= get_string('wizard_prov_cloud_i1', 'local_saipa') ?></li>
                <li><?= get_string('wizard_prov_cloud_i2', 'local_saipa') ?></li>
                <li><?= get_string('wizard_prov_cloud_i3', 'local_saipa') ?></li>
              </ul>
            </div>

            <div class="ai-pcard pc-custom">
              <div class="pc-head"><div class="pc-icon">⚙️</div>
                <h6>
                  <?= get_string('wizard_prov_custom_title', 'local_saipa') ?>
                  <span class="mode-badge badge-custom"><?= get_string('wizard_prov_custom_badge', 'local_saipa') ?></span>
                </h6></div>
              <p><?= get_string('wizard_prov_custom_desc', 'local_saipa') ?></p>
              <ul>
                <li><?= get_string('wizard_prov_custom_i1', 'local_saipa') ?></li>
                <li><?= get_string('wizard_prov_custom_i2', 'local_saipa') ?></li>
              </ul>
            </div>

          </div>

          <div class="alert alert-danger mt-3 mb-0 py-2 px-3" style="font-size:.84rem;">
            <?= get_string('wizard_prov_warning', 'local_saipa') ?>
          </div>

        </div>
      </div>

      <!-- Confirmation checkbox -->
      <div class="form-check mt-3 mb-1">
        <input class="form-check-input" type="checkbox" id="req-confirm">
        <label class="form-check-label" for="req-confirm" style="font-size:.88rem;">
          <?= get_string('wizard_req_confirm', 'local_saipa') ?>
        </label>
      </div>

      <div class="d-flex justify-content-between mt-3">
        <button class="btn btn-outline-secondary" data-spwiz-goto="1">
            <?= get_string('wizard_btn_back', 'local_saipa') ?>
        </button>
        <button class="btn btn-primary px-5" id="req-next-btn" disabled data-spwiz-goto="3">
            <?= get_string('wizard_btn_next', 'local_saipa') ?>
        </button>
      </div>
    </div><!-- /step 2 -->


    <!-- ══════════════════════════════════════════
         STEP 3 — AI Mode
         ══════════════════════════════════════════ -->
    <div class="spwiz-step" id="spwiz-step-3">

      <h4 class="mb-1"><?= get_string('wizard_mode_title', 'local_saipa') ?></h4>
      <p class="text-muted mb-4" style="font-size:.88rem;"><?= get_string('wizard_mode_intro', 'local_saipa') ?></p>

      <div class="mode-cards">
        <label class="mode-card <?= ($cfgmode === 'local_ollama') ? 'selected' : '' ?>" for="sp-mode-local">
          <input type="radio" name="engine_mode" id="sp-mode-local" value="local_ollama"
                 <?= ($cfgmode === 'local_ollama') ? 'checked' : '' ?>>
          <div class="mc-icon">🖥️</div>
          <h5>
            <?= get_string('wizard_prov_local_title', 'local_saipa') ?>
            <span class="mode-badge badge-local"><?= get_string('wizard_prov_local_badge', 'local_saipa') ?></span>
          </h5>
          <p><?= get_string('wizard_mode_local_desc', 'local_saipa') ?></p>
        </label>
        <label class="mode-card <?= ($cfgmode === 'cloud_api') ? 'selected' : '' ?>" for="sp-mode-cloud">
          <input type="radio" name="engine_mode" id="sp-mode-cloud" value="cloud_api"
                 <?= ($cfgmode === 'cloud_api') ? 'checked' : '' ?>>
          <div class="mc-icon">☁️</div>
          <h5>
            <?= get_string('wizard_prov_cloud_title', 'local_saipa') ?>
            <span class="mode-badge badge-cloud"><?= get_string('wizard_prov_cloud_badge', 'local_saipa') ?></span>
          </h5>
          <p><?= get_string('wizard_mode_cloud_desc', 'local_saipa') ?></p>
        </label>
        <label class="mode-card <?= ($cfgmode === 'custom') ? 'selected' : '' ?>" for="sp-mode-custom">
          <input type="radio" name="engine_mode" id="sp-mode-custom" value="custom"
                 <?= ($cfgmode === 'custom') ? 'checked' : '' ?>>
          <div class="mc-icon">⚙️</div>
          <h5>
            <?= get_string('wizard_prov_custom_title', 'local_saipa') ?>
            <span class="mode-badge badge-custom"><?= get_string('wizard_prov_custom_badge', 'local_saipa') ?></span>
          </h5>
          <p><?= get_string('wizard_mode_custom_desc', 'local_saipa') ?></p>
        </label>
      </div>

      <div class="d-flex justify-content-between mt-4">
        <button class="btn btn-outline-secondary" data-spwiz-goto="2">
            <?= get_string('wizard_btn_back', 'local_saipa') ?>
        </button>
        <button class="btn btn-primary px-5" data-spwiz-goto="4"><?= get_string('wizard_btn_next', 'local_saipa') ?></button>
      </div>
    </div><!-- /step 3 -->


    <!-- ══════════════════════════════════════════
         STEP 4 — Engine connection
         ══════════════════════════════════════════ -->
    <div class="spwiz-step" id="spwiz-step-4">

      <h4 class="mb-1"><?= get_string('wizard_engine_title', 'local_saipa') ?></h4>
      <p class="text-muted mb-4" style="font-size:.88rem;"><?= get_string('wizard_engine_intro', 'local_saipa') ?></p>

      <div id="sp-hint-local_ollama" class="alert alert-light border mb-3 py-2 px-3" style="font-size:.82rem;">
        <?= get_string('wizard_hint_local', 'local_saipa') ?>
      </div>
      <div id="sp-hint-cloud_api" class="alert alert-light border mb-3 py-2 px-3" style="font-size:.82rem;display:none">
        <?= get_string('wizard_hint_cloud', 'local_saipa') ?>
      </div>
      <div id="sp-hint-custom" class="alert alert-light border mb-3 py-2 px-3" style="font-size:.82rem;display:none">
        <?= get_string('wizard_hint_custom', 'local_saipa') ?>
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold" for="sp-url"><?= get_string('wizard_url_label', 'local_saipa') ?>
            <span class="text-danger">*</span></label>
        <input type="url" class="form-control" id="sp-url"
               placeholder="<?= get_string('wizard_url_placeholder', 'local_saipa') ?>"
               value="<?= s($cfgurl) ?>">
        <div class="form-text"><?= get_string('wizard_url_help', 'local_saipa') ?></div>
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold" for="sp-token"><?= get_string('wizard_token_label', 'local_saipa') ?></label>
        <input type="password" class="form-control" id="sp-token"
               placeholder="<?= get_string('wizard_token_placeholder', 'local_saipa') ?>"
               value="<?= s($cfgtoken) ?>">
        <div class="form-text"><?= get_string('wizard_token_help', 'local_saipa') ?></div>
      </div>

      <div class="d-flex justify-content-between mt-4">
        <button class="btn btn-outline-secondary" data-spwiz-goto="3">
            <?= get_string('wizard_btn_back', 'local_saipa') ?>
        </button>
        <button class="btn btn-primary px-5" data-spwiz-goto="5"><?= get_string('wizard_btn_next', 'local_saipa') ?></button>
      </div>
    </div><!-- /step 4 -->


    <!-- ══════════════════════════════════════════
         STEP 5 — Telegram Bot (SAIPA-specific)
         ══════════════════════════════════════════ -->
    <div class="spwiz-step" id="spwiz-step-5">

      <h4 class="mb-1"><?= get_string('wizard_channels_title', 'local_saipa') ?></h4>
      <p class="text-muted mb-3" style="font-size:.88rem;">
        <?= get_string('wizard_channels_intro', 'local_saipa') ?>
      </p>

      <!-- Channel selector -->
      <div class="channel-cards mb-4">
        <label class="channel-card <?= ($cfgchannel === 'none') ? 'selected' : '' ?>" for="ch-none">
          <input type="radio" name="messaging_channel" id="ch-none" value="none"
                 <?= ($cfgchannel === 'none') ? 'checked' : '' ?>>
          <div class="ch-icon">🔕</div><h6><?= get_string('wizard_ch_none_title', 'local_saipa') ?></h6>
          <p><?= get_string('wizard_ch_none_desc', 'local_saipa') ?></p>
        </label>
        <label class="channel-card <?= ($cfgchannel === 'telegram') ? 'selected' : '' ?>" for="ch-telegram">
          <input type="radio" name="messaging_channel" id="ch-telegram" value="telegram"
                 <?= ($cfgchannel === 'telegram') ? 'checked' : '' ?>>
          <div class="ch-icon">✈️</div><h6><?= get_string('wizard_ch_telegram_title', 'local_saipa') ?></h6>
          <p><?= get_string('wizard_ch_telegram_desc', 'local_saipa') ?></p>
        </label>
        <label class="channel-card <?= ($cfgchannel === 'whatsapp') ? 'selected' : '' ?>" for="ch-whatsapp">
          <input type="radio" name="messaging_channel" id="ch-whatsapp" value="whatsapp"
                 <?= ($cfgchannel === 'whatsapp') ? 'checked' : '' ?>>
          <div class="ch-icon">💬</div><h6><?= get_string('wizard_ch_whatsapp_title', 'local_saipa') ?></h6>
          <p><?= get_string('wizard_ch_whatsapp_desc', 'local_saipa') ?></p>
        </label>
        <label class="channel-card <?= ($cfgchannel === 'both') ? 'selected' : '' ?>" for="ch-both">
          <input type="radio" name="messaging_channel" id="ch-both" value="both"
                 <?= ($cfgchannel === 'both') ? 'checked' : '' ?>>
          <div class="ch-icon">📡</div><h6><?= get_string('wizard_ch_both_title', 'local_saipa') ?></h6>
          <p><?= get_string('wizard_ch_both_desc', 'local_saipa') ?></p>
        </label>
      </div>

      <!-- Telegram details (shown when telegram || both is selected) -->
      <div id="sp-telegram-section">

        <hr class="mb-3">
        <h5 class="mb-1" style="font-size:.95rem;"><?= get_string('wizard_tg_heading', 'local_saipa') ?></h5>
        <p class="text-muted mb-3" style="font-size:.83rem;">
          <?= get_string('wizard_tg_intro', 'local_saipa') ?>
        </p>

        <!-- How to create a bot -->
        <div class="alert alert-light border mb-3 py-2 px-3" style="font-size:.82rem;">
          <strong><?= get_string('wizard_tg_howto_title', 'local_saipa') ?></strong>
          <ol class="mb-0 mt-1 ps-3">
            <li><?= get_string('wizard_tg_howto_1', 'local_saipa') ?></li>
            <li><?= get_string('wizard_tg_howto_2', 'local_saipa') ?></li>
            <li><?= get_string('wizard_tg_howto_3', 'local_saipa') ?></li>
            <li><?= get_string('wizard_tg_howto_4', 'local_saipa') ?></li>
            <li><?= get_string('wizard_tg_howto_5', 'local_saipa') ?></li>
          </ol>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-7">
            <label class="form-label fw-semibold" for="sp-tg-token">
              <?= get_string('wizard_tg_token_label', 'local_saipa') ?>
              <small class="text-muted fw-normal"><?= get_string('wizard_tg_token_note', 'local_saipa') ?></small>
            </label>
            <div class="input-group">
              <input type="password" class="form-control" id="sp-tg-token"
                     placeholder="<?= get_string('wizard_tg_token_placeholder', 'local_saipa') ?>"
                     autocomplete="off">
              <button class="btn btn-outline-primary" type="button" data-spwiz-action="testbot">
                <?= get_string('wizard_btn_validate', 'local_saipa') ?>
              </button>
            </div>
            <div class="form-text"><?= get_string('wizard_tg_token_help', 'local_saipa') ?></div>
          </div>
          <div class="col-md-5">
            <label class="form-label fw-semibold" for="sp-tg-username"><?= get_string('wizard_tg_username_label', 'local_saipa') ?>
                <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text">@</span>
              <input type="text" class="form-control" id="sp-tg-username"
                     placeholder="<?= get_string('wizard_tg_username_placeholder', 'local_saipa') ?>"
                     value="<?= s($cfgtguser) ?>">
            </div>
            <div class="form-text"><?= get_string('wizard_tg_username_help', 'local_saipa') ?></div>
          </div>
        </div>

        <div id="sp-bot-result" class="mb-3"></div>

        <div class="alert alert-info py-2 px-3" style="font-size:.82rem;">
          <?= get_string('wizard_tg_link_info', 'local_saipa') ?>
        </div>

      </div><!-- /telegram-section -->

      <!-- WhatsApp note -->
      <div id="sp-whatsapp-note" class="alert alert-secondary py-2 px-3 mb-3" style="font-size:.82rem;display:none">
        <?= get_string('wizard_wa_note', 'local_saipa', s($settingsurl)) ?>
      </div>

      <div class="d-flex justify-content-between mt-4">
        <button class="btn btn-outline-secondary" data-spwiz-goto="4">
            <?= get_string('wizard_btn_back', 'local_saipa') ?>
        </button>
        <button class="btn btn-primary px-5" data-spwiz-goto="6"><?= get_string('wizard_btn_next', 'local_saipa') ?></button>
      </div>
    </div><!-- /step 5 -->


    <!-- ══════════════════════════════════════════
         STEP 6 — Health check + summary
         ══════════════════════════════════════════ -->
    <div class="spwiz-step" id="spwiz-step-6">

      <h4 class="mb-1"><?= get_string('wizard_test_title', 'local_saipa') ?></h4>
      <p class="text-muted mb-4" style="font-size:.88rem;"><?= get_string('wizard_test_intro', 'local_saipa') ?></p>

      <div id="sp-health-result" class="mb-4">
        <div class="d-flex align-items-center gap-2 text-muted py-2">
          <div class="spinner-border spinner-border-sm" role="status"></div>
          <span><?= get_string('wizard_test_connecting', 'local_saipa') ?></span>
        </div>
      </div>

      <!-- Configuration summary (shown after test) -->
      <div id="sp-summary" style="display:none">
        <h6 class="text-muted text-uppercase" style="font-size:.75rem;letter-spacing:.05em;margin-bottom:10px;">
          <?= get_string('wizard_summary_heading', 'local_saipa') ?>
        </h6>
        <div class="summary-grid mb-4">

          <div class="summary-card">
            <h6><?= get_string('wizard_summary_engine', 'local_saipa') ?></h6>
            <div class="summary-row">
              <span class="summary-label"><?= get_string('wizard_summary_mode', 'local_saipa') ?></span>
              <span class="summary-value" id="sum-mode">—</span>
            </div>
            <div class="summary-row">
              <span class="summary-label"><?= get_string('wizard_summary_url', 'local_saipa') ?></span>
              <span
                class="summary-value"
                id="sum-url"
                style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;direction:rtl"
              >—</span>
            </div>
            <div class="summary-row">
              <span class="summary-label"><?= get_string('wizard_summary_token', 'local_saipa') ?></span>
              <span class="summary-value" id="sum-token">—</span>
            </div>
          </div>

          <div class="summary-card">
            <h6><?= get_string('wizard_summary_notif', 'local_saipa') ?></h6>
            <div class="summary-row">
              <span class="summary-label"><?= get_string('wizard_summary_channel', 'local_saipa') ?></span>
              <span class="summary-value" id="sum-channel">—</span>
            </div>
            <div class="summary-row">
              <span class="summary-label"><?= get_string('wizard_summary_tgbot', 'local_saipa') ?></span>
              <span class="summary-value" id="sum-tg">—</span>
            </div>
          </div>

          <div class="summary-card">
            <h6><?= get_string('wizard_summary_risk', 'local_saipa') ?></h6>
            <div class="summary-row">
              <span class="summary-label"><?= get_string('wizard_summary_medium', 'local_saipa') ?></span>
              <span class="summary-value">≥ <?= s($cfgriskmed) ?></span>
            </div>
            <div class="summary-row">
              <span class="summary-label"><?= get_string('wizard_summary_high', 'local_saipa') ?></span>
              <span class="summary-value">≥ <?= s($cfgriskhigh) ?></span>
            </div>
            <div class="summary-row">
              <span class="summary-label"><?= get_string('wizard_summary_cooldown', 'local_saipa') ?></span>
              <span class="summary-value"><?= s($cfgcooldown) ?>h</span>
            </div>
          </div>

          <div class="summary-card">
            <h6><?= get_string('wizard_summary_features', 'local_saipa') ?></h6>
            <div class="summary-row">
              <span class="summary-label"><?= get_string('wizard_summary_risk_eval', 'local_saipa') ?></span>
              <span class="summary-value"><?= $cfgriskon
                ? get_string('wizard_enabled', 'local_saipa')
                : get_string('wizard_disabled', 'local_saipa') ?></span>
            </div>
            <div class="summary-row">
              <span class="summary-label"><?= get_string('wizard_summary_rag', 'local_saipa') ?></span>
              <span class="summary-value"><?= $cfgragon
                ? get_string('wizard_enabled', 'local_saipa')
                : get_string('wizard_disabled', 'local_saipa') ?></span>
            </div>
          </div>

        </div>
      </div>

      <div class="d-flex justify-content-between mt-2">
        <button class="btn btn-outline-secondary" data-spwiz-goto="5">
            <?= get_string('wizard_btn_back', 'local_saipa') ?>
        </button>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-secondary" id="sp-retry-btn" style="display:none"
                  data-spwiz-action="runtest"><?= get_string('wizard_btn_retry', 'local_saipa') ?></button>
          <button class="btn btn-success px-5" id="sp-save-btn" style="display:none"
                  data-spwiz-action="save"><?= get_string('wizard_btn_save_finish', 'local_saipa') ?></button>
        </div>
      </div>
    </div><!-- /step 6 -->


    <!-- ══════════════════════════════════════════
         STEP 7 — Done (inline)
         ══════════════════════════════════════════ -->
    <div class="spwiz-step" id="spwiz-step-7">
      <div class="done-card">
        <div class="done-icon">✅</div>
        <h3><?= get_string('wizard_done_inline_title', 'local_saipa') ?></h3>
        <p><?= get_string('wizard_done_inline_desc', 'local_saipa') ?></p>
        <div class="d-flex gap-3 justify-content-center flex-wrap">
          <a href="<?= s($settingsurl) ?>" class="btn btn-outline-secondary">
            <?= get_string('wizard_btn_admin', 'local_saipa') ?>
          </a>
          <a href="<?= (new moodle_url('/course/index.php'))->out() ?>" class="btn btn-primary btn-lg px-5">
            <?= get_string('wizard_btn_courses', 'local_saipa') ?>
          </a>
        </div>
      </div>
    </div><!-- /step 7 -->


    </div><!-- /card-body -->
  </div><!-- /card -->

  <!-- Hidden save form -->
  <form id="sp-save-form" method="post"
        action="<?= (new moodle_url('/local/saipa/setup.php', ['action' => 'save']))->out(false) ?>"
        style="display:none">
    <input type="hidden" name="sesskey"              value="<?= s($sesskey) ?>">
    <input type="hidden" name="engine_mode"          id="sf-mode"    value="">
    <input type="hidden" name="engine_url"           id="sf-url"     value="">
    <input type="hidden" name="engine_token"         id="sf-token"   value="">
    <input type="hidden" name="messaging_channel"    id="sf-channel" value="">
    <input type="hidden" name="telegram_bot_username" id="sf-tguser" value="">
    <input type="hidden" name="risk_threshold_medium" value="<?= s($cfgriskmed) ?>">
    <input type="hidden" name="risk_threshold_high"   value="<?= s($cfgriskhigh) ?>">
    <input type="hidden" name="alert_cooldown_hours"  value="<?= s($cfgcooldown) ?>">
    <input type="hidden" name="risk_eval_enabled"     value="<?= s($cfgriskon) ?>">
    <input type="hidden" name="rag_global_enabled"    value="<?= s($cfgragon) ?>">
  </form>

<?php endif; ?>
</div><!-- /spwiz -->


<?php
// phpcs:enable moodle.Commenting.MissingDocblock.File
$PAGE->requires->js_call_amd('local_saipa/setup_wizard', 'init');
echo $OUTPUT->footer();

