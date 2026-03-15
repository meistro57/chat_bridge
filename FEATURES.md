# 🚀 Chat Bridge - Complete Feature List

## 📋 Table of Contents

- [Core Features](#core-features)
- [Admin Tools](#admin-tools)
- [UI/UX Features](#uiux-features)
- [Security Features](#security-features)
- [Performance Features](#performance-features)
- [Developer Experience](#developer-experience)

---

## Core Features

### 🎭 Persona Management
- **Create & Configure** - Build AI personas with custom personalities
- **AI Persona Creator Bot** - Popout generator on persona create screen
  - Uses configured OpenAI service key (`services.openai.key`)
  - Generates and auto-fills persona `name` + `system_prompt`
- **System Prompts** - Define behavior with detailed instructions
- **Guidelines** - JSON-based behavioral rules
- **Temperature Control** - Default temperature setting (0.0-2.0)
- **Provider-Agnostic** - Personas work with any AI provider/model
- **Reusable Templates** - Use same persona across different providers
- **Shared Library** - 56 pre-configured personas included
- **Ownership Tracking** - Creator attribution for all personas
- **Full CRUD** - Create, Read, Update, Delete operations
- **Favorites** - Star any persona to pin it to the top of the list; toggle via inline star button or `PATCH /personas/{persona}/favorite`
- **JSON Import** - Import a persona from a `.json` file on the create page
- **JSON Export** - Export any persona from the edit page as a portable `.json` file
- **Template Download** - Download a sample template to understand the persona JSON format

### 💬 Conversation Orchestration
- **Automated Turns** - Let AI agents talk to each other
- **Real-time Streaming** - See responses as they're generated
- **Floating Live Logs** - Bottom-right live event panel with chunk/status/completion/error entries
- **WebSocket Support** - Laravel Reverb for live updates
- **Manual Controls** - Start, stop, and resume conversations
- **Per-Conversation Provider/Model Selection** - Choose any provider and model for each agent
- **Dynamic Model Fetching** - Query providers for available models (344+ models supported)
- **Ollama Auto-Discovery** - Detect local models from Ollama native and OpenAI-compatible model endpoints
- **Live Pricing Display** - See cost per 1M tokens before starting conversation
- **Discord Broadcast Toggle** - Optional per-conversation broadcast to Discord
- **Webhook Resolution Chain** - Conversation override -> user default -> global fallback
- **Discord Threading** - Auto-create/continue a dedicated thread per conversation
- **Advanced Chat Controls**:
  - **Max Rounds** - Set conversation length limit (1-500 turns)
  - **Memory Window** - Configure how many recent messages are included each turn (1-50)
  - **Cross-Chat Memory Toggle** - Enable/disable embedding-based memory retrieval per session
  - **Memory Recall Depth** - Number of retrieved memory snippets to inject (1-20)
  - **Memory Similarity Threshold** - Tune retrieval strictness (0.00-1.00)
  - **Stop Word Detection** - Automatic conversation stopping based on keywords
  - **Stop Word Threshold** - Configurable similarity threshold (0.1-1.0)
  - **Custom Stop Words** - Define conversation-ending phrases
- **Status Tracking** - Active, completed, failed states
- **Message History** - Complete conversation logs
- **Transcript Export** - Download conversations as CSV
- **Long Running** - 20-minute timeout support
- **Stale Session Auto-Recovery** - Scheduler-driven `chat:recover-stale` job re-dispatches stuck sessions
- **Stale Lock Force-Unlock** - Automatically releases orphaned `RunChatSession` overlap locks after configured threshold
- **Structured Failure Context** - Empty-turn hard-fail path stores detailed error metadata for diagnostics

### 🔐 Authentication & Authorization
- **User Registration** - Laravel Breeze integration
- **Role-Based Access** - User and Admin roles
- **Session Management** - Secure session handling
- **Profile Management** - Update user information
- **Avatar Upload Persistence** - Multipart-safe profile avatar saves with method-override support
- **Email Verification** - Optional email confirmation
- **Password Reset** - Forgot password functionality
- **Remember Me** - Persistent login option
- **Username Login** - Login with username OR email
- **Read-Only Safety Mode** - Optional global write protection
  - Toggle with `APP_READ_ONLY_MODE=true`
  - Blocks mutating requests and non-infrastructure SQL writes
  - Keeps safe read endpoints available (e.g. analytics SQL runner, persona generator)

### 🔑 API Key Management
- **Encrypted Storage** - AES-256 encryption for keys
- **Per-User Isolation** - Each user manages their own keys
- **Multi-Provider** - Support for multiple AI services
- **Active/Inactive Toggle** - Enable/disable keys
- **Masked Display** - Security through obscurity
- **Real-time Validation** - Test keys before saving
- **Status Indicators** - Visual badges (validated/invalid/untested)
- **Error Tracking** - Store and display validation errors
- **Last Validated** - Timestamp tracking

### 🪙 Personal Access Tokens (`/personal-tokens`)
- **Sanctum Token Management** - Create and revoke personal API tokens for Chat Bridge access
- **Named Tokens** - Label each token for easy identification
- **One-Time Display** - Plaintext token shown once at creation and never again
- **Last Used Tracking** - See when each token was last used
- **Ownership Enforcement** - Users can only delete their own tokens
- **API Compatibility** - Accepted anywhere the shared `CHAT_BRIDGE_TOKEN` env variable is used

### 🧠 RAG (Retrieval-Augmented Generation)
- **Vector Database** - Qdrant for similarity search
- **Automatic Embeddings** - Generated for all messages
- **Embedding Tracking** - Per-message status (`embedded`, `failed`, `skipped`), attempt counter, last error, and exponential-backoff retry scheduling
- **Semantic Search** - Find relevant past conversations
- **Persistent Memory** - AI remembers across sessions
- **Session Memory Profiles** - Each conversation can tune history window and retrieval behavior
- **Context Injection** - Relevant history added to prompts
- **Sub-10ms Retrieval** - Lightning-fast vector search
- **Scalable Storage** - Efficient compression and indexing
- **Document RAG** - Attach `.txt`, `.md`, `.csv`, `.json`, `.docx`, or `.pdf` files as RAG context; injected even when cross-chat memory is disabled

### 🤖 AI Chatbot (`/transcript-chat`)
- **Ask the Archive** - Natural-language Q&A over all your chat transcripts
- **Semantic Retrieval** - Questions are matched to transcript excerpts via Qdrant vector search
- **Grounded Answers** - OpenAI generates answers using only the retrieved context
- **API Key Integration** - Uses the user's stored OpenAI API key (falls back to global config)
- **Dashboard Badge** - Shows "Ready" or "API key required" on the dashboard card
- **Conversation Filter** - Optionally scope questions to a single conversation
- **Source Attribution** - Expandable source list showing which transcript excerpts were used and their match scores
- **Settings Panel** - Per-session configuration:
  - System prompt override
  - Model selection (gpt-4o-mini / gpt-4o)
  - Temperature, max tokens, sources limit, minimum similarity score
  - Visual indicator when settings differ from defaults; one-click reset

### 📊 Analytics & Insights
- **Activity Dashboard** - 7-day trend visualization
- **Top Personas** - Most-used agents statistics
- **Message Tracking** - Count and token usage
- **Automatic Cost Sync** - Provider/model pricing captured from model query API and stored for analytics
- **Full SQL Playground** - Read-only `SELECT` / `WITH` query execution
  - Built-in SQL examples
  - Schema browser for key tables
  - Inline autocomplete for keywords/tables/columns
  - Row limit controls (1-500)
- **Query System** - Advanced filtering options
  - Keyword search
  - Date range filters
  - Persona filters
  - Role filters (user/assistant)
  - Status filters
- **CSV Export** - Up to 1000 records
- **Real-time Metrics** - Live conversation statistics
- **Charts & Graphs** - Recharts integration
- **Chart-Safe Payload Contracts** - Normalized numeric series data for stable chart rendering
- **Timezone-Safe Trend Labels** - Day labels render reliably from `YYYY-MM-DD` trend dates

---

## Admin Tools

### 🧪 System Diagnostics (`/admin/system`)

**11 Diagnostic Actions:**

1. **Health Check** 🏥
   - PHP version
   - Laravel version
   - Database connection
   - Storage permissions
   - Queue status
   - Cache drivers
   - AI services
   - User/Persona counts

2. **Fix Permissions** 🔐
   - Set directory permissions (755)
   - Set file permissions (644)
   - Recursive application
   - Verification checks

3. **Clear All Caches** 🗑️
   - Config cache
   - Route cache
   - View cache
   - Event cache
   - Application cache

4. **Optimize Application** ⚡
   - Cache configs (production)
   - Cache routes (production)
   - Cache views (production)
   - Cache events (production)
   - Environment-aware

5. **Validate AI Services** 🤖
   - Test all enabled AI drivers
   - Check API connectivity
   - Display status for each
   - Error reporting

6. **Check Database** 🗄️
   - Connection test
   - Driver information
   - Migration count
   - Table record counts
   - Database name

7. **Run Tests** 🧪
   - Execute PHPUnit suite
   - Display results
   - Stop on failure option
   - Real-time output

8. **Fix Code Style** ✨
   - Laravel Pint execution
   - PSR-12 compliance
   - Automatic fixes
   - Result reporting

9. **Update Laravel** ⬆️
   - Runs `composer update laravel/framework --with-all-dependencies`
   - No-interaction safe execution
   - Full command output in diagnostics console
   - Skips in testing environment

10. **Reload PHP-FPM** 🔄
    - Gracefully reloads PHP-FPM workers without dropping connections
    - Useful after deploying code changes

11. **Embeddings Key Management** 🔑
    - Update, test, and clear the OpenRouter API key used for embeddings
    - Test validates connectivity against the configured embedding model
    - Shows last 4 characters of stored key for identification

**System Information Panel:**
- PHP & Laravel versions
- Environment (local/production)
- Debug mode status
- Memory limit
- Max execution time
- Disk space (free/total/%)
- Cache driver
- Queue driver
- Database type
- Storage writable status
- Bootstrap cache writable

### 🔭 Laravel Telescope (`/telescope`)

**Monitoring Features:**
- **Requests** - HTTP request/response tracking
- **Exceptions** - Error and exception logging
- **Queries** - Database query profiling
- **Jobs** - Queue job monitoring
- **Mail** - Email tracking
- **Notifications** - Notification logs
- **Logs** - Application log aggregation
- **Events** - Event firing history
- **Cache** - Cache hit/miss tracking
- **Redis** - Redis command monitoring

**Features:**
- Dark theme enabled (`Telescope::night()`)
- Admin-only access (role-based gate)
- Production filtering (errors/failures only)
- Sensitive data hiding
- Configurable watchers
- Tag-based filtering
- Search functionality

### 🔴 Redis Dashboard (`/admin/redis`)

**Operational Features:**
- **Connection Health** - Ping status and connection details
- **Memory Metrics** - Used memory, RSS, peak, fragmentation ratio
- **Cache Efficiency** - Hit/miss counts and hit rate
- **Traffic Snapshot** - Commands/sec and connected clients
- **Keyspace Breakdown** - Per-DB key counts and expiration stats
- **Live Stats Endpoint** - `/admin/redis/stats` for refreshable dashboard data

### 🐛 Laravel Debugbar

**Profiling Features:**
- **Query Profiling** - SQL queries with timing
- **Timeline** - Visual request timeline
- **Memory Usage** - PHP memory consumption
- **Route Info** - Current route details
- **Views** - Rendered views list
- **Session Data** - Session variables
- **Request Data** - All request parameters
- **Response Data** - Response details
- **Auth User** - Current authenticated user

**Features:**
- Auto-disabled in production
- Collapsible bar
- Detailed tabs
- Ajax request tracking
- Exception catching

### 📈 Performance Monitor (`/admin/performance`)

**Live Monitoring Features:**
- **Latency Window** - Last 5 minutes with avg/p95/max response time
- **DB Timing Window** - Avg/p95/max DB query time and query volume
- **Route Breakdown** - Slowest routes by average latency
- **Throughput Series** - Requests per minute (15-minute timeline)
- **Slow Requests Feed** - Recent requests over 1 second
- **Slow Query Feed** - Recent SQL queries over 100ms
- **Queue Health** - Queued + failed jobs snapshot
- **Runtime Context** - Memory, load average, DB/cache drivers

### 🧭 MCP Utilities (`/admin/mcp-utilities`)

**MCP Operations Features:**
- **Embedding Sync Controls** - Compare and populate missing embeddings from the admin UI
- **Provider Capability Checks** - Validate tool support status for active providers (including Ollama)
- **Live MCP Traffic Watch** - Inspect recent MCP/tool execution events with provider filtering
- **Auto-Refresh Monitoring** - Polling controls for near-real-time troubleshooting
- **Admin API Endpoints** - Sanctum-authenticated API routes for embedding compare, populate, flush, and traffic (under `/api/admin/mcp-utilities`)

---

## UI/UX Features

### 🎨 Midnight Glass Design System

**Color Palette:**
- `zinc-950` - Deep black background
- `zinc-900` - Surface backgrounds
- `zinc-800` - Elevated surfaces
- `zinc-700-500` - Borders and dividers
- `zinc-400-100` - Text hierarchy
- `white/5-10` - Subtle overlays

**Glassmorphic Components:**
- `.glass-panel` - Frosted glass effect
- `.glass-panel-hover` - Enhanced hover states
- `backdrop-blur-xl` - Blur effect
- Semi-transparent backgrounds
- Subtle border overlays

**Gradient System:**
- **Blue-Cyan** - Primary actions (`from-blue-500 to-cyan-500`)
- **Purple-Pink** - Secondary actions (`from-purple-500 to-pink-500`)
- **Emerald-Teal** - Success states (`from-emerald-500 to-teal-500`)
- **Orange-Red** - Warning/danger (`from-orange-500 to-red-500`)
- **Violet-Purple** - Admin features (`from-violet-500 to-purple-500`)

**Typography:**
- **Font Family** - Figtree (Google Fonts)
- **Weights** - 400 (regular), 500 (medium), 600 (semibold)
- **Size Scale** - Tailwind default (text-sm to text-5xl)

**Interactive Elements:**
- Smooth hover transitions (300ms)
- Scale transforms on hover (scale-105)
- Glow effects on focus
- Opacity changes
- Border color transitions
- Custom scrollbars (dark themed)

**Custom CSS Classes:**
- `.input-dark` - Dark-themed form inputs
- `.btn-dark` - Dark-themed buttons
- `.card-dark` - Card components
- `.text-gradient-*` - Gradient text utilities
- `.glow-*` - Glowing shadow effects
- `.scrollbar-dark` - Custom scrollbars

**Responsive Design:**
- Mobile-first approach
- Breakpoints: sm (640px), md (768px), lg (1024px), xl (1280px)
- Responsive grids
- Mobile navigation
- Touch-friendly targets

---

## Security Features

### 🔒 Data Protection
- **API Key Encryption** - AES-256 encryption at rest
- **Password Hashing** - bcrypt with salt
- **SQL Injection Prevention** - Eloquent ORM parameterization
- **XSS Protection** - React/Blade auto-escaping
- **CSRF Protection** - Token-based CSRF guards
- **Session Security** - HTTP-only cookies
- **Secure Headers** - Security headers configured

### 🛡️ Access Control
- **Role-Based Access** - User/Admin separation
- **Per-User Data Isolation** - Users see only their data
- **API Key Isolation** - Strict per-user key management
- **Admin-Only Routes** - Middleware protection
- **Gate Definitions** - Laravel Gate for Telescope
- **Ownership Verification** - Model policy checks

### 🔐 Authentication Security
- **Rate Limiting** - Login attempt throttling (5 attempts)
- **Email Verification** - Optional email confirmation
- **Password Reset** - Secure token-based reset
- **Session Timeout** - Configurable session lifetime
- **Remember Me** - Optional persistent login

---

## Performance Features

### ⚡ Speed Optimizations
- **Query Optimization** - Eager loading, N+1 prevention
- **Database Indexing** - Strategic index placement
- **Caching** - Redis/File caching
- **Route Caching** - Production route compilation
- **Config Caching** - Configuration optimization
- **View Caching** - Blade template caching
- **Asset Optimization** - Vite build optimization

### 🚀 Scalability
- **Queue System** - Async job processing
- **Parallel Queue Workers** - 4 concurrent workers via supervisord (configurable via `numprocs` in `docker/supervisor/queue-workers.conf`)
- **WebSocket Offloading** - Separate Reverb server
- **Database Connection Pooling** - Efficient connections
- **Redis Optimization** - Command pipelining
- **Vector Search** - Sub-10ms Qdrant queries
- **Long-Running Support** - 20-minute conversation timeout

### 📊 Monitoring
- **Application Logs** - Structured logging
- **Error Tracking** - Exception monitoring
- **Query Profiling** - Database performance
- **Job Monitoring** - Queue job tracking
- **Cache Hit Rates** - Cache performance metrics

---

## Developer Experience

### 🔧 Development Tools
- **Laravel Telescope** - Complete application insight
- **Laravel Debugbar** - Real-time profiling
- **Laravel Pail** - Beautiful log viewer
- **Laravel Pint** - Code style fixer (PSR-12)
- **PHPUnit/Pest** - Testing framework
- **Hot Module Replacement** - Vite HMR

### 📝 Code Quality
- **PSR-12 Standard** - PHP coding standard
- **Type Hints** - Strict type declarations
- **DocBlocks** - Comprehensive documentation
- **Consistent Naming** - Clear naming conventions
- **DRY Principle** - Don't Repeat Yourself
- **SOLID Principles** - Clean architecture

### 🐳 Docker Support
- **Complete Stack** - All services containerized
- **Development Environment** - Consistent across machines
- **Production Ready** - Docker Compose for deployment
- **Volume Mounts** - Persistent data storage
- **Health Checks** - Service health monitoring
- **Environment Variables** - Configurable via .env

### 📚 Documentation
- **README.md** - Comprehensive project overview
- **FEATURES.md** - Detailed feature list (this file)
- **DOCKER.md** - Docker setup guide
- **RAG_GUIDE.md** - RAG functionality guide
- **ROADMAP.md** - Future development plans
- **CODE_OF_CONDUCT.md** - Contribution guidelines

### 🧪 Testing
- **Feature Tests** - End-to-end functionality
- **Unit Tests** - Isolated component testing
- **API Tests** - Endpoint testing
- **Integration Tests** - Service integration
- **Test Coverage** - Code coverage reporting
- **CI/CD** - GitHub Actions integration

---

## Additional Features

### 🌐 Internationalization
- **Localization Support** - Laravel's built-in i18n
- **Translation Files** - JSON language files
- **Multi-Language Ready** - Prepared for translation
- **Date Formatting** - Locale-aware dates

### 📱 Progressive Web App (PWA) Ready
- **Service Worker Support** - Offline capabilities
- **App Manifest** - Installable as app
- **Responsive Design** - Mobile-optimized
- **Touch Gestures** - Mobile-friendly interactions

### 🔗 API & Integration
- **RESTful API** - Clean API design
- **Dual API Authentication** - Accepts shared env `CHAT_BRIDGE_TOKEN` (backward-compatible) or personal Sanctum tokens
- **MCP API Auth** - MCP routes require personal Sanctum tokens for user-scoped context
- **Webhook Support** - Event notifications
- **MCP Integration** - Model Context Protocol
- **External API** - Chat bridge endpoint (`POST /api/chat-bridge/respond`)
- **Provider Models API** - Dynamic model listing endpoint
  - `/api/providers/models?provider=<name>`
  - Real-time model discovery from AI providers
  - Returns models with pricing, context length, and metadata

### 🎯 Usability
- **Intuitive Navigation** - Clear menu structure
- **Search Functionality** - Quick access to data
- **Keyboard Shortcuts** - Power user features
- **Contextual Help** - Inline guidance
- **Error Messages** - Clear, actionable errors
- **Loading States** - Visual feedback
- **Empty States** - Helpful placeholders

---

**Total Features: 200+**

Built with ❤️ using Laravel and React
