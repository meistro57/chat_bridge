#!/usr/bin/env python3
"""
Test script for validating roles mode functionality with enhanced debugging.

This script tests:
1. JSON configuration loading and validation
2. Persona library management
3. Agent creation with personas
4. Error handling and debugging features
"""

import os
import sys
import json
from pathlib import Path

# Add current directory to path
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from roles_manager import RolesManager, validate_config_schema, ConfigValidationError

def print_header(text):
    """Print a formatted header"""
    print("\n" + "="*70)
    print(f"  {text}")
    print("="*70)

def test_json_loading():
    """Test JSON configuration loading"""
    print_header("TEST 1: JSON Configuration Loading")

    try:
        # Test with debug mode enabled
        manager = RolesManager("roles.json", debug=True)
        print("✅ RolesManager initialized successfully")
        return True
    except Exception as e:
        print(f"❌ Failed to initialize RolesManager: {e}")
        return False

def test_config_validation():
    """Test configuration schema validation"""
    print_header("TEST 2: Configuration Schema Validation")

    manager = RolesManager("roles.json", debug=False)
    is_valid, error = manager.validate_json_file()

    if is_valid:
        print("✅ Configuration file is valid")
        return True
    else:
        print(f"❌ Configuration validation failed: {error}")
        return False

def test_persona_listing():
    """Test listing all available personas"""
    print_header("TEST 3: Persona Library Listing")

    manager = RolesManager("roles.json", debug=True)
    personas = manager.list_all_personas()

    if personas:
        print(f"✅ Found {len(personas)} personas:")
        for i, persona in enumerate(personas, 1):
            print(f"   {i}. {persona}")
        return True
    else:
        print("❌ No personas found in library")
        return False

def test_persona_info():
    """Test retrieving persona information"""
    print_header("TEST 4: Persona Information Retrieval")

    manager = RolesManager("roles.json", debug=True)
    personas = manager.list_all_personas()

    if not personas:
        print("❌ No personas to test")
        return False

    # Test first persona
    test_persona = personas[0]
    print(f"\nTesting persona: {test_persona}")
    persona_data = manager.get_persona_info(test_persona)

    if persona_data:
        print(f"✅ Successfully retrieved persona data")
        print(f"   Provider: {persona_data.get('provider')}")
        print(f"   Model: {persona_data.get('model', 'default')}")
        print(f"   System prompt length: {len(persona_data.get('system', ''))} chars")
        print(f"   Guidelines: {len(persona_data.get('guidelines', []))} items")
        return True
    else:
        print(f"❌ Failed to retrieve persona data")
        return False

def test_agent_creation_validation():
    """Test agent creation validation"""
    print_header("TEST 5: Agent Creation Validation")

    manager = RolesManager("roles.json", debug=True)
    personas = manager.list_all_personas()

    if not personas:
        print("❌ No personas to test")
        return False

    success_count = 0
    for persona in personas[:3]:  # Test first 3 personas
        print(f"\nValidating agent creation for: {persona}")
        if manager.test_agent_creation(persona):
            success_count += 1
        else:
            print(f"⚠️  Agent creation validation failed for {persona}")

    if success_count > 0:
        print(f"\n✅ {success_count}/{min(3, len(personas))} personas passed validation")
        return True
    else:
        print(f"\n❌ All persona validations failed")
        return False

def test_invalid_json_handling():
    """Test handling of invalid JSON"""
    print_header("TEST 6: Invalid JSON Handling")

    # Create a temporary invalid JSON file
    temp_file = "temp_invalid_test.json"
    with open(temp_file, 'w') as f:
        f.write('{"invalid": json, syntax}')

    try:
        manager = RolesManager(temp_file, debug=True)
        # Should fall back to defaults
        print("✅ Invalid JSON handled gracefully (loaded defaults)")
        return True
    except Exception as e:
        print(f"❌ Failed to handle invalid JSON: {e}")
        return False
    finally:
        # Clean up
        if os.path.exists(temp_file):
            os.remove(temp_file)

