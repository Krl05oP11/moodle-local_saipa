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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * English language strings for local_saipa.
 *
 * @package    local_saipa
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */$string['advisor_period']         = 'Period:';
$string['advisor_tab_config']     = 'Configuration';
$string['advisor_tab_controls']   = 'Course Controls';
$string['advisor_tab_engagement'] = 'Engagement';
$string['advisor_tab_risk']       = 'Risk ROI';
$string['advisor_tab_summary']    = 'Summary';
$string['advisor_title']          = 'SAIPA — Advisor Dashboard';
$string['alert_cancel_btn']         = 'Cancel';
$string['alert_default_message']    = 'Hola {$a->student} 👋

Tu docente {$a->teacher} notó que podrías necesitar apoyo en el curso *{$a->course}*.

{$a->risk_detail}

Recordá que podés escribirme en cualquier momento para consultar dudas sobre los temas de la materia. ¡Estoy aquí para ayudarte! 📚';
$string['alert_not_linked']         = '{$a} does not have Telegram linked.';
$string['alert_send_btn']           = 'Send';
$string['alert_sent_error']         = 'Could not send message: {$a}.';
$string['alert_sent_ok']            = 'Message sent to {$a}.';
$string['alert_telegram_btn']       = 'Alert via Telegram';
$string['cfg_alert_template']       = 'Default alert message template';
$string['cfg_cooldown']             = 'Alert cooldown';
$string['cfg_cooldown_hours']       = 'Alert cooldown (hours between alerts to same student)';
$string['cfg_heading_alerts']       = 'Alert Settings';
$string['cfg_heading_features']     = 'Feature Flags';
$string['cfg_heading_gdpr']         = 'Data Retention (GDPR)';
$string['cfg_heading_thresholds']   = 'Risk Thresholds';
$string['cfg_rag_global']           = 'Enable RAG (AI responses) globally';
$string['cfg_retention']            = 'Data retention';
$string['cfg_retention_days']       = 'Days to retain messages and risk scores';
$string['cfg_risk_eval']            = 'Enable nightly risk evaluation';
$string['cfg_risk_high']            = 'High risk threshold';
$string['cfg_risk_medium']          = 'Medium risk threshold';
$string['cfg_save_btn']             = 'Save configuration';
$string['cfg_save_error']           = 'Error saving configuration.';
$string['cfg_saved_ok']             = 'Configuration saved.';
$string['cfg_template_placeholders'] = 'Placeholders: {student}, {course}, {teacher}';
$string['cfg_threshold_high']       = 'High risk threshold (0–1)';
$string['cfg_threshold_medium']     = 'Medium risk threshold (0–1)';
$string['ctrl_col_alerts']  = 'Alerts';
$string['ctrl_col_chat']    = 'Chat';
$string['ctrl_col_course']  = 'Course';
$string['ctrl_col_index']   = 'Re-index';
$string['ctrl_col_rag']     = 'RAG';
$string['ctrl_col_risk']    = 'Risk';
$string['ctrl_col_saipa']   = 'SAIPA';
$string['ctrl_disable_all'] = 'Disable all';
$string['ctrl_enable_all']  = 'Enable all';
$string['ctrl_save_error']  = 'Error saving settings.';
$string['ctrl_saved']       = 'Saved.';
$string['eff_col_alert_date']   = 'Alert date';
$string['eff_col_course']       = 'Course';
$string['eff_col_improved']     = 'Improved';
$string['eff_col_risk_after']   = 'Risk after (14d)';
$string['eff_col_risk_before']  = 'Risk before';
$string['eff_col_user']         = 'Student';
$string['eff_no_data']          = 'No intervention data available for this period.';
$string['effectiveness_title']  = 'Intervention effectiveness';
$string['eng_col_avg_depth']      = 'Avg msgs/session';
$string['eng_col_course']         = 'Course';
$string['eng_col_feedback']       = 'Positive feedback %';
$string['eng_col_msgs']           = 'Messages';
$string['eng_col_users']          = 'Unique users';
$string['eng_course_usage_title'] = 'Per-course usage';
$string['eng_depth_title']        = 'Session depth distribution';
$string['eng_heatmap_desc']       = 'When do students chat? Day 0=Monday, Day 6=Sunday. Hour 0=midnight.';
$string['eng_heatmap_title']      = 'Activity heatmap (hour × day of week)';
$string['engine_error'] = 'SAIPA engine error';
$string['funnel_title']         = 'Alert funnel';
$string['healthcheck:fail']    = 'Cannot reach SAIPA Engine at configured URL';
$string['healthcheck:heading'] = 'Engine Connection Test';
$string['healthcheck:ok']      = 'SAIPA Engine is reachable';
$string['help_btn']                 = 'Help';
$string['help_cfg_cooldown']        = 'Minimum hours between consecutive alerts to the same student. Prevents alert fatigue. Default: 24h.';
$string['help_cfg_high']            = 'Students with a risk score above this value are flagged as high risk (red badge) and trigger automatic alerts. Must be greater than the medium threshold. Recommended: 0.65–0.80.';
$string['help_cfg_medium']          = 'Students with a risk score above this value are flagged as medium risk (yellow badge). Recommended: 0.35–0.50.';
$string['help_cfg_retention']       = 'Days to retain chat messages and risk score history before deletion. Relevant for GDPR compliance. Default: 730 days (2 years).';
$string['help_col_default']         = 'Default';
$string['help_col_effect']          = 'Effect';
$string['help_col_meaning']         = 'Meaning';
$string['help_col_metric']          = 'Metric';
$string['help_col_param']           = 'Parameter';
$string['help_col_toggle']          = 'Control';
$string['help_col_use']             = 'When to change';
$string['help_depth_desc']          = 'Histogram showing how many messages students exchange per session. Sessions with 11+ messages indicate deep engagement. Short sessions (1–3 messages) may signal the student did not find a useful answer.';
$string['help_depth_title']         = 'Session depth distribution';
$string['help_effectiveness_desc']  = 'For each student who received an alert, compares their risk score at the time of the alert versus 14 days later. A green arrow indicates the student\'s risk decreased, suggesting the alert triggered re-engagement.';
$string['help_effectiveness_title'] = 'Intervention effectiveness table';
$string['help_funnel_step1']        = '<strong>Evaluated</strong> — Students whose risk score was computed by the risk model.';
$string['help_funnel_step2']        = '<strong>High risk detected</strong> — Students whose score exceeded the high-risk threshold.';
$string['help_funnel_step3']        = '<strong>Received alert</strong> — Students to whom a Telegram alert was actually sent.';
$string['help_funnel_step4']        = '<strong>Responded</strong> — Students who replied to the alert within 48 hours.';
$string['help_funnel_step5']        = '<strong>Re-engaged with Moodle</strong> — Students who logged in to Moodle within 7 days of receiving the alert.';
$string['help_funnel_title']        = 'Alert funnel';
$string['help_heatmap_desc']        = 'Each cell represents the number of messages sent during that hour and day of the week. Darker blue = more activity. Use this to schedule Telegram campaigns or faculty office hours.';
$string['help_heatmap_title']       = 'Activity heatmap (hour × day)';
$string['help_kpi_active_courses']  = 'Courses that have at least one SAIPA session in the selected period.';
$string['help_kpi_alerts_resp']     = 'Alerts to which the student replied within 48 hours.';
$string['help_kpi_alerts_sent']     = 'Dropout-risk alerts sent by SAIPA to students via Telegram.';
$string['help_kpi_enrolled']        = 'Total students enrolled across all active courses.';
$string['help_kpi_feedback']        = 'Percentage of sessions where the student rated the response positively (👍).';
$string['help_kpi_messages']        = 'Total messages exchanged (student + assistant) in the period.';
$string['help_kpi_saipa_users']     = 'Unique students who opened at least one SAIPA chat session.';
$string['help_sparkline_note']      = 'The daily trend charts are populated by the <em>aggregate_daily_stats</em> scheduled task (runs at 03:00). Data appears after the first night the task executes. Enable it in Site Administration → Server → Scheduled tasks.';
$string['help_sparkline_note_title'] = 'About the sparklines:';
$string['help_subtitle']            = 'Institutional AI-powered pedagogical accompaniment system';
$string['help_support_link']        = 'Contact support';
$string['help_support_url']         = 'mailto:soporte@schaller-ponce.com.ar';
$string['help_tab1_intro']          = 'Shows key performance indicators (KPIs) for the selected period. Use the period selector (7d / 30d / semester / all) to change the time range. All metrics are institution-wide unless a course filter is applied.';
$string['help_tab1_title']          = 'Tab 1 — Institution Summary';
$string['help_tab2_intro']          = 'Shows the effectiveness of SAIPA\'s dropout detection and alert pipeline. Use this tab to demonstrate return-on-investment to academic directors.';
$string['help_tab2_title']          = 'Tab 2 — Dropout Risk ROI';
$string['help_tab3_intro']          = 'Analyses how and when students interact with SAIPA. Useful for understanding adoption patterns and planning communication strategies.';
$string['help_tab3_title']          = 'Tab 3 — Engagement';
$string['help_tab4_intro']          = 'Enable or disable individual SAIPA features per course. Changes take effect immediately without reloading. Use bulk actions to apply settings across all courses at once.';
$string['help_tab4_title']          = 'Tab 4 — Course Controls';
$string['help_tab4_warning']        = 'Disabling SAIPA on a course does not delete existing data. All history, risk scores, and messages are preserved and will resume if SAIPA is re-enabled.';
$string['help_tab5_intro']          = 'Global settings that affect all courses. Only users with the <em>local/saipa:manage</em> capability (typically Site Administrators) can modify these settings.';
$string['help_tab5_note']           = 'Changes to risk thresholds take effect on the next nightly risk evaluation cycle (02:00). Changes to cooldown and retention take effect immediately.';
$string['help_tab5_title']          = 'Tab 5 — Institution Configuration';
$string['help_title']               = 'SAIPA Dashboard — Help';
$string['help_toggle_alerts']       = 'Stops sending Telegram alerts to students in this course, even if a high risk is detected.';
$string['help_toggle_chat']         = 'Hides the chat interface. Risk evaluation and alerts continue running.';
$string['help_toggle_rag']          = 'Disables retrieval-augmented responses. The assistant will answer from the base LLM only (no course-specific knowledge).';
$string['help_toggle_risk']         = 'Stops risk score computation for this course. No new alerts will be generated.';
$string['help_toggle_saipa']        = 'Master switch. When OFF, SAIPA does not appear in the course block for any student.';
$string['help_trend_desc']          = 'Line chart showing the percentage of students at each risk level (high / medium / low) per week. A downward trend in the red line after an intervention period indicates the system is effective.';
$string['help_trend_title']         = 'Weekly risk trend';
$string['kpi_active_courses'] = 'Active courses';
$string['kpi_alerts_resp']    = 'Alerts responded';
$string['kpi_alerts_sent']    = 'Alerts sent';
$string['kpi_enrolled']       = 'Enrolled students';
$string['kpi_feedback']       = 'Positive feedback';
$string['kpi_messages']       = 'Total messages';
$string['kpi_saipa_users']    = 'SAIPA users';
$string['mc_col_active']      = 'SAIPA users';
$string['mc_col_adoption']    = 'Adoption';
$string['mc_col_course']      = 'Course';
$string['mc_col_enrolled']    = 'Enrolled';
$string['mc_col_index']       = 'Index';
$string['mc_col_msgs7d']      = 'Msgs (7d)';
$string['mc_col_risk']        = 'Risk';
$string['mc_no_courses']      = 'No SAIPA-enabled courses found.';
$string['messageprovider:risk_alert']    = 'Dropout risk alerts (SAIPA)';
$string['messageprovider:telegram_link'] = 'Telegram account linking confirmation (SAIPA)';
$string['my_courses_heading'] = 'My SAIPA Courses';
$string['my_courses_title']   = 'SAIPA — My Courses';
$string['no_data_yet']        = 'Not enough data yet — check back in a few days.';
$string['notenrolled']              = 'The specified user is not enrolled in this course.';
$string['period_30d']             = 'Last 30 days';
$string['period_7d']              = 'Last 7 days';
$string['period_all']             = 'All time';
$string['period_semester']        = 'Semester (120d)';
$string['pluginname'] = 'SAIPA - Adaptive Pedagogical Intervention System';
$string['privacy:metadata:risk_history:risk_level'] = 'The risk level label (low/medium/high)';
$string['privacy:metadata:risk_history:score'] = 'The numeric risk score';
$string['privacy:metadata:risk_history:userid'] = 'The user whose risk was scored';
$string['privacy:metadata:saipa_engine']          = 'Chat messages are sent to the SAIPA engine for AI processing';
$string['privacy:metadata:saipa_engine:message']  = 'The text of the message sent for processing';
$string['privacy:metadata:saipa_engine:userid']   = 'The ID of the user whose message is being processed';
$string['privacy:metadata:saipa_feedback']             = 'Thumbs up/down feedback on assistant responses';
$string['privacy:metadata:saipa_feedback:comment']     = 'Optional comment explaining the rating';
$string['privacy:metadata:saipa_feedback:rating']      = 'Rating: 1 for positive, -1 for negative';
$string['privacy:metadata:saipa_feedback:timecreated'] = 'The time the feedback was submitted';
$string['privacy:metadata:saipa_feedback:userid']      = 'The ID of the user who submitted the feedback';
$string['privacy:metadata:saipa_messages']             = 'Individual chat messages within a SAIPA session';
$string['privacy:metadata:saipa_messages:content']     = 'The text content of the message';
$string['privacy:metadata:saipa_messages:role']        = 'Whether the message was sent by the user or the assistant';
$string['privacy:metadata:saipa_messages:timecreated'] = 'The time the message was sent';
$string['privacy:metadata:saipa_notifications']           = 'Log of alert messages sent to students via messaging channels';
$string['privacy:metadata:saipa_notifications:payload']   = 'The JSON payload sent (may include personalised text)';
$string['privacy:metadata:saipa_notifications:status']    = 'Delivery status: sent, failed, or pending';
$string['privacy:metadata:saipa_notifications:template']  = 'The message template used';
$string['privacy:metadata:saipa_notifications:timesent']  = 'The time the notification was sent';
$string['privacy:metadata:saipa_notifications:userid']    = 'The ID of the student who received the notification';
$string['privacy:metadata:saipa_phone_verify']           = 'WhatsApp phone number verification records';
$string['privacy:metadata:saipa_phone_verify:phone']     = 'The phone number provided for WhatsApp verification';
$string['privacy:metadata:saipa_phone_verify:userid']    = 'The ID of the user';
$string['privacy:metadata:saipa_phone_verify:verified']  = 'Whether the phone number has been verified';
$string['privacy:metadata:saipa_risk_history'] = 'Append-only risk score log for longitudinal research';
$string['privacy:metadata:saipa_risk_scores']              = 'Daily dropout risk scores computed per student and course';
$string['privacy:metadata:saipa_risk_scores:factors']      = 'JSON object listing the contributing factors';
$string['privacy:metadata:saipa_risk_scores:risk_level']   = 'Risk category: low, medium, or high';
$string['privacy:metadata:saipa_risk_scores:score']        = 'The computed dropout risk score (0.0–1.0)';
$string['privacy:metadata:saipa_risk_scores:timecomputed'] = 'The time the score was calculated';
$string['privacy:metadata:saipa_risk_scores:userid']       = 'The ID of the student';
$string['privacy:metadata:saipa_sessions']             = 'SAIPA chat session records per student and course';
$string['privacy:metadata:saipa_sessions:courseid']    = 'The course the session belongs to';
$string['privacy:metadata:saipa_sessions:timecreated'] = 'The time the session was created';
$string['privacy:metadata:saipa_sessions:userid']      = 'The ID of the user who owns the session';
$string['privacy:metadata:saipa_telegram_links']                   = 'Telegram account links connecting Moodle users to Telegram';
$string['privacy:metadata:saipa_telegram_links:confirmed']         = 'Whether the Telegram link has been confirmed by the user';
$string['privacy:metadata:saipa_telegram_links:telegram_id']       = 'The Telegram chat ID (set after confirmation)';
$string['privacy:metadata:saipa_telegram_links:telegram_username'] = 'The Telegram @username (informational only)';
$string['privacy:metadata:saipa_telegram_links:userid']            = 'The ID of the Moodle user';
$string['risk_button']              = 'Calculate risk';
$string['risk_demo_button']         = 'Simulate';
$string['risk_trend_title']     = 'Risk level trend by week';
$string['saipa:advisor'] = 'Access the advisor dashboard';
$string['saipa:chat']    = 'Use SAIPA chat assistant';
$string['saipa:manage']  = 'Manage SAIPA settings';
$string['saipa:view']    = 'View SAIPA dashboard';
$string['saipa:viewall'] = 'View all student data across courses';
$string['settings:channel_both']               = 'Both — Telegram and WhatsApp';
$string['settings:channel_none']               = 'None (messaging disabled)';
$string['settings:channel_telegram']           = 'Telegram only';
$string['settings:channel_whatsapp']           = 'WhatsApp only';
$string['settings:engine_token']      = 'API Token';
$string['settings:engine_token_desc'] = 'Bearer token for authenticating requests to saipa-engine';
$string['settings:engine_url']        = 'SAIPA Engine URL';
$string['settings:engine_url_desc']   = 'URL of the saipa-engine FastAPI service (e.g. http://host.docker.internal:8052)';
$string['settings:heading_engine']    = 'SAIPA Engine Connection';
$string['settings:heading_messaging']          = 'Messaging Channels';
$string['settings:heading_messaging_desc']     = 'Choose which messaging apps SAIPA uses to reach students. '
    . 'Telegram requires TELEGRAM_BOT_TOKEN in the engine .env. '
    . 'WhatsApp requires a connected Evolution API instance.';
