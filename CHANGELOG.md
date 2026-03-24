# Changelog

All notable changes to Chat Bridge will be documented in this file.

## [Unreleased] - 2026-03-24 (Fix: Provider-aware model selection in Orchestrator wizard)

### 🐛 Fix: Invalid provider/model combos no longer possible in Orchestrator steps

- **Root cause** — The wizard AI prompt listed providers but no models, so Claude could emit any model string for any provider (e.g. `provider: deepseek, model: gemini-2.0-flash`). There was no UI step to review/correct model selection before materializing.
- **Wizard draft step editor** — The draft review panel now expands each step with Agent A / Agent B columns, each containing a provider dropdown and a live model dropdown. Changing the provider fetches models from `/api/providers/models` and resets the model field, exactly like `Chat/Create.jsx`. The corrected configs are merged back into the draft on "Save & Create".
- **Wizard system prompt** — AI is now instructed to always set `model_a` and `model_b` to `null` so the user picks the correct model in the review step.
- **Backend validation** — `provider_a` / `provider_b` on steps now validate against the known provider list (`in:openai,anthropic,gemini,openrouter,deepseek,ollama,lmstudio,bedrock,mock`) in both `StoreOrchestratorRequest` and `UpdateOrchestratorRequest`.

## [Unreleased] - 2026-03-24 (Bug Fix: AI Driver 400 / Temperature)

### 🐛 Fix: 400 error from AI providers (body logged, temperature wired, retry hardened)

- **Root cause** — `OpenAIDriver::sendChatRequest` never passed `temperature` to the HTTP payload and swallowed 400 response bodies entirely, making provider rejections invisible in logs. A misconfigured step (e.g. `provider: deepseek`, `model: gemini-2.0-flash`) would crash with `HTTP request returned status code 400` and no further detail.
- **Temperature** — `sendChatRequest` and `sendToolChatRequest` now accept and forward the `temperature` parameter in every outgoing payload. Same fix applied to `OpenRouterDriver::chat()` and `streamChat()`.
- **400 logging** — Both `sendChatRequest` and `sendToolChatRequest` log `provider_url`, `model`, `status`, and the full response `body` on any failed response, so provider-side errors (unknown model, invalid param, quota, etc.) are immediately visible in logs.
- **Retry hardening** — `buildRequest()` retry condition is now explicit: retry on `ConnectionException`, `429`, and `5xx`; never retry `4xx` client errors (bad request, auth, not found). Added `throw: false` so the failed response is returned and handled by the existing `$response->failed()` guards rather than auto-thrown as a generic `RequestException`.
- **OpenRouter stream guard** — `OpenRouterDriver::streamChat()` now checks `$response->failed()` and throws with the response body before attempting to read the SSE stream, preventing a stream-read loop on a non-streaming error response.

## [Unreleased] - 2026-03-24 (Bug Fix: Persona Guidelines)

### 🐛 Fix: `foreach` crash on string-typed persona guidelines

- **Root cause** — The `guidelines` column on `personas` was stored as a JSON-encoded string (e.g. `"Be concise."`) instead of a JSON array (`["Be concise."]`) for some personas created via older UI paths. Laravel's `json` cast returns a PHP `string` for that case, and the `?? []` null-guard did not protect against non-null strings. Every conversation turn using one of these personas crashed with `foreach() argument must be of type array|object, string given`.
- **Data migration** (`fix_persona_guidelines_string_to_array`) — Scans all existing personas and wraps any JSON-string guidelines value into a single-element JSON array. Confirmed 0 remaining bad rows after migration.
- **Model cast** — Changed `guidelines` cast from `'json'` to `'array'` on `Persona` model (`json_decode($v, true)`), which is the correct cast for an array field.
- **Guard method** — Added `ConversationService::normalizeToArray()` that safely coerces any value (array, `Collection`, JSON-array string, or unknown type) to a plain PHP array, logging a warning for unexpected shapes instead of crashing.
- **Applied at two callsites** — `ConversationService::buildMessages()` line 140 (primary crash site) and `templateFileContextMessage()` RAG files payload; same inline guard added to `ProcessConversationTurn` line 60 which contains the identical pattern.
- **Non-fatal design** — Malformed guidelines are skipped; the turn still generates a response rather than failing the conversation.
- **11 regression tests** added in `PersonaGuidelinesNormalizationTest` covering array/null/JSON-string/multi-line-string payloads through both `ProcessConversationTurn` and `ConversationService::generateTurn`, plus RAG file-path normalization and the migration logic.

