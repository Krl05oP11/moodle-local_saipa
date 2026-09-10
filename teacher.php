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
 * SAIPA Teacher Dashboard page.
 *
 * @package    local_saipa
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

// ── First-run wizard redirect ─────────────────────────────────────────────────
if (!get_config('local_saipa', 'setup_complete') && has_capability('moodle/site:config', context_system::instance())) {
    redirect(new moodle_url('/local/saipa/setup.php'));
}

$courseid = required_param('courseid', PARAM_INT);

$course  = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_login($course);
require_capability('local/saipa:view', $context);

$PAGE->set_url('/local/saipa/teacher.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('teacher_dashboard_title', 'local_saipa'));
$PAGE->set_heading(format_string($course->fullname));

$PAGE->navbar->add(
    get_string('teacher_dashboard_title', 'local_saipa'),
    new moodle_url('/local/saipa/teacher.php', ['courseid' => $courseid])
);

echo $OUTPUT->header();

echo $OUTPUT->render_from_template('local_saipa/teacher_dashboard', [
    'courseid'   => $courseid,
    'coursename' => format_string($course->fullname),
    'wwwroot'    => (new moodle_url('/'))->out(false),
]);

echo $OUTPUT->footer();
