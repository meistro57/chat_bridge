# chat_bridge_roles.py
"""
Chat Bridge (Roles Edition) — bring two configurable AI personas into dialogue.

Highlights:
  • Reads a roles JSON file to set providers, models, system prompts, and guard rails.
  • Supports OpenAI, Anthropic, Gemini, Ollama, and LM Studio on either side.
  • Interactive provider/model picker plus CLI overrides for scripted runs.
  • Persists transcripts, SQLite logs, and per-session log files.
  • Shares provider adapters and versioning with the pro edition.
"""

from __future__ import annotations

import argparse
import asyncio
import contextlib
import json
import logging
import os
import re
import sqlite3
import sys
from dataclasses import dataclass, field
from datetime import datetime
from difflib import SequenceMatcher
from typing import AsyncGenerator, Dict, Iterable, List, Optional, Tuple

from dotenv import load_dotenv

from bridge_agents import (
    AgentRuntime,
    Turn,
    STALL_TIMEOUT_SEC,
    create_agent,
    get_spec,
    provider_choices,
    resolve_model,
)
from version import __version__


# ───────────────────────── Config / Env ─────────────────────────

load_dotenv()

DEFAULT_PROVIDER_A = os.getenv("BRIDGE_PROVIDER_A", "openai")
DEFAULT_PROVIDER_B = os.getenv("BRIDGE_PROVIDER_B", "anthropic")

STOP_WORDS_DEFAULT = {"goodbye", "end chat", "terminate", "stop", "that is all"}
REPEAT_WINDOW = 6
REPEAT_THRESHOLD = 0.92

DB_PATH = "bridge.db"
GLOBAL_LOG = "chat_bridge.log"
TRANSCRIPTS_DIR = "transcripts"
SESSION_LOGS_DIR = "logs"

DEFAULT_ROLES = {
    "agent_a": {
        "provider": "openai",
        "model": None,
        "system": "You are ChatGPT. Be concise, truthful, and witty.",
        "guidelines": [
            "Cite sources or examples when you make factual claims.",
            "Favour clear structure and highlight key takeaways early.",
        ],
    },
    "agent_b": {
        "provider": "anthropic",
        "model": None,
        "system": "You are Claude. Be nuanced, compassionate, and rigorous.",
        "guidelines": [
            "Surface competing perspectives fairly before choosing a stance.",
            "Make uncertainty explicit and flag assumptions.",
        ],
    },
    "stop_words": ["wrap up", "end chat", "terminate"],
    "temp_a": 0.7,
    "temp_b": 0.7,
}


# ───────────────────────── Utilities ─────────────────────────


def ensure_dirs() -> None:
    os.makedirs(TRANSCRIPTS_DIR, exist_ok=True)
    os.makedirs(SESSION_LOGS_DIR, exist_ok=True)


def safe_slug(text: str, max_len: int = 80) -> str:
    text = " ".join(text.strip().split())
    text = text.lower()
    text = re.sub(r"[^a-z0-9_\- ]+", "", text)
    text = re.sub(r"\s+", "-", text)
    return text[:max_len] if text else "session"


def now_stamp() -> str:
    return datetime.now().strftime("%Y-%m-%d_%H-%M-%S")


def similar(a: str, b: str) -> float:
    return SequenceMatcher(None, a, b).ratio()


