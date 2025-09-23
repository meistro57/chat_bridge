# chat_bridge_pro.py
"""
Chat Bridge Pro — stream two AI assistants through a single console.

What's new in this release?
  • Choose between OpenAI, Anthropic, Gemini, Ollama, or LM Studio for either side.
  • Latest "turbo" defaults per provider (e.g. GPT-4.1 Mini, Claude 3.5 Sonnet, Gemini 1.5 Pro).
  • Interactive multi-choice selector at runtime plus backwards-compatible CLI flags.
  • Shared provider adapters with graceful streaming fallbacks and local-host support.
  • Project-wide semantic versioning exposed via --version.
"""

from __future__ import annotations

import argparse
import asyncio
import contextlib
import logging
import os
import re
import sqlite3
import sys
from dataclasses import dataclass, field
from datetime import datetime
from difflib import SequenceMatcher
from typing import AsyncGenerator, Dict, Iterable, List, Tuple

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

STOP_WORDS = {"goodbye", "end chat", "terminate", "stop", "that is all"}
REPEAT_WINDOW = 6
REPEAT_THRESHOLD = 0.92

DB_PATH = "bridge.db"
GLOBAL_LOG = "chat_bridge.log"
TRANSCRIPTS_DIR = "transcripts"
SESSION_LOGS_DIR = "logs"


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
        tag_a = agent_tag(agent_a)
        tag_b = agent_tag(agent_b)
        self.lines.append(f"# Conversation {conv_id}: {starter}\n")
        self.lines.append(f"*Started at {started_at}*  ")
        self.lines.append(
            f"*{tag_a}: {agent_a.model} | {tag_b}: {agent_b.model}*\n"
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


def resolve_system_prompt(provider_key: str, agent_id: str) -> str:
    env_name = "BRIDGE_SYSTEM_A" if agent_id == "a" else "BRIDGE_SYSTEM_B"
    env_val = os.getenv(env_name)
    if env_val and env_val.strip():
        return env_val.strip()
    return get_spec(provider_key).default_system


def agent_tag(agent: AgentRuntime) -> str:
    side = "A" if agent.agent_id == "a" else "B"
    return f"Agent {side} – {agent.label}"


def list_providers() -> List[str]:
    return provider_choices()


def parse_provider_choice(value: str, options: List[str]) -> str | None:
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

    provider_a = args.provider_a
    provider_b = args.provider_b
    model_a = resolve_model(provider_a, override=args.model_a, agent_env="BRIDGE_MODEL_A")
    model_b = resolve_model(provider_b, override=args.model_b, agent_env="BRIDGE_MODEL_B")

    starter = input("Starter prompt (blank = default): ").strip()
    if not starter:
        starter = "Hello—have each model trade their sharpest teaching trick and critique it."

    provider_a, provider_b, model_a, model_b = interactive_provider_selection(
        provider_a, provider_b, model_a, model_b
    )

    system_a = resolve_system_prompt(provider_a, "a")
    system_b = resolve_system_prompt(provider_b, "b")

    try:
        agent_a = create_agent("a", provider_a, model_a, args.temp_a, system_a)
        agent_b = create_agent("b", provider_b, model_b, args.temp_b, system_b)
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

    print(f"\nChat Bridge v{__version__}")
    print(f"Transcript: {md_path}")
    print(f"Session log: {session_log_path}\n")

    agents: Dict[str, AgentRuntime] = {"a": agent_a, "b": agent_b}
    current_id = "a"
    rounds = 0
    bridge_logger = logging.getLogger("bridge")

    try:
        while rounds < args.max_rounds:
            rounds += 1
            print(f"\n—— Round {rounds} ——")

            if is_repetitive(history.flat_texts):
                print("⚠️  Loop detected. Ending chat before it spirals.")
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

            if contains_stop_word(reply, STOP_WORDS):
                print("🛑 Stop word detected. Ending chat.")
                break

            other_id = "b" if current_id == "a" else "a"
            current_id = other_id

            if is_repetitive(history.flat_texts):
                print("⚠️  Loop detected after this round. Calling it.")
                break

        print(
            f"\nConversation finished.\nMarkdown: {md_path}\nLogs: {session_log_path}  |  Global: {GLOBAL_LOG}"
        )

    except KeyboardInterrupt:
        print("\nInterrupted by user. Until next time.")
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
        description="Stream conversations between two AI providers with logging and transcripts."
    )
    parser.add_argument("--max-rounds", type=int, default=30, help="Max total replies across both agents.")
    parser.add_argument("--mem-rounds", type=int, default=8, help="How many recent turns each side sees.")
    parser.add_argument(
        "--provider-a",
        choices=choices,
        default=DEFAULT_PROVIDER_A,
        help="Provider for Agent A (first speaker).",
    )
    parser.add_argument(
        "--provider-b",
        choices=choices,
        default=DEFAULT_PROVIDER_B,
        help="Provider for Agent B (second speaker).",
    )
    parser.add_argument(
        "--model-a",
        dest="model_a",
        type=str,
        default=None,
        help="Model override for Agent A (defaults follow provider turbo tiers).",
    )
    parser.add_argument(
        "--model-b",
        dest="model_b",
        type=str,
        default=None,
        help="Model override for Agent B (defaults follow provider turbo tiers).",
    )
    parser.add_argument(
        "--openai-model",
        dest="model_a",
        type=str,
        default=None,
        help="Alias for --model-a (backwards compatibility).",
    )
    parser.add_argument(
        "--anthropic-model",
        dest="model_b",
        type=str,
        default=None,
        help="Alias for --model-b (backwards compatibility).",
    )
    parser.add_argument("--temp-a", type=float, default=0.7, help="Sampling temperature for Agent A.")
    parser.add_argument("--temp-b", type=float, default=0.7, help="Sampling temperature for Agent B.")
    parser.add_argument("--version", action="version", version=f"%(prog)s {__version__}")
    return parser.parse_args()


# ───────────────────────── Entry ─────────────────────────


if __name__ == "__main__":
    arguments = parse_args()
    asyncio.run(run_bridge(arguments))

