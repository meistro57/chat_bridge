# Chat Bridge Codebase Overview

This document captures a high-level understanding of how the Chat Bridge project is organized and how its key components work together.

## 1. Primary Entry Points
- **`chat_bridge.py`** – The core CLI experience. It loads environment defaults, draws colorful interactive menus, and supports quick-start, persona-first, and advanced setup flows. Once configuration is chosen (providers, models, personas, temperatures, stop-word policy, and a conversation starter), it instantiates two agents, spins up transcripts/logging, streams alternating replies, checks stop-word/loop guards, and persists every message to SQLite plus Markdown transcripts.
- **`launch.py`** – Lightweight launcher that prints a preset menu of `chat_bridge.py` commands (interactive mode, fixed provider duels, persona mode, long-form sessions, etc.) and shells out to the chosen command.
- **`roles_manager.py`** – Standalone persona manager. It maintains `roles.json`, offers defaults for agent baselines and persona library, backs up prior versions, and provides interactive flows for creating/editing personas, temperatures, stop-word lists, and import/export/reset utilities.

## 2. Provider Abstractions (`bridge_agents.py`)
- Declares the provider registry with metadata (labels, default models/system prompts, env var names, and connectivity notes) for OpenAI, Anthropic, Gemini, Ollama, LM Studio, and DeepSeek.
- Supplies streaming client wrappers per provider: OpenAI/LM Studio/DeepSeek (Chat Completions compatible), Anthropic Messages, Gemini, and Ollama HTTP streaming. Each wrapper logs request/response details and yields token chunks.
- Offers helper utilities: environment override resolution, stop-key validation, turn slicing for memory windows, provider-specific message formatting (chatml, Claude, Gemini, Ollama), and a unified `AgentRuntime` object with a `stream_reply` coroutine.
- Exposes `create_agent` to instantiate provider clients with credential checks and error logging.

## 3. Conversation Lifecycle (`chat_bridge.py`)
- **Configuration helpers**: argument parsing, `.env` loading, provider/persona selection menus, random/default conversation starters, and persona import from `roles.json` (including fallbacks if missing).
- **Session management**: creates Markdown transcripts with metadata (session ID, start time, starter prompt, agent configs, stop-word status), logs to a rotating global log plus per-session file, and writes every turn into a SQLite database (`bridge.db`).
- **Guards**: detects stop words (with optional weighted density checks) and repetitive loops via `SequenceMatcher` averages to end sessions gracefully.
- **Connectivity toolkit**: async `ping_provider` / `ping_all_providers` functions exercise minimal streaming calls and return diagnostic messages/troubleshooting tips, surfaced via the “Test provider connectivity” menu entry.

## 4. Roles & Configuration Files
- Default `roles.json` schema includes `agent_a`/`agent_b` baselines, `persona_library` entries with provider/model/system/guidelines, shared temperatures, stop words, and a `stop_word_detection_enabled` toggle (backward compatible when missing).
- Helper functions in `chat_bridge.py` and `roles_manager.py` load, validate, save, and toggle these settings while preserving backups.

## 5. Supporting Utilities & Tests
- **`check_port.py`** – Minimal MySQL/MariaDB port reachability checker driven by `.env` values, with troubleshooting hints.
- **`certify.py`, `run_tests.py`, and `test_*.py` scripts** – Automation hooks for verifying stop-word toggles, session display behavior, error handling, etc., typically importing the same loader/saver helpers used by the main app.
- **`start_chat_bridge.sh` / `launch.py`** – Convenience wrappers for launching the bridge with environment activation if desired.

## 6. Data & Logging Artifacts
- Chat sessions produce Markdown transcripts in `chatlogs/`, structured session logs in `logs/`, and SQLite entries in `bridge.db`. Global/system diagnostics are written to `chat_bridge.log` and `chat_bridge_errors.log`.

Keep this document in sync if you extend the project so future contributors understand how the pieces fit together.
