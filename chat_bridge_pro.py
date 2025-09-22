# chat_bridge_pro.py
"""
Chat Bridge Pro — Async, streaming, logged conversation between OpenAI (ChatGPT) and Anthropic (Claude)

What’s new:
  • Auto-saves a human-readable Markdown transcript per session:
      transcripts/<TIMESTAMP>__<STARTER_SLUG>.md
  • Optional per-session file log (logs/<TIMESTAMP>__<STARTER_SLUG>.log)
  • CLI flags: --max-rounds, --mem-rounds, --openai-model, --anthropic-model, --temp-a, --temp-b
  • Retains: SQLite logging, streaming, loop detector, Anthropic messages→completions fallback

Usage:
  python chat_bridge_pro.py --max-rounds 60 --mem-rounds 10
"""

import os
import sys
import re
import json
import sqlite3
import asyncio
import logging
import contextlib
import argparse
from datetime import datetime
from dataclasses import dataclass, field
from typing import AsyncGenerator, Dict, List, Tuple

import httpx
from dotenv import load_dotenv
from difflib import SequenceMatcher

# ───────────────────────── Config / Env ─────────────────────────

load_dotenv()

DEFAULT_OPENAI_MODEL = os.getenv("OPENAI_MODEL", "gpt-4o-mini")
DEFAULT_ANTHROPIC_MODEL = os.getenv("ANTHROPIC_MODEL", "claude-3-5-sonnet")

OPENAI_KEY = os.getenv("OPENAI_API_KEY", "")
ANTHROPIC_KEY = os.getenv("ANTHROPIC_API_KEY", "")

if not OPENAI_KEY or not ANTHROPIC_KEY:
    print("ERROR: Missing OPENAI_API_KEY or ANTHROPIC_API_KEY in environment / .env")
    sys.exit(1)

DB_PATH = "bridge.db"
GLOBAL_LOG = "chat_bridge.log"
TRANSCRIPTS_DIR = "transcripts"
SESSION_LOGS_DIR = "logs"

STOP_WORDS = {"goodbye", "end chat", "terminate", "stop", "that is all"}
STALL_TIMEOUT_SEC = 90

REPEAT_WINDOW = 6
REPEAT_THRESHOLD = 0.92

# ───────────────────────── Utilities ─────────────────────────

def ensure_dirs():
    os.makedirs(TRANSCRIPTS_DIR, exist_ok=True)
    os.makedirs(SESSION_LOGS_DIR, exist_ok=True)

def safe_slug(text: str, max_len: int = 80) -> str:
    # collapse whitespace, remove path-nasties, keep alnum + dash + underscore
    text = " ".join(text.strip().split())
    text = text.lower()
    text = re.sub(r"[^a-z0-9_\- ]+", "", text)
    text = re.sub(r"\s+", "-", text)
    return text[:max_len] if text else "session"

def now_stamp() -> str:
    # 2025-09-22_14-55-12 (file-system friendly, local time)
    return datetime.now().strftime("%Y-%m-%d_%H-%M-%S")

def similar(a: str, b: str) -> float:
    return SequenceMatcher(None, a, b).ratio()

