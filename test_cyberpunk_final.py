#!/usr/bin/env python3
"""Cyberpunk GUI Readiness Test"""
import asyncio
import os
import subprocess
import sys
from pathlib import Path

async def test_openrouter():
    """Test OpenRouter integration"""
    print("🔗 Testing OpenRouter...")
    try:
        sys.path.insert(0, str(Path(__file__).parent.resolve()))
        from bridge_agents import create_agent, Turn

        agent = create_agent(
            "test", "openrouter", "openai/gpt-4o-mini",
            0.6, "You are a test assistant. Respond with 'Hello from OpenRouter'."
        )

        turns = [Turn(author="user", text="Say hello.")]
        response = []
        async for chunk in agent.stream_reply(turns, 10):
            response.append(chunk)

        if response:
            print("✅ OpenRouter working")
            return True
        else:
            print("❌ No response from OpenRouter")
            return False
    except Exception as e:
        print(f"❌ OpenRouter failed: {e}")
        return False

def check_services():
    """Check running services"""
    print("🖥️ Checking servers...")

    backend = False
    frontend = False

    try:
        result = subprocess.run(['lsof', '-i', ':8000'], capture_output=True, text=True, timeout=5)
        if 'python' in result.stdout.lower():
            backend = True
            print("✅ Backend running (port 8000)")
    except:
        print("⚠️ Could not check backend port")

    try:
        result = subprocess.run(['lsof', '-i', ':5173'], capture_output=True, text=True, timeout=5)
        if result.returncode == 0:
            frontend = True
            print("✅ Frontend running (port 5173)")
    except:
        print("⚠️ Could not check frontend port")

    return backend, frontend

async def main():
    print("🌈 CYBERPUNK CHAT BRIDGE GUI TEST")
    print("=" * 40)

    # Check environment
    env_ok = bool(os.getenv('OPENROUTER_API_KEY'))
    print(f"🔑 API Keys: {'✅' if env_ok else '❌'}")

    # Check services
    backend_ok, frontend_ok = check_services()

    # Test OpenRouter
    openrouter_ok = await test_openrouter()

    print("\n📈 RESULTS:")
    print("-" * 20)
    print(f"Environment: {'✅' if env_ok else '❌'}")
    print(f"Backend: {'✅' if backend_ok else '❌'}")
    print(f"Frontend: {'✅' if frontend_ok else '❌'}")
    print(f"OpenRouter: {'✅' if openrouter_ok else '❌'}")

    all_ready = env_ok and backend_ok and frontend_ok and openrouter_ok

    if all_ready:
        print("\n🎉 CYBERPUNK GUI IS READY!")
        print("🌐 Open http://localhost:5173")
        return 0
    else:
        print("\n⚠️ Issues found. Check status above.")
        return 1

if __name__ == "__main__":
    exit(asyncio.run(main()))