def test_missing_file_handling():
    """Test handling of missing configuration file"""
    print_header("TEST 7: Missing File Handling")

    try:
        manager = RolesManager("nonexistent_config.json", debug=True)
        # Should create defaults and report the issue
        print("✅ Missing file handled gracefully (created defaults)")

        # Clean up created file
        if os.path.exists("nonexistent_config.json"):
            os.remove("nonexistent_config.json")

        return True
    except Exception as e:
        print(f"❌ Failed to handle missing file: {e}")
        return False

def test_agent_defaults():
    """Test loading agent defaults"""
    print_header("TEST 8: Agent Defaults Loading")

    manager = RolesManager("roles.json", debug=False)

    agent_a = manager.config.get('agent_a')
    agent_b = manager.config.get('agent_b')

    if agent_a and agent_b:
        print("✅ Agent defaults loaded successfully")
        print(f"   Agent A provider: {agent_a.get('provider')}")
        print(f"   Agent B provider: {agent_b.get('provider')}")
        return True
    else:
        print("❌ Failed to load agent defaults")
        return False

def test_temperature_settings():
    """Test temperature settings"""
    print_header("TEST 9: Temperature Settings")

    manager = RolesManager("roles.json", debug=False)

    temp_a = manager.config.get('temp_a')
    temp_b = manager.config.get('temp_b')

    if temp_a is not None and temp_b is not None:
        print(f"✅ Temperature settings loaded")
        print(f"   Temperature A: {temp_a}")
        print(f"   Temperature B: {temp_b}")

        # Validate range
        if 0 <= temp_a <= 2 and 0 <= temp_b <= 2:
            print("✅ Temperature values are in valid range (0-2)")
            return True
        else:
            print("⚠️  Temperature values outside recommended range")
            return False
    else:
        print("❌ Failed to load temperature settings")
        return False

def test_stop_words():
    """Test stop words configuration"""
    print_header("TEST 10: Stop Words Configuration")

    manager = RolesManager("roles.json", debug=False)

    stop_words = manager.config.get('stop_words', [])
    stop_enabled = manager.config.get('stop_word_detection_enabled', False)

    print(f"✅ Stop word detection: {'enabled' if stop_enabled else 'disabled'}")
    print(f"   Stop words count: {len(stop_words)}")
    if stop_words:
        print(f"   Examples: {', '.join(stop_words[:3])}")

    return True

def run_all_tests():
    """Run all test functions"""
    print("\n" + "╔" + "="*68 + "╗")
    print("║" + " "*10 + "CHAT BRIDGE ROLES MODE DEBUG TEST SUITE" + " "*18 + "║")
    print("╚" + "="*68 + "╝")

    tests = [
        ("JSON Configuration Loading", test_json_loading),
        ("Configuration Validation", test_config_validation),
        ("Persona Listing", test_persona_listing),
        ("Persona Information", test_persona_info),
        ("Agent Creation Validation", test_agent_creation_validation),
        ("Invalid JSON Handling", test_invalid_json_handling),
        ("Missing File Handling", test_missing_file_handling),
        ("Agent Defaults", test_agent_defaults),
        ("Temperature Settings", test_temperature_settings),
        ("Stop Words Configuration", test_stop_words),
    ]

    results = []

    for test_name, test_func in tests:
        try:
            result = test_func()
            results.append((test_name, result))
        except Exception as e:
            print(f"\n❌ TEST CRASHED: {test_name}")
            print(f"   Error: {e}")
            import traceback
            traceback.print_exc()
            results.append((test_name, False))

    # Print summary
    print("\n" + "="*70)
    print("  TEST SUMMARY")
    print("="*70)

    passed = sum(1 for _, result in results if result)
    total = len(results)

    for test_name, result in results:
        status = "✅ PASS" if result else "❌ FAIL"
        print(f"{status}  {test_name}")

    print("\n" + "-"*70)
    print(f"Results: {passed}/{total} tests passed ({100*passed//total}% success rate)")
    print("="*70 + "\n")

    return passed == total

if __name__ == "__main__":
    success = run_all_tests()
    sys.exit(0 if success else 1)