$string['settings:heading_whatsapp']  = 'WhatsApp Configuration';
$string['settings:messaging_channel']          = 'Active messaging channel';
$string['settings:messaging_channel_desc']     = 'Select which messaging channels students can link from the SAIPA block.';
$string['settings:status_page'] = 'SAIPA — Installation Status';
$string['settings:telegram_bot_username']      = 'Telegram bot username (without @)';
$string['settings:telegram_bot_username_desc'] = 'The Telegram bot username without the @ sign (e.g. saipa_bot). '
    . 'Used to build the deep link shown to students.';
$string['settings:twilio_from']       = 'Twilio WhatsApp From Number';
$string['settings:twilio_sid']        = 'Twilio Account SID';
$string['settings:twilio_token']      = 'Twilio Auth Token';
$string['settings:whatsapp_provider'] = 'WhatsApp Provider';
$string['settings:whatsapp_provider_desc'] = 'Select WhatsApp Business API provider (twilio or meta)';
$string['sparkline_messages'] = 'Daily messages (last 30 days)';
$string['sparkline_users']    = 'Daily active users (last 30 days)';
$string['summary_active']    = 'SAIPA users';
$string['summary_alerts30d'] = 'Alerts (30d)';
$string['summary_enrolled']  = 'Enrolled';
$string['summary_index']     = 'Index';
$string['summary_msgs7d']    = 'Msgs (7d)';
$string['summary_risk']      = 'High risk';
$string['teacher_back_to_list']     = 'Back to list';
$string['teacher_dashboard_title']  = 'SAIPA — Teacher Dashboard';
$string['teacher_students_heading'] = 'Student Activity';
$string['telegram:link_button']   = 'Link my Telegram';
$string['telegram:link_desc']     = 'Link your Telegram account to receive SAIPA assistant replies directly in the app.';
$string['telegram:link_error']    = 'Could not generate link. Please try again.';
$string['telegram:link_title']    = 'Link Telegram';
$string['telegram:linked_as']     = 'Connected as @{$a}';
$string['telegram:linked_title']  = 'Telegram linked';
$string['telegram:open_dialog']   = 'Open dialog in Telegram';
$string['telegram:polling_msg']   = 'Waiting for confirmation...';
$string['telegram:unlink_button'] = 'Unlink';
$string['whatsapp:confirm_button']    = 'Verify';
$string['whatsapp:link_desc']         = 'Link your WhatsApp number to receive SAIPA assistant messages.';
$string['whatsapp:link_title']        = 'Link WhatsApp';
$string['whatsapp:otp_expired']       = 'Code has expired. Please request a new one.';
$string['whatsapp:otp_invalid']       = 'Incorrect code. Please check and try again.';
$string['whatsapp:otp_label']         = 'Verification code received on WhatsApp';
$string['whatsapp:otp_sent']          = 'Code sent. Check your WhatsApp and enter it here.';
$string['whatsapp:phone_label']       = 'Your WhatsApp number (with country code, no +)';
$string['whatsapp:phone_placeholder'] = 'e.g. 5491112345678';
$string['whatsapp:send_error']        = 'Could not send the code. Check your number and try again.';
$string['whatsapp:send_otp_button']   = 'Send code';
$string['whatsapp:unlink_button']     = 'Unlink';
$string['whatsapp:verified_as']       = 'Verified number: {$a}';
$string['whatsapp:verified_title']    = 'WhatsApp linked';

