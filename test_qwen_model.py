#!/usr/bin/env python3
"""
Test script to verify qwen/qwen3-max model works with OpenRouter
"""

import asyncio
import os
from dotenv import load_dotenv

# Load environment variables
load_dotenv()

from bridge_agents import OpenAIChat

async def test_qwen_model():
    """Test the specific qwen/qwen3-max model"""
    print("🧪 Testing qwen/qwen3-max Model\n")

    api_key = os.getenv("OPENROUTER_API_KEY")

    if not api_key or api_key == "your-api-key-here":
        print("❌ OPENROUTER_API_KEY not configured")
        return

    base_url = os.getenv("OPENROUTER_BASE_URL", "https://openrouter.ai/api/v1")
    model = "qwen/qwen3-max"

    print(f"1. Creating client for model: {model}")
    print(f"   Base URL: {base_url}")
    print(f"   API Key: {api_key[:10]}...{api_key[-4:]}\n")

    client = OpenAIChat(model=model, api_key=api_key, base_url=base_url)

    print(f"2. Client created successfully")
    print(f"   Model: {client.model}")
    print(f"   URL: {client.url}\n")

    print("3. Sending test message...")
    test_messages = [{"role": "user", "content": "Say 'Hello from Qwen!' and nothing else."}]

    try:
        response_text = ""
        print("   Streaming response: ", end="", flush=True)

        async for chunk in client.stream(test_messages, temperature=0.1, max_tokens=20):
            response_text += chunk
            print(chunk, end="", flush=True)

        print(f"\n\n✅ Success! Full response: {response_text.strip()}")

    except Exception as e:
        print(f"\n\n❌ Error: {e}")
        print(f"\nError details:")
        print(f"   Type: {type(e).__name__}")
        print(f"   Message: {str(e)}")

        # Check if it's an HTTP error
        if hasattr(e, 'response'):
            print(f"   Status code: {e.response.status_code}")
            print(f"   Response text: {e.response.text[:500]}")

        return False

    return True

if __name__ == "__main__":
    success = asyncio.run(test_qwen_model())
    if success:
        print("\n🎉 Model works correctly!")
    else:
        print("\n❌ Model test failed")
