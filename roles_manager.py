#!/usr/bin/env python3
"""
🎭 Roles & Personalities Manager
A dedicated script for managing AI roles and personalities in the Chat Bridge system.
Provides comprehensive tools for creating, editing, importing, and managing persona configurations.
"""

import json
import os
import sys
from typing import Dict, List, Optional, Tuple
from datetime import datetime

# ───────────────────────── Colors & Styling ─────────────────────────

class Colors:
    """ANSI color codes for beautiful terminal output"""
    RESET = '\033[0m'
    BOLD = '\033[1m'
    DIM = '\033[2m'

    RED = '\033[91m'
    GREEN = '\033[92m'
    YELLOW = '\033[93m'
    BLUE = '\033[94m'
    MAGENTA = '\033[95m'
    CYAN = '\033[96m'
    WHITE = '\033[97m'

    BG_RED = '\033[101m'
    BG_GREEN = '\033[102m'
    BG_YELLOW = '\033[103m'
    BG_BLUE = '\033[104m'

def colorize(text: str, color: str, bold: bool = False) -> str:
    """Apply color and optional bold formatting to text"""
    prefix = Colors.BOLD + color if bold else color
    return f"{prefix}{text}{Colors.RESET}"

def print_header(title: str):
    """Print a styled header"""
    print("\n" + "="*60)
    print(colorize(f"  🎭 {title.upper()}", Colors.CYAN, bold=True))
    print("="*60)

def print_info(message: str):
    """Print an info message"""
    print(colorize(f"ℹ️  {message}", Colors.BLUE))

def print_success(message: str):
    """Print a success message"""
    print(colorize(f"✅ {message}", Colors.GREEN))

def print_error(message: str):
    """Print an error message"""
    print(colorize(f"❌ {message}", Colors.RED))

def print_warning(message: str):
    """Print a warning message"""
    print(colorize(f"⚠️  {message}", Colors.YELLOW))

# ───────────────────────── Configuration Management ─────────────────────────

class RolesManager:
    """Manages roles and personalities configuration"""

    def __init__(self, config_path: str = "roles.json"):
        self.config_path = config_path
        self.config = self.load_config()

    def load_config(self) -> Dict:
        """Load configuration from file or create default"""
        if os.path.exists(self.config_path):
            try:
                with open(self.config_path, 'r', encoding='utf-8') as f:
                    return json.load(f)
            except (json.JSONDecodeError, IOError) as e:
                print_error(f"Failed to load {self.config_path}: {e}")
                return self.get_default_config()
        else:
            print_info(f"Creating new configuration file: {self.config_path}")
            config = self.get_default_config()
            self.save_config(config)
            return config

    def save_config(self, config: Optional[Dict] = None) -> bool:
        """Save configuration to file"""
        if config is None:
            config = self.config

        try:
            # Create backup if file exists
            if os.path.exists(self.config_path):
                backup_path = f"{self.config_path}.backup.{datetime.now().strftime('%Y%m%d_%H%M%S')}"
                os.rename(self.config_path, backup_path)
                print_info(f"Backup created: {backup_path}")

            with open(self.config_path, 'w', encoding='utf-8') as f:
                json.dump(config, f, indent=2, ensure_ascii=False)

            self.config = config
            return True
        except IOError as e:
            print_error(f"Failed to save {self.config_path}: {e}")
            return False

    def get_default_config(self) -> Dict:
        """Return default configuration"""
        return {
            "agent_a": {
                "provider": "openai",
                "model": None,
                "system": "You are a helpful AI assistant focused on providing accurate and thoughtful responses.",
                "guidelines": [
                    "Be clear and concise in your explanations",
                    "Ask clarifying questions when needed",
                    "Provide examples to illustrate complex concepts"
                ]
            },
            "agent_b": {
                "provider": "anthropic",
                "model": None,
                "system": "You are a knowledgeable AI assistant that engages in thoughtful dialogue.",
                "guidelines": [
                    "Consider multiple perspectives on complex issues",
                    "Build upon previous conversation points",
                    "Acknowledge uncertainty when appropriate"
                ]
            },
            "persona_library": {
                "scientist": {
                    "provider": "anthropic",
                    "model": None,
                    "system": "You are a rigorous scientist focused on evidence-based reasoning and methodology.",
                    "guidelines": [
                        "Cite sources and explain methodology when possible",
                        "Distinguish between hypotheses and established facts",
                        "Suggest experiments or tests to validate claims",
                        "Question assumptions and look for confounding variables"
                    ]
                },
                "philosopher": {
                    "provider": "openai",
                    "model": None,
                    "system": "You are a thoughtful philosopher who explores deep questions about meaning, ethics, and existence.",
                    "guidelines": [
                        "Question fundamental assumptions",
                        "Explore multiple philosophical perspectives",
                        "Use thought experiments to illustrate concepts",
                        "Connect abstract ideas to concrete examples"
                    ]
                },
                "comedian": {
                    "provider": "openai",
                    "model": "gpt-4o",
                    "system": "You are a witty comedian who finds humor in everyday situations while remaining insightful.",
                    "guidelines": [
                        "Use humor to make points more memorable",
                        "Find the absurd in the mundane",
                        "Be clever but not offensive",
                        "Balance humor with genuine insight"
                    ]
                },
                "steel_worker": {
                    "provider": "anthropic",
                    "model": None,
                    "system": "You are a practical steel worker with decades of hands-on experience and no-nonsense wisdom.",
                    "guidelines": [
                        "Focus on practical solutions that actually work",
                        "Value experience over theory",
                        "Speak plainly and directly",
                        "Consider safety and real-world constraints"
                    ]
                }
            },
            "stop_words": ["wrap up", "end chat", "terminate", "goodbye", "farewell"],
            "stop_word_detection_enabled": True,
            "temp_a": 0.7,
            "temp_b": 0.7
        }