## [Unreleased] - 2026-03-15 (AI Orchestrator)

### 🎛️ AI Orchestrator
- New `/orchestrator` module for defining and running automated multi-step AI conversation pipelines.
- **Wizard** (`/orchestrator/wizard`) — Claude-powered conversational setup wizard. Asks clarifying questions, then emits a structured JSON draft inside `<orchestration>` tags. User reviews the draft and clicks "Save & Create" to materialize it.
- **Orchestration model** — Named pipelines with optional cron schedule, timezone, status tracking (`idle` | `running` | `paused` | `completed` | `failed`), and soft deletes.
- **Steps** — Ordered steps with per-step persona/provider/model overrides, three input sources (`static`, `previous_step_output`, `variable`), four output actions (`log`, `pass_to_next`, `store_as_variable`, `webhook`), condition gates, and `pause_before_run` flag.
- **Runs** — `OrchestratorRun` and `OrchestratorStepRun` records capture full execution history: status, timing, output summaries, error messages, and links to underlying conversations.
- **Variable bag** — Runtime `variables` JSON on each run; steps store/read named values using dot notation.
- **Condition evaluation** — `contains`, `not_contains`, `equals`, `regex` conditions against previous step output; unmatched steps are `skipped`.
- **`RunOrchestration` job** — Executes steps sequentially, dispatches the existing `RunChatSession` job synchronously per step, captures output, applies output actions, handles pause/resume flow.
- **`ResumeOrchestratorRun` job** — Picks up execution from the paused step when a user approves.
- **Artisan commands** — `orchestration:run {orchestration}` for manual triggers; `orchestration:schedule` for cron dispatch (registered in `routes/console.php`, runs every minute via `withoutOverlapping`).
- **Real-time events** — `OrchestratorRunCompleted`, `OrchestratorStepStarted`, `OrchestratorStepPaused` broadcast on the user's private channel.
- **Frontend** — `Orchestrator/Index`, `Wizard`, `Show`, `Runs/Index`, `Runs/Show` pages in the Midnight Glass design system.
- **Dashboard card** — Orchestrator module added to the dashboard with violet-purple gradient.
- **27 new tests** — Feature tests covering auth, CRUD, run dispatch, authorization, wizard validation; unit tests for all condition evaluation paths.

## [Unreleased] - 2026-03-15

### ⭐ Persona Favorites
- Added `is_favorite` boolean field to personas (default `true` for all new personas).
- New `PATCH /personas/{persona}/favorite` route (`personas.favorite`) to toggle favorite status.
- Personas list now sorts favorites to the top, then by most recently created.
- Star toggle rendered inline on the Personas index page.
- Added a clear-favorites action and favorites-only filter controls on the Personas index page.

### 🧭 MCP Utilities Docs & Endpoint Examples
- Updated `/admin/mcp-utilities` endpoint table to show API-key-ready `curl` examples (including Authorization headers and JSON payload where needed).
- Updated `README.md` and `MCP.md` to document Personal Access Token usage for `/api/mcp/*` and `/api/admin/mcp-utilities/*` routes.

### 🧠 Embedding Tracking & Retry
- New `embedding_status`, `embedding_attempts`, `embedding_last_error`, `embedding_skip_reason`, `embedding_last_attempt_at`, and `embedding_next_retry_at` columns on `messages` table for observability.
- `MessageEmbeddingPopulator` service handles embedding generation with exponential backoff (60s → 5m → 15m → 30m), max 5 attempts.
- `PopulateMessageEmbeddingJob` dispatched per message; skips already-embedded or `skipped`-status messages and respects `embedding_next_retry_at` cooldown.
- New `ai.embedding_population_max_attempts` and `ai.embedding_input_max_chars` config keys.

### 📄 Document RAG — DOCX & PDF Support
- Template file context now supports `.docx` and `.pdf` uploads in addition to `.txt`, `.md`, `.csv`, and `.json`.
- DOCX text extracted via `ZipArchive` from `word/document.xml`, paragraph/table tags converted to line breaks.
- PDF text extracted via regex-based PDF string literal parsing with escape decoding and octal replacement.
- All extracted content normalized: UTF-8 validation, whitespace collapsing, and length capping via `ai.embedding_input_max_chars`.
- File context injection decoupled from cross-chat memory — template docs are injected even when cross-chat RAG is disabled.

