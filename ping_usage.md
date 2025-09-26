# 🌐 Provider Connectivity Testing

The Chat Bridge now includes comprehensive provider connectivity testing to help diagnose connection issues and verify API keys before starting conversations.

## Features

### 📊 Main Menu Integration
Access from the main menu: **"Test Provider Connectivity"**

### 🔍 Testing Options

1. **Test All Providers** - Comprehensive test of all available providers
2. **Test Specific Provider** - Detailed testing of individual providers
3. **Show Detailed Diagnostics** - System configuration and environment variables

## What Gets Tested

### For Each Provider:
- ✅ **API Key Validity** (OpenAI, Anthropic, Gemini)
- ✅ **Model Accessibility** - Tests if the default model is available
- ✅ **Response Time** - Measures latency in milliseconds
- ✅ **Connection Status** - Verifies network connectivity
- ✅ **Service Availability** - Checks if local services are running (Ollama, LM Studio)

### Detailed Results Include:
- 🟢 **Online**: Provider is fully functional
- 🔴 **Error**: Issues with API keys, models, or connectivity
- ⚪ **Other**: Unsupported or not configured

## Sample Output

```
🌐 PROVIDER CONNECTIVITY TEST
──────────────────────────────────────────────────────────

Testing OpenAI...
  ✅ API key valid, model accessible (245ms)

Testing Anthropic...
  ❌ Invalid API key

📊 PROVIDER STATUS SUMMARY
──────────────────────────────────────────────────────────
Overall Status: 1/2 providers online

🟢 ONLINE PROVIDERS:
  • OpenAI (gpt-4.1-mini) - 245ms

🔴 PROVIDERS WITH ISSUES:
  • Anthropic: ❌ Invalid API key

💡 RECOMMENDATIONS:
  • Some providers are unavailable
  • Check your API keys and network connectivity
  • Consider using available providers for your conversations
```

## Diagnostic Information

The detailed diagnostics show:
- **Environment Variables**: Masked API keys and configuration
- **Provider Specifications**: Default models and requirements
- **API Key Status**: Which keys are set vs missing

## Troubleshooting Common Issues

### 🔑 API Key Problems
- Ensure environment variables are set: `OPENAI_API_KEY`, `ANTHROPIC_API_KEY`, etc.
- Check `.env` file if using one
- Verify API keys are valid and have sufficient quota

### 🌐 Network Issues
- Check internet connectivity
- Verify firewall settings
- For local services (Ollama/LM Studio), ensure they're running

### 🖥️ Local Services
- **Ollama**: Start with `ollama serve`
- **LM Studio**: Ensure local server is running on correct port
- Check `LMSTUDIO_BASE_URL` environment variable

## Usage Examples

```bash
# Interactive mode with ping access
python chat_bridge.py

# Direct conversation (ping available from main menu)
python chat_bridge.py --provider-a openai --provider-b anthropic
```

This ping utility helps ensure smooth conversations by identifying and resolving provider issues before they interrupt your AI discussions!