# ───────────────────────── Persona Management ─────────────────────────

def get_user_input(prompt: str, default: str = "") -> str:
    """Get user input with optional default"""
    if default:
        user_input = input(f"{prompt} [{default}]: ").strip()
        return user_input if user_input else default
    return input(f"{prompt}: ").strip()

def get_multiline_input(prompt: str) -> str:
    """Get multiline input from user"""
    print(f"{prompt}")
    print(colorize("(Enter text, then press Ctrl+D on Unix/Linux or Ctrl+Z on Windows to finish)", Colors.DIM))
    lines = []
    try:
        while True:
            line = input()
            lines.append(line)
    except EOFError:
        pass
    return "\n".join(lines).strip()

def select_provider() -> str:
    """Interactive provider selection"""
    providers = ["openai", "anthropic", "gemini", "ollama", "lmstudio"]

    print("\nAvailable providers:")
    for i, provider in enumerate(providers, 1):
        print(f"  {i}. {provider}")

    while True:
        try:
            choice = int(get_user_input("Select provider", "1")) - 1
            if 0 <= choice < len(providers):
                return providers[choice]
            else:
                print_error("Invalid choice. Please try again.")
        except ValueError:
            print_error("Please enter a number.")

def create_persona_interactive() -> Optional[Dict]:
    """Interactive persona creation"""
    print_header("Create New Persona")

    # Get persona name
    while True:
        name = get_user_input("Persona name (unique identifier)")
        if name and name.replace('_', '').replace('-', '').isalnum():
            break
        print_error("Name must be alphanumeric (underscores and hyphens allowed)")

    # Get provider
    provider = select_provider()
    print_success(f"Selected provider: {provider}")

    # Get system prompt
    print("\n" + colorize("System Prompt:", Colors.CYAN, bold=True))
    print_info("This defines the AI's core personality and role.")
    system = get_multiline_input("Enter system prompt")

    if not system:
        print_error("System prompt cannot be empty")
        return None

    # Get guidelines
    print("\n" + colorize("Guidelines:", Colors.CYAN, bold=True))
    print_info("Add behavioral guidelines (press Enter on empty line to finish)")
    guidelines = []
    while True:
        guideline = get_user_input(f"Guideline {len(guidelines) + 1} (or press Enter to finish)")
        if not guideline:
            break
        guidelines.append(guideline)

    # Get optional model
    model = get_user_input("Specific model (optional, leave empty for provider default)")

    # Get optional notes
    notes = get_user_input("Notes/description (optional)")

    # Build persona data
    persona_data = {
        "provider": provider,
        "system": system,
        "guidelines": guidelines
    }

    if model:
        persona_data["model"] = model

    if notes:
        persona_data["notes"] = notes

    # Show summary
    print("\n" + colorize("Persona Summary:", Colors.GREEN, bold=True))
    print(f"Name: {name}")
    print(f"Provider: {provider}")
    if model:
        print(f"Model: {model}")
    print(f"System: {system[:100]}{'...' if len(system) > 100 else ''}")
    print(f"Guidelines: {len(guidelines)} items")
    if notes:
        print(f"Notes: {notes}")

    if get_user_input("\nSave this persona? (y/n)", "y").lower().startswith('y'):
        return {name: persona_data}

    return None

