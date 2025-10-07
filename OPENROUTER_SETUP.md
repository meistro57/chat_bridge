# OpenRouter Integration Guide

OpenRouter.ai is now integrated into Chat Bridge! OpenRouter provides unified access to multiple AI models through a single API.

## ✨ NEW: Dynamic Model Selection

Chat Bridge now supports **interactive model browsing** for OpenRouter! When you have an OpenRouter API key configured, you can:

- Browse 200+ available models organized by provider
- See real-time pricing and context length information
- Filter models by provider (OpenAI, Anthropic, Meta, Google, etc.)
- Select models interactively during persona/role creation

No need to remember model IDs - just browse and select!

## Setup

### 1. Get an API Key
Visit https://openrouter.ai/keys to create an account and get your API key.

### 2. Configure Environment
Add your OpenRouter API key to the `.env` file:

```bash
# OpenRouter API Configuration
OPENROUTER_API_KEY=sk-or-v1-your-api-key-here
OPENROUTER_MODEL=openai/gpt-4o-mini
# OPENROUTER_BASE_URL=https://openrouter.ai/api/v1  # Optional, uses default if not set
```

### 3. Test the Integration
Run the test script to verify everything is working:

```bash
python test_openrouter.py
```

## Usage

### Interactive Model Selection (Recommended)

When creating or editing personas/roles with OpenRouter:

1. Run Chat Bridge: `python chat_bridge.py`
2. Select "Roles Management" from the main menu
3. Choose "Create new persona" or edit an existing OpenRouter persona
4. Select "openrouter" as the provider
5. When asked "Would you like to browse available OpenRouter models?", choose **yes**
6. Browse models by provider category:
   - Select a provider (e.g., "openai", "anthropic", "meta-llama")
   - View model details including pricing and context length
   - Select your desired model

Example model browser output:
```
📋 openai Models

  1. openai/gpt-4o-mini
     GPT-4o Mini | Context: 128000 | $0.15/1M tokens

  2. openai/gpt-4-turbo
     GPT-4 Turbo | Context: 128000 | $10.00/1M tokens

  3. openai/o1-preview
     O1 Preview | Context: 128000 | $15.00/1M tokens
```

### Command Line

Use OpenRouter as either agent A or agent B:

```bash
# Use OpenRouter as agent A
python chat_bridge.py --provider-a openrouter --provider-b anthropic

# Use OpenRouter as agent B
python chat_bridge.py --provider-a openai --provider-b openrouter

# Use OpenRouter for both agents with different models
python chat_bridge.py --provider-a openrouter --provider-b openrouter \
  --model-a openai/gpt-4o-mini --model-b anthropic/claude-3.5-sonnet
```

### Interactive Menu

When running Chat Bridge interactively, OpenRouter will appear in the provider selection menu:

```bash
python chat_bridge.py
```

Then select OpenRouter from the available providers.

## Available Models

OpenRouter provides access to many models. Popular options include:

- **OpenAI**: `openai/gpt-4o-mini`, `openai/gpt-4-turbo`, `openai/o1-preview`
- **Anthropic**: `anthropic/claude-3.5-sonnet`, `anthropic/claude-3-opus`
- **Meta**: `meta-llama/llama-3.1-70b-instruct`, `meta-llama/llama-3.1-405b`
- **Google**: `google/gemini-2.0-flash-exp`, `google/gemini-pro-1.5`
- **Mistral**: `mistralai/mistral-large`, `mistralai/mixtral-8x22b`
- **DeepSeek**: `deepseek/deepseek-chat`, `deepseek/deepseek-coder`

View all available models at: https://openrouter.ai/models

## Benefits

1. **Model Variety**: Access to 100+ models from various providers
2. **Single API**: One API key for all models
3. **Cost Optimization**: Compare and choose models based on cost
4. **Fallback Options**: Switch models easily if one is unavailable
5. **Latest Models**: Access to newest models as they're released

## Pricing

OpenRouter charges per-token based on the model used. Check current pricing at:
https://openrouter.ai/models

## Provider Ping Test

Test OpenRouter connectivity using the provider ping menu:

```bash
python chat_bridge.py
# Select "Provider Ping" from the menu
# Select "OpenRouter" to test connectivity
```

## Troubleshooting

### API Key Issues
- Ensure `OPENROUTER_API_KEY` is set in your `.env` file
- Verify the key starts with `sk-or-v1-`
- Check your account has sufficient credits at https://openrouter.ai/credits

### Model Access
- Some models may require additional permissions
- Check model availability at https://openrouter.ai/models
- Ensure your model name format is correct (e.g., `provider/model-name`)

### Rate Limits
- OpenRouter has per-minute rate limits
- Consider adding delays between requests
- Check your usage at https://openrouter.ai/activity

## Example Session

```bash
# Start a chat with OpenRouter and Anthropic
python chat_bridge.py --provider-a openrouter --provider-b anthropic \
  --model-a openai/gpt-4o-mini --starter "What is quantum computing?"

# The conversation will alternate between OpenRouter and Anthropic
```

## Advanced Configuration

### Custom Base URL
If you're using a proxy or custom endpoint:

```bash
OPENROUTER_BASE_URL=https://your-proxy.com/api/v1
```

### Model-Specific Settings
You can configure different models for different agents in your roles configuration.

## Support

- OpenRouter Docs: https://openrouter.ai/docs
- OpenRouter Discord: https://discord.gg/openrouter
- Chat Bridge Issues: https://github.com/yourusername/chat_bridge/issues
