#!/usr/bin/env python3
"""
🏆 Chat Bridge Certification Script

This script runs comprehensive tests to certify a Chat Bridge installation.
It verifies all functionality, providers, and creates a certification report.
"""

import asyncio
import json
import os
import sqlite3
import sys
from datetime import datetime
from pathlib import Path
from typing import Dict, List, Tuple

from bridge_agents import provider_choices, get_spec, ensure_credentials
from chat_bridge import (
    ping_all_providers,
    setup_database,
    load_roles_file,
    Colors,
    colorize,
    print_banner,
    print_section_header,
    print_success,
    print_error,
    print_warning,
    print_info
)


class CertificationReport:
    """Generates and manages certification test results"""

    def __init__(self):
        self.tests = []
        self.start_time = datetime.now()

    def add_test(self, name: str, status: str, details: str = "", duration: float = 0.0, provider_info: str = ""):
        """Add a test result"""
        timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S.%f")[:-3]
        self.tests.append({
            'name': name,
            'status': status,
            'details': details,
            'duration': duration,
            'timestamp': timestamp,
            'provider_info': provider_info
        })

    def get_summary(self) -> Dict:
        """Get test summary statistics"""
        total = len(self.tests)
        passed = sum(1 for t in self.tests if t['status'] == 'PASS')
        failed = sum(1 for t in self.tests if t['status'] == 'FAIL')
        warnings = sum(1 for t in self.tests if t['status'] == 'WARN')

        return {
            'total': total,
            'passed': passed,
            'failed': failed,
            'warnings': warnings,
            'duration': (datetime.now() - self.start_time).total_seconds(),
            'success_rate': (passed / total * 100) if total > 0 else 0
        }

    def print_summary(self):
        """Print colored test summary"""
        summary = self.get_summary()
        current_time = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

        print_section_header("Certification Results", "🏆")
        print(f"  {colorize('Completed at:', Colors.WHITE)} {colorize(current_time, Colors.CYAN)}")
        print(f"  {colorize('Total Tests:', Colors.WHITE)} {colorize(str(summary['total']), Colors.CYAN)}")
        print(f"  {colorize('Passed:', Colors.GREEN)} {colorize(str(summary['passed']), Colors.GREEN, bold=True)}")
        if summary['failed'] > 0:
            print(f"  {colorize('Failed:', Colors.RED)} {colorize(str(summary['failed']), Colors.RED, bold=True)}")
        if summary['warnings'] > 0:
            print(f"  {colorize('Warnings:', Colors.YELLOW)} {colorize(str(summary['warnings']), Colors.YELLOW, bold=True)}")

        success_rate = f"{summary['success_rate']:.1f}%"
        duration = f"{summary['duration']:.2f}s"
        print(f"  {colorize('Success Rate:', Colors.WHITE)} {colorize(success_rate, Colors.CYAN)}")
        print(f"  {colorize('Duration:', Colors.WHITE)} {colorize(duration, Colors.CYAN)}")

        print()

        # Overall certification status
        cert_timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        if summary['success_rate'] >= 85 and summary['failed'] <= 3:
            print(f"[{cert_timestamp}] CERTIFICATION STATUS: PASSED")
            print_success("🏆 CERTIFICATION: PASSED")
            print_info("Your Chat Bridge installation is fully certified!")
        elif summary['success_rate'] >= 70 and summary['failed'] <= 5:
            print(f"[{cert_timestamp}] CERTIFICATION STATUS: CONDITIONAL PASS")
            print_warning("⚠️ CERTIFICATION: CONDITIONAL PASS")
            print_info("Your installation works but has some limitations.")
        else:
            print(f"[{cert_timestamp}] CERTIFICATION STATUS: FAILED")
            print_error("❌ CERTIFICATION: FAILED")
            print_error("Your installation needs fixes before certification.")

    def save_report(self, filepath: str):
        """Save detailed report to JSON file"""
        summary = self.get_summary()
        
        # Group tests by provider/component for better organization
        provider_tests = {}
        system_tests = []
        
        for test in self.tests:
            provider_info = test.get('provider_info', 'System')
            if provider_info not in provider_tests:
                provider_tests[provider_info] = []
            provider_tests[provider_info].append(test)
        
        report = {
            'metadata': {
                'timestamp': datetime.now().isoformat(),
                'version': '1.0',
                'platform': sys.platform,
                'python_version': sys.version,
                'total_duration_seconds': summary['duration']
            },
            'summary': summary,
            'tests_by_provider': provider_tests,
            'all_tests': self.tests
        }

        with open(filepath, 'w') as f:
            json.dump(report, f, indent=2)
        print_info(f"Detailed report saved: {colorize(filepath, Colors.CYAN)}")
        
        # Print brief summary of providers tested
        provider_summary = {}
        for provider, tests in provider_tests.items():
            passed = sum(1 for t in tests if t['status'] == 'PASS')
            failed = sum(1 for t in tests if t['status'] == 'FAIL')
            warned = sum(1 for t in tests if t['status'] == 'WARN')
            provider_summary[provider] = {'passed': passed, 'failed': failed, 'warned': warned}
        
        print(f"\n{colorize('Provider Test Summary:', Colors.WHITE)}")
        for provider, stats in provider_summary.items():
            status_color = Colors.GREEN if stats['failed'] == 0 else Colors.RED if stats['failed'] > stats['passed'] else Colors.YELLOW
            print(f"  {colorize(provider + ':', status_color)} {stats['passed']} passed, {stats['failed']} failed, {stats['warned']} warnings")


