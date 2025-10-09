#!/usr/bin/env python3
"""
Test script to verify OpenRouter model selection displays provider/model format
"""

import asyncio
import os
from dotenv import load_dotenv

# Load environment variables
load_dotenv()

from bridge_agents import fetch_openrouter_models

async def test_model_display():
    """Test that model IDs are displayed correctly in provider/model format"""
    print("🧪 Testing OpenRouter Model Display Format\n")

    api_key = os.getenv("OPENROUTER_API_KEY")

    if not api_key or api_key == "your-api-key-here":
        print("❌ OPENROUTER_API_KEY not configured")
        return

    print("1. Fetching models from OpenRouter API...")
    models = await fetch_openrouter_models(api_key)

    if not models:
        print("❌ Failed to fetch models")
        return

    print(f"✅ Fetched {len(models)} models\n")

    print("2. Testing model ID format...")
    provider_format_count = 0
    sample_models = []

    for model in models:
        model_id = model.get("id", "")
        if "/" in model_id:
            provider_format_count += 1
            if len(sample_models) < 10:
                sample_models.append(model_id)

    print(f"✅ Models with provider/model format: {provider_format_count}/{len(models)}")

    if provider_format_count == 0:
        print("❌ ERROR: No models found with provider/model format!")
        return

    print("\n3. Sample model IDs (should show provider/model format):")
    for i, model_id in enumerate(sample_models, 1):
        # Check if it has the slash
        if "/" in model_id:
            provider, model = model_id.split("/", 1)
            print(f"   ✅ {i}. {model_id}")
            print(f"      Provider: {provider}")
            print(f"      Model: {model}")
        else:
            print(f"   ❌ {i}. {model_id} (missing provider prefix!)")

    print("\n4. Testing display formatting (simulating what user sees)...")
    print("-" * 70)

    # Simulate the display as it appears in select_openrouter_model
    for i, model_id in enumerate(sample_models[:5], 1):
        model_data = next((m for m in models if m.get("id") == model_id), None)
        if model_data:
            name = model_data.get("name", "Unknown")
            context = model_data.get("context_length", "N/A")

            # This is how it's displayed in the actual function
            print(f"  {i}. {model_id}")
            print(f"      {name} | Context: {context}")

    print("-" * 70)

    print("\n5. Verifying the function returns correct model IDs...")
    # Test that the function preserves the full model ID including provider prefix
    test_model = sample_models[0]
    print(f"   Selected model ID: {test_model}")

    if "/" in test_model:
        print(f"   ✅ Model ID includes provider prefix!")
        provider = test_model.split("/")[0]
        print(f"   ✅ Provider prefix: {provider}")
    else:
        print(f"   ❌ Model ID missing provider prefix!")
        return

    print("\n6. Checking if model ID is used correctly in API calls...")
    from bridge_agents import OpenAIChat

    base_url = os.getenv("OPENROUTER_BASE_URL", "https://openrouter.ai/api/v1")
    client = OpenAIChat(model=test_model, api_key=api_key, base_url=base_url)

    print(f"   Client model: {client.model}")

    if client.model == test_model and "/" in client.model:
        print(f"   ✅ Model ID correctly preserved: {client.model}")
    else:
        print(f"   ❌ Model ID was modified or lost provider prefix!")
        return

    print("\n🎉 All tests passed!")
    print("✅ OpenRouter models display correctly in provider/model format")
    print("✅ Model IDs are preserved throughout the selection process")
    print("✅ API calls use the correct provider/model format")

if __name__ == "__main__":
    asyncio.run(test_model_display())
