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
    ensure_credentials,
    OpenAIChat,
    AnthropicChat,
    GeminiChat,
    OllamaChat,
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
║                                                                    ║
║                    🎭 Personas  ⚙️ Configurable                   ║
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
    except EOFError:
        # Handle non-interactive mode - return default or exit gracefully
        if default:
            print(f"\n{colorize('ℹ️ Non-interactive mode detected, using default:', Colors.BLUE)} {default}")
            return default
        else:
            print(f"\n{colorize('❌ Non-interactive mode detected but no default provided', Colors.RED)}")
            print(f"{colorize('💡 Tip: Use command line arguments to skip interactive mode', Colors.YELLOW)}")
            sys.exit(1)

def select_from_menu(options: List[Tuple[str, str]], title: str, allow_multiple: bool = False, default: str = "") -> str:
    """Display a menu and get user selection"""
    print_section_header(title)

    for i, (key, description) in enumerate(options, 1):
        print_menu_option(str(i), key, description)

    if allow_multiple:
        prompt = f"Select options (e.g., 1,3 or just 1)"
    else:
        prompt = f"Select option (1-{len(options)})"

    while True:
        choice = get_user_input(prompt, default)
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

    # Find default indices based on environment variables
    default_a_index = "1"  # Default to first option
    default_b_index = "2"  # Default to second option

    for i, (key, _) in enumerate(providers, 1):
        if key == DEFAULT_PROVIDER_A:
            default_a_index = str(i)
        if key == DEFAULT_PROVIDER_B:
            default_b_index = str(i)

    # Select Agent A
    print(colorize("🔹 SELECT AGENT A (First Speaker)", Colors.BLUE, bold=True))
    choice_a = select_from_menu(providers, "Agent A Provider", default=default_a_index)
    provider_a = providers[int(choice_a) - 1][0]
    spec_a = specs[provider_a]
    print_success(f"Agent A: {spec_a.label} ({spec_a.default_model})")

    print()

    # Select Agent B
    print(colorize("🔸 SELECT AGENT B (Second Speaker)", Colors.MAGENTA, bold=True))
    choice_b = select_from_menu(providers, "Agent B Provider", default=default_b_index)
    provider_b = providers[int(choice_b) - 1][0]
    spec_b = specs[provider_b]
    print_success(f"Agent B: {spec_b.label} ({spec_b.default_model})")

    return provider_a, provider_b

def create_custom_role() -> Dict:
    """Create a custom role with user-defined settings"""
    print_section_header("Create Custom Role", "✨")
    
    # Get role name
    role_name = get_user_input("Enter custom role name (e.g., 'detective', 'teacher'): ").strip()
    if not role_name:
        role_name = "custom_role"
    
    # Select provider
    providers = provider_choices()
    provider_options = [(p, get_spec(p).label) for p in providers]
    print_info("Select AI provider for this role:")
    choice = select_from_menu(provider_options, "Provider")
    provider = provider_options[int(choice) - 1][0]
    
    # Get model (optional)
    default_model = get_spec(provider).default_model
    model_input = get_user_input(f"Model (optional, default: {default_model}): ").strip()
    model = model_input if model_input else None
    
    # Get system prompt
    print_info("Enter the system prompt for this role:")
    print_info("This defines the AI's personality, expertise, and behavior.")
    system_prompt = get_user_input("System prompt: ").strip()
    if not system_prompt:
        system_prompt = f"You are a {role_name}. Be helpful, knowledgeable, and stay in character."
    
    # Get guidelines
    print_info("Enter behavioral guidelines (one per line, empty line to finish):")
    print_info("These are specific instructions for how the AI should behave.")
    guidelines = []
    while True:
        guideline = get_user_input(f"Guideline {len(guidelines)+1}> ")
        if not guideline.strip():
            break
        guidelines.append(guideline.strip())
    
    if not guidelines:
        guidelines = [f"Stay in character as a {role_name}", "Be helpful and informative", "Use appropriate language for the role"]
    
    # Get temperature (optional)
    temp_input = get_user_input("Temperature (0.0-2.0, optional, default: 0.7): ").strip()
    temperature = None
    if temp_input:
        try:
            temp_val = float(temp_input)
            if 0 <= temp_val <= 2:
                temperature = temp_val
            else:
                print_warning("Temperature must be between 0.0 and 2.0. Using default.")
        except ValueError:
            print_warning("Invalid temperature value. Using default.")
    
    # Get notes (optional)
    notes = get_user_input("Notes (optional description): ").strip()
    
    # Build the custom role
    custom_role = {
        "provider": provider,
        "model": model,
        "system": system_prompt,
        "guidelines": guidelines
    }
    
    if temperature is not None:
        custom_role["temperature"] = temperature
    if notes:
        custom_role["notes"] = notes
    
    # Show summary
    print_section_header("Custom Role Summary", "📋")
    print(f"Name: {colorize(role_name, Colors.CYAN, bold=True)}")
    print(f"Provider: {colorize(get_spec(provider).label, Colors.GREEN)}")
    print(f"Model: {colorize(str(model or default_model), Colors.YELLOW)}")
    if temperature is not None:
        print(f"Temperature: {colorize(str(temperature), Colors.MAGENTA)}")
    print(f"System: {system_prompt[:100]}{'...' if len(system_prompt) > 100 else ''}")
    print(f"Guidelines: {len(guidelines)} items")
    if notes:
        print(f"Notes: {notes[:50]}{'...' if len(notes) > 50 else ''}")
    
    # Ask if user wants to save permanently
    print()
    save_choice = get_user_input("Save this role permanently to roles.json? (y/n): ").lower()
    custom_role_data = {role_name: custom_role}
    
    if save_choice in ['y', 'yes']:
        custom_role_data["_save_permanently"] = True
        print_success("Role will be saved permanently after the session.")
    else:
        print_info("Role will only be used for this session.")
    
    return custom_role_data