// ── Setup wizard ─────────────────────────────────────────────────────────────
$string['wizard_page_title']   = 'SAIPA — Setup Wizard';
$string['wizard_page_heading'] = 'SAIPA Setup Wizard';

// Progress bar labels.
$string['wizard_step_welcome']      = 'Welcome';
$string['wizard_step_requirements'] = 'Requirements';
$string['wizard_step_ai_mode']      = 'AI Mode';
$string['wizard_step_engine']       = 'Engine';
$string['wizard_step_telegram']     = 'Telegram';
$string['wizard_step_test']         = 'Test';
$string['wizard_step_done']         = 'Done';

// Common buttons.
$string['wizard_btn_next']        = 'Next →';
$string['wizard_btn_back']        = '← Back';
$string['wizard_btn_retry']       = '↻ Retry';
$string['wizard_btn_save_finish'] = '✅ Save & Finish';
$string['wizard_btn_validate']    = 'Validate';

// AJAX / save messages.
$string['wizard_err_url_required']       = 'Engine URL is required.';
$string['wizard_err_connection']         = 'Connection failed: {$a}';
$string['wizard_err_http']               = 'Engine returned HTTP {$a}. Check the URL and token.';
$string['wizard_err_unexpected_response'] = 'Unexpected engine response: {$a}';
$string['wizard_err_no_bot_token']       = 'No bot token provided.';
$string['wizard_err_telegram_unreachable'] = 'Could not reach Telegram API: {$a}';
$string['wizard_err_telegram_api']       = 'Telegram API error: {$a}';
$string['wizard_save_success']           = 'SAIPA configuration saved successfully.';

