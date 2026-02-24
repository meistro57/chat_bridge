#!/usr/bin/env bash

# ============================================================================
# Chat Bridge - Test Runner Script
# A colorful CLI menu for running tests and fixing issues
# ============================================================================

# Safety
set -u

# Color definitions
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
MAGENTA='\033[0;35m'
CYAN='\033[0;36m'
WHITE='\033[1;37m'
BOLD='\033[1m'
DIM='\033[2m'
NC='\033[0m' # No Color

# Emoji/Unicode symbols
CHECK="✓"
CROSS="✗"
ARROW="→"
GEAR="⚙"
ROCKET="🚀"
BUG="🐛"
WRENCH="🔧"
MAGNIFY="🔍"
FIRE="🔥"
SPARKLES="✨"

# Configuration
CACHE_DIR=".test_cache"
FAILED_TESTS_FILE="$CACHE_DIR/failed_tests.txt"
COVERAGE_DIR="coverage"
SERVICE_NAME=""
USE_DOCKER=false

# ============================================================================
# Docker Helpers
# ============================================================================

detect_docker_service() {
    if ! command -v docker &> /dev/null; then
        return 1
    fi

    local services
    services=$(docker compose ps --services --status=running 2>/dev/null)
    if [[ -z "$services" ]]; then
        return 1
    fi

    if echo "$services" | grep -q "^app$"; then
        SERVICE_NAME="app"
        return 0
    fi

    SERVICE_NAME=$(echo "$services" | grep -m1 -E "laravel|app|web")
    if [[ -n "$SERVICE_NAME" ]]; then
        return 0
    fi

    return 1
}

use_docker() {
    if [[ "$USE_DOCKER" == true ]]; then
        return 0
    fi
    return 1
}

run_in_app() {
    local cmd="$1"
    if use_docker; then
        docker compose exec -T "$SERVICE_NAME" sh -lc "$cmd"
    else
        sh -lc "$cmd"
    fi
}

# ============================================================================
# Utility Functions
# ============================================================================

print_header() {
    clear
    echo -e "${CYAN}╔════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║${WHITE}${BOLD}         Chat Bridge - Test Runner & Fixer Menu         ${CYAN}       ║${NC}"
    echo -e "${CYAN}╚════════════════════════════════════════════════════════════════╝${NC}"
    echo ""
}

print_section() {
    echo -e "\n${BLUE}${BOLD}═══ $1 ═══${NC}\n"
}

print_success() {
    echo -e "${GREEN}${CHECK} $1${NC}"
}

print_error() {
    echo -e "${RED}${CROSS} $1${NC}"
}

print_info() {
    echo -e "${CYAN}${ARROW} $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_menu_option() {
    local num=$1
    local icon=$2
    local text=$3
    local color=$4
    echo -e "  ${color}${BOLD}[$num]${NC} $icon  ${WHITE}$text${NC}"
}

press_any_key() {
    echo -e "\n${DIM}Press Enter to continue...${NC}"
    read -r
}

check_artisan_test() {
    if ! run_in_app 'php artisan list 2>/dev/null | grep -q "test "'; then
        print_error "Command 'artisan test' is missing!"
        print_info "This typically means dev dependencies are missing."
        print_info "Run 'composer install' to fix this."
        return 1
    fi
    return 0
}

# ============================================================================
# Test Functions
# ============================================================================