async def test_provider_connectivity(report: CertificationReport) -> int:
    """Test all provider connectivity"""
    print_section_header("Provider Connectivity Tests", "🌐")

    try:
        results = await ping_all_providers()
        online_count = 0

        for provider_key, result in results.items():
            status = "PASS" if result["status"] == "online" else "FAIL"
            if result["status"] == "online":
                online_count += 1

            # Get provider spec for detailed info
            try:
                spec = get_spec(provider_key)
                provider_name = spec.label
                model_info = spec.default_model
                provider_type = spec.kind.upper()
                provider_details = f"Provider: {provider_name} ({provider_type}), Model: {model_info}"
            except:
                provider_details = f"Provider: {provider_key}"

            details = f"Status: {result['status']}, Message: {result['message']}"
            if result.get("response_time"):
                details += f", Response time: {result['response_time']}ms"

            timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            print(f"[{timestamp}] Testing {provider_details} - {status}")

            report.add_test(
                f"Provider Connectivity - {result['label']}",
                status,
                details,
                provider_info=provider_details
            )

        # Overall connectivity test
        timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        overall_details = f"Total providers tested: {len(results)}, Online: {online_count}"
        
        if online_count >= 2:
            report.add_test("Overall Provider Connectivity", "PASS", f"{online_count}/{len(results)} providers online", provider_info="All Providers")
            print(f"[{timestamp}] Overall connectivity: PASS - {overall_details}")
        elif online_count >= 1:
            report.add_test("Overall Provider Connectivity", "WARN", f"Only {online_count}/{len(results)} providers online", provider_info="All Providers")
            print(f"[{timestamp}] Overall connectivity: WARN - {overall_details}")
        else:
            report.add_test("Overall Provider Connectivity", "FAIL", "No providers online", provider_info="All Providers")
            print(f"[{timestamp}] Overall connectivity: FAIL - {overall_details}")

        return online_count

    except Exception as e:
        timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        print(f"[{timestamp}] Provider connectivity test failed: {str(e)}")
        report.add_test("Provider Connectivity Test", "FAIL", f"Exception: {str(e)}", provider_info="System Error")
        return 0