def is_repetitive(history: List[str], window: int = REPEAT_WINDOW, threshold: float = REPEAT_THRESHOLD) -> bool:
    recent = history[-window:]
    if len(recent) < 4:
        return False
    pairs: List[Tuple[str, str]] = []
    for i in range(len(recent) - 1):
        pairs.append((recent[i], recent[i + 1]))
    for i in range(len(recent) - 2):
        pairs.append((recent[i], recent[i + 2]))
    scores = [similar(x, y) for x, y in pairs]
    if not scores:
        return False
    high = [s for s in scores if s >= threshold]
    return len(high) >= max(2, len(scores) // 2)


def contains_stop_word(text: str, stop_words: Iterable[str]) -> bool:
    lower = text.lower()
    return any(sw in lower for sw in stop_words)


# ───────────────────────── Logging ─────────────────────────


def setup_global_logging() -> None:
    logging.basicConfig(
        filename=GLOBAL_LOG,
        level=logging.INFO,
        format="%(asctime)s %(levelname)s %(message)s",
    )


def make_session_logger(path: str) -> logging.Logger:
    logger = logging.getLogger(f"session_{os.path.basename(path)}")
    logger.setLevel(logging.INFO)
    if not any(
        isinstance(h, logging.FileHandler) and h.baseFilename == os.path.abspath(path)
        for h in logger.handlers
    ):
        handler = logging.FileHandler(path, encoding="utf-8")
        handler.setFormatter(logging.Formatter("%(asctime)s %(levelname)s %(message)s"))
        logger.addHandler(handler)
    return logger


# ───────────────────────── SQLite ─────────────────────────


def init_db(path: str = DB_PATH) -> None:
    con = sqlite3.connect(path)
    cur = con.cursor()
    cur.execute(
        """
        CREATE TABLE IF NOT EXISTS conversations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            openai_model TEXT,
            anthropic_model TEXT,
            starter TEXT
        );
        """
    )
    cur.execute(
        """
        CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            conversation_id INTEGER,
            provider TEXT,
            role TEXT,
            content TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            tokens_est INTEGER,
            FOREIGN KEY(conversation_id) REFERENCES conversations(id)
        );
        """
    )
    con.commit()
    con.close()


def create_conversation(agent_a: AgentRuntime, agent_b: AgentRuntime, starter: str) -> int:
    con = sqlite3.connect(DB_PATH)
    cur = con.cursor()
    cur.execute(
        "INSERT INTO conversations(openai_model, anthropic_model, starter) VALUES (?, ?, ?);",
        (agent_a.identifier, agent_b.identifier, starter),
    )
    con.commit()
    cid = cur.lastrowid
    con.close()
    return cid


def log_message_sql(cid: int, provider: str, role: str, content: str) -> None:
    con = sqlite3.connect(DB_PATH)
    cur = con.cursor()
    tokens_est = max(1, len(content.split()))
    cur.execute(
        "INSERT INTO messages(conversation_id, provider, role, content, tokens_est) VALUES (?, ?, ?, ?, ?);",
        (cid, provider, role, content, tokens_est),
    )
    con.commit()
    con.close()


# ───────────────────────── Roles handling ─────────────────────────


def write_default_roles(path: str) -> None:
    with open(path, "w", encoding="utf-8") as handle:
        json.dump(DEFAULT_ROLES, handle, indent=2)


def clean_override(value: Optional[str]) -> Optional[str]:
    if isinstance(value, str) and value.strip():
        return value.strip()
    return None


def convert_legacy_roles(data: Dict) -> Dict:
    converted = {"agent_a": {}, "agent_b": {}}
    openai_cfg = data.get("openai", {})
    anthro_cfg = data.get("anthropic", {})
    converted["agent_a"].update(
        {
            "provider": "openai",
            "system": openai_cfg.get("system"),
            "guidelines": openai_cfg.get("guidelines"),
        }
    )
    converted["agent_b"].update(
        {
            "provider": "anthropic",
            "system": anthro_cfg.get("system"),
            "guidelines": anthro_cfg.get("guidelines"),
        }
    )
    if "stop_words" in data:
        converted["stop_words"] = data["stop_words"]
    if "temp_a" in data:
        converted["temp_a"] = data["temp_a"]
    if "temp_b" in data:
        converted["temp_b"] = data["temp_b"]
    return converted


def merge_roles(data: Dict) -> Dict:
    merged = json.loads(json.dumps(DEFAULT_ROLES))  # deep copy via json
    merged.update({k: v for k, v in data.items() if k not in {"agent_a", "agent_b"}})
    for agent_key in ("agent_a", "agent_b"):
        merged_agent = merged[agent_key]
        incoming = data.get(agent_key, {})
        if incoming:
            merged_agent.update({k: v for k, v in incoming.items() if v is not None})
        merged_agent.setdefault("guidelines", [])
    return merged


def load_roles(path: str) -> Dict:
    if not os.path.exists(path):
        write_default_roles(path)
        return json.loads(json.dumps(DEFAULT_ROLES))
    with open(path, "r", encoding="utf-8") as handle:
        raw = json.load(handle)
    if "agent_a" not in raw and "agent_b" not in raw:
        raw = convert_legacy_roles(raw)
    return merge_roles(raw)


def combine_system(system: Optional[str], guidelines: Iterable[str]) -> str:
    base = (system or "").strip()
    extras = [g.strip() for g in guidelines if isinstance(g, str) and g.strip()]
    if extras:
        extra_text = "\n".join(f"- {g}" for g in extras)
        base = f"{base}\n\n{extra_text}" if base else extra_text
    return base or "You are an AI assistant."


# ───────────────────────── Conversation helpers ─────────────────────────


@dataclass
class ConversationHistory:
    turns: List[Turn] = field(default_factory=list)
    flat_texts: List[str] = field(default_factory=list)

    def add_turn(self, author: str, text: str) -> None:
        self.turns.append(Turn(author=author, text=text))
        self.flat_texts.append(text)


@dataclass
class Transcript:
    lines: List[str] = field(default_factory=list)

    def start(self, conv_id: int, started_at: str, starter: str, agent_a: AgentRuntime, agent_b: AgentRuntime) -> None:
        self.lines.append(f"# Conversation {conv_id}: {starter}\n")
        self.lines.append(f"*Started at {started_at}*  ")
        self.lines.append(
            f"*{agent_tag(agent_a)}: {agent_a.model} | {agent_tag(agent_b)}: {agent_b.model}*\n"
        )

    def add(self, actor_label: str, role: str, timestamp: str, text: str) -> None:
        who = f"{actor_label} ({role})"
        self.lines.append(f"**{who}** [{timestamp}]:\n\n{text}\n")

    def dump(self, path: str) -> None:
        with open(path, "w", encoding="utf-8") as handle:
            handle.write("\n\n".join(self.lines))


async def stream_collect(gen: AsyncGenerator[str, None]) -> str:
    chunks: List[str] = []
    async for piece in gen:
        print(piece, end="", flush=True)
        chunks.append(piece)
    print()
    return "".join(chunks).strip()


def agent_tag(agent: AgentRuntime) -> str:
    side = "A" if agent.agent_id == "a" else "B"
    return f"Agent {side} – {agent.label}"


def list_providers() -> List[str]:
    return provider_choices()


def parse_provider_choice(value: str, options: List[str]) -> Optional[str]:
    cleaned = value.strip().lower()
    if not cleaned:
        return None
    if cleaned.isdigit():
        idx = int(cleaned) - 1
        if 0 <= idx < len(options):
            return options[idx]
        return None
    if cleaned in options:
        return cleaned
    return None


def interactive_provider_selection(
    provider_a: str,
    provider_b: str,
    model_a: str,
    model_b: str,
) -> Tuple[str, str, str, str]:
    options = list_providers()
    print("\nAvailable providers:")
    for idx, key in enumerate(options, start=1):
        spec = get_spec(key)
        print(f"  {idx}) {spec.label:<9} – default model: {spec.default_model} | {spec.description}")

    spec_a = get_spec(provider_a)
    spec_b = get_spec(provider_b)
    print(
        f"Current selection → Agent A: {spec_a.label} ({model_a}), Agent B: {spec_b.label} ({model_b})"
    )
    choice = input("Choose providers as 'A,B' or press Enter to keep: ").strip()

    prev_a, prev_b = provider_a, provider_b
    if choice:
        parts = [p.strip() for p in choice.split(",") if p.strip()]
        if len(parts) == 2:
            maybe_a = parse_provider_choice(parts[0], options)
            maybe_b = parse_provider_choice(parts[1], options)
            if maybe_a:
                provider_a = maybe_a
            else:
                print("⚠️  Invalid Agent A provider selection; keeping previous value.")
            if maybe_b:
                provider_b = maybe_b
            else:
                print("⚠️  Invalid Agent B provider selection; keeping previous value.")
        else:
            print("⚠️  Expected two comma-separated choices. Keeping existing providers.")

    if provider_a != prev_a:
        model_a = resolve_model(provider_a, override=None, agent_env="BRIDGE_MODEL_A")
        print(f"Agent A switched to {get_spec(provider_a).label}. Using model {model_a}.")
    if provider_b != prev_b:
        model_b = resolve_model(provider_b, override=None, agent_env="BRIDGE_MODEL_B")
        print(f"Agent B switched to {get_spec(provider_b).label}. Using model {model_b}.")

    override_a = input(f"Agent A model override [{model_a}]: ").strip()
    if override_a:
        model_a = override_a
    override_b = input(f"Agent B model override [{model_b}]: ").strip()
    if override_b:
        model_b = override_b

    return provider_a, provider_b, model_a, model_b


# ───────────────────────── Runner ─────────────────────────


async def run_bridge(args) -> None:
    ensure_dirs()
    setup_global_logging()
    init_db()

    roles = load_roles(args.roles)

    role_provider_a = clean_override(roles["agent_a"].get("provider"))
    role_provider_b = clean_override(roles["agent_b"].get("provider"))

    provider_a = args.provider_a if args.provider_a != DEFAULT_PROVIDER_A else (role_provider_a or args.provider_a)
    provider_b = args.provider_b if args.provider_b != DEFAULT_PROVIDER_B else (role_provider_b or args.provider_b)

    role_model_a = clean_override(roles["agent_a"].get("model"))
    role_model_b = clean_override(roles["agent_b"].get("model"))

    model_a = resolve_model(provider_a, override=args.model_a or role_model_a, agent_env="BRIDGE_MODEL_A")
    model_b = resolve_model(provider_b, override=args.model_b or role_model_b, agent_env="BRIDGE_MODEL_B")

    temp_a = float(roles.get("temp_a", args.temp_a))
    temp_b = float(roles.get("temp_b", args.temp_b))

    stop_words = set(STOP_WORDS_DEFAULT)
    if isinstance(roles.get("stop_words"), list):
        stop_words |= {w.strip().lower() for w in roles["stop_words"] if isinstance(w, str) and w.strip()}

    sys_a = combine_system(roles["agent_a"].get("system"), roles["agent_a"].get("guidelines", []))
    sys_b = combine_system(roles["agent_b"].get("system"), roles["agent_b"].get("guidelines", []))

    starter = input("Starter prompt (blank = default): ").strip()
    if not starter:
        starter = "Compare how different traditions define awakening. Cite disagreements and convergences."

    provider_a, provider_b, model_a, model_b = interactive_provider_selection(
        provider_a, provider_b, model_a, model_b
    )

    try:
        agent_a = create_agent("a", provider_a, model_a, temp_a, sys_a)
        agent_b = create_agent("b", provider_b, model_b, temp_b, sys_b)
    except RuntimeError as exc:
        print(f"ERROR: {exc}")
        sys.exit(1)

    ts = now_stamp()
    slug = safe_slug(starter)
    md_path = os.path.join(TRANSCRIPTS_DIR, f"{ts}__{slug}.md")
    session_log_path = os.path.join(SESSION_LOGS_DIR, f"{ts}__{slug}.log")
    session_logger = make_session_logger(session_log_path)

    cid = create_conversation(agent_a, agent_b, starter)

    history = ConversationHistory()
    history.add_turn("human", starter)
    log_message_sql(cid, agent_a.provider_key, "user", starter)

    started_at = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    transcript = Transcript()
    transcript.start(cid, started_at, starter, agent_a, agent_b)
    transcript.add(agent_tag(agent_a), "user", started_at, starter)

    print(f"\nChat Bridge Roles v{__version__}")
    print(f"Roles loaded from: {os.path.abspath(args.roles)}")
    print(f"Agent A system preview: {sys_a[:140]!r}")
    print(f"Agent B system preview: {sys_b[:140]!r}")
    print(f"Transcript: {md_path}\nSession log: {session_log_path}\n")

    agents: Dict[str, AgentRuntime] = {"a": agent_a, "b": agent_b}
    current_id = "a"
    rounds = 0
    bridge_logger = logging.getLogger("bridge")

    try:
        while rounds < args.max_rounds:
            rounds += 1
            print(f"\n—— Round {rounds} ——")

            if is_repetitive(history.flat_texts):
                print("⚠️  Loop detected. Stopping.")
                break

            agent = agents[current_id]
            label = agent_tag(agent)
            print(f"[{label}] ", end="", flush=True)

            gen = agent.stream_reply(history.turns, args.mem_rounds)
            try:
                reply = await asyncio.wait_for(stream_collect(gen), timeout=STALL_TIMEOUT_SEC)
            except asyncio.TimeoutError:
                print("…timeout waiting for response.")
                break

            if not reply:
                print("…(no reply)")
                break

            log_message_sql(cid, agent.provider_key, "assistant", reply)
            history.add_turn(agent.agent_id, reply)

            nowt = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            transcript.add(label, "assistant", nowt, reply)
            session_logger.info(f"{label}: {reply[:5000]}")

            if contains_stop_word(reply, stop_words):
                print("🛑 Stop word detected. Ending chat.")
                break

            current_id = "b" if current_id == "a" else "a"

            if is_repetitive(history.flat_texts):
                print("⚠️  Loop detected after this round. Stopping.")
                break

        print(
            f"\nFinished.\nMarkdown: {md_path}\nLogs: {session_log_path}  |  Global: {GLOBAL_LOG}"
        )

    except KeyboardInterrupt:
        print("\nInterrupted by you. Cheerio.")
    except Exception as exc:
        bridge_logger.exception("Unhandled error: %s", exc)
        print(f"\nUnexpected error: {exc}")
    finally:
        with contextlib.suppress(Exception):
            transcript.dump(md_path)


# ───────────────────────── CLI ─────────────────────────


def parse_args():
    choices = list_providers()
    parser = argparse.ArgumentParser(
        description="Role-driven Chat Bridge supporting multiple AI providers."
    )
    parser.add_argument("--roles", type=str, default="roles.json", help="Path to roles JSON file.")
    parser.add_argument("--max-rounds", type=int, default=30, help="Max total replies across both agents.")
    parser.add_argument("--mem-rounds", type=int, default=8, help="How many recent turns each side sees.")
    parser.add_argument(
        "--provider-a",
        choices=choices,
        default=DEFAULT_PROVIDER_A,
        help="Provider override for Agent A.",
    )
    parser.add_argument(
        "--provider-b",
        choices=choices,
        default=DEFAULT_PROVIDER_B,
        help="Provider override for Agent B.",
    )
    parser.add_argument(
        "--model-a",
        dest="model_a",
        type=str,
        default=None,
        help="Model override for Agent A.",
    )
    parser.add_argument(
        "--model-b",
        dest="model_b",
        type=str,
        default=None,
        help="Model override for Agent B.",
    )
    parser.add_argument(
        "--openai-model",
        dest="model_a",
        type=str,
        default=None,
        help="Alias for --model-a (legacy).",
    )
    parser.add_argument(
        "--anthropic-model",
        dest="model_b",
        type=str,
        default=None,
        help="Alias for --model-b (legacy).",
    )
    parser.add_argument(
        "--temp-a",
        type=float,
        default=0.7,
        help="Temperature for Agent A (overridden by roles temp_a).",
    )
    parser.add_argument(
        "--temp-b",
        type=float,
        default=0.7,
        help="Temperature for Agent B (overridden by roles temp_b).",
    )
    parser.add_argument("--version", action="version", version=f"%(prog)s {__version__}")
    return parser.parse_args()


# ───────────────────────── Entry ─────────────────────────


if __name__ == "__main__":
    arguments = parse_args()
    asyncio.run(run_bridge(arguments))