### 📋 Transcript Enhancements
- Transcripts now include a **Chat Header** section summarising: session label, starter prompt preview, memory window, cross-chat memory state, and attached RAG document names.
- Each message entry now includes **Provider** and **Model** fields for traceability.

### 🔧 MCP Tools Fix
- Tool request construction switched from `new Request([...])` to `Request::create('/', 'GET', [...])` — fixes query-string population for `searchConversations`, `getContextualMemory`, and `getRecentChats`.
- `conversation_id` parameter type corrected from `integer` to `string` in the `getConversation` tool schema.
- Added `GET /api/mcp/contextual_memory` alias (underscore) alongside the existing hyphenated route for broader client compatibility.

### 🛠️ Admin MCP Utilities API
- New API routes under `/api/admin/mcp-utilities` (require Sanctum token + admin role):
  - `GET /embeddings/compare` — compare DB vs Qdrant embedding counts.
  - `POST /embeddings/populate` — trigger batch embedding backfill.
  - `POST /flush` — flush MCP caches.
  - `GET /traffic` — retrieve recent MCP tool execution events.

### 🔄 Scheduler
- Replaced `pulse:snapshot` with `pulse:check` in the console schedule for Laravel Pulse compatibility.

## [Unreleased] - 2026-03-12

### 🔑 Personal Access Tokens
- New `/personal-tokens` page for users to create and manage Sanctum personal access tokens.
- Tokens can be used in place of the shared `CHAT_BRIDGE_TOKEN` env variable for API authentication.
- Token name input + copy-on-creation modal with one-time plaintext display.
- Full delete support with per-user ownership enforcement.

### 🔐 API Authentication Upgrade
- Chat Bridge API (`POST /api/chat-bridge/respond`) now accepts either the legacy shared env token or a user personal Sanctum token via `EnsureChatBridgeOrSanctumToken` middleware.
- MCP API routes (`/api/mcp/*` and `POST /api/mcp`) now require a personal Sanctum token (`EnsureSanctumToken`), scoping all tool calls to the authenticated user.

### 🧠 Embedding Provider Priority Swap
- `EmbeddingService` now uses **OpenRouter as the primary embedding provider** and falls back to OpenAI when no OpenRouter key is configured.
- Removes the previous OpenAI-primary / OpenRouter-fallback order that required a failed OpenAI call before trying OpenRouter.

### 🔧 System Diagnostics Additions
- **Embeddings Key panel** — Admin can update, test, and clear the `OPENROUTER_API_KEY` used for embeddings directly from `/admin/system`.
- **Reload PHP-FPM** — New diagnostic action to gracefully reload PHP-FPM without downtime.

### 🎨 Dashboard Live Status Redesign
- Active-conversation indicator on the dashboard now shows an animated glow card with pulsing rings, waveform bars, and a per-session label/turn counter.
- Idle state simplified to a muted indicator.

### 🗄️ Database
- Migration to widen `model_prices` decimal columns for providers that return prices with more decimal places.

### 🖼️ Favicons
- Added `public/favicon-32.png` and `public/favicon-192.png` for browser tab and PWA icon use.

## [1.0.0] - 2026-03-10

### 🛡️ Stream Resilience
- Fixed "Unable to read from stream" crash when TCP connection drops mid-response during large AI outputs (notably via OpenRouter with verbose models like `openai/gpt-oss-120b`).
- `OpenAIDriver` and `OpenRouterDriver` now catch `RuntimeException` from the PSR-7 stream reader after content has already been yielded — logging a warning and breaking cleanly rather than crashing the job.
- Added `isStreamReadError()` helper recognising Guzzle/PSR-7 stream failure signatures: `unable to read from stream`, `stream is detached`, `connection reset`, `broken pipe`.

### 🔧 MCP Tool Filtering
- Model selector on the chat creation page now hides tool-call-incapable models when MCP is enabled.
- All provider model list endpoints return a `supports_tools` flag derived from live API metadata.
- Amber hint beneath the model count shows how many models were hidden due to missing tool support.