def select_role_modes(roles_data: Optional[Dict]) -> Tuple[Optional[str], Optional[str]]:
    """Quick selection of role modes (scientist, philosopher, comedian, steel_worker)"""
    if not roles_data or 'persona_library' not in roles_data:
        return None, None

    print_section_header("Role Mode Selection", "🎭")

    # Define the 4 role modes
    role_modes = [
        ("scientist", "🔬 Scientist - Evidence-based, analytical, methodical"),
        ("philosopher", "🤔 Philosopher - Deep thinking, ethical, existential"),
        ("comedian", "😂 Comedian - Witty, observational, entertaining"),
        ("steel_worker", "🏭 Steel Worker - Practical, hands-on, blue-collar wisdom"),
        ("custom", "✨ Create Custom Role - Define your own role")
    ]

    # Check if preset role modes exist
    available_roles = []
    for role_key, description in role_modes[:-1]:  # Exclude custom for now
        if role_key in roles_data['persona_library']:
            available_roles.append((role_key, description))
    
    # Always add custom option
    available_roles.append(("custom", "✨ Create Custom Role - Define your own role"))

    if len(available_roles) == 1:  # Only custom available
        print_warning("No preset role modes found in persona library.")

    print_info("Choose role modes for your AI assistants:")

    # Agent A role mode
    print(colorize("🔹 ROLE MODE FOR AGENT A", Colors.BLUE, bold=True))
    roles_with_skip = [("skip", "Use default system prompt")] + available_roles
    choice_a = select_from_menu(roles_with_skip, "Agent A Role Mode", default="1")
    
    if choice_a == "1":
        role_a = None
    else:
        selected_role = available_roles[int(choice_a) - 2][0]
        if selected_role == "custom":
            print_info("Creating custom role for Agent A...")
            custom_role = create_custom_role()
            role_key = list(custom_role.keys())[0]
            # Add to roles_data temporarily for this session
            roles_data['persona_library'][role_key] = custom_role[role_key]
            # Check if user wants to save permanently
            if custom_role.get("_save_permanently"):
                if not hasattr(roles_data, '_custom_roles_to_save'):
                    roles_data['_custom_roles_to_save'] = {}
                roles_data['_custom_roles_to_save'][role_key] = custom_role[role_key]
            role_a = role_key
        else:
            role_a = selected_role

    # Agent B role mode
    print(colorize("🔸 ROLE MODE FOR AGENT B", Colors.MAGENTA, bold=True))
    choice_b = select_from_menu(roles_with_skip, "Agent B Role Mode", default="1")
    
    if choice_b == "1":
        role_b = None
    else:
        selected_role = available_roles[int(choice_b) - 2][0]
        if selected_role == "custom":
            print_info("Creating custom role for Agent B...")
            custom_role = create_custom_role()
            role_key = list(custom_role.keys())[0]
            # Add to roles_data temporarily for this session
            roles_data['persona_library'][role_key] = custom_role[role_key]
            # Check if user wants to save permanently
            if custom_role.get("_save_permanently"):
                if '_custom_roles_to_save' not in roles_data:
                    roles_data['_custom_roles_to_save'] = {}
                roles_data['_custom_roles_to_save'][role_key] = custom_role[role_key]
            role_b = role_key
        else:
            role_b = selected_role

    return role_a, role_b

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
    choice_a = select_from_menu(personas_with_skip, "Agent A Persona", default="1")
    persona_a = None if choice_a == "1" else personas[int(choice_a) - 2][0]

    # Agent B persona
    print(colorize("🔸 PERSONA FOR AGENT B", Colors.MAGENTA, bold=True))
    choice_b = select_from_menu(personas_with_skip, "Agent B Persona", default="1")
    persona_b = None if choice_b == "1" else personas[int(choice_b) - 2][0]

    return persona_a, persona_b

def get_conversation_starter() -> str:
    """Get the conversation starter with a nice prompt"""
    print_section_header("Conversation Starter", "💬")
    print_info("Enter a topic or question to start the conversation between the AI assistants.")
    print()

    default_starter = "What is the nature of artificial intelligence and consciousness?"
    starter = get_user_input("Conversation topic", default_starter)
    if starter:
        return starter
    return default_starter

def show_session_summary(provider_a: str, provider_b: str, max_rounds: int, mem_rounds: int, roles_data: Optional[Dict] = None, temp_a: Optional[float] = None, temp_b: Optional[float] = None):
    """Show a summary of the session configuration"""
    print_section_header("Session Configuration", "⚙️")

    spec_a = get_spec(provider_a)
    spec_b = get_spec(provider_b)

    # Get temperature values from roles data if available
    if roles_data:
        display_temp_a = roles_data.get('temp_a', temp_a or 0.7)
        display_temp_b = roles_data.get('temp_b', temp_b or 0.7)
    else:
        display_temp_a = temp_a or 0.7
        display_temp_b = temp_b or 0.7

    print(f"  {colorize('Agent A:', Colors.BLUE, bold=True)} {colorize(spec_a.label, Colors.GREEN)} ({colorize(spec_a.default_model, Colors.YELLOW)})")
    print(f"  {colorize('Agent B:', Colors.MAGENTA, bold=True)} {colorize(spec_b.label, Colors.GREEN)} ({colorize(spec_b.default_model, Colors.YELLOW)})")
    print(f"  {colorize('Max Rounds:', Colors.WHITE)} {colorize(str(max_rounds), Colors.CYAN)}")
    print(f"  {colorize('Memory Rounds:', Colors.WHITE)} {colorize(str(mem_rounds), Colors.CYAN)}")
    print(f"  {colorize('Temperature A:', Colors.WHITE)} {colorize(f'{display_temp_a:.1f}', Colors.CYAN)}")
    print(f"  {colorize('Temperature B:', Colors.WHITE)} {colorize(f'{display_temp_b:.1f}', Colors.CYAN)}")

    print()
    try:
        input(colorize("Press Enter to start the conversation... ", Colors.GREEN))
    except (EOFError, KeyboardInterrupt):
        # In non-interactive mode, just continue
        print(colorize("Starting conversation...", Colors.GREEN))

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

def lessen_stop_word_weight(text: str, stop_words: set, weight_factor: float = 0.5) -> bool:
    """Check if text contains stop words with lessened weight/influence"""
    lower_text = text.lower()
    stop_word_matches = [word for word in stop_words if word in lower_text]
    
    if not stop_word_matches:
        return False
    
    # Calculate weight based on stop word density and context
    text_length = len(text.split())
    stop_word_density = len(stop_word_matches) / max(text_length, 1)
    
    # Apply weight factor - higher density or multiple matches increase likelihood
    weighted_threshold = weight_factor * (1 + stop_word_density)
    
    return stop_word_density >= weighted_threshold

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

