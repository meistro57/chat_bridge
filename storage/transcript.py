"""
Storage and logging utilities for Chat Bridge.

Handles transcripts, conversation history, and session logging.
"""

from __future__ import annotations

import logging
import os
import re
from dataclasses import dataclass, field
from datetime import datetime
from typing import Dict, List, Optional, Tuple


GLOBAL_LOG = "chat_bridge.log"


@dataclass
class Transcript:
    turns: List[Dict] = field(default_factory=list)
    session_config: Optional[Dict] = field(default_factory=dict)

    def add(self, agent: str, role: str, timestamp: str, content: str, round_num: Optional[int] = None):
        self.turns.append({"agent": agent, "role": role, "timestamp": timestamp, "content": content, "round_num": round_num})

    def set_session_config(self, config: Dict):
        """Set the session configuration for the transcript"""
        self.session_config = config

    def dump(self, path: str):
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "w", encoding="utf-8") as f:
            f.write("# Chat Bridge Transcript\n\n")

            # Write session configuration
            if self.session_config:
                f.write("## Session Configuration\n\n")
                f.write(f"**Session ID:** {self.session_config.get('session_id', 'N/A')}\n")
                f.write(f"**Started:** {self.session_config.get('session_start', 'N/A')}\n")
                f.write(f"**Conversation Starter:** {self.session_config.get('starter', 'N/A')}\n\n")

                # Agent configurations
                f.write("### Agent Configuration\n\n")
                f.write(f"**Agent A Provider:** {self.session_config.get('provider_a', 'N/A')}\n")
                f.write(f"**Agent A Model:** {self.session_config.get('model_a', 'N/A')}\n")
                f.write(f"**Agent A Temperature:** {self.session_config.get('temp_a', 'N/A')}\n")
                if self.session_config.get('persona_a'):
                    f.write(f"**Agent A Persona:** {self.session_config.get('persona_a')}\n")
                if self.session_config.get('system_prompt_a'):
                    f.write(f"**Agent A System Prompt:** {self.session_config.get('system_prompt_a')}\n")

                f.write(f"\n**Agent B Provider:** {self.session_config.get('provider_b', 'N/A')}\n")
                f.write(f"**Agent B Model:** {self.session_config.get('model_b', 'N/A')}\n")
                f.write(f"**Agent B Temperature:** {self.session_config.get('temp_b', 'N/A')}\n")
                if self.session_config.get('persona_b'):
                    f.write(f"**Agent B Persona:** {self.session_config.get('persona_b')}\n")
                if self.session_config.get('system_prompt_b'):
                    f.write(f"**Agent B System Prompt:** {self.session_config.get('system_prompt_b')}\n")

                # Session settings
                f.write("\n### Session Settings\n\n")
                f.write(f"**Max Rounds:** {self.session_config.get('max_rounds', 'N/A')}\n")
                f.write(f"**Memory Rounds:** {self.session_config.get('mem_rounds', 'N/A')}\n")
                f.write(f"**Stop Word Detection:** {'Enabled' if self.session_config.get('stop_word_detection_enabled', True) else 'Disabled'}\n")

                if self.session_config.get('stop_words'):
                    stop_words_str = ', '.join(self.session_config.get('stop_words', []))
                    f.write(f"**Stop Words:** {stop_words_str}\n")

                f.write("\n---\n\n")

            # Write conversation turns
            f.write("## Conversation\n\n")
            for turn in self.turns:
                # Add round marker if available
                if turn.get('round_num'):
                    f.write(f"**Round {turn['round_num']}**\n\n")
                f.write(f"### {turn['agent']} ({turn['timestamp']})\n\n")
                f.write(f"{turn['content']}\n\n")


@dataclass
class ConversationHistory:
    """Simple conversation history tracker.

    Note: This uses a simple Turn type from bridge_agents.
    """
    turns: List = field(default_factory=list)

    def add_turn(self, author: str, text: str):
        # Import Turn from bridge_agents to avoid circular dependency
        from bridge_agents import Turn
        self.turns.append(Turn(author=author, text=text))

    @property
    def flat_texts(self) -> List[str]:
        return [turn.text for turn in self.turns]

    def recent_turns(self, limit: int) -> List:
        return self.turns[-limit:]


def setup_logging() -> Tuple[logging.Logger, logging.Logger]:
    """Set up comprehensive logging with error tracking"""
    bridge_logger = logging.getLogger("bridge")
    bridge_logger.setLevel(logging.DEBUG)  # More detailed logging

    if not bridge_logger.handlers:
        # Main log file with detailed format
        handler = logging.FileHandler(GLOBAL_LOG)
        formatter = logging.Formatter(
            "%(asctime)s [%(levelname)s] %(name)s:%(lineno)d - %(message)s"
        )
        handler.setFormatter(formatter)
        bridge_logger.addHandler(handler)

        # Error-only log file for quick debugging
        error_handler = logging.FileHandler("chat_bridge_errors.log")
        error_handler.setLevel(logging.ERROR)
        error_formatter = logging.Formatter(
            "%(asctime)s [ERROR] %(name)s:%(funcName)s:%(lineno)d\n%(message)s\n%(exc_info)s\n" + "-"*80
        )
        error_handler.setFormatter(error_formatter)
        bridge_logger.addHandler(error_handler)

        # Console handler for immediate feedback
        console_handler = logging.StreamHandler()
        console_handler.setLevel(logging.WARNING)
        console_formatter = logging.Formatter("[%(levelname)s] %(message)s")
        console_handler.setFormatter(console_formatter)
        bridge_logger.addHandler(console_handler)

    session_logger = logging.getLogger("session")
    return bridge_logger, session_logger


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
