# Laravel 12 Porting Plan: Chat Bridge 🌉🚀

This document outlines the strategy for porting the Python-based Chat Bridge to Laravel 12, leveraging modern PHP features and the Laravel ecosystem.

## 1. Core Architecture Transformation

| Feature | Python (Source) | Laravel 12 (Target) |
|---------|-----------------|----------------------|
| **Language** | Python 3.10+ (Async/Await) | PHP 8.4+ (Fibers/Concurrency) |
| **Framework** | FastAPI / CLI (argparse) | Laravel 12 (Artisan / Controllers) |
| **AI Layer** | `bridge_agents.py` (httpx) | `App\Services\AI` (Saloon/Guzzle) |
| **Real-time** | WebSockets (Python-based) | **Laravel Reverb** (Native WebSockets) |
| **Database** | SQLite (Manual SQL) | Eloquent (SQLite/PostgreSQL) |
| **Frontend** | React + Vite | Inertia.js + React + Tailwind |
| **CLI** | `inquirer` | **Laravel Prompts** |
| **Memory** | MCP (HTTP/FastAPI) | `App\Services\MCP` (Native Service) |

## 2. Database Schema (Eloquent Migrations)

Port the existing schema to Laravel migrations:

- `personas`: Table for `roles.json` content (CRUD via Web/CLI).
- `conversations`: UUID, agent settings, status, metadata.
- `messages`: Relation to conversation, content, usage tokens, persona snapshots.
- `transcripts`: JSON/File storage references.

## 3. AI Service Layer (`App\Services\AI`)

Implement a factory-based provider system:

- **ProviderManager**: Handles driver resolution based on config.
- **Drivers**: `OpenAIDriver`, `AnthropicDriver`, `GeminiDriver`, `OllamaDriver`.
- **Streaming**: Utilize PHP's `stream_get_contents` or Guzzle's `stream` option.
- **Library Recommendation**: Consider using `openai-php/client` and `prism-php` for unified AI access.

## 4. CLI Experience (Artisan commands)

Port `chat_bridge.py` logic to `app/Console/Commands/BridgeChatCommand.php`:

```php
// Example setup using Laravel Prompts
$providerA = select('Select Agent A Provider', ['openai', 'anthropic', 'gemini']);
$personaA = search('Select Persona for Agent A', fn($q) => Persona::search($q)->pluck('name'));
```

The bridge loop will run within the Artisan process, using `Laravel\Concurrency` if needed for parallel tasks.

## 5. Web Interface & Real-time

- **Laravel Reverb**: Replace current WebSocket implementation with Reverb for high-performance, native PHP WebSockets.
- **Inertia.js**: Use Inertia to bridge the React frontend with Laravel, removing the need for a separate API/Frontend boundary for most tasks.
- **Retro UX**: Port Tailwind utilities and particle effects to a dedicated React component library in `resources/js`.

## 6. MCP Memory System

Port the HTTP-based MCP to Laravel's routing:

- `routes/api.php` handles `/api/mcp/*` endpoints.
- Use **Laravel Scout** with a SQLite/Database engine for searching past memories.
- Vector storage: If adding true RAG, use `pgvector` with Laravel's PostgreSQL driver.

## 7. Migration Steps

### Phase 1: Foundation (Week 1)
- Initialize Laravel 12 project.
- Define Migrations and Models.
- Port `roles.json` to `Persona` seeders/database.
- Setup `Laravel Reverb`.

### Phase 2: AI Layer (Week 2)
- Implement `AIService` and Provider drivers.
- Implement streaming response handling.
- Build the `BridgeChatCommand` Artisan command.

### Phase 3: Web GUI (Week 3)
- Setup Inertia + React + Vite.
- Port retro/cyberpunk themes.
- Create real-time conversation views using Reverb.

### Phase 4: Persistence & Memory (Week 4)
- Port Transcript generation logic to PHP.
- Implement MCP endpoints in Laravel.
- Final testing and "Certification" suite port.

## 8. Development Commands

```bash
# Start the bridge loop
php artisan bridge:chat

# Add a new persona
php artisan bridge:persona-add

# View conversation stats
php artisan bridge:stats
```
