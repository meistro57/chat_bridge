# ChatBridge Neuron AI Integration

This directory contains the integration of Neuron AI framework into ChatBridge.

## Overview

The integration provides a dedicated API endpoint for AI agents to converse with context (history) stored in a `chat_bridge_threads` table.

### Key Components

- **Agent**: `App\Neuron\Agents\ChatBridgeAgent` (wraps Neuron AI Agent)
- **Store**: `App\Services\ChatBridge\HistoryStore` (manages DB persistence)
- **Models**: `ChatBridgeThread`, `ChatBridgeMessage`
- **Controller**: `App\Http\Controllers\Api\ChatBridgeController`
- **Middleware**: `App\Http\Middleware\EnsureChatBridgeToken` (token gate)

## Setup

1. **Install Dependencies**
   ```bash
   composer require neuron-core/neuron-ai
   ```

2. **Database**
   Run migrations to create the new tables:
   ```bash
   php artisan migrate
   ```

3. **Environment**
   Add these keys to your `.env`:
   ```dotenv
   # Neuron Provider
   NEURON_PROVIDER=openai
   OPENAI_API_KEY=sk-...
   OPENAI_MODEL=gpt-3.5-turbo

   # API Security
   CHAT_BRIDGE_TOKEN=your-secret-token-here
   ```

> ℹ️ The `X-CHAT-BRIDGE-TOKEN` header is always required. If `CHAT_BRIDGE_TOKEN` is set, the header must match it. If it is unset, any non-empty header is accepted (useful for local/dev).

## Usage

### API Endpoint

**POST** `/api/chat-bridge/respond`

**Headers**:
- `Content-Type: application/json`
- `Accept: application/json`
- `X-CHAT-BRIDGE-TOKEN: <your-secret-token>`

**Payload**:
```json
{
  "bridge_thread_id": "thread-101",
  "message": "Hello, who are you?",
  "persona": "You are a pirate.",
  "metadata": {
    "source": "widget",
    "request_id": "req-123"
  }
}
```

**Response**:
```json
{
  "bridge_thread_id": "thread-101",
  "assistant_message": "Ahoy matey! I be Captain ChatBridge!",
  "thread_db_id": 1
}
```

### Validation Rules

- `bridge_thread_id`: required, string, max 255 chars.
- `message`: required, string, max 20,000 chars.
- `persona`: optional, string, max 5,000 chars.
- `metadata`: optional, array (persisted with the user message).

### Example cURL

```bash
curl -X POST http://localhost:8000/api/chat-bridge/respond \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -H 'X-CHAT-BRIDGE-TOKEN: your-secret-token-here' \
  -d '{
    "bridge_thread_id": "thread-101",
    "message": "Hello, who are you?",
    "persona": "You are a pirate."
  }'
```

### Testing

Run the feature tests:
```bash
php artisan test tests/Feature/ChatBridgeApiTest.php
```

## Extending

- **Providers**: Edit `config/neuron.php` and `ChatBridgeAgent::provider()` to add more providers (Anthropic, etc).
- **Tools**: Add tools to `ChatBridgeAgent::tools()` method (requires Neuron AI Tool support).
