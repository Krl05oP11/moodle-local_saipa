// This file is part of Moodle - http://moodle.org/
//
// SAIPA Teacher Dashboard AMD module.
// Loads student list + conversation viewer via web services.

/**
 * @module     local_saipa/teacher_dashboard
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/log'], function(Ajax, Log) {

    'use strict';

    var courseId;

    // Keyed by userid → {score, risk_level, factors}
    var riskData = {};

    // Keyed by userid → full student object from get_student_list
    var studentData = {};

    function formatDate(ts) {
        if (!ts) { return ''; }
        return new Date(ts * 1000).toLocaleString('es-AR', { dateStyle: 'short', timeStyle: 'short' });
    }

    function riskBadgeHtml(userid) {
        var r = riskData[userid];
        if (!r) { return ''; }
        var map = {
            low:    { cls: 'badge-success',  icon: '🟢' },
            medium: { cls: 'badge-warning',  icon: '🟡' },
            high:   { cls: 'badge-danger',   icon: '🔴' },
        };
        var cfg = map[r.risk_level] || map.medium;
        return '<span class="badge ' + cfg.cls + ' badge-pill ml-1" title="Riesgo ' + r.risk_level + ': ' + (r.score * 100).toFixed(0) + '%">'
            + cfg.icon + ' ' + (r.score * 100).toFixed(0) + '%</span>';
    }

    function alertEngagementHtml(student) {
        if (!student.last_alert_sent) { return ''; }

        var parts = [];

        // Alert sent indicator
        parts.push('<span title="Alerta enviada el ' + formatDate(student.last_alert_sent) + '" '
            + 'style="color:#6c757d;">📨 ' + formatDate(student.last_alert_sent) + '</span>');

        if (student.alert_responded) {
            var delay = student.alert_response_delay_min;
            var delayStr = delay < 60
                ? delay + ' min'
                : Math.round(delay / 60) + 'h';
            parts.push('<span class="text-success" title="Respondió al bot en ' + delayStr + '">💬 ' + delayStr + '</span>');
        } else {
            parts.push('<span class="text-warning" title="Todavía no respondió al bot">⏳ Sin respuesta</span>');
        }

        if (student.moodle_accessed_after_alert) {
            parts.push('<span class="text-success" title="Ingresó a Moodle después de la alerta">✅ Accedió a Moodle</span>');
        }

        return parts.join(' · ');
    }

    function sendAlert(userid, fullname, btn) {
        var msg = prompt('Mensaje para ' + fullname + ' (dejá vacío para el mensaje por defecto):', '');
        if (msg === null) { return; } // Cancelado

        btn.disabled = true;
        btn.textContent = '…';

        Ajax.call([{
            methodname: 'local_saipa_send_telegram_alert',
            args: { student_id: userid, course_id: courseId, message: msg }
        }])[0].then(function(result) {
            if (result.sent) {
                btn.textContent = '✓';
                btn.classList.replace('btn-warning', 'btn-success');
                // Update engagement indicator to show alert was just sent.
                var engEl = document.getElementById('saipa-eng-' + userid);
                var now   = Math.floor(Date.now() / 1000);
                if (engEl) {
                    // Simulate a fresh student record with alert_sent but not responded.
                    engEl.innerHTML = alertEngagementHtml({
                        last_alert_sent:             now,
                        alert_responded:             false,
                        alert_response_delay_min:    0,
                        moodle_accessed_after_alert: false,
                    });
                }
                setTimeout(function() {
                    btn.textContent = '📨';
                    btn.classList.replace('btn-success', 'btn-warning');
                    btn.disabled = false;
                }, 3000);
            } else {
                alert('No se pudo enviar: ' + result.error);
                btn.textContent = '📨';
                btn.disabled = false;
            }
            return result;
        }).fail(function(err) {
            Log.error('SAIPA send_telegram_alert error: ' + JSON.stringify(err));
            alert('Error al enviar alerta.');
            btn.textContent = '📨';
            btn.disabled = false;
        });
    }

    function renderStudentRow(student) {
        var li = document.createElement('a');
        li.href      = '#';
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
        riskSpan.id        = 'saipa-risk-badge-' + student.userid;
        riskSpan.innerHTML = riskBadgeHtml(student.userid);
        nameRow.appendChild(riskSpan);

        // Telegram alert button — shown for linked students, highlighted when high risk
        var alertBtn = document.createElement('button');
        alertBtn.id        = 'saipa-alert-btn-' + student.userid;
        alertBtn.type      = 'button';
        alertBtn.className = 'btn btn-sm ml-2 ' + (student.telegram_linked ? 'btn-outline-secondary' : 'd-none');
        alertBtn.title     = student.telegram_linked ? 'Enviar alerta por Telegram' : '';
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
            : 'Sin actividad en chat';
        meta.appendChild(activitySpan);

        // Alert engagement indicator
        if (student.last_alert_sent > 0) {
            var engSpan = document.createElement('span');
            engSpan.id        = 'saipa-eng-' + student.userid;
            engSpan.className = 'ml-2';
            engSpan.innerHTML = alertEngagementHtml(student);
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
            badge.className   = 'badge badge-primary badge-pill';
            badge.textContent = student.message_count + ' msgs';
        } else {
            badge.className   = 'badge badge-light badge-pill text-muted';
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

    function _doRisk(isDemo) {
        var btn       = document.getElementById(isDemo ? 'saipa-demo-btn' : 'saipa-risk-btn');
        var otherBtn  = document.getElementById(isDemo ? 'saipa-risk-btn' : 'saipa-demo-btn');
        var status    = document.getElementById('saipa-risk-status');

        if (btn)      { btn.disabled = true; btn.textContent = '…'; }
        if (otherBtn) { otherBtn.disabled = true; }
        if (status)   { status.textContent = isDemo ? 'Simulando…' : 'Calculando…'; }

        Ajax.call([{
            methodname: 'local_saipa_get_course_risk',
            args: { course_id: courseId, demo: isDemo ? 1 : 0 }
        }])[0].then(function(result) {
            riskData = {};
            (result.results || []).forEach(function(r) {
                riskData[r.userid] = r;
                // Update risk badge
                var el = document.getElementById('saipa-risk-badge-' + r.userid);
                if (el) { el.innerHTML = riskBadgeHtml(r.userid); }
                // Highlight alert button for high-risk students with Telegram linked
                var alertEl = document.getElementById('saipa-alert-btn-' + r.userid);
                if (alertEl && !alertEl.classList.contains('d-none')) {
                    if (r.risk_level === 'high') {
                        alertEl.classList.remove('btn-outline-secondary');
                        alertEl.classList.add('btn-warning');
                        alertEl.title = '⚠️ Alto riesgo — Enviar alerta por Telegram';
                    } else {
                        alertEl.classList.remove('btn-warning');
                        alertEl.classList.add('btn-outline-secondary');
                        alertEl.title = 'Enviar alerta por Telegram';
                    }
                }
            });

            // Summary
            var hi  = (result.results || []).filter(function(r) { return r.risk_level === 'high';   }).length;
            var med = (result.results || []).filter(function(r) { return r.risk_level === 'medium'; }).length;
            var lo  = (result.results || []).filter(function(r) { return r.risk_level === 'low';    }).length;
            var label = isDemo ? '(demo) ' : '';
            if (status) {
                status.textContent = label + '🔴 ' + hi + '  🟡 ' + med + '  🟢 ' + lo;
            }
            return result;
        }).fail(function(err) {
            Log.error('SAIPA risk error: ' + JSON.stringify(err));
            if (status) { status.textContent = 'Error al calcular riesgo'; }
        }).always(function() {
            if (btn)      { btn.disabled = false; }
            if (otherBtn) { otherBtn.disabled = false; }
            if (btn && !isDemo) { btn.innerHTML = '&#128202; Calcular riesgo'; }
            if (btn &&  isDemo) { btn.innerHTML = '&#127922; Simular'; }
        });
    }

    function loadRisk() { _doRisk(false); }

    function loadStudentList() {
        Ajax.call([{
            methodname: 'local_saipa_get_student_list',
            args: { course_id: courseId }
        }])[0].then(function(result) {
            var loadingEl = document.getElementById('saipa-student-list-loading');
            var listEl    = document.getElementById('saipa-student-list');

            if (loadingEl) { loadingEl.style.display = 'none'; }
            if (!listEl)   { return result; }

            listEl.innerHTML = '';

            if (!result.students || result.students.length === 0) {
                listEl.innerHTML = '<div class="text-muted p-3 text-center">No hay estudiantes inscriptos.</div>';
            } else {
                result.students.forEach(function(s) {
                    listEl.appendChild(renderStudentRow(s));
                });
            }

            listEl.style.display = '';
            return result;

            // Store student data for later use (e.g. after alert sent)
            result.students.forEach(function(s) {
                studentData[s.userid] = s;
            });
            return result;

        }).fail(function(err) {
            Log.error('SAIPA teacher_dashboard get_student_list error: ' + JSON.stringify(err));
            var loadingEl = document.getElementById('saipa-student-list-loading');
            if (loadingEl) { loadingEl.textContent = 'Error al cargar estudiantes.'; }
        });
    }

    function appendConvMessage(role, content, timecreated, container) {
        var row = document.createElement('div');
        row.className = 'mb-2 d-flex ' + (role === 'user' ? 'justify-content-end' : 'justify-content-start');

        var bubble = document.createElement('div');
        bubble.style.maxWidth   = '78%';
        bubble.style.background = role === 'user' ? '#d1ecf1' : '#fff3cd';
        bubble.style.whiteSpace = 'pre-wrap';
        bubble.style.wordBreak  = 'break-word';
        bubble.className = 'p-2 rounded';

        var meta = document.createElement('div');
        meta.className   = 'text-muted small mb-1';
        meta.textContent = (role === 'user' ? 'Estudiante' : 'SAIPA') + ' · ' + formatDate(timecreated);

        var text = document.createElement('div');
        text.textContent = content;

        bubble.appendChild(meta);
        bubble.appendChild(text);
        row.appendChild(bubble);
        container.appendChild(row);
    }

    function loadConversation(studentId, studentName) {
        document.getElementById('saipa-student-list-panel').style.display = 'none';

        var convPanel = document.getElementById('saipa-conversation-panel');
        convPanel.style.display = '';

        var heading = document.getElementById('saipa-conversation-heading');
        if (heading) { heading.textContent = studentName; }

        var msgContainer = document.getElementById('saipa-conversation-messages');
        msgContainer.innerHTML = '<div class="text-muted small text-center py-4">'
            + '<div class="spinner-border spinner-border-sm mr-2" role="status"></div>Cargando...</div>';

        Ajax.call([{
            methodname: 'local_saipa_get_student_history',
            args: { course_id: courseId, student_id: studentId }
        }])[0].then(function(result) {
            msgContainer.innerHTML = '';

            if (!result.messages || result.messages.length === 0) {
                msgContainer.innerHTML = '<div class="text-muted p-3 text-center">Este estudiante aún no inició una conversación.</div>';
                return result;
            }

            result.messages.forEach(function(msg) {
                appendConvMessage(msg.role, msg.content, msg.timecreated, msgContainer);
            });

            msgContainer.scrollTop = msgContainer.scrollHeight;
            return result;

        }).fail(function(err) {
            Log.error('SAIPA teacher_dashboard get_student_history error: ' + JSON.stringify(err));
            msgContainer.innerHTML = '<div class="text-danger p-3">Error al cargar la conversación.</div>';
        });
    }

    function indexStatusBadge(status) {
        var map = {
            ready:      '<span class="badge badge-success">✓ ready</span>',
            indexing:   '<span class="badge badge-info">⟳ indexing</span>',
            pending:    '<span class="badge badge-secondary">— pending</span>',
            error:      '<span class="badge badge-danger">✗ error</span>',
        };
        return map[status] || '<span class="badge badge-light">' + status + '</span>';
    }

    function loadCourseSummary() {
        Ajax.call([{
            methodname: 'local_saipa_get_course_summary',
            args: { course_id: courseId }
        }])[0].then(function(r) {
            var spinner = document.getElementById('saipa-summary-spinner');
            var cards   = document.getElementById('saipa-summary-cards');
            if (spinner) { spinner.style.display = 'none'; }
            if (!cards)  { return r; }

            // Populate KPI cells
            var adoption = r.enrolled_students > 0
                ? Math.round(r.adoption_rate * 100) + '%' : '—';

            var el = function(id) { return document.getElementById(id); };
            if (el('ss-enrolled')) { el('ss-enrolled').textContent = r.enrolled_students; }
            if (el('ss-active'))   { el('ss-active').textContent   = r.active_saipa_users; }
            if (el('ss-adoption')) { el('ss-adoption').textContent  = adoption; }
            if (el('ss-msgs7d'))   { el('ss-msgs7d').textContent   = r.messages_7d; }
            if (el('ss-alerts')) {
                el('ss-alerts').textContent = r.alerts_sent_30d
                    + (r.alerts_responded_30d ? ' / ' + r.alerts_responded_30d : '');
            }
            if (el('ss-risk')) {
                var hi = r.high_risk;
                var riskHtml = hi > 0
                    ? '<span class="text-danger font-weight-bold">' + hi + '</span>'
                    : '<span class="text-success">0</span>';
                el('ss-risk').innerHTML = riskHtml;
            }
            if (el('ss-index')) {
                el('ss-index').innerHTML = indexStatusBadge(r.index_status);
            }

            cards.style.display = '';
            return r;
        }).fail(function(err) {
            Log.warn('SAIPA: could not load course summary: ' + JSON.stringify(err));
            var spinner = document.getElementById('saipa-summary-spinner');
            if (spinner) { spinner.style.display = 'none'; }
        });
    }

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

        loadCourseSummary();
        loadStudentList();
        Log.debug('SAIPA: teacher dashboard initialised for course ' + courseId);
    }

    return { init: init };
});
