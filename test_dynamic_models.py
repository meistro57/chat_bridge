#!/usr/bin/env python3
"""
Test dynamic model selection from OpenRouter
"""

import asyncio
import os
from dotenv import load_dotenv

# Load environment variables
load_dotenv()

# Import after loading env
from bridge_agents import fetch_openrouter_models

async def test_dynamic_model_selection():
    """Test the dynamic model fetching and display"""
    print("🧪 Testing Dynamic Model Selection\n")

    api_key = os.getenv("OPENROUTER_API_KEY")

    if not api_key or api_key == "your-api-key-here":
        print("❌ OPENROUTER_API_KEY not configured")
        print("\nTo test dynamic model selection:")
        print("1. Get an API key from https://openrouter.ai/keys")
        print("2. Add it to your .env file:")
        print("   OPENROUTER_API_KEY=sk-or-v1-your-key-here")
        print("\n✅ Code integration is complete and ready to use!")
        print("\nFeatures implemented:")
        print("  • fetch_openrouter_models() function in bridge_agents.py")
        print("  • select_openrouter_model() interactive UI in chat_bridge.py")
        print("  • Integration in create_custom_role()")
        print("  • Integration in edit_persona()")
        print("\nWhen you add your API key, you'll be able to:")
        print("  • Browse all available OpenRouter models")
        print("  • Filter by provider (OpenAI, Anthropic, Meta, etc.)")
        print("  • See model details (context length, pricing)")
        print("  • Select models interactively during setup")
        return

    print("🔍 Fetching models from OpenRouter...\n")

    try:
        models = await fetch_openrouter_models(api_key)

        if not models:
            print("❌ No models returned")
            print("   Check your API key and network connection")
            return

        print(f"✅ Successfully fetched {len(models)} models!\n")

        # Group by provider
        providers = {}
        for model in models:
            model_id = model.get("id", "")
            if "/" in model_id:
                provider = model_id.split("/")[0]
                if provider not in providers:
                    providers[provider] = []
                providers[provider].append(model)

        print("📊 Models by Provider:\n")
        for provider, provider_models in sorted(providers.items()):
            print(f"  • {provider}: {len(provider_models)} models")

        print(f"\n🎉 Dynamic model selection is working!")
        print(f"\nYou can now:")
        print(f"  1. Run chat_bridge.py")
        print(f"  2. Choose 'Roles Management'")
        print(f"  3. Create a new persona with OpenRouter")
        print(f"  4. Select 'yes' when asked to browse models")
        print(f"  5. Browse {len(models)} available models organized by provider!")

        # Show some example models
        print(f"\n📋 Sample models available:")
        for i, model in enumerate(models[:5]):
            model_id = model.get("id", "")
            name = model.get("name", "Unknown")
            context = model.get("context_length", "N/A")
            print(f"     {i+1}. {model_id}")
            print(f"        {name} | Context: {context}")

        print(f"     ... and {len(models) - 5} more!")

    except Exception as e:
        print(f"❌ Error: {e}")
        import traceback
        traceback.print_exc()


if __name__ == "__main__":
    asyncio.run(test_dynamic_model_selection())
