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
$PAGE->set_title('SAIPA — Setup Wizard');
$PAGE->set_heading('SAIPA Setup Wizard');

$action = optional_param('action', '', PARAM_ALPHA);

// ── AJAX: engine health check ─────────────────────────────────────────────────
if ($action === 'health') {
    require_sesskey();
    header('Content-Type: application/json');
    header('X-Content-Type-Options: nosniff');

    $engine_url   = optional_param('engine_url',   '', PARAM_URL);
    $engine_token = optional_param('engine_token', '', PARAM_RAW);

    if (empty($engine_url)) {
        echo json_encode(['error' => 'Engine URL is required.']);
        die();
    }

    $url     = rtrim($engine_url, '/') . '/health';
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . $engine_token,
    ];
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $resp  = curl_exec($ch);
    $errno = curl_errno($ch);
    $err   = curl_error($ch);
    $http  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno) {
        echo json_encode(['error' => 'Connection failed: ' . $err]);
        die();
    }
    if ($http !== 200) {
        echo json_encode(['error' => "Engine returned HTTP $http. Check the URL and token."]);
        die();
    }
    $decoded = json_decode($resp, true);
    if (!$decoded || ($decoded['status'] ?? '') !== 'ok') {
        echo json_encode(['error' => 'Unexpected engine response: ' . substr($resp, 0, 200)]);
        die();
    }
    echo json_encode([
        'ok'      => true,
        'version' => $decoded['version']        ?? '?',
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

    $bot_token = optional_param('bot_token', '', PARAM_RAW);
    if (empty($bot_token)) {
        echo json_encode(['error' => 'No bot token provided.']);
        die();
    }

    // Call Telegram's getMe to validate the token (no data stored, read-only).
    $tg_url = 'https://api.telegram.org/bot' . urlencode($bot_token) . '/getMe';
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $tg_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $resp  = curl_exec($ch);
    $errno = curl_errno($ch);
    $err   = curl_error($ch);
    curl_close($ch);

    if ($errno) {
        echo json_encode(['error' => 'Could not reach Telegram API: ' . $err]);
        die();
    }
    $tg = json_decode($resp, true);
    if (!$tg || empty($tg['ok'])) {
        $desc = $tg['description'] ?? 'Invalid response';
        echo json_encode(['error' => 'Telegram API error: ' . $desc]);
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

    $mode            = required_param('engine_mode',          PARAM_ALPHA);
    $engine_url      = required_param('engine_url',           PARAM_URL);
    $engine_token    = optional_param('engine_token',         '', PARAM_RAW);
    $msg_channel     = optional_param('messaging_channel',    'none', PARAM_ALPHA);
    $tg_username     = optional_param('telegram_bot_username','', PARAM_ALPHANUMEXT);
    $risk_med        = optional_param('risk_threshold_medium','0.40', PARAM_FLOAT);
    $risk_high       = optional_param('risk_threshold_high',  '0.75', PARAM_FLOAT);
    $cooldown        = optional_param('alert_cooldown_hours', 24, PARAM_INT);
    $risk_enabled    = optional_param('risk_eval_enabled',    1, PARAM_INT);
    $rag_enabled     = optional_param('rag_global_enabled',   1, PARAM_INT);

    $valid_modes = ['local_ollama', 'cloud_api', 'saipa_cloud', 'custom'];
    if (!in_array($mode, $valid_modes)) { $mode = 'custom'; }
    $valid_channels = ['none', 'telegram', 'whatsapp', 'both'];
    if (!in_array($msg_channel, $valid_channels)) { $msg_channel = 'none'; }

    set_config('engine_mode',            $mode,         'local_saipa');
    set_config('engine_url',             $engine_url,   'local_saipa');
    set_config('engine_token',           $engine_token, 'local_saipa');
    set_config('messaging_channel',      $msg_channel,  'local_saipa');
    set_config('telegram_bot_username',  $tg_username,  'local_saipa');
    set_config('risk_threshold_medium',  $risk_med,     'local_saipa');
    set_config('risk_threshold_high',    $risk_high,    'local_saipa');
    set_config('alert_cooldown_hours',   $cooldown,     'local_saipa');
    set_config('risk_eval_enabled',      $risk_enabled, 'local_saipa');
    set_config('rag_global_enabled',     $rag_enabled,  'local_saipa');
    set_config('setup_complete',         1,             'local_saipa');

    redirect(
        new moodle_url('/local/saipa/setup.php', ['done' => 1]),
        'SAIPA configuration saved successfully.',
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// ── Page render setup ─────────────────────────────────────────────────────────
$done         = optional_param('done', 0, PARAM_INT);
$sesskey      = sesskey();
$settings_url = (new moodle_url('/admin/settings.php', ['section' => 'local_saipa']))->out(false);
$teacher_url  = (new moodle_url('/local/saipa/teacher.php'))->out(false);

// Pre-fill from existing config (wizard re-run).
$cfg_mode    = get_config('local_saipa', 'engine_mode')           ?: 'local_ollama';
$cfg_url     = get_config('local_saipa', 'engine_url')            ?: 'http://localhost:8052';
$cfg_token   = get_config('local_saipa', 'engine_token')          ?: '';
$cfg_channel = get_config('local_saipa', 'messaging_channel')     ?: 'none';
$cfg_tg_user = get_config('local_saipa', 'telegram_bot_username') ?: '';
$cfg_risk_med  = get_config('local_saipa', 'risk_threshold_medium')  ?: '0.40';
$cfg_risk_high = get_config('local_saipa', 'risk_threshold_high')    ?: '0.75';
$cfg_cooldown  = get_config('local_saipa', 'alert_cooldown_hours')   ?: '24';
$cfg_risk_on   = get_config('local_saipa', 'risk_eval_enabled')      ?? '1';
$cfg_rag_on    = get_config('local_saipa', 'rag_global_enabled')     ?? '1';

// Detect server environment.
$php_ver_ok    = version_compare(PHP_VERSION, '8.1.0', '>=');
$moodle_ver_ok = ($CFG->version >= 2024042200);
$curl_ok       = function_exists('curl_init');
$php_ver_str   = PHP_VERSION;
$moodle_ver_str = $CFG->release ?? 'unknown';

echo $OUTPUT->header();
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

<?php if ($done): ?>

  <!-- Completion screen (after save + redirect) -->
  <div class="card shadow-sm">
    <div class="card-body done-card">
      <div class="done-icon">🎉</div>
      <h3>SAIPA is ready!</h3>
      <p>The AI companion has been configured and is ready to assist teachers and students.<br>
         Add the <strong>SAIPA block</strong> to a course to get started.</p>
      <div class="d-flex gap-3 justify-content-center flex-wrap">
        <a href="<?= s($settings_url) ?>" class="btn btn-outline-secondary">⚙️ Admin Settings</a>
        <a href="<?= s($teacher_url) ?>" class="btn btn-outline-primary">📊 Teacher Dashboard</a>
        <a href="<?= (new moodle_url('/course/index.php'))->out() ?>" class="btn btn-primary btn-lg px-5">
          Go to My Courses →
        </a>
      </div>
    </div>
  </div>

<?php else: ?>

  <!-- Progress bar -->
  <div class="spwiz-progress" id="spwiz-progress">
    <div class="step active" data-step="1"><div class="step-circle">1</div><div class="step-label">Welcome</div></div>
    <div class="step"        data-step="2"><div class="step-circle">2</div><div class="step-label">Requirements</div></div>
    <div class="step"        data-step="3"><div class="step-circle">3</div><div class="step-label">AI Mode</div></div>
    <div class="step"        data-step="4"><div class="step-circle">4</div><div class="step-label">Engine</div></div>
    <div class="step"        data-step="5"><div class="step-circle">5</div><div class="step-label">Telegram</div></div>
    <div class="step"        data-step="6"><div class="step-circle">6</div><div class="step-label">Test</div></div>
    <div class="step"        data-step="7"><div class="step-circle">7</div><div class="step-label">Done</div></div>
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
          <h3 class="mb-1">Welcome to SAIPA</h3>
          <p class="text-muted mb-0">
            This wizard configures the AI companion in a few steps.
            It covers the engine connection, notification channels, and core parameters.
          </p>
        </div>
      </div>
      <hr class="my-3">

      <p class="mb-3" style="font-size:.9rem;">
        SAIPA (Sistema de Acompañamiento Inteligente Pedagógico con IA) helps teachers
        detect at-risk students early and supports learning through AI-powered tools:
      </p>

      <div class="feature-grid mb-4">
        <div class="feature-item">
          <div class="fi-icon">🔴</div>
          <div><h6>Dropout Risk Detection</h6>
            <p>XGBoost model predicts dropout probability from 11 engagement features. Risk badges per student: 🟢 Low / 🟡 Medium / 🔴 High.</p></div>
        </div>
        <div class="feature-item">
          <div class="fi-icon">💬</div>
          <div><h6>RAG-Powered Chat</h6>
            <p>Students and teachers chat with an AI assistant that has context from the course materials. Role-aware responses.</p></div>
        </div>
        <div class="feature-item">
          <div class="fi-icon">📲</div>
          <div><h6>Proactive Telegram Alerts</h6>
            <p>Teachers send personalised AI-generated alerts to at-risk students directly from the dashboard via Telegram.</p></div>
        </div>
        <div class="feature-item">
          <div class="fi-icon">📊</div>
          <div><h6>Advisor Dashboard</h6>
            <p>Institution-wide view: risk distribution, engagement trends, per-course health across all courses.</p></div>
        </div>
        <div class="feature-item">
          <div class="fi-icon">📚</div>
          <div><h6>Course Indexing</h6>
            <p>Index Moodle Pages, PDFs, and PPTX presentations into a vector database for RAG retrieval.</p></div>
        </div>
        <div class="feature-item">
          <div class="fi-icon">🤝</div>
          <div><h6>EVAL-IA Compatible</h6>
            <p>When both plugins are installed, EVAL-IA automatically inherits SAIPA's engine configuration.</p></div>
        </div>
      </div>

      <div class="d-flex justify-content-end">
        <button class="btn btn-primary px-5" onclick="spwizGoto(2)">Next →</button>
      </div>
    </div><!-- /step 1 -->


    <!-- ══════════════════════════════════════════
         STEP 2 — Requirements (CRITICAL)
         ══════════════════════════════════════════ -->
    <div class="spwiz-step" id="spwiz-step-2">

      <h4 class="mb-1">Minimum requirements</h4>
      <p class="text-muted mb-4" style="font-size:.88rem;">
        Verify that your environment meets all requirements before continuing.
        <strong>SAIPA will not work without an active AI service.</strong>
      </p>

      <!-- Platform -->
      <div class="req-section">
        <div class="req-section-header" style="background:#f8f9fa;">🖥️ Platform</div>
        <div class="req-section-body">

          <div class="req-row">
            <div class="req-status"><?= $moodle_ver_ok ? '✅' : '❌' ?></div>
            <div class="req-label">
              <strong>Moodle 4.4 or 4.5</strong>
              <span>Older versions are not supported.</span>
            </div>
            <div class="req-value"><?= s($moodle_ver_str) ?></div>
          </div>

          <div class="req-row">
            <div class="req-status"><?= $php_ver_ok ? '✅' : '❌' ?></div>
            <div class="req-label">
              <strong>PHP 8.1+</strong>
              <span>PHP 7.x is not supported.</span>
            </div>
            <div class="req-value"><?= s($php_ver_str) ?></div>
          </div>

          <div class="req-row">
            <div class="req-status"><?= $curl_ok ? '✅' : '❌' ?></div>
            <div class="req-label">
              <strong>PHP cURL extension</strong>
              <span>Required to communicate with the AI engine and Telegram API.</span>
            </div>
            <div class="req-value"><?= $curl_ok ? 'Enabled' : '<span class="text-danger">Missing</span>' ?></div>
          </div>

          <div class="req-row">
            <div class="req-status">ℹ️</div>
            <div class="req-label">
              <strong>block_saipa (companion block)</strong>
              <span>Required — provides the chat widget in course sidebars.</span>
            </div>
            <div class="req-value">
              <?php
              $block_installed = $DB->record_exists('config_plugins', ['plugin' => 'block_saipa', 'name' => 'version']);
              echo $block_installed
                  ? '<span class="text-success">Installed</span>'
                  : '<span class="text-danger">Not installed — <a href="' .
                    (new moodle_url('/admin/index.php'))->out() . '">install now</a></span>';
              ?>
            </div>
          </div>

        </div>
      </div>

      <!-- AI Engine -->
      <div class="req-section" style="border-color:#f0ad4e;">
        <div class="req-section-header" style="background:#fff8e1;color:#856404;border-bottom:1px solid #f0e0a0;">
          ⚠️ AI Engine — <em>Required. SAIPA will not function without this.</em>
        </div>
        <div class="req-section-body">

          <p style="font-size:.87rem;margin-bottom:14px;">
            SAIPA uses the <strong>saipa-engine</strong> Python service for all AI operations:
            course chat (RAG), dropout risk prediction, alert generation, and Telegram integration.
            This service must be running and reachable from this Moodle server.
          </p>

          <div class="req-row">
            <div class="req-status">🐍</div>
            <div class="req-label">
              <strong>saipa-engine (Python 3.11+ / FastAPI)</strong>
              <span>Handles LLM inference, vector search (ChromaDB), XGBoost risk model, and Telegram bot.</span>
            </div>
            <div class="req-value" style="white-space:normal;max-width:200px;text-align:right;">
              <span class="badge bg-warning text-dark" style="font-size:.72rem;">Must be deployed separately</span>
            </div>
          </div>

          <div class="req-row">
            <div class="req-status">🗄️</div>
            <div class="req-label">
              <strong>ChromaDB (embedded in saipa-engine)</strong>
              <span>Vector database for RAG over indexed course materials.</span>
            </div>
            <div class="req-value">Included in engine</div>
          </div>

          <div class="req-row">
            <div class="req-status">🔤</div>
            <div class="req-label">
              <strong>Large Language Model (LLM)</strong>
              <span>Powers chat, risk explanations, and alert generation. See provisioning options below.</span>
            </div>
            <div class="req-value" style="white-space:normal;max-width:200px;text-align:right;">
              <span class="badge bg-danger" style="font-size:.72rem;">AI service required</span>
            </div>
          </div>

          <div class="req-row">
            <div class="req-status">📊</div>
            <div class="req-label">
              <strong>XGBoost risk model</strong>
              <span>Pre-trained model included in saipa-engine. Requires at least 4 weeks of student activity data for meaningful predictions.</span>
            </div>
            <div class="req-value">Included in engine</div>
          </div>

        </div>
      </div>

      <!-- AI Provisioning options -->
      <div class="req-section" style="border-color:#0d6efd;">
        <div class="req-section-header" style="background:#e7f1ff;color:#084298;border-bottom:1px solid #b6d4fe;">
          🤖 AI service provisioning — choose one option
        </div>
        <div class="req-section-body">

          <p style="font-size:.85rem;margin-bottom:14px;color:#495057;">
            The LLM that powers SAIPA can come from three sources. You must have at least one ready.
          </p>

          <div class="ai-provision-cards">

            <div class="ai-pcard pc-local">
              <div class="pc-head"><div class="pc-icon">🖥️</div>
                <h6>Local — Ollama <span class="mode-badge badge-local">SELF-HOSTED</span></h6></div>
              <p>Run the LLM on your own server using <a href="https://ollama.com" target="_blank">Ollama</a>. Full privacy — no data leaves your infrastructure.</p>
              <ul>
                <li>Recommended: <code>qwen2.5:14b</code> (≥16 GB RAM)</li>
                <li>Minimum: any 7B model (≥8 GB RAM)</li>
                <li>saipa-engine must have network access to Ollama</li>
              </ul>
            </div>

            <div class="ai-pcard pc-cloud">
              <div class="pc-head"><div class="pc-icon">☁️</div>
                <h6>Cloud API <span class="mode-badge badge-cloud">OPENAI-COMPATIBLE</span></h6></div>
              <p>Any OpenAI-compatible API (OpenAI, Azure, Groq, Mistral…) with your own key.</p>
              <ul>
                <li>No local GPU required</li>
                <li>Cost depends on usage and provider</li>
                <li>Set <code>OPENAI_API_KEY</code> in saipa-engine's <code>.env</code></li>
              </ul>
            </div>

            <div class="ai-pcard pc-saipa">
              <div class="pc-head"><div class="pc-icon">🌐</div>
                <h6>SAIPA Cloud <span class="mode-badge badge-soon">COMING SOON</span></h6></div>
              <p>Fully managed engine. No Ollama, no ChromaDB to install. Subscribe and connect.</p>
              <ul>
                <li>Zero infrastructure to manage</li>
                <li>Join waitlist at <code>cloud.saipa.online</code></li>
              </ul>
            </div>

            <div class="ai-pcard pc-custom">
              <div class="pc-head"><div class="pc-icon">⚙️</div>
                <h6>Custom / Enterprise <span class="mode-badge badge-custom">ADVANCED</span></h6></div>
              <p>Any compatible engine at a custom URL. Full control for advanced deployments.</p>
              <ul>
                <li>Must implement <code>GET /health</code></li>
                <li>Must implement <code>POST /chat</code> and related endpoints</li>
              </ul>
            </div>

          </div>

          <div class="alert alert-danger mt-3 mb-0 py-2 px-3" style="font-size:.84rem;">
            <strong>⛔ Without an active AI service, SAIPA will not be able to:</strong>
            respond to student chat messages, generate risk scores, create Telegram alerts,
            index course materials, or provide advisor-level analytics.
            All these functions depend exclusively on the AI engine.
            <strong>Do not continue</strong> unless you have one of the options above deployed and ready.
          </div>

        </div>
      </div>

      <!-- Confirmation checkbox -->
      <div class="form-check mt-3 mb-1">
        <input class="form-check-input" type="checkbox" id="req-confirm">
        <label class="form-check-label" for="req-confirm" style="font-size:.88rem;">
          I have read the requirements above. An AI service (saipa-engine + LLM) is deployed
          and reachable from this server.
        </label>
      </div>

      <div class="d-flex justify-content-between mt-3">
        <button class="btn btn-outline-secondary" onclick="spwizGoto(1)">← Back</button>
        <button class="btn btn-primary px-5" id="req-next-btn" disabled onclick="spwizGoto(3)">Next →</button>
      </div>
    </div><!-- /step 2 -->


    <!-- ══════════════════════════════════════════
         STEP 3 — AI Mode
         ══════════════════════════════════════════ -->
    <div class="spwiz-step" id="spwiz-step-3">

      <h4 class="mb-1">Choose your AI provisioning mode</h4>
      <p class="text-muted mb-4" style="font-size:.88rem;">Select the option that matches your deployed AI infrastructure.</p>

      <div class="mode-cards">
        <label class="mode-card <?= ($cfg_mode==='local_ollama')?'selected':'' ?>" for="sp-mode-local">
          <input type="radio" name="engine_mode" id="sp-mode-local" value="local_ollama"
                 <?= ($cfg_mode==='local_ollama')?'checked':'' ?>>
          <div class="mc-icon">🖥️</div>
          <h5>Local — Ollama <span class="mode-badge badge-local">SELF-HOSTED</span></h5>
          <p>saipa-engine running on your server with Ollama as the LLM backend. Full data privacy.</p>
        </label>
        <label class="mode-card <?= ($cfg_mode==='cloud_api')?'selected':'' ?>" for="sp-mode-cloud">
          <input type="radio" name="engine_mode" id="sp-mode-cloud" value="cloud_api"
                 <?= ($cfg_mode==='cloud_api')?'checked':'' ?>>
          <div class="mc-icon">☁️</div>
          <h5>Cloud API <span class="mode-badge badge-cloud">OPENAI-COMPATIBLE</span></h5>
          <p>saipa-engine configured with an OpenAI-compatible API key. No local GPU required.</p>
        </label>
        <label class="mode-card <?= ($cfg_mode==='saipa_cloud')?'selected':'' ?>" for="sp-mode-saipa">
          <input type="radio" name="engine_mode" id="sp-mode-saipa" value="saipa_cloud"
                 <?= ($cfg_mode==='saipa_cloud')?'checked':'' ?>>
          <div class="mc-icon">🌐</div>
          <h5>SAIPA Cloud <span class="mode-badge badge-soon">COMING SOON</span></h5>
          <p>Fully managed engine by Schaller &amp; Ponce. Subscribe and connect with a single API key.</p>
        </label>
        <label class="mode-card <?= ($cfg_mode==='custom')?'selected':'' ?>" for="sp-mode-custom">
          <input type="radio" name="engine_mode" id="sp-mode-custom" value="custom"
                 <?= ($cfg_mode==='custom')?'checked':'' ?>>
          <div class="mc-icon">⚙️</div>
          <h5>Custom / Enterprise <span class="mode-badge badge-custom">ADVANCED</span></h5>
          <p>Any compatible engine at a custom URL. Full control for advanced deployments.</p>
        </label>
      </div>

      <div class="d-flex justify-content-between mt-4">
        <button class="btn btn-outline-secondary" onclick="spwizGoto(2)">← Back</button>
        <button class="btn btn-primary px-5" onclick="spwizGoto(4)">Next →</button>
      </div>
    </div><!-- /step 3 -->


    <!-- ══════════════════════════════════════════
         STEP 4 — Engine connection
         ══════════════════════════════════════════ -->
    <div class="spwiz-step" id="spwiz-step-4">

      <h4 class="mb-1">Engine connection</h4>
      <p class="text-muted mb-4" style="font-size:.88rem;">Enter the URL and token for the saipa-engine service.</p>

      <div id="sp-hint-local_ollama" class="alert alert-light border mb-3 py-2 px-3" style="font-size:.82rem;">
        <strong>🖥️ Local / Ollama:</strong>
        Default port is <code>8052</code>. If running via Docker on the same host, use
        <code>http://localhost:8052</code>. If Moodle itself runs in Docker, use
        <code>http://host.docker.internal:8052</code>.
      </div>
      <div id="sp-hint-cloud_api" class="alert alert-light border mb-3 py-2 px-3" style="font-size:.82rem;">
        <strong>☁️ Cloud API:</strong>
        Enter the URL of your saipa-engine instance (configured with your cloud API key)
        and the <code>ENGINE_SECRET</code> token.
      </div>
      <div id="sp-hint-saipa_cloud" class="alert alert-warning mb-3 py-2 px-3" style="font-size:.82rem;">
        <strong>🌐 SAIPA Cloud is not yet available.</strong>
        Please select Local or Cloud API to continue.
      </div>
      <div id="sp-hint-custom" class="alert alert-light border mb-3 py-2 px-3" style="font-size:.82rem;">
        <strong>⚙️ Custom:</strong>
        Enter the base URL of your engine. The wizard will verify
        <code>{url}/health</code> returns <code>{"status":"ok"}</code>.
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold" for="sp-url">Engine URL <span class="text-danger">*</span></label>
        <input type="url" class="form-control" id="sp-url"
               placeholder="http://localhost:8052"
               value="<?= s($cfg_url) ?>">
        <div class="form-text">Base URL of the saipa-engine — no trailing slash.</div>
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold" for="sp-token">Engine Token</label>
        <input type="password" class="form-control" id="sp-token"
               placeholder="Leave blank if not configured"
               value="<?= s($cfg_token) ?>">
        <div class="form-text">Value of <code>ENGINE_SECRET</code> in the engine's <code>.env</code>. Leave blank if not set.</div>
      </div>

      <div class="d-flex justify-content-between mt-4">
        <button class="btn btn-outline-secondary" onclick="spwizGoto(3)">← Back</button>
        <button class="btn btn-primary px-5" onclick="spwizGoto(5)">Next →</button>
      </div>
    </div><!-- /step 4 -->


    <!-- ══════════════════════════════════════════
         STEP 5 — Telegram Bot (SAIPA-specific)
         ══════════════════════════════════════════ -->
    <div class="spwiz-step" id="spwiz-step-5">

      <h4 class="mb-1">Notification channels</h4>
      <p class="text-muted mb-3" style="font-size:.88rem;">
        Choose how SAIPA delivers proactive alerts to students and teachers.
        Telegram is optional but strongly recommended — it is SAIPA's most powerful engagement feature.
      </p>

      <!-- Channel selector -->
      <div class="channel-cards mb-4">
        <label class="channel-card <?= ($cfg_channel==='none')?'selected':'' ?>" for="ch-none">
          <input type="radio" name="messaging_channel" id="ch-none" value="none"
                 <?= ($cfg_channel==='none')?'checked':'' ?>>
          <div class="ch-icon">🔕</div><h6>None</h6>
          <p>Moodle notifications only. Alerts visible inside Moodle.</p>
        </label>
        <label class="channel-card <?= ($cfg_channel==='telegram')?'selected':'' ?>" for="ch-telegram">
          <input type="radio" name="messaging_channel" id="ch-telegram" value="telegram"
                 <?= ($cfg_channel==='telegram')?'checked':'' ?>>
          <div class="ch-icon">✈️</div><h6>Telegram</h6>
          <p>Students receive alerts and AI chat via Telegram. Recommended.</p>
        </label>
        <label class="channel-card <?= ($cfg_channel==='whatsapp')?'selected':'' ?>" for="ch-whatsapp">
          <input type="radio" name="messaging_channel" id="ch-whatsapp" value="whatsapp"
                 <?= ($cfg_channel==='whatsapp')?'checked':'' ?>>
          <div class="ch-icon">💬</div><h6>WhatsApp</h6>
          <p>Requires Twilio or Meta Cloud API. Configure after setup.</p>
        </label>
        <label class="channel-card <?= ($cfg_channel==='both')?'selected':'' ?>" for="ch-both">
          <input type="radio" name="messaging_channel" id="ch-both" value="both"
                 <?= ($cfg_channel==='both')?'checked':'' ?>>
          <div class="ch-icon">📡</div><h6>Both</h6>
          <p>Telegram + WhatsApp. Maximum reach.</p>
        </label>
      </div>

      <!-- Telegram details (shown when telegram or both is selected) -->
      <div id="sp-telegram-section">

        <hr class="mb-3">
        <h5 class="mb-1" style="font-size:.95rem;">✈️ Telegram Bot configuration</h5>
        <p class="text-muted mb-3" style="font-size:.83rem;">
          SAIPA uses a Telegram bot to deliver alerts and enable bidirectional chat with students.
          The bot token lives in <strong>saipa-engine's <code>.env</code> file</strong>
          (<code>TELEGRAM_BOT_TOKEN</code>); the bot username is stored in Moodle for display purposes.
        </p>

        <!-- How to create a bot -->
        <div class="alert alert-light border mb-3 py-2 px-3" style="font-size:.82rem;">
          <strong>How to create a Telegram bot:</strong>
          <ol class="mb-0 mt-1 ps-3">
            <li>Open Telegram and search for <strong>@BotFather</strong></li>
            <li>Send <code>/newbot</code> and follow the prompts</li>
            <li>Copy the token (format: <code>1234567890:AABCD...</code>)</li>
            <li>Add the token to saipa-engine's <code>.env</code>: <code>TELEGRAM_BOT_TOKEN=&lt;token&gt;</code></li>
            <li>Restart the engine: <code>docker compose restart saipa-engine</code></li>
          </ol>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-7">
            <label class="form-label fw-semibold" for="sp-tg-token">Bot token <small class="text-muted fw-normal">(for validation only — not saved to Moodle)</small></label>
            <div class="input-group">
              <input type="password" class="form-control" id="sp-tg-token"
                     placeholder="1234567890:AABCDEF..."
                     autocomplete="off">
              <button class="btn btn-outline-primary" type="button" onclick="spTestBot()">
                Validate
              </button>
            </div>
            <div class="form-text">Enter the token to verify it works. It will NOT be stored here — only the username is saved to Moodle.</div>
          </div>
          <div class="col-md-5">
            <label class="form-label fw-semibold" for="sp-tg-username">Bot username <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text">@</span>
              <input type="text" class="form-control" id="sp-tg-username"
                     placeholder="saipa_bot"
                     value="<?= s($cfg_tg_user) ?>">
            </div>
            <div class="form-text">The bot's username without the @ prefix. Shown to students when they link their account.</div>
          </div>
        </div>

        <div id="sp-bot-result" class="mb-3"></div>

        <div class="alert alert-info py-2 px-3" style="font-size:.82rem;">
          <strong>Students link their accounts by:</strong>
          going to <em>My Profile → SAIPA → Link Telegram</em> in Moodle, then
          sending <code>/vincular &lt;code&gt;</code> to the bot in Telegram.
        </div>

      </div><!-- /telegram-section -->

      <!-- WhatsApp note -->
      <div id="sp-whatsapp-note" class="alert alert-secondary py-2 px-3 mb-3" style="font-size:.82rem;display:none">
        <strong>💬 WhatsApp configuration</strong> requires Twilio or Meta Cloud API credentials.
        This cannot be completed in the wizard. After finishing, go to
        <a href="<?= s($settings_url) ?>">Admin Settings → SAIPA → WhatsApp</a> to configure it.
      </div>

      <div class="d-flex justify-content-between mt-4">
        <button class="btn btn-outline-secondary" onclick="spwizGoto(4)">← Back</button>
        <button class="btn btn-primary px-5" onclick="spwizGoto(6)">Next →</button>
      </div>
    </div><!-- /step 5 -->


    <!-- ══════════════════════════════════════════
         STEP 6 — Health check + summary
         ══════════════════════════════════════════ -->
    <div class="spwiz-step" id="spwiz-step-6">

      <h4 class="mb-1">Connection test &amp; configuration summary</h4>
      <p class="text-muted mb-4" style="font-size:.88rem;">Verifying connectivity with the SAIPA Engine…</p>

      <div id="sp-health-result" class="mb-4">
        <div class="d-flex align-items-center gap-2 text-muted py-2">
          <div class="spinner-border spinner-border-sm" role="status"></div>
          <span>Connecting…</span>
        </div>
      </div>

      <!-- Configuration summary (shown after test) -->
      <div id="sp-summary" style="display:none">
        <h6 class="text-muted text-uppercase" style="font-size:.75rem;letter-spacing:.05em;margin-bottom:10px;">Configuration summary</h6>
        <div class="summary-grid mb-4">

          <div class="summary-card">
            <h6>🤖 AI Engine</h6>
            <div class="summary-row">
              <span class="summary-label">Mode</span>
              <span class="summary-value" id="sum-mode">—</span>
            </div>
            <div class="summary-row">
              <span class="summary-label">URL</span>
              <span class="summary-value" id="sum-url" style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;direction:rtl">—</span>
            </div>
            <div class="summary-row">
              <span class="summary-label">Token</span>
              <span class="summary-value" id="sum-token">—</span>
            </div>
          </div>

          <div class="summary-card">
            <h6>📲 Notifications</h6>
            <div class="summary-row">
              <span class="summary-label">Channel</span>
              <span class="summary-value" id="sum-channel">—</span>
            </div>
            <div class="summary-row">
              <span class="summary-label">Telegram bot</span>
              <span class="summary-value" id="sum-tg">—</span>
            </div>
          </div>

          <div class="summary-card">
            <h6>📊 Risk thresholds</h6>
            <div class="summary-row">
              <span class="summary-label">Medium 🟡</span>
              <span class="summary-value">≥ <?= s($cfg_risk_med) ?></span>
            </div>
            <div class="summary-row">
              <span class="summary-label">High 🔴</span>
              <span class="summary-value">≥ <?= s($cfg_risk_high) ?></span>
            </div>
            <div class="summary-row">
              <span class="summary-label">Alert cooldown</span>
              <span class="summary-value"><?= s($cfg_cooldown) ?>h</span>
            </div>
          </div>

          <div class="summary-card">
            <h6>⚙️ Features</h6>
            <div class="summary-row">
              <span class="summary-label">Risk evaluation</span>
              <span class="summary-value"><?= $cfg_risk_on ? '✅ Enabled' : '⬜ Disabled' ?></span>
            </div>
            <div class="summary-row">
              <span class="summary-label">RAG global chat</span>
              <span class="summary-value"><?= $cfg_rag_on ? '✅ Enabled' : '⬜ Disabled' ?></span>
            </div>
          </div>

        </div>
      </div>

      <div class="d-flex justify-content-between mt-2">
        <button class="btn btn-outline-secondary" onclick="spwizGoto(5)">← Back</button>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-secondary" id="sp-retry-btn" style="display:none"
                  onclick="spRunTest()">↻ Retry</button>
          <button class="btn btn-success px-5" id="sp-save-btn" style="display:none"
                  onclick="spSave()">✅ Save &amp; Finish</button>
        </div>
      </div>
    </div><!-- /step 6 -->


    <!-- ══════════════════════════════════════════
         STEP 7 — Done (inline)
         ══════════════════════════════════════════ -->
    <div class="spwiz-step" id="spwiz-step-7">
      <div class="done-card">
        <div class="done-icon">✅</div>
        <h3>Configuration saved!</h3>
        <p>SAIPA is connected and ready. Add the <strong>SAIPA block</strong> to any course
           to activate the chat widget and risk dashboard for that course.</p>
        <div class="d-flex gap-3 justify-content-center flex-wrap">
          <a href="<?= s($settings_url) ?>" class="btn btn-outline-secondary">⚙️ Admin Settings</a>
          <a href="<?= (new moodle_url('/course/index.php'))->out() ?>" class="btn btn-primary btn-lg px-5">
            Go to My Courses →
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
    <input type="hidden" name="risk_threshold_medium" value="<?= s($cfg_risk_med) ?>">
    <input type="hidden" name="risk_threshold_high"   value="<?= s($cfg_risk_high) ?>">
    <input type="hidden" name="alert_cooldown_hours"  value="<?= s($cfg_cooldown) ?>">
    <input type="hidden" name="risk_eval_enabled"     value="<?= s($cfg_risk_on) ?>">
    <input type="hidden" name="rag_global_enabled"    value="<?= s($cfg_rag_on) ?>">
  </form>

<?php endif; ?>
</div><!-- /spwiz -->

<script>
(function () {
    'use strict';

    var currentStep = 1;
    var healthOk    = false;

    var modeLabels = {
        local_ollama: 'Local — Ollama',
        cloud_api:    'Cloud API',
        saipa_cloud:  'SAIPA Cloud',
        custom:       'Custom / Enterprise'
    };
    var channelLabels = {
        none:     'None (Moodle only)',
        telegram: 'Telegram',
        whatsapp: 'WhatsApp',
        both:     'Telegram + WhatsApp'
    };

    // ── Navigation ────────────────────────────────────────────────────────────
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

    function getSelectedMode() {
        var checked = document.querySelector('input[name="engine_mode"]:checked');
        return checked ? checked.value : 'local_ollama';
    }

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

    function getSelectedChannel() {
        var checked = document.querySelector('input[name="messaging_channel"]:checked');
        return checked ? checked.value : 'none';
    }

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
    function spTestBot() {
        var resultEl  = document.getElementById('sp-bot-result');
        var tokenEl   = document.getElementById('sp-tg-token');
        var usernameEl = document.getElementById('sp-tg-username');

        if (!tokenEl || !tokenEl.value.trim()) {
            resultEl.innerHTML = '<div class="alert alert-warning py-2 px-3 mb-0" style="font-size:.82rem;">Please enter a bot token first.</div>';
            return;
        }

        resultEl.innerHTML =
            '<div class="d-flex align-items-center gap-2 text-muted py-1">' +
            '<div class="spinner-border spinner-border-sm" role="status"></div>' +
            '<span>Validating bot token…</span></div>';

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
                    '✅ <strong>Bot verified:</strong> @' + he(data.username) +
                    ' (' + he((data.name || '').trim()) + '). ' +
                    'Username has been filled automatically.</div>';
            } else {
                resultEl.innerHTML =
                    '<div class="alert alert-danger py-2 px-3 mb-0" style="font-size:.82rem;">' +
                    '❌ <strong>Error:</strong> ' + he(data.error || 'Invalid token') + '</div>';
            }
        })
        .catch(function (e) {
            resultEl.innerHTML =
                '<div class="alert alert-danger py-2 px-3 mb-0" style="font-size:.82rem;">' +
                '❌ Network error: ' + he(e.message) + '</div>';
        });
    }
    window.spTestBot = spTestBot;

    // ── Health check ──────────────────────────────────────────────────────────
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
            '<span>Connecting to engine…</span></div>';

        var url   = (document.getElementById('sp-url')   || {}).value || '';
        var token = (document.getElementById('sp-token') || {}).value || '';
        url = url.trim();

        if (!url) {
            renderHealthError('Engine URL is empty. Go back and enter a URL.', url);
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
                    '<div class="health-label"><strong>Engine reachable</strong></div>' +
                    '<div class="health-value">' + he(url) + '</div></div>' +
                    '<div class="health-row">' +
                    '<div class="health-icon">🔢</div>' +
                    '<div class="health-label">Engine version</div>' +
                    '<div class="health-value">' + he(data.version) + '</div></div>' +
                    '<div class="health-row">' +
                    '<div class="health-icon">⏱️</div>' +
                    '<div class="health-label">Uptime</div>' +
                    '<div class="health-value">' + he(data.uptime) + '</div></div>' +
                    '<div class="alert alert-success py-2 px-3 mt-3 mb-0" style="font-size:.85rem;">' +
                    '🎉 <strong>Connection successful!</strong> Review the summary below and click <em>Save &amp; Finish</em>.</div>';

                // Populate summary.
                var usernameEl = document.getElementById('sp-tg-username');
                setText('sum-mode',    modeLabels[getSelectedMode()] || getSelectedMode());
                setText('sum-url',     url);
                setText('sum-token',   token ? '●●●●●●●●' : 'Not configured');
                setText('sum-channel', channelLabels[getSelectedChannel()] || getSelectedChannel());
                setText('sum-tg',      usernameEl && usernameEl.value ? '@' + usernameEl.value : 'Not configured');
                if (summaryEl) summaryEl.style.display = '';
                saveBtn.style.display = '';
            } else {
                renderHealthError(data.error || 'Unknown error', url);
            }
        })
        .catch(function (e) { renderHealthError(e.message || 'Network error', url); });
    }
    window.spRunTest = spRunTest;

    function renderHealthError(msg, url) {
        document.getElementById('sp-health-result').innerHTML =
            '<div class="health-row">' +
            '<div class="health-icon">❌</div>' +
            '<div class="health-label"><strong>Connection failed</strong></div>' +
            '<div class="health-value">' + he(url || '—') + '</div></div>' +
            '<div class="alert alert-danger py-2 px-3 mt-3 mb-0" style="font-size:.84rem;">' +
            '<strong>Error:</strong> ' + he(msg) + '<br><br>' +
            '<strong>Troubleshooting:</strong>' +
            '<ul class="mb-0 mt-1">' +
            '<li>Is saipa-engine running? <code>docker compose ps</code></li>' +
            '<li>Correct URL? Default: <code>http://localhost:8052</code></li>' +
            '<li>Running Moodle in Docker? Use <code>http://host.docker.internal:8052</code></li>' +
            '<li>Token match? Check <code>ENGINE_SECRET</code> in <code>.env</code></li>' +
            '</ul></div>';
        document.getElementById('sp-retry-btn').style.display = '';
    }

    // ── Save ──────────────────────────────────────────────────────────────────
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
    function he(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;')
                        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function setText(id, val) {
        var el = document.getElementById(id);
        if (el) el.textContent = val;
    }
}());
</script>

<?php echo $OUTPUT->footer(); ?>
