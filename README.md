# 🤖 Chat Bridge

**A sophisticated AI conversation orchestration platform built with Laravel 12 and React 19**

Chat Bridge enables you to create, manage, and monitor automated conversations between AI personas powered by multiple LLM providers (OpenAI, Anthropic, and more). Perfect for testing AI interactions, generating synthetic conversations, and exploring multi-agent AI systems.

---

## ✨ Features

### 🎭 Persona Management
- Create reusable AI agent configurations
- Support for multiple AI providers (OpenAI, Anthropic, etc.)
- Customizable system prompts and guidelines
- Temperature control for response creativity
- Per-user persona isolation

### 💬 Conversation Orchestration
- Automated multi-turn conversations between two personas
- Real-time streaming responses via WebSockets
- Configurable conversation parameters
- Stop signal support for manual intervention
- Conversation status tracking (active, completed, failed)

### 🔐 User Authentication & Management
- User registration and login (Laravel Breeze)
- Role-based access control (User/Admin)
- User-scoped data isolation
- Admin panel for user management
- Profile management

### 🔑 API Key Management
- Secure encrypted storage of API credentials
- Support for multiple AI providers
- Per-user API key isolation
- Active/inactive key toggling
- Masked key display for security

### 📊 Real-time Features
- Live message streaming with Laravel Reverb (WebSockets)
- Chunk-by-chunk response updates
- Conversation status broadcasting
- Real-time UI updates with Inertia.js

### 🎨 Modern UI
- "Midnight Glass" dark theme
- Glassmorphic design with Tailwind CSS v4
- Responsive React components
- Smooth transitions and animations
- Accessible interface

### 🧠 RAG (Retrieval-Augmented Generation)
- **AI Memory**: Persistent memory across conversations
- **Semantic Search**: Find relevant past messages using embeddings
- **Context-Aware Responses**: AI uses previous conversations for informed replies
- **Vector Database**: Qdrant for efficient similarity search
- **Automatic Embeddings**: Generated and stored for all messages

### 🐳 Docker Support
- **Complete Containerization**: Docker Compose orchestration
- **Production-Ready**: PostgreSQL, Redis, Qdrant included
- **Easy Deployment**: One-command setup
- **Scalable Architecture**: Separate containers for app, queue, WebSocket
- **Persistent Storage**: Volumes for database and vector storage

### 🚀 Performance & Scalability
- Async job processing with Laravel Queue
- Database-backed queue system
- Long-running conversation support (20 min timeout)
- Message embeddings for semantic search
- Efficient N+1 query prevention
- Vector search with sub-10ms response times

---

## 🛠️ Tech Stack

### Backend
- **Framework**: Laravel 12 (PHP 8.2+)
- **Database**: SQLite (dev) / PostgreSQL (Docker/production)
- **Queue**: Database driver (dev) / Redis (Docker/production)
- **WebSockets**: Laravel Reverb
- **Authentication**: Laravel Breeze + Sanctum
- **AI Integration**: Neuron AI (multi-provider abstraction)
- **HTTP Client**: Saloon PHP
- **Vector Database**: Qdrant (RAG functionality)

### Frontend
- **Framework**: React 19
- **SPA Bridge**: Inertia.js 2.0
- **Build Tool**: Vite 7
- **Styling**: Tailwind CSS v4
- **Real-time**: Laravel Echo
- **Icons**: Lucide React

### Development Tools
- **Testing**: PHPUnit/Pest
- **Code Style**: Laravel Pint
- **Logs**: Laravel Pail
- **Package Manager**: Composer & NPM

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

### 6. (Optional) Seed Sample Data
```bash
php artisan db:seed
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

### 1. Create Your First User
Visit `http://localhost:8000/register` and create an account.

The first user should be set as admin:
```bash
php artisan tinker
```
```php
$user = User::first();
$user->role = 'admin';
$user->save();
```

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
│   │   ├── ConversationService.php            # Turn generation
│   │   └── TranscriptService.php              # Export conversations
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
│   │   │   └── ApiKeys/                       # API key management
│   │   └── app.jsx                            # React entry point
│   └── css/
│       └── app.css                            # Tailwind imports
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

# With coverage
php artisan test --coverage
```

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

### Chat Bridge Documentation
- **[DOCKER.md](DOCKER.md)** - Complete Docker deployment guide
- **[RAG_GUIDE.md](RAG_GUIDE.md)** - RAG functionality and AI memory guide
- **[ROADMAP.md](ROADMAP.md)** - Future development plans
- **[LARAVEL_ENHANCEMENTS.md](LARAVEL_ENHANCEMENTS.md)** - UX improvements

### External Documentation
- [Laravel Documentation](https://laravel.com/docs/12.x)
- [Inertia.js Guide](https://inertiajs.com/)
- [React Documentation](https://react.dev/)
- [Tailwind CSS](https://tailwindcss.com/)
- [Laravel Reverb](https://reverb.laravel.com/)
- [Qdrant Documentation](https://qdrant.tech/documentation/)

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

## 🙏 Acknowledgments

- **Laravel** - Elegant PHP framework
- **Inertia.js** - Modern monolith architecture
- **React** - UI library
- **Tailwind CSS** - Utility-first CSS
- **Neuron AI** - Multi-provider AI abstraction
- **Saloon PHP** - HTTP client for APIs

---

## 📞 Support

For support, please open an issue on GitHub or contact the maintainers.

---

## 🗺️ Roadmap

See [ROADMAP.md](ROADMAP.md) for planned features and improvements.

---

## 🎨 Screenshots

### Dashboard
![Dashboard](docs/images/dashboard.png)

### Conversation View
![Conversation](docs/images/conversation.png)

### Persona Management
![Personas](docs/images/personas.png)

---

Made with ❤️ using Laravel and React
