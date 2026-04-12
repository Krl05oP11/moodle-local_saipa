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
$string['help_funnel_step1']        = '<strong>Evaluated</strong> — Students whose risk score was computed by the XGBoost model.';
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