// Step 1 — Welcome.
$string['wizard_welcome_title']   = 'Welcome to SAIPA';
$string['wizard_welcome_intro']   = 'This wizard configures the AI companion in a few steps. It covers the engine connection, notification channels, and core parameters.';
$string['wizard_welcome_about']   = 'SAIPA (Sistema de Acompañamiento Inteligente Pedagógico con IA) helps teachers detect at-risk students early and supports learning through AI-powered tools:';
$string['wizard_feat_dropout_title'] = 'Dropout Risk Detection';
$string['wizard_feat_dropout_desc']  = 'A rule-based model scores dropout probability from 11 engagement signals. Risk badges per student: 🟢 Low / 🟡 Medium / 🔴 High.';
$string['wizard_feat_chat_title']    = 'RAG-Powered Chat';
$string['wizard_feat_chat_desc']     = 'Students and teachers chat with an AI assistant that has context from the course materials. Role-aware responses.';
$string['wizard_feat_alerts_title']  = 'Proactive Telegram Alerts';
$string['wizard_feat_alerts_desc']   = 'Teachers send personalised AI-generated alerts to at-risk students directly from the dashboard via Telegram.';
$string['wizard_feat_advisor_title'] = 'Advisor Dashboard';
$string['wizard_feat_advisor_desc']  = 'Institution-wide view: risk distribution, engagement trends, per-course health across all courses.';
$string['wizard_feat_index_title']   = 'Course Indexing';
$string['wizard_feat_index_desc']    = 'Index Moodle Pages, PDFs, and PPTX presentations into a vector database for RAG retrieval.';
$string['wizard_feat_evalia_title']  = 'EVAL-IA Compatible';
$string['wizard_feat_evalia_desc']   = 'When both plugins are installed, EVAL-IA automatically inherits SAIPA\'s engine configuration.';

