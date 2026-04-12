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
 * SAIPA help page.
 *
 * @package    local_saipa
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();

$page = optional_param('page', 'advisor', PARAM_ALPHA);

$PAGE->set_url('/local/saipa/help.php', ['page' => $page]);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('help_title', 'local_saipa'));
$PAGE->set_heading(get_string('help_title', 'local_saipa'));
$CFG->docroot = '';   // No recursive docs button on this page.

$PAGE->navbar->add(
    get_string('advisor_title', 'local_saipa'),
    new moodle_url('/local/saipa/advisor.php')
);
$PAGE->navbar->add(get_string('help_title', 'local_saipa'));

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_saipa/help_' . $page, []);
echo $OUTPUT->footer();
