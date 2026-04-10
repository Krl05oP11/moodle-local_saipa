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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Privacy API provider for local_saipa.
 * Required for any plugin that stores personal data (GDPR compliance).
 *
 * @package    local_saipa
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_saipa\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider covering all 7 personal-data tables in local_saipa.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Return the metadata collection describing all personal data stored.
     *
     * @param collection $collection The metadata collection to populate.
     * @return collection The populated metadata collection.
     */
    public static function get_metadata(collection $collection): collection {

        $collection->add_database_table('saipa_sessions', [
            'userid'       => 'privacy:metadata:saipa_sessions:userid',
            'courseid'     => 'privacy:metadata:saipa_sessions:courseid',
            'timecreated'  => 'privacy:metadata:saipa_sessions:timecreated',
        ], 'privacy:metadata:saipa_sessions');

        $collection->add_database_table('saipa_messages', [
            'content'      => 'privacy:metadata:saipa_messages:content',
            'role'         => 'privacy:metadata:saipa_messages:role',
            'timecreated'  => 'privacy:metadata:saipa_messages:timecreated',
        ], 'privacy:metadata:saipa_messages');

        $collection->add_database_table('saipa_risk_scores', [
            'userid'       => 'privacy:metadata:saipa_risk_scores:userid',
            'score'        => 'privacy:metadata:saipa_risk_scores:score',
            'risk_level'   => 'privacy:metadata:saipa_risk_scores:risk_level',
            'factors'      => 'privacy:metadata:saipa_risk_scores:factors',
            'timecomputed' => 'privacy:metadata:saipa_risk_scores:timecomputed',
        ], 'privacy:metadata:saipa_risk_scores');

        $collection->add_database_table('saipa_phone_verify', [
            'userid'       => 'privacy:metadata:saipa_phone_verify:userid',
            'phone'        => 'privacy:metadata:saipa_phone_verify:phone',
            'verified'     => 'privacy:metadata:saipa_phone_verify:verified',
        ], 'privacy:metadata:saipa_phone_verify');

        $collection->add_database_table('saipa_notifications', [
            'userid'       => 'privacy:metadata:saipa_notifications:userid',
            'template'     => 'privacy:metadata:saipa_notifications:template',
            'payload'      => 'privacy:metadata:saipa_notifications:payload',
            'status'       => 'privacy:metadata:saipa_notifications:status',
            'timesent'     => 'privacy:metadata:saipa_notifications:timesent',
        ], 'privacy:metadata:saipa_notifications');

        $collection->add_database_table('saipa_feedback', [
            'userid'       => 'privacy:metadata:saipa_feedback:userid',
            'rating'       => 'privacy:metadata:saipa_feedback:rating',
            'comment'      => 'privacy:metadata:saipa_feedback:comment',
            'timecreated'  => 'privacy:metadata:saipa_feedback:timecreated',
        ], 'privacy:metadata:saipa_feedback');

        $collection->add_database_table('saipa_telegram_links', [
            'userid'             => 'privacy:metadata:saipa_telegram_links:userid',
            'telegram_id'        => 'privacy:metadata:saipa_telegram_links:telegram_id',
            'telegram_username'  => 'privacy:metadata:saipa_telegram_links:telegram_username',
            'confirmed'          => 'privacy:metadata:saipa_telegram_links:confirmed',
        ], 'privacy:metadata:saipa_telegram_links');

        $collection->add_external_location_link('saipa_engine', [
            'userid'  => 'privacy:metadata:saipa_engine:userid',
            'message' => 'privacy:metadata:saipa_engine:message',
        ], 'privacy:metadata:saipa_engine');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user ID.
     * @return contextlist The list of contexts containing user data.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        // Course contexts from sessions.
        $sql = 'SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course} c ON c.id = ctx.instanceid AND ctx.contextlevel = :ctxlevel
                  JOIN {saipa_sessions} s ON s.courseid = c.id
                 WHERE s.userid = :userid';
        $contextlist->add_from_sql($sql, ['ctxlevel' => CONTEXT_COURSE, 'userid' => $userid]);

        // System context for phone_verify, notifications, telegram_links.
        $contextlist->add_system_context();

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist for the context.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if ($context->contextlevel == CONTEXT_COURSE) {
            $userlist->add_from_sql(
                'userid',
                'SELECT userid FROM {saipa_sessions} WHERE courseid = :courseid',
                ['courseid' => $context->instanceid]
            );
            $userlist->add_from_sql(
                'userid',
                'SELECT userid FROM {saipa_risk_scores} WHERE courseid = :courseid',
                ['courseid' => $context->instanceid]
            );
        }

        if ($context->contextlevel == CONTEXT_SYSTEM) {
            $userlist->add_from_sql('userid', 'SELECT userid FROM {saipa_phone_verify}', []);
            $userlist->add_from_sql('userid', 'SELECT userid FROM {saipa_notifications}', []);
            $userlist->add_from_sql('userid', 'SELECT userid FROM {saipa_telegram_links}', []);
        }
    }

    /**
     * Export all user data for the specified approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts for export.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_COURSE) {
                self::export_course_data($context, $userid);
            } else if ($context->contextlevel == CONTEXT_SYSTEM) {
                self::export_system_data($context, $userid);
            }
        }
    }

    /**
     * Export course-scoped data: sessions, messages, risk scores, feedback.
     *
     * @param \context $context The course context.
     * @param int $userid The user ID.
     */
    private static function export_course_data(\context $context, int $userid): void {
        global $DB;
        $subcontext = [get_string('pluginname', 'local_saipa')];

        $sessions = $DB->get_records('saipa_sessions', ['userid' => $userid, 'courseid' => $context->instanceid]);
        foreach ($sessions as $session) {
            $messages = $DB->get_records('saipa_messages', ['sessionid' => $session->id], 'timecreated ASC');
            writer::with_context($context)->export_data(
                array_merge($subcontext, ['session_' . $session->id]),
                (object) [
                    'timecreated' => transform::datetime($session->timecreated),
                    'messages'    => array_map(function ($m) {
                        return (object) [
                            'role'        => $m->role,
                            'content'     => $m->content,
                            'timecreated' => transform::datetime($m->timecreated),
                        ];
                    }, array_values($messages)),
                ]
            );
        }

        $scores = $DB->get_records('saipa_risk_scores', ['userid' => $userid, 'courseid' => $context->instanceid]);
        if ($scores) {
            writer::with_context($context)->export_data(
                array_merge($subcontext, ['risk_scores']),
                (object) ['scores' => array_map(function ($s) {
                    return (object) [
                        'score'        => $s->score,
                        'risk_level'   => $s->risk_level,
                        'factors'      => $s->factors,
                        'timecomputed' => transform::datetime($s->timecomputed),
                    ];
                }, array_values($scores))]
            );
        }

        $feedback = $DB->get_records_sql(
            'SELECT f.* FROM {saipa_feedback} f
               JOIN {saipa_messages} m ON m.id = f.messageid
               JOIN {saipa_sessions} s ON s.id = m.sessionid
              WHERE f.userid = :userid AND s.courseid = :courseid',
            ['userid' => $userid, 'courseid' => $context->instanceid]
        );
        if ($feedback) {
            writer::with_context($context)->export_data(
                array_merge($subcontext, ['feedback']),
                (object) ['feedback' => array_map(function ($f) {
                    return (object) [
                        'rating'      => $f->rating,
                        'comment'     => $f->comment,
                        'timecreated' => transform::datetime($f->timecreated),
                    ];
                }, array_values($feedback))]
            );
        }
    }

    /**
     * Export system-scoped data: phone verification, notifications, telegram links.
     *
     * @param \context $context The system context.
     * @param int $userid The user ID.
     */
    private static function export_system_data(\context $context, int $userid): void {
        global $DB;
        $subcontext = [get_string('pluginname', 'local_saipa')];

        if ($row = $DB->get_record('saipa_phone_verify', ['userid' => $userid])) {
            writer::with_context($context)->export_data(
                array_merge($subcontext, ['phone_verify']),
                (object) [
                    'phone'       => $row->phone,
                    'verified'    => transform::yesno($row->verified),
                    'timecreated' => transform::datetime($row->timecreated),
                ]
            );
        }

        $notifications = $DB->get_records('saipa_notifications', ['userid' => $userid], 'timesent ASC');
        if ($notifications) {
            writer::with_context($context)->export_data(
                array_merge($subcontext, ['notifications']),
                (object) ['notifications' => array_map(function ($n) {
                    return (object) [
                        'template' => $n->template,
                        'status'   => $n->status,
                        'timesent' => transform::datetime($n->timesent),
                    ];
                }, array_values($notifications))]
            );
        }

        if ($row = $DB->get_record('saipa_telegram_links', ['userid' => $userid])) {
            writer::with_context($context)->export_data(
                array_merge($subcontext, ['telegram_link']),
                (object) [
                    'telegram_username' => $row->telegram_username,
                    'confirmed'         => transform::yesno($row->confirmed),
                    'timecreated'       => transform::datetime($row->timecreated),
                ]
            );
        }
    }

    // -------------------------------------------------------------------------
    // Deletion
    // -------------------------------------------------------------------------

    /**
     * Delete all personal data for all users in the specified context.
     *
     * @param \context $context The context to delete data in.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context->contextlevel == CONTEXT_COURSE) {
            $sessions = $DB->get_records('saipa_sessions', ['courseid' => $context->instanceid]);
            foreach ($sessions as $session) {
                $messages = $DB->get_records('saipa_messages', ['sessionid' => $session->id]);
                foreach ($messages as $message) {
                    $DB->delete_records('saipa_feedback', ['messageid' => $message->id]);
                }
                $DB->delete_records('saipa_messages', ['sessionid' => $session->id]);
            }
            $DB->delete_records('saipa_sessions', ['courseid' => $context->instanceid]);
            $DB->delete_records('saipa_risk_scores', ['courseid' => $context->instanceid]);
        }
    }

    /**
     * Delete all data for the specified user in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_COURSE) {
                $sessions = $DB->get_records('saipa_sessions', ['userid' => $userid, 'courseid' => $context->instanceid]);
                foreach ($sessions as $session) {
                    $messages = $DB->get_records('saipa_messages', ['sessionid' => $session->id]);
                    foreach ($messages as $message) {
                        $DB->delete_records('saipa_feedback', ['messageid' => $message->id]);
                    }
                    $DB->delete_records('saipa_messages', ['sessionid' => $session->id]);
                }
                $DB->delete_records('saipa_sessions', ['userid' => $userid, 'courseid' => $context->instanceid]);
                $DB->delete_records('saipa_risk_scores', ['userid' => $userid, 'courseid' => $context->instanceid]);
            } else if ($context->contextlevel == CONTEXT_SYSTEM) {
                $DB->delete_records('saipa_phone_verify', ['userid' => $userid]);
                $DB->delete_records('saipa_notifications', ['userid' => $userid]);
                $DB->delete_records('saipa_telegram_links', ['userid' => $userid]);
            }
        }
    }

    /**
     * Delete multiple users' data within a single context.
     *
     * @param approved_userlist $userlist The approved users and context.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        if ($context->contextlevel == CONTEXT_COURSE) {
            $sessions = $DB->get_records_select(
                'saipa_sessions',
                "userid $insql AND courseid = :courseid",
                $inparams + ['courseid' => $context->instanceid]
            );
            foreach ($sessions as $session) {
                $messages = $DB->get_records('saipa_messages', ['sessionid' => $session->id]);
                foreach ($messages as $message) {
                    $DB->delete_records('saipa_feedback', ['messageid' => $message->id]);
                }
                $DB->delete_records('saipa_messages', ['sessionid' => $session->id]);
            }
            $DB->delete_records_select(
                'saipa_sessions',
                "userid $insql AND courseid = :courseid",
                $inparams + ['courseid' => $context->instanceid]
            );
            $DB->delete_records_select(
                'saipa_risk_scores',
                "userid $insql AND courseid = :courseid",
                $inparams + ['courseid' => $context->instanceid]
            );
        } else if ($context->contextlevel == CONTEXT_SYSTEM) {
            $DB->delete_records_select('saipa_phone_verify', "userid $insql", $inparams);
            $DB->delete_records_select('saipa_notifications', "userid $insql", $inparams);
            $DB->delete_records_select('saipa_telegram_links', "userid $insql", $inparams);
        }
    }
}
