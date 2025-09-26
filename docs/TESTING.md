# 🧪 Chat Bridge Testing & Certification Guide

This document outlines comprehensive testing procedures to ensure Chat Bridge operates correctly across all providers and configurations.

## 🎯 Testing Overview

Chat Bridge testing covers three main areas:
1. **Provider Connectivity** - API connections and authentication
2. **Conversation Flow** - AI-to-AI conversation functionality
3. **System Integration** - Database, logging, and file management

## 🔬 Test Suites

### 1. Provider Connectivity Tests

#### Automated Connectivity Testing
```bash
# Test all providers
python chat_bridge.py
# Choose option 3 (Test Provider Connectivity)
# Choose option 1 (Test all providers)
```

#### Manual Provider Tests
```bash
# Test individual providers
python -c "
import asyncio
from chat_bridge import ping_provider

async def test():
    result = await ping_provider('openai')
    print(f'OpenAI: {result}')

asyncio.run(test())
"
```

#### Expected Results
- ✅ **Online**: API key valid, model accessible with response time
- ❌ **Error**: Detailed error message with troubleshooting steps
- 📊 **Summary**: Provider status overview with recommendations

### 2. Conversation Flow Tests

#### Basic Conversation Test
```bash
# Minimal 2-round conversation
python chat_bridge.py \
  --provider-a anthropic \
  --provider-b openai \
  --starter "Hello, introduce yourself" \
  --max-rounds 2 \
  --mem-rounds 4
```

#### Extended Conversation Test
```bash
# Longer conversation with memory
python chat_bridge.py \
  --provider-a openai \
  --provider-b anthropic \
  --starter "Let's discuss the philosophy of artificial intelligence" \
  --max-rounds 10 \
  --mem-rounds 6
```

#### Temperature Variation Test
```bash
# Test different creativity levels
python chat_bridge.py \
  --provider-a openai \
  --provider-b anthropic \
  --temp-a 0.2 \
  --temp-b 0.9 \
  --starter "Write a creative story together" \
  --max-rounds 6
```

### 3. Roles & Personas Tests

#### Roles Configuration Test
```bash
# Test roles.json loading and persona application
python chat_bridge.py --roles roles.json
# Select personas during interactive setup
```

#### Roles Management Test
```bash
# Test interactive roles management
python chat_bridge.py
# Choose option 2 (Manage Roles & Personas)
# Test creating, editing, and deleting personas
```

### 4. Error Handling Tests

#### Missing API Key Test
```bash
# Test error handling with missing keys
unset OPENAI_API_KEY
python chat_bridge.py --provider-a openai --provider-b anthropic --starter "test"
# Should show clear error message
```

#### Invalid Model Test
```bash
# Test with non-existent model
python chat_bridge.py \
  --provider-a openai \
  --model-a "invalid-model-name" \
  --provider-b anthropic \
  --starter "test" \
  --max-rounds 1
```

#### Network Connectivity Test
```bash
# Test offline behavior (disconnect network)
python chat_bridge.py
# Choose option 3 (Test Provider Connectivity)
# Should show network error messages
```

## 📋 Certification Checklist

### Core Functionality ✅
- [ ] All 5 providers (OpenAI, Anthropic, Gemini, Ollama, LM Studio) connect successfully
- [ ] Conversations flow naturally between agents
- [ ] Streaming output works correctly
- [ ] Conversation termination works (stop words, max rounds)
- [ ] Memory management functions properly
- [ ] Temperature settings affect response style

### User Interface ✅
- [ ] Interactive menus display correctly with colors
- [ ] Provider selection works
- [ ] Persona selection functions
- [ ] Error messages are clear and helpful
- [ ] Progress indicators show during conversations
- [ ] Graceful handling of keyboard interrupts (Ctrl+C)

### Data Persistence ✅
- [ ] Transcripts saved to `transcripts/` directory
- [ ] Session logs created in `logs/` directory
- [ ] Global log `chat_bridge.log` updated
- [ ] SQLite database `bridge.db` populated
- [ ] File naming follows timestamp format

