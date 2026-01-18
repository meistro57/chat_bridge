# ChatBridge Neuron AI Integration

This directory contains the integration of Neuron AI framework into ChatBridge.

## Overview

The integration provides a dedicated API endpoint for AI agents to converse with context (history) stored in a `chat_bridge_threads` table.

### Key Components

- **Agent**: `App\Neuron\Agents\ChatBridgeAgent` (wraps Neuron AI Agent)
- **Store**: `App\Services\ChatBridge\HistoryStore` (Manages DB persistence)
- **Models**: `ChatBridgeThread`, `ChatBridgeMessage`
- **Controller**: `App\Http\Controllers\Api\ChatBridgeController`

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
   OPENAI_MODEL=gpt-4o-mini

   # API Security
   CHAT_BRIDGE_TOKEN=your-secret-token-here
   ```

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
  "persona": "You are a pirate."
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

### Testing

Run the feature tests:
```bash
php artisan test tests/Feature/ChatBridgeApiTest.php
```

## Extending

- **Providers**: Edit `config/neuron.php` and `ChatBridgeAgent::provider()` to add more providers (Anthropic, etc).
- **Tools**: Add tools to `ChatBridgeAgent::tools()` method (requires Neuron AI Tool support).