### 🌐 Live Provider Model Queries
- All AI providers now query their live APIs for available models using the configured key rather than returning static lists.
- OpenRouter: reads `supported_parameters` to set `supports_tools`.
- DeepSeek: queries `https://api.deepseek.com/v1/models` (OpenAI-compatible); infers tool support from model ID.
- Bedrock: queries `https://bedrock.{region}.amazonaws.com/foundation-models` with full SigV4 GET signing; filters to `ON_DEMAND` + `TEXT` output models.
- `ProviderController::getApiKey()` falls back to any active key for the provider if no user-scoped key exists.

### 🚧 Maintenance Banner
- Admin panel toggle to display a site-wide amber "Under Construction" banner during maintenance windows.
- State persisted to `storage/app/maintenance_banner.json` — survives cache clears and container restarts.
- Shared to every page as an Inertia prop via `HandleInertiaRequests`; rendered in `AuthenticatedLayout` with no extra client requests.

### ✏️ Edit & Retry for Failed Conversations
- New "Edit & Retry" button on failed conversation pages opens a modal to change provider/model for Agent A and/or Agent B before re-dispatching from the last completed round.
- `ChatController::retryWith()` validates and updates only the fields provided, records `retry_model_change` metadata, and resumes from remaining rounds.
- Route: `POST /chat/{conversation}/retry-with` (`chat.retry-with`).
- 4 new feature tests covering model update, both-agent update, non-failed rejection, and ownership enforcement.

---

## [Unreleased] - 2026-03-05

### 📊 Analytics Chart Data Integrity
- Hardened analytics chart input normalization on `Analytics/Index` to guarantee chart-safe numeric values.
- Fixed date label rendering for `YYYY-MM-DD` trend data to avoid timezone-shifted chart labels.
- Aligned `/analytics/metrics` payload shape with the dashboard by including `openRouterStats`.
- Added chart payload regression coverage in `AnalyticsTest` to enforce key/type expectations.

### 🧠 OpenRouter Stats Safety
- OpenRouter dashboard stats are now skipped in `testing` environment, avoiding external HTTP noise during test runs while preserving runtime behavior.

### 🖼️ Profile Avatar Save Reliability
- Updated profile avatar form submission to use multipart-safe method override (`POST` + `_method=patch`) so avatar uploads persist correctly.

### 🔴 Admin Redis Dashboard
- Added a dedicated admin Redis dashboard at `/admin/redis` with overview metrics (status, memory, cache efficiency, keyspace).
- Added live stats endpoint at `/admin/redis/stats` and a Boost Dashboard quick-link to the new Redis page.
- Added `AdminRedisDashboardTest` coverage for access control and stats payload structure.

### 🐳 Docker / Composer Compatibility
- Added PHP `gd` extension to Docker image builds to satisfy `phpoffice/phpspreadsheet` platform requirements used by `maatwebsite/excel`.

## [Unreleased] - 2026-03-04

### 🧠 Session Memory Controls
- Added per-session agent memory settings on chat creation:
  - `memory_history_limit` (recent turn context window)
  - `memory_rag_enabled` (cross-chat memory toggle)
  - `memory_rag_source_limit` (retrieved memory depth)
  - `memory_rag_score_threshold` (retrieval strictness)
- Chat creation now stores memory settings in conversation metadata and applies them at runtime.
- Turn generation now respects per-conversation history window (instead of a fixed value).

### 🛡️ Live Status Reliability
- Hardened `/chat/live-status` against cache backend outages:
  - stop-signal reads now fail open and return `stop_requested=false` instead of breaking the endpoint.
- Header live status widget now remains functional even when Redis/cache connectivity is temporarily unavailable.

### 🤖 Ollama Model + Tooling Support
- Expanded Ollama model discovery to query both common local endpoints:
  - Ollama native tags (`/api/tags`)
  - OpenAI-compatible model listing (`/v1/models` or `/models`)
- Added Ollama tool-calling support in the provider driver via `/api/chat` tool payloads.
- MCP traffic tooling now records provider/model context so Ollama tool activity is visible in admin monitoring.

### 🧭 MCP Utilities Traffic Watch
- Added MCP traffic monitor support class and event logging.
- Added admin MCP traffic endpoint: `/admin/mcp-utilities/traffic`.
- Added live traffic controls in the MCP Utilities page to inspect recent tool calls by provider (including Ollama).