### Error Handling ✅
- [ ] Missing API keys show helpful error messages
- [ ] Invalid models handled gracefully
- [ ] Network timeouts handled properly
- [ ] Rate limiting detected and explained
- [ ] Local server connection failures explained
- [ ] Troubleshooting tips provided for each error type

### Roles System ✅
- [ ] `roles.json` loading works
- [ ] Persona application modifies agent behavior
- [ ] Interactive roles management functions
- [ ] Persona creation wizard works
- [ ] Import/export functionality works
- [ ] Temperature and stop word configuration works

### Provider-Specific Tests ✅

#### OpenAI
- [ ] GPT-4.1 Mini model accessible
- [ ] Streaming responses work
- [ ] Rate limiting handled
- [ ] Custom models work with `OPENAI_MODEL`

#### Anthropic
- [ ] Claude 3.5 Sonnet accessible
- [ ] Messages API streaming works
- [ ] Fallback to completions API if needed
- [ ] Custom models work with `ANTHROPIC_MODEL`

#### Gemini
- [ ] Gemini 1.5 Pro accessible
- [ ] Content format correct (parts array)
- [ ] Rate limiting handled gracefully
- [ ] Custom models work with `GEMINI_MODEL`

#### Ollama
- [ ] Local server detection works
- [ ] Model availability checked
- [ ] Chat API format correct
- [ ] Custom host with `OLLAMA_HOST`

#### LM Studio
- [ ] Local server detection works
- [ ] OpenAI-compatible API works
- [ ] Model loading detection
- [ ] Custom URL with `LMSTUDIO_BASE_URL`

## 🚨 Known Issues & Limitations

### Current Limitations
- Gemini API has strict rate limits on free tier
- Ollama requires manual model installation
- LM Studio requires manual server startup
- Some models may have token limits affecting longer conversations

### Rate Limiting
- OpenAI: 3 RPM on free tier, 3500 RPM on paid
- Anthropic: Varies by tier, usually 5-50 RPM
- Gemini: 15 RPM free tier, 300 RPM with billing
- Local providers: No API limits

## 🔧 Development Testing

### Unit Testing Framework
```bash
# Run basic unit tests
python -m pytest test_chat_bridge.py -v
```

### Integration Testing
```bash
# Test full conversation flow
python test_bridge_agents.py
```

### Performance Testing
```bash
# Test with high memory rounds
python chat_bridge.py \
  --provider-a anthropic \
  --provider-b openai \
  --starter "Let's have a long philosophical discussion" \
  --max-rounds 50 \
  --mem-rounds 20
```

## 📊 Success Criteria

A Chat Bridge installation is considered **certified** when:

1. **All provider tests pass** (at least 3 out of 5 providers online)
2. **Conversations complete successfully** without errors
3. **All data persistence works** (files, database, logs)
4. **Error handling is comprehensive** with helpful messages
5. **Interactive features function** (menus, roles management)
6. **Documentation is accurate** and troubleshooting tips work

## 🏆 Certification Command

Run this comprehensive test to certify your installation:

```bash
# Comprehensive certification test
echo "🧪 Running Chat Bridge Certification Test"
echo "1. Testing provider connectivity..."
python chat_bridge.py << EOF
3
1
4
4
EOF

echo "2. Testing conversation flow..."
python chat_bridge.py \
  --provider-a anthropic \
  --provider-b openai \
  --starter "Please introduce yourselves and discuss AI capabilities" \
  --max-rounds 4

echo "3. Checking file outputs..."
ls -la transcripts/ logs/ bridge.db chat_bridge.log

echo "✅ Certification complete!"
```

## 📞 Support & Troubleshooting

If any tests fail:

1. **Check the troubleshooting section** in README.md
2. **Use the built-in diagnostics** (Provider Connectivity Test)
3. **Review error messages** for specific guidance
4. **Check logs** in `chat_bridge.log` and session logs
5. **Verify API keys and quotas** with providers

Remember: The enhanced error reporting system provides specific troubleshooting steps for each type of failure. Always check the detailed error messages first!