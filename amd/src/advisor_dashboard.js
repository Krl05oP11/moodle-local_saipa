// This file is part of Moodle - http://moodle.org/
//
// SAIPA — Advisor Dashboard AMD module.
// 5-tab lazy-loading dashboard for advisors/admins.
// Charts use Canvas 2D native API (no external dependencies).
// Heatmap uses CSS grid (no external dependencies).

/**
 * @module     local_saipa/advisor_dashboard
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/log', 'jquery'], function(Ajax, Log, $) {

    'use strict';

    var loaded   = {};   // Which tabs have been loaded already.
    var period   = '30d';
    var isManager = false;

    // ── Helpers ────────────────────────────────────────────────────────────────

    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function fmtDate(ts) {
        if (!ts) { return '—'; }
        return new Date(ts * 1000).toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: '2-digit' });
    }

    function pct(val) {
        return Math.round((val || 0) * 100) + '%';
    }

    function showSpinner(tabId) {
        var el = document.getElementById('tab-' + tabId + '-loading');
        var ct = document.getElementById('tab-' + tabId + '-content');
        var er = document.getElementById('tab-' + tabId + '-error');
        if (el) { el.style.display = ''; }
        if (ct) { ct.style.display = 'none'; }
        if (er) { er.style.display = 'none'; }
    }

    function showContent(tabId) {
        var el = document.getElementById('tab-' + tabId + '-loading');
        var ct = document.getElementById('tab-' + tabId + '-content');
        if (el) { el.style.display = 'none'; }
        if (ct) { ct.style.display = ''; }
    }

    function showError(tabId, msg) {
        var el = document.getElementById('tab-' + tabId + '-loading');
        var er = document.getElementById('tab-' + tabId + '-error');
        if (el) { el.style.display = 'none'; }
        if (er) { er.textContent = msg; er.style.display = ''; }
    }

    function globalSpinner(visible) {
        var sp = document.getElementById('saipa-adv-spinner');
        if (sp) { sp.style.display = visible ? '' : 'none'; }
    }

    // ── Simple sparkline using Canvas 2D ──────────────────────────────────────

    function drawSparkline(canvasId, labels, data, label, color) {
        var canvas = document.getElementById(canvasId);
        if (!canvas) { return; }
        if (!data || data.length === 0) {
            // No data yet — show message below the canvas.
            var msg = canvas.parentNode && canvas.parentNode.querySelector('.spark-no-data');
            if (msg) { msg.style.display = ''; }
            return;
        }

        canvas.width = canvas.offsetWidth || 400;   // Sync pixel buffer to CSS width.
        var ctx = canvas.getContext('2d');
        var w   = canvas.width;
        var h   = canvas.height;
        var pad = 4;

        ctx.clearRect(0, 0, w, h);

        var max  = Math.max.apply(null, data) || 1;
        var step = (w - pad * 2) / Math.max(data.length - 1, 1);

        ctx.beginPath();
        ctx.strokeStyle = color || '#007bff';
        ctx.lineWidth   = 2;
        ctx.lineJoin    = 'round';
        ctx.lineCap     = 'round';

        data.forEach(function(val, i) {
            var x = pad + i * step;
            var y = pad + (1 - val / max) * (h - pad * 2);
            if (i === 0) { ctx.moveTo(x, y); } else { ctx.lineTo(x, y); }
        });
        ctx.stroke();
    }

    // ── TAB 1 — Institution summary ────────────────────────────────────────────

    function loadSummary() {
        showSpinner('summary');
        globalSpinner(true);

        Ajax.call([{
            methodname: 'local_saipa_get_institution_summary',
            args: { period: period }
        }])[0].then(function(r) {
            var el = function(id) { return document.getElementById(id); };

            if (el('kpi-active-courses')) { el('kpi-active-courses').textContent = r.active_courses; }
            if (el('kpi-enrolled'))       { el('kpi-enrolled').textContent       = r.total_enrolled; }
            if (el('kpi-saipa-users'))    { el('kpi-saipa-users').textContent    = r.total_saipa_users; }
            if (el('kpi-adoption'))       { el('kpi-adoption').textContent       = pct(r.adoption_rate); }
            if (el('kpi-messages'))       { el('kpi-messages').textContent       = r.total_messages; }
            if (el('kpi-feedback'))       { el('kpi-feedback').textContent       = pct(r.positive_feedback_pct); }
            if (el('kpi-alerts-sent'))    { el('kpi-alerts-sent').textContent    = r.alerts_sent; }
            if (el('kpi-alerts-resp'))    { el('kpi-alerts-resp').textContent    = r.alerts_responded; }
            if (el('kpi-resp-rate'))      { el('kpi-resp-rate').textContent      = pct(r.alert_response_rate); }

            // Sparklines
            var msgLabels  = (r.trend_messages  || []).map(function(d) { return fmtDate(d.date); });
            var msgData    = (r.trend_messages  || []).map(function(d) { return d.count; });
            var usrLabels  = (r.trend_new_users || []).map(function(d) { return fmtDate(d.date); });
            var usrData    = (r.trend_new_users || []).map(function(d) { return d.count; });

            showContent('summary');   // Must come BEFORE canvas draws (offsetWidth = 0 on hidden divs).
            drawSparkline('spark-messages', msgLabels, msgData, 'Mensajes', '#007bff');
            drawSparkline('spark-users',    usrLabels, usrData, 'Usuarios', '#28a745');
            return r;
        }).fail(function(err) {
            Log.error('SAIPA advisor summary error: ' + JSON.stringify(err));
            var msg = (err && err.message) ? err.message : JSON.stringify(err);
            showError('summary', 'Error: ' + msg);
        }).always(function() {
            globalSpinner(false);
        });
    }

    // ── TAB 2 — Risk ROI ───────────────────────────────────────────────────────

    function buildFunnel(f) {
        var container = document.getElementById('adv-funnel');
        if (!container) { return; }

        var steps = [
            { label: 'Evaluados',    value: f.evaluated,       color: '#6c757d' },
            { label: 'Riesgo alto',  value: f.identified_high, color: '#dc3545' },
            { label: 'Alertados',    value: f.received_alert,  color: '#fd7e14' },
            { label: 'Respondieron', value: f.responded_alert, color: '#ffc107' },
            { label: 'Re-ingresaron',value: f.accessed_after,  color: '#28a745' },
        ];

        var maxVal = Math.max(1, f.evaluated);
        container.innerHTML = '';

        steps.forEach(function(s) {
            var widthPct = Math.round((s.value / maxVal) * 100);
            var col = document.createElement('div');
            col.className = 'flex-fill mx-1 d-flex flex-column align-items-center';

            var bar = document.createElement('div');
            bar.style.background  = s.color;
            bar.style.width       = '100%';
            bar.style.height      = Math.max(20, widthPct) + 'px';
            bar.style.borderRadius = '4px 4px 0 0';
            bar.title = s.label + ': ' + s.value;

            var lbl = document.createElement('div');
            lbl.className = 'text-muted mt-1 text-center';
            lbl.style.fontSize = '0.7rem';
            lbl.innerHTML = '<strong>' + s.value + '</strong><br/>' + s.label;

            col.appendChild(bar);
            col.appendChild(lbl);
            container.appendChild(col);
        });
    }

    function buildRiskTrend(rows) {
        var canvas = document.getElementById('risk-trend-chart');
        if (!canvas || !rows || rows.length === 0) { return; }

        canvas.width = canvas.offsetWidth || 400;   // Sync pixel buffer to CSS width.
        var ctx  = canvas.getContext('2d');
        var w    = canvas.width;
        var h    = canvas.height;
        var padX = 32;
        var padY = 24;
        var iw   = w - padX * 2;
        var ih   = h - padY * 2;

        ctx.clearRect(0, 0, w, h);

        var series = [
            { data: rows.map(function(r) { return r.pct_high   * 100; }), color: '#dc3545', label: 'Alto' },
            { data: rows.map(function(r) { return r.pct_medium * 100; }), color: '#ffc107', label: 'Medio' },
            { data: rows.map(function(r) { return r.pct_low    * 100; }), color: '#28a745', label: 'Bajo' }
        ];
        var stepX = iw / Math.max(rows.length - 1, 1);

        // Grid lines + Y-axis labels.
        ctx.font = '10px sans-serif';
        [0, 25, 50, 75, 100].forEach(function(v) {
            var y = padY + ih - (v / 100) * ih;
            ctx.fillStyle = '#888';
            ctx.textAlign = 'right';
            ctx.fillText(v + '%', padX - 4, y + 4);
            ctx.beginPath();
            ctx.strokeStyle = '#e0e0e0';
            ctx.lineWidth = 0.5;
            ctx.moveTo(padX, y);
            ctx.lineTo(w - padX, y);
            ctx.stroke();
        });

        // X-axis: first and last label only.
        var labels = rows.map(function(r) { return fmtDate(r.week); });
        ctx.fillStyle = '#888';
        ctx.textAlign = 'center';
        if (labels.length > 0) {
            ctx.fillText(labels[0], padX, h - 4);
            if (labels.length > 1) { ctx.fillText(labels[labels.length - 1], w - padX, h - 4); }
        }

        // Lines.
        series.forEach(function(s) {
            ctx.beginPath();
            ctx.strokeStyle = s.color;
            ctx.lineWidth   = 2;
            ctx.lineJoin    = 'round';
            s.data.forEach(function(val, i) {
                var x = padX + i * stepX;
                var y = padY + ih - (val / 100) * ih;
                if (i === 0) { ctx.moveTo(x, y); } else { ctx.lineTo(x, y); }
            });
            ctx.stroke();
        });

        // Legend.
        var lx = padX;
        series.forEach(function(s) {
            ctx.fillStyle = s.color;
            ctx.fillRect(lx, 4, 12, 8);
            ctx.fillStyle = '#444';
            ctx.font = '10px sans-serif';
            ctx.textAlign = 'left';
            ctx.fillText(s.label, lx + 16, 12);
            lx += 56;
        });
    }

    function buildEffectiveness(rows) {
        var tbody = document.getElementById('effectiveness-tbody');
        var empty = document.getElementById('effectiveness-empty');
        if (!tbody) { return; }

        tbody.innerHTML = '';

        if (!rows || rows.length === 0) {
            if (empty) { empty.style.display = ''; }
            return;
        }
        if (empty) { empty.style.display = 'none'; }

        rows.forEach(function(r) {
            var improved = r.improved
                ? '<span class="text-success">&#9650; Sí</span>'
                : '<span class="text-danger">&#9660; No</span>';
            var tr = document.createElement('tr');
            tr.innerHTML = ''
                + '<td>' + r.userid + '</td>'
                + '<td>' + r.courseid + '</td>'
                + '<td>' + fmtDate(r.alert_time) + '</td>'
                + '<td class="text-right">' + (r.risk_at_alert  * 100).toFixed(1) + '%</td>'
                + '<td class="text-right">' + (r.risk_14d_later * 100).toFixed(1) + '%</td>'
                + '<td class="text-center">' + improved + '</td>';
            tbody.appendChild(tr);
        });
    }

    function loadRisk() {
        showSpinner('risk');
        globalSpinner(true);

        Ajax.call([{
            methodname: 'local_saipa_get_risk_dashboard',
            args: { period: period, courseid: 0 }
        }])[0].then(function(r) {
            buildFunnel(r.funnel);
            buildEffectiveness(r.intervention_effectiveness);
            showContent('risk');   // Must come BEFORE canvas draws (offsetWidth = 0 on hidden divs).
            buildRiskTrend(r.risk_trend);
            return r;
        }).fail(function(err) {
            Log.error('SAIPA advisor risk error: ' + JSON.stringify(err));
            showError('risk', 'Error loading risk data.');
        }).always(function() {
            globalSpinner(false);
        });
    }

    // ── TAB 3 — Engagement ─────────────────────────────────────────────────────

    function buildHeatmap(rows) {
        var container = document.getElementById('eng-heatmap');
        if (!container) { return; }

        // Index: [dow][hour] = count
        var grid = {};
        var maxVal = 1;
        (rows || []).forEach(function(r) {
            if (!grid[r.day_of_week]) { grid[r.day_of_week] = {}; }
            grid[r.day_of_week][r.hour] = r.count;
            if (r.count > maxVal) { maxVal = r.count; }
        });

        // Argentine week: Dom=0, Lun=1 ... Sáb=6
        // Backend sends ISO DOW: 0=Lun...6=Dom → remap visual = (iso+1)%7
        var days = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
        var visualGrid = {};
        Object.keys(grid).forEach(function(isoDow) {
            var vis = (parseInt(isoDow, 10) + 1) % 7;
            visualGrid[vis] = grid[isoDow];
        });
        container.innerHTML = '';

        // Header row (hour labels every 3h, with larger font)
        var emptyHeader = document.createElement('div');
        emptyHeader.className = 'hm-header';
        container.appendChild(emptyHeader);

        for (var h = 0; h < 24; h++) {
            var hdr = document.createElement('div');
            hdr.className      = 'hm-header';
            hdr.style.fontSize = '12px';
            hdr.textContent    = h % 3 === 0 ? String(h).padStart(2, '0') : '';
            container.appendChild(hdr);
        }

        // Data rows (Dom → Sáb)
        for (var d = 0; d < 7; d++) {
            var rowLabel = document.createElement('div');
            rowLabel.className      = 'hm-row-label';
            rowLabel.style.fontSize = '12px';
            rowLabel.textContent    = days[d];
            container.appendChild(rowLabel);

            for (var col = 0; col < 24; col++) {
                var count = (visualGrid[d] && visualGrid[d][col]) ? visualGrid[d][col] : 0;
                var cell = document.createElement('div');
                cell.className     = 'hm-cell';
                cell.style.background = count === 0
                    ? '#f0f0f0'
                    : 'rgba(0, 123, 255, ' + (count / maxVal).toFixed(2) + ')';
                cell.title = days[d] + ' ' + String(col).padStart(2, '0') + ':00 — ' + count + ' mensajes';
                container.appendChild(cell);
            }
        }
    }

    function buildDepthChart(depths) {
        var canvas = document.getElementById('eng-depth-chart');
        if (!canvas || !depths || depths.length === 0) { return; }

        canvas.width = canvas.offsetWidth || 400;   // Sync pixel buffer to CSS width.
        var ctx  = canvas.getContext('2d');
        var w    = canvas.width;
        var h    = canvas.height;
        var padX = 36;
        var padY = 12;
        var iw   = w - padX * 2;
        var ih   = h - padY * 2 - 18; // 18px for x-axis labels.

        ctx.clearRect(0, 0, w, h);

        var data   = depths.map(function(d) { return d.count; });
        var labels = depths.map(function(d) { return d.bucket + ' msgs'; });
        var max    = Math.max.apply(null, data) || 1;
        var gap    = iw / depths.length;
        var barW   = gap * 0.65;

        // Y-axis grid + labels.
        ctx.font = '10px sans-serif';
        [0, Math.round(max / 2), max].forEach(function(v) {
            var y = padY + ih - (v / max) * ih;
            ctx.fillStyle = '#888';
            ctx.textAlign = 'right';
            ctx.fillText(v, padX - 4, y + 4);
            ctx.beginPath();
            ctx.strokeStyle = '#e0e0e0';
            ctx.lineWidth = 0.5;
            ctx.moveTo(padX, y);
            ctx.lineTo(w - padX, y);
            ctx.stroke();
        });

        // Bars + x-axis labels.
        data.forEach(function(val, i) {
            var x    = padX + i * gap + (gap - barW) / 2;
            var barH = (val / max) * ih;
            var y    = padY + ih - barH;
            ctx.fillStyle = '#17a2b8';
            ctx.fillRect(x, y, barW, barH);
            ctx.fillStyle = '#444';
            ctx.textAlign = 'center';
            ctx.fillText(labels[i], x + barW / 2, h - 4);
        });
    }

    function loadEngagement() {
        showSpinner('engagement');
        globalSpinner(true);

        Ajax.call([{
            methodname: 'local_saipa_get_engagement_stats',
            args: { period: period }
        }])[0].then(function(r) {
            // Per-course table
            var tbody = document.getElementById('eng-course-tbody');
            if (tbody) {
                tbody.innerHTML = '';
                (r.course_usage || []).forEach(function(c) {
                    var fb = c.feedback_ratio >= 0 ? pct(c.feedback_ratio) : '—';
                    var tr = document.createElement('tr');
                    tr.innerHTML = ''
                        + '<td>' + escapeHtml(c.coursename) + '</td>'
                        + '<td class="text-right">' + c.message_count + '</td>'
                        + '<td class="text-right">' + c.unique_users + '</td>'
                        + '<td class="text-right">' + c.avg_session_msgs.toFixed(1) + '</td>'
                        + '<td class="text-right">' + fb + '</td>';
                    tbody.appendChild(tr);
                });
            }

            buildHeatmap(r.hourly_heatmap);
            showContent('engagement');   // Must come BEFORE canvas draws (offsetWidth = 0 on hidden divs).
            buildDepthChart(r.session_depth);
            return r;
        }).fail(function(err) {
            Log.error('SAIPA advisor engagement error: ' + JSON.stringify(err));
            showError('engagement', 'Error loading engagement data.');
        }).always(function() {
            globalSpinner(false);
        });
    }

    // ── TAB 4 — Course controls ─────────────────────────────────────────────────

    function saveCourseSetting(courseid, field, value) {
        var statusEl = document.getElementById('ctrl-save-status');
        var args = { courseid: courseid };
        args[field] = value ? 1 : 0;

        Ajax.call([{
            methodname: 'local_saipa_set_course_settings',
            args: args
        }])[0].then(function(r) {
            if (statusEl) {
                statusEl.textContent = r.success ? 'Guardado.' : ('Error: ' + r.message);
                setTimeout(function() { statusEl.textContent = ''; }, 2000);
            }
            return r;
        }).fail(function(err) {
            Log.error('SAIPA set_course_settings error: ' + JSON.stringify(err));
            if (statusEl) { statusEl.textContent = 'Error al guardar.'; }
        });
    }

    function buildToggle(courseid, field, currentVal) {
        var chk = document.createElement('input');
        chk.type      = 'checkbox';
        chk.className = 'saipa-toggle';
        chk.checked   = !!currentVal;
        chk.dataset.courseid = courseid;
        chk.dataset.field    = field;
        chk.addEventListener('change', function() {
            saveCourseSetting(parseInt(chk.dataset.courseid, 10), chk.dataset.field, chk.checked);
        });
        return chk;
    }

    function buildIndexBtn(courseid) {
        var btn = document.createElement('button');
        btn.type      = 'button';
        btn.className = 'btn btn-sm btn-outline-secondary';
        btn.textContent = '⟳';
        btn.title = 'Re-index course ' + courseid;
        btn.addEventListener('click', function() {
            btn.disabled    = true;
            btn.textContent = '…';
            Ajax.call([{
                methodname: 'local_saipa_index_course',
                args: { course_id: courseid }
            }])[0].then(function(r) {
                btn.textContent = r.status === 'ok' ? '✓' : '✗';
                setTimeout(function() { btn.textContent = '⟳'; btn.disabled = false; }, 3000);
                return r;
            }).fail(function() {
                btn.textContent = '✗';
                btn.disabled = false;
            });
        });
        return btn;
    }

    function setAllToggle(enable) {
        var toggles = document.querySelectorAll('#ctrl-tbody .saipa-toggle');
        toggles.forEach(function(chk) {
            if (chk.checked !== enable) {
                chk.checked = enable;
                saveCourseSetting(
                    parseInt(chk.dataset.courseid, 10),
                    chk.dataset.field,
                    enable
                );
            }
        });
    }

    function loadControls() {
        showSpinner('controls');
        globalSpinner(true);

        Ajax.call([{
            methodname: 'local_saipa_get_course_settings',
            args: {}
        }])[0].then(function(r) {
            var tbody = document.getElementById('ctrl-tbody');
            if (!tbody) { showContent('controls'); return r; }
            tbody.innerHTML = '';

            (r.courses || []).forEach(function(c) {
                var tr = document.createElement('tr');

                var nameTd = document.createElement('td');
                nameTd.textContent = c.coursename;

                var fields = ['saipa_enabled', 'chat_enabled', 'risk_enabled', 'alerts_enabled', 'rag_enabled'];
                var tds = [nameTd];

                fields.forEach(function(f) {
                    var td = document.createElement('td');
                    td.className = 'text-center';
                    td.appendChild(buildToggle(c.courseid, f, c[f]));
                    tds.push(td);
                });

                var idxTd = document.createElement('td');
                idxTd.className = 'text-center';
                idxTd.appendChild(buildIndexBtn(c.courseid));
                tds.push(idxTd);

                tds.forEach(function(td) { tr.appendChild(td); });
                tbody.appendChild(tr);
            });

            // Bulk buttons
            var enableAll  = document.getElementById('ctrl-enable-all');
            var disableAll = document.getElementById('ctrl-disable-all');
            if (enableAll)  { enableAll.addEventListener('click',  function() { setAllToggle(true);  }); }
            if (disableAll) { disableAll.addEventListener('click', function() { setAllToggle(false); }); }

            showContent('controls');
            return r;
        }).fail(function(err) {
            Log.error('SAIPA advisor controls error: ' + JSON.stringify(err));
            showError('controls', 'Error loading course controls.');
        }).always(function() {
            globalSpinner(false);
        });
    }

    // ── TAB 5 — Institution config (managers only) ─────────────────────────────

    function loadConfig() {
        // Config values are loaded from Moodle's admin_settings page via `get_config()`.
        // For the advisor dashboard view, we re-read from what the server already set.
        // The form is pre-populated via PHP data attributes in the future; for now
        // we just show the form with defaults and let the admin submit changes.
        var spinner = document.getElementById('tab-config-loading');
        var content = document.getElementById('tab-config-content');
        if (spinner) { spinner.style.display = 'none'; }
        if (content) { content.style.display = ''; }

        var form = document.getElementById('adv-config-form');
        if (!form) { return; }

        form.addEventListener('submit', function(e) {
            e.preventDefault();

            var args = {};
            var med = parseFloat(form.querySelector('[name="risk_threshold_medium"]').value);
            var hi  = parseFloat(form.querySelector('[name="risk_threshold_high"]').value);
            var cool = parseInt(form.querySelector('[name="alert_cooldown_hours"]').value, 10);
            var ret  = parseInt(form.querySelector('[name="data_retention_days"]').value, 10);
            var tpl  = form.querySelector('[name="default_alert_template"]').value;
            var risk = form.querySelector('[name="risk_eval_enabled"]').checked ? 1 : 0;
            var rag  = form.querySelector('[name="rag_global_enabled"]').checked ? 1 : 0;

            if (!isNaN(med))  { args.risk_threshold_medium  = med;  }
            if (!isNaN(hi))   { args.risk_threshold_high    = hi;   }
            if (!isNaN(cool)) { args.alert_cooldown_hours   = cool; }
            if (!isNaN(ret))  { args.data_retention_days    = ret;  }
            args.default_alert_template = tpl;
            args.risk_eval_enabled      = risk;
            args.rag_global_enabled     = rag;

            var statusEl = document.getElementById('cfg-save-status');
            var saveBtn  = document.getElementById('cfg-save-btn');
            if (saveBtn)  { saveBtn.disabled = true; }
            if (statusEl) { statusEl.textContent = '…'; }

            Ajax.call([{
                methodname: 'local_saipa_save_institution_config',
                args: args
            }])[0].then(function(r) {
                if (statusEl) {
                    statusEl.textContent = r.success ? 'Configuración guardada.' : ('Error: ' + r.message);
                }
                return r;
            }).fail(function(err) {
                Log.error('SAIPA save_institution_config error: ' + JSON.stringify(err));
                if (statusEl) { statusEl.textContent = 'Error al guardar.'; }
            }).always(function() {
                if (saveBtn) { saveBtn.disabled = false; }
            });
        });
    }

    // ── Tab switching ──────────────────────────────────────────────────────────

    function onTabShow(tabName) {
        if (loaded[tabName]) { return; }
        loaded[tabName] = true;

        if (tabName === 'summary')    { loadSummary();    }
        if (tabName === 'risk')       { loadRisk();       }
        if (tabName === 'engagement') { loadEngagement(); }
        if (tabName === 'controls')   { loadControls();   }
        if (tabName === 'config')     { loadConfig();     }
    }

    function onPeriodChange() {
        // Reload already-visited tabs with new period.
        var reloadTabs = ['summary', 'risk', 'engagement'];
        reloadTabs.forEach(function(t) {
            if (loaded[t]) {
                loaded[t] = false;   // Allow reload.
                onTabShow(t);
            }
        });
    }

    // ── init ───────────────────────────────────────────────────────────────────

    function init() {
        var root = document.getElementById('saipa-advisor');
        if (root) { isManager = root.dataset.isManager === '1'; }

        // Period selector
        var periodEl = document.getElementById('saipa-period');
        if (periodEl) {
            periodEl.addEventListener('change', function() {
                period = periodEl.value;
                onPeriodChange();
            });
        }

        // Bootstrap tab events — must use jQuery because shown.bs.tab is a jQuery custom event.
        $('#saipa-adv-tabs a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
            onTabShow($(e.target).data('tab'));
        });

        // Load the first (active) tab immediately.
        onTabShow('summary');

        // ── Floating Assistant (FAB) ────────────────────────────────────────
        var fab      = document.getElementById('saipa-fab');
        var panel    = document.getElementById('saipa-chat-panel');
        var closeBtn = document.getElementById('saipa-chat-close');
        var sendBtn  = document.getElementById('saipa-chat-send');
        var input    = document.getElementById('saipa-chat-input');
        var messages = document.getElementById('saipa-chat-messages');

        function appendMsg(text, role) {
            if (!messages) { return; }
            var div = document.createElement('div');
            div.className = 'saipa-msg saipa-msg--' + role;
            div.textContent = text;
            messages.appendChild(div);
            messages.scrollTop = messages.scrollHeight;
        }

        function appendTyping() {
            if (!messages) { return null; }
            var div = document.createElement('div');
            div.className = 'saipa-msg saipa-msg--assistant saipa-msg--typing';
            div.textContent = '…';
            messages.appendChild(div);
            messages.scrollTop = messages.scrollHeight;
            return div;
        }

        function sendMessage() {
            if (!input) { return; }
            var text = input.value.trim();
            if (!text) { return; }
            input.value = '';
            appendMsg(text, 'user');
            var typingEl = appendTyping();
            if (sendBtn) { sendBtn.disabled = true; }

            Ajax.call([{
                methodname: 'local_saipa_admin_chat',
                args: { message: text, context: 'advisor' }
            }])[0].then(function(r) {
                if (typingEl && typingEl.parentNode) { typingEl.parentNode.removeChild(typingEl); }
                appendMsg(r.reply || 'Sin respuesta.', 'assistant');
                return r;
            }).fail(function(err) {
                if (typingEl && typingEl.parentNode) { typingEl.parentNode.removeChild(typingEl); }
                appendMsg('Error al conectar con el asistente.', 'assistant');
                Log.error('SAIPA FAB chat error: ' + JSON.stringify(err));
            }).always(function() {
                if (sendBtn) { sendBtn.disabled = false; }
            });
        }

        // ── FAB drag-to-reposition ──────────────────────────────────────
        var STORAGE_KEY = 'saipa_fab_pos';
        var isDragging  = false;
        var dragMoved   = false;
        var dragStartX, dragStartY, fabStartLeft, fabStartTop;

        function getFabRect() {
            return fab ? fab.getBoundingClientRect() : null;
        }

        function applyFabPos(left, top) {
            // Clamp within viewport.
            var w = window.innerWidth, h = window.innerHeight;
            var fw = fab.offsetWidth, fh = fab.offsetHeight;
            left = Math.max(0, Math.min(left, w - fw));
            top  = Math.max(0, Math.min(top,  h - fh));
            fab.style.left   = left + 'px';
            fab.style.top    = top  + 'px';
            fab.style.right  = 'auto';
            fab.style.bottom = 'auto';
            // Reposition panel above the button.
            if (panel) {
                panel.style.left   = Math.max(0, Math.min(left + fw - 360, w - 364)) + 'px';
                panel.style.top    = Math.max(0, top - 510) + 'px';
                panel.style.right  = 'auto';
                panel.style.bottom = 'auto';
            }
        }

        function saveFabPos(left, top) {
            try { localStorage.setItem(STORAGE_KEY, JSON.stringify({l: left, t: top})); } catch(e) {}
        }

        function restoreFabPos() {
            try {
                var raw = localStorage.getItem(STORAGE_KEY);
                if (!raw) { return false; }
                var pos = JSON.parse(raw);
                applyFabPos(pos.l, pos.t);
                return true;
            } catch(e) { return false; }
        }

        if (fab) {
            restoreFabPos();

            fab.addEventListener('mousedown', function(e) {
                if (e.button !== 0) { return; }
                isDragging = true;
                dragMoved  = false;
                dragStartX = e.clientX;
                dragStartY = e.clientY;
                var rect   = getFabRect();
                fabStartLeft = rect.left;
                fabStartTop  = rect.top;
                fab.classList.add('saipa-fab--dragging');
                e.preventDefault();
            });

            fab.addEventListener('touchstart', function(e) {
                var t = e.touches[0];
                isDragging = true;
                dragMoved  = false;
                dragStartX = t.clientX;
                dragStartY = t.clientY;
                var rect   = getFabRect();
                fabStartLeft = rect.left;
                fabStartTop  = rect.top;
                fab.classList.add('saipa-fab--dragging');
            }, {passive: true});
        }

        document.addEventListener('mousemove', function(e) {
            if (!isDragging || !fab) { return; }
            var dx = e.clientX - dragStartX;
            var dy = e.clientY - dragStartY;
            if (Math.abs(dx) > 4 || Math.abs(dy) > 4) { dragMoved = true; }
            if (dragMoved) { applyFabPos(fabStartLeft + dx, fabStartTop + dy); }
        });

        document.addEventListener('touchmove', function(e) {
            if (!isDragging || !fab) { return; }
            var t  = e.touches[0];
            var dx = t.clientX - dragStartX;
            var dy = t.clientY - dragStartY;
            if (Math.abs(dx) > 4 || Math.abs(dy) > 4) { dragMoved = true; }
            if (dragMoved) { applyFabPos(fabStartLeft + dx, fabStartTop + dy); }
        }, {passive: true});

        function stopDrag() {
            if (!isDragging) { return; }
            isDragging = false;
            if (fab) {
                fab.classList.remove('saipa-fab--dragging');
                var rect = getFabRect();
                if (dragMoved) { saveFabPos(rect.left, rect.top); }
            }
        }

        document.addEventListener('mouseup',    stopDrag);
        document.addEventListener('touchend',   stopDrag);
        document.addEventListener('touchcancel', stopDrag);

        if (fab && panel) {
            fab.addEventListener('click', function() {
                if (dragMoved) { return; }   // Ignore click that ended a drag.
                var visible = panel.style.display !== 'none';
                panel.style.display = visible ? 'none' : 'flex';
                if (!visible && input) { input.focus(); }
            });
        }

        if (closeBtn && panel) {
            closeBtn.addEventListener('click', function() {
                panel.style.display = 'none';
            });
        }

        if (sendBtn) {
            sendBtn.addEventListener('click', sendMessage);
        }

        if (input) {
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });
        }

        Log.debug('SAIPA: advisor dashboard initialised');
    }

    return { init: init };
});
