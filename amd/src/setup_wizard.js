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
 * SAIPA setup wizard (setup.php): step navigation, Telegram bot test, engine health check and save.
 *
 * The page renders every step; this module shows one at a time. Strings and labels come
 * from the data-config attribute of the .spwiz element (JSON written by setup.php), and the
 * buttons declare what they do with data-spwiz-goto="<step>" or data-spwiz-action.
 *
 * @module     local_saipa/setup_wizard
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/config'], function(Config) {

    'use strict';

    var L = {};
    var modeLabels = {};
    var channelLabels = {};
    var setupUrl = '';
    var currentStep = 1;

    /**
     * Escape a value for HTML.
     *
     * @param {*} s Value to escape.
     * @returns {string}
     */
    function he(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    /**
     * Set the text of an element, if it exists.
     *
     * @param {string} id Element id.
     * @param {string} val Text.
     */
    function setText(id, val) {
        var el = document.getElementById(id);
        if (el) {
            el.textContent = val;
        }
    }

    /**
     * POST an action to setup.php with the session key and return the decoded JSON.
     *
     * @param {Object} fields Form fields besides sesskey.
     * @returns {Promise}
     */
    function post(fields) {
        var fd = new FormData();
        fd.append('sesskey', Config.sesskey);
        Object.keys(fields).forEach(function(k) {
            fd.append(k, fields[k]);
        });
        return fetch(setupUrl, {method: 'POST', credentials: 'same-origin', body: fd})
            .then(function(r) {
                return r.json();
            });
    }

    /**
     * Engine mode chosen in step 3.
     *
     * @returns {string}
     */
    function getSelectedMode() {
        var checked = document.querySelector('input[name="engine_mode"]:checked');
        return checked ? checked.value : 'local_ollama';
    }

    /**
     * Show the hint of the chosen engine mode in step 4.
     */
    function updateHints() {
        var mode = getSelectedMode();
        ['local_ollama', 'cloud_api', 'custom'].forEach(function(m) {
            var el = document.getElementById('sp-hint-' + m);
            if (el) {
                el.style.display = (m === mode) ? '' : 'none';
            }
        });
    }

    /**
     * Messaging channel chosen in step 5.
     *
     * @returns {string}
     */
    function getSelectedChannel() {
        var checked = document.querySelector('input[name="messaging_channel"]:checked');
        return checked ? checked.value : 'none';
    }

    /**
     * Show the Telegram settings and the WhatsApp note that apply to the chosen channel.
     */
    function updateChannelUI() {
        var ch = getSelectedChannel();
        var tgSection = document.getElementById('sp-telegram-section');
        var waNote = document.getElementById('sp-whatsapp-note');
        if (tgSection) {
            tgSection.style.display = (ch === 'telegram' || ch === 'both') ? '' : 'none';
        }
        if (waNote) {
            waNote.style.display = (ch === 'whatsapp' || ch === 'both') ? '' : 'none';
        }
    }

    /**
     * Check the Telegram bot token with the engine and fill in the bot's username.
     */
    function testBot() {
        var resultEl = document.getElementById('sp-bot-result');
        var tokenEl = document.getElementById('sp-tg-token');
        var usernameEl = document.getElementById('sp-tg-username');

        if (!tokenEl || !tokenEl.value.trim()) {
            resultEl.innerHTML = '<div class="alert alert-warning py-2 px-3 mb-0" style="font-size:.82rem;">'
                + he(L.enter_token) + '</div>';
            return;
        }

        resultEl.innerHTML =
            '<div class="d-flex align-items-center gap-2 text-muted py-1">' +
            '<div class="spinner-border spinner-border-sm" role="status"></div>' +
            '<span>' + he(L.validating) + '</span></div>';

        post({action: 'testbot', 'bot_token': tokenEl.value.trim()}).then(function(data) {
            if (data.ok) {
                if (usernameEl && data.username) {
                    usernameEl.value = data.username;
                }
                resultEl.innerHTML =
                    '<div class="alert alert-success py-2 px-3 mb-0" style="font-size:.82rem;">' +
                    '✅ <strong>' + he(L.bot_verified) + '</strong> @' + he(data.username) +
                    ' (' + he((data.name || '').trim()) + '). ' +
                    he(L.username_autofill) + '</div>';
            } else {
                resultEl.innerHTML =
                    '<div class="alert alert-danger py-2 px-3 mb-0" style="font-size:.82rem;">' +
                    '❌ <strong>' + he(L.error) + '</strong> ' + he(data.error || L.invalid_token) + '</div>';
            }
            return data;
        }).catch(function(e) {
            resultEl.innerHTML =
                '<div class="alert alert-danger py-2 px-3 mb-0" style="font-size:.82rem;">' +
                '❌ ' + he(L.network_error) + ' ' + he(e.message) + '</div>';
        });
    }

    /**
     * Show why the engine could not be reached, with the troubleshooting list.
     *
     * @param {string} msg Error message.
     * @param {string} url Engine URL that was tried.
     */
    function renderHealthError(msg, url) {
        document.getElementById('sp-health-result').innerHTML =
            '<div class="health-row">' +
            '<div class="health-icon">❌</div>' +
            '<div class="health-label"><strong>' + he(L.connection_failed) + '</strong></div>' +
            '<div class="health-value">' + he(url || '—') + '</div></div>' +
            '<div class="alert alert-danger py-2 px-3 mt-3 mb-0" style="font-size:.84rem;">' +
            '<strong>' + he(L.error) + '</strong> ' + he(msg) + '<br><br>' +
            '<strong>' + he(L.troubleshooting) + '</strong>' +
            '<ul class="mb-0 mt-1">' +
            '<li>' + L.ts_running + '</li>' +
            '<li>' + L.ts_url + '</li>' +
            '<li>' + L.ts_docker + '</li>' +
            '<li>' + L.ts_token + '</li>' +
            '</ul></div>';
        document.getElementById('sp-retry-btn').style.display = '';
    }

    /**
     * Check that the engine answers, then show the summary and the save button (step 6).
     */
    function runTest() {
        var resultEl = document.getElementById('sp-health-result');
        var saveBtn = document.getElementById('sp-save-btn');
        var retryBtn = document.getElementById('sp-retry-btn');
        var summaryEl = document.getElementById('sp-summary');

        saveBtn.style.display = 'none';
        retryBtn.style.display = 'none';
        if (summaryEl) {
            summaryEl.style.display = 'none';
        }

        resultEl.innerHTML =
            '<div class="d-flex align-items-center gap-2 text-muted py-2">' +
            '<div class="spinner-border spinner-border-sm" role="status"></div>' +
            '<span>' + he(L.connecting) + '</span></div>';

        var url = ((document.getElementById('sp-url') || {}).value || '').trim();
        var token = (document.getElementById('sp-token') || {}).value || '';

        if (!url) {
            renderHealthError(L.url_empty, url);
            return;
        }

        post({action: 'health', 'engine_url': url, 'engine_token': token}).then(function(data) {
            if (data.ok) {
                resultEl.innerHTML =
                    '<div class="health-row">' +
                    '<div class="health-icon">✅</div>' +
                    '<div class="health-label"><strong>' + he(L.engine_reachable) + '</strong></div>' +
                    '<div class="health-value">' + he(url) + '</div></div>' +
                    '<div class="health-row">' +
                    '<div class="health-icon">🔢</div>' +
                    '<div class="health-label">' + he(L.engine_version) + '</div>' +
                    '<div class="health-value">' + he(data.version) + '</div></div>' +
                    '<div class="health-row">' +
                    '<div class="health-icon">⏱️</div>' +
                    '<div class="health-label">' + he(L.uptime) + '</div>' +
                    '<div class="health-value">' + he(data.uptime) + '</div></div>' +
                    '<div class="alert alert-success py-2 px-3 mt-3 mb-0" style="font-size:.85rem;">' +
                    L.success + '</div>';

                var usernameEl = document.getElementById('sp-tg-username');
                setText('sum-mode', modeLabels[getSelectedMode()] || getSelectedMode());
                setText('sum-url', url);
                setText('sum-token', token ? '●●●●●●●●' : L.not_configured);
                setText('sum-channel', channelLabels[getSelectedChannel()] || getSelectedChannel());
                setText('sum-tg', usernameEl && usernameEl.value ? '@' + usernameEl.value : L.not_configured);
                if (summaryEl) {
                    summaryEl.style.display = '';
                }
                saveBtn.style.display = '';
            } else {
                renderHealthError(data.error || L.unknown_error, url);
            }
            return data;
        }).catch(function(e) {
            renderHealthError(e.message || L.network_error, url);
        });
    }

    /**
     * Copy the wizard's choices into the hidden save form and submit it.
     */
    function save() {
        var usernameEl = document.getElementById('sp-tg-username');
        document.getElementById('sf-mode').value = getSelectedMode();
        document.getElementById('sf-url').value = (document.getElementById('sp-url') || {}).value || '';
        document.getElementById('sf-token').value = (document.getElementById('sp-token') || {}).value || '';
        document.getElementById('sf-channel').value = getSelectedChannel();
        document.getElementById('sf-tguser').value = usernameEl ? usernameEl.value : '';
        document.getElementById('sp-save-form').submit();
    }

    /**
     * Show a step and update the progress bar.
     *
     * @param {number} step Step number (1-6).
     */
    function goTo(step) {
        var prev = document.getElementById('spwiz-step-' + currentStep);
        if (prev) {
            prev.classList.remove('active');
        }
        currentStep = step;
        var next = document.getElementById('spwiz-step-' + step);
        if (next) {
            next.classList.add('active');
        }

        document.querySelectorAll('#spwiz-progress .step').forEach(function(el) {
            var n = parseInt(el.dataset.step, 10);
            el.classList.remove('active', 'done');
            if (n === step) {
                el.classList.add('active');
            }
            if (n < step) {
                el.classList.add('done');
            }
        });

        if (step === 4) {
            updateHints();
        }
        if (step === 5) {
            updateChannelUI();
        }
        if (step === 6) {
            runTest();
        }

        window.scrollTo({top: 0, behavior: 'smooth'});
    }

    /**
     * Make a group of option cards behave like the radio buttons they contain.
     *
     * @param {string} selector Card selector.
     * @param {Function} [onchange] Called after a card is chosen.
     */
    function bindCards(selector, onchange) {
        document.querySelectorAll(selector).forEach(function(card) {
            card.addEventListener('click', function() {
                document.querySelectorAll(selector).forEach(function(c) {
                    c.classList.remove('selected');
                });
                card.classList.add('selected');
                card.querySelector('input[type=radio]').checked = true;
                if (onchange) {
                    onchange();
                }
            });
        });
    }

    return {
        /**
         * Wire up the wizard.
         */
        init: function() {
            var root = document.querySelector('.spwiz[data-config]');
            if (!root) {
                return;
            }
            var config = JSON.parse(root.dataset.config);
            L = config.strings;
            modeLabels = config.modes;
            channelLabels = config.channels;
            setupUrl = config.url;

            var actions = {testbot: testBot, runtest: runTest, save: save};
            root.addEventListener('click', function(e) {
                var go = e.target.closest('[data-spwiz-goto]');
                if (go) {
                    goTo(parseInt(go.dataset.spwizGoto, 10));
                    return;
                }
                var action = e.target.closest('[data-spwiz-action]');
                if (action && actions[action.dataset.spwizAction]) {
                    actions[action.dataset.spwizAction]();
                }
            });

            var reqCheck = document.getElementById('req-confirm');
            var reqNextBtn = document.getElementById('req-next-btn');
            if (reqCheck) {
                reqCheck.addEventListener('change', function() {
                    reqNextBtn.disabled = !reqCheck.checked;
                });
            }

            bindCards('.mode-card');
            bindCards('.channel-card', updateChannelUI);
            updateChannelUI();
        }
    };
});
