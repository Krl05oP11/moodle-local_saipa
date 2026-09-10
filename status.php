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
 * SAIPA Installation Status Page
 *
 * Shows a comprehensive health dashboard for admins: engine connectivity,
 * configuration, cron, message providers, Ollama && ChromaDB.
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

/**
 * Saipa status row.
 */
function saipa_status_row(string $label, bool $ok, string $detail = '', bool $warning = false): string {
    if ($ok) {
        $icon  = '<span class="badge badge-success" style="font-size:1rem">&#10003; OK</span>';
    } else if ($warning) {
        $icon  = '<span class="badge badge-warning text-dark" style="font-size:1rem">&#9888; WARNING</span>';
    } else {
        $icon  = '<span class="badge badge-danger" style="font-size:1rem">&#10007; ERROR</span>';
    }
    $detailhtml = $detail ? '<br><small class="text-muted">' . htmlspecialchars($detail) . '</small>' : '';
    return '<tr><td class="py-2"><strong>' . htmlspecialchars($label) . '</strong>' . $detailhtml . '</td>'
         . '<td class="py-2 text-right">' . $icon . '</td></tr>';
}

/**
 * Saipa section header.
 */
function saipa_section_header(string $title): string {
    return '<tr class="table-secondary"><th colspan="2" class="py-2 px-3">' . htmlspecialchars($title) . '</th></tr>';
}

// ── Check 1: Engine URL configured ────────────────────────────────────────

$engineurl   = get_config('local_saipa', 'engine_url');
$enginetoken = get_config('local_saipa', 'engine_token');

$urlok    = !empty($engineurl);
$tokenok  = !empty($enginetoken) && strlen($enginetoken) >= 16;
$tokenmsg = '';
if (!empty($enginetoken) && strlen($enginetoken) < 16) {
    $tokenmsg = 'Token is too short (< 16 chars) — use a strong random token in production';
}

// ── Check 2: Engine reachable + /health response ──────────────────────────

$enginereachable = false;
$engineversion   = '';
$enginedetail    = '';

if ($urlok) {
    $health = local_saipa_engine_request('/health', null, 5);
    if (isset($health['status']) && $health['status'] === 'ok') {
        $enginereachable = true;
        $engineversion   = $health['version'] ?? '';
        $enginedetail    = 'Version: ' . ($engineversion ?: 'unknown') . ' | URL: ' . $engineurl;
    } else if (isset($health['error'])) {
        $enginedetail = $health['error'];
    } else {
        $enginedetail = 'Unexpected response: ' . json_encode($health);
    }
} else {
    $enginedetail = 'Engine URL is not configured. Go to Site Administration → Plugins → SAIPA → Settings.';
}

// ── Check 3: Ollama reachable ──────────────────────────────────────────────

$ollamaok     = false;
$ollamadetail = '';

if ($enginereachable) {
    $ollamaresp = local_saipa_engine_request('/health/ollama', null, 8);
    if (isset($ollamaresp['status'])) {
        $ollamaok     = ($ollamaresp['status'] === 'ok');
        $ollamadetail = 'Model: ' . ($ollamaresp['model'] ?? 'unknown')
                       . ' | Embed: ' . ($ollamaresp['embed_model'] ?? 'unknown');
        if (!$ollamaok) {
            $ollamadetail .= ' | Error: ' . ($ollamaresp['detail'] ?? 'unreachable');
        }
    } else {
        $ollamadetail = 'Engine did not return Ollama status. Check engine logs.';
    }
} else {
    $ollamadetail = 'Cannot check — engine is not reachable.';
}

// ── Check 4: ChromaDB writable ─────────────────────────────────────────────

$chromaok     = false;
$chromadetail = '';

if ($enginereachable) {
    $chromaresp = local_saipa_engine_request('/health/chroma', null, 8);
    if (isset($chromaresp['status'])) {
        $chromaok     = ($chromaresp['status'] === 'ok');
        $chromadetail = 'Collections: ' . ($chromaresp['collection_count'] ?? '?');
        if (!$chromaok) {
            $chromadetail .= ' | Error: ' . ($chromaresp['detail'] ?? 'unavailable');
        }
    } else {
        $chromadetail = 'Engine did not return ChromaDB status. Check engine logs.';
    }
} else {
    $chromadetail = 'Cannot check — engine is not reachable.';
}

// ── Check 5: Telegram bot status ─────────────────────────────────────────

$channel          = get_config('local_saipa', 'messaging_channel') ?: 'none';
$telegramenabled = in_array($channel, ['telegram', 'both'], true);
$telegramok      = false;
$telegramdetail  = '';
$telegramwarn    = false;

