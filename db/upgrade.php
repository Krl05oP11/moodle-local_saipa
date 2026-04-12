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
 * Upgrade steps for local_saipa.
 *
 * @package    local_saipa
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Xmldb local saipa upgrade.
 */
function xmldb_local_saipa_upgrade(int $oldversion): bool {
    global $DB, $CFG;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026031901) {
        // Register the risk_alert message provider introduced in 0.3.1.
        require_once($CFG->libdir . '/messagelib.php');
        message_update_providers('local_saipa');
        upgrade_plugin_savepoint(true, 2026031901, 'local', 'saipa');
    }

    if ($oldversion < 2026031902) {
        // Create saipa_telegram_links table for Telegram account binding.
        $table = new xmldb_table('saipa_telegram_links');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('telegram_id', XMLDB_TYPE_INTEGER, '18', null, null);
        $table->add_field('telegram_username', XMLDB_TYPE_CHAR, '100', null, null);
        $table->add_field('link_token', XMLDB_TYPE_CHAR, '128', null, null);
        $table->add_field('token_expires', XMLDB_TYPE_INTEGER, '10', null, null);
        $table->add_field('confirmed', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('uq_userid', XMLDB_KEY_UNIQUE, ['userid']);
        $table->add_key('fk_userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        $table->add_index('idx_telegram_id', XMLDB_INDEX_UNIQUE, ['telegram_id']);
        $table->add_index('idx_link_token', XMLDB_INDEX_UNIQUE, ['link_token']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Auto-generate HMAC secret if not already set.
        if (!get_config('local_saipa', 'telegram_hmac_secret')) {
            set_config('telegram_hmac_secret', bin2hex(random_bytes(32)), 'local_saipa');
        }

        // Register the telegram_link message provider.
        require_once($CFG->libdir . '/messagelib.php');
        message_update_providers('local_saipa');

        upgrade_plugin_savepoint(true, 2026031902, 'local', 'saipa');
    }

    if ($oldversion < 2026031903) {
        // Migrate telegram_enabled (bool) → messaging_channel (select).
        // If the admin had previously enabled Telegram, preserve that choice.
        $wastelegramenabled = (bool) get_config('local_saipa', 'telegram_enabled');
        if (!get_config('local_saipa', 'messaging_channel')) {
            set_config('messaging_channel', $wastelegramenabled ? 'telegram' : 'none', 'local_saipa');
        }
        // Remove the now-obsolete telegram_enabled config key.
        unset_config('telegram_enabled', 'local_saipa');

        upgrade_plugin_savepoint(true, 2026031903, 'local', 'saipa');
    }

    if ($oldversion < 2026031904) {
        // Add responded_at column to saipa_notifications.

        // This column was missing from the original install.xml && must be added for
        // mark_alert_responded WS to work on fresh installations.
        $table = new xmldb_table('saipa_notifications');
        $field = new xmldb_field(
            'responded_at',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            null,
            null,
            '0',
            'timesent'
        );

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026031904, 'local', 'saipa');
    }

    if ($oldversion < 2026032401) {
        // Create saipa_course_settings: per-course feature flags for admin control.
        $table = new xmldb_table('saipa_course_settings');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('saipa_enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('chat_enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('risk_enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('alerts_enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('rag_enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('uq_courseid', XMLDB_KEY_UNIQUE, ['courseid']);
        $table->add_key('fk_courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Create saipa_daily_stats: pre-aggregated daily metrics for advisor dashboard.
        $table = new xmldb_table('saipa_daily_stats');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('stat_date', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('active_users', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('new_sessions', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('total_messages', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('user_messages', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('assistant_messages', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('positive_feedback', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('negative_feedback', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('alerts_sent', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('alerts_responded', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('students_high_risk', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('students_medium_risk', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('students_low_risk', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        // NUMBER(8,2): precision '8,2' format sets both length && decimals in XMLDB API.
        $table->add_field('avg_response_delay_min', XMLDB_TYPE_NUMBER, '8,2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('uq_course_date', XMLDB_KEY_UNIQUE, ['courseid', 'stat_date']);
        $table->add_key('fk_courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Create saipa_risk_history: append-only risk score log for trend analysis / research.
        $table = new xmldb_table('saipa_risk_history');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        // NUMBER(5,4): precision '5,4' format sets both length && decimals in XMLDB API.
        $table->add_field('score', XMLDB_TYPE_NUMBER, '5,4', null, XMLDB_NOTNULL);
        $table->add_field('risk_level', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL);
        $table->add_field('timecomputed', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('fk_courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_index(
            'idx_userid_courseid_time',
            XMLDB_INDEX_NOTUNIQUE,
            ['userid', 'courseid', 'timecomputed']
        );
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Backfill saipa_risk_history from current saipa_risk_scores so trend charts
        // have at least one data point from day one.
        if ($dbman->table_exists(new xmldb_table('saipa_risk_scores'))) {
            $DB->execute(
                'INSERT INTO {saipa_risk_history} (userid, courseid, score, risk_level, timecomputed)
                 SELECT userid, courseid, score, risk_level, timecomputed FROM {saipa_risk_scores}'
            );
        }

        upgrade_plugin_savepoint(true, 2026032401, 'local', 'saipa');
    }

    // 2026032402 — Register new web services (dashboard + advisor WS).
    // No schema changes; version bump forces Moodle to re-read db/services.php.
    if ($oldversion < 2026032402) {
        upgrade_plugin_savepoint(true, 2026032402, 'local', 'saipa');
    }

    // 2026032403 — Register local_saipa_admin_chat WS.
    if ($oldversion < 2026032403) {
        upgrade_plugin_savepoint(true, 2026032403, 'local', 'saipa');
    }

    return true;
}