def apply_persona(agent: AgentRuntime, persona_key: Optional[str], roles_data: Optional[Dict]) -> tuple[AgentRuntime, Optional[float]]:
    """Apply persona from roles data if specified, returns (agent, custom_temperature)"""
    if not persona_key or not roles_data or 'persona_library' not in roles_data:
        return agent, None

    persona = roles_data['persona_library'].get(persona_key)
    if not persona:
        return agent, None

    # Update agent with persona settings
    if persona.get('system'):
        agent.system_prompt = persona['system']
        if persona.get('guidelines'):
            guidelines_text = "\n".join(f"• {g}" for g in persona['guidelines'])
            agent.system_prompt += f"\n\nGuidelines:\n{guidelines_text}"

    # Return custom temperature if specified in persona
    custom_temp = persona.get('temperature')
    return agent, custom_temp


def save_roles_file(roles_data: Dict, roles_path: str) -> bool:
    """Save roles data to JSON file with pretty formatting"""
    try:
        with open(roles_path, 'w', encoding='utf-8') as f:
            json.dump(roles_data, f, indent=2, ensure_ascii=False)
        return True
    except Exception as e:
        print_error(f"Could not save roles file {roles_path}: {e}")
        return False


def create_new_persona() -> Optional[Dict]:
    """Interactive creation of a new persona"""
    print_section_header("Create New Persona", "✨")

    # Get persona name
    name = get_user_input("Enter persona name (e.g., 'expert_analyst'): ").strip()
    if not name:
        return None

    # Select provider
    providers = provider_choices()
    provider_options = [(p, get_spec(p).label) for p in providers]
    print_info("Select provider for this persona:")
    choice = select_from_menu(provider_options, "Provider")
    if not choice:
        return None
    provider = provider_options[int(choice) - 1][0]

    # Get system prompt
    print_info("Enter system prompt (multi-line supported, empty line to finish):")
    system_lines = []
    while True:
        line = get_user_input("System> " if not system_lines else "     > ")
        if not line.strip() and system_lines:
            break
        if line.strip():
            system_lines.append(line)

    if not system_lines:
        print_warning("System prompt cannot be empty")
        return None

    system_prompt = "\n".join(system_lines)

    # Get guidelines
    print_info("Enter guidelines (one per line, empty line to finish):")
    guidelines = []
    while True:
        guideline = get_user_input(f"Guideline {len(guidelines)+1}> ")
        if not guideline.strip():
            break
        guidelines.append(guideline.strip())

    # Get model (optional)
    model_input = get_user_input(f"Model (optional, default: {get_spec(provider).default_model}): ").strip()
    model = model_input if model_input else None

    # Optional notes
    notes = get_user_input("Notes (optional): ").strip()

    persona = {
        "provider": provider,
        "model": model,
        "system": system_prompt,
        "guidelines": guidelines
    }

    if notes:
        persona["notes"] = notes

    return {name: persona}


def edit_persona(persona_key: str, persona_data: Dict) -> Optional[Dict]:
    """Interactive editing of an existing persona"""
    print_section_header(f"Edit Persona: {persona_key}", "✏️")

    options = [
        ("1", "Edit system prompt"),
        ("2", "Edit guidelines"),
        ("3", "Change provider"),
        ("4", "Change model"),
        ("5", "Edit notes"),
        ("6", "Delete persona"),
        ("7", "Back to main menu")
    ]

    while True:
        print_info(f"Current persona: {persona_key}")
        print(f"Provider: {colorize(persona_data['provider'], Colors.BLUE)}")
        print(f"Model: {colorize(str(persona_data.get('model', 'default')), Colors.CYAN)}")
        print(f"System: {persona_data['system'][:100]}...")
        print(f"Guidelines: {len(persona_data.get('guidelines', []))} items")
        if persona_data.get('notes'):
            print(f"Notes: {persona_data['notes'][:50]}...")

        choice = select_from_menu(options, "Edit Option")
        if not choice:
            return persona_data

        if choice == "1":  # Edit system prompt
            print_info("Current system prompt:")
            print(persona_data['system'])
            new_system = get_user_input("New system prompt (or Enter to keep current): ")
            if new_system.strip():
                persona_data['system'] = new_system.strip()

        elif choice == "2":  # Edit guidelines
            print_info("Current guidelines:")
            for i, g in enumerate(persona_data.get('guidelines', []), 1):
                print(f"{i}. {g}")

            print_info("Enter new guidelines (one per line, empty line to finish):")
            guidelines = []
            while True:
                guideline = get_user_input(f"Guideline {len(guidelines)+1}> ")
                if not guideline.strip():
                    break
                guidelines.append(guideline.strip())

            if guidelines:
                persona_data['guidelines'] = guidelines

        elif choice == "3":  # Change provider
            providers = provider_choices()
            provider_options = [(p, get_spec(p).label) for p in providers]
            print_info("Select new provider:")
            prov_choice = select_from_menu(provider_options, "Provider")
            if prov_choice:
                persona_data['provider'] = provider_options[int(prov_choice) - 1][0]

        elif choice == "4":  # Change model
            current_model = persona_data.get('model', 'default')
            print_info(f"Current model: {current_model}")
            new_model = get_user_input("New model (or Enter for default): ").strip()
            persona_data['model'] = new_model if new_model else None

        elif choice == "5":  # Edit notes
            current_notes = persona_data.get('notes', '')
            print_info(f"Current notes: {current_notes}")
            new_notes = get_user_input("New notes (or Enter to clear): ").strip()
            if new_notes:
                persona_data['notes'] = new_notes
            elif 'notes' in persona_data:
                del persona_data['notes']

        elif choice == "6":  # Delete persona
            confirm = get_user_input(f"Delete persona '{persona_key}'? (yes/no): ").lower()
            if confirm in ['yes', 'y']:
                return None  # Signal to delete

        elif choice == "7":  # Back
            return persona_data

    return persona_data


