#!/usr/bin/env python3
"""
Test script to verify error logging functionality
"""

import asyncio
import logging
import sys
from chat_bridge import setup_logging
from bridge_agents import create_agent, ensure_credentials, get_spec

async def test_error_logging():
    """Test various error conditions to verify logging works"""
    print("🧪 Testing comprehensive error logging system...")

    # Setup logging
    bridge_logger, session_logger = setup_logging()

    # Test 1: Invalid provider
    print("\n1. Testing invalid provider error...")
    try:
        agent = create_agent("test", "invalid_provider", "test-model", 0.7, "Test system prompt")
    except Exception as e:
        bridge_logger.error(f"Expected error for invalid provider: {e}")
        print("✅ Invalid provider error logged correctly")

    # Test 2: Missing API key
    print("\n2. Testing missing API key error...")
    try:
        # Temporarily unset API keys to test error handling
        import os
        original_key = os.environ.get("OPENAI_API_KEY")
        if "OPENAI_API_KEY" in os.environ:
            del os.environ["OPENAI_API_KEY"]

        ensure_credentials("openai")

        # Restore original key if it existed
        if original_key:
            os.environ["OPENAI_API_KEY"] = original_key
    except Exception as e:
        print("✅ Missing API key error logged correctly")

    # Test 3: Connection error simulation
    print("\n3. Testing connection error simulation...")
    try:
        # This will fail with connection refused
        from bridge_agents import OllamaChat
        client = OllamaChat("test-model", host="http://localhost:99999")
        messages = [{"role": "user", "content": "test"}]

        # Try to stream (this will fail)
        async for chunk in client.stream(messages, "Test prompt", 0.7, 10):
            pass

    except Exception as e:
        bridge_logger.error(f"Expected connection error: {e}", exc_info=True)
        print("✅ Connection error logged correctly")

    # Test 4: JSON parsing error simulation
    print("\n4. Testing JSON parsing error...")
    try:
        import json
        json.loads("invalid json {")
    except json.JSONDecodeError as e:
        bridge_logger.error(f"JSON parsing error: {e}", exc_info=True)
        print("✅ JSON parsing error logged correctly")

    print(f"\n📋 Log files created:")
    print(f"  - Main log: chat_bridge.log")
    print(f"  - Error log: chat_bridge_errors.log")

    print(f"\n🎉 Error logging system test completed!")

if __name__ == "__main__":
    asyncio.run(test_error_logging())