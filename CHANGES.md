# SAIPA Changelog

All notable changes to the SAIPA plugin (local_saipa) are documented in this file.

## [Unreleased]

### Changed
- All calls to `saipa-engine` now go through Moodle's `\core\http_client`
  (Guzzle) instead of raw PHP `curl_*`, so they honour the site's proxy and
  HTTP-security settings. The transport error message has a stable prefix
  (`Engine request failed: …`); non-2xx responses are now reported as errors
  instead of being parsed as a body.
- `engine_url` no longer defaults to `http://host.docker.internal:8052` (a
  dev-only assumption). The default is now empty — AI features stay disabled,
  with a clear message, until an admin sets the URL.
- **`block_saipa` is no longer a hard dependency.** `local_saipa` is the core
  component; its dashboards, risk analysis and cron work without the block. The
  dependency now points the correct way (`block_saipa` requires `local_saipa`),
  and the setup wizard lists the block as *recommended*, not *required*.
- Dropout-risk scoring is a documented **deterministic rule-based model** over 11
  engagement signals (never an XGBoost/ML model). User-facing strings, the setup
  wizard, and this changelog now describe it accurately.
- Low/medium/high risk thresholds configured in plugin settings are now sent to
  `saipa-engine` on every `/analytics/risk/batch` call, so the badge a student
  gets matches the site's own configuration. Engine defaults: medium 0.40,
  high 0.75.

### Fixed
- Engine connection docs and language strings renamed `ENGINE_SECRET` →
  `SAIPA_API_TOKEN` and marked it **required** (the engine now refuses to start
  with an empty token unless `SAIPA_DEV_MODE=true`).
- **PHPCS: the plugin is now clean under `phpcs --standard=moodle`** (0 errors,
  the invocation CI and the plugins-directory prechecks use). The 0.5.1
  changelog had claimed "22 residual errors"; the real figure under that
  standard was 82, all in `setup.php` and `status.php`:
  - `status.php`: the HTML tail is now emitted from one PHP block instead of
    inline `<?php echo ?>` islands, which also stopped the `MissingDocblock`
    sniff from misfiring. 17 errors → 0.
  - `setup.php`: the 10 over-long lines of templated wizard HTML are wrapped
    (all now < 125 chars); the `MissingDocblock.File` false positive (the sniff
    re-fires on every reopened `<?php` tag though the file docblock is present)
    is suppressed with a scoped `phpcs:disable` … `phpcs:enable` pair around the
    HTML template only. 65 errors → 0.
  - 368 **warnings** remain plugin-wide (0 errors) — the bulk are line-length in
    the wizard's embedded `<style>` / `<script>` blocks. Non-blocking; common
    for admin pages.
- Graceful degradation when `saipa-engine` is unreachable:
  - `index_course` stops after the first transport error instead of waiting a
    full timeout per remaining item, and records `status = 'error'` (was always
    `'ready'`, even when nothing was indexed).
  - the nightly risk-evaluation task probes `/health` first and skips the run
    (retrying next night) instead of spending one connect-timeout per course.
- **`risk_threshold_medium` default (`0.40`) failed Moodle's own admin-setting
  validation** on install (`admin_setting_configtext::validate()` round-trips
  the default through `clean_param(PARAM_FLOAT)` and string-compares — `"0.40"`
  cleans to `0.4`, so `"0.40" !== "0.4"` and the default was silently never
  applied). Found running PHPUnit's `admin/tool/phpunit/cli/init.php` against a
  real Moodle+PostgreSQL for the first time. Fixed by using `'0.4'` as the
  default (`risk_threshold_high` at `0.75` round-trips fine). Not a functional
  bug — `local_saipa_risk_thresholds()` already falls back to the same 0.40 when
  the config value is unset — but the admin settings page showed an empty field.
- **PHPUnit actually run for the first time** (real Moodle 4.4.12 + PostgreSQL
  14, not just `php -l`): **73 tests, 196 assertions, all green**, after fixing
  a test helper (`external_v050_test.php::create_session()`) that omitted the
  `contextid`/`timemodified` columns `saipa_sessions` requires — production
  code (`chat.php`, `get_history.php`) already sets both correctly; only the
  test fixture was stale. The 0.5.1 changelog's "86 tests" figure was wrong;
  73 is the real count.

### Added
- Setup Wizard (`setup.php`): seven-step guided installer with real-time
  engine health check and Telegram test-bot, prominent requirements display,
  and auto-redirect from `teacher.php` on first run.
