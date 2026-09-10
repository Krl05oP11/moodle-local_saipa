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
 * Web service function definitions for local_saipa.
 * These are the AJAX endpoints called by the block_saipa JS widget.
 *
 * @package    local_saipa
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [

    'local_saipa_health_check' => [
        'classname'     => 'local_saipa\external\health_check',
        'methodname'    => 'execute',
        'description'   => 'Checks connectivity between Moodle && saipa-engine',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'local/saipa:manage',
        'loginrequired' => true,
    ],

    'local_saipa_chat' => [
        'classname'     => 'local_saipa\external\chat',
        'methodname'    => 'execute',
        'description'   => 'Sends a student message to saipa-engine && returns the AI reply',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'local/saipa:chat',
        'loginrequired' => true,
    ],

    'local_saipa_get_student_list' => [
        'classname'     => 'local_saipa\external\get_student_list',
        'methodname'    => 'execute',
        'description'   => 'Returns enrolled students with chat activity for a course (teacher only)',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'local/saipa:view',
        'loginrequired' => true,
    ],

    'local_saipa_get_student_history' => [
        'classname'     => 'local_saipa\external\get_student_history',
        'methodname'    => 'execute',
        'description'   => 'Returns full conversation history for a specific student (teacher only)',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'local/saipa:view',
        'loginrequired' => true,
    ],

    'local_saipa_get_history' => [
        'classname'     => 'local_saipa\external\get_history',
        'methodname'    => 'execute',
        'description'   => 'Returns conversation history for the current user in a course',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'local/saipa:chat',
        'loginrequired' => true,
    ],

    'local_saipa_index_course' => [
        'classname'     => 'local_saipa\external\index_course',
        'methodname'    => 'execute',
        'description'   => 'Indexes all mod_page content from a course into the RAG vector store',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'local/saipa:view',
        'loginrequired' => true,
    ],

    'local_saipa_save_feedback' => [
        'classname'     => 'local_saipa\external\save_feedback',
        'methodname'    => 'execute',
        'description'   => 'Saves a thumbs-up || thumbs-down rating for an assistant message',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'local/saipa:chat',
        'loginrequired' => true,
    ],

    'local_saipa_get_student_features' => [
        'classname'     => 'local_saipa\external\get_student_features',
        'methodname'    => 'execute',
        'description'   => 'Aggregates 11 dropout-risk signals for a student in a course (called by saipa-engine)',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'local/saipa:view',
        'loginrequired' => true,
    ],

    'local_saipa_get_course_risk' => [
        'classname'     => 'local_saipa\external\get_course_risk',
        'methodname'    => 'execute',
        'description'   => 'Computes dropout risk for all enrolled students in a course (teacher only)',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'local/saipa:view',
        'loginrequired' => true,
    ],

    // ── Telegram integration ──────────────────────────────────────────────────

    'local_saipa_telegram_generate_link' => [
        'classname'     => 'local_saipa\external\telegram_generate_link',
        'methodname'    => 'execute',
        'description'   => 'Generates a signed HMAC deep-link token so a student can link their Telegram account',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'local/saipa:chat',
        'loginrequired' => true,
    ],

    'local_saipa_mark_alert_responded' => [
        'classname'     => 'local_saipa\external\mark_alert_responded',
        'methodname'    => 'execute',
        'description'   => 'Marks a pending teacher_alert as responded when the student sends a Telegram message (server-to-server)',
        'type'          => 'write',
        'ajax'          => false,
        'capabilities'  => '',
        'loginrequired' => false,
    ],

    'local_saipa_send_telegram_alert' => [
        'classname'     => 'local_saipa\external\send_telegram_alert',
        'methodname'    => 'execute',
        'description'   => 'Sends a proactive Telegram message to a student (teacher only)',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'local/saipa:view',
        'loginrequired' => true,
    ],

    'local_saipa_telegram_confirm_link' => [
        'classname'     => 'local_saipa\external\telegram_confirm_link',
        'methodname'    => 'execute',
        'description'   => 'Validates an HMAC token && binds a Telegram chat_id to the Moodle user (server-to-server)',
        'type'          => 'write',
        'ajax'          => false,
        'capabilities'  => '',
        'loginrequired' => false,
    ],

    'local_saipa_telegram_get_session' => [
        'classname'     => 'local_saipa\external\telegram_get_session',
        'methodname'    => 'execute',
        'description'   => 'Resolves a Telegram chat_id to Moodle user + course context (server-to-server)',
        'type'          => 'read',
        'ajax'          => false,
        'capabilities'  => '',
        'loginrequired' => false,
    ],

    'local_saipa_telegram_unlink' => [
        'classname'     => 'local_saipa\external\telegram_unlink',
        'methodname'    => 'execute',
        'description'   => 'Removes the Telegram link for the current Moodle user (AJAX)',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'local/saipa:chat',
        'loginrequired' => true,
    ],

    'local_saipa_telegram_unlink_by_id' => [
        'classname'     => 'local_saipa\external\telegram_unlink_by_id',
        'methodname'    => 'execute',
        'description'   => 'Removes the Telegram link for a given telegram_id (server-to-server, called on /desconectar)',
        'type'          => 'write',
        'ajax'          => false,
        'capabilities'  => '',
        'loginrequired' => false,
    ],

    'local_saipa_telegram_get_status' => [
        'classname'     => 'local_saipa\external\telegram_get_status',
        'methodname'    => 'execute',
        'description'   => 'Returns the Telegram link status for the current Moodle user (AJAX polling)',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'local/saipa:chat',
        'loginrequired' => true,
    ],

    // ── WhatsApp integration ──────────────────────────────────────────────────

    'local_saipa_whatsapp_start_verify' => [
        'classname'     => 'local_saipa\external\whatsapp_start_verify',
        'methodname'    => 'execute',
        'description'   => 'Sends a WhatsApp OTP to the student\'s phone number to begin verification',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'local/saipa:chat',
        'loginrequired' => true,
    ],

    'local_saipa_whatsapp_confirm_otp' => [
        'classname'     => 'local_saipa\external\whatsapp_confirm_otp',
        'methodname'    => 'execute',
        'description'   => 'Validates the OTP && marks the WhatsApp phone number as verified',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'local/saipa:chat',
        'loginrequired' => true,
    ],

    'local_saipa_whatsapp_unlink' => [
        'classname'     => 'local_saipa\external\whatsapp_unlink',
        'methodname'    => 'execute',
        'description'   => 'Removes the WhatsApp phone verification for the current user',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'local/saipa:chat',
        'loginrequired' => true,
    ],

    'local_saipa_whatsapp_get_status' => [
        'classname'     => 'local_saipa\external\whatsapp_get_status',
        'methodname'    => 'execute',
        'description'   => 'Returns the WhatsApp verification status for the current user',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'local/saipa:chat',
        'loginrequired' => true,
    ],

    // ── v0.5.0 — Dashboard & multi-course services ────────────────────────────

    'local_saipa_get_course_summary' => [
        'classname'     => 'local_saipa\external\get_course_summary',
        'methodname'    => 'execute',
        'description'   => 'Returns KPI metrics bar for a single course (teacher.php)',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'local/saipa:view',
        'loginrequired' => true,
    ],

    'local_saipa_get_my_courses' => [
        'classname'     => 'local_saipa\external\get_my_courses',
        'methodname'    => 'execute',
        'description'   => 'Returns summary metrics for all courses the user teaches (or all courses for admins)',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'local/saipa:viewall',
        'loginrequired' => true,
    ],

    'local_saipa_get_institution_summary' => [
        'classname'     => 'local_saipa\external\get_institution_summary',
        'methodname'    => 'execute',
        'description'   => 'Returns institution-wide KPIs && sparkline data for the advisor dashboard',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'local/saipa:advisor',
        'loginrequired' => true,
    ],

    'local_saipa_get_risk_dashboard' => [
        'classname'     => 'local_saipa\external\get_risk_dashboard',
        'methodname'    => 'execute',
        'description'   => 'Returns risk ROI data: alert funnel, weekly trend, && intervention effectiveness',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'local/saipa:advisor',
        'loginrequired' => true,
    ],

    'local_saipa_get_engagement_stats' => [
        'classname'     => 'local_saipa\external\get_engagement_stats',
        'methodname'    => 'execute',
        'description'   => 'Returns engagement metrics: per-course usage, hourly heatmap, session depth',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'local/saipa:advisor',
        'loginrequired' => true,
    ],

    'local_saipa_get_course_settings' => [
        'classname'     => 'local_saipa\external\get_course_settings',
        'methodname'    => 'execute',
        'description'   => 'Returns per-course feature flag settings for the advisor controls tab',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'local/saipa:advisor',
        'loginrequired' => true,
    ],

    'local_saipa_set_course_settings' => [
        'classname'     => 'local_saipa\external\set_course_settings',
        'methodname'    => 'execute',
        'description'   => 'Upserts per-course feature flags (saipa_enabled, chat, risk, alerts, rag)',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'local/saipa:advisor',
        'loginrequired' => true,
    ],

    'local_saipa_save_institution_config' => [
        'classname'     => 'local_saipa\external\save_institution_config',
        'methodname'    => 'execute',
        'description'   => 'Saves global institution settings (thresholds, cooldown, retention, templates)',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'local/saipa:manage',
        'loginrequired' => true,
    ],

    'local_saipa_admin_chat' => [
        'classname'     => 'local_saipa\external\admin_chat',
        'methodname'    => 'execute',
        'description'   => 'Contextual advisor/admin chat assistant — answers questions about the SAIPA dashboard',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'local/saipa:view',
        'loginrequired' => true,
    ],

];