def manage_roles_configuration(roles_path: str = "roles.json"):
    """Interactive roles management interface"""
    while True:
        print_section_header("Roles Configuration Manager", "⚙️")

        # Load current roles
        roles_data = load_roles_file(roles_path)
        if not roles_data:
            print_warning(f"Could not load {roles_path}. Creating new configuration...")
            roles_data = {
                "agent_a": {
                    "provider": "openai",
                    "model": None,
                    "system": "You are ChatGPT. Be concise, truthful, and witty.",
                    "guidelines": []
                },
                "agent_b": {
                    "provider": "anthropic",
                    "model": None,
                    "system": "You are Claude. Be concise, compassionate, truthful, and reflective.",
                    "guidelines": []
                },
                "persona_library": {},
                "stop_words": ["wrap up", "end chat", "terminate"],
                "temp_a": 0.6,
                "temp_b": 0.7
            }

        # Show current status
        persona_count = len(roles_data.get('persona_library', {}))
        print_info(f"Current configuration: {persona_count} personas")

        options = [
            ("1", "View/Edit personas"),
            ("2", "Create new persona"),
            ("3", "Edit default agents (A & B)"),
            ("4", "Edit temperature settings"),
            ("5", "Edit stop words"),
            ("6", "Export configuration"),
            ("7", "Import configuration"),
            ("8", "Reset to defaults"),
            ("9", "Back to main menu")
        ]

        choice = select_from_menu(options, "Roles Management")
        if not choice:
            break

        if choice == "1":  # View/Edit personas
            if not roles_data.get('persona_library'):
                print_warning("No personas found. Create one first!")
                continue

            persona_options = []
            for key, persona in roles_data['persona_library'].items():
                provider_name = get_spec(persona['provider']).label
                persona_options.append((key, f"{key} ({provider_name})"))

            print_info("Select persona to edit:")
            persona_choice = select_from_menu(persona_options, "Persona")
            if persona_choice:
                selected_key = persona_options[int(persona_choice) - 1][0]
                selected_persona = roles_data['persona_library'][selected_key]

                result = edit_persona(selected_key, selected_persona.copy())
                if result is None:  # Delete requested
                    del roles_data['persona_library'][selected_key]
                    print_success(f"Persona '{selected_key}' deleted")
                else:
                    roles_data['persona_library'][selected_key] = result
                    print_success(f"Persona '{selected_key}' updated")

                if save_roles_file(roles_data, roles_path):
                    print_success("Configuration saved")

        elif choice == "2":  # Create new persona
            new_persona = create_new_persona()
            if new_persona:
                persona_name = list(new_persona.keys())[0]
                if persona_name in roles_data.get('persona_library', {}):
                    overwrite = get_user_input(f"Persona '{persona_name}' exists. Overwrite? (yes/no): ")
                    if overwrite.lower() not in ['yes', 'y']:
                        continue

                if 'persona_library' not in roles_data:
                    roles_data['persona_library'] = {}
                roles_data['persona_library'].update(new_persona)

                if save_roles_file(roles_data, roles_path):
                    print_success(f"Persona '{persona_name}' created and saved")

        elif choice == "3":  # Edit default agents
            agent_options = [("agent_a", "Agent A"), ("agent_b", "Agent B")]
            agent_choice = select_from_menu(agent_options, "Select Agent")
            if agent_choice:
                agent_key = agent_options[int(agent_choice) - 1][0]
                agent_data = roles_data[agent_key]

                print_section_header(f"Edit {agent_key.upper()}", "🤖")

                edit_options = [
                    ("1", "Edit system prompt"),
                    ("2", "Edit guidelines"),
                    ("3", "Change provider"),
                    ("4", "Back")
                ]

                edit_choice = select_from_menu(edit_options, "Edit Option")
                if edit_choice == "1":
                    print_info("Current system prompt:")
                    print(agent_data['system'])
                    new_system = get_user_input("New system prompt: ")
                    if new_system.strip():
                        agent_data['system'] = new_system.strip()
                        if save_roles_file(roles_data, roles_path):
                            print_success("Agent updated")

                elif edit_choice == "2":
                    print_info("Current guidelines:")
                    for i, g in enumerate(agent_data.get('guidelines', []), 1):
                        print(f"{i}. {g}")

                    print_info("Enter new guidelines (one per line, empty line to finish):")
                    guidelines = []
                    while True:
                        guideline = get_user_input(f"Guideline {len(guidelines)+1}> ")
                        if not guideline.strip():
                            break
                        guidelines.append(guideline.strip())

                    if guidelines:
                        agent_data['guidelines'] = guidelines
                        if save_roles_file(roles_data, roles_path):
                            print_success("Guidelines updated")

                elif edit_choice == "3":
                    providers = provider_choices()
                    provider_options = [(p, get_spec(p).label) for p in providers]
                    print_info("Select new provider:")
                    prov_choice = select_from_menu(provider_options, "Provider")
                    if prov_choice:
                        agent_data['provider'] = provider_options[int(prov_choice) - 1][0]
                        if save_roles_file(roles_data, roles_path):
                            print_success("Provider updated")

        elif choice == "4":  # Edit temperature settings
            print_section_header("Temperature Settings", "🌡️")
            print_info(f"Current temp_a: {roles_data.get('temp_a', 0.6)}")
            print_info(f"Current temp_b: {roles_data.get('temp_b', 0.7)}")

            new_temp_a = get_user_input("New temp_a (0.0-2.0): ").strip()
            if new_temp_a:
                try:
                    temp_val = float(new_temp_a)
                    if 0 <= temp_val <= 2:
                        roles_data['temp_a'] = temp_val
                    else:
                        print_warning("Temperature must be between 0.0 and 2.0")
                except ValueError:
                    print_warning("Invalid temperature value")

            new_temp_b = get_user_input("New temp_b (0.0-2.0): ").strip()
            if new_temp_b:
                try:
                    temp_val = float(new_temp_b)
                    if 0 <= temp_val <= 2:
                        roles_data['temp_b'] = temp_val
                    else:
                        print_warning("Temperature must be between 0.0 and 2.0")
                except ValueError:
                    print_warning("Invalid temperature value")

            if save_roles_file(roles_data, roles_path):
                print_success("Temperature settings updated")

        elif choice == "5":  # Edit stop words
            print_section_header("Stop Words", "🛑")
            current_words = roles_data.get('stop_words', [])
            print_info(f"Current stop words: {', '.join(current_words)}")

            print_info("Enter new stop words (one per line, empty line to finish):")
            stop_words = []
            while True:
                word = get_user_input(f"Stop word {len(stop_words)+1}> ")
                if not word.strip():
                    break
                stop_words.append(word.strip())

            if stop_words:
                roles_data['stop_words'] = stop_words
                if save_roles_file(roles_data, roles_path):
                    print_success("Stop words updated")

        elif choice == "6":  # Export configuration
            export_path = get_user_input("Export to file (default: roles_backup.json): ").strip()
            if not export_path:
                export_path = "roles_backup.json"

            if save_roles_file(roles_data, export_path):
                print_success(f"Configuration exported to {export_path}")

        elif choice == "7":  # Import configuration
            import_path = get_user_input("Import from file: ").strip()
            if import_path and os.path.exists(import_path):
                imported_data = load_roles_file(import_path)
                if imported_data:
                    confirm = get_user_input("This will replace current configuration. Continue? (yes/no): ")
                    if confirm.lower() in ['yes', 'y']:
                        if save_roles_file(imported_data, roles_path):
                            print_success("Configuration imported successfully")
                        else:
                            print_error("Failed to save imported configuration")
                else:
                    print_error("Failed to load import file")
            else:
                print_error("Import file not found")

        elif choice == "8":  # Reset to defaults
            confirm = get_user_input("Reset to default configuration? This cannot be undone. (yes/no): ")
            if confirm.lower() in ['yes', 'y']:
                # Create default configuration
                default_config = {
                    "agent_a": {
                        "provider": "openai",
                        "model": None,
                        "system": "You are ChatGPT. Be concise, truthful, and witty. Prioritise verifiable facts and clear reasoning.",
                        "guidelines": [
                            "Cite sources or examples when you make factual claims.",
                            "Favour clear structure and highlight key takeaways early.",
                            "When comparing sources, cite examples and name key scholars or texts.",
                            "If asked for probabilities, explain your methodology and uncertainty.",
                            "Prefer structured answers with brief lists, then a crisp conclusion."
                        ]
                    },
                    "agent_b": {
                        "provider": "anthropic",
                        "model": None,
                        "system": "You are Claude. Be concise, compassionate, truthful, and reflective. Balance clarity with nuance.",
                        "guidelines": [
                            "Surface competing perspectives fairly before choosing a stance.",
                            "Make uncertainty explicit and flag assumptions.",
                            "Surface connections across traditions without overclaiming equivalence.",
                            "Offer metaphors to illuminate abstract ideas, but keep them tight.",
                            "State uncertainty explicitly when evidence is thin or contested."
                        ]
                    },
                    "persona_library": {},
                    "stop_words": ["wrap up", "end chat", "terminate"],
                    "temp_a": 0.6,
                    "temp_b": 0.7
                }

                if save_roles_file(default_config, roles_path):
                    print_success("Configuration reset to defaults")

        elif choice == "9":  # Back
            break