if (!$telegramenabled) {
    $telegramok   = true;   // disabled = not an error
    $telegramwarn = false;
    $telegramdetail = 'Telegram integration is disabled — active channel: ' . htmlspecialchars($channel) . '.';
} else if ($enginereachable) {
    $tgresp = local_saipa_engine_request('/telegram/status', null, 8);
    if (isset($tgresp['status'])) {
        if ($tgresp['status'] === 'ok') {
            $telegramok     = true;
            $botname        = $tgresp['name'] ?? '';
            $botusername    = $tgresp['username'] ?? '';
            $telegramdetail = 'Bot connected: ' . $botname . ' (@' . $botusername . ')';
        } else if ($tgresp['status'] === 'disabled') {
            $telegramok     = true;    // token not set in engine .env — warning, not fatal
            $telegramwarn   = true;
            $telegramdetail = 'Telegram enabled in Moodle settings but TELEGRAM_BOT_TOKEN is not set in engine .env';
        } else {
            $telegramdetail = 'Error: ' . ($tgresp['detail'] ?? json_encode($tgresp));
        }
    } else {
        $telegramdetail = 'Unexpected response from engine /telegram/status';
    }
} else {
    $telegramwarn   = true;
    $telegramdetail = 'Cannot check — engine is not reachable.';
}

// ── Check 6: Web services enabled ────────────────────────────────────────

$wsenabled = !empty($CFG->enablewebservices);
$wsdetail  = $wsenabled
    ? 'Web Services are enabled site-wide.'
    : 'Go to Site Administration → Advanced Features → Enable Web Services.';

// ── Check 7: SAIPA External Service && token ────────────────────────────

global $DB;

$wsservice    = $DB->get_record('external_services', ['shortname' => 'saipa_service'], 'id,name,enabled');
$serviceok    = !empty($wsservice) && !empty($wsservice->enabled);
$servicedetail = '';

if (!$wsservice) {
    $servicedetail = 'Service not found. Run Moodle upgrade: php admin/cli/upgrade.php --non-interactive';
} else if (!$wsservice->enabled) {
    $servicedetail = 'Service "SAIPA External Service" exists but is DISABLED.';
} else {
    // Count tokens for this service.
    $tokencount = $DB->count_records('external_tokens', ['externalserviceid' => $wsservice->id]);
    $servicedetail = 'Service enabled. Tokens issued: ' . $tokencount;
    if ($tokencount === 0) {
        $servicedetail .= ' — Create a web service user && generate a token.';
    }
}

// ── Check 8: Cron task registered && enabled ────────────────────────────

$crontask   = $DB->get_record(
    'task_scheduled',
    ['classname' => '\local_saipa\task\risk_evaluation'],
    'id,classname,disabled,lastruntime,nextruntime'
);
$cronok     = !empty($crontask) && empty($crontask->disabled);
$cronwarn   = !empty($crontask) && !empty($crontask->disabled);
$crondetail = '';

if (!$crontask) {
    $crondetail = 'Task not found. Run php admin/cli/upgrade.php --non-interactive';
} else if ($crontask->disabled) {
    $crondetail = 'Task is DISABLED. Enable it at Site Administration → Server → Scheduled Tasks.';
} else {
    $last = $crontask->lastruntime ? userdate($crontask->lastruntime) : 'never';
    $next = $crontask->nextruntime ? userdate($crontask->nextruntime) : 'unknown';
    $crondetail = 'Last run: ' . $last . ' | Next run: ' . $next;
}

// ── Check 9: Message provider registered ─────────────────────────────────

$msgprovider = $DB->get_record(
    'message_providers',
    ['component' => 'local_saipa', 'name' => 'risk_alert'],
    'id,name'
);
$msgok     = !empty($msgprovider);
$msgdetail = $msgok
    ? 'Provider "risk_alert" registered (id=' . $msgprovider->id . ')'
    : 'Provider not found. Run: php admin/cli/upgrade.php --non-interactive';

// ── Check 10: DB tables present ───────────────────────────────────────────

$requiredtables = [
    'saipa_sessions', 'saipa_messages', 'saipa_feedback',
    'saipa_risk_scores', 'saipa_course_index', 'saipa_telegram_links',
];
$missingtables = [];
foreach ($requiredtables as $t) {
    if (!$DB->get_manager()->table_exists($t)) {
        $missingtables[] = $t;
    }
}
$tablesok     = empty($missingtables);
$tablesdetail = $tablesok
    ? 'All ' . count($requiredtables) . ' tables present: ' . implode(', ', $requiredtables)
    : 'MISSING: ' . implode(', ', $missingtables) . ' — Run: php admin/cli/upgrade.php --non-interactive';

// ── Check 11: Plugin version ──────────────────────────────────────────────

$pluginmanager  = \core_plugin_manager::instance();
$plugininfo    = $pluginmanager->get_plugin_info('local_saipa');
$pluginversion = $plugininfo ? $plugininfo->versiondb : 'unknown';
$plugindisk    = $plugininfo ? $plugininfo->versiondisk : 'unknown';
$versionok     = ((string)$pluginversion === (string)$plugindisk);
$versiondetail = 'Installed: ' . $pluginversion . ' | On disk: ' . $plugindisk;
if (!$versionok) {
    $versiondetail .= ' — RUN UPGRADE';
}

// ── Render page ───────────────────────────────────────────────────────────

$issues = 0;
$warnings = 0;