$services = [
    'SAIPA External Service' => [
        'functions'       => [
            'local_saipa_health_check',
            'local_saipa_chat',
            'local_saipa_get_history',
            'local_saipa_get_student_list',
            'local_saipa_get_student_history',
            'local_saipa_index_course',
            'local_saipa_save_feedback',
            'local_saipa_get_student_features',
            'local_saipa_get_course_risk',
            'local_saipa_send_telegram_alert',
            'local_saipa_telegram_generate_link',
            'local_saipa_telegram_confirm_link',
            'local_saipa_telegram_get_session',
            'local_saipa_telegram_unlink',
            'local_saipa_telegram_unlink_by_id',
            'local_saipa_telegram_get_status',
            'local_saipa_whatsapp_start_verify',
            'local_saipa_whatsapp_confirm_otp',
            'local_saipa_whatsapp_unlink',
            'local_saipa_whatsapp_get_status',
            'local_saipa_get_course_summary',
            'local_saipa_get_my_courses',
            'local_saipa_get_institution_summary',
            'local_saipa_get_risk_dashboard',
            'local_saipa_get_engagement_stats',
            'local_saipa_get_course_settings',
            'local_saipa_set_course_settings',
            'local_saipa_save_institution_config',
            'local_saipa_admin_chat',
        ],
        'restrictedusers' => 0,
        'enabled'         => 1,
        'shortname'       => 'saipa_service',
    ],
];