### 🚨 Empty-Turn Failure Behavior
- Removed the static assistant fallback text (`"I need to regroup for a moment..."`) from runtime recovery.
- Empty/whitespace turn failures now exhaust retry/rescue attempts and then mark the conversation failed with structured context for debugging.
- Session UI now surfaces `last_error_context` metadata to make provider/model failure diagnosis explicit.

## [Unreleased] - 2026-02-27

### 🤖 **AI Chatbot (Ask the Archive)**
- Added **AI Chatbot** dashboard card linking to `/transcript-chat` with an API key status badge (green "Ready" / amber "API key required") based on the user's stored OpenAI key
- AI Chatbot now resolves the OpenAI API key from the user's saved API Keys first, falling back to the global `OPENAI_API_KEY` config — consistent with the rest of the app
- Added collapsible **Settings panel** on the Ask the Archive page:
  - **System Prompt** — override the default assistant instructions per session
  - **Model** — choose `gpt-4o-mini`, `gpt-4o`, or `gpt-4o (nov)`
  - **Temperature** slider (0–1, default 0.3)
  - **Max Tokens** input (256–4096, default 1024)
  - **Sources** — number of transcript excerpts to retrieve (1–10, default 6)
  - **Min Score** slider — similarity threshold (0.05–1, default 0.3)
  - Settings button highlights when changed from defaults; "Reset to defaults" link restores them
- Lowered default similarity score threshold from **0.65 → 0.30** — typical conversational queries score 0.23–0.41 so the previous threshold silently returned no results
- Fixed `qdrant:init` chicken-and-egg bug: the command used `isAvailable()` (checks for collection existence) as a pre-flight check, causing it to fail before the collection was ever created; added `RagService::ping()` which hits `/collections` to verify Qdrant is reachable regardless of collection state
- Shortened the chat window height from `h-screen` to `calc(100vh - 9rem)` to fit cleanly within the visible viewport

### ⚡ **Parallel Queue Workers**
- Queue container now runs **4 parallel workers** via `supervisord` instead of a single `php artisan queue:work` process — up to 4 conversations can now stream simultaneously
- Added `docker/supervisor/queue-workers.conf` with `numprocs=4`, `--max-jobs=500`, and `stopwaitsecs=1200`
- Updated `docker-compose.yml` queue service command and added `./docker` volume mount so the supervisor config is live without a rebuild
- To increase concurrency, bump `numprocs` in `docker/supervisor/queue-workers.conf` and restart the queue container

## [Unreleased] - 2026-02-23

### 🛡️ Reliability & Error Handling
- Added SQLite bootstrap guard in `AppServiceProvider` to auto-create missing `database.sqlite` files when `DB_CONNECTION=sqlite`
- Updated Gemini default model from `gemini-1.5-flash` to `gemini-2.0-flash`
- Improved Gemini driver error handling with actionable messages for model/API-version mismatches
- Normalized Gemini API key validation failures to user-facing guidance instead of raw provider payload errors
- Added targeted tests for SQLite bootstrap behavior and Gemini unsupported-model failure handling

### 🎨 UI
- Refreshed the chat session create page copy/labels for current terminology and added a no-personas empty-state helper

### 📊 **Analytics SQL Playground**
- Upgraded `/analytics/query` into a full SQL runner with read-only guardrails (`SELECT` / `WITH` only)
- Added schema explorer + SQL keyword/table/column autocomplete in the query editor
- Added built-in SQL examples with authenticated user template hydration
- Added execution telemetry on results (`row_count`, `execution_ms`, truncation indicator)
- Added explicit row limits (1-500) and single-statement enforcement for safety

### 💰 **Pricing & Analytics Accuracy**
- Added automatic provider/model pricing persistence in the model listing API (`GET /api/providers/models`)
- New `model_prices` table stores prompt/completion pricing per provider/model
- Analytics now prefers stored model pricing for cost calculations, then falls back to static config defaults
- Added tests to verify pricing upsert flow and analytics DB-pricing precedence

### 📈 **Performance Monitoring**
- Added admin performance monitor page at `/admin/performance`
- Added `/admin/performance/stats` JSON endpoint with rolling request/DB performance snapshots
- Added request instrumentation middleware for latency, memory, SQL totals, and slow query capture
- Added route latency breakdown, throughput series, slow request feed, and queue/runtime health metrics

