# chat_bridge_roles_gemini.py
"""
Chat Bridge (Roles Edition) — OpenAI ↔ Anthropic/Gemini with role file support

What you get (vs. chat_bridge_roles.py):
  • Adds Google Gemini as a drop‑in alternative to Claude
  • Choose the 2nd provider via --provider-b (anthropic | gemini)
  • Reads roles.json (now with optional "google" persona)
  • Same goodies: streaming, transcripts, SQLite logs, loop detector

Usage examples:
  python chat_bridge_roles_gemini.py --roles roles.json --provider-b gemini
  python chat_bridge_roles_gemini.py --roles roles.json --provider-b anthropic

Env:
  OPENAI_API_KEY=...
  ANTHROPIC_API_KEY=...        (if using Claude)
  GOOGLE_API_KEY=...           (if using Gemini)
  OPENAI_MODEL=gpt-4o-mini     (override as you like)
  ANTHROPIC_MODEL=claude-3-5-sonnet
  GOOGLE_MODEL=gemini-1.5-pro
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
from typing import AsyncGenerator, Dict, List, Tuple, Optional

import httpx
from dotenv import load_dotenv
from difflib import SequenceMatcher

# ───────────────────────── Env ─────────────────────────
load_dotenv()

OPENAI_KEY = os.getenv("OPENAI_API_KEY", "")
ANTHROPIC_KEY = os.getenv("ANTHROPIC_API_KEY", "")
GOOGLE_KEY = os.getenv("GOOGLE_API_KEY", "")

DEFAULT_OPENAI_MODEL = os.getenv("OPENAI_MODEL", "gpt-4o-mini")
DEFAULT_ANTHROPIC_MODEL = os.getenv("ANTHROPIC_MODEL", "claude-3-5-sonnet")
DEFAULT_GOOGLE_MODEL = os.getenv("GOOGLE_MODEL", "gemini-1.5-pro")

if not OPENAI_KEY:
    print("ERROR: Missing OPENAI_API_KEY in environment/.env")
    sys.exit(1)

# ───────────────────────── Paths ─────────────────────────
DB_PATH = "bridge.db"
GLOBAL_LOG = "chat_bridge.log"
TRANSCRIPTS_DIR = "transcripts"
SESSION_LOGS_DIR = "logs"

# ───────────────────────── Defaults ─────────────────────────
STOP_WORDS_DEFAULT = {"goodbye", "end chat", "terminate", "stop", "that is all"}
STALL_TIMEOUT_SEC = 90
REPEAT_WINDOW = 6
REPEAT_THRESHOLD = 0.92

DEFAULT_ROLES = {
    "openai": {
        "system": "You are ChatGPT. Be concise, helpful, truthful, and witty. Prioritise verifiable facts and clear reasoning.",
        "guidelines": [
            "If asked for probabilities, explain your methodology and uncertainty.",
            "Prefer examples and citations where appropriate.",
        ],
    },
    "anthropic": {
        "system": "You are Claude. Be concise, compassionate, truthful, and reflective. Balance clarity with nuance.",
        "guidelines": [
            "Surface connections across traditions and viewpoints where helpful.",
            "Avoid speculation without stating uncertainty and alternatives.",
        ],
    },
    "google": {
        "system": "You are Gemini. Be crisp, insightful, and creative, with practical structure and clear tradeoffs.",
        "guidelines": [
            "Prefer stepwise structure and bullet summaries.",
            "Flag uncertainty and cite sources when appropriate.",
        ],
    },
}

# ───────────────────────── Utilities ─────────────────────────

def ensure_dirs():
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

# ───────────────────────── Roles loader ─────────────────────────

def load_roles(path: str) -> Dict:
    roles = {}
    if not os.path.exists(path):
        with open(path, "w", encoding="utf-8") as f:
            json.dump(DEFAULT_ROLES, f, indent=2)
        roles = DEFAULT_ROLES
    else:
        with open(path, "r", encoding="utf-8") as f:
            roles = json.load(f)

    merged = DEFAULT_ROLES.copy()
    merged.update(roles or {})
    for key in ("openai", "anthropic", "google"):
        if key not in merged:
            merged[key] = DEFAULT_ROLES[key]
        merged[key].setdefault("system", DEFAULT_ROLES[key]["system"])
        merged[key].setdefault("guidelines", DEFAULT_ROLES[key]["guidelines"])
    return merged

def combine_system(system: str, guidelines: List[str]) -> str:
    if not guidelines:
        return system
    return system.strip() + "\n\n" + "\n".join(f"- {g}" for g in guidelines)

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
        provider_b TEXT,
        model_b TEXT,
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


def create_conversation(openai_model: str, provider_b: str, model_b: str, starter: str) -> int:
    con = sqlite3.connect(DB_PATH)
    cur = con.cursor()
    cur.execute(
        "INSERT INTO conversations(openai_model, provider_b, model_b, starter) VALUES (?, ?, ?, ?);",
        (openai_model, provider_b, model_b, starter),
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

# ───────────────────────── Providers (streaming) ─────────────────────────

class OpenAIChat:
    def __init__(self, api_key: str, model: str):
        self.key = api_key
        self.model = model
        self.base_url = "https://api.openai.com/v1/chat/completions"
        self.headers = {"Authorization": f"Bearer {self.key}", "Content-Type": "application/json"}

    async def stream(self, messages: List[Dict[str, str]], temperature: float = 0.7, max_tokens: int = 800) -> AsyncGenerator[str, None]:
        payload = {
            "model": self.model,
            "messages": messages,
            "temperature": temperature,
            "stream": True,
            "max_tokens": max_tokens,
        }
        async with httpx.AsyncClient(timeout=httpx.Timeout(STALL_TIMEOUT_SEC)) as client:
            async with client.stream("POST", self.base_url, headers=self.headers, json=payload) as r:
                r.raise_for_status()
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

    async def _stream_messages(self, system_prompt: str, messages_for_claude: List[Dict[str, str]], temperature: float, max_tokens: int) -> AsyncGenerator[str, None]:
        payload = {
            "model": self.model,
            "system": system_prompt,
            "max_tokens": max_tokens,
            "temperature": temperature,
            "messages": messages_for_claude,
            "stream": True,
        }
        async with httpx.AsyncClient(timeout=httpx.Timeout(STALL_TIMEOUT_SEC)) as client:
            async with client.stream("POST", self.base_messages, headers=self.msg_headers, json=payload) as r:
                if r.status_code in (404, 405):
                    raise httpx.HTTPStatusError("Messages unsupported; fallback.", request=r.request, response=r)
                r.raise_for_status()
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

    async def _stream_complete(self, prompt: str, temperature: float, max_tokens: int) -> AsyncGenerator[str, None]:
        payload = {
            "model": self.model,
            "prompt": prompt,
            "max_tokens_to_sample": max_tokens,
            "temperature": temperature,
            "stream": True,
        }
        async with httpx.AsyncClient(timeout=httpx.Timeout(STALL_TIMEOUT_SEC)) as client:
            async with client.stream("POST", self.base_complete, headers=self.cpl_headers, json=payload) as r:
                r.raise_for_status()
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

    async def stream(self, system_prompt: str, messages_for_claude: List[Dict[str, str]], temperature: float = 0.7, max_tokens: int = 800) -> AsyncGenerator[str, None]:
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

class GeminiChat:
    """Minimal Gemini client using Google Generative Language REST API.
    We keep it simple (non‑SSE); we "stream" by chunking the final text.
    """

    def __init__(self, api_key: str, model: str):
        if not api_key:
            raise RuntimeError("GOOGLE_API_KEY missing but Gemini selected")
        self.key = api_key
        self.model = model
        self.base = f"https://generativelanguage.googleapis.com/v1beta/models/{self.model}:generateContent"
        self.headers = {"Content-Type": "application/json"}

    @staticmethod
    def _to_google_messages(system_prompt: str, msgs: List[Dict[str, str]]) -> List[Dict]:
        parts: List[Dict] = []
        if system_prompt:
            parts.append({"role": "system", "parts": [{"text": system_prompt}]})
        for m in msgs:
            role = m.get("role", "user")
            content = m.get("content", "")
            if role == "user":
                parts.append({"role": "user", "parts": [{"text": content}]})
            else:
                parts.append({"role": "model", "parts": [{"text": content}]})
        return parts

    async def stream(self, system_prompt: str, msgs: List[Dict[str, str]], temperature: float = 0.7, max_tokens: int = 800) -> AsyncGenerator[str, None]:
        body = {
            "contents": self._to_google_messages(system_prompt, msgs),
            "generationConfig": {"temperature": temperature, "maxOutputTokens": max_tokens},
        }
        params = {"key": self.key}
        async with httpx.AsyncClient(timeout=httpx.Timeout(STALL_TIMEOUT_SEC)) as client:
            r = await client.post(self.base, headers=self.headers, params=params, json=body)
            r.raise_for_status()
            data = r.json()
            text = ""
            try:
                text = data["candidates"][0]["content"]["parts"][0]["text"]
            except Exception:
                text = json.dumps(data)  # dump raw for debugging
            # naive chunking to emulate streaming
            chunk = max(40, len(text) // 20)
            for i in range(0, len(text), chunk):
                yield text[i : i + chunk]

# ───────────────────────── Conversation state ─────────────────────────

@dataclass
class History:
    msgs_openai: List[Dict[str, str]] = field(default_factory=list)
    msgs_b_side: List[Dict] = field(default_factory=list)  # anthropic or google format
    flat_texts: List[str] = field(default_factory=list)

    def add_openai(self, role: str, content: str) -> None:
        self.msgs_openai.append({"role": role, "content": content})
        self.flat_texts.append(content)

    # For provider B we use a neutral internal structure and convert per‑provider
    def add_b_user_from_openai(self, content: str) -> None:
        # when OpenAI speaks, B sees it as a user message
        self.msgs_b_side.append({"role": "user", "content": content})
        self.flat_texts.append(content)

    def add_b_assistant(self, content: str) -> None:
        self.msgs_b_side.append({"role": "assistant", "content": content})
        self.flat_texts.append(content)

    def tail_openai(self, n: int) -> List[Dict[str, str]]:
        msgs = self.msgs_openai
        if msgs and msgs[0]["role"] == "system":
            head = [msgs[0]]
            body = msgs[1:]
            return head + body[-n:]
        return msgs[-n:]

    def tail_b(self, n: int) -> List[Dict]:
        return self.msgs_b_side[-n:]

# ───────────────────────── Transcript ─────────────────────────

@dataclass
class Transcript:
    lines: List[str] = field(default_factory=list)

    def start(self, conv_id: int, started_at: str, openai_model: str, provider_b: str, model_b: str, starter: str):
        self.lines.append(f"# Conversation {conv_id}: {starter}\n")
        self.lines.append(f"*Started at {started_at}*  ")
        self.lines.append(f"*OpenAI model: {openai_model}, Provider B: {provider_b} ({model_b})*\n")

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

# ───────────────────────── Runner ─────────────────────────

async def run_bridge(args):
    os.makedirs(TRANSCRIPTS_DIR, exist_ok=True)
    os.makedirs(SESSION_LOGS_DIR, exist_ok=True)
    logging.basicConfig(filename=GLOBAL_LOG, level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")
    init_db()

    roles = load_roles(args.roles)
    stop_words = set(STOP_WORDS_DEFAULT)
    if isinstance(roles.get("stop_words"), list):
        stop_words |= {w.strip().lower() for w in roles["stop_words"] if isinstance(w, str) and w.strip()}

    # Compose system prompts
    sys_openai = combine_system(roles["openai"]["system"], roles["openai"].get("guidelines", []))

    provider_b = args.provider_b.lower()
    if provider_b == "anthropic":
        sys_b = combine_system(roles["anthropic"]["system"], roles["anthropic"].get("guidelines", []))
    elif provider_b == "gemini":
        sys_b = combine_system(roles["google"]["system"], roles["google"].get("guidelines", []))
    else:
        print("ERROR: --provider-b must be 'anthropic' or 'gemini'")
        sys.exit(1)

    # Starter prompt
    starter = input("Starter prompt (blank = default): ").strip()
    if not starter:
        starter = "Compare how different traditions define awakening. Cite examples and disagreements."

    # Filenames & logging
    ts = now_stamp()
    slug = safe_slug(starter)
    md_path = os.path.join(TRANSCRIPTS_DIR, f"{ts}__{slug}.md")
    session_log_path = os.path.join(SESSION_LOGS_DIR, f"{ts}__{slug}.log")
    session_logger = logging.getLogger(f"session_{os.path.basename(session_log_path)}")
    session_logger.setLevel(logging.INFO)
    if not session_logger.handlers:
        fh = logging.FileHandler(session_log_path, encoding="utf-8")
        fh.setFormatter(logging.Formatter("%(asctime)s %(levelname)s %(message)s"))
        session_logger.addHandler(fh)

    # Providers
    openai = OpenAIChat(OPENAI_KEY, args.openai_model)
    if provider_b == "anthropic":
        if not ANTHROPIC_KEY:
            print("ERROR: ANTHROPIC_API_KEY missing but --provider-b anthropic selected")
            sys.exit(1)
        b_client = AnthropicChat(ANTHROPIC_KEY, args.model_b)
    else:
        if not GOOGLE_KEY:
            print("ERROR: GOOGLE_API_KEY missing but --provider-b gemini selected")
            sys.exit(1)
        b_client = GeminiChat(GOOGLE_KEY, args.model_b)

    # DB
    cid = create_conversation(args.openai_model, provider_b, args.model_b, starter)

    # History + transcript header
    history = History()
    history.add_openai("system", sys_openai)
    # B side keeps its own message format; we store neutral and convert later

    # Seed first user message to OpenAI
    history.add_openai("user", starter)
    log_message_sql(cid, "openai", "user", starter)

    started_at = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    transcript = Transcript()
    transcript.start(cid, started_at, args.openai_model, provider_b, args.model_b, starter)
    transcript.add("openai", "user", started_at, starter)

    print(f"\nRoles loaded from: {os.path.abspath(args.roles)}")
    print(f"Provider B: {provider_b} | Model: {args.model_b}")
    print(f"Transcript: {md_path}\nSession log: {session_log_path}\n")

    current = "openai"
    rounds = 0

    try:
        while rounds < args.max_rounds:
            rounds += 1
            print(f"\n—— Round {rounds} ——")

            if is_repetitive(history.flat_texts):
                print("⚠️  Loop detected. Stopping.")
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
                history.add_b_user_from_openai(reply)

                nowt = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
                transcript.add("openai", "assistant", nowt, reply)
                session_logger.info(f"OpenAI assistant: {reply[:4000]}")
                if any(sw in reply.lower() for sw in stop_words):
                    print("🛑 Stop word detected (OpenAI). Ending chat.")
                    break
                current = "b"

            else:
                print(f"[{provider_b.capitalize()}] ", end="", flush=True)
                # Convert neutral B history into provider‑specific input
                b_msgs = history.tail_b(args.mem_rounds)
                if provider_b == "anthropic":
                    # Build Anthropic messages array
                    anthro_msgs = []
                    for item in b_msgs:
                        if item["role"] == "user":
                            anthro_msgs.append({"role": "user", "content": [{"type": "text", "text": item["content"]}]})
                        else:
                            anthro_msgs.append({"role": "assistant", "content": [{"type": "text", "text": item["content"]}]})
                    gen = b_client.stream(system_prompt=sys_b, messages_for_claude=anthro_msgs, temperature=args.temp_b)
                else:
                    # Gemini uses OpenAI-like flat messages [{role, content}]
                    gemini_msgs = []
                    for item in b_msgs:
                        role = "user" if item["role"] == "user" else "assistant"
                        gemini_msgs.append({"role": role, "content": item["content"]})
                    gen = b_client.stream(system_prompt=sys_b, msgs=gemini_msgs, temperature=args.temp_b)

                reply = await asyncio.wait_for(stream_collect(gen), timeout=STALL_TIMEOUT_SEC)
                if not reply:
                    print("…(no reply from Provider B)")
                    break

                log_message_sql(cid, provider_b, "assistant", reply)
                history.add_b_assistant(reply)
                # OpenAI sees provider B as user
                history.add_openai("user", reply)

                nowt = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
                transcript.add(provider_b, "assistant", nowt, reply)
                session_logger.info(f"{provider_b} assistant: {reply[:4000]}")
                if any(sw in reply.lower() for sw in stop_words):
                    print("🛑 Stop word detected (Provider B). Ending chat.")
                    break
                current = "openai"

            if is_repetitive(history.flat_texts):
                print("⚠️  Loop detected after this round. Stopping.")
                break

        print(f"\nFinished.\nMarkdown: {md_path}\nLogs: {session_log_path}  |  Global: {GLOBAL_LOG}")

    except KeyboardInterrupt:
        print("\nInterrupted by you. Cheerio.")
    except asyncio.TimeoutError:
        print("\n⏱️  A provider stalled beyond timeout. Stopping.")
    except Exception as e:
        logging.getLogger("bridge").exception("Unhandled error: %s", e)
        print(f"\nUnexpected error: {e}")
    finally:
        try:
            transcript.dump(md_path)
        except Exception as e:
            print(f"Failed to write transcript: {e}")

# ───────────────────────── CLI ─────────────────────────

def parse_args():
    p = argparse.ArgumentParser(description="OpenAI ↔ Anthropic/Gemini chat bridge with role file support.")
    p.add_argument("--roles", type=str, default="roles.json", help="Path to roles JSON file.")
    p.add_argument("--max-rounds", type=int, default=30, help="Max total replies across both agents.")
    p.add_argument("--mem-rounds", type=int, default=8, help="How many recent turns each side sees.")
    p.add_argument("--openai-model", type=str, default=DEFAULT_OPENAI_MODEL, help="OpenAI chat model.")
    p.add_argument("--provider-b", type=str, default="anthropic", choices=["anthropic", "gemini"], help="Second provider.")
    p.add_argument("--model-b", type=str, default=DEFAULT_ANTHROPIC_MODEL, help="Model for provider B (Claude or Gemini model name).")
    p.add_argument("--temp-a", type=float, default=0.7, help="Temperature for OpenAI (overridable via roles file).")
    p.add_argument("--temp-b", type=float, default=0.7, help="Temperature for provider B.")
    return p.parse_args()

# ───────────────────────── Entry ─────────────────────────

if __name__ == "__main__":
    args = parse_args()
    # If provider-b is gemini and user didn't override model-b, switch default
    if args.provider_b == "gemini" and args.model_b == DEFAULT_ANTHROPIC_MODEL:
        args.model_b = DEFAULT_GOOGLE_MODEL
    asyncio.run(run_bridge(args))

# ───────────────────────── roles.json (drop‑in example) ─────────────────────────
# Save as roles.json if you want a starting point. The script will also auto‑create one if missing.
# {
#   "openai": {
#     "system": "You are ChatGPT. Be concise, helpful, truthful, and witty. Prioritise verifiable facts and clear reasoning.",
#     "guidelines": [
#       "When comparing sources, cite examples and name key scholars or texts.",
#       "If asked for probabilities, explain your methodology and uncertainty.",
#       "Prefer structured answers with brief lists, then a crisp conclusion."
#     ]
#   },
#   "anthropic": {
#     "system": "You are Claude. Be concise, compassionate, truthful, and reflective. Balance clarity with nuance.",
#     "guidelines": [
#       "Surface connections across traditions without overclaiming equivalence.",
#       "Offer metaphors to illuminate abstract ideas, but keep them tight.",
#       "State uncertainty explicitly when evidence is thin or contested."
#     ]
#   },
#   "google": {
#     "system": "You are Gemini. Be crisp, insightful, and creative, with practical structure and clear tradeoffs.",
#     "guidelines": [
#       "Prefer stepwise structure and bullet summaries.",
#       "Flag uncertainty and cite sources when appropriate."
#     ]
#   },
#   "stop_words": ["wrap up", "end chat", "terminate"],
#   "temp_a": 0.6,
#   "temp_b": 0.7
# }