async def ping_provider(provider_key: str) -> Dict[str, any]:
    """Test connectivity to a specific provider"""
    spec = get_spec(provider_key)
    result = {
        "provider": provider_key,
        "label": spec.label,
        "status": "unknown",
        "message": "",
        "response_time": None,
        "model": spec.default_model,
        "error": None
    }

    try:
        import time
        start_time = time.time()

        # Create a minimal test agent
        if provider_key == "openai":
            try:
                api_key = ensure_credentials(provider_key)
                client = OpenAIChat(model=spec.default_model, api_key=api_key)
                test_messages = [{"role": "user", "content": "Hello"}]

                # Try to get a minimal response
                response_started = False
                async for chunk in client.stream(test_messages, temperature=0.1, max_tokens=5):
                    if chunk.strip():
                        response_started = True
                        break  # We got a response, that's enough

                if response_started:
                    result["status"] = "online"
                    result["message"] = "✅ API key valid, model accessible"
                else:
                    result["status"] = "error"
                    result["message"] = "❌ No response received"

            except Exception as e:
                result["status"] = "error"
                error_str = str(e)
                if "401" in error_str or "unauthorized" in error_str.lower():
                    result["message"] = "❌ Invalid API key - Check OPENAI_API_KEY environment variable"
                    result["troubleshooting"] = [
                        "1. Verify OPENAI_API_KEY is set correctly",
                        "2. Check if API key has sufficient credits",
                        "3. Ensure API key has not expired"
                    ]
                elif "403" in error_str or "forbidden" in error_str.lower():
                    result["message"] = "❌ Access forbidden - API key lacks permissions for this model"
                    result["troubleshooting"] = [
                        "1. Verify your API key has access to the model",
                        "2. Check if your account has the required tier access",
                        "3. Try with a different model (e.g., gpt-3.5-turbo)"
                    ]
                elif "429" in error_str:
                    result["message"] = "❌ Rate limit exceeded - Too many requests"
                    result["troubleshooting"] = [
                        "1. Wait before retrying (rate limits reset over time)",
                        "2. Check your usage limits in OpenAI dashboard",
                        "3. Consider upgrading your API plan"
                    ]
                elif "connection" in error_str.lower() or "network" in error_str.lower():
                    result["message"] = "❌ Network connection failed"
                    result["troubleshooting"] = [
                        "1. Check your internet connection",
                        "2. Verify firewall/proxy settings",
                        "3. Try again in a few moments"
                    ]
                else:
                    result["message"] = f"❌ {error_str}"
                    result["troubleshooting"] = [
                        "1. Check your API key and network connection",
                        "2. Verify the model name is correct",
                        "3. Check OpenAI service status"
                    ]
                result["error"] = error_str

        elif provider_key == "anthropic":
            try:
                api_key = ensure_credentials(provider_key)
                client = AnthropicChat(api_key=api_key, model=spec.default_model)

                response_started = False
                async for chunk in client.stream("You are a test assistant. Respond with just 'OK'.", [{"role": "user", "content": "Hello"}], temperature=0.1, max_tokens=5):
                    if chunk.strip():
                        response_started = True
                        break

                if response_started:
                    result["status"] = "online"
                    result["message"] = "✅ API key valid, model accessible"
                else:
                    result["status"] = "error"
                    result["message"] = "❌ No response received"

            except Exception as e:
                result["status"] = "error"
                error_str = str(e)
                if "401" in error_str or "unauthorized" in error_str.lower():
                    result["message"] = "❌ Invalid API key - Check ANTHROPIC_API_KEY environment variable"
                    result["troubleshooting"] = [
                        "1. Verify ANTHROPIC_API_KEY is set correctly",
                        "2. Ensure API key is valid and active",
                        "3. Check if you have sufficient credits"
                    ]
                elif "403" in error_str:
                    result["message"] = "❌ Access forbidden - Check API key permissions"
                    result["troubleshooting"] = [
                        "1. Verify your API key has access to Claude models",
                        "2. Check your account status",
                        "3. Try a different model version"
                    ]
                elif "429" in error_str:
                    result["message"] = "❌ Rate limit exceeded"
                    result["troubleshooting"] = [
                        "1. Wait before retrying",
                        "2. Check your usage limits",
                        "3. Consider API tier upgrade"
                    ]
                else:
                    result["message"] = f"❌ {error_str}"
                    result["troubleshooting"] = [
                        "1. Check ANTHROPIC_API_KEY environment variable",
                        "2. Verify network connectivity",
                        "3. Check Anthropic service status"
                    ]
                result["error"] = error_str

        elif provider_key == "gemini":
            try:
                api_key = ensure_credentials(provider_key)
                client = GeminiChat(api_key=api_key, model=spec.default_model)

                response_started = False
                test_contents = [{"role": "user", "parts": [{"text": "Hello"}]}]
                async for chunk in client.stream("You are a test assistant. Respond with just 'OK'.", test_contents, temperature=0.1, max_tokens=5):
                    if chunk.strip():
                        response_started = True
                        break

                if response_started:
                    result["status"] = "online"
                    result["message"] = "✅ API key valid, model accessible"
                else:
                    result["status"] = "error"
                    result["message"] = "❌ No response received"

            except Exception as e:
                result["status"] = "error"
                error_str = str(e)
                if "401" in error_str or "invalid" in error_str.lower():
                    result["message"] = "❌ Invalid API key - Check GEMINI_API_KEY environment variable"
                    result["troubleshooting"] = [
                        "1. Verify GEMINI_API_KEY is set correctly",
                        "2. Enable Gemini API in Google Cloud Console",
                        "3. Ensure API key has Generative AI permissions"
                    ]
                elif "429" in error_str:
                    result["message"] = "❌ Rate limit exceeded or quota exhausted"
                    result["troubleshooting"] = [
                        "1. Wait for rate limit to reset (usually hourly/daily)",
                        "2. Check your Gemini API quota in Google Cloud Console",
                        "3. Enable billing if using free tier limits",
                        "4. Consider requesting quota increase"
                    ]
                elif "403" in error_str:
                    result["message"] = "❌ API access forbidden"
                    result["troubleshooting"] = [
                        "1. Enable Gemini API in Google Cloud Console",
                        "2. Check API key permissions",
                        "3. Verify billing is enabled for your project"
                    ]
                else:
                    result["message"] = f"❌ {error_str}"
                    result["troubleshooting"] = [
                        "1. Check GEMINI_API_KEY environment variable",
                        "2. Enable Gemini API in Google Cloud Console",
                        "3. Verify network connectivity"
                    ]
                result["error"] = error_str

        elif provider_key == "ollama":
            try:
                client = OllamaChat(model=spec.default_model)

                response_started = False
                test_messages = [{"role": "user", "content": "Hello"}]
                async for chunk in client.stream(test_messages, "You are a test assistant. Respond with just 'OK'.", temperature=0.1, max_tokens=5):
                    if chunk.strip():
                        response_started = True
                        break

                if response_started:
                    result["status"] = "online"
                    result["message"] = "✅ Ollama server accessible, model available"
                else:
                    result["status"] = "error"
                    result["message"] = "❌ No response received"

            except Exception as e:
                result["status"] = "error"
                error_str = str(e)
                if "Connection refused" in error_str or "connection error" in error_str.lower():
                    result["message"] = "❌ Ollama server not running or unreachable"
                    result["troubleshooting"] = [
                        "1. Start Ollama: 'ollama serve' or 'systemctl start ollama'",
                        "2. Check if Ollama is running on correct port (default: 11434)",
                        "3. Verify OLLAMA_HOST environment variable if using custom host",
                        "4. Ensure firewall allows connections to Ollama port"
                    ]
                elif "404" in error_str:
                    result["message"] = "❌ Ollama model not found or API endpoint incorrect"
                    result["troubleshooting"] = [
                        "1. Pull the model: 'ollama pull llama3.1:8b-instruct'",
                        "2. List available models: 'ollama list'",
                        "3. Check model name in OLLAMA_MODEL environment variable",
                        "4. Verify Ollama API is accessible at /api/chat"
                    ]
                else:
                    result["message"] = f"❌ {error_str}"
                    result["troubleshooting"] = [
                        "1. Start Ollama server: 'ollama serve'",
                        "2. Pull required model: 'ollama pull llama3.1:8b-instruct'",
                        "3. Check Ollama logs for errors",
                        "4. Verify OLLAMA_HOST and OLLAMA_MODEL settings"
                    ]
                result["error"] = error_str

        elif provider_key == "lmstudio":
            try:
                base_url = os.getenv("LMSTUDIO_BASE_URL", "http://localhost:1234/v1")
                client = OpenAIChat(model=spec.default_model, api_key=None, base_url=base_url)

                response_started = False
                test_messages = [{"role": "user", "content": "Hello"}]
                async for chunk in client.stream(test_messages, temperature=0.1, max_tokens=5):
                    if chunk.strip():
                        response_started = True
                        break

                if response_started:
                    result["status"] = "online"
                    result["message"] = f"✅ LM Studio server accessible at {base_url}"
                else:
                    result["status"] = "error"
                    result["message"] = "❌ No response received"

            except Exception as e:
                result["status"] = "error"
                error_str = str(e)
                if "Connection refused" in error_str or "connection error" in error_str.lower():
                    result["message"] = f"❌ LM Studio server not running at {os.getenv('LMSTUDIO_BASE_URL', 'http://localhost:1234/v1')}"
                    result["troubleshooting"] = [
                        "1. Start LM Studio application",
                        "2. Load a model in LM Studio",
                        "3. Start the local server in LM Studio (usually port 1234)",
                        "4. Verify LMSTUDIO_BASE_URL environment variable",
                        "5. Check if another application is using port 1234"
                    ]
                elif "404" in error_str:
                    result["message"] = "❌ LM Studio API endpoint not found"
                    result["troubleshooting"] = [
                        "1. Ensure local server is started in LM Studio",
                        "2. Verify the correct API endpoint URL",
                        "3. Check if model is loaded and ready",
                        "4. Try restarting the LM Studio server"
                    ]
                else:
                    result["message"] = f"❌ {error_str}"
                    result["troubleshooting"] = [
                        "1. Start LM Studio and load a model",
                        "2. Enable local server in LM Studio settings",
                        "3. Check LMSTUDIO_BASE_URL and LMSTUDIO_MODEL",
                        "4. Verify firewall allows connections to LM Studio"
                    ]
                result["error"] = error_str

        else:
            result["status"] = "unsupported"
            result["message"] = f"❓ Provider {provider_key} not supported for ping test"

        end_time = time.time()
        result["response_time"] = round((end_time - start_time) * 1000, 2)  # Convert to milliseconds

    except Exception as e:
        result["status"] = "error"
        result["message"] = f"❌ Unexpected error: {str(e)}"
        result["error"] = str(e)

    return result


