"""
Terminal UI utilities for Chat Bridge.

Provides ANSI color codes and styled printing functions for beautiful terminal output.
"""

from __future__ import annotations


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
