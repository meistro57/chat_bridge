#!/usr/bin/env python3
"""
🚀 Chat Bridge Launcher

Quick launcher with preset configurations for common use cases.
"""

import sys
import subprocess
from pathlib import Path

def print_colored(text: str, color_code: str = "0"):
    """Print colored text"""
    print(f"\033[{color_code}m{text}\033[0m")

def print_banner():
    """Print launcher banner"""
    banner = """
╔═══════════════════════════════════════════════════════════════════╗
║                        🚀 CHAT BRIDGE LAUNCHER                     ║
║                     Quick Start Configurations                     ║
╚═══════════════════════════════════════════════════════════════════╝
"""
    print_colored(banner, "96;1")  # Cyan bold

def main():
    print_banner()

    print_colored("Select a configuration:", "93;1")  # Yellow bold
    print()

    configs = [
        ("🎯 Interactive Mode", "python chat_bridge.py", "Full interactive setup with menus"),
        ("🤖 OpenAI vs Anthropic", "python chat_bridge.py --provider-a openai --provider-b anthropic", "Classic GPT vs Claude conversation"),
        ("🧠 Gemini vs Claude", "python chat_bridge.py --provider-a gemini --provider-b anthropic", "Google Gemini vs Anthropic Claude"),
        ("🏠 Local Models", "python chat_bridge.py --provider-a ollama --provider-b lmstudio", "Local Ollama vs LM Studio"),
        ("🎭 Persona Mode", "python chat_bridge.py --roles roles.json", "Use predefined personas from roles.json"),
        ("📚 Long Discussion", "python chat_bridge.py --max-rounds 100 --mem-rounds 20", "Extended conversation with more memory"),
        ("⚡ Quick Test", "python chat_bridge.py --max-rounds 5", "Short 5-round test conversation"),
    ]

    for i, (name, cmd, desc) in enumerate(configs, 1):
        print_colored(f"  [{i}] {name}", "96;1")  # Cyan bold
        print_colored(f"      {desc}", "37")      # White dim
        print_colored(f"      → {cmd}", "90")      # Dark gray
        print()

    print_colored("[0] Exit", "91")  # Red
    print()

    try:
        choice = input("\033[93mSelect option: \033[0m").strip()

        if choice == "0" or not choice:
            print_colored("👋 Goodbye!", "93")
            return

        try:
            index = int(choice) - 1
            if 0 <= index < len(configs):
                _, command, _ = configs[index]
                print_colored(f"\n🚀 Launching: {command}", "92")
                print_colored("─" * 60, "90")
                subprocess.run(command.split(), cwd=Path(__file__).parent)
            else:
                print_colored("❌ Invalid selection", "91")
        except ValueError:
            print_colored("❌ Please enter a number", "91")

    except KeyboardInterrupt:
        print_colored("\n👋 Goodbye!", "93")

if __name__ == "__main__":
    main()