async def ping_all_providers() -> Dict[str, Dict]:
    """Test connectivity to all available providers"""
    print_section_header("Provider Connectivity Test", "🌐")
    print_info("Testing connectivity to all providers...")

    results = {}
    providers = provider_choices()

    for provider_key in providers:
        print(f"\n{colorize('Testing', Colors.YELLOW)} {colorize(get_spec(provider_key).label, Colors.WHITE)}...")
        try:
            result = await ping_provider(provider_key)
            results[provider_key] = result

            # Show immediate result
            status_color = Colors.GREEN if result["status"] == "online" else Colors.RED
            if result["response_time"]:
                time_info = f" ({result['response_time']}ms)"
            else:
                time_info = ""

            print(f"  {colorize(result['message'], status_color)}{time_info}")

        except Exception as e:
            results[provider_key] = {
                "provider": provider_key,
                "label": get_spec(provider_key).label,
                "status": "error",
                "message": f"❌ Test failed: {str(e)}",
                "error": str(e),
                "response_time": None
            }
            print(f"  {colorize(f'❌ Test failed: {str(e)}', Colors.RED)}")

    return results


def show_provider_status_summary(results: Dict[str, Dict]):
    """Display a comprehensive summary of provider status"""
    print_section_header("Provider Status Summary", "📊")

    online_count = sum(1 for r in results.values() if r["status"] == "online")
    total_count = len(results)

    print(f"{colorize('Overall Status:', Colors.WHITE, bold=True)} {online_count}/{total_count} providers online")
    print()

    # Group by status
    online_providers = []
    offline_providers = []
    error_providers = []

    for result in results.values():
        if result["status"] == "online":
            online_providers.append(result)
        elif result["status"] == "error":
            error_providers.append(result)
        else:
            offline_providers.append(result)

    # Show online providers
    if online_providers:
        print(f"{colorize('🟢 ONLINE PROVIDERS:', Colors.GREEN, bold=True)}")
        for provider in online_providers:
            time_info = f" - {provider['response_time']}ms" if provider.get("response_time") else ""
            print(f"  • {colorize(provider['label'], Colors.GREEN)} ({provider['model']}){time_info}")
        print()

    # Show error providers
    if error_providers:
        print(f"{colorize('🔴 PROVIDERS WITH ISSUES:', Colors.RED, bold=True)}")
        for provider in error_providers:
            print(f"  • {colorize(provider['label'], Colors.RED)}: {provider['message']}")
            if provider.get("troubleshooting"):
                print(f"    {colorize('💡 Troubleshooting:', Colors.YELLOW, bold=True)}")
                for tip in provider["troubleshooting"]:
                    print(f"      {colorize(tip, Colors.YELLOW)}")
                print()
        print()

    # Show offline providers
    if offline_providers:
        print(f"{colorize('⚪ OTHER PROVIDERS:', Colors.YELLOW, bold=True)}")
        for provider in offline_providers:
            print(f"  • {colorize(provider['label'], Colors.YELLOW)}: {provider['message']}")
        print()

    # Recommendations
    print(f"{colorize('💡 RECOMMENDATIONS:', Colors.CYAN, bold=True)}")
    if online_count == 0:
        print("  • No providers are currently available")
        print("  • Check your API keys and network connectivity")
        print("  • Verify local services (Ollama, LM Studio) are running")
    elif online_count < total_count:
        print("  • Some providers are unavailable")
        print("  • Consider using available providers for your conversations")
        if any(r["provider"] in ["ollama", "lmstudio"] and r["status"] == "error" for r in results.values()):
            print("  • Start local services (Ollama/LM Studio) if needed")
    else:
        print("  • All providers are online and ready to use! 🎉")