def test_file_structure(report: CertificationReport):
    """Test required files and directories exist"""
    print_section_header("File Structure Tests", "📁")
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    print(f"[{timestamp}] Starting file structure validation...")

    required_files = [
        'chat_bridge.py',
        'bridge_agents.py',
        'version.py',
        'README.md'
    ]

    required_dirs = [
        'transcripts',
        'logs',
        'docs'
    ]

    for file in required_files:
        timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        if os.path.exists(file):
            report.add_test(f"Required file: {file}", "PASS", "File exists", provider_info="File System")
            print(f"[{timestamp}] File check: {file} - FOUND")
            print_success(f"✅ {file}")
        else:
            report.add_test(f"Required file: {file}", "FAIL", "File missing", provider_info="File System")
            print(f"[{timestamp}] File check: {file} - MISSING")
            print_error(f"❌ {file} - MISSING")

    for directory in required_dirs:
        timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        if os.path.exists(directory):
            report.add_test(f"Required directory: {directory}", "PASS", "Directory exists", provider_info="File System")
            print(f"[{timestamp}] Directory check: {directory}/ - EXISTS")
            print_success(f"✅ {directory}/")
        else:
            # Create directory and warn
            os.makedirs(directory, exist_ok=True)
            report.add_test(f"Required directory: {directory}", "WARN", "Directory created", provider_info="File System")
            print(f"[{timestamp}] Directory check: {directory}/ - CREATED")
            print_warning(f"⚠️ {directory}/ - Created")


def test_database_operations(report: CertificationReport):
    """Test SQLite database operations"""
    print_section_header("Database Operations Tests", "🗄️")
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    print(f"[{timestamp}] Starting database operations test...")

    try:
        # Test database setup
        timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        conn = setup_database()
        report.add_test("Database Setup", "PASS", "Database created successfully", provider_info="SQLite Database")
        print(f"[{timestamp}] Database setup: SUCCESS")
        print_success("✅ Database setup")

        # Test table creation
        cursor = conn.cursor()
        cursor.execute("SELECT name FROM sqlite_master WHERE type='table'")
        tables = [row[0] for row in cursor.fetchall()]

        required_tables = ['conversations', 'messages']
        for table in required_tables:
            timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            if table in tables:
                report.add_test(f"Database table: {table}", "PASS", "Table exists", provider_info="SQLite Database")
                print(f"[{timestamp}] Table check: {table} - EXISTS")
                print_success(f"✅ Table '{table}'")
            else:
                report.add_test(f"Database table: {table}", "FAIL", "Table missing", provider_info="SQLite Database")
                print(f"[{timestamp}] Table check: {table} - MISSING")
                print_error(f"❌ Table '{table}' - MISSING")

        conn.close()

    except Exception as e:
        timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        report.add_test("Database Operations", "FAIL", f"Exception: {str(e)}", provider_info="SQLite Database")
        print(f"[{timestamp}] Database operations: ERROR - {str(e)}")
        print_error(f"❌ Database error: {e}")


