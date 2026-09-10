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

// ── AJAX: engine health check ─────────────────────────────────────────────────
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
        $client   = new \core\http_client(['timeout' => 10]);
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

// ── AJAX: Telegram bot token validation ──────────────────────────────────────
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
        $desc = $tg['description'] ?? 'Invalid response';
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

// ── POST: save all configuration ──────────────────────────────────────────────
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $mode            = required_param('engine_mode', PARAM_ALPHA);
    $engineurl      = required_param('engine_url', PARAM_URL);
    $enginetoken    = optional_param('engine_token', '', PARAM_RAW);
    $msgchannel     = optional_param('messaging_channel', 'none', PARAM_ALPHA);
    $tgusername     = optional_param('telegram_bot_username', '', PARAM_ALPHANUMEXT);
    $riskmed        = optional_param('risk_threshold_medium', '0.40', PARAM_FLOAT);
    $riskhigh       = optional_param('risk_threshold_high', '0.75', PARAM_FLOAT);
    $cooldown        = optional_param('alert_cooldown_hours', 24, PARAM_INT);
    $riskenabled    = optional_param('risk_eval_enabled', 1, PARAM_INT);
    $ragenabled     = optional_param('rag_global_enabled', 1, PARAM_INT);

    $validmodes = ['local_ollama', 'cloud_api', 'saipa_cloud', 'custom'];
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

// ── Page render setup ─────────────────────────────────────────────────────────
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

// Detect server environment.
$phpverok    = version_compare(PHP_VERSION, '8.1.0', '>=');
$moodleverok = ($CFG->version >= 2024042200);
$curlok       = function_exists('curl_init');
$phpverstr   = PHP_VERSION;
$moodleverstr = $CFG->release ?? 'unknown';

echo $OUTPUT->header();

// phpcs:disable moodle.Commenting.MissingDocblock.File -- False positive: this sniff
// re-fires on every reopened PHP tag in the HTML template below, although the
// file docblock is present at the top of the file. Re-enabled at end of file.
?>
<style>
/* ════════════════════════════════════════════════════════
   SAIPA Setup Wizard — styles
   ════════════════════════════════════════════════════════ */
.spwiz { max-width: 820px; margin: 0 auto; padding: 0 0 80px; }

