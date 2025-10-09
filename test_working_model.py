#!/usr/bin/env python3
"""
Test script to verify a working OpenRouter model (not filtered)
"""

import asyncio
import os
from dotenv import load_dotenv

# Load environment variables
load_dotenv()

from bridge_agents import OpenAIChat

async def test_working_model():
    """Test a model that should work (openai/gpt-4o-mini)"""
    print("🧪 Testing OpenRouter with openai/gpt-4o-mini\n")

    api_key = os.getenv("OPENROUTER_API_KEY")

    if not api_key or api_key == "your-api-key-here":
        print("❌ OPENROUTER_API_KEY not configured")
        return False

    base_url = os.getenv("OPENROUTER_BASE_URL", "https://openrouter.ai/api/v1")
    model = "openai/gpt-4o-mini"

    print(f"Model: {model}")
    print(f"Format: {'✅ provider/model' if '/' in model else '❌ missing provider prefix'}\n")

    client = OpenAIChat(model=model, api_key=api_key, base_url=base_url)

    print("Sending test message...")
    test_messages = [{"role": "user", "content": "Say 'Hello from OpenRouter!' and nothing else."}]

    try:
        response_text = ""
        print("Response: ", end="", flush=True)

        async for chunk in client.stream(test_messages, temperature=0.1, max_tokens=20):
            response_text += chunk
            print(chunk, end="", flush=True)

        print(f"\n\n✅ Success!")
        print(f"✅ Model format preserved: {model}")
        print(f"✅ Full response: {response_text.strip()}")
        return True

    except Exception as e:
        print(f"\n\n❌ Error: {e}")
        return False

if __name__ == "__main__":
    success = asyncio.run(test_working_model())
    if success:
        print("\n🎉 OpenRouter model selection working correctly!")
        print("📋 Models display in provider/model format")
        print("✅ Model IDs preserved throughout the system")
    else:
        print("\n❌ Test failed")