run_all_tests() {
    print_section "Running All Tests (App + Modules)"
    
    if ! check_artisan_test; then
        press_any_key
        return
    fi
    
    print_info "Executing full test suite..."

    local all_passed=true

    # Run core application tests
    echo -e "${CYAN}${BOLD}Running Core Application Tests...${NC}\n"
    if run_in_app "php artisan test --colors=always"; then
        print_success "Core application tests passed!"
    else
        print_error "Some core application tests failed!"
        all_passed=false
        save_failed_tests
    fi

    # Run module tests if modules exist
    if has_modules; then
        if [ -d "Modules" ]; then
            # Safe listing of modules
            local modules=()
            while IFS= read -r module; do
                 modules+=("$module")
            done < <(find Modules -maxdepth 1 -mindepth 1 -type d -exec basename {} \; | sort)

            if [[ ${#modules[@]} -gt 0 ]]; then
                echo -e "\n${CYAN}${BOLD}Running Module Tests...${NC}\n"

                for module in "${modules[@]}"; do
                    if [[ -d "Modules/$module/Tests" ]]; then
                        echo -e "${BLUE}Testing module: $module${NC}"

                        if run_in_app "php artisan test \"Modules/$module/Tests\" --colors=always 2>/dev/null"; then
                            print_success "Module $module tests passed!"
                        else
                            print_error "Module $module tests failed!"
                            all_passed=false
                        fi
                    fi
                done
            fi
        fi
    fi

    echo ""
    if [[ "$all_passed" == true ]]; then
        print_success "All tests passed!"
        rm -f "$FAILED_TESTS_FILE"
    else
        print_error "Some tests failed!"
        save_failed_tests
    fi

    press_any_key
}

run_feature_tests() {
    print_section "Running Feature Tests"
    if ! check_artisan_test; then press_any_key; return; fi

    print_info "Executing feature test suite..."

    if run_in_app "php artisan test --testsuite=Feature --colors=always"; then
        print_success "Feature tests passed!"
    else
        print_error "Some feature tests failed!"
        save_failed_tests
    fi

    press_any_key
}

run_unit_tests() {
    print_section "Running Unit Tests"
    if ! check_artisan_test; then press_any_key; return; fi

    print_info "Executing unit test suite..."

    if run_in_app "php artisan test --testsuite=Unit --colors=always"; then
        print_success "Unit tests passed!"
    else
        print_error "Some unit tests failed!"
        save_failed_tests
    fi

    press_any_key
}

run_specific_test() {
    print_section "Run Specific Test File"
    if ! check_artisan_test; then press_any_key; return; fi

    echo -e "${YELLOW}Available test files:${NC}\n"

    # Robust file listing
    local files=()
    while IFS= read -r file; do
        if [ -f "$file" ]; then
            files+=("$file")
        fi
    done < <(find tests -name "*Test.php" -type f | sort)

    local i=1
    for file in "${files[@]}"; do
        echo -e "  ${CYAN}[$i]${NC} $file"
        ((i++))
    done

    echo -e "\n${WHITE}Enter file number (or 0 to cancel): ${NC}"
    read -r choice

    if [[ "$choice" == "0" ]]; then
        return
    fi

    if [[ "$choice" -gt 0 && "$choice" -le "${#files[@]}" ]]; then
        local selected_file="${files[$((choice-1))]}"
        print_info "Running: $selected_file"

        if run_in_app "php artisan test \"$selected_file\" --colors=always"; then
            print_success "Test passed!"
        else
            print_error "Test failed!"
        fi
    else
        print_error "Invalid selection!"
    fi

    press_any_key
}

run_with_coverage() {
    print_section "Running Tests with Coverage"
    if ! check_artisan_test; then press_any_key; return; fi

    if ! run_in_app "command -v php > /dev/null"; then
        print_error "PHP not found!"
        press_any_key
        return
    fi

    print_info "Generating coverage report..."
    print_warning "This may take a while..."

    mkdir -p "$COVERAGE_DIR"

    if run_in_app "XDEBUG_MODE=coverage php artisan test --coverage --min=70 --colors=always"; then
        print_success "Tests passed with sufficient coverage!"
    else
        print_warning "Check coverage report for details"
    fi

    press_any_key
}

run_parallel_tests() {
    print_section "Running Tests in Parallel"
    if ! check_artisan_test; then press_any_key; return; fi

    print_info "Executing tests in parallel mode..."

    if run_in_app "php artisan test --parallel --colors=always"; then
        print_success "All parallel tests passed!"
    else
        print_error "Some parallel tests failed!"
    fi

    press_any_key
}

run_failed_tests() {
    print_section "Re-running Failed Tests"
    if ! check_artisan_test; then press_any_key; return; fi

    if [[ ! -f "$FAILED_TESTS_FILE" ]]; then
        print_warning "No failed tests recorded!"
        print_info "Run tests first to track failures"
        press_any_key
        return
    fi

    print_info "Re-running previously failed tests..."

    # Check for order-by support or fallback
    if run_in_app "php artisan test --help | grep -q \"order-by\""; then
        CMD="php artisan test --order-by=defects --colors=always"
    else
        CMD="php artisan test --colors=always"
    fi

    if run_in_app "$CMD"; then
        print_success "All previously failed tests now pass!"
        rm -f "$FAILED_TESTS_FILE"
    else
        print_error "Some tests still failing!"
    fi

    press_any_key
}

watch_tests() {
    print_section "Watch Mode"
    print_info "Watching for file changes..."
    print_warning "Press Ctrl+C to stop watching"
    echo ""

    if ! command -v inotifywait &> /dev/null; then
        print_error "inotifywait not found! Install inotify-tools:"
        echo -e "  ${CYAN}sudo apt-get install inotify-tools${NC}"
        press_any_key
        return
    fi

    while true; do
        inotifywait -r -e modify,create,delete \
            --exclude '(vendor|node_modules|\.git|\.test_cache|coverage)' \
            app tests 2>/dev/null

        clear
        print_section "Re-running Tests (File Changed)"
        if check_artisan_test; then
            run_in_app "php artisan test --colors=always"
        fi
        echo -e "\n${DIM}Waiting for changes...${NC}"
    done
}

run_docker_tests() {
    print_section "Running Tests in Docker"

    print_info "Running tests in Docker container..."
    if ! detect_docker_service; then
        print_error "Could not detect a running Docker app service."
        press_any_key
        return
    fi
    USE_DOCKER=true
    
    print_info "Using service: $SERVICE_NAME"

    if run_in_app "php artisan test --colors=always"; then
        print_success "Docker tests passed!"
    else
        print_error "Docker tests failed!"
    fi

    press_any_key
}

# ============================================================================
# Code Quality Functions
# ============================================================================

fix_code_style() {
    print_section "Fix Code Style Issues"

    # Check for Laravel Pint (Laravel's code style fixer)
    if run_in_app "test -f vendor/bin/pint"; then
        print_info "Running Laravel Pint..."

        if run_in_app "./vendor/bin/pint"; then
            print_success "Code style fixed!"
        else
            print_error "Failed to fix code style"
        fi
    # Check for PHP-CS-Fixer
    elif run_in_app "command -v php-cs-fixer > /dev/null"; then
        print_info "Running PHP-CS-Fixer..."

        if run_in_app "php-cs-fixer fix"; then
            print_success "Code style fixed!"
        else
            print_error "Failed to fix code style"
        fi
    else
        print_warning "No code style fixer found!"
        print_info "Install Laravel Pint:"
        echo -e "  ${CYAN}composer require laravel/pint --dev${NC}"
    fi

    press_any_key
}

run_static_analysis() {
    print_section "Running Static Analysis"

    # Check for PHPStan
    if run_in_app "test -f vendor/bin/phpstan"; then
        print_info "Running PHPStan..."
        if run_in_app "./vendor/bin/phpstan analyse"; then
            print_success "No static analysis errors found!"
        else
            print_error "Static analysis found issues"
        fi
    # Check for Larastan
    elif run_in_app "test -f vendor/bin/larastan"; then
        print_info "Running Larastan..."
        if run_in_app "./vendor/bin/larastan analyse"; then
            print_success "No static analysis errors found!"
        else
            print_error "Static analysis found issues"
        fi
    else
        print_warning "No static analysis tool found!"
        print_info "Install PHPStan/Larastan:"
        echo -e "  ${CYAN}composer require nunomaduro/larastan --dev${NC}"
    fi

    press_any_key
}

# ============================================================================
# Maintenance Functions
# ============================================================================

clean_environment() {
    print_section "Cleaning Test Environment"

    print_info "Clearing application cache..."
    run_in_app "php artisan cache:clear"
    run_in_app "php artisan config:clear"
    run_in_app "php artisan route:clear"
    run_in_app "php artisan view:clear"

    if [[ -d "$COVERAGE_DIR" ]]; then
        print_info "Removing coverage reports..."
        rm -rf "$COVERAGE_DIR"
    fi

    if [[ -d "$CACHE_DIR" ]]; then
        print_info "Removing test cache..."
        rm -rf "$CACHE_DIR"
    fi

    print_success "Environment cleaned!"
    press_any_key
}

fix_permissions() {
    print_section "Fixing Permissions"
    print_info "Setting secure permissions on storage and bootstrap/cache..."

    # Use more secure permissions (755 for directories, 644 for files)
    if use_docker; then
        if run_in_app "chmod -R 777 /var/www/html/storage /var/www/html/bootstrap/cache"; then
            print_success "Container permissions set to 777"
        else
            print_error "Failed to set container permissions"
        fi
    elif chmod -R 755 storage bootstrap/cache 2>/dev/null; then
        print_success "Directory permissions set to 755"

        # Make sure we can write to these directories
        if [ -w storage ] && [ -w bootstrap/cache ]; then
            print_success "Write permissions verified!"
        else
            print_warning "Directory permissions set, but may need ownership adjustment"
            print_info "If tests fail, run: sudo chown -R \$USER:\$USER storage bootstrap/cache"
        fi
    else
        print_error "Failed to set permissions"
        print_info "Try: sudo chown -R \$USER:\$USER storage bootstrap/cache"
    fi
    press_any_key
}

list_test_files() {
    print_section "Test Files Overview"

    echo -e "${YELLOW}Feature Tests:${NC}"
    find tests/Feature -name "*Test.php" -type f | while read -r file; do
        local test_count=$(grep -c "function test_" "$file" 2>/dev/null || echo "0")
        echo -e "  ${GREEN}${CHECK}${NC} $file ${DIM}($test_count tests)${NC}"
    done

    echo -e "\n${YELLOW}Unit Tests:${NC}"
    find tests/Unit -name "*Test.php" -type f | while read -r file; do
        local test_count=$(grep -c "function test_" "$file" 2>/dev/null || echo "0")
        echo -e "  ${GREEN}${CHECK}${NC} $file ${DIM}($test_count tests)${NC}"
    done

    echo -e "\n${CYAN}${BOLD}Statistics:${NC}"
    local total_files=$(find tests -name "*Test.php" -type f | wc -l)
    
    echo -e "  Total test files: ${WHITE}$total_files${NC}"

    press_any_key
}

generate_coverage_html() {
    print_section "Generating HTML Coverage Report"
    if ! check_artisan_test; then press_any_key; return; fi

    print_info "Generating detailed coverage report..."
    print_warning "This may take a while..."

    mkdir -p "$COVERAGE_DIR"

    if run_in_app "XDEBUG_MODE=coverage php artisan test --coverage-html=\"$COVERAGE_DIR/html\" --colors=always"; then
        print_success "Coverage report generated!"
        print_info "Open: ${CYAN}$COVERAGE_DIR/html/index.html${NC}"
    else
        print_error "Failed to generate coverage report"
    fi

    press_any_key
}

run_quick_check() {
    print_section "Quick Health Check"

    print_info "Running quick validation..."
    echo ""

    # Check PHP version
    echo -ne "${WHITE}PHP Version:${NC} "
    run_in_app "php -v | head -n 1"

    # Check composer dependencies
    echo -ne "\n${WHITE}Composer Dependencies:${NC} "
    if run_in_app "test -d vendor"; then
        print_success "Installed"
    else
        print_error "Missing (run: composer install)"
    fi

    # Check environment file
    echo -ne "${WHITE}Environment File:${NC} "
    if run_in_app "test -f .env"; then
        print_success "Found"
    else
        print_error "Missing (copy .env.example to .env)"
    fi

    # Check app key
    echo -ne "${WHITE}Application Key:${NC} "
    if run_in_app "grep -q \"APP_KEY=base64:\" .env 2>/dev/null"; then
        print_success "Set"
    else
        print_warning "Missing (run: php artisan key:generate)"
    fi

    # Check test database
    echo -ne "${WHITE}Test Database:${NC} "
    if run_in_app "grep -q \"DB_CONNECTION=sqlite\" .env.testing 2>/dev/null || grep -q \":memory:\" phpunit.xml"; then
        print_success "Configured (SQLite)"
    else
        print_warning "Check configuration"
    fi

    # Check database connection
    echo -ne "${WHITE}Database Connection:${NC} "
    if run_in_app "php artisan db:show 2>/dev/null | grep -q \"Connection\""; then
        print_success "Connected"
    else
        print_warning "Check database settings"
    fi

    # Check migrations
    echo -ne "${WHITE}Database Migrations:${NC} "
    if run_in_app "php artisan migrate:status 2>/dev/null | grep -q \"Ran\""; then
        print_success "Up to date"
    else
        print_warning "May need to run: php artisan migrate"
    fi

    # Check AI Manager registration
    echo -ne "${WHITE}AI Manager Service:${NC} "
    if run_in_app "php artisan tinker --execute=\"echo (app()->bound('ai') ? 'Registered' : 'Missing');\" 2>/dev/null | grep -q \"Registered\""; then
        print_success "Registered"
    else
        print_error "Not registered (check AppServiceProvider)"
    fi

    # Check permissions
    echo -ne "${WHITE}Storage Permissions:${NC} "
    if use_docker; then
        if run_in_app "test -w /var/www/html/storage && test -w /var/www/html/bootstrap/cache"; then
            print_success "Writable"
        else
            print_error "Not writable (run option 15 to fix)"
        fi
    elif [[ -w "storage" ]] && [[ -w "bootstrap/cache" ]]; then
        print_success "Writable"
    else
        print_error "Not writable (run option 15 to fix)"
    fi

    # Run a quick test (Robust logic)
    echo -e "\n${WHITE}Running smoke test...${NC}"

    if ! run_in_app "php artisan list 2>/dev/null | grep -q \"test \""; then
        print_error "Command 'artisan test' NOT FOUND."
        print_info "You might need to run 'composer install' to get dev dependencies."
        print_warning "Check if 'nunomaduro/collision' is installed."
    else
        # Run in subshell to capture output without swallowing exit code
        if run_in_app "php artisan test --testsuite=Unit --stop-on-failure --colors=always > /tmp/smoke_test.log 2>&1"; then
            print_success "Basic tests working!"
        else
            print_error "Tests have issues"
            run_in_app "tail -n 5 /tmp/smoke_test.log"
        fi
        run_in_app "rm -f /tmp/smoke_test.log"
    fi

    press_any_key
}

save_failed_tests() {
    mkdir -p "$CACHE_DIR"
    date > "$FAILED_TESTS_FILE"
    print_info "Failed test information saved"
}

run_filter_test() {
    print_section "Run Tests by Filter"
    if ! check_artisan_test; then press_any_key; return; fi

    echo -e "${WHITE}Enter test name filter (e.g., 'test_user_can_login'):${NC} "
    read -r filter

    if [[ -z "$filter" ]]; then
        print_error "Filter cannot be empty!"
        press_any_key
        return
    fi

    print_info "Running tests matching: $filter"

    if run_in_app "php artisan test --filter=\"$filter\" --colors=always"; then
        print_success "Filtered tests passed!"
    else
        print_error "Filtered tests failed!"
    fi

    press_any_key
}

# ============================================================================
# Module Testing Functions (Laravel Modules Support)
# ============================================================================

has_modules() {
    if [[ -d "Modules" ]]; then
        return 0
    fi
    return 1
}

validate_ai_services() {
    print_section "Validating AI Services"

    print_info "Checking AI driver registration..."

    # Check if AI manager is bound
    if run_in_app "php artisan tinker --execute='try { \$manager = app(\"ai\"); echo \"AI Manager: OK\".PHP_EOL; foreach ([\"openai\", \"anthropic\", \"gemini\", \"deepseek\", \"openrouter\", \"ollama\", \"lmstudio\", \"mock\"] as \$driver) { try { \$manager->driver(\$driver); echo ucfirst(\$driver).\" Driver: OK\".PHP_EOL; } catch (\\Throwable \$e) { echo ucfirst(\$driver).\" Driver: FAILED - \".\$e->getMessage().PHP_EOL; } } } catch (\\Throwable \$e) { echo \"AI Manager: FAILED - \".\$e->getMessage().PHP_EOL; }' 2>/dev/null"; then
        print_success "AI services validated!"
    else
        print_error "AI service validation failed!"
    fi

    press_any_key
}

check_database_setup() {
    print_section "Database Setup Check"

    print_info "Checking database configuration..."

    # Check if database is accessible
    if ! run_in_app "php artisan db:show 2>&1 | grep -q \"Connection\""; then
        print_error "Database connection failed!"
        print_info "Check your .env file database settings"
        press_any_key
        return
    fi

    print_success "Database connected"

    # Check migrations
    print_info "Checking migrations status..."
    if run_in_app "php artisan migrate:status 2>&1 | grep -q \"Pending\""; then
        print_warning "You have pending migrations!"
        echo -ne "\n${WHITE}Run migrations now? (y/n): ${NC}"
        read -r run_migrations

        if [[ "$run_migrations" == "y" ]]; then
            print_info "Running migrations..."
            if run_in_app "php artisan migrate --force"; then
                print_success "Migrations completed!"
            else
                print_error "Migration failed!"
            fi
        fi
    else
        print_success "All migrations are up to date"
    fi

    # Check if database is seeded
    print_info "Checking if database needs seeding..."
    if run_in_app "php artisan tinker --execute=\"echo (\\\\App\\\\Models\\\\User::count() > 0) ? 'Seeded' : 'Empty';\" 2>/dev/null | grep -q \"Empty\""; then
        print_warning "Database appears empty"
        echo -ne "\n${WHITE}Run seeders now? (y/n): ${NC}"
        read -r run_seeders

        if [[ "$run_seeders" == "y" ]]; then
            print_info "Running database seeders..."
            if run_in_app "php artisan db:seed --force"; then
                print_success "Database seeded!"
            else
                print_error "Seeding failed!"
            fi
        fi
    else
        print_success "Database contains data"
    fi

    press_any_key
}

optimize_application() {
    print_section "Optimizing Application"

    print_info "Clearing all caches..."
    run_in_app "php artisan cache:clear"
    run_in_app "php artisan config:clear"
    run_in_app "php artisan route:clear"
    run_in_app "php artisan view:clear"

    print_info "Optimizing application for production..."
    run_in_app "php artisan config:cache"
    run_in_app "php artisan route:cache"
    run_in_app "php artisan view:cache"

    print_info "Optimizing composer autoloader..."
    run_in_app "composer dump-autoload --optimize"

    print_success "Application optimized!"
    press_any_key
}

# ============================================================================
# Main Menu
# ============================================================================

show_menu() {
    print_header

    echo -e "${YELLOW}${BOLD}  ${ROCKET} Test Execution${NC}"
    print_menu_option "1" "${FIRE}" "Run All Tests (App + Modules)" "${GREEN}"
    print_menu_option "2" "${SPARKLES}" "Run Feature Tests Only" "${CYAN}"
    print_menu_option "3" "${GEAR}" "Run Unit Tests Only" "${CYAN}"
    print_menu_option "4" "${MAGNIFY}" "Run Specific Test File" "${BLUE}"
    print_menu_option "5" "${MAGNIFY}" "Run Tests by Filter/Pattern" "${BLUE}"
    print_menu_option "6" "${BUG}" "Re-run Failed Tests Only" "${RED}"

    echo -e "\n${YELLOW}${BOLD}  ${WRENCH} Advanced Testing${NC}"
    print_menu_option "7" "${SPARKLES}" "Run with Coverage Report" "${MAGENTA}"
    print_menu_option "8" "${ROCKET}" "Run Tests in Parallel" "${GREEN}"
    print_menu_option "9" "${MAGNIFY}" "Watch Mode (Auto-rerun)" "${CYAN}"
    print_menu_option "10" "${GEAR}" "Run Tests in Docker" "${BLUE}"
    print_menu_option "11" "${SPARKLES}" "Generate HTML Coverage Report" "${MAGENTA}"

    echo -e "\n${YELLOW}${BOLD}  ${WRENCH} Code Quality & Fixes${NC}"
    print_menu_option "12" "${WRENCH}" "Fix Code Style Issues" "${GREEN}"
    print_menu_option "13" "${MAGNIFY}" "Run Static Analysis" "${CYAN}"
    print_menu_option "14" "${ROCKET}" "Quick Health Check" "${BLUE}"
    print_menu_option "15" "${WRENCH}" "Fix Permissions" "${RED}"

    echo -e "\n${YELLOW}${BOLD}  ${GEAR} Application Checks${NC}"
    print_menu_option "16" "${ROCKET}" "Validate AI Services" "${MAGENTA}"
    print_menu_option "17" "${GEAR}" "Check Database Setup" "${CYAN}"
    print_menu_option "18" "${SPARKLES}" "Optimize Application" "${GREEN}"

    echo -e "\n${YELLOW}${BOLD}  ${GEAR} Maintenance${NC}"
    print_menu_option "19" "${SPARKLES}" "List All Test Files" "${WHITE}"
    print_menu_option "20" "${WRENCH}" "Clean Test Environment" "${YELLOW}"

    echo -e "\n${RED}${BOLD}  [0]${NC} ${CROSS}  ${WHITE}Exit${NC}"

    echo -e "\n${CYAN}${BOLD}╔════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}${BOLD}║${NC} ${WHITE}Enter your choice:${NC}                                              ${CYAN}${BOLD}║${NC}"
    echo -e "${CYAN}${BOLD}╚════════════════════════════════════════════════════════════════╝${NC}"
    echo -ne "${WHITE}${BOLD} > ${NC}"
}

# ============================================================================
# Main Loop
# ============================================================================

# Create cache directory
mkdir -p "$CACHE_DIR"

# Detect Docker service (if running)
if detect_docker_service; then
    USE_DOCKER=true
fi

# Main Loop
while true; do
    show_menu
    read -r choice

    case $choice in
        1) run_all_tests ;;
        2) run_feature_tests ;;
        3) run_unit_tests ;;
        4) run_specific_test ;;
        5) run_filter_test ;;
        6) run_failed_tests ;;
        7) run_with_coverage ;;
        8) run_parallel_tests ;;
        9) watch_tests ;;
        10) run_docker_tests ;;
        11) generate_coverage_html ;;
        12) fix_code_style ;;
        13) run_static_analysis ;;
        14) run_quick_check ;;
        15) fix_permissions ;;
        16) validate_ai_services ;;
        17) check_database_setup ;;
        18) optimize_application ;;
        19) list_test_files ;;
        20) clean_environment ;;
        0)
            echo -e "\n${GREEN}${SPARKLES} Happy testing! ${SPARKLES}${NC}\n"
            exit 0
            ;;
        *)
            print_error "Invalid choice! Please try again."
            sleep 1
            ;;
    esac
done