### 🔔 **Discord Conversation Broadcasts**
- Added per-conversation Discord broadcast toggle on chat creation
- Added optional per-conversation webhook override with fallback to user/global webhook defaults
- Added conversation lifecycle Discord notifications (start, per-message, completion, failure)
- Added automatic Discord thread creation support and persisted thread IDs for ongoing updates
- Added circuit-breaker behavior and safe failure handling so Discord outages do not break chat runs
- Added configurable Discord settings in `config/discord.php` and environment templates

### 🎭 **Persona Import / Export**
- **Import JSON on Create** - Upload a `.json` file on the create persona page to pre-fill all fields
- **Download Template** - Download a sample `persona-template.json` so users understand the expected format
- **Export JSON on Edit** - Export any persona from the edit page as a named JSON file (e.g. `technical-expert.json`)
- Exported files are directly re-importable on the create page

### 🐛 **Bug Fixes**
- Fixed Gemini API key checker crashing with a 500 error on brand-new keys — `strlen()` was being called on the `AIResponse` object instead of its `content` string
- Widened `catch (\Exception)` to `catch (\Throwable)` in the key test endpoint so PHP `Error` subclasses (e.g. `TypeError`) are handled gracefully
- Fixed OpenAI admin key checker crash by reading `AIResponse->content` before trimming

### 🧪 **Tests**
- Added `ApiKeyFactory` for use in tests
- Added three new `ApiKeyTest` cases: successful Gemini key validation, failed validation, and auth enforcement
- Added System Diagnostics test coverage for OpenAI key test endpoint and Laravel update action

### 🛡️ **Conversation Reliability**
- Added provider HTTP resilience controls in `config/ai.php`:
  - `AI_HTTP_TIMEOUT_SECONDS`
  - `AI_HTTP_CONNECT_TIMEOUT_SECONDS`
  - `AI_HTTP_RETRY_ATTEMPTS`
  - `AI_HTTP_RETRY_DELAY_MS`
- Added OpenAI driver request timeout/connect-timeout/retry behavior
- Added job-level fallback behavior when retryable provider exceptions persist
- Added floating live log panel on chat session view for real-time event tracing

### 🧰 **Admin & Diagnostics**
- Added new System Diagnostics action: **Update Laravel**
  - Runs `composer update laravel/framework --with-all-dependencies --no-interaction`
  - Skips execution in `testing` environment

---

## [Unreleased] - 2026-02-20

### 🛠️ **MCP Tool Calling (Function Calling)**
- **Agentic AI personas** - AI can now autonomously call MCP tools during conversations
- **5 MCP tools available** to all AI personas:
  - `search_conversations` - Search past messages by keyword
  - `get_contextual_memory` - Vector similarity search for related messages
  - `get_recent_chats` - Retrieve recent conversation summaries
  - `get_conversation` - Load specific conversation with full history
  - `get_mcp_stats` - Database statistics
- **Multi-provider support** - Works with OpenAI, Anthropic, and Gemini drivers
- **Intelligent agentic loop** - AI calls tools, processes results, and continues autonomously (max 5 iterations)
- **Tool execution logging** - All tool calls logged for transparency and debugging
- **Configuration** - `AI_TOOLS_ENABLED` and `AI_MAX_TOOL_ITERATIONS` in config
- **Architecture updates**:
  - `ToolDefinition` class for cross-provider tool schemas
  - `McpTools` registry with all available tools
  - `ToolExecutor` service for safe tool execution
  - Updated `AIDriverInterface` with `chatWithTools()` and `supportsTools()`
  - Modified `ConversationService` with agentic tool calling loop

### 📊 **Analytics Enhancements**
- Enhanced analytics charts with gradients, glows, and animations
- Provider usage donut chart with interactive legend and percentages
- Conversations over time with gradient fills and glowing data points
- Top personas bar chart with purple-pink gradients
- Tokens by provider with color-coded gradients and smart Y-axis formatting
- Fixed cost calculation - token usage now properly tracked and displayed
- Backfilled historical messages with estimated token counts
- Added missing model pricing for `gpt-4o-2024-11-20` and `claude-haiku-4-5-20251001`