def edit_persona_interactive(persona_key: str, persona_data: Dict) -> Optional[Dict]:
    """Interactive persona editing"""
    print_header(f"Edit Persona: {persona_key}")

    # Display current values
    print(colorize("Current configuration:", Colors.YELLOW))
    print(f"Provider: {persona_data.get('provider', 'Not set')}")
    print(f"Model: {persona_data.get('model', 'Default')}")
    print(f"System: {persona_data.get('system', 'Not set')[:100]}{'...' if len(persona_data.get('system', '')) > 100 else ''}")
    print(f"Guidelines: {len(persona_data.get('guidelines', []))} items")

    # Edit options
    options = [
        "Edit provider",
        "Edit model",
        "Edit system prompt",
        "Edit guidelines",
        "Edit notes",
        "Save changes",
        "Cancel"
    ]

    modified_data = persona_data.copy()

    while True:
        print("\n" + colorize("Edit options:", Colors.CYAN))
        for i, option in enumerate(options, 1):
            print(f"  {i}. {option}")

        try:
            choice = int(get_user_input("Select option", "6")) - 1

            if choice == 0:  # Edit provider
                modified_data["provider"] = select_provider()
                print_success(f"Provider updated to: {modified_data['provider']}")

            elif choice == 1:  # Edit model
                current_model = modified_data.get("model", "")
                new_model = get_user_input("Model name (leave empty for default)", current_model)
                modified_data["model"] = new_model if new_model else None
                print_success("Model updated")

            elif choice == 2:  # Edit system prompt
                print(f"Current system prompt:\n{modified_data.get('system', '')}")
                new_system = get_multiline_input("Enter new system prompt")
                if new_system:
                    modified_data["system"] = new_system
                    print_success("System prompt updated")

            elif choice == 3:  # Edit guidelines
                guidelines = modified_data.get("guidelines", [])
                print(f"Current guidelines ({len(guidelines)} items):")
                for i, guideline in enumerate(guidelines, 1):
                    print(f"  {i}. {guideline}")

                print("\nOptions: (a)dd, (d)elete, (c)lear, (k)eep current")
                action = get_user_input("Action", "k").lower()

                if action.startswith('a'):
                    new_guideline = get_user_input("New guideline")
                    if new_guideline:
                        guidelines.append(new_guideline)
                        print_success("Guideline added")
                elif action.startswith('d'):
                    try:
                        idx = int(get_user_input("Delete guideline number")) - 1
                        if 0 <= idx < len(guidelines):
                            removed = guidelines.pop(idx)
                            print_success(f"Removed: {removed}")
                        else:
                            print_error("Invalid guideline number")
                    except ValueError:
                        print_error("Please enter a valid number")
                elif action.startswith('c'):
                    guidelines.clear()
                    print_success("All guidelines cleared")

                modified_data["guidelines"] = guidelines

            elif choice == 4:  # Edit notes
                current_notes = modified_data.get("notes", "")
                new_notes = get_user_input("Notes", current_notes)
                modified_data["notes"] = new_notes if new_notes else None
                print_success("Notes updated")

            elif choice == 5:  # Save changes
                return {persona_key: modified_data}

            elif choice == 6:  # Cancel
                return None

            else:
                print_error("Invalid choice")

        except ValueError:
            print_error("Please enter a valid number")