def is_repetitive(history: List[str], window: int = REPEAT_WINDOW, threshold: float = REPEAT_THRESHOLD) -> bool:
    recent = history[-window:]
    if len(recent) < 4:
        return False
    pairs: List[Tuple[str, str]] = []
    for i in range(len(recent) - 1):
        pairs.append((recent[i], recent[i+1]))
    for i in range(len(recent) - 2):
        pairs.append((recent[i], recent[i+2]))
    scores = [similar(x, y) for x, y in pairs]
    if not scores:
        return False
    high = [s for s in scores if s >= threshold]
    return len(high) >= max(2, len(scores) // 2)

def contains_stop_word(text: str) -> bool:
    lower = text.lower()
    return any(sw in lower for sw in STOP_WORDS)

# ───────────────────────── Logging setup ─────────────────────────

def setup_global_logging():
    logging.basicConfig(
        filename=GLOBAL_LOG,
        level=logging.INFO,
        format="%(asctime)s %(levelname)s %(message)s"
    )

def make_session_logger(path: str) -> logging.Logger:
    logger = logging.getLogger(f"session_{os.path.basename(path)}")
    logger.setLevel(logging.INFO)
    # prevent duplicate handlers if rerun in same process
    if not any(isinstance(h, logging.FileHandler) and h.baseFilename == os.path.abspath(path)
               for h in logger.handlers):
        fh = logging.FileHandler(path, encoding="utf-8")
        fh.setFormatter(logging.Formatter("%(asctime)s %(levelname)s %(message)s"))
        logger.addHandler(fh)
    return logger

# ───────────────────────── SQLite ─────────────────────────

def init_db(path: str = DB_PATH) -> None:
    con = sqlite3.connect(path)
    cur = con.cursor()
    cur.execute("""
    CREATE TABLE IF NOT EXISTS conversations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        openai_model TEXT,
        anthropic_model TEXT,
        starter TEXT
    );
    """)
    cur.execute("""
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
    """)
    con.commit()
    con.close()

def create_conversation(openai_model: str, anthropic_model: str, starter: str) -> int:
    con = sqlite3.connect(DB_PATH)
    cur = con.cursor()
    cur.execute(
        "INSERT INTO conversations(openai_model, anthropic_model, starter) VALUES (?, ?, ?);",
        (openai_model, anthropic_model, starter)
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
        (cid, provider, role, content, tokens_est)
    )
    con.commit()
    con.close()

# ───────────────────────── Providers (Streaming) ─────────────────────────

class OpenAIChat:
    """Minimal client for /v1/chat/completions with streaming."""
    def __init__(self, api_key: str, model: str):
        self.key = api_key
        self.model = model
        self.base_url = "https://api.openai.com/v1/chat/completions"
        self.headers = {
            "Authorization": f"Bearer {self.key}",
            "Content-Type": "application/json",
        }

    async def stream(
        self,
        messages: List[Dict[str, str]],
        temperature: float = 0.7,
        max_tokens: int = 800
    ) -> AsyncGenerator[str, None]:
        payload = {
            "model": self.model,
            "messages": messages,
            "temperature": temperature,
            "stream": True,
            "max_tokens": max_tokens
        }
        async with httpx.AsyncClient(timeout=httpx.Timeout(STALL_TIMEOUT_SEC)) as client:
            async with client.stream("POST", self.base_url, headers=self.headers, json=payload) as r:
                r.raise_for_status()
                # capture request id if present
                req_id = r.headers.get("x-request-id") or r.headers.get("request-id")
                if req_id:
                    logging.getLogger("bridge").info(f"OpenAI request-id: {req_id}")
                async for line in r.aiter_lines():
                    if not line:
                        continue
                    if line.startswith("data: "):
                        data = line[6:].strip()
                        if data == "[DONE]":
                            break
                        with contextlib.suppress(Exception):
                            obj = json.loads(data)
                            delta = obj["choices"][0]["delta"].get("content")
                            if delta:
                                yield delta

class AnthropicChat:
    """
    Adaptive Anthropic client:
      - Try /v1/messages with SSE first.
      - On 404/405 (or network fail), fall back to /v1/complete streaming.
    """
    def __init__(self, api_key: str, model: str):
        self.key = api_key
        self.model = model
        self.base_messages = "https://api.anthropic.com/v1/messages"
        self.base_complete = "https://api.anthropic.com/v1/complete"
        self.msg_headers = {
            "x-api-key": self.key,
            "anthropic-version": "2023-06-01",
            "content-type": "application/json",
            "accept": "text/event-stream",
        }
        self.cpl_headers = {
            "x-api-key": self.key,
            "content-type": "application/json",
            "accept": "text/event-stream",
        }

    @staticmethod
    def _to_prompt_from_messages(system_prompt: str, anthro_msgs: List[Dict[str, str]]) -> str:
        lines: List[str] = []
        if system_prompt:
            lines.append(f"[System]: {system_prompt}")
        for item in anthro_msgs:
            role = item.get("role", "user")
            parts = []
            for blk in item.get("content", []):
                if blk.get("type") == "text":
                    parts.append(blk.get("text", ""))
            text = "\n".join(parts).strip()
            if not text:
                continue
            if role == "user":
                lines.append(f"[User (ChatGPT)]: {text}")
            else:
                lines.append(f"[Assistant (Claude)]: {text}")
        lines.append("\n[Assistant (Claude)]:")
        return "\n".join(lines)

    async def _stream_messages(
        self,
        system_prompt: str,
        messages_for_claude: List[Dict[str, str]],
        temperature: float,
        max_tokens: int
    ) -> AsyncGenerator[str, None]:
        payload = {
            "model": self.model,
            "system": system_prompt,
            "max_tokens": max_tokens,
            "temperature": temperature,
            "messages": messages_for_claude,
            "stream": True
        }
        async with httpx.AsyncClient(timeout=httpx.Timeout(STALL_TIMEOUT_SEC)) as client:
            async with client.stream("POST", self.base_messages, headers=self.msg_headers, json=payload) as r:
                if r.status_code in (404, 405):
                    text = await r.aread()
                    raise httpx.HTTPStatusError(
                        "Messages endpoint unsupported; falling back.",
                        request=r.request, response=r
                    )
                r.raise_for_status()
                req_id = r.headers.get("x-request-id") or r.headers.get("request-id")
                if req_id:
                    logging.getLogger("bridge").info(f"Anthropic request-id: {req_id}")
                async for raw in r.aiter_lines():
                    if not raw:
                        continue
                    if raw.startswith("data: "):
                        data = raw[6:].strip()
                        if data == "[DONE]":
                            break
                        with contextlib.suppress(Exception):
                            obj = json.loads(data)
                            if obj.get("type") in ("content_block_delta", "content_block"):
                                delta = obj.get("delta", {})
                                if delta.get("type") == "text_delta":
                                    txt = delta.get("text")
                                    if txt:
                                        yield txt

    async def _stream_complete(
        self,
        prompt: str,
        temperature: float,
        max_tokens: int
    ) -> AsyncGenerator[str, None]:
        payload = {
            "model": self.model,
            "prompt": prompt,
            "max_tokens_to_sample": max_tokens,
            "temperature": temperature,
            "stream": True
        }
        async with httpx.AsyncClient(timeout=httpx.Timeout(STALL_TIMEOUT_SEC)) as client:
            async with client.stream("POST", self.base_complete, headers=self.cpl_headers, json=payload) as r:
                r.raise_for_status()
                req_id = r.headers.get("x-request-id") or r.headers.get("request-id")
                if req_id:
                    logging.getLogger("bridge").info(f"Anthropic request-id (complete): {req_id}")
                async for raw in r.aiter_lines():
                    if not raw:
                        continue
                    if raw.startswith("data: "):
                        data = raw[6:].strip()
                        if data == "[DONE]":
                            break
                        with contextlib.suppress(Exception):
                            obj = json.loads(data)
                            if "completion" in obj:
                                txt = obj.get("completion")
                                if txt:
                                    yield txt
                            elif obj.get("type") == "completion" and "delta" in obj:
                                delta = obj["delta"]
                                if isinstance(delta, str):
                                    yield delta

    async def stream(
        self,
        system_prompt: str,
        messages_for_claude: List[Dict[str, str]],
        temperature: float = 0.7,
        max_tokens: int = 800
    ) -> AsyncGenerator[str, None]:
        try:
            async for piece in self._stream_messages(system_prompt, messages_for_claude, temperature, max_tokens):
                yield piece
            return
        except httpx.HTTPStatusError as e:
            if e.response is None or e.response.status_code not in (404, 405):
                raise
        except httpx.RequestError:
            pass
        prompt = self._to_prompt_from_messages(system_prompt, messages_for_claude)
        async for piece in self._stream_complete(prompt, temperature, max_tokens):
            yield piece

# ───────────────────────── Conversation State ─────────────────────────

@dataclass
class History:
    msgs_openai: List[Dict[str, str]] = field(default_factory=list)
    msgs_anthropic: List[Dict[str, str]] = field(default_factory=list)
    flat_texts: List[str] = field(default_factory=list)

    def add_openai(self, role: str, content: str) -> None:
        self.msgs_openai.append({"role": role, "content": content})
        self.flat_texts.append(content)

    def add_anthropic(self, role: str, content: str) -> None:
        self.msgs_anthropic.append({"role": role, "content": [{"type": "text", "text": content}]})
        self.flat_texts.append(content)

    def tail_openai(self, n: int) -> List[Dict[str, str]]:
        msgs = self.msgs_openai
        if msgs and msgs[0]["role"] == "system":
            head = [msgs[0]]
            body = msgs[1:]
            return head + body[-n:]
        return msgs[-n:]

    def tail_anthropic(self, n: int) -> List[Dict[str, str]]:
        return self.msgs_anthropic[-n:]

# ───────────────────────── Transcript helpers ─────────────────────────

@dataclass
class Transcript:
    # store lines as we go so we don't need to requery DB to write the .md
    lines: List[str] = field(default_factory=list)

    def start(self, conv_id: int, started_at: str, openai_model: str, anthropic_model: str, starter: str):
        self.lines.append(f"# Conversation {conv_id}: {starter}\n")
        self.lines.append(f"*Started at {started_at}*  ")
        self.lines.append(f"*OpenAI model: {openai_model}, Anthropic model: {anthropic_model}*\n")

    def add(self, provider: str, role: str, timestamp: str, text: str):
        who = f"{provider.capitalize()} ({role})"
        self.lines.append(f"**{who}** [{timestamp}]:\n\n{text}\n")

    def dump(self, path: str):
        with open(path, "w", encoding="utf-8") as f:
            f.write("\n\n".join(self.lines))

# ───────────────────────── Streaming collector ─────────────────────────

async def stream_collect(gen: AsyncGenerator[str, None]) -> str:
    chunks: List[str] = []
    async for piece in gen:
        print(piece, end="", flush=True)
        chunks.append(piece)
    print()
    return "".join(chunks).strip()

# ───────────────────────── Main Orchestrator ─────────────────────────

async def run_bridge(args):
    ensure_dirs()
    setup_global_logging()
    init_db()

    # Prompt
    starter = input("Starter prompt (blank = default): ").strip()
    if not starter:
        starter = "Hello Claude—tell ChatGPT a clever riddle and why it matters. Keep it playful."

    # Conversation metadata & filenames
    ts = now_stamp()
    slug = safe_slug(starter)
    md_path = os.path.join(TRANSCRIPTS_DIR, f"{ts}__{slug}.md")
    session_log_path = os.path.join(SESSION_LOGS_DIR, f"{ts}__{slug}.log")
    session_logger = make_session_logger(session_log_path)

    # Clients
    openai = OpenAIChat(OPENAI_KEY, args.openai_model)
    claude = AnthropicChat(ANTHROPIC_KEY, args.anthropic_model)

    # DB row
    cid = create_conversation(args.openai_model, args.anthropic_model, starter)

    # History & transcript header
    history = History()
    history.add_openai("system", "You are ChatGPT. Be concise, helpful, and witty.")
    claude_system = "You are Claude. Be concise, helpful, and witty."

    # record first user message
    history.add_openai("user", starter)
    log_message_sql(cid, "openai", "user", starter)

    # transcript header
    started_at = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    transcript = Transcript()
    transcript.start(cid, started_at, args.openai_model, args.anthropic_model, starter)
    transcript.add("openai", "user", started_at, starter)

    current = "openai"
    rounds = 0
    bridge_logger = logging.getLogger("bridge")

    print(f"\nTranscript will be saved to: {md_path}")
    print(f"Session log: {session_log_path}\n")

    try:
        while rounds < args.max_rounds:
            rounds += 1
            print(f"\n—— Round {rounds} ——")

            if is_repetitive(history.flat_texts):
                print("⚠️  Loop detected. Ending chat before we bore ourselves to tears.")
                break

            if current == "openai":
                print("[OpenAI] ", end="", flush=True)
                messages = history.tail_openai(args.mem_rounds)
                gen = openai.stream(messages, temperature=args.temp_a)
                reply = await asyncio.wait_for(stream_collect(gen), timeout=STALL_TIMEOUT_SEC)
                if not reply:
                    print("…(no reply from OpenAI)")
                    break

                log_message_sql(cid, "openai", "assistant", reply)
                history.add_openai("assistant", reply)
                history.add_anthropic("user", reply)

                nowt = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
                transcript.add("openai", "assistant", nowt, reply)
                session_logger.info(f"OpenAI assistant: {reply[:5000]}")

                if contains_stop_word(reply):
                    print("🛑 Stop word detected (OpenAI). Ending chat.")
                    break

                current = "anthropic"

            else:
                print("[Anthropic] ", end="", flush=True)
                anthro_msgs = history.tail_anthropic(args.mem_rounds)
                gen = claude.stream(
                    system_prompt=claude_system,
                    messages_for_claude=anthro_msgs,
                    temperature=args.temp_b
                )
                reply = await asyncio.wait_for(stream_collect(gen), timeout=STALL_TIMEOUT_SEC)
                if not reply:
                    print("…(no reply from Anthropic)")
                    break

                log_message_sql(cid, "anthropic", "assistant", reply)
                history.add_anthropic("assistant", reply)
                history.add_openai("user", reply)

                nowt = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
                transcript.add("anthropic", "assistant", nowt, reply)
                session_logger.info(f"Anthropic assistant: {reply[:5000]}")

                if contains_stop_word(reply):
                    print("🛑 Stop word detected (Anthropic). Ending chat.")
                    break

                current = "openai"

            if is_repetitive(history.flat_texts):
                print("⚠️  Loop detected after this round. Calling it.")
                break

        print(f"\nConversation finished.\nMarkdown: {md_path}\nLogs: {session_log_path}  |  Global: {GLOBAL_LOG}")

    except KeyboardInterrupt:
        print("\nInterrupted by you. Cheerio.")
    except asyncio.TimeoutError:
        print("\n⏱️  A provider stalled beyond timeout. Stopping.")
    except Exception as e:
        bridge_logger.exception("Unhandled error: %s", e)
        print(f"\nUnexpected error: {e}")
    finally:
        # Always write the transcript at the end (even if cut short)
        try:
            transcript.dump(md_path)
        except Exception as e:
            print(f"Failed to write transcript: {e}")

# ───────────────────────── CLI ─────────────────────────

def parse_args():
    p = argparse.ArgumentParser(description="OpenAI ↔ Anthropic chat bridge with per-session Markdown transcripts.")
    p.add_argument("--max-rounds", type=int, default=30, help="Max total replies across both agents.")
    p.add_argument("--mem-rounds", type=int, default=8, help="How many recent exchanges each side sees.")
    p.add_argument("--openai-model", type=str, default=DEFAULT_OPENAI_MODEL, help="OpenAI chat model.")
    p.add_argument("--anthropic-model", type=str, default=DEFAULT_ANTHROPIC_MODEL, help="Anthropic model.")
    p.add_argument("--temp-a", type=float, default=0.7, help="Temperature for OpenAI.")
    p.add_argument("--temp-b", type=float, default=0.7, help="Temperature for Anthropic.")
    return p.parse_args()

# ───────────────────────── Entry ─────────────────────────

if __name__ == "__main__":
    args = parse_args()
    asyncio.run(run_bridge(args))
