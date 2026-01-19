#!/bin/bash

# ============================================================================
# Chat Bridge - Test Runner Script
# A colorful CLI menu for running tests and fixing issues
# ============================================================================

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
    echo -e "\n${DIM}Press any key to continue...${NC}"
    read -n 1 -s
}

# ============================================================================
# Test Functions
# ============================================================================

run_all_tests() {
    print_section "Running All Tests (App + Modules)"
    print_info "Executing full test suite..."

    local all_passed=true

    # Run core application tests
    echo -e "${CYAN}${BOLD}Running Core Application Tests...${NC}\n"
    if php artisan test --colors=always; then
        print_success "Core application tests passed!"
    else
        print_error "Some core application tests failed!"
        all_passed=false
        save_failed_tests
    fi

    # Run module tests if modules exist
    if has_modules; then
        local modules=($(list_modules))
        if [[ ${#modules[@]} -gt 0 ]]; then
            echo -e "\n${CYAN}${BOLD}Running Module Tests...${NC}\n"

            for module in "${modules[@]}"; do
                if [[ -d "Modules/$module/Tests" ]]; then
                    echo -e "${BLUE}Testing module: $module${NC}"

                    if php artisan test "Modules/$module/Tests" --colors=always 2>/dev/null; then
                        print_success "Module $module tests passed!"
                    else
                        print_error "Module $module tests failed!"
                        all_passed=false
                    fi
                fi
            done
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
    print_info "Executing feature test suite..."

    if php artisan test --testsuite=Feature --colors=always; then
        print_success "Feature tests passed!"
    else
        print_error "Some feature tests failed!"
        save_failed_tests
    fi

    press_any_key
}

run_unit_tests() {
    print_section "Running Unit Tests"
    print_info "Executing unit test suite..."

    if php artisan test --testsuite=Unit --colors=always; then
        print_success "Unit tests passed!"
    else
        print_error "Some unit tests failed!"
        save_failed_tests
    fi

    press_any_key
}

run_specific_test() {
    print_section "Run Specific Test File"
    echo -e "${YELLOW}Available test files:${NC}\n"

    # List all test files with numbers
    local files=($(find tests -name "*Test.php" -type f | sort))
    local i=1
    for file in "${files[@]}"; do
        echo -e "  ${CYAN}[$i]${NC} $file"
        ((i++))
    done

    echo -e "\n${WHITE}Enter file number (or 0 to cancel): ${NC}"
    read -r choice

    if [[ "$choice" -eq 0 ]]; then
        return
    fi

    if [[ "$choice" -gt 0 && "$choice" -le "${#files[@]}" ]]; then
        local selected_file="${files[$((choice-1))]}"
        print_info "Running: $selected_file"

        if php artisan test "$selected_file" --colors=always; then
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

    if ! command -v php &> /dev/null; then
        print_error "PHP not found!"
        press_any_key
        return
    fi

    print_info "Generating coverage report..."
    print_warning "This may take a while..."

    mkdir -p "$COVERAGE_DIR"

    if XDEBUG_MODE=coverage php artisan test --coverage --min=70 --colors=always; then
        print_success "Tests passed with sufficient coverage!"
    else
        print_warning "Check coverage report for details"
    fi

    press_any_key
}

run_parallel_tests() {
    print_section "Running Tests in Parallel"
    print_info "Executing tests in parallel mode..."

    if php artisan test --parallel --colors=always; then
        print_success "All parallel tests passed!"
    else
        print_error "Some parallel tests failed!"
    fi

    press_any_key
}

run_failed_tests() {
    print_section "Re-running Failed Tests"

    if [[ ! -f "$FAILED_TESTS_FILE" ]]; then
        print_warning "No failed tests recorded!"
        print_info "Run tests first to track failures"
        press_any_key
        return
    fi

    print_info "Re-running previously failed tests..."

    # PHPUnit can re-run failed tests using --cache-result
    if php artisan test --order-by=defects --colors=always; then
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
        php artisan test --colors=always
        echo -e "\n${DIM}Waiting for changes...${NC}"
    done
}

run_docker_tests() {
    print_section "Running Tests in Docker"

    if ! command -v docker &> /dev/null; then
        print_error "Docker not found!"
        press_any_key
        return
    fi

    print_info "Running tests in Docker container..."

    if docker compose exec app php artisan test --colors=always; then
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
    if [[ -f "vendor/bin/pint" ]]; then
        print_info "Running Laravel Pint..."

        if ./vendor/bin/pint; then
            print_success "Code style fixed!"
        else
            print_error "Failed to fix code style"
        fi
    # Check for PHP-CS-Fixer
    elif command -v php-cs-fixer &> /dev/null; then
        print_info "Running PHP-CS-Fixer..."

        if php-cs-fixer fix; then
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
    if [[ -f "vendor/bin/phpstan" ]]; then
        print_info "Running PHPStan..."

        if ./vendor/bin/phpstan analyse; then
            print_success "No static analysis errors found!"
        else
            print_error "Static analysis found issues"
        fi
    # Check for Psalm
    elif [[ -f "vendor/bin/psalm" ]]; then
        print_info "Running Psalm..."

        if ./vendor/bin/psalm; then
            print_success "No static analysis errors found!"
        else
            print_error "Static analysis found issues"
        fi
    # Check for Larastan
    elif [[ -f "vendor/bin/larastan" ]]; then
        print_info "Running Larastan..."

        if ./vendor/bin/larastan analyse; then
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
    php artisan cache:clear

    print_info "Clearing config cache..."
    php artisan config:clear

    print_info "Clearing route cache..."
    php artisan route:clear

    print_info "Clearing view cache..."
    php artisan view:clear

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

list_test_files() {
    print_section "Test Files Overview"

    echo -e "${YELLOW}Feature Tests:${NC}"
    find tests/Feature -name "*Test.php" -type f | while read -r file; do
        local test_count=$(grep -c "public function test_" "$file" 2>/dev/null || echo "0")
        echo -e "  ${GREEN}${CHECK}${NC} $file ${DIM}($test_count tests)${NC}"
    done

    echo -e "\n${YELLOW}Unit Tests:${NC}"
    find tests/Unit -name "*Test.php" -type f | while read -r file; do
        local test_count=$(grep -c "public function test_" "$file" 2>/dev/null || echo "0")
        echo -e "  ${GREEN}${CHECK}${NC} $file ${DIM}($test_count tests)${NC}"
    done

    echo -e "\n${CYAN}${BOLD}Statistics:${NC}"
    local total_files=$(find tests -name "*Test.php" -type f | wc -l)
    local feature_files=$(find tests/Feature -name "*Test.php" -type f | wc -l)
    local unit_files=$(find tests/Unit -name "*Test.php" -type f | wc -l)

    echo -e "  Total test files: ${WHITE}$total_files${NC}"
    echo -e "  Feature tests: ${WHITE}$feature_files${NC}"
    echo -e "  Unit tests: ${WHITE}$unit_files${NC}"

    press_any_key
}

generate_coverage_html() {
    print_section "Generating HTML Coverage Report"

    print_info "Generating detailed coverage report..."
    print_warning "This may take a while..."

    mkdir -p "$COVERAGE_DIR"

    if XDEBUG_MODE=coverage php artisan test --coverage-html="$COVERAGE_DIR/html" --colors=always; then
        print_success "Coverage report generated!"
        print_info "Open: ${CYAN}$COVERAGE_DIR/html/index.html${NC}"

        # Try to open in browser
        if command -v xdg-open &> /dev/null; then
            echo -e "\n${WHITE}Open report in browser? [y/N]:${NC} "
            read -r open_browser
            if [[ "$open_browser" == "y" || "$open_browser" == "Y" ]]; then
                xdg-open "$COVERAGE_DIR/html/index.html" &
            fi
        fi
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
    php -v | head -n 1

    # Check composer dependencies
    echo -ne "\n${WHITE}Composer Dependencies:${NC} "
    if [[ -d "vendor" ]]; then
        print_success "Installed"
    else
        print_error "Missing (run: composer install)"
    fi

    # Check test database
    echo -ne "${WHITE}Test Database:${NC} "
    if grep -q "DB_CONNECTION=sqlite" .env.testing 2>/dev/null || grep -q ":memory:" phpunit.xml; then
        print_success "Configured (SQLite)"
    else
        print_warning "Check configuration"
    fi

    # Run a quick test
    echo -e "\n${WHITE}Running smoke test...${NC}"
    if php artisan test --testsuite=Unit --stop-on-failure --colors=always 2>&1 | tail -n 5; then
        print_success "Basic tests working!"
    else
        print_error "Tests have issues"
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

    echo -e "${WHITE}Enter test name filter (e.g., 'test_user_can_login'):${NC} "
    read -r filter

    if [[ -z "$filter" ]]; then
        print_error "Filter cannot be empty!"
        press_any_key
        return
    fi

    print_info "Running tests matching: $filter"

    if php artisan test --filter="$filter" --colors=always; then
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
    # Check if nwidart/laravel-modules is installed
    if [[ -d "Modules" ]] || grep -q "nwidart/laravel-modules" composer.json 2>/dev/null; then
        return 0
    fi
    return 1
}

list_modules() {
    if [[ -d "Modules" ]]; then
        find Modules -maxdepth 1 -mindepth 1 -type d -exec basename {} \; | sort
    fi
}

run_all_module_tests() {
    print_section "Running All Module Tests"

    if ! has_modules; then
        print_warning "No Laravel modules found!"
        print_info "Install nwidart/laravel-modules:"
        echo -e "  ${CYAN}composer require nwidart/laravel-modules${NC}"
        press_any_key
        return
    fi

    print_info "Testing all modules..."

    local modules=($(list_modules))
    local failed_modules=()

    for module in "${modules[@]}"; do
        echo -e "\n${CYAN}${BOLD}Testing module: $module${NC}"

        if php artisan test "Modules/$module/Tests" --colors=always 2>/dev/null; then
            print_success "Module $module tests passed!"
        else
            print_error "Module $module tests failed!"
            failed_modules+=("$module")
        fi
    done

    echo ""
    if [[ ${#failed_modules[@]} -eq 0 ]]; then
        print_success "All module tests passed!"
    else
        print_error "Failed modules: ${failed_modules[*]}"
    fi

    press_any_key
}

run_specific_module_test() {
    print_section "Run Specific Module Tests"

    if ! has_modules; then
        print_warning "No Laravel modules found!"
        print_info "Install nwidart/laravel-modules:"
        echo -e "  ${CYAN}composer require nwidart/laravel-modules${NC}"
        press_any_key
        return
    fi

    local modules=($(list_modules))

    if [[ ${#modules[@]} -eq 0 ]]; then
        print_warning "No modules found in Modules directory!"
        press_any_key
        return
    fi

    echo -e "${YELLOW}Available modules:${NC}\n"

    local i=1
    for module in "${modules[@]}"; do
        # Count tests in module
        local test_count=0
        if [[ -d "Modules/$module/Tests" ]]; then
            test_count=$(find "Modules/$module/Tests" -name "*Test.php" | wc -l)
        fi
        echo -e "  ${CYAN}[$i]${NC} $module ${DIM}($test_count test files)${NC}"
        ((i++))
    done

    echo -e "\n${WHITE}Enter module number (or 0 to cancel): ${NC}"
    read -r choice

    if [[ "$choice" -eq 0 ]]; then
        return
    fi

    if [[ "$choice" -gt 0 && "$choice" -le "${#modules[@]}" ]]; then
        local selected_module="${modules[$((choice-1))]}"
        print_info "Running tests for module: $selected_module"

        if [[ ! -d "Modules/$selected_module/Tests" ]]; then
            print_warning "No tests found for module: $selected_module"
            press_any_key
            return
        fi

        if php artisan test "Modules/$selected_module/Tests" --colors=always; then
            print_success "Module $selected_module tests passed!"
        else
            print_error "Module $selected_module tests failed!"
        fi
    else
        print_error "Invalid selection!"
    fi

    press_any_key
}

list_module_tests() {
    print_section "Module Tests Overview"

    if ! has_modules; then
        print_warning "No Laravel modules found!"
        print_info "To enable modular architecture, install:"
        echo -e "  ${CYAN}composer require nwidart/laravel-modules${NC}"
        echo -e "\n${DIM}Then create modules with:${NC}"
        echo -e "  ${CYAN}php artisan module:make ModuleName${NC}"
        press_any_key
        return
    fi

    local modules=($(list_modules))

    if [[ ${#modules[@]} -eq 0 ]]; then
        print_warning "No modules found in Modules directory!"
        press_any_key
        return
    fi

    for module in "${modules[@]}"; do
        echo -e "\n${YELLOW}${BOLD}Module: $module${NC}"

        if [[ -d "Modules/$module/Tests" ]]; then
            local feature_tests=$(find "Modules/$module/Tests/Feature" -name "*Test.php" 2>/dev/null | wc -l)
            local unit_tests=$(find "Modules/$module/Tests/Unit" -name "*Test.php" 2>/dev/null | wc -l)

            echo -e "  ${GREEN}${CHECK}${NC} Feature tests: ${WHITE}$feature_tests${NC}"
            echo -e "  ${GREEN}${CHECK}${NC} Unit tests: ${WHITE}$unit_tests${NC}"

            # List test files
            find "Modules/$module/Tests" -name "*Test.php" 2>/dev/null | while read -r file; do
                local test_count=$(grep -c "public function test_" "$file" 2>/dev/null || echo "0")
                local relative_path=${file#Modules/$module/Tests/}
                echo -e "    ${DIM}└─${NC} $relative_path ${DIM}($test_count tests)${NC}"
            done
        else
            echo -e "  ${YELLOW}⚠${NC} No tests directory found"
        fi
    done

    press_any_key
}

run_module_feature_tests() {
    print_section "Running All Module Feature Tests"

    if ! has_modules; then
        print_warning "No Laravel modules found!"
        press_any_key
        return
    fi

    local modules=($(list_modules))

    for module in "${modules[@]}"; do
        if [[ -d "Modules/$module/Tests/Feature" ]]; then
            echo -e "\n${CYAN}${BOLD}Testing $module feature tests...${NC}"

            if php artisan test "Modules/$module/Tests/Feature" --colors=always; then
                print_success "Module $module feature tests passed!"
            else
                print_error "Module $module feature tests failed!"
            fi
        fi
    done

    press_any_key
}

run_module_unit_tests() {
    print_section "Running All Module Unit Tests"

    if ! has_modules; then
        print_warning "No Laravel modules found!"
        press_any_key
        return
    fi

    local modules=($(list_modules))

    for module in "${modules[@]}"; do
        if [[ -d "Modules/$module/Tests/Unit" ]]; then
            echo -e "\n${CYAN}${BOLD}Testing $module unit tests...${NC}"

            if php artisan test "Modules/$module/Tests/Unit" --colors=always; then
                print_success "Module $module unit tests passed!"
            else
                print_error "Module $module unit tests failed!"
            fi
        fi
    done

    press_any_key
}

setup_module_testing() {
    print_section "Setup Module Testing"

    print_info "Installing nwidart/laravel-modules..."

    if composer require nwidart/laravel-modules; then
        print_success "Laravel Modules installed!"

        print_info "Publishing configuration..."
        php artisan vendor:publish --provider="Nwidart\Modules\LaravelModulesServiceProvider"

        print_success "Module system ready!"
        print_info "Create your first module with:"
        echo -e "  ${CYAN}php artisan module:make YourModuleName${NC}"
    else
        print_error "Failed to install Laravel Modules"
    fi

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

    echo -e "\n${YELLOW}${BOLD}  📦 Module Testing${NC}"
    print_menu_option "17" "${FIRE}" "Run All Module Tests" "${GREEN}"
    print_menu_option "18" "${SPARKLES}" "Run Specific Module Tests" "${CYAN}"
    print_menu_option "19" "${GEAR}" "Run Module Feature Tests" "${BLUE}"
    print_menu_option "20" "${MAGNIFY}" "Run Module Unit Tests" "${BLUE}"
    print_menu_option "21" "${SPARKLES}" "List Module Tests Overview" "${WHITE}"
    print_menu_option "22" "${WRENCH}" "Setup Module Testing" "${MAGENTA}"

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

    echo -e "\n${YELLOW}${BOLD}  ${GEAR} Maintenance${NC}"
    print_menu_option "15" "${SPARKLES}" "List All Test Files" "${WHITE}"
    print_menu_option "16" "${WRENCH}" "Clean Test Environment" "${YELLOW}"

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
        15) list_test_files ;;
        16) clean_environment ;;
        17) run_all_module_tests ;;
        18) run_specific_module_test ;;
        19) run_module_feature_tests ;;
        20) run_module_unit_tests ;;
        21) list_module_tests ;;
        22) setup_module_testing ;;
        0)
            clear
            echo -e "${GREEN}${SPARKLES} Thanks for using Chat Bridge Test Runner! ${SPARKLES}${NC}"
            echo -e "${CYAN}Happy testing!${NC}\n"
            exit 0
            ;;
        *)
            print_error "Invalid choice! Please try again."
            sleep 1
            ;;
    esac
done
