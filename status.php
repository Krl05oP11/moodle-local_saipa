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
 * SAIPA Installation Status Page
 *
 * Shows a comprehensive health dashboard for admins: engine connectivity,
 * configuration, cron, message providers, Ollama and ChromaDB.
 *
 * Access: Site Administration → Plugins → Local plugins → SAIPA → Installation Status
 *
 * @package    local_saipa
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url(new moodle_url('/local/saipa/status.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('SAIPA — Installation Status');
$PAGE->set_heading('SAIPA — Installation Status');
$PAGE->set_pagelayout('admin');

// ── Helper: render a status row ────────────────────────────────────────────

function saipa_status_row(string $label, bool $ok, string $detail = '', bool $warning = false): string {
    if ($ok) {
        $icon  = '<span class="badge badge-success" style="font-size:1rem">&#10003; OK</span>';
    } else if ($warning) {
        $icon  = '<span class="badge badge-warning text-dark" style="font-size:1rem">&#9888; WARNING</span>';
    } else {
        $icon  = '<span class="badge badge-danger" style="font-size:1rem">&#10007; ERROR</span>';
    }
    $detail_html = $detail ? '<br><small class="text-muted">' . htmlspecialchars($detail) . '</small>' : '';
    return '<tr><td class="py-2"><strong>' . htmlspecialchars($label) . '</strong>' . $detail_html . '</td>'
         . '<td class="py-2 text-right">' . $icon . '</td></tr>';
}

function saipa_section_header(string $title): string {
    return '<tr class="table-secondary"><th colspan="2" class="py-2 px-3">' . htmlspecialchars($title) . '</th></tr>';
}

// ── Check 1: Engine URL configured ────────────────────────────────────────

$engine_url   = get_config('local_saipa', 'engine_url');
$engine_token = get_config('local_saipa', 'engine_token');

$url_ok    = !empty($engine_url);
$token_ok  = !empty($engine_token) && strlen($engine_token) >= 16;
$token_msg = '';
if (!empty($engine_token) && strlen($engine_token) < 16) {
    $token_msg = 'Token is too short (< 16 chars) — use a strong random token in production';
}

// ── Check 2: Engine reachable + /health response ──────────────────────────

$engine_reachable = false;
$engine_version   = '';
$engine_detail    = '';

if ($url_ok) {
    $health = local_saipa_engine_request('/health', null, 5);
    if (isset($health['status']) && $health['status'] === 'ok') {
        $engine_reachable = true;
        $engine_version   = $health['version'] ?? '';
        $engine_detail    = 'Version: ' . ($engine_version ?: 'unknown') . ' | URL: ' . $engine_url;
    } else if (isset($health['error'])) {
        $engine_detail = $health['error'];
    } else {
        $engine_detail = 'Unexpected response: ' . json_encode($health);
    }
} else {
    $engine_detail = 'Engine URL is not configured. Go to Site Administration → Plugins → SAIPA → Settings.';
}

// ── Check 3: Ollama reachable ──────────────────────────────────────────────

$ollama_ok     = false;
$ollama_detail = '';

if ($engine_reachable) {
    $ollama_resp = local_saipa_engine_request('/health/ollama', null, 8);
    if (isset($ollama_resp['status'])) {
        $ollama_ok     = ($ollama_resp['status'] === 'ok');
        $ollama_detail = 'Model: ' . ($ollama_resp['model'] ?? 'unknown')
                       . ' | Embed: ' . ($ollama_resp['embed_model'] ?? 'unknown');
        if (!$ollama_ok) {
            $ollama_detail .= ' | Error: ' . ($ollama_resp['detail'] ?? 'unreachable');
        }
    } else {
        $ollama_detail = 'Engine did not return Ollama status. Check engine logs.';
    }
} else {
    $ollama_detail = 'Cannot check — engine is not reachable.';
}

// ── Check 4: ChromaDB writable ─────────────────────────────────────────────

$chroma_ok     = false;
$chroma_detail = '';

if ($engine_reachable) {
    $chroma_resp = local_saipa_engine_request('/health/chroma', null, 8);
    if (isset($chroma_resp['status'])) {
        $chroma_ok     = ($chroma_resp['status'] === 'ok');
        $chroma_detail = 'Collections: ' . ($chroma_resp['collection_count'] ?? '?');
        if (!$chroma_ok) {
            $chroma_detail .= ' | Error: ' . ($chroma_resp['detail'] ?? 'unavailable');
        }
    } else {
        $chroma_detail = 'Engine did not return ChromaDB status. Check engine logs.';
    }
} else {
    $chroma_detail = 'Cannot check — engine is not reachable.';
}

// ── Check 5: Telegram bot status ─────────────────────────────────────────

$channel          = get_config('local_saipa', 'messaging_channel') ?: 'none';
$telegram_enabled = in_array($channel, ['telegram', 'both'], true);
$telegram_ok      = false;
$telegram_detail  = '';
$telegram_warn    = false;

if (!$telegram_enabled) {
    $telegram_ok   = true;   // disabled = not an error
    $telegram_warn = false;
    $telegram_detail = 'Telegram integration is disabled — active channel: ' . htmlspecialchars($channel) . '.';
} else if ($engine_reachable) {
    $tg_resp = local_saipa_engine_request('/telegram/status', null, 8);
    if (isset($tg_resp['status'])) {
        if ($tg_resp['status'] === 'ok') {
            $telegram_ok     = true;
            $bot_name        = $tg_resp['name'] ?? '';
            $bot_username    = $tg_resp['username'] ?? '';
            $telegram_detail = 'Bot connected: ' . $bot_name . ' (@' . $bot_username . ')';
        } else if ($tg_resp['status'] === 'disabled') {
            $telegram_ok     = true;    // token not set in engine .env — warning, not fatal
            $telegram_warn   = true;
            $telegram_detail = 'Telegram enabled in Moodle settings but TELEGRAM_BOT_TOKEN is not set in engine .env';
        } else {
            $telegram_detail = 'Error: ' . ($tg_resp['detail'] ?? json_encode($tg_resp));
        }
    } else {
        $telegram_detail = 'Unexpected response from engine /telegram/status';
    }
} else {
    $telegram_warn   = true;
    $telegram_detail = 'Cannot check — engine is not reachable.';
}

// ── Check 6: Web services enabled ────────────────────────────────────────

$ws_enabled    = !empty($CFG->enablewebservices);
$ws_detail     = $ws_enabled ? 'Web Services are enabled site-wide.' : 'Go to Site Administration → Advanced Features → Enable Web Services.';

// ── Check 7: SAIPA External Service and token ────────────────────────────

global $DB;

$ws_service    = $DB->get_record('external_services', ['shortname' => 'saipa_external_service'], 'id,name,enabled');
$service_ok    = !empty($ws_service) && !empty($ws_service->enabled);
$service_detail = '';

if (!$ws_service) {
    $service_detail = 'Service not found. Run Moodle upgrade: php admin/cli/upgrade.php --non-interactive';
} else if (!$ws_service->enabled) {
    $service_detail = 'Service "SAIPA External Service" exists but is DISABLED.';
} else {
    // Count tokens for this service.
    $token_count = $DB->count_records('external_tokens', ['externalserviceid' => $ws_service->id]);
    $service_detail = 'Service enabled. Tokens issued: ' . $token_count;
    if ($token_count === 0) {
        $service_detail .= ' — Create a web service user and generate a token.';
    }
}

// ── Check 8: Cron task registered and enabled ────────────────────────────

$cron_task   = $DB->get_record(
    'task_scheduled',
    ['classname' => '\local_saipa\task\risk_evaluation'],
    'id,classname,disabled,lastruntime,nextruntime'
);
$cron_ok     = !empty($cron_task) && empty($cron_task->disabled);
$cron_warn   = !empty($cron_task) && !empty($cron_task->disabled);
$cron_detail = '';

if (!$cron_task) {
    $cron_detail = 'Task not found. Run php admin/cli/upgrade.php --non-interactive';
} else if ($cron_task->disabled) {
    $cron_detail = 'Task is DISABLED. Enable it at Site Administration → Server → Scheduled Tasks.';
} else {
    $last = $cron_task->lastruntime ? userdate($cron_task->lastruntime) : 'never';
    $next = $cron_task->nextruntime ? userdate($cron_task->nextruntime) : 'unknown';
    $cron_detail = 'Last run: ' . $last . ' | Next run: ' . $next;
}

// ── Check 9: Message provider registered ─────────────────────────────────

$msg_provider = $DB->get_record(
    'message_providers',
    ['component' => 'local_saipa', 'name' => 'risk_alert'],
    'id,name'
);
$msg_ok     = !empty($msg_provider);
$msg_detail = $msg_ok
    ? 'Provider "risk_alert" registered (id=' . $msg_provider->id . ')'
    : 'Provider not found. Run: php admin/cli/upgrade.php --non-interactive';

// ── Check 10: DB tables present ───────────────────────────────────────────

$required_tables = [
    'saipa_sessions', 'saipa_messages', 'saipa_feedback',
    'saipa_risk_scores', 'saipa_course_index', 'saipa_telegram_links',
];
$missing_tables = [];
foreach ($required_tables as $t) {
    if (!$DB->get_manager()->table_exists($t)) {
        $missing_tables[] = $t;
    }
}
$tables_ok     = empty($missing_tables);
$tables_detail = $tables_ok
    ? 'All ' . count($required_tables) . ' tables present: ' . implode(', ', $required_tables)
    : 'MISSING: ' . implode(', ', $missing_tables) . ' — Run: php admin/cli/upgrade.php --non-interactive';

// ── Check 11: Plugin version ──────────────────────────────────────────────

$pluginmanager  = \core_plugin_manager::instance();
$plugin_info    = $pluginmanager->get_plugin_info('local_saipa');
$plugin_version = $plugin_info ? $plugin_info->versiondb : 'unknown';
$plugin_disk    = $plugin_info ? $plugin_info->versiondisk : 'unknown';
$version_ok     = ((string)$plugin_version === (string)$plugin_disk);
$version_detail = 'Installed: ' . $plugin_version . ' | On disk: ' . $plugin_disk;
if (!$version_ok) {
    $version_detail .= ' — RUN UPGRADE';
}

// ── Render page ───────────────────────────────────────────────────────────

$issues = 0;
$warnings = 0;

$checks = [
    // [label, ok, detail, is_warning]
    ['Engine URL configured', $url_ok, $url_ok ? $engine_url : 'Not configured', false],
    ['Engine API token configured', $token_ok, $token_msg ?: ($token_ok ? 'Token set (' . strlen($engine_token) . ' chars)' : 'Token not set'), !empty($token_msg)],
    ['Engine reachable (/health)', $engine_reachable, $engine_detail, false],
    ['Ollama model available', $ollama_ok, $ollama_detail, !$engine_reachable],
    ['ChromaDB accessible', $chroma_ok, $chroma_detail, !$engine_reachable],
    ['Telegram bot', $telegram_ok, $telegram_detail, $telegram_warn],
];

foreach ($checks as $c) {
    if (!$c[1]) {
        if ($c[3]) {
            $warnings++;
        } else {
            $issues++;
        }
    }
}

$admin_checks = [
    ['Web Services enabled', $ws_enabled, $ws_detail, false],
    ['SAIPA External Service', $service_ok, $service_detail, false],
    ['Risk Evaluation cron task', $cron_ok, $cron_detail, $cron_warn],
    ['Message provider (risk_alert)', $msg_ok, $msg_detail, false],
    ['Database tables', $tables_ok, $tables_detail, false],
    ['Plugin version up-to-date', $version_ok, $version_detail, false],
];

foreach ($admin_checks as $c) {
    if (!$c[1]) {
        if ($c[3]) {
            $warnings++;
        } else {
            $issues++;
        }
    }
}

// Overall status banner
if ($issues === 0 && $warnings === 0) {
    $banner_class = 'alert-success';
    $banner_text  = '&#10003; All checks passed — SAIPA is correctly installed and operational.';
} else if ($issues === 0) {
    $banner_class = 'alert-warning';
    $banner_text  = '&#9888; ' . $warnings . ' warning(s) — SAIPA is functional but review the items below.';
} else {
    $banner_class = 'alert-danger';
    $banner_text  = '&#10007; ' . $issues . ' error(s) found — SAIPA requires attention before it can function correctly.';
}

echo $OUTPUT->header();

?>
<div class="container-fluid py-3" style="max-width:900px">

    <h2>SAIPA — Installation Status</h2>
    <p class="text-muted">Comprehensive health check for all SAIPA components. Refresh this page after making changes.</p>

    <div class="alert <?php echo $banner_class; ?> mb-4" role="alert">
        <?php echo $banner_text; ?>
    </div>

    <table class="table table-bordered table-sm mb-4">
        <thead class="thead-light">
            <tr><th>Component</th><th class="text-right" style="width:140px">Status</th></tr>
        </thead>
        <tbody>
            <?php echo saipa_section_header('saipa-engine Connection'); ?>
            <?php foreach ($checks as $c) : ?>
                <?php echo saipa_status_row($c[0], $c[1], $c[2], $c[3]); ?>
            <?php endforeach; ?>

            <?php echo saipa_section_header('Moodle Configuration'); ?>
            <?php foreach ($admin_checks as $c) : ?>
                <?php echo saipa_status_row($c[0], $c[1], $c[2], $c[3]); ?>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h4 class="mt-4">Quick Actions</h4>
    <div class="list-group mb-4">
        <a href="<?php echo (new moodle_url('/admin/settings.php', ['section' => 'local_saipa']))->out(); ?>"
           class="list-group-item list-group-item-action">
            &#9881; SAIPA Settings (engine URL &amp; token)
        </a>
        <a href="<?php echo (new moodle_url('/admin/tool/task/scheduledtasks.php'))->out(); ?>"
           class="list-group-item list-group-item-action">
            &#128336; Scheduled Tasks (enable/disable cron)
        </a>
        <a href="<?php echo (new moodle_url('/admin/webservice/service.php', ['id' => $ws_service->id ?? 0]))->out(); ?>"
           class="list-group-item list-group-item-action">
            &#128273; Web Services (manage tokens)
        </a>
        <a href="<?php echo (new moodle_url('/message/defaultoutputs.php'))->out(); ?>"
           class="list-group-item list-group-item-action">
            &#128276; Message Notification Settings
        </a>
        <a href="<?php echo (new moodle_url('/local/saipa/health_check.php'))->out(); ?>"
           class="list-group-item list-group-item-action">
            &#128268; Engine Basic Health Check
        </a>
    </div>

    <h4 class="mt-4">Installation Commands Reference</h4>
    <p class="text-muted small">Run these from the Moodle root directory inside the container:</p>
    <pre class="bg-dark text-white p-3 rounded small"
># Apply DB upgrades (run after plugin update)
php admin/cli/upgrade.php --non-interactive

# Purge all caches
php admin/cli/purge_caches.php

# Run cron manually (test risk evaluation task)
php admin/cli/cron.php

# Run only the SAIPA risk evaluation task
php admin/cli/scheduled_task.php --execute='\local_saipa\task\risk_evaluation'</pre>

    <p class="text-muted small mt-3">
        SAIPA v<?php echo htmlspecialchars((string)$plugin_disk); ?> &mdash;
        &copy; 2026 Schaller &amp; Ponce &lt;dev@schaller-ponce.com.ar&gt;
    </p>
</div>
<?php

echo $OUTPUT->footer();
