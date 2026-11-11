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
 * SAIPA — My Courses Dashboard AMD module.
 * Loads multi-course summary table via local_saipa_get_my_courses web service.
 *
 * @module     local_saipa/my_courses_dashboard
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/log', 'core/str', 'local_saipa/dom'], function(Ajax, Log, Str, Dom) {

    'use strict';

    var courses = [];
    var sortCol = 'coursename';
    var sortAsc = true;
    var wwwroot = '';

    /**
     * Build the badge for a course RAG index status.
     *
     * @param {string} status Index status (ready, indexing, pending or error).
     * @returns {HTMLElement} Badge element.
     */
    function indexBadge(status) {
        var map = {
            ready:    {cls: 'badge-success', text: '✓'},
            indexing: {cls: 'badge-info', text: '⟳'},
            pending:  {cls: 'badge-secondary', text: '—'},
            error:    {cls: 'badge-danger', text: '✗'},
        };
        var cfg = map[status] || {cls: 'badge-light', text: status || '—'};
        return Dom.el('span', 'badge ' + cfg.cls, cfg.text);
    }

    /**
     * Build the summary of high, medium and low risk student counts.
     *
     * @param {number} hi Number of high-risk students.
     * @param {number} med Number of medium-risk students.
     * @param {number} lo Number of low-risk students.
     * @returns {Array} Nodes for Dom.append(), or a dash when all counts are zero.
     */
    function riskParts(hi, med, lo) {
        var parts = [];
        if (hi > 0) { parts.push(Dom.el('span', 'text-danger', '🔴 ' + hi)); }
        if (med > 0) { parts.push(Dom.el('span', 'text-warning', '🟡 ' + med)); }
        if (lo > 0) { parts.push(Dom.el('span', 'text-success', '🟢 ' + lo)); }
        return parts.length ? Dom.join(parts, ' ') : [Dom.el('span', 'text-muted', '—')];
    }

    /**
     * Render the course rows into the summary table, toggling the empty-state message.
     *
     * @param {Array} data Course summary records to render.
     */
    function renderRows(data) {
        var tbody = document.getElementById('saipa-mc-tbody');
        var empty = document.getElementById('saipa-mc-empty');
        if (!tbody) { return; }

        Dom.replace(tbody);

        if (!data || data.length === 0) {
            if (empty) { empty.style.display = ''; }
            return;
        }
        if (empty) { empty.style.display = 'none'; }

        data.forEach(function(c) {
            var tr = document.createElement('tr');
            var adoptionPct = Math.round((c.adoption_rate || 0) * 100);
            var href = wwwroot + 'local/saipa/teacher.php?courseid=' + c.courseid;

            Dom.append(tr, [
                Dom.el('td', '', [
                    Dom.el('a', '', c.coursename, {href: href}),
                    Dom.el('br'),
                    Dom.el('small', 'text-muted', c.shortname)
                ]),
                Dom.el('td', 'text-right', c.enrolled_students),
                Dom.el('td', 'text-right', c.active_saipa_users),
                Dom.el('td', 'text-right', adoptionPct + '%'),
                Dom.el('td', 'text-right', c.messages_7d),
                Dom.el('td', 'text-center', riskParts(c.high_risk, c.medium_risk, c.low_risk)),
                Dom.el('td', 'text-center', indexBadge(c.index_status)),
                Dom.el('td', '', Dom.el('a', 'btn btn-sm btn-outline-primary', '\uD83D\uDC41', {href: href}))
            ]);

            tbody.appendChild(tr);
        });
    }

    /**
     * Sort the courses by a column, toggling direction when the same column is chosen again, and re-render.
     *
     * @param {string} col Course field name to sort by.
     */
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
            if (typeof av === 'string') { return mult * av.localeCompare(bv, 'es', {sensitivity: 'base'}); }
            return mult * (av - bv);
        });
        renderRows(filterData());
    }

    /**
     * Filter the courses by the search box text, matching course name or short name.
     *
     * @returns {Array} The matching course records.
     */
    function filterData() {
        var q = (document.getElementById('saipa-mc-search') || {}).value || '';
        q = q.toLowerCase();
        if (!q) { return courses; }
        return courses.filter(function(c) {
            return c.coursename.toLowerCase().indexOf(q) >= 0
                || c.shortname.toLowerCase().indexOf(q) >= 0;
        });
    }

    /**
     * Initialise the My Courses dashboard: load course data and wire up sorting and search.
     */
    function init() {
        var root = document.getElementById('saipa-my-courses');
        if (root) { wwwroot = root.dataset.wwwroot || ''; }

        Ajax.call([{
            methodname: 'local_saipa_get_my_courses',
            args: {}
        }])[0].then(function(result) {
            var spinner = document.getElementById('saipa-mc-spinner');
            var tableDiv = document.getElementById('saipa-mc-table-wrap');
            if (spinner) { spinner.style.display = 'none'; }
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
            var spinner = document.getElementById('saipa-mc-spinner');
            var errorDiv = document.getElementById('saipa-mc-error');
            if (spinner) { spinner.style.display = 'none'; }
            if (errorDiv) {
                Str.get_string('mc_load_error', 'local_saipa').then(function(msg) {
                    errorDiv.textContent = msg;
                    errorDiv.style.display = '';
                    return msg;
                }).catch(Log.error);
            }
        });
    }

    return {init: init};
});
