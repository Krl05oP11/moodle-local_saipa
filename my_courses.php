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
 * SAIPA — My Courses page.
 * Shows a summary table for all courses the user teaches (or all courses for admins).
 * If the user has only 1 course, redirects directly to teacher.php for that course.
 *
 * @package    local_saipa
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$syscontext = context_system::instance();

require_login();

// No system-wide gate: access is checked per-course below (admins vs teachers).
$isadmin   = has_capability('local/saipa:manage', $syscontext);
$coursesqs = [];

if ($isadmin) {
    $coursesqs = $DB->get_fieldset_sql(
        'SELECT DISTINCT courseid FROM {saipa_sessions} ORDER BY courseid'
    );
} else {
    $enrolled = enrol_get_users_courses($USER->id, true, ['id']);
    foreach ($enrolled as $c) {
        $ctx = context_course::instance($c->id);
        if (has_capability('local/saipa:view', $ctx)) {
            $coursesqs[] = $c->id;
        }
    }
}

if (count($coursesqs) === 1) {
    redirect(new moodle_url('/local/saipa/teacher.php', ['courseid' => reset($coursesqs)]));
}

$PAGE->set_url('/local/saipa/my_courses.php');
$PAGE->set_context($syscontext);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('my_courses_title', 'local_saipa'));
$PAGE->set_heading(get_string('my_courses_title', 'local_saipa'));

$PAGE->navbar->add(
    get_string('my_courses_title', 'local_saipa'),
    new moodle_url('/local/saipa/my_courses.php')
);

echo $OUTPUT->header();

echo $OUTPUT->render_from_template('local_saipa/my_courses_dashboard', [
    'wwwroot' => (new moodle_url('/'))->out(false),
]);

echo $OUTPUT->footer();