$checks = [
    // [label, ok, detail, is_warning]
    ['Engine URL configured', $urlok, $urlok ? $engineurl : 'Not configured', false],
    [
        'Engine API token configured',
        $tokenok,
        $tokenmsg ?: ($tokenok ? 'Token set (' . strlen($enginetoken) . ' chars)' : 'Token not set'),
        !empty($tokenmsg),
    ],
    ['Engine reachable (/health)', $enginereachable, $enginedetail, false],
    ['Ollama model available', $ollamaok, $ollamadetail, !$enginereachable],
    ['ChromaDB accessible', $chromaok, $chromadetail, !$enginereachable],
    ['Telegram bot', $telegramok, $telegramdetail, $telegramwarn],
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

$adminchecks = [
    ['Web Services enabled', $wsenabled, $wsdetail, false],
    ['SAIPA External Service', $serviceok, $servicedetail, false],
    ['Risk Evaluation cron task', $cronok, $crondetail, $cronwarn],
    ['Message provider (risk_alert)', $msgok, $msgdetail, false],
    ['Database tables', $tablesok, $tablesdetail, false],
    ['Plugin version up-to-date', $versionok, $versiondetail, false],
];

foreach ($adminchecks as $c) {
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
    $bannerclass = 'alert-success';
    $bannertext  = '&#10003; All checks passed — SAIPA is correctly installed && operational.';
} else if ($issues === 0) {
    $bannerclass = 'alert-warning';
    $bannertext  = '&#9888; ' . $warnings . ' warning(s) — SAIPA is functional but review the items below.';
} else {
    $bannerclass = 'alert-danger';
    $bannertext  = '&#10007; ' . $issues . ' error(s) found — SAIPA requires attention before it can function correctly.';
}

echo $OUTPUT->header();

// Build the status-table body in PHP so the markup below is a single string.
// Inline PHP islands in HTML also make the file-docblock sniff misfire.
$tablerows = saipa_section_header('saipa-engine Connection');
foreach ($checks as $c) {
    $tablerows .= saipa_status_row($c[0], $c[1], $c[2], $c[3]);
}
$tablerows .= saipa_section_header('Moodle Configuration');
foreach ($adminchecks as $c) {
    $tablerows .= saipa_status_row($c[0], $c[1], $c[2], $c[3]);
}

$settingsurl = (new moodle_url('/admin/settings.php', ['section' => 'local_saipa']))->out();
$tasksurl    = (new moodle_url('/admin/tool/task/scheduledtasks.php'))->out();
$wsurl       = (new moodle_url('/admin/webservice/service.php', ['id' => $wsservice->id ?? 0]))->out();
$messagesurl = (new moodle_url('/message/defaultoutputs.php'))->out();
$healthurl   = (new moodle_url('/local/saipa/health_check.php'))->out();

$clireference = <<<'CLI'
# Apply DB upgrades (run after plugin update)
php admin/cli/upgrade.php --non-interactive

# Purge all caches
php admin/cli/purge_caches.php

# Run cron manually (test risk evaluation task)
php admin/cli/cron.php

# Run only the SAIPA risk evaluation task
php admin/cli/scheduled_task.php --execute='\local_saipa\task\risk_evaluation'
CLI;

$pluginversiondisplay = htmlspecialchars((string) $plugindisk);

echo <<<HTML
<div class="container-fluid py-3" style="max-width:900px">

    <h2>SAIPA — Installation Status</h2>
    <p class="text-muted">Comprehensive health check for all SAIPA components. Refresh after making changes.</p>

    <div class="alert {$bannerclass} mb-4" role="alert">
        {$bannertext}
    </div>

    <table class="table table-bordered table-sm mb-4">
        <thead class="thead-light">
            <tr><th>Component</th><th class="text-right" style="width:140px">Status</th></tr>
        </thead>
        <tbody>
            {$tablerows}
        </tbody>
    </table>

    <h4 class="mt-4">Quick Actions</h4>
    <div class="list-group mb-4">
        <a href="{$settingsurl}" class="list-group-item list-group-item-action">
            &#9881; SAIPA Settings (engine URL &amp; token)
        </a>
        <a href="{$tasksurl}" class="list-group-item list-group-item-action">
            &#128336; Scheduled Tasks (enable/disable cron)
        </a>
        <a href="{$wsurl}" class="list-group-item list-group-item-action">
            &#128273; Web Services (manage tokens)
        </a>
        <a href="{$messagesurl}" class="list-group-item list-group-item-action">
            &#128276; Message Notification Settings
        </a>
        <a href="{$healthurl}" class="list-group-item list-group-item-action">
            &#128268; Engine Basic Health Check
        </a>
    </div>

    <h4 class="mt-4">Installation Commands Reference</h4>
    <p class="text-muted small">Run these from the Moodle root directory inside the container:</p>
    <pre class="bg-dark text-white p-3 rounded small">{$clireference}</pre>

    <p class="text-muted small mt-3">
        SAIPA v{$pluginversiondisplay} &mdash;
        &copy; 2026 Schaller &amp; Ponce &lt;dev@schaller-ponce.com.ar&gt;
    </p>
</div>
HTML;

echo $OUTPUT->footer();