def test_roles_system(report: CertificationReport):
    """Test roles and personas system"""
    print_section_header("Roles System Tests", "🎭")
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    print(f"[{timestamp}] Starting roles system test...")

    # Test with sample roles file
    sample_roles = {
        "agent_a": {
            "provider": "openai",
            "system": "Test system prompt",
            "guidelines": ["Test guideline"]
        },
        "agent_b": {
            "provider": "anthropic",
            "system": "Test system prompt B",
            "guidelines": []
        },
        "persona_library": {
            "test_persona": {
                "name": "test_persona",
                "provider": "openai",
                "system": "Test persona",
                "guidelines": ["Persona guideline"]
            }
        },
        "temp_a": 0.7,
        "temp_b": 0.8,
        "stop_words": ["end", "stop"]
    }

    test_roles_file = "test_roles_cert.json"

    try:
        # Create test roles file
        with open(test_roles_file, 'w') as f:
            json.dump(sample_roles, f, indent=2)

        # Test loading
        timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        roles = load_roles_file(test_roles_file)
        if roles:
            report.add_test("Roles File Loading", "PASS", "Roles loaded successfully", provider_info="Roles System")
            print(f"[{timestamp}] Roles loading: SUCCESS")
            print_success("✅ Roles file loading")

            # Test structure
            required_keys = ["agent_a", "agent_b", "persona_library"]
            for key in required_keys:
                timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
                if key in roles:
                    report.add_test(f"Roles structure: {key}", "PASS", "Key present", provider_info="Roles System")
                    print(f"[{timestamp}] Roles key check: {key} - FOUND")
                    print_success(f"✅ Roles key '{key}'")
                else:
                    report.add_test(f"Roles structure: {key}", "FAIL", "Key missing", provider_info="Roles System")
                    print(f"[{timestamp}] Roles key check: {key} - MISSING")
                    print_error(f"❌ Roles key '{key}' - MISSING")

            # Test persona library
            timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            if "persona_library" in roles and len(roles["persona_library"]) > 0:
                persona_count = len(roles['persona_library'])
                report.add_test("Persona Library", "PASS", f"Found {persona_count} personas", provider_info="Roles System")
                print(f"[{timestamp}] Persona library: {persona_count} personas found")
                print_success(f"✅ Persona library ({persona_count} personas)")
            else:
                report.add_test("Persona Library", "WARN", "No personas found", provider_info="Roles System")
                print(f"[{timestamp}] Persona library: No personas found")
                print_warning("⚠️ No personas in library")

        else:
            timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            report.add_test("Roles File Loading", "FAIL", "Failed to load roles", provider_info="Roles System")
            print(f"[{timestamp}] Roles loading: FAILED")
            print_error("❌ Roles file loading failed")

    except Exception as e:
        timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        report.add_test("Roles System", "FAIL", f"Exception: {str(e)}", provider_info="Roles System")
        print(f"[{timestamp}] Roles system: ERROR - {str(e)}")
        print_error(f"❌ Roles system error: {e}")

    finally:
        # Clean up
        if os.path.exists(test_roles_file):
            os.remove(test_roles_file)


def test_import_functionality(report: CertificationReport):
    """Test that all modules can be imported"""
    print_section_header("Import Tests", "📦")
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    print(f"[{timestamp}] Starting module import tests...")

    modules = [
        ('bridge_agents', 'provider_choices, get_spec, create_agent'),
        ('chat_bridge', 'ping_provider, ConversationHistory, Transcript'),
        ('version', '__version__')
    ]

    for module_name, imports in modules:
        timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        try:
            exec(f"from {module_name} import {imports}")
            report.add_test(f"Import: {module_name}", "PASS", f"Successfully imported {imports}", provider_info="Python Module")
            print(f"[{timestamp}] Module import: {module_name} - SUCCESS")
            print_success(f"✅ {module_name}")
        except Exception as e:
            report.add_test(f"Import: {module_name}", "FAIL", f"Import error: {str(e)}", provider_info="Python Module")
            print(f"[{timestamp}] Module import: {module_name} - FAILED: {str(e)}")
            print_error(f"❌ {module_name} - {e}")


def test_provider_specs(report: CertificationReport):
    """Test all provider specifications are valid"""
    print_section_header("Provider Specification Tests", "⚙️")
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    print(f"[{timestamp}] Starting provider specification tests...")

    try:
        providers = provider_choices()

        for provider_key in providers:
            timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            try:
                spec = get_spec(provider_key)
                provider_info = f"Provider: {spec.label if hasattr(spec, 'label') else provider_key}"

                # Check required attributes
                if all(hasattr(spec, attr) for attr in ['label', 'default_model', 'needs_key']):
                    details = f"Label: {spec.label}, Model: {spec.default_model}, Type: {spec.kind.upper() if hasattr(spec, 'kind') else 'Unknown'}"
                    report.add_test(f"Provider spec: {provider_key}", "PASS", details, provider_info=provider_info)
                    print(f"[{timestamp}] Provider spec: {spec.label} ({provider_key}) - VALID")
                    print_success(f"✅ {spec.label} ({provider_key})")
                else:
                    report.add_test(f"Provider spec: {provider_key}", "FAIL", "Missing required attributes", provider_info=provider_info)
                    print(f"[{timestamp}] Provider spec: {provider_key} - INVALID")
                    print_error(f"❌ {provider_key} - Invalid spec")

            except Exception as e:
                timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
                report.add_test(f"Provider spec: {provider_key}", "FAIL", f"Exception: {str(e)}", provider_info=f"Provider: {provider_key}")
                print(f"[{timestamp}] Provider spec: {provider_key} - ERROR: {str(e)}")
                print_error(f"❌ {provider_key} - {e}")

    except Exception as e:
        timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        report.add_test("Provider Specifications", "FAIL", f"Exception: {str(e)}", provider_info="System Error")
        print(f"[{timestamp}] Provider specifications: ERROR - {str(e)}")
        print_error(f"❌ Provider specs error: {e}")


