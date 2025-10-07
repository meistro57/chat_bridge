#!/usr/bin/env python3
"""
Test script to fetch available models from OpenRouter API
"""

import asyncio
import json
import os
from dotenv import load_dotenv
import httpx

# Load environment variables
load_dotenv()

async def fetch_openrouter_models():
    """Fetch available models from OpenRouter API"""
    api_key = os.getenv("OPENROUTER_API_KEY")

    if not api_key or api_key == "your-api-key-here":
        print("❌ OPENROUTER_API_KEY not configured")
        print("ℹ️  Set your API key in .env to test model fetching")
        return None

    print("🔍 Fetching available models from OpenRouter...\n")

    # Try the models endpoint
    url = "https://openrouter.ai/api/v1/models"
    headers = {
        "Authorization": f"Bearer {api_key}",
        "Content-Type": "application/json"
    }

    try:
        async with httpx.AsyncClient(timeout=30.0) as client:
            print(f"📡 GET {url}")
            response = await client.get(url, headers=headers)

            print(f"📥 Status: {response.status_code}")

            if response.status_code == 200:
                data = response.json()

                # Check if we have models data
                if "data" in data:
                    models = data["data"]
                    print(f"✅ Found {len(models)} models\n")

                    # Show first 10 models as examples
                    print("📋 Sample models:")
                    for i, model in enumerate(models[:10]):
                        model_id = model.get("id", "unknown")
                        name = model.get("name", "Unknown")
                        context_length = model.get("context_length", "N/A")
                        pricing = model.get("pricing", {})
                        prompt_price = pricing.get("prompt", "N/A")

                        print(f"\n  {i+1}. {model_id}")
                        print(f"     Name: {name}")
                        print(f"     Context: {context_length}")
                        print(f"     Price (prompt): ${prompt_price} per token")

                    print(f"\n... and {len(models) - 10} more models")

                    # Save full list to file
                    with open("openrouter_models.json", "w") as f:
                        json.dump(data, f, indent=2)
                    print(f"\n💾 Full model list saved to: openrouter_models.json")

                    return models
                else:
                    print("⚠️  Unexpected response format")
                    print(json.dumps(data, indent=2))
            else:
                print(f"❌ Request failed: {response.status_code}")
                print(f"Response: {response.text}")

    except Exception as e:
        print(f"❌ Error: {e}")

    return None

async def test_model_access():
    """Test if we can determine which models are accessible"""
    api_key = os.getenv("OPENROUTER_API_KEY")

    if not api_key or api_key == "your-api-key-here":
        print("\n⚠️  Cannot test model access without API key")
        return

    print("\n\n🔐 Testing model access detection...")
    print("ℹ️  OpenRouter allows access to all models by default")
    print("ℹ️  Access is controlled by credits, not permissions")
    print("ℹ️  Models will fail at runtime if insufficient credits\n")

if __name__ == "__main__":
    asyncio.run(fetch_openrouter_models())
    asyncio.run(test_model_access())
