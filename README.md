<div align="center">

# 🤖 Chat Bridge

### _The Ultimate AI Conversation Orchestration Platform_
### --The results of when a Steel Fabricator gets his hands on AI and finds Github.--
https://quantummindsunited.com/

[![Tests](https://github.com/meistro57/chat_bridge/actions/workflows/laravel.yml/badge.svg)](https://github.com/meistro57/chat_bridge/actions/workflows/laravel.yml)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![React](https://img.shields.io/badge/React-18-61DAFB?logo=react&logoColor=black)](https://react.dev)
[![Tailwind](https://img.shields.io/badge/Tailwind-v3-38BDF8?logo=tailwind-css&logoColor=white)](https://tailwindcss.com)
[![Docker](https://img.shields.io/badge/Docker-Ready-2496ED?logo=docker&logoColor=white)](https://www.docker.com/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)
[![Made with Love](https://img.shields.io/badge/Made%20with-%E2%9D%A4%EF%B8%8F-red)](https://github.com/meistro57/chat_bridge)

**Current Version:** 0.8.0

**Orchestrate intelligent conversations between AI agents. Test, experiment, and explore multi-agent AI systems with enterprise-grade tooling.**

[Features](#-features) • [Installation](#-installation) • [Docker Setup](#-docker-deployment) • [Documentation](#-documentation) • [Contributing](#-contributing)

---

</div>

## 🌟 What is Chat Bridge?

Chat Bridge is a **production-ready AI conversation orchestration platform** that enables you to:

- 🎭 **Create AI Personas** with custom behaviors, system prompts, and parameters
- 💬 **Orchestrate Conversations** between different AI agents in real-time
- 📊 **Analyze Interactions** with advanced analytics and full conversation history
- 🔐 **Manage Credentials** securely with encrypted API key storage
- 🧠 **Leverage RAG** for context-aware conversations with persistent memory
- 🐛 **Debug Everything** with built-in Telescope and Debugbar integration
- 🎨 **Enjoy Dark Mode** with our stunning "Midnight Glass" UI design

Perfect for **AI researchers, developers, and enthusiasts** who want to experiment with multi-agent systems, test AI behaviors, generate synthetic training data, or simply explore the fascinating world of AI-to-AI conversations.

---

## 🎉 🚨 **INTRODUCING: AUTONOMOUS SELF-HEALING** 🚨 🎉

<div align="center">

### 🤖 **Meet Codex: Your Application's AI Guardian** 🤖

</div>

Chat Bridge features a **groundbreaking autonomous AI agent** called **Codex** that can diagnose, analyze, and provide actionable insights for your entire Laravel application—**automatically**.

<table>
<tr>
<td width="50%" valign="top">

### 🧠 **What is Codex?**

Codex is an **AI-powered diagnostic agent** that understands your Laravel application at a deep level. It's not just a chatbot—it's a **self-aware system analyst** that can:

- 🔍 **Analyze database performance** - Find N+1 queries, missing indexes, slow queries
- 🐛 **Debug recent errors** - Identify patterns, root causes, and suggest fixes
- 🔒 **Security audits** - Check for vulnerabilities, exposed keys, CSRF issues
- ⚡ **Performance analysis** - Identify bottlenecks, memory issues, cache problems
- 🧪 **Test coverage** - Analyze tests and suggest improvements
- ✨ **Code quality** - Review Laravel best practices and SOLID principles
- 📦 **Dependency audits** - Find outdated packages and security issues
- 🗄️ **Migration reviews** - Check indexing, constraints, and optimizations

</td>
<td width="50%" valign="top">

### ⚡ **Autonomous Capabilities**

**Codex isn't just reactive—it's proactive:**

```
🤖 Codex detects a slow query
   ↓
🔍 Analyzes execution plan
   ↓
📊 Checks table indexes
   ↓
💡 Identifies missing index
   ↓
📝 Suggests migration code
   ↓
✅ Provides implementation guide
```

**All autonomously, in seconds.**

</td>
</tr>
</table>

### 🎯 **10 Predefined Superpowers** (Skills sourced from [Superpowers Laravel](https://github.com/jpcaparas/superpowers-laravel))

Choose from powerful preset actions or write your own custom prompts:

<table>
<tr>
<td width="33%">

**🏥 System Health Analysis**
Complete diagnostic sweep with recommendations

**🐛 Debug Recent Errors**
Pattern analysis & root cause identification

**⚡ Database Query Analysis**
N+1 detection & optimization suggestions

**🔒 Security Audit**
Vulnerability scanning & remediation

</td>
<td width="33%">

**🧪 Test Coverage Analysis**
Identify gaps & suggest tests

**✨ Code Quality Review**
Best practices & refactoring opportunities

**🚀 Performance Analysis**
Bottleneck detection & optimization

</td>
<td width="34%">

**📚 API Documentation**
Auto-generate comprehensive docs

**📦 Dependency Audit**
Security & compatibility checks

**🗄️ Migration Review**
Schema optimization analysis

</td>
</tr>
</table>

### 🎪 **How It Works**

1. Navigate to `/admin/system`
2. Select a **Quick Action** from the dropdown (or write your own prompt)
3. Click **"Invoke Codex"**
4. Watch Codex **autonomously analyze** your application using:
    - 📝 **Log analysis** - Pattern recognition in errors
    - ⚙️ **Configuration checks** - Setting validation
    - 🧠 **Codex CLI** - Non-interactive runs via `codex exec` with your service key

**Codex uses Codex CLI with local context (system info, error extracts, log tail) to provide actionable insights.**

---

<div align="center">

### 💫 **This is the future of application maintenance** 💫

**No more manual debugging sessions. No more guessing. Just ask Codex.**

🎉 **Chat Bridge: The first self-aware, self-diagnosing Laravel application** 🎉

</div>

---

## ✨ Features at a Glance

<table>
<tr>
<td width="50%">

### 🎭 **Persona System**

Create reusable AI persona templates with:

- 🔧 Custom system prompts & guidelines
- 🪄 **Built-in AI Persona Creator Bot** popout on the Persona create screen
  - Uses your **OpenAI service API key** (not per-user API key records)
  - Auto-generates and auto-fills both **Persona Name** and **System Prompt**
- 🌡️ Default temperature controls (0.0-2.0)
- 🔄 **Provider/model-agnostic design**
- 👥 Shared library - 56 pre-configured personas
- 📝 Creator attribution tracking
- ✏️ Full CRUD operations
- 🎯 Reusable across any AI provider

</td>
<td width="50%">

### 💬 **Conversation Engine**

Orchestrate AI discussions with:

- ⚡ Real-time streaming via WebSockets
- 🧾 Floating live event log panel on session view (chunks, status, completion, errors)
- 🔄 Automated multi-turn dialogues (configurable max rounds)
- 🎯 Manual stop/resume controls
- 🛑 **Smart stop-word detection with thresholds**
- 🤖 **Per-conversation provider/model selection**
- 💰 **Live pricing display for 344+ models**
- 📡 Live status broadcasting
- 🔔 Optional Discord broadcast per conversation (webhook + thread support)
- 💾 Complete conversation history
- 📥 Transcript export (CSV)

</td>
</tr>
<tr>
<td width="50%">

### 🔐 **Security & Auth**

Enterprise-grade protection:

- 🔒 Encrypted API key storage
- 👤 Role-based access (User/Admin)
- 🔑 Per-user credential isolation
- ✅ Real-time API key validation
- 🛡️ CSRF & XSS protection
- 🔐 Password hashing (bcrypt)

</td>
<td width="50%">

### 🧠 **RAG Intelligence**

Contextual AI with memory:

- 🗄️ Qdrant vector database
- 🔍 Semantic message search
- 💭 Persistent conversation memory
- ⚡ Sub-10ms retrieval times
- 🎯 Context-aware responses
- 📊 Automatic embeddings

</td>
<td width="50%">

### 🤖 **AI Chatbot**

Ask questions about your transcripts:

- 💬 Natural-language Q&A over chat history
- 🧠 Semantic retrieval via Qdrant embeddings
- 🔑 Uses your stored OpenAI API key
- ⚙️ Per-session settings (model, temperature, prompt, score threshold)
- 📎 Source attribution with similarity scores
- 🟢 Dashboard badge shows API key status

</td>
</tr>
<tr>
<td width="50%">

### 🛠️ **MCP Tool Calling**

AI personas with superpowers:

- 🔍 **search_conversations** - Find past messages by keyword
- 🧠 **get_contextual_memory** - Vector similarity search
- 📋 **get_recent_chats** - Retrieve recent conversations
- 💬 **get_conversation** - Load full conversation history
- 🤖 **Agentic loop** - AI autonomously calls tools as needed
- ⚙️ **Provider support** - OpenAI, Anthropic, Gemini

</td>
<td width="50%">

### 📊 **Analytics Suite**

Deep insights into conversations:

- 📈 7-day activity trends (charts)
- 👥 Top persona statistics
- 🧪 Full SQL playground (read-only `SELECT` / `WITH`)
- ✨ SQL examples, schema browser, and inline autocomplete
- 📥 CSV export (1000 records)
- 💬 Message & token tracking
- 📊 Real-time metrics
- 💰 Chart pricing accuracy via live provider pricing sync + `model_prices` persistence

</td>
</tr>
<tr>
<td width="50%">

### 🐛 **Debug Tools**

Professional debugging suite:

- 🔭 **Laravel Telescope** - Monitor everything
- 🐛 **Laravel Debugbar** - Real-time profiling
- 🧪 **System Diagnostics** - Health checks
- 🤖 **Codex CLI + Boost MCP** - Admin-managed service key with test/clear controls
- 📝 Enhanced logging system
- 🔧 Maintenance automation
- ✨ Code style fixer (Pint)

</td>
</tr>
</table>

### 🎨 **Midnight Glass UI Design**

<img width="1134" height="1571" alt="image" src="https://github.com/user-attachments/assets/7ad92e4f-cddf-4435-8aa5-3b8b50c05664" />

<img width="1144" height="1609" alt="image" src="https://github.com/user-attachments/assets/9bca1b5a-4526-4208-b7ce-1943d08cded8" />

<img width="1133" height="923" alt="image" src="https://github.com/user-attachments/assets/c83a0fe2-eaf9-4216-beec-bb6f2b72f987" />

<img width="1131" height="765" alt="image" src="https://github.com/user-attachments/assets/b7d85765-8b2c-4798-94af-5ad953c7c660" />

<img width="1136" height="724" alt="image" src="https://github.com/user-attachments/assets/456ece67-601f-42e8-beac-62f5c6fc101f" />

<img width="1134" height="1365" alt="image" src="https://github.com/user-attachments/assets/c5ad7156-85db-4aa8-9f58-609786ff23ca" />

<img width="1132" height="1943" alt="image" src="https://github.com/user-attachments/assets/18b32b75-8013-4e3b-955d-e99db8d9d013" />

<img width="1154" height="781" alt="image" src="https://github.com/user-attachments/assets/a038e775-f7a9-4b6c-925a-1cfd73d8025a" />

<img width="1150" height="1725" alt="image" src="https://github.com/user-attachments/assets/06b60398-95bf-4e6a-a7fb-9c69c3608ec2" />

<img width="1135" height="1070" alt="image" src="https://github.com/user-attachments/assets/5fa3de8f-9bc4-4daa-a54a-10a2d7efa978" />

<img width="1133" height="1049" alt="image" src="https://github.com/user-attachments/assets/77aa1510-3a88-44d8-b1ce-051489814663" />

<img width="1146" height="845" alt="image" src="https://github.com/user-attachments/assets/00230bc2-752b-479d-8e31-6863bc885c06" />

<img width="1137" height="811" alt="image" src="https://github.com/user-attachments/assets/d45adcd7-3298-4e55-81b3-2d2d2a708e6e" />

<img width="1140" height="1088" alt="image" src="https://github.com/user-attachments/assets/b9726e61-fc0a-4162-a2a9-91b72b0ba32a" />


<table>
<tr>
<td align="center">
<img src="https://img.shields.io/badge/Theme-Dark%20Only-181818?style=for-the-badge" alt="Dark Theme"/>
<br/>
<strong>Fully Dark UI</strong>
</td>
<td align="center">
<img src="https://img.shields.io/badge/Design-Glassmorphic-00D9FF?style=for-the-badge" alt="Glassmorphic"/>
<br/>
<strong>Frosted Glass Effects</strong>
</td>
<td align="center">
<img src="https://img.shields.io/badge/Colors-Gradient-FF6B6B?style=for-the-badge" alt="Gradients"/>
<br/>
<strong>Beautiful Gradients</strong>
</td>
<td align="center">
<img src="https://img.shields.io/badge/UX-Smooth-4ECDC4?style=for-the-badge" alt="Smooth"/>
<br/>
<strong>Buttery Animations</strong>
</td>
</tr>
</table>

Our custom-designed dark theme features:

- 🌑 **Deep zinc-950 background** - True black for OLED displays
- ✨ **Glassmorphic panels** - Frosted glass with backdrop blur
- 🎨 **Gradient accents** - Blue, purple, emerald, and cyan themes
- 📜 **Custom scrollbars** - Styled for dark mode
- 🎭 **Hover effects** - Elegant micro-interactions
- 💫 **Glow effects** - Subtle shadows on interactive elements

### 🐳 **Docker Deployment**

```bash
# One command to rule them all
docker compose up -d
```

**Includes:**

- 🚀 Nginx + PHP-FPM application server
- 🗄️ PostgreSQL 16 database
- 💾 Redis caching & queues
- 🔌 Laravel Reverb WebSocket server
- 👷 Background queue workers
- 🧠 Qdrant vector database for RAG

All services configured, optimized, and ready for production!

### ⚡ **Performance**

<table>
<tr>
<td align="center">
<h3>🚀</h3>
<strong>Sub-10ms</strong><br/>
Vector Search
</td>
<td align="center">
<h3>⚡</h3>
<strong>20 min</strong><br/>
Long Conversations
</td>
<td align="center">
<h3>📊</h3>
<strong>N+1</strong><br/>
Query Prevention
</td>
<td align="center">
<h3>🔄</h3>
<strong>Real-time</strong><br/>
WebSocket Streaming
</td>
</tr>
</table>

- Async job processing with **4 parallel queue workers** (supervisord)
- Redis-backed queue system
- Efficient database queries
- Real-time streaming responses
- Optimized for scale

---

## 🛠️ Tech Stack

<table>
<tr>
<td valign="top" width="50%">

### **Backend Excellence**

🔥 **Framework**

- Laravel 12.x (Latest)
- PHP 8.2+ with strict types

🗄️ **Data Layer**

- PostgreSQL 16 (Production)
- SQLite (Development)
- Redis (Cache & Queue)
- Qdrant (Vector Database)

⚡ **Real-time**

- Laravel Reverb (WebSockets)
- Laravel Echo (Client)
- Server-Sent Events

🔐 **Authentication**

- Laravel Breeze
- Laravel Sanctum
- Role-based Access Control

🤖 **AI Integration**

- Neuron AI (Multi-provider)
- Saloon PHP (HTTP Client)
- 8+ AI Provider Support

</td>
<td valign="top" width="50%">

### **Frontend Magic**

⚛️ **UI Framework**

- React 18
- Inertia.js 2.0
- TypeScript Support

🎨 **Styling**

- Tailwind CSS v3
- Custom Dark Theme
- Glassmorphic Design
- CSS-in-JS Compatible

📊 **Visualization**

- Recharts (Analytics)
- Custom Chart Components
- Real-time Updates

🔧 **Build Tools**

- Vite 7 (Lightning Fast)
- NPM/Yarn
- Hot Module Replacement

🐛 **Developer Tools**

- Laravel Telescope
- Laravel Debugbar
- Laravel Pint (Code Style)
- PHPUnit/Pest (Testing)

</td>
</tr>
</table>

### **🎯 Supported AI Providers**

| Provider         | Models                                 | Dynamic Fetching | Status    |
| ---------------- | -------------------------------------- | ---------------- | --------- |
| 🤖 OpenAI        | GPT-4o, GPT-4 Turbo, etc.              | ✅ API           | ✅ Active |
| 🧠 Anthropic     | Claude Sonnet 4.5, Opus 4.5, Haiku 4.5 | ✅ API           | ✅ Active |
| 🌟 Google Gemini | Gemini 2.0 Flash, 2.0 Flash Lite, 1.5 Pro | ⚙️ Static        | ✅ Active |
| 🚀 DeepSeek      | DeepSeek Chat, R1                      | ⚙️ Static        | ✅ Active |
| 🔀 OpenRouter    | **344+ models** with live pricing      | ✅ API           | ✅ Active |
| 🏠 Ollama        | Local Models                           | ✅ Auto-detect   | ✅ Active |
| 💻 LM Studio     | Local Models                           | ✅ Auto-detect   | ✅ Active |
| 🧪 Mock          | Testing & Development                  | N/A              | ✅ Active |

---

## 🔧 Admin & Developer Tools

Chat Bridge includes a comprehensive suite of professional tools for system administration and debugging:

### 🧪 **System Diagnostics Dashboard** (`/admin/system`)

A powerful web-based control panel with 9 diagnostic actions:

| Action             | Description                           | Use Case             |
| ------------------ | ------------------------------------- | -------------------- |
| 🏥 Health Check    | Complete system status overview       | Quick sanity check   |
| 🔐 Fix Permissions | Repair file permissions automatically | After deployment     |
| 🗑️ Clear Caches    | Clear config, routes, views, events   | Development workflow |
| ⚡ Optimize App    | Cache configs for production          | Before going live    |
| 🤖 Validate AI     | Test all AI service connections       | API key validation   |
| 🗄️ Check Database  | Database health & statistics          | Monitoring           |
| 🧪 Run Tests       | Execute full PHPUnit test suite       | CI/CD integration    |
| ✨ Fix Code Style  | Auto-fix with Laravel Pint            | Code quality         |
| ⬆️ Update Laravel  | Update `laravel/framework` safely     | Framework maintenance |

**System Information Panel:**

- PHP & Laravel versions
- Environment & debug status
- Memory limit & execution time
- Disk space usage
- Cache & Queue drivers
- File permission status

**Codex Service Key Panel:**

- Set a single OpenAI service key for Codex/Boost diagnostics
- Test key connectivity from the dashboard
- Clear the stored key when rotating credentials
- Codex CLI is bundled in the app image and uses the same service key

### 🔭 **Laravel Telescope** (`/telescope`)

Professional application monitoring:

- 📊 Request/Response tracking
- 🐛 Exception monitoring
- 💾 Database query profiling
- 📬 Job & Queue monitoring
- 📧 Mail & Notification tracking
- 📝 Log aggregation
- ⏱️ Performance metrics

**Dark theme enabled** • **Admin-only access** • **Production-ready**

### 🐛 **Laravel Debugbar**

Real-time profiling bar (bottom of page):

- ⚡ Query profiling with execution time
- 🧠 Memory usage tracking
- ⏱️ Timeline visualization
- 📁 Included files list
- 🔀 Route information
- 📊 Request/Response data

**Auto-disabled in production** • **Zero performance impact**

### 📈 **Performance Monitor** (`/admin/performance`)

Request and query-level observability for admins:

- 10-second auto-refresh snapshot API (`/admin/performance/stats`)
- 5-minute latency window (avg, p95, max, error rate)
- DB insights (query count, total DB time, slow query rate)
- Throughput chart (last 15 minutes)
- Slowest route breakdown and recent slow requests
- Recent slow SQL query samples (>= 100ms)
- Queue health and runtime context (DB/cache driver, memory, load)

### 🤖 **Model Context Protocol (MCP) Server** (`/api/mcp`)
Native implementation of the JSON-RPC 2.0 MCP protocol for developer interaction:

- Standardized `initialize`, `tools/list`, and `tools/call` methods
- Protocol Version: `2024-11-05`
- Compatible with [Crush](https://crush.charm.land) and other MCP clients
- Securely exposes chat search, recent history, and system statistics as tools

For full integration guide, see **[MCP.md](MCP.md)**

---

## 📋 Requirements

- PHP >= 8.2
- Composer
- Node.js >= 18
- NPM or Yarn
- SQLite (or MySQL/PostgreSQL)

---

## 🚀 Installation

### 1. Clone the Repository

```bash
git clone <repository-url>
cd chat_bridge
```

### 2. Install Dependencies

```bash
composer install
npm install
```

### 3. Environment Setup

```bash
cp .env.example .env
php artisan key:generate
```

### 4. Configure Database

```bash
touch database/database.sqlite
php artisan migrate --force
```

Note: when `DB_CONNECTION=sqlite`, Chat Bridge now auto-creates a missing SQLite file on boot. The command above is still recommended for first-time setup.

### 5. Build Assets

```bash
npm run build
```

### 6. Start the Application

For automatic port selection and service management (recommended):

```bash
chmod +x start-services.sh
./start-services.sh
```

This script will:

1. Find available ports for Web and WebSocket servers
2. Configure your environment
3. Rebuild frontend assets
4. Start Web Server, Reverb, and Queue
5. Display the access URLs

Or run manually:

```bash
php artisan serve
php artisan queue:work
php artisan reverb:start
```

---

## 🐳 Docker Deployment

For production deployment or easier setup, use Docker:

### Quick Start with Docker

```bash
# 1. Clone repository
git clone <repository-url>
cd chat_bridge

# 2. Copy Docker environment file
cp .env.docker .env

# 3. Configure your API keys in .env
nano .env

# 4. Start all services
make setup
# Or: docker compose up -d

# 5. Access the application
# Web: http://localhost:8000
# WebSocket: http://localhost:8080
# Qdrant: http://localhost:6333/dashboard
```

### Switching From Host Mode To Docker Mode

If you previously ran with host PHP + SQLite, switch back to Docker-safe settings with:

```bash
cd ~/chat_bridge
cp .env.docker .env
docker compose up -d
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan queue:restart
```

**Data persistence:** Docker volumes are preserved by default. Use `make clean-volumes` only when you want to wipe the database and Qdrant data.

### Docker Services

The Docker deployment includes:

- **app**: Laravel application (Nginx + PHP-FPM)
- **queue**: 4 parallel background workers for conversations (supervisord)
- **reverb**: WebSocket server for real-time updates
- **postgres**: PostgreSQL database
- **redis**: Redis for caching and queue
- **qdrant**: Vector database for RAG

### Initialize RAG

After starting Docker services:

```bash
# Initialize Qdrant vector database
make init

# (Optional) Generate embeddings for existing messages
make embeddings

# (Optional) Sync existing messages to Qdrant
make sync
```

### Common Docker Commands

```bash
make up           # Start all services
make down         # Stop all services
make logs         # View all logs
make shell        # Open shell in app container
make migrate      # Run migrations
make clean        # Remove all containers (keeps volumes)
make clean-volumes # Remove all containers and volumes (destructive)
```

For detailed Docker documentation, see **[DOCKER.md](DOCKER.md)**

Docker troubleshooting highlights:

- If Docker builds fail with `permission denied` on `storage/postgres`, see the new troubleshooting section in `DOCKER.md`.
- After changing PHP dependencies (`composer.json` / `composer.lock`), rebuild images: `docker compose build app queue reverb && docker compose up -d app queue reverb`.

For RAG functionality guide, see **[RAG_GUIDE.md](RAG_GUIDE.md)**

---

## 🎮 Quick Start

### Development Mode

```bash
composer dev
```

This single command starts:

- 🌐 Laravel development server (port 8000)
- 📦 Queue worker
- 📝 Log viewer (Pail)
- ⚡ Vite dev server (HMR)
- 🔌 Reverb WebSocket server

### Production Build

```bash
npm run build
php artisan optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 📖 Usage Guide

### 1. Login with Default Admin

Visit `http://localhost:8000/login` (or `http://localhost:8002` for Docker) and use the default credentials:

- **Email**: `admin@chatbridge.local`
- **Password**: `password`

This admin user is automatically created with full admin rights during installation via database seeder.

### 2. Add API Keys

1. Navigate to `/api-keys`
2. Click "Add API Key"
3. Select provider (e.g., "openai" or "anthropic")
4. Paste your API key
5. Add a label (optional)
6. Save

### 3. Create Personas

1. Go to `/personas`
2. Click "Create Persona"
3. Configure:
    - **Name**: Unique identifier
    - **System Prompt**: Instructions for the AI's personality and behavior
    - **Default Temperature**: 0.0 (deterministic) to 2.0 (creative)
    - **Notes**: Optional internal notes
4. Save

> 💡 **Note**: Personas are now provider/model-agnostic templates! You select the provider and model when creating a conversation, allowing you to reuse the same persona with different AI models.

### 4. Start a Conversation

1. Navigate to `/chat/create`
2. Configure **Agent A**:
   - Select persona template
   - Choose AI provider (Anthropic, OpenAI, OpenRouter, etc.)
   - Select model from **dynamically fetched list** with pricing
3. Configure **Agent B**:
   - Select persona template
   - Choose AI provider (can be different from Agent A!)
   - Select model with live pricing
4. Enter **Starter Message**
5. Configure **Chat Control Settings**:
    - **Max Rounds**: Limit conversation turns (1-100)
    - **Stop Word Detection**: Enable automatic stopping
    - **Stop Words**: Comma-separated trigger words (e.g., "goodbye, end")
    - **Threshold**: Detection sensitivity (0.1-1.0)
    - **Discord Broadcast** (optional): Stream conversation updates to Discord
    - **Discord Webhook Override** (optional): Leave blank to use your profile/system default
6. Click "Begin Simulation"
7. Watch the real-time conversation unfold!

> 💰 **Pricing**: All models display cost per 1M tokens (input/output) so you can make informed decisions!

### 5. Monitor Conversations

- View active conversations on `/chat`
- Click any conversation to see details
- Use the stop button to halt long conversations
- Download transcripts for analysis

### 6. Run Analytics SQL Queries

1. Open `/analytics/query`
2. Use an example query or type your own SQL
3. Use autocomplete suggestions for keywords/tables/columns
4. Press `Ctrl+Enter` or `Cmd+Enter` to run
5. Adjust row limit (1-500) and export filtered results to CSV

Notes:
- The SQL runner allows read-only `SELECT` and `WITH` queries only
- Multi-statement and write operations are blocked for safety

---

## 🗂️ Project Structure

```
chat_bridge/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/UserController.php      # User management
│   │   │   ├── ApiKeyController.php           # API key CRUD
│   │   │   ├── ChatController.php             # Conversations
│   │   │   └── PersonaController.php          # Persona CRUD
│   │   └── Middleware/
│   │       └── EnsureUserIsAdmin.php          # Admin middleware
│   ├── Jobs/
│   │   ├── RunChatSession.php                 # Main conversation loop
│   │   └── ProcessConversationTurn.php        # Single turn handler
│   ├── Models/
│   │   ├── User.php                           # User model
│   │   ├── Persona.php                        # AI agent config
│   │   ├── Conversation.php                   # Chat session
│   │   ├── Message.php                        # Individual message
│   │   └── ApiKey.php                         # Encrypted API keys
│   ├── Services/
│   │   ├── AI/
│   │   │   ├── AIManager.php                  # AI provider abstraction
│   │   │   └── EmbeddingService.php           # Vector embeddings
│   │   ├── System/
│   │   │   └── [System services]              # System utilities
│   │   ├── ConversationService.php            # Turn generation
│   │   ├── TranscriptService.php              # Export conversations
│   │   └── AnalyticsController.php            # Analytics and queries
│   └── Events/
│       ├── MessageChunkSent.php               # Streaming chunks
│       ├── MessageCompleted.php               # Full message
│       └── ConversationStatusUpdated.php      # Status changes
├── database/
│   └── migrations/                            # Database schema
├── resources/
│   ├── js/
│   │   ├── Pages/
│   │   │   ├── Auth/                          # Login/Register
│   │   │   ├── Chat/                          # Conversation UI
│   │   │   ├── Personas/                      # Persona management
│   │   │   ├── ApiKeys/                       # API key management
│   │   │   ├── Analytics/                     # Analytics dashboard
│   │   │   ├── Admin/                         # Admin panel
│   │   │   └── Dashboard.jsx                  # Main dashboard
│   │   └── app.jsx                            # React entry point
│   └── css/
│       └── app.css                            # Tailwind + custom dark theme
├── routes/
│   ├── web.php                                # Web routes
│   ├── api.php                                # API routes
│   └── channels.php                           # Broadcast channels
├── LARAVEL_ENHANCEMENTS.md                    # UX improvement suggestions
└── ROADMAP.md                                 # Future development plan
```

---

## 🔒 Security Features

- ✅ Encrypted API key storage
- ✅ CSRF protection
- ✅ SQL injection prevention (Eloquent ORM)
- ✅ XSS protection (React/Blade escaping)
- ✅ Password hashing (bcrypt)
- ✅ User data isolation
- ✅ Role-based access control
- ✅ Middleware authentication checks

---

## 🌐 API Endpoints

### Authentication

- `POST /register` - Register new user
- `POST /login` - User login
- `POST /logout` - User logout

### Conversations

- `GET /chat` - List conversations
- `POST /chat` - Create conversation (with provider/model selection & chat controls)
- `GET /chat/{id}` - View conversation
- `POST /chat/{id}/stop` - Stop conversation
- `DELETE /chat/{id}` - Delete conversation
- `GET /chat/{id}/transcript` - Download transcript

### Provider API

- `GET /api/providers/models?provider={name}` - Get available models for provider (with pricing)
    - Also upserts provider/model token pricing into `model_prices` for analytics cost estimation

### Personas

- `GET /personas` - List personas
- `POST /personas` - Create persona
- `GET /personas/{id}/edit` - Edit form
- `PUT /personas/{id}` - Update persona
- `DELETE /personas/{id}` - Delete persona

### API Keys

- `GET /api-keys` - List API keys
- `POST /api-keys` - Add API key
- `PUT /api-keys/{id}` - Update API key
- `DELETE /api-keys/{id}` - Delete API key
- `POST /api-keys/{id}/test` - Validate API key with provider

### AI Chatbot

- `GET /transcript-chat` - Ask the Archive interface
- `POST /transcript-chat/ask` - Submit a question (supports `system_prompt`, `model`, `temperature`, `max_tokens`, `source_limit`, `score_threshold`)

### Analytics

- `GET /analytics` - Analytics dashboard with charts
- `GET /analytics/query` - Query page with filters + SQL playground
- `POST /analytics/query/run-sql` - Execute read-only SQL (`SELECT` / `WITH`)
- `POST /analytics/export` - Export conversations to CSV

### Admin (Requires Admin Role)

- `GET /admin/users` - List all users
- `POST /admin/users` - Create user
- `PUT /admin/users/{id}` - Update user
- `DELETE /admin/users/{id}` - Delete user
- `GET /admin/performance` - Performance monitor dashboard
- `GET /admin/performance/stats` - Performance monitor JSON snapshot

### External API

- `POST /api/chat-bridge/respond` - Chat bridge endpoint (requires token)

### MCP Server

- `POST /api/mcp` - Native MCP JSON-RPC 2.0 endpoint (no auth required for local dev)

---

## ⚙️ Configuration

### Environment Variables

```env
APP_NAME="Chat Bridge"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite
# Or for production:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=chat_bridge
# DB_USERNAME=root
# DB_PASSWORD=

QUEUE_CONNECTION=database
CACHE_DRIVER=file
SESSION_DRIVER=file

REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret

# Discord conversation broadcasting
DISCORD_STREAMING_ENABLED=true
# DISCORD_WEBHOOK_URL=https://discord.com/api/webhooks/...
DISCORD_THREAD_AUTO_CREATE=true
DISCORD_CIRCUIT_BREAKER_THRESHOLD=5

# Add your AI provider keys to database via UI
# DO NOT store them in .env for security
```

### AI Provider Setup

Chat Bridge supports multiple AI providers through the Neuron AI package:

- **OpenAI**: GPT-4, GPT-3.5, etc.
- **Anthropic**: Claude 3.5 Sonnet, Claude 3 Opus, etc.
- **Custom**: Extend with additional providers

Add API keys via the web interface (`/api-keys`) for secure encrypted storage.

---

## 🧪 Testing

The project includes automated testing via PHPUnit and GitHub Actions.

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/ConversationTest.php

# Run with coverage
php artisan test --coverage

# Use the interactive test runner
./run_tests.sh
```

**Or use the System Diagnostics panel** at `/admin/system` to run tests via the web interface!

---

## 🐛 Troubleshooting

### Queue Not Processing

```bash
php artisan queue:work --tries=1
```

> This project uses Laravel queue workers (`queue:work` / `queue:restart`), not Horizon.

### WebSocket Connection Failed

Check Reverb is running:

```bash
php artisan reverb:start
```

### Discord Broadcast Not Posting

If conversations are not appearing in Discord:

```bash
# Global feature switch
DISCORD_STREAMING_ENABLED=true

# Optional global fallback webhook
# DISCORD_WEBHOOK_URL=https://discord.com/api/webhooks/...

# Restart workers after changing config/env
php artisan optimize:clear
php artisan queue:restart
```

Also confirm the conversation has **Discord Broadcast** enabled and that either:
- conversation webhook override is set, or
- user profile default webhook is set, or
- global `DISCORD_WEBHOOK_URL` is configured.

### Broadcast Payload Too Large

If messages vanish or you see payload size errors, lower streaming chunk size or raise Reverb limits:

```bash
# Lower broadcast chunk size (safer for large personas)
AI_STREAM_CHUNK_SIZE=600

# Increase Reverb request/message limits (local Reverb only)
REVERB_MAX_REQUEST_SIZE=25000
REVERB_APP_MAX_MESSAGE_SIZE=25000
```

Then restart the services:

```bash
php artisan queue:restart
```

### Conversation Stops Early (Empty Turn)
If a provider occasionally returns an empty response, tune retry behavior:
```bash
# Retry empty turns before failing the conversation
AI_EMPTY_TURN_RETRY_ATTEMPTS=2
AI_EMPTY_TURN_RETRY_DELAY_MS=500
```

Apply config changes and restart workers:
```bash
php artisan optimize:clear
php artisan queue:restart
```

### Provider Timeouts (cURL error 28)
If first-token latency is high for some models, increase provider HTTP resilience:
```bash
AI_HTTP_TIMEOUT_SECONDS=90
AI_HTTP_CONNECT_TIMEOUT_SECONDS=15
AI_HTTP_RETRY_ATTEMPTS=2
AI_HTTP_RETRY_DELAY_MS=500
```

### Build Errors

Clear cache and rebuild:

```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
npm run build
```

### SQLite File Missing

If you see `Database file at path ... database.sqlite does not exist`:

```bash
touch database/database.sqlite
php artisan migrate --force
```

Also confirm `DB_DATABASE` is an absolute path in `.env` when running inside Docker.

### Gemini Model Not Supported

If API key testing fails with `is not found for API version`:

```bash
# Prefer a v1beta-supported default
GEMINI_MODEL=gemini-2.0-flash
php artisan optimize:clear
```

You can list available models for your key:

```bash
curl "https://generativelanguage.googleapis.com/v1beta/models?key=YOUR_KEY"
```

### Database Locked (SQLite)

Stop all queue workers and retry:

```bash
php artisan queue:restart
php artisan migrate --force
```

---

## 📚 Documentation

### 📖 Chat Bridge Documentation

| Document                                         | Description                     |
| ------------------------------------------------ | ------------------------------- |
| **[FEATURES.md](FEATURES.md)**                   | 🎯 Complete feature list (200+) |
| **[DOCKER.md](DOCKER.md)**                       | 🐳 Docker deployment guide      |
| **[RAG_GUIDE.md](RAG_GUIDE.md)**                 | 🧠 RAG & AI memory guide        |
| **[ROADMAP.md](ROADMAP.md)**                     | 🗺️ Future development plans     |
| **[DATA_MANIPULATION.md](DATA_MANIPULATION.md)** | 📊 Data operations guide        |
| **[MCP.md](MCP.md)**                             | 🤖 MCP server integration guide |

### 🌐 External Documentation

- **[Laravel 12.x](https://laravel.com/docs/12.x)** - Framework documentation
- **[React 18](https://react.dev/)** - UI library guide
- **[Inertia.js](https://inertiajs.com/)** - SPA bridge documentation
- **[Tailwind CSS v3](https://tailwindcss.com/)** - Styling framework
- **[Laravel Reverb](https://reverb.laravel.com/)** - WebSocket server
- **[Qdrant](https://qdrant.tech/documentation/)** - Vector database
- **[Laravel Telescope](https://laravel.com/docs/telescope)** - Debug tool
- **[Recharts](https://recharts.org/)** - Charting library

---

## 🤝 Contributing

Contributions are welcome! Please follow these steps:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

---

## 📄 License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

---

## 🎯 Quick Stats

<div align="center">

| Metric                         | Count         |
| ------------------------------ | ------------- |
| 🎭 **Pre-configured Personas** | 56            |
| 🤖 **AI Providers Supported**  | 8+            |
| ✨ **Features**                | 200+          |
| 🎨 **Custom CSS Utilities**    | 15+           |
| 📊 **Admin Dashboard Actions** | 8             |
| 🧪 **Test Coverage**           | Comprehensive |
| 📦 **Total Dependencies**      | 93+           |
| ⚡ **Vector Search Speed**     | <10ms         |

</div>

---

## 🌟 What Makes Chat Bridge Special?

<table>
<tr>
<td width="50%">

### 🎨 **Stunning UI**

Not just functional—beautiful! Our custom "Midnight Glass" dark theme with glassmorphic design makes working with AI agents a visual treat.

### 🔧 **Developer-First**

Built by developers, for developers. Includes Telescope, Debugbar, comprehensive testing, and a full diagnostics suite.

</td>
<td width="50%">

### 🚀 **Production-Ready**

Not a toy project. Enterprise-grade security, performance optimization, Docker deployment, and comprehensive monitoring.

### 🧠 **Intelligent**

RAG-powered conversations with persistent memory. Your AI agents remember context across sessions for truly intelligent discussions.

</td>
</tr>
</table>

---

## 🙏 Acknowledgments

Powered by amazing open-source projects:

- **[Laravel](https://laravel.com)** - The PHP Framework for Web Artisans
- **[React](https://react.dev)** - A JavaScript library for building user interfaces
- **[Inertia.js](https://inertiajs.com)** - The Modern Monolith
- **[Tailwind CSS](https://tailwindcss.com)** - A utility-first CSS framework
- **[Vite](https://vitejs.dev)** - Next Generation Frontend Tooling
- **[Qdrant](https://qdrant.tech)** - Vector Database for AI
- **[Laravel Reverb](https://reverb.laravel.com)** - Blazing fast WebSockets
- **[Neuron AI](https://github.com/UseNeuron/neuron)** - Multi-provider AI SDK

---

## 📞 Support & Community

- 📧 **Issues**: [GitHub Issues](https://github.com/meistro57/chat_bridge/issues)
- 💬 **Discussions**: [GitHub Discussions](https://github.com/meistro57/chat_bridge/discussions)
- 🐛 **Bug Reports**: Use GitHub Issues with the `bug` label
- ✨ **Feature Requests**: Use GitHub Issues with the `enhancement` label

---

## 🗺️ What's Next?

Check out our [ROADMAP.md](ROADMAP.md) for upcoming features and improvements!

**Coming Soon:**

- 🌐 Multi-language support
- 📱 Mobile app (React Native)
- 🎙️ Voice conversation support
- 🔌 Plugin system
- 📊 Advanced analytics
- 🤝 Team collaboration features

---

## ⭐ Star History

If you find Chat Bridge useful, please consider giving it a star! ⭐

---

<div align="center">

### Made with ❤️ by developers who love AI

**[⬆ back to top](#-chat-bridge)**

---

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](http://makeapullrequest.com)
[![Maintenance](https://img.shields.io/badge/Maintained%3F-yes-green.svg)](https://github.com/meistro57/chat_bridge/graphs/commit-activity)

</div>
