#!/usr/bin/env python3
"""
🌉 Chat Bridge - Unified Edition

A beautiful, interactive chat bridge that connects two AI assistants with style!
Features colorful menus, persona selection, and comprehensive logging.

Created by consolidating chat_bridge_pro.py, chat_bridge_roles.py, and adding
enhanced visual experience with colors and better menu navigation.
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

# ───────────────────────── Colors & Styling ─────────────────────────

class Colors:
    """ANSI color codes for beautiful terminal output"""
    RESET = '\033[0m'
    BOLD = '\033[1m'
    DIM = '\033[2m'

    # Main colors
    RED = '\033[91m'
    GREEN = '\033[92m'
    YELLOW = '\033[93m'
    BLUE = '\033[94m'
    MAGENTA = '\033[95m'
    CYAN = '\033[96m'
    WHITE = '\033[97m'

    # Background colors
    BG_RED = '\033[101m'
    BG_GREEN = '\033[102m'
    BG_YELLOW = '\033[103m'
    BG_BLUE = '\033[104m'
    BG_MAGENTA = '\033[105m'
    BG_CYAN = '\033[106m'

    # Special effects
    RAINBOW = [RED, YELLOW, GREEN, CYAN, BLUE, MAGENTA]

def colorize(text: str, color: str, bold: bool = False) -> str:
    """Apply color and optional bold formatting to text"""
    prefix = Colors.BOLD + color if bold else color
    return f"{prefix}{text}{Colors.RESET}"

def rainbow_text(text: str) -> str:
    """Apply rainbow colors to each character"""
    colored = ""
    for i, char in enumerate(text):
        if char.isspace():
            colored += char
        else:
            color = Colors.RAINBOW[i % len(Colors.RAINBOW)]
            colored += f"{color}{char}"
    return colored + Colors.RESET

def print_banner():
    """Print a beautiful welcome banner"""
    banner = """
