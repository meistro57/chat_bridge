#!/usr/bin/env python3
"""
Test the GUI backend functionality
"""

import sys
import asyncio
from pathlib import Path

# Add project root to path
sys.path.insert(0, str(Path(__file__).parent))

from web_gui.backend.main import PersonaManager, ConversationRequest, Conversation

def test_persona_loading():
    """Test that personas load correctly"""
    print("🔍 Testing persona loading...")
    
    pm = PersonaManager()
    personas = pm.load_personas_from_config()
    
    print(f"✅ Loaded {len(personas)} personas")
    
    # Check some key personas
    interesting_personas = ['scientist', 'philosopher', 'comedian', 'steel_worker', 'ADHD_Kid']
    for p in interesting_personas:
        if p in personas:
            print(f"  ✅ {p}: provider={personas[p].provider}, system_preview={personas[p].system_prompt[:50]}")
    
    return len(personas) > 0

def test_persona_availability():
    """Test which personas are available based on credentials"""
    print("\n🔍 Testing persona availability...")
    
    pm = PersonaManager()
    pm.persona_library = pm.load_personas_from_config()
    
    available = pm.get_available_personas()
    print(f"✅ {len(available)} personas available (credentials OK)")
    
    for key, persona in list(available.items())[:5]:
        print(f"  ✅ {key}: {persona['name']}")
    
    return len(available) > 0

async def test_conversation_initialization():
    """Test conversation object initialization"""
    print("\n🔍 Testing conversation initialization...")
    
    # Create a test conversation request
    request = ConversationRequest(
        provider_a="openai",
        provider_b="anthropic",
        persona_a="scientist",
        persona_b="philosopher",
        starter_message="Hello, let's discuss AI consciousness",
        max_rounds=5,
        temperature_a=0.7,
        temperature_b=0.7
    )
    
    try:
        conversation = Conversation(request)
        conversation.initialize_agents()
        print("✅ Conversation initialized successfully")
        print(f"  Agent A: {request.provider_a} ({request.persona_a})")
        print(f"  Agent B: {request.provider_b} ({request.persona_b})")
        print(f"  Max rounds: {request.max_rounds}")
        return True
    except Exception as e:
        print(f"❌ Failed to initialize conversation: {e}")
        return False

def test_api_endpoints():
    """Test basic API endpoint functionality"""
    print("\n🔍 Testing API endpoints...")
    
    from web_gui.backend.main import app, get_providers, get_personas
    
    # Test providers endpoint
    providers_response = asyncio.run(get_providers())
    providers = providers_response["providers"]
    print(f"✅ Providers endpoint: {len(providers)} providers available")
    for p in providers[:3]:
        print(f"  {p['key']}: {p['label']}")
    
    # Test personas endpoint
    personas_response = asyncio.run(get_personas())
    personas = personas_response["personas"]
    print(f"✅ Personas endpoint: {len(personas)} personas returned")
    
    return len(providers) > 0 and len(personas) > 0

def main():
    """Run all tests"""
    print("🌉 Chat Bridge GUI Backend Test Suite")
    print("=" * 50)
    
    tests_passed = 0
    total_tests = 4
    
    # Test 1: Persona loading
    if test_persona_loading():
        tests_passed += 1
    
    # Test 2: Persona availability
    if test_persona_availability():
        tests_passed += 1
    
    # Test 3: Conversation initialization
    if asyncio.run(test_conversation_initialization()):
        tests_passed += 1
    
    # Test 4: API endpoints
    if test_api_endpoints():
        tests_passed += 1
    
    print("=" * 50)
    print(f"📊 Test Results: {tests_passed}/{total_tests} tests passed")
    
    if tests_passed == total_tests:
        print("✅ All tests passed! GUI backend should work correctly.")
    else:
        print("❌ Some tests failed. Check the output for details.")

if __name__ == "__main__":
    main()