#!/bin/bash

# --- Colors ---
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
MAGENTA='\033[0;35m'
NC='\033[0m' # No Color

# --- UI Helpers ---
print_header() {
    clear
    echo -e "${CYAN}==================================================${NC}"
    echo -e "${CYAN}       CHAT BRIDGE - LARAVEL COMMAND CENTER       ${NC}"
    echo -e "${CYAN}==================================================${NC}"
}

print_status() {
    echo -e "${YELLOW}>>> $1${NC}"
}

print_success() {
    echo -e "${GREEN}✔ $1${NC}"
}

print_error() {
    echo -e "${RED}✘ $1${NC}"
}

# --- Actions ---
startup_app() {
    print_status "Starting environment setup..."
    
    if [ ! -f .env ]; then
        print_status "Creating .env from .env.example..."
        cp .env.example .env
        php artisan key:generate
    fi

    print_status "Running comprehensive startup (Composer, NPM, Migrations)..."
    composer run dev
}

run_tests() {
    print_status "Running all PHPUnit tests..."
    php artisan test --compact
    read -p "Press enter to return to menu..."
}

run_ai_test() {
    echo -e "${MAGENTA}Select AI Provider to test:${NC}"
    echo "1) OpenAI"
    echo "2) Anthropic"
    echo "3) Gemini"
    echo "4) DeepSeek"
    echo "5) Ollama"
    echo "6) Back to main menu"
    read -p "Selection: " ai_choice

    case $ai_choice in
        1) php artisan ai:test openai ;;
        2) php artisan ai:test anthropic ;;
        3) php artisan ai:test gemini ;;
        4) php artisan ai:test deepseek ;;
        5) php artisan ai:test ollama ;;
        6) return ;;
        *) print_error "Invalid selection" ;;
    esac
    read -p "Press enter to return to menu..."
}

run_pint() {
    print_status "Running Laravel Pint (Code Formatter)..."
    vendor/bin/pint
    print_success "Code formatted."
    read -p "Press enter to return to menu..."
}

run_refresh() {
    print_status "Refreshing Database..."
    php artisan migrate:fresh --seed
    print_success "Database wiped, migrated and seeded."
    read -p "Press enter to return to menu..."
}

# --- Main Menu ---
while true; do
    print_header
    echo -e "${BLUE}Main Menu:${NC}"
    echo -e "1) ${GREEN}Launch App${NC} (Runs everything via concurrently)"
    echo -e "2) ${YELLOW}Run PHPUnit Tests${NC}"
    echo -e "3) ${MAGENTA}Test AI Connectivity${NC}"
    echo -e "4) ${CYAN}Format Code (Pint)${NC}"
    echo -e "5) ${RED}Refresh DB & Seed${NC}"
    echo -e "q) Exit"
    echo
    read -p "Choose an option: " choice

    case $choice in
        1) startup_app ; break ;;
        2) run_tests ;;
        3) run_ai_test ;;
        4) run_pint ;;
        5) run_refresh ;;
        q|Q) echo -e "${CYAN}Goodbye!${NC}"; exit 0 ;;
        *) print_error "Invalid option" ; sleep 1 ;;
    esac
done