### 🎛️ **Boost Dashboard Enhancements**
- Added live statistics section with real-time database counts
- Auto-refresh every 30 seconds with manual refresh button
- Live stats: conversations, messages, personas, users, embeddings
- MCP health indicator with pulsing status dot
- "Updated Xs ago" timestamp display
- Quick action links to related admin pages
- New `/admin/boost/stats` JSON endpoint for AJAX updates

### 🧪 Stability
- Guarded broadcasts against oversized payloads and added streaming chunking helpers
- Increased Reverb payload limits for local deployments
- Improved message streaming reliability on the conversation view

### 🔔 Notifications
- Added per-conversation email alert toggle on chat creation
- Display email alert state in session view

### 🧰 Admin & Diagnostics
- Added analytics search page and tests
- Improved Codex invocation handling for non-git environments

## [0.9.0] - 2026-03-05

_(Entries above this line were carried forward as unreleased work and are now captured in [1.0.0].)_

---

## [0.8.0] - 2026-01-24

### 🧾 Documentation
- Documented the current application version in README
- Added `APP_VERSION` to environment templates and config
- Included `APP_VERSION` in Docker environment defaults

## [Latest] - 2026-01-20

### 🎉 Major Features Added

#### 🔧 **System Diagnostics Dashboard**
- Added comprehensive admin diagnostics panel at `/admin/system`
- 8 diagnostic actions: Health Check, Fix Permissions, Clear Caches, Optimize, Validate AI, Check Database, Run Tests, Fix Code Style
- Real-time system information panel
- Web-based execution of maintenance tasks
- Beautiful UI with action cards and live output console

#### 🔭 **Laravel Telescope Integration**
- Installed and configured Laravel Telescope for application monitoring
- Enabled dark theme (`Telescope::night()`)
- Admin-only access with role-based gate
- Complete request/response tracking
- Exception monitoring
- Database query profiling
- Job and queue monitoring
- Accessible at `/telescope`

#### 🐛 **Laravel Debugbar Integration**
- Installed Laravel Debugbar for real-time profiling
- Query profiling with execution times
- Memory usage tracking
- Timeline visualization
- Route and view information
- Auto-disabled in production

#### 🎨 **Enhanced Dark Theme**
- Custom "Midnight Glass" design system
- Added glassmorphic UI components
- Custom CSS utilities (`.glass-panel`, `.input-dark`, `.btn-dark`, etc.)
- Gradient text effects (blue, purple, emerald)
- Custom dark scrollbars
- Glow effects for interactive elements
- 15+ new Tailwind CSS utility classes
- Deep zinc-950 background for OLED displays
- Frosted glass effects with backdrop blur

#### 📊 **Analytics Dashboard**
- Complete analytics system at `/analytics`
- 7-day activity trend charts (Recharts)
- Top persona statistics
- Advanced query system with filters
- CSV export (up to 1000 records)
- Real-time metrics display

#### 🔑 **API Key Validation System**
- Real-time API key testing
- Visual status indicators (validated/invalid/untested)
- Error tracking and display
- Last validated timestamps
- Test endpoint at `/api-keys/{id}/test`

#### 👥 **Username Login Support**
- Changed login to accept username OR email
- Removed @ requirement from login form
- Updated validation rules
- Updated UI label to "Email or Username"
- Default admin user now uses "admin" as username

#### 📝 **Enhanced Logging**
- Comprehensive conversation lifecycle logging
- Structured logging with context
- Turn-by-turn execution timing
- Error tracking throughout the system

### 🏗️ Infrastructure Improvements

#### 🐳 **Docker Optimizations**
- Fixed Reverb container configuration
- Added `SKIP_MIGRATIONS` flag for services
- Improved docker-entrypoint.sh script
- Better environment variable handling
- All containers stable and functional

#### 📚 **Documentation Overhaul**
- **README.md** - Completely redesigned with:
  - Centered header with badges
  - Feature showcase tables
  - Tech stack grid layout
  - Admin tools documentation
  - Quick stats section
  - Visual hierarchy improvements
  - 900+ lines of comprehensive documentation
- **FEATURES.md** - New comprehensive feature list:
  - 200+ features documented
  - Organized by category
  - Detailed descriptions
  - Use cases and examples
  - 391 lines of feature documentation
- Updated all existing documentation
- Added proper navigation links
- Improved code examples

### 🔒 Security Updates

