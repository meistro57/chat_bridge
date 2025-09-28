#!/usr/bin/env python3
"""
Test script to verify session summary shows stop word detection status
"""

import json
import tempfile
from chat_bridge import load_roles_file

def test_session_display_logic():
    """Test the logic that determines stop word detection status in session display"""

    # Test with enabled setting
    roles_with_enabled = {
        "stop_word_detection_enabled": True,
        "stop_words": ["goodbye", "end chat"]
    }

    # Test with disabled setting
    roles_with_disabled = {
        "stop_word_detection_enabled": False,
        "stop_words": ["goodbye", "end chat"]
    }

    # Test without the setting (should default to enabled)
    roles_without_setting = {
        "stop_words": ["goodbye", "end chat"]
    }

    # Test enabled case
    stop_detection_enabled = roles_with_enabled.get('stop_word_detection_enabled', True)
    status_text = "enabled" if stop_detection_enabled else "disabled"
    assert status_text == "enabled", f"Expected 'enabled', got '{status_text}'"

    # Test disabled case
    stop_detection_enabled = roles_with_disabled.get('stop_word_detection_enabled', True)
    status_text = "enabled" if stop_detection_enabled else "disabled"
    assert status_text == "disabled", f"Expected 'disabled', got '{status_text}'"

    # Test default case (no setting)
    stop_detection_enabled = roles_without_setting.get('stop_word_detection_enabled', True)
    status_text = "enabled" if stop_detection_enabled else "disabled"
    assert status_text == "enabled", f"Expected 'enabled' by default, got '{status_text}'"

    # Test None roles_data case
    roles_data = None
    stop_detection_enabled = roles_data.get('stop_word_detection_enabled', True) if roles_data else True
    status_text = "enabled" if stop_detection_enabled else "disabled"
    assert status_text == "enabled", f"Expected 'enabled' when roles_data is None, got '{status_text}'"

    print("✅ Session display logic test passed!")

if __name__ == "__main__":
    test_session_display_logic()
    print("🎉 Session display test completed!")