def list_personas(manager: RolesManager) -> None:
    """List all available personas"""
    print_header("Available Personas")

    personas = manager.config.get("persona_library", {})
    if not personas:
        print_warning("No personas found")
        return

    for name, data in personas.items():
        print(f"\n{colorize(name, Colors.CYAN, bold=True)}")
        print(f"  Provider: {data.get('provider', 'Not set')}")
        print(f"  Model: {data.get('model', 'Default')}")
        system = data.get('system', '')
        print(f"  System: {system[:80]}{'...' if len(system) > 80 else ''}")
        print(f"  Guidelines: {len(data.get('guidelines', []))} items")
        if 'notes' in data:
            print(f"  Notes: {data['notes']}")

def delete_persona(manager: RolesManager) -> None:
    """Delete a persona"""
    print_header("Delete Persona")

    personas = manager.config.get("persona_library", {})
    if not personas:
        print_warning("No personas to delete")
        return

    print("Available personas:")
    persona_names = list(personas.keys())
    for i, name in enumerate(persona_names, 1):
        print(f"  {i}. {name}")

    try:
        choice = int(get_user_input("Select persona to delete")) - 1
        if 0 <= choice < len(persona_names):
            persona_name = persona_names[choice]

            print(f"\nPersona to delete: {colorize(persona_name, Colors.RED)}")
            if get_user_input("Are you sure? (y/n)", "n").lower().startswith('y'):
                del manager.config["persona_library"][persona_name]
                if manager.save_config():
                    print_success(f"Persona '{persona_name}' deleted successfully")
                else:
                    print_error("Failed to save changes")
            else:
                print_info("Deletion cancelled")
        else:
            print_error("Invalid choice")
    except ValueError:
        print_error("Please enter a valid number")

def edit_agent_defaults(manager: RolesManager) -> None:
    """Edit default agent configurations"""
    print_header("Edit Default Agent Settings")

    agents = ["agent_a", "agent_b"]
    print("Select agent to edit:")
    for i, agent in enumerate(agents, 1):
        current = manager.config.get(agent, {})
        print(f"  {i}. {agent.upper()} - Provider: {current.get('provider', 'Not set')}")

    try:
        choice = int(get_user_input("Select agent", "1")) - 1
        if 0 <= choice < len(agents):
            agent_key = agents[choice]
            current_config = manager.config.get(agent_key, {})

            edited = edit_persona_interactive(agent_key.upper(), current_config)
            if edited:
                agent_data = list(edited.values())[0]
                manager.config[agent_key] = agent_data
                if manager.save_config():
                    print_success(f"Agent {agent_key.upper()} updated successfully")
                else:
                    print_error("Failed to save changes")
        else:
            print_error("Invalid choice")
    except ValueError:
        print_error("Please enter a valid number")

def manage_stop_words(manager: RolesManager) -> None:
    """Manage stop words configuration"""
    print_header("Stop Words Management")

    stop_words = manager.config.get("stop_words", [])
    enabled = manager.config.get("stop_word_detection_enabled", True)

    print(f"Stop word detection: {colorize('ENABLED' if enabled else 'DISABLED', Colors.GREEN if enabled else Colors.RED)}")
    print(f"Current stop words ({len(stop_words)} items):")
    for i, word in enumerate(stop_words, 1):
        print(f"  {i}. {word}")

    options = [
        "Toggle detection on/off",
        "Add stop word",
        "Remove stop word",
        "Clear all stop words",
        "Reset to defaults",
        "Back to main menu"
    ]

    while True:
        print("\n" + colorize("Options:", Colors.CYAN))
        for i, option in enumerate(options, 1):
            print(f"  {i}. {option}")

        try:
            choice = int(get_user_input("Select option", "6")) - 1

            if choice == 0:  # Toggle detection
                manager.config["stop_word_detection_enabled"] = not enabled
                enabled = not enabled
                status = "ENABLED" if enabled else "DISABLED"
                print_success(f"Stop word detection {status}")

            elif choice == 1:  # Add stop word
                new_word = get_user_input("New stop word").strip()
                if new_word and new_word not in stop_words:
                    stop_words.append(new_word)
                    manager.config["stop_words"] = stop_words
                    print_success(f"Added stop word: {new_word}")
                elif new_word in stop_words:
                    print_warning("Stop word already exists")

            elif choice == 2:  # Remove stop word
                if stop_words:
                    try:
                        idx = int(get_user_input("Remove stop word number")) - 1
                        if 0 <= idx < len(stop_words):
                            removed = stop_words.pop(idx)
                            manager.config["stop_words"] = stop_words
                            print_success(f"Removed stop word: {removed}")
                        else:
                            print_error("Invalid number")
                    except ValueError:
                        print_error("Please enter a valid number")
                else:
                    print_warning("No stop words to remove")

            elif choice == 3:  # Clear all
                if get_user_input("Clear all stop words? (y/n)", "n").lower().startswith('y'):
                    manager.config["stop_words"] = []
                    stop_words = []
                    print_success("All stop words cleared")

            elif choice == 4:  # Reset to defaults
                if get_user_input("Reset to default stop words? (y/n)", "n").lower().startswith('y'):
                    default_words = ["wrap up", "end chat", "terminate", "goodbye", "farewell"]
                    manager.config["stop_words"] = default_words
                    stop_words = default_words
                    print_success("Reset to default stop words")

            elif choice == 5:  # Back
                if manager.save_config():
                    print_success("Stop words configuration saved")
                return

            else:
                print_error("Invalid choice")

        except ValueError:
            print_error("Please enter a valid number")

