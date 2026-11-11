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
$PAGE->set_title(get_string('settings:status_page', 'local_saipa'));
$PAGE->set_heading(get_string('settings:status_page', 'local_saipa'));
$PAGE->set_pagelayout('admin');

// Check 1: Engine URL configured.

$engineurl   = get_config('local_saipa', 'engine_url');
$enginetoken = get_config('local_saipa', 'engine_token');

$urlok    = !empty($engineurl);
$tokenok  = !empty($enginetoken) && strlen($enginetoken) >= 16;
$tokenmsg = '';
if (!empty($enginetoken) && strlen($enginetoken) < 16) {
    $tokenmsg = get_string('status_token_short', 'local_saipa');
}

// Check 2: Engine reachable + /health response.

$enginereachable = false;
$engineversion   = '';
$enginedetail    = '';

if ($urlok) {
    $health = local_saipa_engine_request('/health', null, 5);
    if (isset($health['status']) && $health['status'] === 'ok') {
        $enginereachable = true;
        $engineversion   = $health['version'] ?? '';
        $enginedetail    = get_string('status_engine_detail', 'local_saipa', (object) [
            'version' => $engineversion ?: get_string('status_unknown', 'local_saipa'),
            'url'     => $engineurl,
        ]);
    } else if (isset($health['error'])) {
        $enginedetail = $health['error'];
    } else {
        $enginedetail = get_string('status_unexpected_response', 'local_saipa', json_encode($health));
    }
} else {
    $enginedetail = get_string('status_engine_url_missing', 'local_saipa');
}

// Check 3: Ollama reachable.

$ollamaok     = false;
$ollamadetail = '';

if ($enginereachable) {
    $ollamaresp = local_saipa_engine_request('/health/ollama', null, 8);
    if (isset($ollamaresp['status'])) {
        $ollamaok     = ($ollamaresp['status'] === 'ok');
        $ollamadetail = get_string('status_ollama_detail', 'local_saipa', (object) [
            'model' => $ollamaresp['model'] ?? get_string('status_unknown', 'local_saipa'),
            'embed' => $ollamaresp['embed_model'] ?? get_string('status_unknown', 'local_saipa'),
        ]);
        if (!$ollamaok) {
            $ollamadetail .= ' | ' . get_string(
                'error_detail',
                'local_saipa',
                $ollamaresp['detail'] ?? get_string('status_unreachable', 'local_saipa')
            );
        }
    } else {
        $ollamadetail = get_string('status_ollama_no_status', 'local_saipa');
    }
} else {
    $ollamadetail = get_string('status_cannot_check', 'local_saipa');
}

// Check 4: ChromaDB writable.

$chromaok     = false;
$chromadetail = '';

if ($enginereachable) {
    $chromaresp = local_saipa_engine_request('/health/chroma', null, 8);
    if (isset($chromaresp['status'])) {
        $chromaok     = ($chromaresp['status'] === 'ok');
        $chromadetail = get_string('status_chroma_detail', 'local_saipa', $chromaresp['collection_count'] ?? '?');
        if (!$chromaok) {
            $chromadetail .= ' | ' . get_string(
                'error_detail',
                'local_saipa',
                $chromaresp['detail'] ?? get_string('status_unavailable', 'local_saipa')
            );
        }
    } else {
        $chromadetail = get_string('status_chroma_no_status', 'local_saipa');
    }
} else {
    $chromadetail = get_string('status_cannot_check', 'local_saipa');
}

// Check 5: Telegram bot status.

$channel          = get_config('local_saipa', 'messaging_channel') ?: 'none';
$telegramenabled = in_array($channel, ['telegram', 'both'], true);
$telegramok      = false;
$telegramdetail  = '';
$telegramwarn    = false;