// Step 2 — Requirements.
$string['wizard_req_title'] = 'Minimum requirements';
$string['wizard_req_intro'] = 'Verify that your environment meets all requirements before continuing. <strong>SAIPA will not work without an active AI service.</strong>';
$string['wizard_req_platform']    = '🖥️ Platform';
$string['wizard_req_moodle_title']  = 'Moodle 4.4 or 4.5';
$string['wizard_req_moodle_desc']   = 'Older versions are not supported.';
$string['wizard_req_php_title']     = 'PHP 8.1+';
$string['wizard_req_php_desc']      = 'PHP 7.x is not supported.';
$string['wizard_req_curl_title']    = 'PHP cURL extension';
$string['wizard_req_curl_desc']     = 'Required to communicate with the AI engine and Telegram API.';
$string['wizard_req_curl_enabled']  = 'Enabled';
$string['wizard_req_curl_missing']  = 'Missing';
$string['wizard_req_block_title']   = 'block_saipa (companion block)';
$string['wizard_req_block_desc']    = 'Required — provides the chat widget in course sidebars.';
$string['wizard_req_block_installed'] = 'Installed';
$string['wizard_req_block_missing']   = 'Not installed — <a href="{$a}">install now</a>';

$string['wizard_req_ai_heading']   = '⚠️ AI Engine — <em>Required. SAIPA will not function without this.</em>';
$string['wizard_req_ai_intro']     = 'SAIPA uses the <strong>saipa-engine</strong> Python service for all AI operations: course chat (RAG), dropout risk prediction, alert generation, and Telegram integration. This service must be running and reachable from this Moodle server.';
$string['wizard_req_enginepy_title'] = 'saipa-engine (Python 3.11+ / FastAPI)';
$string['wizard_req_enginepy_desc']  = 'Handles LLM inference, vector search (ChromaDB), the dropout-risk model, and Telegram bot.';
$string['wizard_req_enginepy_value'] = 'Must be deployed separately';
$string['wizard_req_chroma_title']   = 'ChromaDB (embedded in saipa-engine)';
$string['wizard_req_chroma_desc']    = 'Vector database for RAG over indexed course materials.';
$string['wizard_req_included']       = 'Included in engine';
$string['wizard_req_llm_title']      = 'Large Language Model (LLM)';
$string['wizard_req_llm_desc']       = 'Powers chat, risk explanations, and alert generation. See provisioning options below.';
$string['wizard_req_llm_value']      = 'AI service required';
$string['wizard_req_xgb_title']      = 'Dropout-risk model';
$string['wizard_req_xgb_desc']       = 'A deterministic rule-based scorer bundled with saipa-engine. Works from day one; at least 4 weeks of student activity make the signal meaningful.';