def export_config(manager: RolesManager) -> None:
    """Export configuration to file"""
    print_header("Export Configuration")

    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    default_name = f"roles_export_{timestamp}.json"

    export_path = get_user_input("Export to file", default_name)

    try:
        with open(export_path, 'w', encoding='utf-8') as f:
            json.dump(manager.config, f, indent=2, ensure_ascii=False)
        print_success(f"Configuration exported to: {export_path}")
    except IOError as e:
        print_error(f"Export failed: {e}")

def import_config(manager: RolesManager) -> None:
    """Import configuration from file"""
    print_header("Import Configuration")

    import_path = get_user_input("Import from file")

    if not os.path.exists(import_path):
        print_error(f"File not found: {import_path}")
        return

    try:
        with open(import_path, 'r', encoding='utf-8') as f:
            new_config = json.load(f)

        # Validate basic structure
        if not isinstance(new_config, dict):
            print_error("Invalid configuration format")
            return

        print_warning("This will replace your current configuration!")
        if get_user_input("Continue? (y/n)", "n").lower().startswith('y'):
            if manager.save_config(new_config):
                print_success(f"Configuration imported from: {import_path}")
            else:
                print_error("Failed to save imported configuration")
        else:
            print_info("Import cancelled")

    except (json.JSONDecodeError, IOError) as e:
        print_error(f"Import failed: {e}")

# ───────────────────────── Main Menu ─────────────────────────

def show_main_menu() -> None:
    """Display the main menu"""
    print("\n" + colorize("🎭 ROLES & PERSONALITIES MANAGER", Colors.CYAN, bold=True))
    print("─" * 50)

    options = [
        "📋 List all personas",
        "✨ Create new persona",
        "✏️  Edit existing persona",
        "🗑️  Delete persona",
        "🤖 Edit default agents (A & B)",
        "🛑 Manage stop words",
        "🌡️  Edit temperature settings",
        "📤 Export configuration",
        "📥 Import configuration",
        "🔄 Reset to defaults",
        "❌ Exit"
    ]

    for i, option in enumerate(options, 1):
        print(f"  {i:2d}. {option}")
    print("─" * 50)

def edit_temperature_settings(manager: RolesManager) -> None:
    """Edit temperature settings"""
    print_header("Temperature Settings")

    temp_a = manager.config.get("temp_a", 0.7)
    temp_b = manager.config.get("temp_b", 0.7)

    print(f"Current temperatures:")
    print(f"  Agent A: {temp_a}")
    print(f"  Agent B: {temp_b}")

    try:
        new_temp_a = float(get_user_input("Temperature for Agent A (0.0-2.0)", str(temp_a)))
        new_temp_b = float(get_user_input("Temperature for Agent B (0.0-2.0)", str(temp_b)))

        if 0.0 <= new_temp_a <= 2.0 and 0.0 <= new_temp_b <= 2.0:
            manager.config["temp_a"] = new_temp_a
            manager.config["temp_b"] = new_temp_b
            if manager.save_config():
                print_success("Temperature settings updated")
            else:
                print_error("Failed to save settings")
        else:
            print_error("Temperature must be between 0.0 and 2.0")
    except ValueError:
        print_error("Please enter valid numbers")

