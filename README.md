<div align="center">

# 🤖 Chat Bridge

### *The Ultimate AI Conversation Orchestration Platform*

[![Tests](https://github.com/meistro57/chat_bridge/actions/workflows/laravel.yml/badge.svg)](https://github.com/meistro57/chat_bridge/actions/workflows/laravel.yml)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![React](https://img.shields.io/badge/React-19-61DAFB?logo=react&logoColor=black)](https://react.dev)
[![Tailwind](https://img.shields.io/badge/Tailwind-v4-38BDF8?logo=tailwind-css&logoColor=white)](https://tailwindcss.com)
[![Docker](https://img.shields.io/badge/Docker-Ready-2496ED?logo=docker&logoColor=white)](https://www.docker.com/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)
[![Made with Love](https://img.shields.io/badge/Made%20with-%E2%9D%A4%EF%B8%8F-red)](https://github.com/meistro57/chat_bridge)

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

## ✨ Features at a Glance

<table>
<tr>
<td width="50%">

### 🎭 **Persona System**
Create sophisticated AI agents with:
- 🔧 Custom system prompts & guidelines
- 🌡️ Temperature controls (0.0-2.0)
- 🔄 Multi-provider support (8+ AI services)
- 👥 Shared library - 56 pre-configured personas
- 📝 Creator attribution tracking
- ✏️ Full CRUD operations

</td>
<td width="50%">

### 💬 **Conversation Engine**
Orchestrate AI discussions with:
- ⚡ Real-time streaming via WebSockets
- 🔄 Automated multi-turn dialogues
- 🎯 Manual stop/resume controls
- 📡 Live status broadcasting
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
</tr>
<tr>
<td width="50%">

### 📊 **Analytics Suite**
Deep insights into conversations:
- 📈 7-day activity trends (charts)
- 👥 Top persona statistics
- 🔍 Advanced query filters
- 📥 CSV export (1000 records)
- 💬 Message & token tracking
- 📊 Real-time metrics

</td>
<td width="50%">

### 🐛 **Debug Tools**
Professional debugging suite:
- 🔭 **Laravel Telescope** - Monitor everything
- 🐛 **Laravel Debugbar** - Real-time profiling
- 🧪 **System Diagnostics** - Health checks
- 📝 Enhanced logging system
- 🔧 Maintenance automation
- ✨ Code style fixer (Pint)

</td>
</tr>
</table>

### 🎨 **Midnight Glass UI Design**

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

- Async job processing with Laravel Queue
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
- React 19 (Latest)
- Inertia.js 2.0
- TypeScript Support

🎨 **Styling**
- Tailwind CSS v4
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

| Provider | Models | Status |
|----------|--------|--------|
| 🤖 OpenAI | GPT-4, GPT-3.5, etc. | ✅ Active |
| 🧠 Anthropic | Claude 3.5 Sonnet, Opus | ✅ Active |
| 🌟 Google | Gemini Pro, Ultra | ✅ Active |
| 🚀 DeepSeek | DeepSeek Chat | ✅ Active |
| 🔀 OpenRouter | Multi-model Gateway | ✅ Active |
| 🏠 Ollama | Local Models | ✅ Active |
| 💻 LM Studio | Local Models | ✅ Active |
| 🧪 Mock | Testing & Development | ✅ Active |

---

## 🔧 Admin & Developer Tools

Chat Bridge includes a comprehensive suite of professional tools for system administration and debugging:

### 🧪 **System Diagnostics Dashboard** (`/admin/system`)

A powerful web-based control panel with 8 diagnostic actions:

| Action | Description | Use Case |
|--------|-------------|----------|
| 🏥 Health Check | Complete system status overview | Quick sanity check |
| 🔐 Fix Permissions | Repair file permissions automatically | After deployment |
| 🗑️ Clear Caches | Clear config, routes, views, events | Development workflow |
| ⚡ Optimize App | Cache configs for production | Before going live |
| 🤖 Validate AI | Test all AI service connections | API key validation |
| 🗄️ Check Database | Database health & statistics | Monitoring |
| 🧪 Run Tests | Execute full PHPUnit test suite | CI/CD integration |
| ✨ Fix Code Style | Auto-fix with Laravel Pint | Code quality |

**System Information Panel:**
- PHP & Laravel versions
- Environment & debug status
- Memory limit & execution time
- Disk space usage
- Cache & Queue drivers
- File permission status

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

### 5. Build Assets
```bash
npm run build
```

### 7. Start the Application
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

### Docker Services

The Docker deployment includes:
- **app**: Laravel application (Nginx + PHP-FPM)
- **queue**: Background worker for conversations
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
make clean        # Remove all containers and volumes
```

For detailed Docker documentation, see **[DOCKER.md](DOCKER.md)**

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

- **Username**: `admin` (accepts username without @ symbol)
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
   - **Provider**: AI provider (must have corresponding API key)
   - **Model**: Specific model (e.g., "gpt-4", "claude-3-5-sonnet")
   - **System Prompt**: Instructions for the AI
   - **Guidelines**: JSON array of behavioral rules
   - **Temperature**: 0.0 (deterministic) to 2.0 (creative)
4. Save

### 4. Start a Conversation
1. Navigate to `/chat/create`
2. Select **Persona A** (starts conversation)
3. Select **Persona B** (responds)
4. Enter a **Starter Message**
5. Click "Start Conversation"
6. Watch the real-time conversation unfold!

### 5. Monitor Conversations
- View active conversations on `/chat`
- Click any conversation to see details
- Use the stop button to halt long conversations
- Download transcripts for analysis

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
- `POST /chat` - Create conversation
- `GET /chat/{id}` - View conversation
- `POST /chat/{id}/stop` - Stop conversation
- `DELETE /chat/{id}` - Delete conversation
- `GET /chat/{id}/transcript` - Download transcript

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

### Analytics
- `GET /analytics` - Analytics dashboard with charts
- `POST /analytics/query` - Query conversation history
- `POST /analytics/export` - Export conversations to CSV

### Admin (Requires Admin Role)
- `GET /admin/users` - List all users
- `POST /admin/users` - Create user
- `PUT /admin/users/{id}` - Update user
- `DELETE /admin/users/{id}` - Delete user

### External API
- `POST /api/chat-bridge/respond` - Chat bridge endpoint (requires token)

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

### WebSocket Connection Failed
Check Reverb is running:
```bash
php artisan reverb:start
```

### Build Errors
Clear cache and rebuild:
```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
npm run build
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
| Document | Description |
|----------|-------------|
| **[FEATURES.md](FEATURES.md)** | 🎯 Complete feature list (200+) |
| **[DOCKER.md](DOCKER.md)** | 🐳 Docker deployment guide |
| **[RAG_GUIDE.md](RAG_GUIDE.md)** | 🧠 RAG & AI memory guide |
| **[ROADMAP.md](ROADMAP.md)** | 🗺️ Future development plans |
| **[DATA_MANIPULATION.md](DATA_MANIPULATION.md)** | 📊 Data operations guide |

### 🌐 External Documentation
- **[Laravel 12.x](https://laravel.com/docs/12.x)** - Framework documentation
- **[React 19](https://react.dev/)** - UI library guide
- **[Inertia.js](https://inertiajs.com/)** - SPA bridge documentation
- **[Tailwind CSS v4](https://tailwindcss.com/)** - Styling framework
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

| Metric | Count |
|--------|-------|
| 🎭 **Pre-configured Personas** | 56 |
| 🤖 **AI Providers Supported** | 8+ |
| ✨ **Features** | 200+ |
| 🎨 **Custom CSS Utilities** | 15+ |
| 📊 **Admin Dashboard Actions** | 8 |
| 🧪 **Test Coverage** | Comprehensive |
| 📦 **Total Dependencies** | 93+ |
| ⚡ **Vector Search Speed** | <10ms |

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