- PHPUnit test suite: 86 tests covering critical web services, privacy API,
  scheduled tasks, and Moodle core compliance.
- PHPCS: 82 errors in `setup.php` and `status.php` under the plugins-directory
  standard (the "22" figure here was measured with a local `phpcs.xml.dist`
  that the prechecker does not read). Resolved in [Unreleased].
- Class and function docblocks across all external web service classes and
  scheduled task files.

### Fixed
- `VALUE_OPTIONAL` replaced with `VALUE_DEFAULT` in `save_institution_config`
  and `set_course_settings` WS parameters (top-level optional params are not
  allowed in Moodle external API).
- Privacy provider now declares `saipa_risk_history` table in metadata and
  all three delete methods (required for Moodle privacy table coverage test).
- Empty catch blocks replaced with `debugging()` calls.
- `install.xml` normalised via XMLDB API (adds `SEQUENCE="false"` to non-PK
  fields).
- Language strings sorted alphabetically in `en`, `es`, and `pt_br` packs.

## [0.5.0] - 2026-03-26

### Added
- Advisor Dashboard (`advisor.php`): five-tab institutional overview — Risk
  Overview, Engagement Stats, Course Summary, Risk History, and Settings.
- Multi-course page (`my_courses.php`): teacher view across all enrolled courses.
- Metrics bar in the Teacher Dashboard header (active students, messages,
  risk distribution).
- SAIPA Assistant (FAB): draggable floating chat button on `advisor.php`,
  powered by `/chat/advisor` engine endpoint.
- Per-course settings: `saipa_course_settings` table with feature flags
  (`chat_enabled`, `risk_enabled`, `alerts_enabled`, `rag_enabled`).
- Aggregated daily stats: `saipa_daily_stats` table with pre-computed engagement
  metrics; `aggregate_daily_stats` scheduled task (03:00, disabled by default).
- Risk history tracking: `saipa_risk_history` append-only table with backfill
  from existing `saipa_risk_scores`.
- Capabilities: `local/saipa:viewall`, `local/saipa:advisor`.
- 9 new web services (30 total): `get_course_summary`, `get_my_courses`,
  `get_institution_summary`, `get_risk_dashboard`, `get_engagement_stats`,
  `get_course_settings`, `set_course_settings`, `save_institution_config`,
  `admin_chat`.
- Spanish language pack installed site-wide.
- Demo seed script: `scripts/seed_demo_full.py` — 32 students, 5 profiles,
  8 weeks of realistic engagement history.

## [0.4.2] - 2026-03-27

### Added
- LICENSE file (GPL v3).
- `CHANGES.md` initial creation.
- Privacy API provider (`classes/privacy/provider.php`): full GDPR compliance
  covering all tables with personal data.
- `README.md` in English with full feature documentation.
- `MATURITY_BETA` in `version.php`.

### Fixed
- Critical bugs from consolidation: schema synchronisation, CORS security,
  cron task validation.

## [0.4.0] - 2026-03-24

### Added
- Telegram bidirectional messaging: Moodle → Telegram → Moodle.
- Proactive teacher-to-student alerts with personalised AI-generated messages.
- Engagement metrics: alert response tracking (responded / ignored).
- `saipa_telegram_links` table for Moodle-Telegram account binding.
- Telegram account linking via one-time code (`/vincular <code>`).
- `messaging_channel` setting (replaces boolean `telegram_enabled`).
- `responded_at` column on `saipa_notifications` for alert tracking.

## [0.3.0] - 2026-03-19

### Added
- Dropout risk model: 11 engagement features, per-student risk badges
  (🟢 Low / 🟡 Medium / 🔴 High).
- Risk evaluation scheduled task with demo mode.
- `risk_alert` message provider for Moodle's messaging subsystem.

## [0.2.0] - 2026-03-15

### Added
- RAG-powered chat: role-aware responses (student vs teacher).
- WhatsApp infrastructure via Evolution API (Baileys).
- Chat history persistence per user per course.
- 👍/👎 feedback on AI responses.

## [0.1.0] - 2026-03-10

### Added
- Initial release: web chat, conversation history, teacher dashboard,
  material indexing (PDF, PPTX, Moodle pages) to ChromaDB via RAG pipeline.
- 14 web services registered in `db/services.php`.
- Language packs: English and Spanish.
- Capabilities: `local/saipa:chat`, `local/saipa:view`, `local/saipa:manage`.