╔══════════════════════════════════════════════════════════════════╗
║                          🌉 CHAT BRIDGE 🌉                        ║
║                     Connect Two AI Assistants                     ║
╚══════════════════════════════════════════════════════════════════╝
"""
    print(colorize(banner, Colors.CYAN, bold=True))

def print_section_header(title: str, icon: str = "🔧"):
    """Print a styled section header"""
    line = "─" * 60
    print(f"\n{colorize(line, Colors.DIM)}")
    print(f"{colorize(icon, Colors.YELLOW)} {colorize(title.upper(), Colors.WHITE, bold=True)}")
    print(f"{colorize(line, Colors.DIM)}")

def print_menu_option(number: str, title: str, description: str, color: str = Colors.WHITE):
    """Print a styled menu option"""
    num_colored = colorize(f"[{number}]", Colors.CYAN, bold=True)
    title_colored = colorize(title, color, bold=True)
    desc_colored = colorize(description, Colors.DIM)
    print(f"  {num_colored} {title_colored}")
    print(f"      {desc_colored}")

def print_provider_option(number: str, provider: str, model: str, description: str):
    """Print a styled provider option with model info"""
    num_colored = colorize(f"[{number}]", Colors.CYAN, bold=True)
    provider_colored = colorize(provider, Colors.GREEN, bold=True)
    model_colored = colorize(model, Colors.YELLOW)
    desc_colored = colorize(description, Colors.DIM)
    print(f"  {num_colored} {provider_colored} - {model_colored}")
    print(f"      {desc_colored}")

def print_success(message: str):
    """Print a success message"""
    print(f"{colorize('✅', Colors.GREEN)} {colorize(message, Colors.GREEN)}")

def print_error(message: str):
    """Print an error message"""
    print(f"{colorize('❌', Colors.RED)} {colorize(message, Colors.RED, bold=True)}")

def print_warning(message: str):
    """Print a warning message"""
    print(f"{colorize('⚠️ ', Colors.YELLOW)} {colorize(message, Colors.YELLOW)}")

def print_info(message: str):
    """Print an info message"""
    print(f"{colorize('ℹ️ ', Colors.BLUE)} {colorize(message, Colors.BLUE)}")

# ───────────────────────── Config / Env ─────────────────────────

load_dotenv()

DEFAULT_PROVIDER_A = os.getenv("BRIDGE_PROVIDER_A", "openai")
DEFAULT_PROVIDER_B = os.getenv("BRIDGE_PROVIDER_B", "anthropic")

STOP_WORDS_DEFAULT = {"goodbye", "end chat", "terminate", "stop", "that is all"}
REPEAT_WINDOW = 6
REPEAT_THRESHOLD = 0.8
GLOBAL_LOG = "chat_bridge.log"

# ───────────────────────── Menus & Interaction ─────────────────────────

def get_user_input(prompt: str, default: str = "") -> str:
    """Get user input with colored prompt"""
    prompt_colored = colorize(prompt, Colors.CYAN)
    if default:
        default_colored = colorize(f"[{default}]", Colors.DIM)
        full_prompt = f"{prompt_colored} {default_colored}: "
    else:
        full_prompt = f"{prompt_colored}: "

    try:
        response = input(full_prompt).strip()
        return response if response else default
    except KeyboardInterrupt:
        print(f"\n{colorize('👋 Goodbye!', Colors.YELLOW)}")
        sys.exit(0)

def select_from_menu(options: List[Tuple[str, str]], title: str, allow_multiple: bool = False) -> str:
    """Display a menu and get user selection"""
    print_section_header(title)

    for i, (key, description) in enumerate(options, 1):
        print_menu_option(str(i), key, description)

    if allow_multiple:
        prompt = f"Select options (e.g., 1,3 or just 1)"
    else:
        prompt = f"Select option (1-{len(options)})"

    while True:
        choice = get_user_input(prompt)
        if not choice:
            continue

        try:
            if allow_multiple and ',' in choice:
                indices = [int(x.strip()) for x in choice.split(',')]
                if all(1 <= i <= len(options) for i in indices):
                    return ','.join(str(i) for i in indices)
            else:
                index = int(choice)
                if 1 <= index <= len(options):
                    return str(index)

            print_error(f"Please enter a number between 1 and {len(options)}")
        except ValueError:
            print_error("Please enter a valid number")

def select_providers() -> Tuple[str, str]:
    """Interactive provider selection with beautiful menus"""
    print_section_header("Provider Selection", "🤖")

    # Get provider specs
    specs = {key: get_spec(key) for key in provider_choices()}

    print_info("Available AI Providers:")
    print()

    providers = []
    for key, spec in specs.items():
        providers.append((key, f"{spec.label} - {spec.description}"))

    # Select Agent A
    print(colorize("🔹 SELECT AGENT A (First Speaker)", Colors.BLUE, bold=True))
    choice_a = select_from_menu(providers, "Agent A Provider")
    provider_a = providers[int(choice_a) - 1][0]
    spec_a = specs[provider_a]
    print_success(f"Agent A: {spec_a.label} ({spec_a.default_model})")

    print()

    # Select Agent B
    print(colorize("🔸 SELECT AGENT B (Second Speaker)", Colors.MAGENTA, bold=True))
    choice_b = select_from_menu(providers, "Agent B Provider")
    provider_b = providers[int(choice_b) - 1][0]
    spec_b = specs[provider_b]
    print_success(f"Agent B: {spec_b.label} ({spec_b.default_model})")

    return provider_a, provider_b

def select_personas(roles_data: Optional[Dict]) -> Tuple[Optional[str], Optional[str]]:
    """Select personas from roles.json if available"""
    if not roles_data or 'persona_library' not in roles_data:
        return None, None

    print_section_header("Persona Selection", "🎭")

    personas = []
    for key, persona in roles_data['persona_library'].items():
        provider_name = get_spec(persona['provider']).label
        system_preview = persona['system'][:80] + "..." if len(persona['system']) > 80 else persona['system']
        personas.append((key, f"{provider_name} - {system_preview}"))

    if not personas:
        return None, None

    print_info("Available Personas (optional - press Enter to skip):")

    # Agent A persona
    print(colorize("🔹 PERSONA FOR AGENT A", Colors.BLUE, bold=True))
    personas_with_skip = [("skip", "Use default system prompt")] + personas
    choice_a = select_from_menu(personas_with_skip, "Agent A Persona")
    persona_a = None if choice_a == "1" else personas[int(choice_a) - 2][0]

    # Agent B persona
    print(colorize("🔸 PERSONA FOR AGENT B", Colors.MAGENTA, bold=True))
    choice_b = select_from_menu(personas_with_skip, "Agent B Persona")
    persona_b = None if choice_b == "1" else personas[int(choice_b) - 2][0]

    return persona_a, persona_b

def get_conversation_starter() -> str:
    """Get the conversation starter with a nice prompt"""
    print_section_header("Conversation Starter", "💬")
    print_info("Enter a topic or question to start the conversation between the AI assistants.")
    print()

    while True:
        starter = get_user_input("Conversation topic")
        if starter:
            return starter
        print_error("Please enter a conversation starter")

def show_session_summary(provider_a: str, provider_b: str, max_rounds: int, mem_rounds: int):
    """Show a summary of the session configuration"""
    print_section_header("Session Configuration", "⚙️")

    spec_a = get_spec(provider_a)
    spec_b = get_spec(provider_b)

    print(f"  {colorize('Agent A:', Colors.BLUE, bold=True)} {colorize(spec_a.label, Colors.GREEN)} ({colorize(spec_a.default_model, Colors.YELLOW)})")
    print(f"  {colorize('Agent B:', Colors.MAGENTA, bold=True)} {colorize(spec_b.label, Colors.GREEN)} ({colorize(spec_b.default_model, Colors.YELLOW)})")
    print(f"  {colorize('Max Rounds:', Colors.WHITE)} {colorize(str(max_rounds), Colors.CYAN)}")
    print(f"  {colorize('Memory Rounds:', Colors.WHITE)} {colorize(str(mem_rounds), Colors.CYAN)}")

    print()
    input(colorize("Press Enter to start the conversation... ", Colors.GREEN))

# ───────────────────────── Core Logic (from existing scripts) ─────────────────────────

@dataclass
class Transcript:
    turns: List[Dict] = field(default_factory=list)

    def add(self, agent: str, role: str, timestamp: str, content: str):
        self.turns.append({"agent": agent, "role": role, "timestamp": timestamp, "content": content})

    def dump(self, path: str):
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "w", encoding="utf-8") as f:
            f.write("# Chat Bridge Transcript\n\n")
            for turn in self.turns:
                f.write(f"## {turn['agent']} ({turn['timestamp']})\n")
                f.write(f"{turn['content']}\n\n")

@dataclass
class ConversationHistory:
    turns: List[Turn] = field(default_factory=list)

    def add_turn(self, author: str, text: str):
        self.turns.append(Turn(author=author, text=text))

    @property
    def flat_texts(self) -> List[str]:
        return [turn.text for turn in self.turns]

    def recent_turns(self, limit: int) -> List[Turn]:
        return self.turns[-limit:]

def setup_logging() -> Tuple[logging.Logger, logging.Logger]:
    """Set up loggers"""
    bridge_logger = logging.getLogger("bridge")
    bridge_logger.setLevel(logging.INFO)

    if not bridge_logger.handlers:
        handler = logging.FileHandler(GLOBAL_LOG)
        formatter = logging.Formatter("%(asctime)s [%(levelname)s] %(message)s")
        handler.setFormatter(formatter)
        bridge_logger.addHandler(handler)

    session_logger = logging.getLogger("session")
    return bridge_logger, session_logger

def setup_database():
    """Initialize SQLite database"""
    conn = sqlite3.connect("bridge.db")
    conn.execute("""
        CREATE TABLE IF NOT EXISTS conversations (
            id TEXT PRIMARY KEY,
            timestamp TEXT,
            starter TEXT,
            agent_a_provider TEXT,
            agent_b_provider TEXT,
            status TEXT
        )
    """)
    conn.execute("""
        CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            conversation_id TEXT,
            agent_provider TEXT,
            role TEXT,
            content TEXT,
            timestamp TEXT,
            FOREIGN KEY (conversation_id) REFERENCES conversations(id)
        )
    """)
    conn.commit()
    return conn

def log_conversation_start(conn: sqlite3.Connection, cid: str, starter: str,
                          provider_a: str, provider_b: str):
    """Log conversation start to database"""
    timestamp = datetime.now().isoformat()
    conn.execute(
        "INSERT INTO conversations VALUES (?, ?, ?, ?, ?, ?)",
        (cid, timestamp, starter, provider_a, provider_b, "active")
    )
    conn.commit()

def log_message_sql(cid: str, provider: str, role: str, content: str):
    """Log message to SQLite database"""
    conn = sqlite3.connect("bridge.db")
    timestamp = datetime.now().isoformat()
    conn.execute(
        "INSERT INTO messages (conversation_id, agent_provider, role, content, timestamp) VALUES (?, ?, ?, ?, ?)",
        (cid, provider, role, content[:10000], timestamp)  # Truncate very long messages
    )
    conn.commit()
    conn.close()

def contains_stop_word(text: str, stop_words: set) -> bool:
    """Check if text contains any stop words"""
    lower_text = text.lower()
    return any(word in lower_text for word in stop_words)

def is_repetitive(texts: List[str], window: int = REPEAT_WINDOW, threshold: float = REPEAT_THRESHOLD) -> bool:
    """Detect if recent messages are too repetitive"""
    if len(texts) < window:
        return False

    recent = texts[-window:]
    similarities = []

    for i in range(len(recent)):
        for j in range(i + 1, len(recent)):
            similarity = SequenceMatcher(None, recent[i], recent[j]).ratio()
            similarities.append(similarity)

    if similarities:
        avg_similarity = sum(similarities) / len(similarities)
        return avg_similarity > threshold

    return False

def create_session_paths(starter: str) -> Tuple[str, str]:
    """Create paths for session files"""
    timestamp = datetime.now().strftime("%Y-%m-%d_%H-%M-%S")
    slug = re.sub(r"[^\w\s-]", "", starter.lower())[:50]
    slug = re.sub(r"[\s_-]+", "-", slug).strip("-")

    base_name = f"{timestamp}__{slug}"
    md_path = f"transcripts/{base_name}.md"
    log_path = f"logs/{base_name}.log"

    return md_path, log_path

def setup_session_logger(log_path: str) -> logging.Logger:
    """Set up session-specific logger"""
    os.makedirs(os.path.dirname(log_path), exist_ok=True)

    session_logger = logging.getLogger(f"session_{datetime.now().timestamp()}")
    session_logger.setLevel(logging.INFO)
    session_logger.handlers.clear()

    handler = logging.FileHandler(log_path)
    formatter = logging.Formatter("%(asctime)s - %(message)s")
    handler.setFormatter(formatter)
    session_logger.addHandler(handler)

    return session_logger

def load_roles_file(roles_path: Optional[str]) -> Optional[Dict]:
    """Load roles configuration if available"""
    if not roles_path or not os.path.exists(roles_path):
        return None

    try:
        with open(roles_path, 'r', encoding='utf-8') as f:
            return json.load(f)
    except Exception as e:
        print_warning(f"Could not load roles file {roles_path}: {e}")
        return None

def apply_persona(agent: AgentRuntime, persona_key: Optional[str], roles_data: Optional[Dict]) -> AgentRuntime:
    """Apply persona from roles data if specified"""
    if not persona_key or not roles_data or 'persona_library' not in roles_data:
        return agent

    persona = roles_data['persona_library'].get(persona_key)
    if not persona:
        return agent

    # Update agent with persona settings
    if persona.get('system'):
        agent.system_prompt = persona['system']
        if persona.get('guidelines'):
            guidelines_text = "\n".join(f"• {g}" for g in persona['guidelines'])
            agent.system_prompt += f"\n\nGuidelines:\n{guidelines_text}"

    return agent

# ───────────────────────── Main Bridge Logic ─────────────────────────

async def run_bridge(args):
    """Main bridge execution with beautiful progress display"""

    print_banner()

    # Load roles if specified
    roles_data = load_roles_file(getattr(args, 'roles', None))

    # Interactive setup
    if not (args.provider_a and args.provider_b and getattr(args, 'starter', None)):
        provider_a, provider_b = select_providers()

        if roles_data:
            persona_a, persona_b = select_personas(roles_data)
        else:
            persona_a, persona_b = None, None

        starter = get_conversation_starter()
    else:
        provider_a, provider_b = args.provider_a, args.provider_b
        persona_a, persona_b = None, None
        starter = args.starter

    show_session_summary(provider_a, provider_b, args.max_rounds, args.mem_rounds)

    # Setup logging and database
    bridge_logger, _ = setup_logging()
    conn = setup_database()

    # Create session files
    md_path, session_log_path = create_session_paths(starter)
    session_logger = setup_session_logger(session_log_path)

    # Generate conversation ID and log start
    cid = f"conv_{datetime.now().strftime('%Y%m%d_%H%M%S')}"
    log_conversation_start(conn, cid, starter, provider_a, provider_b)

    # Create agents
    try:
        agent_a = create_agent(provider_a, "a", resolve_model(provider_a, args.model_a), args.temp_a)
        agent_b = create_agent(provider_b, "b", resolve_model(provider_b, args.model_b), args.temp_b)

        # Apply personas if specified
        if roles_data:
            agent_a = apply_persona(agent_a, persona_a, roles_data)
            agent_b = apply_persona(agent_b, persona_b, roles_data)
    except Exception as e:
        print_error(f"Failed to create agents: {e}")
        return

    # Initialize conversation
    history = ConversationHistory()
    transcript = Transcript()
    history.add_turn("human", starter)

    nowt = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    transcript.add("Human", "user", nowt, starter)
    log_message_sql(cid, "human", "user", starter)

    # Get stop words
    stop_words = set(roles_data.get('stop_words', [])) if roles_data else STOP_WORDS_DEFAULT

    print_section_header("Conversation in Progress", "💭")
    print_info(f"Starting conversation: {colorize(starter, Colors.WHITE, bold=True)}")
    print()

    agents = {"a": agent_a, "b": agent_b}
    current_id = "a"

    try:
        for round_num in range(1, args.max_rounds + 1):
            agent = agents[current_id]
            label = f"Agent {current_id.upper()}"

            print(f"{colorize(f'Round {round_num}', Colors.CYAN)} - {colorize(label, Colors.GREEN, bold=True)} ({colorize(agent.provider_key, Colors.YELLOW)}) is thinking...")

            # Get recent context
            recent_context = history.recent_turns(args.mem_rounds * 2)

            # Stream response
            full_reply = ""
            async for chunk in agent.stream_response(recent_context):
                if chunk:
                    print(chunk, end="", flush=True)
                    full_reply += chunk

            print()  # New line after response

            if not full_reply.strip():
                print_warning(f"{label} had no response")
                break

            # Log the response
            log_message_sql(cid, agent.provider_key, "assistant", full_reply)
            history.add_turn(agent.agent_id, full_reply)

            nowt = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            transcript.add(label, "assistant", nowt, full_reply)
            session_logger.info(f"{label}: {full_reply[:1000]}")

            # Check for stop conditions
            if contains_stop_word(full_reply, stop_words):
                print(f"\n{colorize('🛑 Stop word detected. Ending conversation.', Colors.RED)}")
                break

            # Switch to other agent
            current_id = "b" if current_id == "a" else "a"

            # Check for repetitive content
            if is_repetitive(history.flat_texts):
                print(f"\n{colorize('⚠️  Loop detected. Ending conversation.', Colors.YELLOW)}")
                break

            print(f"\n{colorize('─' * 60, Colors.DIM)}\n")

        # Conversation finished
        print_section_header("Conversation Complete", "✅")
        print_success("Conversation finished successfully!")
        print_info(f"Transcript saved: {colorize(md_path, Colors.CYAN)}")
        print_info(f"Session log: {colorize(session_log_path, Colors.CYAN)}")
        print_info(f"Global log: {colorize(GLOBAL_LOG, Colors.CYAN)}")

    except KeyboardInterrupt:
        print(f"\n{colorize('👋 Interrupted by user. Saving transcript...', Colors.YELLOW)}")
    except Exception as exc:
        bridge_logger.exception("Unhandled error: %s", exc)
        print_error(f"Unexpected error: {exc}")
    finally:
        with contextlib.suppress(Exception):
            transcript.dump(md_path)
        conn.close()

# ───────────────────────── CLI ─────────────────────────

def parse_args():
    """Parse command line arguments"""
    choices = provider_choices()
    parser = argparse.ArgumentParser(
        description="🌉 Beautiful Chat Bridge - Connect two AI assistants with style!",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Examples:
  %(prog)s                                    # Interactive mode with menus
  %(prog)s --provider-a openai --provider-b anthropic --starter "What is consciousness?"
  %(prog)s --roles roles.json --max-rounds 50
        """
    )

    parser.add_argument("--provider-a", choices=choices, help="Provider for Agent A")
    parser.add_argument("--provider-b", choices=choices, help="Provider for Agent B")
    parser.add_argument("--model-a", help="Model override for Agent A")
    parser.add_argument("--model-b", help="Model override for Agent B")
    parser.add_argument("--temp-a", type=float, default=0.7, help="Temperature for Agent A")
    parser.add_argument("--temp-b", type=float, default=0.7, help="Temperature for Agent B")
    parser.add_argument("--max-rounds", type=int, default=30, help="Maximum conversation rounds")
    parser.add_argument("--mem-rounds", type=int, default=8, help="Memory rounds for context")
    parser.add_argument("--roles", help="Path to roles.json file for personas")
    parser.add_argument("--starter", help="Conversation starter (skips interactive mode)")
    parser.add_argument("--version", action="version", version=f"Chat Bridge {__version__}")

    # Legacy compatibility
    parser.add_argument("--openai-model", dest="model_a", help="Legacy alias for --model-a")
    parser.add_argument("--anthropic-model", dest="model_b", help="Legacy alias for --model-b")

    return parser.parse_args()

# ───────────────────────── Entry Point ─────────────────────────

if __name__ == "__main__":
    try:
        arguments = parse_args()
        asyncio.run(run_bridge(arguments))
    except KeyboardInterrupt:
        print(f"\n{colorize('👋 Goodbye!', Colors.YELLOW)}")
        sys.exit(0)