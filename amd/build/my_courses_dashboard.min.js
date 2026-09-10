// This file is part of Moodle - http://moodle.org/
//
// SAIPA — My Courses Dashboard AMD module.
// Loads multi-course summary table via local_saipa_get_my_courses web service.

/**
 * @module     local_saipa/my_courses_dashboard
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/log'], function(Ajax, Log) {

    'use strict';

    var courses    = [];
    var sortCol    = 'coursename';
    var sortAsc    = true;
    var wwwroot    = '';

    function indexBadge(status) {
        var map = {
            ready:    '<span class="badge badge-success">✓</span>',
            indexing: '<span class="badge badge-info">⟳</span>',
            pending:  '<span class="badge badge-secondary">—</span>',
            error:    '<span class="badge badge-danger">✗</span>',
        };
        return map[status] || '<span class="badge badge-light">' + (status || '—') + '</span>';
    }

    function riskHtml(hi, med, lo) {
        var parts = [];
        if (hi  > 0) { parts.push('<span class="text-danger">🔴 ' + hi  + '</span>'); }
        if (med > 0) { parts.push('<span class="text-warning">🟡 ' + med + '</span>'); }
        if (lo  > 0) { parts.push('<span class="text-success">🟢 ' + lo  + '</span>'); }
        return parts.length ? parts.join(' ') : '<span class="text-muted">—</span>';
    }

    function renderRows(data) {
        var tbody = document.getElementById('saipa-mc-tbody');
        var empty = document.getElementById('saipa-mc-empty');
        if (!tbody) { return; }

        tbody.innerHTML = '';

        if (!data || data.length === 0) {
            if (empty) { empty.style.display = ''; }
            return;
        }
        if (empty) { empty.style.display = 'none'; }

        data.forEach(function(c) {
            var tr = document.createElement('tr');
            var adoptionPct = Math.round((c.adoption_rate || 0) * 100);
            var href = wwwroot + 'local/saipa/teacher.php?courseid=' + c.courseid;

            tr.innerHTML = ''
                + '<td><a href="' + href + '">' + escapeHtml(c.coursename) + '</a>'
                + '<br/><small class="text-muted">' + escapeHtml(c.shortname) + '</small></td>'
                + '<td class="text-right">' + c.enrolled_students + '</td>'
                + '<td class="text-right">' + c.active_saipa_users + '</td>'
                + '<td class="text-right">' + adoptionPct + '%</td>'
                + '<td class="text-right">' + c.messages_7d + '</td>'
                + '<td class="text-center">' + riskHtml(c.high_risk, c.medium_risk, c.low_risk) + '</td>'
                + '<td class="text-center">' + indexBadge(c.index_status) + '</td>'
                + '<td><a href="' + href + '" class="btn btn-sm btn-outline-primary">&#128065;</a></td>';

            tbody.appendChild(tr);
        });
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function sortData(col) {
        if (sortCol === col) {
            sortAsc = !sortAsc;
        } else {
            sortCol = col;
            sortAsc = true;
        }
        var mult = sortAsc ? 1 : -1;
        courses.sort(function(a, b) {
            var av = a[col];
            var bv = b[col];
            if (typeof av === 'string') { return mult * av.localeCompare(bv, 'es', { sensitivity: 'base' }); }
            return mult * (av - bv);
        });
        renderRows(filterData());
    }

    function filterData() {
        var q = (document.getElementById('saipa-mc-search') || {}).value || '';
        q = q.toLowerCase();
        if (!q) { return courses; }
        return courses.filter(function(c) {
            return c.coursename.toLowerCase().indexOf(q) >= 0
                || c.shortname.toLowerCase().indexOf(q) >= 0;
        });
    }

    function init() {
        var root = document.getElementById('saipa-my-courses');
        if (root) { wwwroot = root.dataset.wwwroot || ''; }

        Ajax.call([{
            methodname: 'local_saipa_get_my_courses',
            args: {}
        }])[0].then(function(result) {
            var spinner  = document.getElementById('saipa-mc-spinner');
            var tableDiv = document.getElementById('saipa-mc-table-wrap');
            if (spinner)  { spinner.style.display  = 'none'; }
            if (tableDiv) { tableDiv.style.display = ''; }

            courses = result.courses || [];
            renderRows(filterData());

            // Sortable headers.
            var headers = document.querySelectorAll('#saipa-mc-table .saipa-sortable');
            headers.forEach(function(th) {
                th.style.cursor = 'pointer';
                th.addEventListener('click', function() {
                    sortData(th.dataset.col);
                });
            });

            // Search filter.
            var searchEl = document.getElementById('saipa-mc-search');
            if (searchEl) {
                searchEl.addEventListener('input', function() {
                    renderRows(filterData());
                });
            }

            return result;
        }).fail(function(err) {
            Log.error('SAIPA my_courses_dashboard error: ' + JSON.stringify(err));
            var spinner  = document.getElementById('saipa-mc-spinner');
            var errorDiv = document.getElementById('saipa-mc-error');
            if (spinner)  { spinner.style.display  = 'none'; }
            if (errorDiv) {
                errorDiv.textContent = 'Error loading course data.';
                errorDiv.style.display = '';
            }
        });
    }

    return { init: init };
});
