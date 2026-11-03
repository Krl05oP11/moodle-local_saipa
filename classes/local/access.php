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
 * Access checks shared by the web services that act on another user.
 *
 * @package    local_saipa
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_saipa\local;

/**
 * Checks that a user passed to a course-level service belongs to that course.
 *
 * Capability checks alone are not enough: a teacher holds local/saipa:view in
 * their own course, so every service that takes a user id must also check
 * that the user is enrolled there and, in separate-groups courses, that the
 * caller may see that user's groups.
 */
class access {
    /**
     * Throws unless $userid is an active student of the course, visible to the current user.
     *
     * A student, as everywhere in SAIPA, is an active participant who can use the chat
     * (local/saipa:chat) and is not staff (no local/saipa:view): a teacher cannot read a
     * co-teacher's chat or message them through these services.
     * Call it after validate_context() and require_capability() on the same context.
     *
     * @param \context_course $context Course context already validated by the caller.
     * @param int $userid The user the service is about to read or act on.
     * @throws \moodle_exception notenrolled or notsamegroup.
     */
    public static function require_course_user(\context_course $context, int $userid): void {
        $student = is_enrolled($context, $userid, 'local/saipa:chat', true)
            && !has_capability('local/saipa:view', $context, $userid);
        if (!$student) {
            throw new \moodle_exception('notenrolled', 'local_saipa');
        }
        if (!groups_user_groups_visible(get_course($context->instanceid), $userid)) {
            throw new \moodle_exception('notsamegroup', 'local_saipa');
        }
    }

    /**
     * Validates the context of a service that acts on the current user's own SAIPA chat channels.
     *
     * With a course id, the course context is validated and local/saipa:chat is required there,
     * which is how block_saipa calls these services. Without one (course id 0), the user's own
     * context is validated and the user must hold local/saipa:chat in at least one course.
     *
     * @param int $courseid Course the call comes from, or 0.
     * @throws \required_capability_exception If the user cannot use the SAIPA chat.
     */
    public static function require_chat_user(int $courseid): void {
        global $USER;
        if ($courseid > 0) {
            $context = \context_course::instance($courseid);
            \core_external\external_api::validate_context($context);
            require_capability('local/saipa:chat', $context);
            return;
        }
        \core_external\external_api::validate_context(\context_user::instance($USER->id));
        if (!get_user_capability_course('local/saipa:chat', $USER->id, true, '', '', 1)) {
            throw new \required_capability_exception(\context_system::instance(), 'local/saipa:chat', 'nopermissions', '');
        }
    }

    /**
     * Filters a list of user ids down to the ones the current user may see in the course.
     *
     * In courses without separate groups, or when the current user can access
     * all groups, the list comes back unchanged.
     *
     * @param \context_course $context Course context already validated by the caller.
     * @param int[] $userids User ids enrolled in the course.
     * @return int[] The visible subset, keys preserved.
     */
    public static function filter_visible_users(\context_course $context, array $userids): array {
        $course = get_course($context->instanceid);
        $separate = groups_get_course_groupmode($course) == SEPARATEGROUPS;
        if (!$separate || has_capability('moodle/site:accessallgroups', $context)) {
            return $userids;
        }
        return array_filter($userids, fn($uid) => groups_user_groups_visible($course, (int) $uid));
    }
}