async def run_certification():
    """Run complete certification suite"""
    start_time = datetime.now()
    timestamp = start_time.strftime("%Y-%m-%d %H:%M:%S")
    
    print_banner()
    print_section_header("Chat Bridge Certification", "🏆")
    print(f"[{timestamp}] Starting comprehensive certification tests...")
    print_info("Running comprehensive tests to certify your installation...")
    print()

    report = CertificationReport()

    # Run all test suites
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    print(f"[{timestamp}] Running test suite: Module imports")
    test_import_functionality(report)
    
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    print(f"[{timestamp}] Running test suite: File structure")
    test_file_structure(report)
    
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    print(f"[{timestamp}] Running test suite: Provider specifications")
    test_provider_specs(report)
    
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    print(f"[{timestamp}] Running test suite: Database operations")
    test_database_operations(report)
    
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    print(f"[{timestamp}] Running test suite: Roles system")
    test_roles_system(report)
    
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    print(f"[{timestamp}] Running test suite: Provider connectivity")
    online_providers = await test_provider_connectivity(report)

    # Generate and display results
    end_time = datetime.now()
    total_duration = (end_time - start_time).total_seconds()
    timestamp = end_time.strftime("%Y-%m-%d %H:%M:%S")
    print(f"\n[{timestamp}] All tests completed in {total_duration:.2f} seconds")
    print()
    report.print_summary()

    # Save detailed report
    report_timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    current_timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    report_file = f"certification_report_{report_timestamp}.json"
    print(f"[{current_timestamp}] Saving detailed certification report...")
    report.save_report(report_file)

    # Additional recommendations
    print()
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    print(f"[{timestamp}] Generating recommendations based on test results...")
    print_section_header("Recommendations", "💡")

    if online_providers == 0:
        print_warning("⚠️ No providers are online. Set up API keys to enable conversations.")
    elif online_providers < 2:
        print_warning("⚠️ Only one provider online. Add more API keys for provider variety.")
    else:
        print_success(f"✅ {online_providers} providers online - Great for diverse conversations!")

    if not os.path.exists('.env'):
        print_warning("⚠️ No .env file found. Create one with your API keys for better security.")

    if not os.path.exists('roles.json'):
        print_info("💡 Consider creating a roles.json file for custom personas.")

    print()
    final_timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    print(f"[{final_timestamp}] Certification process completed")
    print_info("Run the certification anytime with: python certify.py")
    print_info("For detailed troubleshooting, see: docs/TESTING.md")

    return report.get_summary()


if __name__ == "__main__":
    try:
        summary = asyncio.run(run_certification())

        # Exit with appropriate code
        if summary['success_rate'] >= 85 and summary['failed'] <= 3:
            sys.exit(0)  # Full certification
        elif summary['success_rate'] >= 70:
            sys.exit(1)  # Conditional pass
        else:
            sys.exit(2)  # Failed certification

    except KeyboardInterrupt:
        print(f"\n{colorize('👋 Certification interrupted by user', Colors.YELLOW)}")
        sys.exit(3)
    except Exception as e:
        print(f"\n{colorize(f'❌ Certification error: {e}', Colors.RED)}")
        sys.exit(4)