/* Progress bar */
.spwiz-progress { display: flex; align-items: flex-start; margin-bottom: 32px; }
.spwiz-progress .step {
    display: flex; flex-direction: column; align-items: center; gap: 5px;
    flex: 1; position: relative;
}
.spwiz-progress .step::after {
    content: ''; position: absolute; top: 15px; left: 50%; width: 100%;
    height: 2px; background: #dee2e6; z-index: 0;
}
.spwiz-progress .step:last-child::after { display: none; }
.spwiz-progress .step-circle {
    width: 30px; height: 30px; border-radius: 50%;
    background: #dee2e6; color: #6c757d;
    display: flex; align-items: center; justify-content: center;
    font-size: .78rem; font-weight: 700; position: relative; z-index: 1;
    transition: background .25s, color .25s;
}
.spwiz-progress .step.active .step-circle { background: #0d6efd; color: #fff; }
.spwiz-progress .step.done   .step-circle { background: #198754; color: #fff; }
.spwiz-progress .step.done::after         { background: #198754; }
.spwiz-progress .step-label { font-size: .65rem; color: #6c757d; text-align: center; line-height: 1.2; }
.spwiz-progress .step.active .step-label  { color: #0d6efd; font-weight: 600; }
.spwiz-progress .step.done   .step-label  { color: #198754; }

/* Steps */
.spwiz-step { display: none; }
.spwiz-step.active { display: block; animation: sp-fadein .2s ease; }
@keyframes sp-fadein { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:none; } }

/* Feature grid */
.feature-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.feature-item { display: flex; gap: 10px; }
.fi-icon { font-size: 1.4rem; flex-shrink: 0; line-height: 1.3; }
.feature-item h6 { font-size: .86rem; font-weight: 600; margin-bottom: 2px; }
.feature-item p  { font-size: .78rem; color: #6c757d; margin: 0; }

/* Requirements */
.req-section { border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden; margin-bottom: 18px; }
.req-section-header { padding: 10px 16px; font-weight: 700; font-size: .88rem;
                      display: flex; align-items: center; gap: 8px; }
.req-section-body   { padding: 14px 16px; }
.req-row { display: flex; align-items: flex-start; gap: 10px; padding: 6px 0;
           border-bottom: 1px solid #f5f5f5; }
.req-row:last-child { border-bottom: none; }
.req-status { font-size: 1rem; flex-shrink: 0; width: 20px; text-align: center; margin-top: 1px; }
.req-label  { flex: 1; font-size: .85rem; }
.req-label strong { display: block; }
.req-label span   { color: #6c757d; font-size: .78rem; }
.req-value  { font-size: .82rem; color: #6c757d; text-align: right; white-space: nowrap; }
.ai-provision-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 6px; }
.ai-pcard { border: 1px solid #dee2e6; border-radius: 8px; padding: 14px 14px 12px; font-size: .82rem; }
.ai-pcard .pc-head { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
.ai-pcard .pc-icon { font-size: 1.4rem; }
.ai-pcard h6 { font-size: .86rem; font-weight: 700; margin: 0; }
.ai-pcard p  { color: #6c757d; margin: 0; line-height: 1.4; }
.ai-pcard ul { margin: 6px 0 0 0; padding-left: 16px; color: #6c757d; }
.ai-pcard ul li { margin-bottom: 2px; }
.pc-local  { border-top: 3px solid #198754; }
.pc-cloud  { border-top: 3px solid #0d6efd; }
.pc-saipa  { border-top: 3px solid #fd7e14; }
.pc-custom { border-top: 3px solid #6c757d; }

/* Mode cards */
.mode-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.mode-card {
    border: 2px solid #dee2e6; border-radius: 10px; padding: 18px 16px 14px;
    cursor: pointer; transition: border-color .2s, box-shadow .2s; position: relative;
}
.mode-card:hover    { border-color: #86b7fe; box-shadow: 0 0 0 3px rgba(13,110,253,.08); }
.mode-card.selected { border-color: #0d6efd; box-shadow: 0 0 0 3px rgba(13,110,253,.12); }
.mode-card input[type=radio] { position: absolute; opacity: 0; pointer-events: none; }
.mode-card .mc-icon { font-size: 1.8rem; margin-bottom: 8px; }
.mode-card h5  { font-size: .92rem; font-weight: 700; margin-bottom: 5px; }
.mode-card p   { font-size: .78rem; color: #6c757d; margin: 0; }
.mode-badge {
    display: inline-block; font-size: .63rem; font-weight: 700;
    padding: 2px 7px; border-radius: 20px; margin-left: 5px; vertical-align: middle;
}
.badge-local  { background: #d1e7dd; color: #0a3622; }
.badge-cloud  { background: #cfe2ff; color: #084298; }
.badge-soon   { background: #fff3cd; color: #664d03; }
.badge-custom { background: #e2e3e5; color: #41464b; }

/* Telegram channel cards */
.channel-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
.channel-card {
    border: 2px solid #dee2e6; border-radius: 8px; padding: 16px 12px 12px;
    cursor: pointer; text-align: center; transition: border-color .2s, box-shadow .2s; position: relative;
}
.channel-card:hover    { border-color: #86b7fe; box-shadow: 0 0 0 3px rgba(13,110,253,.08); }
.channel-card.selected { border-color: #0d6efd; box-shadow: 0 0 0 3px rgba(13,110,253,.12); }
.channel-card input[type=radio] { position: absolute; opacity: 0; pointer-events: none; }
.channel-card .ch-icon { font-size: 1.8rem; margin-bottom: 6px; }
.channel-card h6 { font-size: .82rem; font-weight: 700; margin-bottom: 3px; }
.channel-card p  { font-size: .72rem; color: #6c757d; margin: 0; }

/* Bot test result */
#sp-bot-result { min-height: 36px; }

/* Health check */
.health-row { display: flex; align-items: flex-start; gap: 12px;
              padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
.health-row:last-child { border-bottom: none; }
.health-icon  { font-size: 1.2rem; width: 24px; text-align: center; flex-shrink: 0; }
.health-label { flex: 1; font-size: .88rem; }
.health-value { font-size: .82rem; color: #6c757d; text-align: right; }

/* Summary (step 6) */
.summary-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.summary-card { border: 1px solid #dee2e6; border-radius: 8px; padding: 14px; font-size: .84rem; }
.summary-card h6 { font-size: .82rem; font-weight: 700; color: #6c757d;
                   text-transform: uppercase; letter-spacing: .04em; margin-bottom: 8px; }
.summary-row { display: flex; justify-content: space-between; padding: 3px 0;
               border-bottom: 1px solid #f5f5f5; }
.summary-row:last-child { border-bottom: none; }
.summary-label { color: #6c757d; }
.summary-value { font-weight: 500; }

/* Done */
.done-card { text-align: center; padding: 44px 20px 36px; }
.done-card .done-icon { font-size: 3.5rem; margin-bottom: 14px; }
.done-card h3 { font-weight: 700; margin-bottom: 8px; }
.done-card p  { color: #6c757d; margin-bottom: 28px; }
</style>

<div class="spwiz mt-4">

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
    <div class="step active" data-step="1"><div class="step-circle">1</div><div class="step-label"><?= get_string('wizard_step_welcome', 'local_saipa') ?></div></div>
    <div class="step"        data-step="2"><div class="step-circle">2</div><div class="step-label"><?= get_string('wizard_step_requirements', 'local_saipa') ?></div></div>
    <div class="step"        data-step="3"><div class="step-circle">3</div><div class="step-label"><?= get_string('wizard_step_ai_mode', 'local_saipa') ?></div></div>
    <div class="step"        data-step="4"><div class="step-circle">4</div><div class="step-label"><?= get_string('wizard_step_engine', 'local_saipa') ?></div></div>
    <div class="step"        data-step="5"><div class="step-circle">5</div><div class="step-label"><?= get_string('wizard_step_telegram', 'local_saipa') ?></div></div>
    <div class="step"        data-step="6"><div class="step-circle">6</div><div class="step-label"><?= get_string('wizard_step_test', 'local_saipa') ?></div></div>
    <div class="step"        data-step="7"><div class="step-circle">7</div><div class="step-label"><?= get_string('wizard_step_done', 'local_saipa') ?></div></div>
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
        <button class="btn btn-primary px-5" onclick="spwizGoto(2)"><?= get_string('wizard_btn_next', 'local_saipa') ?></button>
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
                $blockinstalled = $DB->record_exists('config_plugins', ['plugin' => 'block_saipa', 'name' => 'version']);
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
              <span class="badge bg-warning text-dark" style="font-size:.72rem;"><?= get_string('wizard_req_enginepy_value', 'local_saipa') ?></span>
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
              <span class="badge bg-danger" style="font-size:.72rem;"><?= get_string('wizard_req_llm_value', 'local_saipa') ?></span>
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

            <div class="ai-pcard pc-saipa">
              <div class="pc-head"><div class="pc-icon">🌐</div>
                <h6>
                  <?= get_string('wizard_prov_saipa_title', 'local_saipa') ?>
                  <span class="mode-badge badge-soon"><?= get_string('wizard_prov_saipa_badge', 'local_saipa') ?></span>
                </h6></div>
              <p><?= get_string('wizard_prov_saipa_desc', 'local_saipa') ?></p>
              <ul>
                <li><?= get_string('wizard_prov_saipa_i1', 'local_saipa') ?></li>
                <li><?= get_string('wizard_prov_saipa_i2', 'local_saipa') ?></li>
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
        <button class="btn btn-outline-secondary" onclick="spwizGoto(1)"><?= get_string('wizard_btn_back', 'local_saipa') ?></button>
        <button class="btn btn-primary px-5" id="req-next-btn" disabled onclick="spwizGoto(3)"><?= get_string('wizard_btn_next', 'local_saipa') ?></button>
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
        <label class="mode-card <?= ($cfgmode === 'saipa_cloud') ? 'selected' : '' ?>" for="sp-mode-saipa">
          <input type="radio" name="engine_mode" id="sp-mode-saipa" value="saipa_cloud"
                 <?= ($cfgmode === 'saipa_cloud') ? 'checked' : '' ?>>
          <div class="mc-icon">🌐</div>
          <h5>
            <?= get_string('wizard_prov_saipa_title', 'local_saipa') ?>
            <span class="mode-badge badge-soon"><?= get_string('wizard_prov_saipa_badge', 'local_saipa') ?></span>
          </h5>
          <p><?= get_string('wizard_mode_saipa_desc', 'local_saipa') ?></p>
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
        <button class="btn btn-outline-secondary" onclick="spwizGoto(2)"><?= get_string('wizard_btn_back', 'local_saipa') ?></button>
        <button class="btn btn-primary px-5" onclick="spwizGoto(4)"><?= get_string('wizard_btn_next', 'local_saipa') ?></button>
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
      <div id="sp-hint-saipa_cloud" class="alert alert-warning mb-3 py-2 px-3" style="font-size:.82rem;display:none">
        <?= get_string('wizard_hint_saipa', 'local_saipa') ?>
      </div>
      <div id="sp-hint-custom" class="alert alert-light border mb-3 py-2 px-3" style="font-size:.82rem;display:none">
        <?= get_string('wizard_hint_custom', 'local_saipa') ?>
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold" for="sp-url"><?= get_string('wizard_url_label', 'local_saipa') ?> <span class="text-danger">*</span></label>
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
        <button class="btn btn-outline-secondary" onclick="spwizGoto(3)"><?= get_string('wizard_btn_back', 'local_saipa') ?></button>
        <button class="btn btn-primary px-5" onclick="spwizGoto(5)"><?= get_string('wizard_btn_next', 'local_saipa') ?></button>
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
              <button class="btn btn-outline-primary" type="button" onclick="spTestBot()">
                <?= get_string('wizard_btn_validate', 'local_saipa') ?>
              </button>
            </div>
            <div class="form-text"><?= get_string('wizard_tg_token_help', 'local_saipa') ?></div>
          </div>
          <div class="col-md-5">
            <label class="form-label fw-semibold" for="sp-tg-username"><?= get_string('wizard_tg_username_label', 'local_saipa') ?> <span class="text-danger">*</span></label>
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
        <button class="btn btn-outline-secondary" onclick="spwizGoto(4)"><?= get_string('wizard_btn_back', 'local_saipa') ?></button>
        <button class="btn btn-primary px-5" onclick="spwizGoto(6)"><?= get_string('wizard_btn_next', 'local_saipa') ?></button>
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
        <h6 class="text-muted text-uppercase" style="font-size:.75rem;letter-spacing:.05em;margin-bottom:10px;"><?= get_string('wizard_summary_heading', 'local_saipa') ?></h6>
        <div class="summary-grid mb-4">

          <div class="summary-card">
            <h6><?= get_string('wizard_summary_engine', 'local_saipa') ?></h6>
            <div class="summary-row">
              <span class="summary-label"><?= get_string('wizard_summary_mode', 'local_saipa') ?></span>
              <span class="summary-value" id="sum-mode">—</span>
            </div>
            <div class="summary-row">
              <span class="summary-label"><?= get_string('wizard_summary_url', 'local_saipa') ?></span>
              <span class="summary-value" id="sum-url" style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;direction:rtl">—</span>
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
              <span class="summary-value"><?= $cfgriskon ? get_string('wizard_enabled', 'local_saipa') : get_string('wizard_disabled', 'local_saipa') ?></span>
            </div>
            <div class="summary-row">
              <span class="summary-label"><?= get_string('wizard_summary_rag', 'local_saipa') ?></span>
              <span class="summary-value"><?= $cfgragon ? get_string('wizard_enabled', 'local_saipa') : get_string('wizard_disabled', 'local_saipa') ?></span>
            </div>
          </div>

        </div>
      </div>

      <div class="d-flex justify-content-between mt-2">
        <button class="btn btn-outline-secondary" onclick="spwizGoto(5)"><?= get_string('wizard_btn_back', 'local_saipa') ?></button>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-secondary" id="sp-retry-btn" style="display:none"
                  onclick="spRunTest()"><?= get_string('wizard_btn_retry', 'local_saipa') ?></button>
          <button class="btn btn-success px-5" id="sp-save-btn" style="display:none"
                  onclick="spSave()"><?= get_string('wizard_btn_save_finish', 'local_saipa') ?></button>
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
          <a href="<?= s($settingsurl) ?>" class="btn btn-outline-secondary"><?= get_string('wizard_btn_admin', 'local_saipa') ?></a>
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

<script>
(function () {
    'use strict';

    var L = <?= json_encode([
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
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    var currentStep = 1;
    var healthOk    = false;

    var modeLabels = {
        local_ollama: <?= json_encode(get_string('wizard_js_mode_local', 'local_saipa'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        cloud_api:    <?= json_encode(get_string('wizard_js_mode_cloud', 'local_saipa'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        saipa_cloud:  <?= json_encode(get_string('wizard_js_mode_saipa', 'local_saipa'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        custom:       <?= json_encode(get_string('wizard_js_mode_custom', 'local_saipa'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    };
    var channelLabels = {
        none:     <?= json_encode(get_string('wizard_js_ch_none', 'local_saipa'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        telegram: <?= json_encode(get_string('wizard_js_ch_telegram', 'local_saipa'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        whatsapp: <?= json_encode(get_string('wizard_js_ch_whatsapp', 'local_saipa'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        both:     <?= json_encode(get_string('wizard_js_ch_both', 'local_saipa'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    };

    // ── Navigation ────────────────────────────────────────────────────────────
    /**
     * SpwizGoto.
     */
    function spwizGoto(step) {
        var prev = document.getElementById('spwiz-step-' + currentStep);
        if (prev) prev.classList.remove('active');
        currentStep = step;
        var next = document.getElementById('spwiz-step-' + step);
        if (next) next.classList.add('active');

        document.querySelectorAll('#spwiz-progress .step').forEach(function (el) {
            var n = parseInt(el.dataset.step, 10);
            el.classList.remove('active', 'done');
            if (n === step) el.classList.add('active');
            if (n < step)   el.classList.add('done');
        });

        if (step === 4) updateHints();
        if (step === 5) updateChannelUI();
        if (step === 6) spRunTest();

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    window.spwizGoto = spwizGoto;

    // ── Requirements confirmation ─────────────────────────────────────────────
    var reqCheck   = document.getElementById('req-confirm');
    var reqNextBtn = document.getElementById('req-next-btn');
    if (reqCheck) {
        reqCheck.addEventListener('change', function () {
            reqNextBtn.disabled = !reqCheck.checked;
        });
    }

    // ── Mode cards ────────────────────────────────────────────────────────────
    document.querySelectorAll('.mode-card').forEach(function (card) {
        card.addEventListener('click', function () {
            document.querySelectorAll('.mode-card').forEach(function (c) { c.classList.remove('selected'); });
            card.classList.add('selected');
            card.querySelector('input[type=radio]').checked = true;
        });
    });

    /**
     * GetSelectedMode.
     */
    function getSelectedMode() {
        var checked = document.querySelector('input[name="engine_mode"]:checked');
        return checked ? checked.value : 'local_ollama';
    }

    /**
     * UpdateHints.
     */
    function updateHints() {
        var mode  = getSelectedMode();
        var modes = ['local_ollama', 'cloud_api', 'saipa_cloud', 'custom'];
        modes.forEach(function (m) {
            var el = document.getElementById('sp-hint-' + m);
            if (el) el.style.display = (m === mode) ? '' : 'none';
        });
    }

    // ── Channel cards ─────────────────────────────────────────────────────────
    document.querySelectorAll('.channel-card').forEach(function (card) {
        card.addEventListener('click', function () {
            document.querySelectorAll('.channel-card').forEach(function (c) { c.classList.remove('selected'); });
            card.classList.add('selected');
            card.querySelector('input[type=radio]').checked = true;
            updateChannelUI();
        });
    });

    /**
     * GetSelectedChannel.
     */
    function getSelectedChannel() {
        var checked = document.querySelector('input[name="messaging_channel"]:checked');
        return checked ? checked.value : 'none';
    }

    /**
     * UpdateChannelUI.
     */
    function updateChannelUI() {
        var ch  = getSelectedChannel();
        var tgSection  = document.getElementById('sp-telegram-section');
        var waNote     = document.getElementById('sp-whatsapp-note');

        if (tgSection) tgSection.style.display = (ch === 'telegram' || ch === 'both') ? '' : 'none';
        if (waNote)    waNote.style.display    = (ch === 'whatsapp' || ch === 'both') ? '' : 'none';
    }
    // Init on load.
    updateChannelUI();

    // ── Telegram bot test ─────────────────────────────────────────────────────
    /**
     * SpTestBot.
     */
    function spTestBot() {
        var resultEl  = document.getElementById('sp-bot-result');
        var tokenEl   = document.getElementById('sp-tg-token');
        var usernameEl = document.getElementById('sp-tg-username');

        if (!tokenEl || !tokenEl.value.trim()) {
            resultEl.innerHTML = '<div class="alert alert-warning py-2 px-3 mb-0" style="font-size:.82rem;">' + he(L.enter_token) + '</div>';
            return;
        }

        resultEl.innerHTML =
            '<div class="d-flex align-items-center gap-2 text-muted py-1">' +
            '<div class="spinner-border spinner-border-sm" role="status"></div>' +
            '<span>' + he(L.validating) + '</span></div>';

        var fd = new FormData();
        fd.append('action',    'testbot');
        fd.append('sesskey',   '<?= $sesskey ?>');
        fd.append('bot_token', tokenEl.value.trim());

        fetch('<?= (new moodle_url('/local/saipa/setup.php'))->out(false) ?>',
            { method: 'POST', credentials: 'same-origin', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.ok) {
                // Auto-fill the username field.
                if (usernameEl && data.username) {
                    usernameEl.value = data.username;
                }
                resultEl.innerHTML =
                    '<div class="alert alert-success py-2 px-3 mb-0" style="font-size:.82rem;">' +
                    '✅ <strong>' + he(L.bot_verified) + '</strong> @' + he(data.username) +
                    ' (' + he((data.name || '').trim()) + '). ' +
                    he(L.username_autofill) + '</div>';
            } else {
                resultEl.innerHTML =
                    '<div class="alert alert-danger py-2 px-3 mb-0" style="font-size:.82rem;">' +
                    '❌ <strong>' + he(L.error) + '</strong> ' + he(data.error || L.invalid_token) + '</div>';
            }
        })
        .catch(function (e) {
            resultEl.innerHTML =
                '<div class="alert alert-danger py-2 px-3 mb-0" style="font-size:.82rem;">' +
                '❌ ' + he(L.network_error) + ' ' + he(e.message) + '</div>';
        });
    }
    window.spTestBot = spTestBot;

    // ── Health check ──────────────────────────────────────────────────────────
    /**
     * SpRunTest.
     */
    function spRunTest() {
        healthOk = false;
        var resultEl = document.getElementById('sp-health-result');
        var saveBtn  = document.getElementById('sp-save-btn');
        var retryBtn = document.getElementById('sp-retry-btn');
        var summaryEl = document.getElementById('sp-summary');

        saveBtn.style.display  = 'none';
        retryBtn.style.display = 'none';
        if (summaryEl) summaryEl.style.display = 'none';

        resultEl.innerHTML =
            '<div class="d-flex align-items-center gap-2 text-muted py-2">' +
            '<div class="spinner-border spinner-border-sm" role="status"></div>' +
            '<span>' + he(L.connecting) + '</span></div>';

        var url   = (document.getElementById('sp-url')   || {}).value || '';
        var token = (document.getElementById('sp-token') || {}).value || '';
        url = url.trim();

        if (!url) {
            renderHealthError(L.url_empty, url);
            return;
        }

        var fd = new FormData();
        fd.append('action',       'health');
        fd.append('sesskey',      '<?= $sesskey ?>');
        fd.append('engine_url',   url);
        fd.append('engine_token', token);

        fetch('<?= (new moodle_url('/local/saipa/setup.php'))->out(false) ?>',
            { method: 'POST', credentials: 'same-origin', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.ok) {
                healthOk = true;
                resultEl.innerHTML =
                    '<div class="health-row">' +
                    '<div class="health-icon">✅</div>' +
                    '<div class="health-label"><strong>' + he(L.engine_reachable) + '</strong></div>' +
                    '<div class="health-value">' + he(url) + '</div></div>' +
                    '<div class="health-row">' +
                    '<div class="health-icon">🔢</div>' +
                    '<div class="health-label">' + he(L.engine_version) + '</div>' +
                    '<div class="health-value">' + he(data.version) + '</div></div>' +
                    '<div class="health-row">' +
                    '<div class="health-icon">⏱️</div>' +
                    '<div class="health-label">' + he(L.uptime) + '</div>' +
                    '<div class="health-value">' + he(data.uptime) + '</div></div>' +
                    '<div class="alert alert-success py-2 px-3 mt-3 mb-0" style="font-size:.85rem;">' +
                    L.success + '</div>';

                // Populate summary.
                var usernameEl = document.getElementById('sp-tg-username');
                setText('sum-mode',    modeLabels[getSelectedMode()] || getSelectedMode());
                setText('sum-url',     url);
                setText('sum-token',   token ? '●●●●●●●●' : L.not_configured);
                setText('sum-channel', channelLabels[getSelectedChannel()] || getSelectedChannel());
                setText('sum-tg',      usernameEl && usernameEl.value ? '@' + usernameEl.value : L.not_configured);
                if (summaryEl) summaryEl.style.display = '';
                saveBtn.style.display = '';
            } else {
                renderHealthError(data.error || L.unknown_error, url);
            }
        })
        .catch(function (e) { renderHealthError(e.message || L.network_error, url); });
    }
    window.spRunTest = spRunTest;

    /**
     * RenderHealthError.
     */
    function renderHealthError(msg, url) {
        document.getElementById('sp-health-result').innerHTML =
            '<div class="health-row">' +
            '<div class="health-icon">❌</div>' +
            '<div class="health-label"><strong>' + he(L.connection_failed) + '</strong></div>' +
            '<div class="health-value">' + he(url || '—') + '</div></div>' +
            '<div class="alert alert-danger py-2 px-3 mt-3 mb-0" style="font-size:.84rem;">' +
            '<strong>' + he(L.error) + '</strong> ' + he(msg) + '<br><br>' +
            '<strong>' + he(L.troubleshooting) + '</strong>' +
            '<ul class="mb-0 mt-1">' +
            '<li>' + L.ts_running + '</li>' +
            '<li>' + L.ts_url + '</li>' +
            '<li>' + L.ts_docker + '</li>' +
            '<li>' + L.ts_token + '</li>' +
            '</ul></div>';
        document.getElementById('sp-retry-btn').style.display = '';
    }

    // ── Save ──────────────────────────────────────────────────────────────────
    /**
     * SpSave.
     */
    function spSave() {
        var usernameEl = document.getElementById('sp-tg-username');
        document.getElementById('sf-mode').value    = getSelectedMode();
        document.getElementById('sf-url').value     = (document.getElementById('sp-url')   || {}).value || '';
        document.getElementById('sf-token').value   = (document.getElementById('sp-token') || {}).value || '';
        document.getElementById('sf-channel').value = getSelectedChannel();
        document.getElementById('sf-tguser').value  = usernameEl ? usernameEl.value : '';
        document.getElementById('sp-save-form').submit();
    }
    window.spSave = spSave;

    // ── Helpers ───────────────────────────────────────────────────────────────
    /**
     * He.
     */
    function he(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;')
                        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    /**
     * SetText.
     */
    function setText(id, val) {
        var el = document.getElementById(id);
        if (el) el.textContent = val;
    }
}());
</script>

<?php
// phpcs:enable moodle.Commenting.MissingDocblock.File
echo $OUTPUT->footer();

