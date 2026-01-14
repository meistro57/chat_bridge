#!/usr/bin/env python3
"""
🌉 Laravel Chat Bridge Manager
Interactive colorful manager for the Laravel port.
"""

import os
import sys
import subprocess
import shutil
from pathlib import Path

# Colors
CYAN = "\033[96m"
GREEN = "\033[92m"
YELLOW = "\033[93m"
RED = "\033[91m"
BOLD = "\033[1m"
DIM = "\033[90m"
RESET = "\033[0m"

APP_DIR = Path("/home/mark/chat_bridge/chat_bridge_laravel")
ROOT_DIR = Path("/home/mark/chat_bridge")

def print_banner():
    """Print the manager banner"""
    banner = f"""
{CYAN}{BOLD}╔═══════════════════════════════════════════════════════════════════╗
║                      🌉 LARAVEL CHAT BRIDGE                        ║
║                       Management Console                           ║
╚═══════════════════════════════════════════════════════════════════╝{RESET}"""
    print(banner)

def run_command(cmd, cwd=APP_DIR, shell=True):
    """Run a shell command and print status"""
    print(f"{DIM}→ Executing: {cmd}{RESET}")
    try:
        subprocess.run(cmd, shell=shell, cwd=cwd, check=True)
        return True
    except subprocess.CalledProcessError as e:
        print(f"{RED}❌ Command failed: {e}{RESET}")
        return False

def setup_laravel():
    """Run full setup"""
    print(f"\n{YELLOW}{BOLD}🚀 Starting Laravel Setup...{RESET}")
    
    # 1. Composer Install
    if not run_command("composer install"): return
    
    # 2. Env setup
    env_file = APP_DIR / ".env"
    if not env_file.exists():
        print(f"{YELLOW}Creating .env from example...{RESET}")
        shutil.copy(APP_DIR / ".env.example", env_file)
        run_command("php artisan key:generate")
    
    # 3. Database setup
    db_file = APP_DIR / "database" / "database.sqlite"
    if not db_file.exists():
        print(f"{YELLOW}Creating SQLite database...{RESET}")
        db_file.touch()
    
    # 4. Migrate
    run_command("php artisan migrate --force")
    
    # 5. NPM setup
    if shutil.which("npm"):
        run_command("npm install")
        run_command("npm run build")
    else:
        print(f"{RED}NPM not found. Skipping frontend build.{RESET}")
        
    # 6. Seed
    run_command("php artisan db:seed --class=PersonaSeeder")
    
    print(f"\n{GREEN}{BOLD}✅ Setup Complete!{RESET}")
    input("\nPress Enter to return to menu...")

def sync_env_keys():
    """Sync keys from root .env to Laravel .env"""
    root_env = ROOT_DIR / ".env"
    lar_env = APP_DIR / ".env"
    
    if not root_env.exists():
        print(f"{RED}Root .env not found. Please create it first.{RESET}")
        return
    
    if not lar_env.exists():
        shutil.copy(APP_DIR / ".env.example", lar_env)
        run_command("php artisan key:generate")
        
    print(f"{YELLOW}Syncing API keys from root .env...{RESET}")
    
    keys_to_sync = [
        "OPENAI_API_KEY", "ANTHROPIC_API_KEY", "GEMINI_API_KEY", "DEEPSEEK_API_KEY", "OPENROUTER_API_KEY",
        "OPENAI_MODEL", "ANTHROPIC_MODEL", "GEMINI_MODEL", "DEEPSEEK_MODEL", "OLLAMA_MODEL"
    ]
    
    root_keys = {}
    with open(root_env, 'r') as f:
        for line in f:
            if '=' in line and not line.startswith('#'):
                k, _, v = line.partition('=')
                root_keys[k.strip()] = v.strip()
                
    with open(lar_env, 'r') as f:
        lar_lines = f.readlines()
        
    new_lines = []
    processed_keys = set()
    
    for line in lar_lines:
        if '=' in line and not line.startswith('#'):
            k, _, v = line.strip().partition('=')
            k = k.strip()
            if k in root_keys:
                new_lines.append(f"{k}={root_keys[k]}\n")
                processed_keys.add(k)
                continue
        new_lines.append(line)
        
    # Add missing keys if any
    for k in keys_to_sync:
        if k in root_keys and k not in processed_keys:
            new_lines.append(f"{k}={root_keys[k]}\n")
            
    with open(lar_env, 'w') as f:
        f.writelines(new_lines)
        
    print(f"{GREEN}✅ API keys synced successfully!{RESET}")
    input("\nPress Enter to return to menu...")

def main():
    while True:
        os.system('clear')
        print_banner()
        
        print(f"{YELLOW}{BOLD}Select an action:{RESET}\n")
        print(f"  {CYAN}[1] {BOLD}🎯 Start CLI Bridge{RESET}         {DIM}(Artisan command - interactive){RESET}")
        print(f"  {CYAN}[2] {BOLD}🌐 Run Web Environment{RESET}      {DIM}(Web server, Queue, Reverb, Pail){RESET}")
        print(f"  {CYAN}[3] {BOLD}⚙️  Run Initial Setup{RESET}       {DIM}(Composer, NPM, Migrations){RESET}")
        print(f"  {CYAN}[4] {BOLD}🔑 Sync API Keys{RESET}            {DIM}(Import from root .env){RESET}")
        print(f"  {CYAN}[5] {BOLD}💾 Reset Database{RESET}           {DIM}(Wipe and re-migrate){RESET}")
        print(f"  {CYAN}[6] {BOLD}🛑 Stop Python Backend{RESET}      {DIM}(Free up port 8000){RESET}")
        print(f"\n  {RED}[0] Exit{RESET}")
        print()
        
        choice = input(f"{YELLOW}Selection: {RESET}").strip()
        
        if choice == "1":
            run_command("php artisan bridge:chat")
        elif choice == "2":
            print(f"\n{GREEN}Launching web environment... (Press Ctrl+C to stop){RESET}")
            run_command("composer dev")
        elif choice == "3":
            setup_laravel()
        elif choice == "4":
            sync_env_keys()
        elif choice == "5":
            if input(f"{RED}Are you sure you want to wipe the database? (y/n): {RESET}").lower() == 'y':
                run_command("php artisan migrate:fresh --seed")
                run_command("php artisan db:seed --class=PersonaSeeder")
                input("\nDatabase reset complete. Press Enter...")
        elif choice == "6":
            print(f"\n{YELLOW}Stopping Python backend processes...{RESET}")
            # Try to kill main.py (FastAPI) and chat_bridge.py
            run_command("pkill -f 'python.*main.py' || echo 'No main.py found.'")
            run_command("pkill -f 'python.*chat_bridge.py' || echo 'No chat_bridge.py found.'")
            input("\nDone. Press Enter to return to menu...")
        elif choice == "0":
            print(f"👋 {CYAN}Goodbye!{RESET}")
            break
        else:
            print(f"{RED}Invalid selection.{RESET}")
            input("\nPress Enter to continue...")

if __name__ == "__main__":
    main()
