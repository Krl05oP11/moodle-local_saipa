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
     * Throws unless $userid is enrolled in the course and visible to the current user.
     *
     * Call it after validate_context() and require_capability() on the same context.
     *
     * @param \context_course $context Course context already validated by the caller.
     * @param int $userid The user the service is about to read or act on.
     * @throws \moodle_exception notenrolled or notsamegroup.
     */
    public static function require_course_user(\context_course $context, int $userid): void {
        if (!is_enrolled($context, $userid)) {
            throw new \moodle_exception('notenrolled', 'local_saipa');
        }
        if (!groups_user_groups_visible(get_course($context->instanceid), $userid)) {
            throw new \moodle_exception('notsamegroup', 'local_saipa');
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