if (!$telegramenabled) {
    $telegramok   = true;   // Disabled = not an error.
    $telegramwarn = false;
    $telegramdetail = get_string('status_telegram_disabled', 'local_saipa', htmlspecialchars($channel));
} else if ($enginereachable) {
    $tgresp = local_saipa_engine_request('/telegram/status', null, 8);
    if (isset($tgresp['status'])) {
        if ($tgresp['status'] === 'ok') {
            $telegramok     = true;
            $botname        = $tgresp['name'] ?? '';
            $botusername    = $tgresp['username'] ?? '';
            $telegramdetail = get_string('status_telegram_connected', 'local_saipa', (object) [
                'name'     => $botname,
                'username' => $botusername,
            ]);
        } else if ($tgresp['status'] === 'disabled') {
            $telegramok     = true;    // Token not set in engine .env — warning, not fatal.
            $telegramwarn   = true;
            $telegramdetail = get_string('status_telegram_no_token', 'local_saipa');
        } else {
            $telegramdetail = get_string('error_detail', 'local_saipa', $tgresp['detail'] ?? json_encode($tgresp));
        }
    } else {
        $telegramdetail = get_string('status_telegram_unexpected', 'local_saipa');
    }
} else {
    $telegramwarn   = true;
    $telegramdetail = get_string('status_cannot_check', 'local_saipa');
}

// Check 6: Web services enabled.

$wsenabled = !empty($CFG->enablewebservices);
$wsdetail  = $wsenabled
    ? get_string('status_ws_enabled', 'local_saipa')
    : get_string('status_ws_disabled', 'local_saipa');

// Check 7: SAIPA External Service and token.

global $DB;

$wsservice    = $DB->get_record('external_services', ['shortname' => 'saipa_service'], 'id,name,enabled');
$serviceok    = !empty($wsservice) && !empty($wsservice->enabled);
$servicedetail = '';

if (!$wsservice) {
    $servicedetail = get_string('status_service_missing', 'local_saipa');
} else if (!$wsservice->enabled) {
    $servicedetail = get_string('status_service_disabled', 'local_saipa');
} else {
    // Count tokens for this service.
    $tokencount = $DB->count_records('external_tokens', ['externalserviceid' => $wsservice->id]);
    $servicedetail = get_string('status_service_tokens', 'local_saipa', $tokencount);
    if ($tokencount === 0) {
        $servicedetail .= ' — ' . get_string('status_service_no_tokens', 'local_saipa');
    }
}

// Check 8: Cron task registered and enabled.

$crontask   = $DB->get_record(
    'task_scheduled',
    ['classname' => '\local_saipa\task\risk_evaluation'],
    'id,classname,disabled,lastruntime,nextruntime'
);
$cronok     = !empty($crontask) && empty($crontask->disabled);
$cronwarn   = !empty($crontask) && !empty($crontask->disabled);
$crondetail = '';

if (!$crontask) {
    $crondetail = get_string('status_cron_missing', 'local_saipa');
} else if ($crontask->disabled) {
    $crondetail = get_string('status_cron_disabled', 'local_saipa');
} else {
    $last = $crontask->lastruntime ? userdate($crontask->lastruntime) : get_string('status_never', 'local_saipa');
    $next = $crontask->nextruntime ? userdate($crontask->nextruntime) : get_string('status_unknown', 'local_saipa');
    $crondetail = get_string('status_cron_detail', 'local_saipa', (object) ['last' => $last, 'next' => $next]);
}

// Check 9: Message provider registered.

$msgprovider = $DB->get_record(
    'message_providers',
    ['component' => 'local_saipa', 'name' => 'risk_alert'],
    'id,name'
);
$msgok     = !empty($msgprovider);
$msgdetail = $msgok
    ? get_string('status_msgprovider_ok', 'local_saipa', $msgprovider->id)
    : get_string('status_msgprovider_missing', 'local_saipa');

// Check 10: DB tables present.

$requiredtables = [
    'local_saipa_sessions', 'local_saipa_messages', 'local_saipa_feedback',
    'local_saipa_risk_scores', 'local_saipa_course_index', 'local_saipa_telegram_links',
];
$missingtables = [];
foreach ($requiredtables as $t) {
    if (!$DB->get_manager()->table_exists($t)) {
        $missingtables[] = $t;
    }
}
$tablesok     = empty($missingtables);
$tablesdetail = $tablesok
    ? get_string('status_tables_ok', 'local_saipa', (object) [
        'count'  => count($requiredtables),
        'tables' => implode(', ', $requiredtables),
    ])
    : get_string('status_tables_missing', 'local_saipa', implode(', ', $missingtables));

// Check 11: Plugin version.

