import asyncio
import sys
import os
from dotenv import load_dotenv
load_dotenv()
sys.path.append('.')
from bridge_agents import OllamaChat

async def test_ollama():
    """Test Ollama connection using the bridge_agents module"""
    chat = OllamaChat('llama3.2:latest')
    messages = [{'role': 'user', 'content': 'Why is the sky blue?'}]
    
    print("Testing Ollama connection...")
    try:
        async for chunk in chat.stream(messages, 'You are a helpful assistant.'):
            print(chunk, end='', flush=True)
        print("\n\nTest completed successfully!")
    except Exception as e:
        print(f"Test failed: {e}")

if __name__ == "__main__":
    asyncio.run(test_ollama())
