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
 * SAIPA — Advisor / Admin Dashboard.
 * Institution-wide analytics && controls (5-tab layout).
 *
 * @package    local_saipa
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$syscontext = context_system::instance();

require_login();
require_capability('local/saipa:advisor', $syscontext);

$ismanager = has_capability('local/saipa:manage', $syscontext);

$PAGE->set_url('/local/saipa/advisor.php');
$PAGE->set_context($syscontext);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('advisor_title', 'local_saipa'));
$PAGE->set_heading(get_string('advisor_title', 'local_saipa'));

// Our "? Ayuda" button in the template links to help.php.
// The Moodle "?" button retains its default behaviour (shows both docs links).

$PAGE->navbar->add(
    get_string('advisor_title', 'local_saipa'),
    new moodle_url('/local/saipa/advisor.php')
);

echo $OUTPUT->header();

echo $OUTPUT->render_from_template('local_saipa/advisor_dashboard', [
    'wwwroot'    => (new moodle_url('/'))->out(false),
    'is_manager' => $ismanager,
    'help_url'   => (new moodle_url('/local/saipa/help.php', ['page' => 'advisor']))->out(false),
]);

echo $OUTPUT->footer();
