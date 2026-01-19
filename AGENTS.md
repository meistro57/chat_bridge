# Agent Guide for Chat Bridge

This repository contains **Chat Bridge**, an application for facilitating automated conversations between two AI personas.

## Project Overview

- **Goal**: Run automated "Chat Sessions" between two AI agents (defined as Personas).
- **Stack**: Laravel 12 (PHP 8.2+), Inertia.js (React), TailwindCSS v4, SQLite.
- **AI Integration**: Custom driver-based architecture supporting OpenAI, Anthropic, DeepSeek, Ollama, etc.

## UI Design System: "Midnight Glass"

The application features a custom implementation of a modern, dark-mode aesthetic.

- **Theme**: Deep Zinc (`zinc-950`) background with ambient radial gradients.
- **Glassmorphism**: Panels use translucent backgrounds (`bg-white/5`), backdrop blurs, and subtle white borders.
- **Colors**:
  - **Indigo/Violet**: Primary actions and brand accents.
  - **Emerald**: Success states and active indicators.
  - **Red**: Destructive actions (stops/deletes).
- **Typography**: Uses modern system sans-serif fonts with tight tracking for headers and monospaced accents.

## Technical Architecture

### 1. The Manager Pattern (AI Drivers)
All AI interactions go through `App\Services\AI\AIManager`.
- **Interface**: `App\Services\AI\Contracts\AIDriverInterface`
- **Drivers**: located in `App\Services\AI\Drivers/`
- **Configuration**: `config/services.php` (for keys) and `config/ai.php` (if exists).

**Adding a new AI Provider**:
1. Create a driver class implementing `AIDriverInterface`.
2. Add the driver creation method to `AIManager`.
3. Add credentials to `config/services.php` and `.env`.

### 2. The Job Queue (Async Chat)
Chat generations are **asynchronous**.
- **Controller**: `ChatController::store` creates the `Conversation` and dispatches...
- **Job**: `App\Jobs\RunChatSession` handles the actual loop.
- **Effect**: You cannot get the full conversation immediately after creation. You must poll or check the database.
- **Stop Signal**: Conversations run until completion or a manual stop signal via Cache (`conversation.stop.{id}`).

### 3. Frontend (Inertia + React)
- **Pages**: `resources/js/Pages/`
- **Routing**: `routes/web.php` maps directly to Inertia renders.
- **Real-time**: Uses Laravel Echo / Reverb for live message updates.

## Key Models

- **Persona**: Represents an AI identity (System Prompt, Model, Provider).
- **Conversation**: A session between two Personas (A and B).
- **Message**: Individual chat bubbles in the conversation.

## Development Commands

### Setup
```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
```

### Running Locally
**Recommended: Use the start script**
This script handles port selection and starts all services (App, Reverb, Queue).
```bash
./start-services.sh
```

**Or run services manually:**
```bash
# Start backend
php artisan serve

# Start frontend (hot reload)
npm run dev

# Start queue (CRITICAL for chat processing)
php artisan queue:listen

# Start WebSocket server (for real-time updates)
php artisan reverb:start
```

### Testing
```bash
# Run PHPUnit / Pest tests
php artisan test
```

## Common Tasks for Agents

**1. Creating a Migration**
Laravel standard:
```bash
php artisan make:migration create_table_name
```
After defining schema, run `php artisan migrate`.

**2. Adding a Route**
- **API**: `routes/api.php`
- **Web/UI**: `routes/web.php` (most common for Inertia apps)

**3. Debugging AI Calls**
- Check `storage/logs/laravel.log`.
- Verify `.env` keys for the specific provider.
- Mock drivers are available if keys are missing (`MockDriver`).

## Gotchas

- **Queue Worker Required**: If you create a chat and nothing happens, the queue worker is likely not running. Run `php artisan queue:work` or `queue:listen`.
- **Environment config**: Tailwind v4 is used (configured in `vite.config.js` and CSS), so `tailwind.config.js` might be missing or minimal.
- **Sqlite**: Default database is SQLite (`database/database.sqlite`).
- **Docker Builds**: Ensure `composer.lock` is up-to-date. If you encounter `Class 'Laravel\Pail\PailServiceProvider' not found`, run `composer update` to sync the lock file.