$string['wizard_prov_heading'] = '🤖 AI service provisioning — choose one option';
$string['wizard_prov_intro']   = 'The LLM that powers SAIPA can come from three sources. You must have at least one ready.';
$string['wizard_prov_local_title'] = 'Local — Ollama';
$string['wizard_prov_local_badge'] = 'SELF-HOSTED';
$string['wizard_prov_local_desc']  = 'Run the LLM on your own server using <a href="https://ollama.com" target="_blank">Ollama</a>. Full privacy — no data leaves your infrastructure.';
$string['wizard_prov_local_i1']    = 'Recommended: <code>qwen2.5:14b</code> (≥16 GB RAM)';
$string['wizard_prov_local_i2']    = 'Minimum: any 7B model (≥8 GB RAM)';
$string['wizard_prov_local_i3']    = 'saipa-engine must have network access to Ollama';
$string['wizard_prov_cloud_title'] = 'Cloud API';
$string['wizard_prov_cloud_badge'] = 'OPENAI-COMPATIBLE';
$string['wizard_prov_cloud_desc']  = 'Any OpenAI-compatible API (OpenAI, Azure, Groq, Mistral…) with your own key.';
$string['wizard_prov_cloud_i1']    = 'No local GPU required';
$string['wizard_prov_cloud_i2']    = 'Cost depends on usage and provider';
$string['wizard_prov_cloud_i3']    = 'Set <code>OPENAI_API_KEY</code> in saipa-engine\'s <code>.env</code>';
$string['wizard_prov_saipa_title'] = 'SAIPA Cloud';
$string['wizard_prov_saipa_badge'] = 'COMING SOON';
$string['wizard_prov_saipa_desc']  = 'Fully managed engine. No Ollama, no ChromaDB to install. Subscribe and connect.';
$string['wizard_prov_saipa_i1']    = 'Zero infrastructure to manage';
$string['wizard_prov_saipa_i2']    = 'Join waitlist at <code>cloud.saipa.online</code>';
$string['wizard_prov_custom_title'] = 'Custom / Enterprise';
$string['wizard_prov_custom_badge'] = 'ADVANCED';
$string['wizard_prov_custom_desc']  = 'Any compatible engine at a custom URL. Full control for advanced deployments.';
$string['wizard_prov_custom_i1']    = 'Must implement <code>GET /health</code>';
$string['wizard_prov_custom_i2']    = 'Must implement <code>POST /chat</code> and related endpoints';