async def provider_ping_menu():
    """Interactive provider ping menu"""
    while True:
        print_section_header("Provider Connectivity Test", "🌐")

        options = [
            ("1", "Test all providers"),
            ("2", "Test specific provider"),
            ("3", "Show detailed diagnostics"),
            ("4", "Back to main menu")
        ]

        choice = select_from_menu(options, "Provider Test Options")
        if not choice:
            break

        if choice == "1":  # Test all providers
            results = await ping_all_providers()
            print()
            show_provider_status_summary(results)
            input(colorize("\nPress Enter to continue...", Colors.GREEN))

        elif choice == "2":  # Test specific provider
            providers = provider_choices()
            provider_options = [(p, get_spec(p).label) for p in providers]
            print_info("Select provider to test:")
            prov_choice = select_from_menu(provider_options, "Provider")
            if prov_choice:
                provider_key = provider_options[int(prov_choice) - 1][0]
                print(f"\n{colorize('Testing', Colors.YELLOW)} {colorize(get_spec(provider_key).label, Colors.WHITE)}...")
                result = await ping_provider(provider_key)

                print(f"\n{colorize('DETAILED RESULTS:', Colors.WHITE, bold=True)}")
                print(f"Provider: {colorize(result['label'], Colors.CYAN)}")
                print(f"Model: {colorize(result['model'], Colors.YELLOW)}")
                print(f"Status: {colorize(result['status'].upper(), Colors.GREEN if result['status'] == 'online' else Colors.RED)}")
                print(f"Message: {result['message']}")
                if result.get("response_time"):
                    response_time_str = f"{result['response_time']}ms"
                    print(f"Response Time: {colorize(response_time_str, Colors.CYAN)}")
                if result.get("error"):
                    print(f"Error Details: {colorize(result['error'], Colors.RED)}")

                input(colorize("\nPress Enter to continue...", Colors.GREEN))

        elif choice == "3":  # Show detailed diagnostics
            print_section_header("System Diagnostics", "🔧")

            print(f"{colorize('ENVIRONMENT VARIABLES:', Colors.WHITE, bold=True)}")
            env_vars = [
                "OPENAI_API_KEY", "ANTHROPIC_API_KEY", "GEMINI_API_KEY",
                "LMSTUDIO_BASE_URL", "OLLAMA_HOST", "OPENAI_MODEL", "ANTHROPIC_MODEL"
            ]

            for env_var in env_vars:
                value = os.getenv(env_var)
                if value:
                    # Mask API keys for security
                    if "API_KEY" in env_var and len(value) > 8:
                        masked_value = value[:4] + "*" * (len(value) - 8) + value[-4:]
                        print(f"  {env_var}: {colorize(masked_value, Colors.GREEN)}")
                    else:
                        print(f"  {env_var}: {colorize(value, Colors.GREEN)}")
                else:
                    print(f"  {env_var}: {colorize('Not set', Colors.RED)}")

            print(f"\n{colorize('PROVIDER SPECIFICATIONS:', Colors.WHITE, bold=True)}")
            for provider_key in provider_choices():
                spec = get_spec(provider_key)
                print(f"  {spec.label} ({provider_key}):")
                print(f"    Default Model: {colorize(spec.default_model, Colors.YELLOW)}")
                print(f"    Needs Key: {colorize(str(spec.needs_key), Colors.CYAN)}")
                if spec.key_env:
                    key_status = "✅ Set" if os.getenv(spec.key_env) else "❌ Missing"
                    print(f"    API Key ({spec.key_env}): {colorize(key_status, Colors.GREEN if '✅' in key_status else Colors.RED)}")

            input(colorize("\nPress Enter to continue...", Colors.GREEN))

        elif choice == "4":  # Back
            break


