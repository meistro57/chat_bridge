#!/usr/bin/env python3
"""
End-to-end test for cyberpunk GUI with OpenRouter
"""
import asyncio
import json
import subprocess
import sys
import time
from pathlib import Path

async def test_backend_api():
    """Test backend API endpoints"""
    print("🔧 Testing backend API...")
    try:
        # Import for testing
        sys.path.insert(0, str(Path(__file__).parent.parent.parent.resolve()))

        from fastapi.testclient import TestClient
        from web_gui.backend.main import app

        client = TestClient(app)

        # Test personas endpoint
        response = client.get("/api/personas")
        if response.status_code != 200:
            print(f"❌ Personas endpoint failed: {response.status_code}")
            return False

        personas_data = response.json()
        personas = personas_data.get('personas', [])
        print(f"✅ Personas endpoint working - loaded {len(personas)} personas")

        # Check if we have the expected personas
        persona_names = {p['id'] for p in personas}
        expected = {'philosopher', 'scientist', 'comedian', 'steel_worker', 'deepseek', 'adhd_kid', 'complainer'}
        if expected.issubset(persona_names):
            print("✅ All expected personas found")
        else:
            print(f"⚠️ Missing personas: {expected - persona_names}")

        # Test providers endpoint
        try:
            response = client.get("/api/providers")
            if response.status_code == 200:
                print("✅ Providers endpoint working")
            else:
                print(f"⚠️ Providers endpoint returned {response.status_code}")
        except Exception as e:
            print(f"⚠️ Providers endpoint test failed: {e}")

        return True

    except Exception as e:
        print(f"❌ Backend API test failed: {e}")
        return False

async def test_openrouter_integration():
    """Test OpenRouter provider integration"""
    print("🔗 Testing OpenRouter integration...")
    try:
        from chat_bridge.bridge_agents import create_agent

        # Test creating agent
        agent = create_agent(
            agent_id="test_gui_agent",
            provider_key="openrouter",
            model="openai/gpt-4o-mini",
            temperature=0.6,
            system_prompt="You are a helpful test assistant. Keep responses brief."
        )

        # Test generating a response
        from chat_bridge.bridge_agents import Turn
        turns = [Turn(author="user", text="Say hello in exactly 3 words.")]
        response_chunks = []

        async for chunk in agent.stream_reply(turns, 10):
            response_chunks.append(chunk)

        full_response = "".join(response_chunks).strip()
        if len(full_response.split()) <= 5:  # Allow some variation
            print(f"✅ OpenRouter agent responded: '{full_response}'")
            return True
        else:
            print(f"❌ Unexpected response length: '{full_response}'")
            return False

    except Exception as e:
        print(f"❌ OpenRouter integration test failed: {e}")
        return False

async def main():
    """Main test function"""
    print("🌐 Cyberpunk GUI End-to-End Test")
    print("=" * 50)

    # Test 1: Backend API
    backend_ok = await test_backend_api()
    if not backend_ok:
        print("❌ Critical: Backend API issues")
        return

    # Test 2: OpenRouter integration
    openrouter_ok = await test_openrouter_integration()
    if not openrouter_ok:
        print("❌ Critical: OpenRouter integration issues")
        return

    print("\n🎉 All tests passed!")
    print("\n🚀 Cyberpunk GUI should be ready:")
    print("   • Backend API: http://localhost:8000")
    print("   • Frontend GUI: http://localhost:5173")
    print("   • OpenRouter provider: ✅ configured and tested")
    print("\n🧭 GUI Testing Steps:")
    print("   1. Open http://localhost:5173 in browser")
    print("   2. Should see cyberpunk 'AI Nexus' landing page")
    print("   3. Select personas for both agents")
    print("   4. Choose 'OpenRouter' as provider for both")
    print("   5. Set max rounds, temperatures")
    print("   6. Enter a starter message")
    print("   7. Click ⚡ to start conversation")
    print("   8. Watch real-time AI chat with streaming")

if __name__ == "__main__":
    asyncio.run(main())