$pluginmanager  = \core_plugin_manager::instance();
$plugininfo    = $pluginmanager->get_plugin_info('local_saipa');
$pluginversion = $plugininfo ? $plugininfo->versiondb : get_string('status_unknown', 'local_saipa');
$plugindisk    = $plugininfo ? $plugininfo->versiondisk : get_string('status_unknown', 'local_saipa');
$versionok     = ((string)$pluginversion === (string)$plugindisk);
$versiondetail = get_string('status_version_detail', 'local_saipa', (object) ['db' => $pluginversion, 'disk' => $plugindisk]);
if (!$versionok) {
    $versiondetail .= ' — ' . get_string('status_version_run_upgrade', 'local_saipa');
}

// Render page.

$issues = 0;
$warnings = 0;

$checks = [
    // Format: [label, ok, detail, is_warning].
    [
        get_string('status_check_url', 'local_saipa'),
        $urlok,
        $urlok ? $engineurl : get_string('status_not_configured', 'local_saipa'),
        false,
    ],
    [
        get_string('status_check_token', 'local_saipa'),
        $tokenok,
        $tokenmsg ?: ($tokenok
            ? get_string('status_token_set', 'local_saipa', strlen($enginetoken))
            : get_string('status_token_not_set', 'local_saipa')),
        !empty($tokenmsg),
    ],
    [get_string('status_check_engine', 'local_saipa'), $enginereachable, $enginedetail, false],
    [get_string('status_check_ollama', 'local_saipa'), $ollamaok, $ollamadetail, !$enginereachable],
    [get_string('status_check_chroma', 'local_saipa'), $chromaok, $chromadetail, !$enginereachable],
    [get_string('status_check_telegram', 'local_saipa'), $telegramok, $telegramdetail, $telegramwarn],
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
    [get_string('status_check_ws', 'local_saipa'), $wsenabled, $wsdetail, false],
    [get_string('status_check_service', 'local_saipa'), $serviceok, $servicedetail, false],
    [get_string('status_check_cron', 'local_saipa'), $cronok, $crondetail, $cronwarn],
    [get_string('status_check_msgprovider', 'local_saipa'), $msgok, $msgdetail, false],
    [get_string('status_check_tables', 'local_saipa'), $tablesok, $tablesdetail, false],
    [get_string('status_check_version', 'local_saipa'), $versionok, $versiondetail, false],
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

// The page is a Mustache template (templates/status_page.mustache); values are escaped there.
$rows = function (array $list): array {
    return array_map(fn($c) => [
        'label'   => $c[0],
        'detail'  => $c[2],
        'ok'      => (bool) $c[1],
        'warning' => !$c[1] && $c[3],
        'error'   => !$c[1] && !$c[3],
    ], $list);
};

$templatecontext = [
    'bannerok'       => $issues === 0 && $warnings === 0,
    'bannerwarnings' => $issues === 0 && $warnings > 0,
    'bannererrors'   => $issues > 0,
    'warnings'       => $warnings,
    'issues'         => $issues,
    'sections'       => [
        ['title' => get_string('status_section_engine', 'local_saipa'), 'rows' => $rows($checks)],
        ['title' => get_string('status_section_moodle', 'local_saipa'), 'rows' => $rows($adminchecks)],
    ],
    'settingsurl'    => (new moodle_url('/admin/settings.php', ['section' => 'local_saipa']))->out(false),
    'tasksurl'       => (new moodle_url('/admin/tool/task/scheduledtasks.php'))->out(false),
    'wsurl'          => (new moodle_url('/admin/webservice/service.php', ['id' => $wsservice->id ?? 0]))->out(false),
    'messagesurl'    => (new moodle_url('/message/defaultoutputs.php'))->out(false),
    'clireference'   => implode("\n", [
        '# Apply DB upgrades (run after plugin update)',
        'php admin/cli/upgrade.php --non-interactive',
        '',
        '# Purge all caches',
        'php admin/cli/purge_caches.php',
        '',
        '# Run cron manually (test risk evaluation task)',
        'php admin/cli/cron.php',
        '',
        '# Run only the SAIPA risk evaluation task',
        "php admin/cli/scheduled_task.php --execute='\\local_saipa\\task\\risk_evaluation'",
    ]),
    'version'        => (string) $plugindisk,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_saipa/status_page', $templatecontext);
echo $OUTPUT->footer();
