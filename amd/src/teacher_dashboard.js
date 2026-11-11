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
 * SAIPA Teacher Dashboard AMD module.
 * Loads student list + conversation viewer via web services.
 *
 * @module     local_saipa/teacher_dashboard
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/log', 'core/str', 'local_saipa/dom'], function(Ajax, Log, Str, Dom) {

    'use strict';

    var courseId;

    // Keyed by userid → {score, risk_level, factors}
    var riskData = {};

    // ── i18n ───────────────────────────────────────────────────────────────────
    // Every UI string this module builds, keyed by lang key ('component/key' for core
    // components, plain key for local_saipa). Values are the English fallback (identical
    // to lang/en) shown until loadStrings() resolves. Placeholders ({$a}, {$a->x}) are
    // left untouched by core/str when no parameter is passed and are filled by fmt().
    var S = {
        index_status_error: 'error',
        index_status_indexing: 'indexing',
        index_status_pending: 'pending',
        index_status_ready: 'ready',
        msgs_count: '{$a} msgs',
        risk_button: 'Calculate risk',
        risk_demo_button: 'Simulate',
        risk_level_high: 'High',
        risk_level_low: 'Low',
        risk_level_medium: 'Medium',
        teacher_alert_accessed: 'Accessed Moodle',
        teacher_alert_accessed_title: 'Logged in to Moodle after the alert',
        teacher_alert_btn: 'Send alert via Telegram',
        teacher_alert_btn_high: '⚠️ High risk — Send alert via Telegram',
        teacher_alert_delay_hours: '{$a}h',
        teacher_alert_delay_minutes: '{$a} min',
        teacher_alert_no_reply: 'No reply',
        teacher_alert_not_replied: 'Has not replied to the bot yet',
        teacher_alert_prompt: 'Message for {$a} (leave empty for the default message):',
        teacher_alert_replied_in: 'Replied to the bot in {$a}',
        teacher_alert_sent_on: 'Alert sent on {$a}',
        teacher_conv_empty: 'This student has not started a conversation yet.',
        teacher_conv_load_error: 'Error loading the conversation.',
        teacher_conv_student: 'Student',
        teacher_no_chat_activity: 'No chat activity',
        teacher_no_students: 'No students enrolled.',
        teacher_risk_badge: 'Risk: {$a->level} ({$a->score}%)',
        teacher_risk_calculating: 'Calculating…',
        teacher_risk_error: 'Error calculating risk',
        teacher_risk_simulating: 'Simulating…',
        teacher_students_load_error: 'Error loading students.',
        'core/loading': 'Loading'
    };

    /**
     * Resolve every string in S against the current language in one batch request.
     * Failures are logged and leave the English fallbacks in place, so the returned
     * promise always resolves.
     *
     * @returns {Promise} Resolved once S holds the best available strings.
     */
    function loadStrings() {
        var keys = Object.keys(S);
        return Str.get_strings(keys.map(function(k) {
            var parts = k.split('/');
            return parts.length === 2 ? {key: parts[1], component: parts[0]} : {key: k, component: 'local_saipa'};
        })).then(function(values) {
            keys.forEach(function(k, i) { S[k] = values[i]; });
            return values;
        }).catch(function(err) {
            Log.error('SAIPA teacher: string load error: ' + err);
        });
    }

    /**
     * Get a string from S with its placeholders filled in.
     *
     * @param {string} key Key in S.
     * @param {string|number|Object} [a] Value for {$a}, or an object whose properties fill {$a->prop}.
     * @returns {string} The resolved string.
     */
    function fmt(key, a) {
        var str = S[key];
        if (a !== null && typeof a === 'object') {
            Object.keys(a).forEach(function(prop) {
                str = str.split('{$a->' + prop + '}').join(String(a[prop]));
            });
            return str;
        }
        if (typeof a !== 'undefined') {
            str = str.split('{$a}').join(String(a));
        }
        return str;
    }

    /**
     * Format a Unix timestamp as a short date and time string in the page language.
     *
     * @param {number} ts Unix timestamp in seconds.
     * @returns {string} Formatted date, or an empty string when ts is falsy.
     */
    function formatDate(ts) {
        if (!ts) { return ''; }
        return new Date(ts * 1000).toLocaleString(document.documentElement.lang || undefined,
            {dateStyle: 'short', timeStyle: 'short'});
    }

    /**
     * Build the risk badge of a student from the cached risk data.
     *
     * @param {number} userid Moodle user id of the student.
     * @returns {(HTMLElement|null)} Badge element, or null when no risk data is cached.
     */
    function riskBadge(userid) {
        var r = riskData[userid];
        if (!r) { return null; }
        var map = {
            low:    {cls: 'badge-success', icon: '🟢'},
            medium: {cls: 'badge-warning', icon: '🟡'},
            high:   {cls: 'badge-danger', icon: '🔴'},
        };
        var cfg = map[r.risk_level] || map.medium;
        var title = fmt('teacher_risk_badge', {
            level: S['risk_level_' + r.risk_level] || r.risk_level,
            score: (r.score * 100).toFixed(0)
        });
        return Dom.el('span', 'badge ' + cfg.cls + ' badge-pill ml-1', cfg.icon + ' ' + (r.score * 100).toFixed(0) + '%',
            {title: title});
    }

    /**
     * Build the summary of a student's engagement with the last Telegram alert.
     *
     * @param {Object} student Student record with the alert engagement fields.
     * @returns {Array} Nodes and separators for Dom.append(); empty when no alert was sent.
     */
    function alertEngagement(student) {
        if (!student.last_alert_sent) { return []; }

        var parts = [];

        // Alert sent indicator.
        parts.push(Dom.el('span', 'text-muted', '📨 ' + formatDate(student.last_alert_sent),
            {title: fmt('teacher_alert_sent_on', formatDate(student.last_alert_sent))}));

        if (student.alert_responded) {
            var delay = student.alert_response_delay_min;
            var delayStr = delay < 60
                ? fmt('teacher_alert_delay_minutes', delay)
                : fmt('teacher_alert_delay_hours', Math.round(delay / 60));
            parts.push(Dom.el('span', 'text-success', '💬 ' + delayStr, {title: fmt('teacher_alert_replied_in', delayStr)}));
        } else {
            parts.push(Dom.el('span', 'text-warning', '⏳ ' + S.teacher_alert_no_reply, {title: S.teacher_alert_not_replied}));
        }

        if (student.moodle_accessed_after_alert) {
            parts.push(Dom.el('span', 'text-success', '✅ ' + S.teacher_alert_accessed, {title: S.teacher_alert_accessed_title}));
        }

        return Dom.join(parts, ' · ');
    }

    /**
     * Prompt for a message and send a Telegram alert to a student, updating the button state.
     *
     * @param {number} userid Moodle user id of the student.
     * @param {string} fullname Student full name shown in the prompt.
     * @param {HTMLElement} btn Alert button that triggered the action.
     */
    function sendAlert(userid, fullname, btn) {
        var msg = prompt(fmt('teacher_alert_prompt', fullname), '');
        if (msg === null) { return; } // Cancelled.

        btn.disabled = true;
        btn.textContent = '…';

        Ajax.call([{
            methodname: 'local_saipa_send_telegram_alert',
            args: {student_id: userid, course_id: courseId, message: msg}
        }])[0].then(function(result) {
            if (result.sent) {
                btn.textContent = '✓';
                btn.classList.replace('btn-warning', 'btn-success');
                // Update engagement indicator to show alert was just sent.
                var engEl = document.getElementById('saipa-eng-' + userid);
                var now = Math.floor(Date.now() / 1000);
                if (engEl) {
                    // Simulate a fresh student record with alert_sent but not responded.
                    Dom.replace(engEl, alertEngagement({
                        last_alert_sent:             now,
                        alert_responded:             false,
                        alert_response_delay_min:    0,
                        moodle_accessed_after_alert: false,
                    }));
                }
                setTimeout(function() {
                    btn.textContent = '📨';
                    btn.classList.replace('btn-success', 'btn-warning');
                    btn.disabled = false;
                }, 3000);
            } else {
                // Known error codes have their own message; anything else is shown as it came.
                var known = {
                    cooldown: ['alert_error_cooldown'],
                    engine_failed: ['alert_error_engine_failed'],
                    message_too_long: ['alert_error_message_too_long'],
                    student_not_linked: ['alert_not_linked', fullname],
                }[result.error];
                (known
                    ? Str.get_string(known[0], 'local_saipa', known[1])
                    : Str.get_string('alert_sent_error', 'local_saipa', result.error)
                ).then(alert).catch(Log.error);
                btn.textContent = '📨';
                btn.disabled = false;
            }
            return result;
        }).fail(function(err) {
            Log.error('SAIPA send_telegram_alert error: ' + JSON.stringify(err));
            Str.get_string('alert_sent_error', 'local_saipa', err.message || '').then(alert).catch(Log.error);
            btn.textContent = '📨';
            btn.disabled = false;
        });
    }

    /**
     * Create the clickable list item for a student, with risk badge, alert button and activity info.
     *
     * @param {Object} student Student record returned by local_saipa_get_student_list.
     * @returns {HTMLElement} The list item element.
     */
    function renderStudentRow(student) {
        var li = document.createElement('a');
        li.href = '#';
        li.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
        li.dataset.studentid = student.userid;

        var left = document.createElement('div');

        var nameRow = document.createElement('div');
        nameRow.className = 'd-flex align-items-center';

        var nameSpan = document.createElement('span');
        nameSpan.textContent = student.fullname;
        nameRow.appendChild(nameSpan);

        // Risk badge placeholder — updated when risk data arrives
        var riskSpan = document.createElement('span');
        riskSpan.id = 'saipa-risk-badge-' + student.userid;
        Dom.replace(riskSpan, riskBadge(student.userid));
        nameRow.appendChild(riskSpan);

        // Telegram alert button — shown for linked students, highlighted when high risk
        var alertBtn = document.createElement('button');
        alertBtn.id = 'saipa-alert-btn-' + student.userid;
        alertBtn.type = 'button';
        alertBtn.className = 'btn btn-sm ml-2 ' + (student.telegram_linked ? 'btn-outline-secondary' : 'd-none');
        alertBtn.title = student.telegram_linked ? S.teacher_alert_btn : '';
        alertBtn.textContent = '📨';
        alertBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            sendAlert(student.userid, student.fullname, alertBtn);
        });
        nameRow.appendChild(alertBtn);

        var meta = document.createElement('div');
        meta.className = 'text-muted small d-flex align-items-center flex-wrap';

        var activitySpan = document.createElement('span');
        activitySpan.textContent = student.has_session
            ? formatDate(student.last_message)
            : S.teacher_no_chat_activity;
        meta.appendChild(activitySpan);

        // Alert engagement indicator
        if (student.last_alert_sent > 0) {
            var engSpan = document.createElement('span');
            engSpan.id = 'saipa-eng-' + student.userid;
            engSpan.className = 'ml-2';
            Dom.replace(engSpan, alertEngagement(student));
            meta.appendChild(engSpan);
        } else {
            var engSpan = document.createElement('span');
            engSpan.id = 'saipa-eng-' + student.userid;
            meta.appendChild(engSpan);
        }

        left.appendChild(nameRow);
        left.appendChild(meta);

        var badge = document.createElement('span');
        if (student.has_session && student.message_count > 0) {
            badge.className = 'badge badge-primary badge-pill';
            badge.textContent = fmt('msgs_count', student.message_count);
        } else {
            badge.className = 'badge badge-light badge-pill text-muted';
            badge.textContent = '—';
        }

        li.appendChild(left);
        li.appendChild(badge);

        li.addEventListener('click', function(e) {
            e.preventDefault();
            loadConversation(student.userid, student.fullname);
        });

        return li;
    }

    /**
     * Request course risk scores and update the student badges, alert buttons and summary.
     *
     * @param {boolean} isDemo Whether to request simulated (demo) risk data.
     */
    function _doRisk(isDemo) {
        var btn = document.getElementById(isDemo ? 'saipa-demo-btn' : 'saipa-risk-btn');
        var otherBtn = document.getElementById(isDemo ? 'saipa-risk-btn' : 'saipa-demo-btn');
        var status = document.getElementById('saipa-risk-status');

        if (btn) { btn.disabled = true; btn.textContent = '…'; }
        if (otherBtn) { otherBtn.disabled = true; }
        if (status) { status.textContent = isDemo ? S.teacher_risk_simulating : S.teacher_risk_calculating; }

        Ajax.call([{
            methodname: 'local_saipa_get_course_risk',
            args: {course_id: courseId, demo: isDemo ? 1 : 0}
        }])[0].then(function(result) {
            riskData = {};
            (result.results || []).forEach(function(r) {
                riskData[r.userid] = r;
                // Update risk badge
                var el = document.getElementById('saipa-risk-badge-' + r.userid);
                if (el) { Dom.replace(el, riskBadge(r.userid)); }
                // Highlight alert button for high-risk students with Telegram linked
                var alertEl = document.getElementById('saipa-alert-btn-' + r.userid);
                if (alertEl && !alertEl.classList.contains('d-none')) {
                    if (r.risk_level === 'high') {
                        alertEl.classList.remove('btn-outline-secondary');
                        alertEl.classList.add('btn-warning');
                        alertEl.title = S.teacher_alert_btn_high;
                    } else {
                        alertEl.classList.remove('btn-warning');
                        alertEl.classList.add('btn-outline-secondary');
                        alertEl.title = S.teacher_alert_btn;
                    }
                }
            });

            // Summary
            var hi = (result.results || []).filter(function(r) { return r.risk_level === 'high'; }).length;
            var med = (result.results || []).filter(function(r) { return r.risk_level === 'medium'; }).length;
            var lo = (result.results || []).filter(function(r) { return r.risk_level === 'low'; }).length;
            var label = isDemo ? '(demo) ' : '';
            if (status) {
                status.textContent = label + '🔴 ' + hi + '  🟡 ' + med + '  🟢 ' + lo;
            }
            return result;
        }).fail(function(err) {
            Log.error('SAIPA risk error: ' + JSON.stringify(err));
            if (status) { status.textContent = S.teacher_risk_error; }
        }).always(function() {
            if (btn) { btn.disabled = false; }
            if (otherBtn) { otherBtn.disabled = false; }
            if (btn && !isDemo) { btn.textContent = '\uD83D\uDCCA ' + S.risk_button; }
            if (btn && isDemo) { btn.textContent = '\uD83C\uDFB2 ' + S.risk_demo_button; }
        });
    }

    /**
     * Calculate real (non-demo) risk scores for the course.
     */
    function loadRisk() { _doRisk(false); }

    /**
     * Load the course student list and render it in the dashboard.
     */
    function loadStudentList() {
        Ajax.call([{
            methodname: 'local_saipa_get_student_list',
            args: {course_id: courseId}
        }])[0].then(function(result) {
            var loadingEl = document.getElementById('saipa-student-list-loading');
            var listEl = document.getElementById('saipa-student-list');

            if (loadingEl) { loadingEl.style.display = 'none'; }
            if (!listEl) { return result; }

            Dom.replace(listEl);

            if (!result.students || result.students.length === 0) {
                Dom.replace(listEl, Dom.el('div', 'text-muted p-3 text-center', S.teacher_no_students));
            } else {
                result.students.forEach(function(s) {
                    listEl.appendChild(renderStudentRow(s));
                });
            }

            listEl.style.display = '';
            return result;

        }).fail(function(err) {
            Log.error('SAIPA teacher_dashboard get_student_list error: ' + JSON.stringify(err));
            var loadingEl = document.getElementById('saipa-student-list-loading');
            if (loadingEl) { loadingEl.textContent = S.teacher_students_load_error; }
        });
    }

    /**
     * Append a chat message bubble to the conversation container.
     *
     * @param {string} role Message author role ('user' or the assistant).
     * @param {string} content Message text.
     * @param {number} timecreated Unix timestamp in seconds when the message was created.
     * @param {HTMLElement} container Element the message is appended to.
     */
    function appendConvMessage(role, content, timecreated, container) {
        var row = document.createElement('div');
        row.className = 'mb-2 d-flex ' + (role === 'user' ? 'justify-content-end' : 'justify-content-start');

        var bubble = document.createElement('div');
        bubble.style.maxWidth = '78%';
        bubble.style.background = role === 'user' ? '#d1ecf1' : '#fff3cd';
        bubble.style.whiteSpace = 'pre-wrap';
        bubble.style.wordBreak = 'break-word';
        bubble.className = 'p-2 rounded';

        var meta = document.createElement('div');
        meta.className = 'text-muted small mb-1';
        meta.textContent = (role === 'user' ? S.teacher_conv_student : 'SAIPA') + ' · ' + formatDate(timecreated);

        var text = document.createElement('div');
        text.textContent = content;

        bubble.appendChild(meta);
        bubble.appendChild(text);
        row.appendChild(bubble);
        container.appendChild(row);
    }

    /**
     * Show the conversation panel and load the chat history of a student.
     *
     * @param {number} studentId Moodle user id of the student.
     * @param {string} studentName Student name shown as the panel heading.
     */
    function loadConversation(studentId, studentName) {
        document.getElementById('saipa-student-list-panel').style.display = 'none';

        var convPanel = document.getElementById('saipa-conversation-panel');
        convPanel.style.display = '';

        var heading = document.getElementById('saipa-conversation-heading');
        if (heading) { heading.textContent = studentName; }

        var msgContainer = document.getElementById('saipa-conversation-messages');
        Dom.replace(msgContainer, Dom.el('div', 'text-muted small text-center py-4', [
            Dom.el('div', 'spinner-border spinner-border-sm mr-2', null, {role: 'status'}),
            S['core/loading']
        ]));

        Ajax.call([{
            methodname: 'local_saipa_get_student_history',
            args: {course_id: courseId, student_id: studentId}
        }])[0].then(function(result) {
            Dom.replace(msgContainer);

            if (!result.messages || result.messages.length === 0) {
                Dom.replace(msgContainer, Dom.el('div', 'text-muted p-3 text-center', S.teacher_conv_empty));
                return result;
            }

            result.messages.forEach(function(msg) {
                appendConvMessage(msg.role, msg.content, msg.timecreated, msgContainer);
            });

            msgContainer.scrollTop = msgContainer.scrollHeight;
            return result;

        }).fail(function(err) {
            Log.error('SAIPA teacher_dashboard get_student_history error: ' + JSON.stringify(err));
            Dom.replace(msgContainer, Dom.el('div', 'text-danger p-3', S.teacher_conv_load_error));
        });
    }

    /**
     * Build the badge for the course RAG index status.
     *
     * @param {string} status Index status (ready, indexing, pending or error).
     * @returns {HTMLElement} Badge element.
     */
    function indexStatusBadge(status) {
        var map = {
            ready:    {cls: 'badge-success', text: '✓ ' + S.index_status_ready},
            indexing: {cls: 'badge-info', text: '⟳ ' + S.index_status_indexing},
            pending:  {cls: 'badge-secondary', text: '— ' + S.index_status_pending},
            error:    {cls: 'badge-danger', text: '✗ ' + S.index_status_error},
        };
        var cfg = map[status] || {cls: 'badge-light', text: status};
        return Dom.el('span', 'badge ' + cfg.cls, cfg.text);
    }

    /**
     * Load the course summary KPIs and populate the summary cards.
     */
    function loadCourseSummary() {
        Ajax.call([{
            methodname: 'local_saipa_get_course_summary',
            args: {course_id: courseId}
        }])[0].then(function(r) {
            var spinner = document.getElementById('saipa-summary-spinner');
            var cards = document.getElementById('saipa-summary-cards');
            if (spinner) { spinner.style.display = 'none'; }
            if (!cards) { return r; }

            // Populate KPI cells
            var adoption = r.enrolled_students > 0
                ? Math.round(r.adoption_rate * 100) + '%' : '—';

            var el = function(id) { return document.getElementById(id); };
            if (el('ss-enrolled')) { el('ss-enrolled').textContent = r.enrolled_students; }
            if (el('ss-active')) { el('ss-active').textContent = r.active_saipa_users; }
            if (el('ss-adoption')) { el('ss-adoption').textContent = adoption; }
            if (el('ss-msgs7d')) { el('ss-msgs7d').textContent = r.messages_7d; }
            if (el('ss-alerts')) {
                el('ss-alerts').textContent = r.alerts_sent_30d
                    + (r.alerts_responded_30d ? ' / ' + r.alerts_responded_30d : '');
            }
            if (el('ss-risk')) {
                // The service returns high_risk_count (MMR-462 #19: this card read a field that does not exist).
                var hi = r.high_risk_count;
                var riskSpan = document.createElement('span');
                riskSpan.className = hi > 0 ? 'text-danger font-weight-bold' : 'text-success';
                riskSpan.textContent = hi > 0 ? hi : 0;
                el('ss-risk').textContent = '';
                el('ss-risk').appendChild(riskSpan);
            }
            if (el('ss-index')) {
                Dom.replace(el('ss-index'), indexStatusBadge(r.index_status));
            }

            cards.style.display = '';
            return r;
        }).fail(function(err) {
            Log.warn('SAIPA: could not load course summary: ' + JSON.stringify(err));
            var spinner = document.getElementById('saipa-summary-spinner');
            if (spinner) { spinner.style.display = 'none'; }
        });
    }

    /**
     * Initialise the teacher dashboard for a course.
     *
     * @param {number} cid Course id.
     */
    function init(cid) {
        courseId = cid;

        var backBtn = document.getElementById('saipa-back-btn');
        if (backBtn) {
            backBtn.addEventListener('click', function() {
                document.getElementById('saipa-conversation-panel').style.display = 'none';
                document.getElementById('saipa-student-list-panel').style.display = '';
            });
        }

        var riskBtn = document.getElementById('saipa-risk-btn');
        if (riskBtn) { riskBtn.addEventListener('click', loadRisk); }

        var demoBtn = document.getElementById('saipa-demo-btn');
        if (demoBtn) { demoBtn.addEventListener('click', function() { _doRisk(true); }); }

        // Render the summary and student list once the UI strings are available.
        loadStrings().then(function() {
            loadCourseSummary();
            loadStudentList();
            return null;
        }).catch(Log.error);
        Log.debug('SAIPA: teacher dashboard initialised for course ' + courseId);
    }

    return {init: init};
});
