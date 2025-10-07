#!/usr/bin/env python3
"""
Test script for OpenRouter integration
"""

import asyncio
import os
from dotenv import load_dotenv

# Load environment variables
load_dotenv()

from bridge_agents import get_spec, create_agent, ensure_credentials, OpenAIChat

async def test_openrouter():
    """Test OpenRouter provider integration"""
    print("🧪 Testing OpenRouter Integration\n")

    # Test 1: Check provider specification
    print("1. Testing provider specification...")
    try:
        spec = get_spec("openrouter")
        print(f"   ✅ Provider key: {spec.key}")
        print(f"   ✅ Provider label: {spec.label}")
        print(f"   ✅ Provider kind: {spec.kind}")
        print(f"   ✅ Default model: {spec.default_model}")
        print(f"   ✅ Needs key: {spec.needs_key}")
        print(f"   ✅ Key env: {spec.key_env}")
        print(f"   ✅ Model env: {spec.model_env}")
    except Exception as e:
        print(f"   ❌ Failed: {e}")
        return

    # Test 2: Check API key availability
    print("\n2. Testing API key configuration...")
    api_key = os.getenv("OPENROUTER_API_KEY")
    if not api_key or api_key == "your-api-key-here":
        print(f"   ⚠️  OPENROUTER_API_KEY not configured in .env file")
        print(f"   ℹ️  Please set OPENROUTER_API_KEY to test API connectivity")
        print(f"   ℹ️  You can get an API key from: https://openrouter.ai/keys")
        return
    else:
        print(f"   ✅ API key found: {api_key[:10]}...{api_key[-4:]}")

    # Test 3: Create OpenRouter client
    print("\n3. Testing client creation...")
    try:
        base_url = os.getenv("OPENROUTER_BASE_URL", "https://openrouter.ai/api/v1")
        client = OpenAIChat(model=spec.default_model, api_key=api_key, base_url=base_url)
        print(f"   ✅ Client created successfully")
        print(f"   ✅ Model: {spec.default_model}")
        print(f"   ✅ Base URL: {base_url}")
    except Exception as e:
        print(f"   ❌ Failed: {e}")
        return

    # Test 4: Test API connectivity
    print("\n4. Testing API connectivity...")
    try:
        test_messages = [{"role": "user", "content": "Say 'Hello from OpenRouter!' and nothing else."}]
        response_text = ""

        print(f"   📤 Sending test message...")
        async for chunk in client.stream(test_messages, temperature=0.1, max_tokens=20):
            response_text += chunk
            print(f"   📥 Received: {chunk}", end="", flush=True)

        print()
        if response_text.strip():
            print(f"   ✅ API connectivity successful!")
            print(f"   ✅ Full response: {response_text.strip()}")
        else:
            print(f"   ❌ No response received")
    except Exception as e:
        print(f"   ❌ API call failed: {e}")
        return

    # Test 5: Create agent runtime
    print("\n5. Testing agent creation...")
    try:
        agent = create_agent(
            agent_id="test",
            provider_key="openrouter",
            model=spec.default_model,
            temperature=0.7,
            system_prompt="You are a helpful assistant."
        )
        print(f"   ✅ Agent created successfully")
        print(f"   ✅ Agent ID: {agent.agent_id}")
        print(f"   ✅ Provider: {agent.provider_key}")
        print(f"   ✅ Model: {agent.model}")
    except Exception as e:
        print(f"   ❌ Failed: {e}")
        return

    print("\n🎉 All tests passed! OpenRouter integration is working correctly.")
    print("\n📝 You can now use OpenRouter in Chat Bridge:")
    print(f"   - Set OPENROUTER_API_KEY in .env file")
    print(f"   - Use --provider-a openrouter or --provider-b openrouter")
    print(f"   - Available models: https://openrouter.ai/models")

if __name__ == "__main__":
    asyncio.run(test_openrouter())
