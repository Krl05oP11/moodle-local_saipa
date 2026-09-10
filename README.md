# SAIPA — AI Companion for Moodle

**SAIPA** (Sistema de Acompañamiento Inteligente Pedagógico con IA) is an AI-powered companion plugin for Moodle 4.x that helps teachers detect at-risk students early, enables role-aware RAG chat over course materials, and delivers proactive alerts via Telegram.

[![Moodle 4.4+](https://img.shields.io/badge/Moodle-4.4%2B-orange)](https://moodle.org)
[![PHP 8.1+](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://php.net)
[![License: GPL v3](https://img.shields.io/badge/License-GPL%20v3-green)](LICENSE)
[![Maturity: Beta](https://img.shields.io/badge/Maturity-Beta-yellow)]()

---

## Features

### Dropout Risk Detection
- Scores student dropout probability with a deterministic rule-based model over 11 engagement signals (login frequency, activity completion rate, forum participation, assignment submission ratio, etc.); low/medium/high thresholds are admin-configurable
- Per-student risk badges: 🟢 Low / 🟡 Medium / 🔴 High
- Historical risk tracking with weekly snapshots
- Risk dashboard with course-level and institution-level summaries

### RAG-Powered Chat
- Students and teachers chat with an AI assistant that has context from the course materials
- Role-aware responses: the assistant adapts its tone and depth depending on whether the user is a student or a teacher
- Powered by LangChain + ChromaDB (vector store) + Ollama (local LLM, default: `qwen2.5:14b`)
- Conversation history persisted per user per course
- 👍/👎 feedback on responses

### Proactive Telegram Alerts
- Teachers can send personalised AI-generated alerts to at-risk students directly from the dashboard
- Bidirectional: students can reply via Telegram and the conversation is recorded in Moodle
- Alert engagement tracking (responded / ignored)

### Advisor Dashboard
Five-tab institutional overview for academic advisors and coordinators:
- **Risk Overview** — institution-wide risk distribution
- **Engagement Stats** — daily active users, completion rates
- **Course Summary** — per-course health indicators
- **Risk History** — weekly trend charts
- **Settings** — institution-wide AI configuration

### SAIPA Assistant (FAB)
- Draggable floating action button available on the advisor dashboard
- Powered by a dedicated `/chat/advisor` engine endpoint for institutional-level queries

### Telegram Integration
- Bot polling or webhook mode
- Students link their Moodle account to Telegram via a one-time code (`/vincular <code>`)
- Full bidirectional messaging: Moodle → Telegram → Moodle

---

## Requirements

| Requirement | Version |
|-------------|---------|
| Moodle | 4.4 or 4.5 |
| PHP | 8.1+ |
| Database | MySQL 8+ / MariaDB 10.6+ / PostgreSQL 13+ |
| **SAIPA Engine** | Python 3.11+, FastAPI, Ollama |
| **block_saipa** | 2026032401+ (companion block) |

### SAIPA Engine
The plugin requires a separate Python service (`saipa-engine`) that handles:
- LLM inference via [Ollama](https://ollama.com) (local) or any OpenAI-compatible API
- Vector search with ChromaDB
- Telegram bot polling
- Rule-based dropout-risk model

See the [SAIPA Engine repository](https://github.com/Krl05oP11/saipa-engine) for setup instructions.

---

## Installation

### 1. Install the Moodle plugin

**Via ZIP upload (recommended):**
1. Download the latest release ZIP from the [Releases](../../releases) page.
2. In Moodle: *Site administration → Plugins → Install plugins → Upload ZIP file*.
3. Follow the on-screen upgrade wizard.

**Via Git:**
```bash
cd /path/to/moodle/local
git clone https://github.com/Krl05oP11/moodle-local_saipa.git saipa
```
Then visit `/admin/upgrade.php` in your browser.

### 2. Install block_saipa
The companion block must also be installed:
```bash
cd /path/to/moodle/blocks
git clone https://github.com/Krl05oP11/moodle-block_saipa.git saipa
```

### 3. Deploy the SAIPA Engine
```bash
git clone https://github.com/Krl05oP11/saipa-engine.git
cd saipa-engine
cp .env.example .env   # configure MOODLE_URL, MOODLE_TOKEN, TELEGRAM_BOT_TOKEN
docker compose up -d
```

---

## Configuration

After installation, go to *Site administration → Plugins → Local plugins → SAIPA*:

| Setting | Description |
|---------|-------------|
| **Engine URL** | Base URL of the SAIPA Engine, e.g. `http://localhost:8052` |
| **Engine token** | Bearer token configured in the engine (`.env` → `SAIPA_API_TOKEN`). Required — the engine rejects an empty token. |
| **Telegram Bot Token** | Token from [@BotFather](https://t.me/BotFather) |
| **Alert cooldown (hours)** | Minimum hours between alerts to the same student (default: 24) |
| **Risk threshold — medium** | Probability threshold for 🟡 Medium risk (default: 0.4) |
| **Risk threshold — high** | Probability threshold for 🔴 High risk (default: 0.7) |

### Index your course content
Before students can use the chat, a teacher must index the course materials:

1. Open the course → *SAIPA → Teacher Dashboard → Index Course*.
2. Supported formats: Moodle Pages, PDF files, PPTX presentations.
3. Indexing runs in the background; large courses may take a few minutes.

---

## Usage

### For Teachers
- Navigate to a course → sidebar → **SAIPA** → Teacher Dashboard.
- Use the **Risk** tab to view at-risk students and send Telegram alerts.
- Use the **Chat** tab to interact with the course AI assistant.
- Use the **Settings** tab to configure course-level AI behaviour.

### For Students
- The SAIPA block appears in the course sidebar after a teacher adds it.
- Click the block to open the chat panel.
- Link your Telegram account: *SAIPA → My Account → Link Telegram*, then send `/vincular <code>` to the bot.

### For Advisors
- Navigate to *SAIPA → Advisor Dashboard* (requires `local/saipa:advisor`).
- View institution-wide risk and engagement data across all courses.

---

## Capabilities

| Capability | Description | Default roles |
|------------|-------------|---------------|
| `local/saipa:chat` | Use the AI chat | student, teacher |
| `local/saipa:view` | View SAIPA panels in a course | student, teacher |
| `local/saipa:manage` | Full teacher dashboard access | editingteacher, manager |
| `local/saipa:viewall` | Institution-wide advisor view | manager |
| `local/saipa:advisor` | Access the advisor dashboard | manager |

---

## Architecture

```
Moodle (PHP 8.1+)                  SAIPA Engine (Python / FastAPI)
──────────────────                  ───────────────────────────────
local_saipa/                        rag/
  lib.php ──────── REST ──────────▶   LangChain + ChromaDB + Ollama
  classes/external/                 analytics/
    chat.php                          rule-based risk (11 signals)
    get_course_risk.php             telegram/
    send_telegram_alert.php ──────▶   Bot polling / webhook
    index_course.php ─── base64 ──▶ routers/
block_saipa/                          index.py, chat.py, risk.py
  block_saipa.php                     telegram.py
```

---

## Database Tables

| Table | Purpose |
|-------|---------|
| `saipa_sessions` | Chat session state per user per course |
| `saipa_messages` | Full conversation history |
| `saipa_risk_scores` | Latest risk score per student per course |
| `saipa_risk_history` | Weekly risk snapshots |
| `saipa_alerts` | Alert log (sent, responded, ignored) |
| `saipa_telegram_links` | Moodle user ↔ Telegram chat_id mapping |
| `saipa_daily_stats` | Aggregated daily engagement metrics |
| `saipa_course_settings` | Per-course AI configuration overrides |

---

## Privacy / GDPR

SAIPA implements the Moodle Privacy API (`core_privacy\local\request`). Personal data stored per user includes chat history, risk scores, alert log, and Telegram link. All data can be exported or deleted via *Site administration → Privacy and policies → Data requests*.

See `classes/privacy/provider.php` for the full declaration.

---

## Changelog

See [CHANGES.md](CHANGES.md).

---

## Contributing

Bug reports and feature requests: [GitHub Issues](../../issues).

Pull requests are welcome. Please follow [Moodle coding style](https://moodledev.io/general/development/policies/codingstyle) and run `phpcs` before submitting.

---

## License

GNU General Public License v3 or later — see [LICENSE](LICENSE).

Copyright 2026 Schaller & Ponce — <dev@schaller-ponce.com.ar>