$string['wizard_prov_warning'] = '<strong>⛔ Without an active AI service, SAIPA will not be able to:</strong> respond to student chat messages, generate risk scores, create Telegram alerts, index course materials, or provide advisor-level analytics. All these functions depend exclusively on the AI engine. <strong>Do not continue</strong> unless you have one of the options above deployed and ready.';
$string['wizard_req_confirm'] = 'I have read the requirements above. An AI service (saipa-engine + LLM) is deployed and reachable from this server.';

// Step 3 — AI Mode.
$string['wizard_mode_title'] = 'Choose your AI provisioning mode';
$string['wizard_mode_intro'] = 'Select the option that matches your deployed AI infrastructure.';
$string['wizard_mode_local_desc']  = 'saipa-engine running on your server with Ollama as the LLM backend. Full data privacy.';
$string['wizard_mode_cloud_desc']  = 'saipa-engine configured with an OpenAI-compatible API key. No local GPU required.';
$string['wizard_mode_saipa_desc']  = 'Fully managed engine by Schaller & Ponce. Subscribe and connect with a single API key.';
$string['wizard_mode_custom_desc'] = 'Any compatible engine at a custom URL. Full control for advanced deployments.';

// Step 4 — Engine connection.
$string['wizard_engine_title'] = 'Engine connection';
$string['wizard_engine_intro'] = 'Enter the URL and token for the saipa-engine service.';
$string['wizard_hint_local']   = '<strong>🖥️ Local / Ollama:</strong> Default port is <code>8052</code>. If running via Docker on the same host, use <code>http://localhost:8052</code>. If Moodle itself runs in Docker, use <code>http://host.docker.internal:8052</code>.';
$string['wizard_hint_cloud']   = '<strong>☁️ Cloud API:</strong> Enter the URL of your saipa-engine instance (configured with your cloud API key) and the <code>SAIPA_API_TOKEN</code> token.';
$string['wizard_hint_saipa']   = '<strong>🌐 SAIPA Cloud is not yet available.</strong> Please select Local or Cloud API to continue.';
$string['wizard_hint_custom']  = '<strong>⚙️ Custom:</strong> Enter the base URL of your engine. The wizard will verify <code>{url}/health</code> returns <code>{"status":"ok"}</code>.';
$string['wizard_url_label']       = 'Engine URL';
$string['wizard_url_placeholder'] = 'http://localhost:8052';
$string['wizard_url_help']        = 'Base URL of the saipa-engine — no trailing slash.';
$string['wizard_token_label']       = 'Engine Token';
$string['wizard_token_placeholder'] = 'Leave blank if not configured';
$string['wizard_token_help']        = 'Value of <code>SAIPA_API_TOKEN</code> in the engine\'s <code>.env</code>. Leave blank if not set.';

// Step 5 — Telegram / Channels.
$string['wizard_channels_title'] = 'Notification channels';
$string['wizard_channels_intro'] = 'Choose how SAIPA delivers proactive alerts to students and teachers. Telegram is optional but strongly recommended — it is SAIPA\'s most powerful engagement feature.';
$string['wizard_ch_none_title']     = 'None';
$string['wizard_ch_none_desc']      = 'Moodle notifications only. Alerts visible inside Moodle.';
$string['wizard_ch_telegram_title'] = 'Telegram';
$string['wizard_ch_telegram_desc']  = 'Students receive alerts and AI chat via Telegram. Recommended.';
$string['wizard_ch_whatsapp_title'] = 'WhatsApp';
$string['wizard_ch_whatsapp_desc']  = 'Requires Twilio or Meta Cloud API. Configure after setup.';
$string['wizard_ch_both_title']     = 'Both';
$string['wizard_ch_both_desc']      = 'Telegram + WhatsApp. Maximum reach.';

$string['wizard_tg_heading'] = '✈️ Telegram Bot configuration';
$string['wizard_tg_intro']   = 'SAIPA uses a Telegram bot to deliver alerts and enable bidirectional chat with students. The bot token lives in <strong>saipa-engine\'s <code>.env</code> file</strong> (<code>TELEGRAM_BOT_TOKEN</code>); the bot username is stored in Moodle for display purposes.';
$string['wizard_tg_howto_title'] = 'How to create a Telegram bot:';
$string['wizard_tg_howto_1'] = 'Open Telegram and search for <strong>@BotFather</strong>';
$string['wizard_tg_howto_2'] = 'Send <code>/newbot</code> and follow the prompts';
$string['wizard_tg_howto_3'] = 'Copy the token (format: <code>1234567890:AABCD...</code>)';
$string['wizard_tg_howto_4'] = 'Add the token to saipa-engine\'s <code>.env</code>: <code>TELEGRAM_BOT_TOKEN=&lt;token&gt;</code>';
$string['wizard_tg_howto_5'] = 'Restart the engine: <code>docker compose restart saipa-engine</code>';
$string['wizard_tg_token_label']       = 'Bot token';
$string['wizard_tg_token_note']        = '(for validation only — not saved to Moodle)';
$string['wizard_tg_token_placeholder'] = '1234567890:AABCDEF...';
$string['wizard_tg_token_help']        = 'Enter the token to verify it works. It will NOT be stored here — only the username is saved to Moodle.';
$string['wizard_tg_username_label']       = 'Bot username';
$string['wizard_tg_username_placeholder'] = 'saipa_bot';
$string['wizard_tg_username_help']        = 'The bot\'s username without the @ prefix. Shown to students when they link their account.';
$string['wizard_tg_link_info'] = '<strong>Students link their accounts by:</strong> going to <em>My Profile → SAIPA → Link Telegram</em> in Moodle, then sending <code>/vincular &lt;code&gt;</code> to the bot in Telegram.';
$string['wizard_wa_note'] = '<strong>💬 WhatsApp configuration</strong> requires Twilio or Meta Cloud API credentials. This cannot be completed in the wizard. After finishing, go to <a href="{$a}">Admin Settings → SAIPA → WhatsApp</a> to configure it.';

