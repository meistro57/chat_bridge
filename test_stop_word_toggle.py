#!/usr/bin/env python3
"""
Test script for stop word detection toggle functionality
"""

import json
import os
import tempfile
from chat_bridge import load_roles_file, save_roles_file

def test_stop_word_toggle():
    """Test the stop word detection toggle functionality"""

    # Create a temporary roles file
    test_roles = {
        "agent_a": {
            "provider": "openai",
            "model": None,
            "system": "Test agent A",
            "guidelines": []
        },
        "agent_b": {
            "provider": "anthropic",
            "model": None,
            "system": "Test agent B",
            "guidelines": []
        },
        "persona_library": {},
        "stop_words": ["goodbye", "end chat", "terminate"],
        "stop_word_detection_enabled": True,
        "temp_a": 0.6,
        "temp_b": 0.7
    }

    # Create temporary file
    with tempfile.NamedTemporaryFile(mode='w', suffix='.json', delete=False) as f:
        json.dump(test_roles, f, indent=2)
        temp_path = f.name

    try:
        # Test loading the configuration
        loaded_roles = load_roles_file(temp_path)
        assert loaded_roles is not None, "Failed to load roles file"
        assert loaded_roles['stop_word_detection_enabled'] == True, "Stop word detection should be enabled by default"

        # Test toggling to disabled
        loaded_roles['stop_word_detection_enabled'] = False
        assert save_roles_file(loaded_roles, temp_path), "Failed to save roles file"

        # Verify the toggle was saved
        reloaded_roles = load_roles_file(temp_path)
        assert reloaded_roles['stop_word_detection_enabled'] == False, "Stop word detection should be disabled after toggle"

        # Test toggling back to enabled
        reloaded_roles['stop_word_detection_enabled'] = True
        assert save_roles_file(reloaded_roles, temp_path), "Failed to save roles file again"

        # Final verification
        final_roles = load_roles_file(temp_path)
        assert final_roles['stop_word_detection_enabled'] == True, "Stop word detection should be enabled after second toggle"

        print("✅ Stop word detection toggle functionality test passed!")

    finally:
        # Clean up temporary file
        os.unlink(temp_path)

def test_backward_compatibility():
    """Test that configurations without the toggle still work"""

    # Create a roles file without the stop_word_detection_enabled field
    old_roles = {
        "agent_a": {
            "provider": "openai",
            "model": None,
            "system": "Test agent A",
            "guidelines": []
        },
        "agent_b": {
            "provider": "anthropic",
            "model": None,
            "system": "Test agent B",
            "guidelines": []
        },
        "persona_library": {},
        "stop_words": ["goodbye", "end chat", "terminate"],
        "temp_a": 0.6,
        "temp_b": 0.7
    }

    # Create temporary file
    with tempfile.NamedTemporaryFile(mode='w', suffix='.json', delete=False) as f:
        json.dump(old_roles, f, indent=2)
        temp_path = f.name

    try:
        # Test loading old configuration
        loaded_roles = load_roles_file(temp_path)
        assert loaded_roles is not None, "Failed to load old roles file"

        # Test that default value is used when key is missing
        stop_detection_enabled = loaded_roles.get('stop_word_detection_enabled', True)
        assert stop_detection_enabled == True, "Should default to enabled when key is missing"

        print("✅ Backward compatibility test passed!")

    finally:
        # Clean up temporary file
        os.unlink(temp_path)

if __name__ == "__main__":
    test_stop_word_toggle()
    test_backward_compatibility()
    print("🎉 All tests passed!")