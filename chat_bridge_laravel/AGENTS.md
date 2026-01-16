# Agent Guide - Chat Bridge (Laravel)

This repository is a Laravel 12 application using Inertia.js with React 19 and Tailwind CSS 4. It's designed to facilitate conversations between different AI personas using various LLM drivers.

## 🛠 Essential Commands

### Environment Setup
- `composer run setup`: Full local environment setup (installs dependencies, migrates database, builds assets).
- `cp .env.example .env && php artisan key:generate`: Manual environment initialization.

### Development
- `composer run dev`: Starts all necessary services concurrently (Serve, Vite, Queue, Reverb, Pail).
- `php artisan serve`: Start local PHP server.
- `php artisan queue:listen`: Start queue worker (crucial for AI chat sessions).
- `php artisan reverb:start`: Start WebSocket server for real-time streaming to the UI.
- `npm run dev`: Start Vite development server.

### Testing & Quality
- `composer run test`: Clear config and run PHPUnit tests.
- `php artisan test --compact`: Run tests with compact output.
- `vendor/bin/pint`: Format code using Laravel Pint.
- `php artisan ai:test {provider}`: Test connectivity to a specific AI provider (e.g., `openai`, `anthropic`).

## 🏗 Project Architecture

### AI Service Layer (`app/Services/AI`)
- **`AIManager`**: Handles creation of AI drivers using the Laravel Manager pattern.
- **`Drivers/`**: Individual implementations for LLM providers (OpenAI, Anthropic, Gemini, Ollama, etc.).
- **`Contracts/AIDriverInterface`**: Defines the interface for all drivers (`chat`, `streamChat`).
- **`EmbeddingService`**: Handles generation of vector embeddings for messages.

### Conversation Logic
- **`RunChatSession` Job**: A long-running queued job that manages the turn-taking loop between two AI personas.
- **`ConversationService`**: Domain logic for generating turns, saving messages, and finalizing conversations.
- **`StopWordService`**: Utility to detect when a conversation should naturally terminate.

### Frontend (`resources/js`)
- **React 19 + Inertia v2**: Using `.jsx` components in `Pages/`.
- **WebSocket Streaming**: Uses Laravel Echo with Reverb to stream AI responses in real-time.
- **Tailwind CSS 4**: CSS-first configuration in `app.css` under themed sections (`matrix`, `retro`, `cyber`).

## 📏 Conventions

### Backend (PHP 8.4+)
- **TypeScript-like Strictness**: Use explicit return types and parameter types.
- **Constructor Promotion**: Use PHP 8 constructor property promotion.
- **Artisan First**: Use `php artisan make:...` for all boilerplate generation.
- **Form Requests**: Use dedicated Request classes for validation.
- **Pint**: Run `vendor/bin/pint --dirty` before every commit.

### Models
- Use the `casts()` method instead of the `$casts` property.
- Implement relationship methods with return type hints.

### Frontend (React/Inertia)
- Components live in `resources/js/Pages`.
- Use the `<Link>` and `router` from `@inertiajs/react` for navigation.
- Real-time events are listened to in `useEffect` hooks via `window.Echo`.

## ⚠️ Gotchas & Non-Obvious Patterns

- **Queue is Essential**: AI conversations are handled in the background. If `php artisan queue:listen` isn't running, conversations will never progress.
- **Real-time Streaming**: The UI relies on `Reverb`. Ensure `REVERB_APP_KEY`, etc., are configured in `.env`.
- **Database Migrations**: When modifying columns in Laravel 12, redefine all attributes or they will be lost.
- **Inertia v2 Features**: The project is ready for deferred props and lazy loading; prefer these for complex data sets.
- **AI Drivers**: Drivers generally expect certain keys in `config/services.php` (e.g., `services.openai.key`).

## 📡 Events & Broadcasting
- `MessageChunkSent`: Broadcasts small parts of text during generation.
- `MessageCompleted`: Broadcasts the final message object once persisted.
- Channels: Private channels follow the `conversation.{id}` pattern.