def reset_to_defaults(manager: RolesManager) -> None:
    """Reset configuration to defaults"""
    print_header("Reset to Defaults")

    print_warning("This will replace ALL current configuration with defaults!")
    print("Your current personas, settings, and customizations will be lost.")

    if get_user_input("Are you absolutely sure? (type 'RESET' to confirm)") == "RESET":
        default_config = manager.get_default_config()
        if manager.save_config(default_config):
            print_success("Configuration reset to defaults")
        else:
            print_error("Failed to reset configuration")
    else:
        print_info("Reset cancelled")

def main():
    """Main application loop"""
    print_header("Roles & Personalities Manager")
    print_info("Manage AI roles and personalities for the Chat Bridge system")

    # Initialize manager
    config_path = "roles.json"
    if len(sys.argv) > 1:
        config_path = sys.argv[1]

    manager = RolesManager(config_path)
    print_success(f"Loaded configuration from: {config_path}")

    while True:
        show_main_menu()

        try:
            choice = int(get_user_input("Select option", "11")) - 1

            if choice == 0:  # List personas
                list_personas(manager)

            elif choice == 1:  # Create persona
                new_persona = create_persona_interactive()
                if new_persona:
                    name, data = list(new_persona.items())[0]
                    if name in manager.config.get("persona_library", {}):
                        if get_user_input(f"Persona '{name}' already exists. Overwrite? (y/n)", "n").lower().startswith('y'):
                            manager.config.setdefault("persona_library", {})[name] = data
                            if manager.save_config():
                                print_success(f"Persona '{name}' created successfully")
                        else:
                            print_info("Creation cancelled")
                    else:
                        manager.config.setdefault("persona_library", {})[name] = data
                        if manager.save_config():
                            print_success(f"Persona '{name}' created successfully")

            elif choice == 2:  # Edit persona
                personas = manager.config.get("persona_library", {})
                if not personas:
                    print_warning("No personas to edit")
                    continue

                print("Available personas:")
                persona_names = list(personas.keys())
                for i, name in enumerate(persona_names, 1):
                    print(f"  {i}. {name}")

                try:
                    persona_choice = int(get_user_input("Select persona to edit")) - 1
                    if 0 <= persona_choice < len(persona_names):
                        persona_name = persona_names[persona_choice]
                        persona_data = personas[persona_name]

                        edited = edit_persona_interactive(persona_name, persona_data)
                        if edited:
                            name, data = list(edited.items())[0]
                            manager.config["persona_library"][persona_name] = data
                            if manager.save_config():
                                print_success(f"Persona '{persona_name}' updated successfully")
                            else:
                                print_error("Failed to save changes")
                    else:
                        print_error("Invalid choice")
                except ValueError:
                    print_error("Please enter a valid number")

            elif choice == 3:  # Delete persona
                delete_persona(manager)

            elif choice == 4:  # Edit default agents
                edit_agent_defaults(manager)

            elif choice == 5:  # Manage stop words
                manage_stop_words(manager)

            elif choice == 6:  # Edit temperatures
                edit_temperature_settings(manager)

            elif choice == 7:  # Export config
                export_config(manager)

            elif choice == 8:  # Import config
                import_config(manager)

            elif choice == 9:  # Reset to defaults
                reset_to_defaults(manager)

            elif choice == 10:  # Exit
                print_success("Goodbye! 👋")
                break

            else:
                print_error("Invalid choice. Please try again.")

        except ValueError:
            print_error("Please enter a valid number.")
        except KeyboardInterrupt:
            print("\n" + colorize("Interrupted by user. Goodbye! 👋", Colors.YELLOW))
            break
        except Exception as e:
            print_error(f"An unexpected error occurred: {e}")

if __name__ == "__main__":
    main()