# ───────────────────────── Main Bridge Logic ─────────────────────────

async def show_main_menu() -> str:
    """Show main menu and return user choice"""
    print_section_header("Main Menu", "🚀")

    options = [
        ("1", "Start Chat Bridge Conversation"),
        ("2", "Manage Roles & Personas"),
        ("3", "Test Provider Connectivity"),
        ("4", "Exit")
    ]

    return select_from_menu(options, "What would you like to do?")


async def run_bridge(args):
    """Main bridge execution with beautiful progress display"""

    print_banner()

    # Check if we should show main menu (interactive mode only)
    if not (args.provider_a and args.provider_b and getattr(args, 'starter', None)):
        while True:
            choice = await show_main_menu()

            if choice == "1":  # Start conversation
                break
            elif choice == "2":  # Manage roles
                roles_path = getattr(args, 'roles', 'roles.json')
                manage_roles_configuration(roles_path)
                continue
            elif choice == "3":  # Test provider connectivity
                await provider_ping_menu()
                continue
            elif choice == "4":  # Exit
                print(f"\n{colorize('👋 Goodbye!', Colors.YELLOW)}")
                return
            else:
                print_error("Invalid choice. Please try again.")
                continue

    # Load roles if specified
    roles_data = load_roles_file(getattr(args, 'roles', None))

    # Interactive setup
    if not (args.provider_a and args.provider_b and getattr(args, 'starter', None)):
        provider_a, provider_b = select_providers()

        if roles_data:
            # Ask user if they want to use role modes or full persona selection
            print_section_header("Persona Options", "🎭")
            mode_options = [
                ("1", "Quick Role Modes (Scientist, Philosopher, Comedian, Steel Worker)"),
                ("2", "Full Persona Library"),
                ("3", "Skip - Use default prompts")
            ]
            
            mode_choice = select_from_menu(mode_options, "Select persona mode", default="1")
            
            if mode_choice == "1":
                persona_a, persona_b = select_role_modes(roles_data)
            elif mode_choice == "2":
                persona_a, persona_b = select_personas(roles_data)
            else:
                persona_a, persona_b = None, None
        else:
            persona_a, persona_b = None, None

        starter = get_conversation_starter()
    else:
        provider_a, provider_b = args.provider_a, args.provider_b
        persona_a, persona_b = None, None
        starter = args.starter

    # Get temperature values from roles data if available
    temp_a = roles_data.get('temp_a', args.temp_a) if roles_data else args.temp_a
    temp_b = roles_data.get('temp_b', args.temp_b) if roles_data else args.temp_b

    show_session_summary(provider_a, provider_b, args.max_rounds, args.mem_rounds, roles_data, temp_a, temp_b)

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
        agent_a = create_agent("a", provider_a, resolve_model(provider_a, args.model_a), temp_a, get_spec(provider_a).default_system)
        agent_b = create_agent("b", provider_b, resolve_model(provider_b, args.model_b), temp_b, get_spec(provider_b).default_system)

        # Apply personas if specified and handle custom temperatures
        if roles_data:
            agent_a, custom_temp_a = apply_persona(agent_a, persona_a, roles_data)
            agent_b, custom_temp_b = apply_persona(agent_b, persona_b, roles_data)
            
            # Override temperatures if custom roles specify them
            if custom_temp_a is not None:
                temp_a = custom_temp_a
                agent_a.temperature = custom_temp_a
            if custom_temp_b is not None:
                temp_b = custom_temp_b
                agent_b.temperature = custom_temp_b
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
            async for chunk in agent.stream_reply(recent_context, args.mem_rounds):
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
            
        # Save custom roles if any were created and marked for saving
        if roles_data and '_custom_roles_to_save' in roles_data:
            roles_path = getattr(args, 'roles', 'roles.json')
            try:
                # Load current roles file to preserve existing data
                current_roles = load_roles_file(roles_path) or roles_data
                # Add custom roles to persona library
                for role_name, role_data in roles_data['_custom_roles_to_save'].items():
                    current_roles['persona_library'][role_name] = role_data
                # Save updated roles file
                if save_roles_file(current_roles, roles_path):
                    print_success(f"Custom roles saved to {roles_path}")
                else:
                    print_error("Failed to save custom roles")
            except Exception as e:
                print_error(f"Error saving custom roles: {e}")
                
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