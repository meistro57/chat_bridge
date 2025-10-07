# OpenRouter Dynamic Model Selection

## Overview

Your Chat Bridge now includes **dynamic model selection** for OpenRouter! Instead of manually typing model IDs, you can browse and select from 200+ available models interactively.

## What This Means

✅ **Before**: You had to know the exact model ID (e.g., `anthropic/claude-3.5-sonnet`)
✅ **After**: Browse models by provider, see pricing, and select interactively

## How It Works

### 1. Automatic Model Discovery

The system fetches available models from OpenRouter's API based on your API key:
- Only shows models you have access to
- Updates in real-time
- Includes pricing and context length info

### 2. Smart Organization

Models are organized by provider prefix:
- `openai/*` - OpenAI models (GPT-4, GPT-4o-mini, O1, etc.)
- `anthropic/*` - Anthropic models (Claude 3.5 Sonnet, Opus, etc.)
- `meta-llama/*` - Meta's Llama models
- `google/*` - Google Gemini models
- `mistralai/*` - Mistral models
- `deepseek/*` - DeepSeek models
- ...and many more!

### 3. Interactive Selection

When creating or editing OpenRouter personas:
```
Would you like to browse available OpenRouter models? (y/n)
> y

Fetching available OpenRouter models...
Found 247 available models

OpenRouter Model Selection
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Select a provider category:
  1. openai (12 models)
  2. anthropic (8 models)
  3. meta-llama (15 models)
  4. google (6 models)
  ...
  0. Enter model ID manually

Select provider category (0-25): 1

openai Models
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

  1. openai/gpt-4o-mini
     GPT-4o Mini | Context: 128000 | $0.15/1M tokens

  2. openai/gpt-4-turbo
     GPT-4 Turbo | Context: 128000 | $10.00/1M tokens

  3. openai/o1-preview
     O1 Preview | Context: 128000 | $15.00/1M tokens

  ...
  0. Enter model ID manually

Select model (0-12): 1

✓ Selected: openai/gpt-4o-mini
```

## Technical Details

### Files Modified

1. **bridge_agents.py** (line 584-611)
   - Added `fetch_openrouter_models()` function
   - Fetches models from `https://openrouter.ai/api/v1/models`
   - Returns model list with pricing and metadata

2. **chat_bridge.py** (line 213-304)
   - Added `select_openrouter_model()` function
   - Interactive UI for browsing and selecting models
   - Groups models by provider, shows pricing info

3. **chat_bridge.py** (line 369-387)
   - Integrated into `create_custom_role()`
   - Offers model browser when creating OpenRouter personas

4. **chat_bridge.py** (line 1011-1029)
   - Integrated into `edit_persona()`
   - Offers model browser when editing OpenRouter personas

### API Integration

The system uses OpenRouter's models API:
- Endpoint: `GET https://openrouter.ai/api/v1/models`
- Authentication: Bearer token (your API key)
- Returns: Array of model objects with:
  - `id`: Model identifier (e.g., "openai/gpt-4o-mini")
  - `name`: Human-readable name
  - `context_length`: Maximum context window
  - `pricing.prompt`: Cost per prompt token
  - `pricing.completion`: Cost per completion token

### Access Control

OpenRouter uses a credit-based system:
- All models are accessible with valid API key
- Access is controlled by account credits, not permissions
- Models will fail at runtime if insufficient credits
- No special access control needed for model listing

## Usage Examples

### Example 1: Create New Persona with Model Browser

```bash
python chat_bridge.py
# Select "Roles Management"
# Select "Create new persona"
# Enter name: "philosopher"
# Select provider: openrouter
# Browse models? yes
# Select provider category: anthropic
# Select model: anthropic/claude-3.5-sonnet
# ... continue with system prompt and guidelines
```

### Example 2: Change Model for Existing Persona

```bash
python chat_bridge.py
# Select "Roles Management"
# Select "View/Edit personas"
# Select persona to edit
# Select "Change model"
# Browse models? yes
# Select provider category: meta-llama
# Select model: meta-llama/llama-3.1-70b-instruct
```

### Example 3: Test Dynamic Model Fetching

```bash
python test_dynamic_models.py
# Shows available models and statistics
```

## Benefits

1. **No Memorization Required**: Don't need to remember exact model IDs
2. **Real-Time Pricing**: See costs before selecting
3. **Context Awareness**: See context lengths to choose appropriate model
4. **Provider Organization**: Easy to find models by company
5. **Always Up-to-Date**: Automatically shows latest available models

## Fallback Behavior

If API key is not configured or fetch fails:
- Falls back to manual model entry
- Shows helpful error messages
- Default model is still available
- No disruption to workflow

## Future Enhancements

Possible future improvements:
- Filter models by price range
- Filter by context length
- Search models by keyword
- Show model capabilities/features
- Cache model list for faster access
- Show model performance metrics

## Testing

Test the integration:

```bash
# Test basic integration
python test_openrouter.py

# Test dynamic model fetching
python test_dynamic_models.py

# Test OpenRouter models API directly
python test_openrouter_models.py
```

## Troubleshooting

**Models not loading?**
- Check `OPENROUTER_API_KEY` is set in `.env`
- Verify API key is valid (starts with `sk-or-v1-`)
- Check network connectivity
- Try manual model entry as fallback

**Wrong models shown?**
- Models shown are based on OpenRouter's current offerings
- Some models may require minimum credits
- Check https://openrouter.ai/models for full list

**Selection not working?**
- Ensure you're selecting a number from the list
- Option `0` allows manual entry
- Press Ctrl+C to cancel at any time

## More Information

- OpenRouter Models: https://openrouter.ai/models
- OpenRouter API Docs: https://openrouter.ai/docs
- OpenRouter Pricing: https://openrouter.ai/models (see individual model pages)
- Get API Key: https://openrouter.ai/keys
