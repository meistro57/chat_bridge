#!/usr/bin/env python3
"""
Demo: Interactive OpenRouter Model Selection
This demonstrates the model browsing feature
"""

import asyncio
import os
from dotenv import load_dotenv

load_dotenv()

# Minimal imports to demo the feature
from bridge_agents import fetch_openrouter_models

async def demo():
    """Demo the model selection UI"""
    api_key = os.getenv("OPENROUTER_API_KEY")

    if not api_key or api_key == "your-api-key-here":
        print("❌ Please set OPENROUTER_API_KEY in .env")
        return

    print("🎬 OpenRouter Model Selection Demo\n")
    print("=" * 60)

    # Step 1: Fetch models
    print("\n📡 Step 1: Fetching available models from OpenRouter API...")
    models = await fetch_openrouter_models(api_key)
    print(f"✅ Retrieved {len(models)} models")

    # Step 2: Group by provider
    print("\n📊 Step 2: Organizing models by provider...")
    providers = {}
    for model in models:
        model_id = model.get("id", "")
        if "/" in model_id:
            provider = model_id.split("/")[0]
            if provider not in providers:
                providers[provider] = []
            providers[provider].append(model)

    print(f"✅ Found {len(providers)} providers")

    # Step 3: Show popular providers
    print("\n🌟 Step 3: Popular Providers (with model counts)")
    print("-" * 60)

    popular = ["openai", "anthropic", "google", "meta-llama", "mistralai", "deepseek", "x-ai"]
    for provider in popular:
        if provider in providers:
            count = len(providers[provider])
            print(f"  • {provider:20} {count:3} models")

    # Step 4: Show example models from different providers
    print("\n🎯 Step 4: Example Models Available")
    print("-" * 60)

    examples = [
        ("OpenAI GPT-4o-mini", "openai/gpt-4o-mini"),
        ("Anthropic Claude 4.5", "anthropic/claude-sonnet-4.5"),
        ("Google Gemini", "google/gemini-2.0-flash-exp"),
        ("Meta Llama 3.3", "meta-llama/llama-3.3-70b-instruct"),
        ("Mistral Large", "mistralai/mistral-large"),
        ("DeepSeek V3.2", "deepseek/deepseek-v3.2-exp"),
        ("X.AI Grok 2", "x-ai/grok-2-vision"),
    ]

    for name, model_id in examples:
        # Find the model in our list
        model_data = next((m for m in models if m.get("id") == model_id), None)
        if model_data:
            context = model_data.get("context_length", "N/A")
            pricing = model_data.get("pricing", {})
            prompt_cost = pricing.get("prompt", "N/A")

            if isinstance(prompt_cost, str) and prompt_cost != "N/A":
                try:
                    price = float(prompt_cost) * 1_000_000
                    price_str = f"${price:.2f}/1M"
                except:
                    price_str = "N/A"
            else:
                price_str = "N/A"

            print(f"\n  {name}")
            print(f"    ID: {model_id}")
            print(f"    Context: {context:,} tokens")
            print(f"    Price: {price_str} prompt tokens")

    # Step 5: Usage info
    print("\n" + "=" * 60)
    print("\n💡 To use interactive model selection in Chat Bridge:")
    print("  1. Run: python chat_bridge.py")
    print("  2. Select 'Roles Management'")
    print("  3. Select 'Create new persona'")
    print("  4. Choose 'openrouter' as provider")
    print("  5. Say 'yes' to browse models")
    print("  6. Select from 324 models across 53+ providers!")
    print("\n✨ No need to memorize model IDs - just browse and select!\n")

if __name__ == "__main__":
    asyncio.run(demo())
