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
define(['core/config', 'core/templates', 'core/log', 'local_saipa/dom'], function(Config, Templates, Log, Dom) {

    'use strict';

    var L = {};
    var modeLabels = {};
    var channelLabels = {};
    var setupUrl = '';
    var currentStep = 1;

    /**
     * Replace the contents of a result box with a spinner and a text.
     *
     * @param {HTMLElement} el Result box.
     * @param {string} pad Bootstrap padding class (py-1 or py-2).
     * @param {string} text Plain text.
     */
    function showSpinner(el, pad, text) {
        Dom.replace(el, Dom.el('div', 'd-flex align-items-center gap-2 text-muted ' + pad, [
            Dom.el('div', 'spinner-border spinner-border-sm', null, {role: 'status'}),
            Dom.el('span', '', text)
        ]));
    }

    /**
     * Replace the contents of a result box with an alert.
     *
     * @param {HTMLElement} el Result box.
     * @param {string} type Bootstrap alert type (success, warning or danger).
     * @param {Array} content Texts and nodes for Dom.append().
     */
    function showAlert(el, type, content) {
        Dom.replace(el, Dom.el('div', 'alert alert-' + type + ' py-2 px-3 mb-0 spwiz-result', content));
    }

    /**
     * Render the engine test result (template local_saipa/setup_wizard_health) into the result box.
     *
     * @param {Object} context Template context: ok, url, version, uptime, error.
     */
    function renderHealth(context) {
        var el = document.getElementById('sp-health-result');
        Templates.render('local_saipa/setup_wizard_health', context).then(function(html, js) {
            Templates.replaceNodeContents(el, html, js);
            return el;
        }).fail(function(err) {
            Log.error('SAIPA setup wizard: could not render the engine test result', err);
        });
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
            showAlert(resultEl, 'warning', [L.enter_token]);
            return;
        }

        showSpinner(resultEl, 'py-1', L.validating);

        post({action: 'testbot', 'bot_token': tokenEl.value.trim()}).then(function(data) {
            if (data.ok) {
                if (usernameEl && data.username) {
                    usernameEl.value = data.username;
                }
                showAlert(resultEl, 'success', ['✅ ', Dom.el('strong', '', L.bot_verified),
                    ' @' + data.username + ' (' + (data.name || '').trim() + '). ' + L.username_autofill]);
            } else {
                showAlert(resultEl, 'danger', ['❌ ', Dom.el('strong', '', L.error), ' ' + (data.error || L.invalid_token)]);
            }
            return data;
        }).catch(function(e) {
            showAlert(resultEl, 'danger', ['❌ ' + L.network_error + ' ' + e.message]);
        });
    }

    /**
     * Show why the engine could not be reached, with the troubleshooting list.
     *
     * @param {string} msg Error message.
     * @param {string} url Engine URL that was tried.
     */
    function renderHealthError(msg, url) {
        renderHealth({ok: false, url: url || '', version: '', uptime: '', error: msg});
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

        showSpinner(resultEl, 'py-2', L.connecting);

        var url = ((document.getElementById('sp-url') || {}).value || '').trim();
        var token = (document.getElementById('sp-token') || {}).value || '';

        if (!url) {
            renderHealthError(L.url_empty, url);
            return;
        }

        post({action: 'health', 'engine_url': url, 'engine_token': token}).then(function(data) {
            if (data.ok) {
                renderHealth({ok: true, url: url, version: data.version, uptime: data.uptime, error: ''});

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