- API key encryption maintained
- Role-based access for admin features
- Telescope admin-only gate
- Proper authorization checks
- CSRF protection maintained

### 🎯 Performance

- Maintains sub-10ms vector search
- Efficient database queries
- Optimized asset building
- Redis caching operational
- Queue system stable

### 🐛 Bug Fixes

- Fixed file permissions issues (config/ai.php)
- Resolved Reverb container startup issues
- Fixed PersonaSeeder to use correct admin user
- Resolved duplicate migration issues
- Fixed login form validation

### 📦 Dependencies Added

- `laravel/telescope:^5.16` - Application monitoring
- `barryvdh/laravel-debugbar:^3.16` - Debug profiling
- Plus 38 additional dev dependencies for testing

### 🗂️ New Files

#### Controllers
- `app/Http/Controllers/Admin/SystemController.php` - System diagnostics
- `app/Http/Controllers/AnalyticsController.php` - Analytics dashboard

#### Frontend Pages
- `resources/js/Pages/Admin/System.jsx` - Diagnostics UI
- `resources/js/Pages/Analytics/Index.jsx` - Analytics dashboard
- `resources/js/Pages/Dashboard.jsx` - Enhanced dashboard

#### Styling
- `resources/css/app.css` - Enhanced with dark theme utilities

#### Documentation
- `FEATURES.md` - Complete feature list
- `CHANGELOG.md` - This file

#### Configuration
- `config/debugbar.php` - Debugbar configuration
- `config/telescope.php` - Telescope configuration
- `app/Providers/TelescopeServiceProvider.php` - Telescope service provider

### 🔄 Modified Files

#### Routes
- `routes/web.php` - Added admin system routes

#### Database
- `database/seeders/PersonaSeeder.php` - Fixed admin user lookup
- `database/seeders/DatabaseSeeder.php` - Changed admin email to "admin"
- Added Telescope migrations

#### Authentication
- `app/Http/Requests/Auth/LoginRequest.php` - Accept username or email
- `resources/js/Pages/Auth/Login.jsx` - Updated form fields

#### Controllers
- `app/Http/Controllers/PersonaController.php` - Shared persona access
- `app/Http/Controllers/ChatController.php` - Enhanced logging

#### Providers
- `bootstrap/providers.php` - Added TelescopeServiceProvider

### 📊 Statistics

- **Total Lines of Code**: Significantly increased
- **Documentation**: 1,300+ lines
- **Features**: 200+
- **Admin Tools**: 8 diagnostic actions
- **AI Providers**: 8 supported
- **Pre-configured Personas**: 56
- **Test Coverage**: Comprehensive
- **Docker Services**: 6 containers
- **Dependencies**: 93+ packages

### 🚀 Deployment Status

- ✅ All Docker containers running
- ✅ Application accessible at http://localhost:8002
- ✅ Telescope accessible at http://localhost:8002/telescope
- ✅ System Diagnostics at http://localhost:8002/admin/system
- ✅ Debugbar active in development
- ✅ All personas seeded (56)
- ✅ Admin user created (username: admin, password: password)

### 🎨 UI/UX Improvements

- Midnight Glass dark theme fully implemented
- Glassmorphic design system
- Beautiful gradient accents
- Smooth animations and transitions
- Custom scrollbars
- Enhanced hover effects
- Improved visual hierarchy
- Better color contrast
- Accessible design

### 🔧 Developer Experience

- Telescope for debugging
- Debugbar for profiling
- System diagnostics panel
- Web-based test runner
- Code style fixer
- Comprehensive logging
- Docker development environment
- Hot module replacement (Vite)

### 📈 What's Next

See [ROADMAP.md](ROADMAP.md) for upcoming features!

**Planned:**
- Multi-language support
- Mobile app
- Voice conversations
- Plugin system
- Advanced analytics
- Team collaboration

---

## How to Update

```bash
# Pull latest changes
git pull origin main

# Install new dependencies
composer install
npm install

# Run migrations
php artisan migrate --force

# Build assets
npm run build

# Clear caches
php artisan optimize:clear

# Restart Docker (if using Docker)
docker compose down
docker compose up -d
```

---

**Note**: This changelog represents a massive update to the Chat Bridge platform, adding enterprise-grade debugging tools, comprehensive system administration features, and a complete visual overhaul with the Midnight Glass dark theme.