// Step 6 — Test & summary.
$string['wizard_test_title']       = 'Connection test & configuration summary';
$string['wizard_test_intro']       = 'Verifying connectivity with the SAIPA Engine…';
$string['wizard_test_connecting']  = 'Connecting…';
$string['wizard_summary_heading']  = 'Configuration summary';
$string['wizard_summary_engine']   = '🤖 AI Engine';
$string['wizard_summary_mode']     = 'Mode';
$string['wizard_summary_url']      = 'URL';
$string['wizard_summary_token']    = 'Token';
$string['wizard_summary_notif']    = '📲 Notifications';
$string['wizard_summary_channel']  = 'Channel';
$string['wizard_summary_tgbot']    = 'Telegram bot';
$string['wizard_summary_risk']     = '📊 Risk thresholds';
$string['wizard_summary_medium']   = 'Medium 🟡';
$string['wizard_summary_high']     = 'High 🔴';
$string['wizard_summary_cooldown'] = 'Alert cooldown';
$string['wizard_summary_features'] = '⚙️ Features';
$string['wizard_summary_risk_eval'] = 'Risk evaluation';
$string['wizard_summary_rag']      = 'RAG global chat';
$string['wizard_enabled']          = '✅ Enabled';
$string['wizard_disabled']         = '⬜ Disabled';

// Step 7 — Done.
$string['wizard_done_title']     = 'SAIPA is ready!';
$string['wizard_done_desc']      = 'The AI companion has been configured and is ready to assist teachers and students.<br>Add the <strong>SAIPA block</strong> to a course to get started.';
$string['wizard_done_inline_title'] = 'Configuration saved!';
$string['wizard_done_inline_desc']  = 'SAIPA is connected and ready. Add the <strong>SAIPA block</strong> to any course to activate the chat widget and risk dashboard for that course.';
$string['wizard_btn_admin']    = '⚙️ Admin Settings';
$string['wizard_btn_teacher']  = '📊 Teacher Dashboard';
$string['wizard_btn_courses']  = 'Go to My Courses →';

// JS runtime strings.
$string['wizard_js_enter_token']       = 'Please enter a bot token first.';
$string['wizard_js_validating']        = 'Validating bot token…';
$string['wizard_js_bot_verified']      = 'Bot verified:';
$string['wizard_js_username_autofill'] = 'Username has been filled automatically.';
$string['wizard_js_error']             = 'Error:';
$string['wizard_js_invalid_token']     = 'Invalid token';
$string['wizard_js_network_error']     = 'Network error:';
$string['wizard_js_connecting']        = 'Connecting to engine…';
$string['wizard_js_url_empty']         = 'Engine URL is empty. Go back and enter a URL.';
$string['wizard_js_engine_reachable']  = 'Engine reachable';
$string['wizard_js_engine_version']    = 'Engine version';
$string['wizard_js_uptime']            = 'Uptime';
$string['wizard_js_success']           = '🎉 <strong>Connection successful!</strong> Review the summary below and click <em>Save & Finish</em>.';
$string['wizard_js_not_configured']    = 'Not configured';
$string['wizard_js_connection_failed'] = 'Connection failed';
$string['wizard_js_unknown_error']     = 'Unknown error';
$string['wizard_js_troubleshooting']   = 'Troubleshooting:';
$string['wizard_js_ts_running']        = 'Is saipa-engine running? <code>docker compose ps</code>';
$string['wizard_js_ts_url']            = 'Correct URL? Default: <code>http://localhost:8052</code>';
$string['wizard_js_ts_docker']         = 'Running Moodle in Docker? Use <code>http://host.docker.internal:8052</code>';
$string['wizard_js_ts_token']          = 'Token match? Check <code>SAIPA_API_TOKEN</code> in <code>.env</code>';
$string['wizard_js_mode_local']        = 'Local — Ollama';
$string['wizard_js_mode_cloud']        = 'Cloud API';
$string['wizard_js_mode_saipa']        = 'SAIPA Cloud';
$string['wizard_js_mode_custom']       = 'Custom / Enterprise';
$string['wizard_js_ch_none']           = 'None (Moodle only)';
$string['wizard_js_ch_telegram']       = 'Telegram';
$string['wizard_js_ch_whatsapp']       = 'WhatsApp';
$string['wizard_js_ch_both']           = 'Telegram + WhatsApp';
