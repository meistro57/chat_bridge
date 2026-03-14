# AI Orchestrator — Implementation Plan

## Overview

The AI Orchestrator is a feature that lets users define **sequences of AI-driven tasks** that run automatically. It sits on top of the existing personas, templates, and conversation infrastructure.

Users interact with a conversational setup wizard (powered by Claude) that:
1. Asks clarifying questions to understand the goal
2. Auto-generates personas and templates if needed
3. Wires everything into an **Orchestration** — a named, ordered sequence of steps
4. Optionally schedules the orchestration to run on a cron schedule or on-demand

An **orchestration run** executes each step in order: launching a conversation from a template, optionally substituting personas or providers, capturing the final output, and passing it to the next step.

---

## Conceptual Model

```
Orchestration
  ├── name, description, goal (natural language)
  ├── is_scheduled (bool)
  ├── cron_expression (nullable)
  ├── status (idle | running | paused | completed | failed)
  └── Steps[] (ordered)
        ├── step_number
        ├── label
        ├── template_id (nullable — auto-created if null)
        ├── persona_a_id override (nullable)
        ├── persona_b_id override (nullable)
        ├── provider_a / model_a override (nullable)
        ├── provider_b / model_b override (nullable)
        ├── input_source (static | previous_step_output | user_variable)
        ├── input_value / input_variable_name
        ├── output_action (log | pass_to_next | webhook | store_as_variable)
        ├── output_variable_name (nullable)
        ├── condition (nullable JSON — run only if previous output matches)
        └── pause_before_run (bool — wait for human approval)

OrchestratorRun
  ├── orchestration_id
  ├── status (queued | running | paused | completed | failed)
  ├── triggered_by (schedule | manual | api)
  ├── variables (JSON — runtime variable bag)
  ├── started_at / completed_at
  └── StepRuns[]
        ├── step_id
        ├── conversation_id (FK → conversations)
        ├── status (pending | running | paused | completed | skipped | failed)
        ├── output_summary (captured final assistant message or summary)
        ├── condition_passed (bool)
        └── started_at / completed_at
```

---

## Database Migrations

Create migrations in this order:

### 1. `create_orchestrations_table`

```php
Schema::create('orchestrations', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->text('description')->nullable();
    $table->text('goal')->nullable();           // natural-language user intent
    $table->boolean('is_scheduled')->default(false);
    $table->string('cron_expression')->nullable();
    $table->string('timezone')->default('UTC');
    $table->string('status')->default('idle'); // idle|running|paused|completed|failed
    $table->timestamp('last_run_at')->nullable();
    $table->timestamp('next_run_at')->nullable();
    $table->json('metadata')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

### 2. `create_orchestration_steps_table`

```php
Schema::create('orchestration_steps', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('orchestration_id')->constrained()->cascadeOnDelete();
    $table->unsignedInteger('step_number');
    $table->string('label')->nullable();
    $table->foreignUuid('template_id')->nullable()->constrained('conversation_templates')->nullOnDelete();

    // Overrides (all nullable — fall back to template defaults)
    $table->foreignUuid('persona_a_id')->nullable()->constrained('personas')->nullOnDelete();
    $table->foreignUuid('persona_b_id')->nullable()->constrained('personas')->nullOnDelete();
    $table->string('provider_a')->nullable();
    $table->string('model_a')->nullable();
    $table->string('provider_b')->nullable();
    $table->string('model_b')->nullable();

    // Input wiring
    $table->string('input_source')->default('static'); // static|previous_step_output|variable
    $table->text('input_value')->nullable();           // for static
    $table->string('input_variable_name')->nullable(); // for variable

    // Output wiring
    $table->string('output_action')->default('log'); // log|pass_to_next|webhook|store_as_variable
    $table->string('output_variable_name')->nullable();
    $table->string('output_webhook_url')->nullable();

    // Control flow
    $table->json('condition')->nullable(); // e.g. {"contains": "approved"}
    $table->boolean('pause_before_run')->default(false);

    $table->timestamps();
});
```

### 3. `create_orchestrator_runs_table`

```php
Schema::create('orchestrator_runs', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('orchestration_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('status')->default('queued'); // queued|running|paused|completed|failed
    $table->string('triggered_by')->default('manual'); // schedule|manual|api
    $table->json('variables')->nullable();             // runtime variable bag
    $table->text('error_message')->nullable();
    $table->timestamp('started_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();
});
```

### 4. `create_orchestrator_step_runs_table`

```php
Schema::create('orchestrator_step_runs', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('run_id')->constrained('orchestrator_runs')->cascadeOnDelete();
    $table->foreignUuid('step_id')->constrained('orchestration_steps')->cascadeOnDelete();
    $table->foreignUuid('conversation_id')->nullable()->constrained()->nullOnDelete();
    $table->string('status')->default('pending'); // pending|running|paused|completed|skipped|failed
    $table->text('output_summary')->nullable();
    $table->boolean('condition_passed')->nullable();
    $table->text('error_message')->nullable();
    $table->timestamp('started_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();
});
```

---

## Models

Use `php artisan make:model` for each. All use UUID primary keys (add `use HasUuids;`).

### `Orchestration`
- `HasUuids`, `SoftDeletes`
- Casts: `metadata` → array, `is_scheduled` → boolean
- Relations:
  - `belongsTo(User)`
  - `hasMany(OrchestratorStep, 'orchestration_id')->orderBy('step_number')`
  - `hasMany(OrchestratorRun)`
  - `hasOne(OrchestratorRun)->latestOfMany()` as `latestRun`

### `OrchestratorStep`
- `HasUuids`
- Casts: `condition` → array, `pause_before_run` → boolean
- Relations:
  - `belongsTo(Orchestration)`
  - `belongsTo(ConversationTemplate, 'template_id')`
  - `belongsTo(Persona, 'persona_a_id')`
  - `belongsTo(Persona, 'persona_b_id')`

### `OrchestratorRun`
- `HasUuids`
- Casts: `variables` → array, `started_at` → datetime, `completed_at` → datetime
- Relations:
  - `belongsTo(Orchestration)`
  - `belongsTo(User)`
  - `hasMany(OrchestratorStepRun, 'run_id')`

### `OrchestratorStepRun`
- `HasUuids`
- Relations:
  - `belongsTo(OrchestratorRun, 'run_id')`
  - `belongsTo(OrchestratorStep, 'step_id')`
  - `belongsTo(Conversation)`

---

## Artisan Commands (use `php artisan make:command`)

### `orchestration:run {orchestration}`
- Manually triggers a run for a given orchestration ID
- Creates an `OrchestratorRun` and dispatches `RunOrchestration` job

### `orchestration:schedule`
- Evaluates all scheduled orchestrations where `next_run_at <= now()`
- Dispatches `RunOrchestration` for each due orchestration
- Register in `routes/console.php` to run every minute: `Schedule::command('orchestration:schedule')->everyMinute()`

---

## Jobs

### `RunOrchestration` (implements `ShouldQueue`)

**Responsibility**: Execute all steps of an orchestrator run sequentially.

**Flow**:
```
1. Load orchestration + ordered steps
2. Mark run as `running`
3. For each step:
   a. Evaluate condition against previous step's output — skip if not met
   b. If pause_before_run, mark step_run as `paused`, broadcast event, stop
   c. Resolve runtime input (static | variable | previous output)
   d. Resolve template, personas, providers (apply overrides)
   e. Create Conversation from template (auto-start = false)
   f. Set starter_message from resolved input
   g. Dispatch existing RunChatSession job and WAIT for completion
      - Poll conversation status every 5s with retry up to timeout
   h. Capture output: extract last message from conversation
   i. Apply output_action (store variable, POST webhook, etc.)
   j. Mark step_run as `completed`
4. Mark run as `completed`
5. Update orchestration.last_run_at and compute next_run_at if scheduled
6. Broadcast OrchestratorRunCompleted event
```

**Failure handling**: On exception, mark the step_run and run as `failed`, save error_message.

### `ResumeOrchestratorRun`

Dispatched when a user approves a paused step. Picks up from the paused step.

---

## Services

### `OrchestratorService`

```php
class OrchestratorService
{
    public function createFromIntent(User $user, string $goal, array $answers): Orchestration { }
    public function resolveStepInput(OrchestratorStep $step, OrchestratorRun $run): string { }
    public function evaluateCondition(?array $condition, ?string $previousOutput): bool { }
    public function computeNextRunAt(string $cronExpression, string $timezone): Carbon { }
    public function captureConversationOutput(Conversation $conversation): string { }
    public function applyOutputAction(OrchestratorStep $step, OrchestratorRun $run, string $output): void { }
}
```

### `OrchestratorWizardService`

Handles the multi-turn AI conversation that helps users build an orchestration.

```php
class OrchestratorWizardService
{
    // Sends user message to Claude with context about existing personas/templates
    // Returns: { reply: string, done: bool, orchestration_draft: array|null }
    public function chat(User $user, array $history, string $userMessage): array { }

    // Takes the final draft from Claude and creates DB records
    public function materialize(User $user, array $draft): Orchestration { }
}
```

**System prompt for wizard** (built dynamically, injected into Claude API call):
```
You are an AI orchestration assistant. Help the user design a sequence of AI tasks.

Available personas: {comma-separated list of user's persona names with IDs}
Available templates: {comma-separated list of user's template names with IDs}
Available providers: openai, anthropic, gemini, openrouter, deepseek, ollama

Ask clarifying questions one at a time until you understand:
1. The overall goal
2. Each step (what it does, which template or new template to use, which personas/providers)
3. Input/output wiring between steps
4. Whether to schedule and how often

When you have enough information, respond with JSON inside <orchestration> tags:
<orchestration>{ ... draft JSON matching the schema ... }</orchestration>
```

---

## Events

### `OrchestratorRunCompleted`
- Payload: `OrchestratorRun` with step runs
- Broadcast on user's private channel

### `OrchestratorStepStarted`
- Payload: `OrchestratorStepRun`
- Used for real-time UI progress

### `OrchestratorStepPaused`
- Payload: `OrchestratorStepRun`
- Notifies user that manual approval is needed

---

## Controllers

### `OrchestratorController` (resource, web)

| Method | Route | Description |
|--------|-------|-------------|
| `index` | GET /orchestrator | List orchestrations |
| `show` | GET /orchestrator/{id} | Detail + run history |
| `store` | POST /orchestrator | Create orchestration (from wizard output) |
| `update` | PUT /orchestrator/{id} | Edit orchestration |
| `destroy` | DELETE /orchestrator/{id} | Soft delete |
| `run` | POST /orchestrator/{id}/run | Manual trigger |
| `pause` | POST /orchestrator/{id}/pause | Pause active run |
| `resume` | POST /orchestrator/{run}/resume | Resume paused step |

### `OrchestratorWizardController`

| Method | Route | Description |
|--------|-------|-------------|
| `show` | GET /orchestrator/wizard | Wizard UI page |
| `chat` | POST /orchestrator/wizard/chat | Send message, get AI reply |
| `materialize` | POST /orchestrator/wizard/materialize | Save draft as orchestration |

### `OrchestratorRunController`

| Method | Route | Description |
|--------|-------|-------------|
| `index` | GET /orchestrator/{id}/runs | All runs for orchestration |
| `show` | GET /orchestrator/runs/{run} | Single run detail with step statuses |

---

## Form Requests

- `StoreOrchestratorRequest` — validate name, goal, steps array
- `UpdateOrchestratorRequest` — same as store but all fields optional
- `WizardChatRequest` — validate `message` (string, required) and `history` (array)

---

## Frontend Pages (`resources/js/Pages/Orchestrator/`)

### `Index.jsx`
- List of orchestrations with status badges, last run time, next scheduled run
- Quick-action buttons: Run, Pause, Edit, Delete
- "New Orchestration" button → opens wizard

### `Wizard.jsx`
- Full-screen conversational UI (chat bubble layout)
- User types goal, AI replies with questions
- When AI returns complete draft, shows a structured preview panel:
  - Step-by-step summary with persona/template chips
  - Schedule configuration card
  - "Save & Create" + "Keep Editing" buttons

### `Show.jsx`
- Orchestration detail: steps list (visual timeline/sequencer)
- Run history table
- "Run Now" button with confirmation
- Real-time run progress panel (step statuses, live output preview)
- Pause/Resume controls for paused steps

### `Runs/Show.jsx`
- Single run detail: each step_run with status, timing, output summary
- Link to the underlying conversation for each step
- Error messages for failed steps

---

## Navigation

Add "Orchestrator" to the main nav in `AuthenticatedLayout.jsx`, between Templates and Analytics.

---

## Route Registration

Add to `routes/web.php`:

```php
Route::middleware(['auth', 'verified'])->prefix('orchestrator')->name('orchestrator.')->group(function () {
    Route::resource('/', OrchestratorController::class)->parameters(['' => 'orchestration']);
    Route::post('/{orchestration}/run', [OrchestratorController::class, 'run'])->name('run');
    Route::post('/{orchestration}/pause', [OrchestratorController::class, 'pause'])->name('pause');
    Route::post('/runs/{run}/resume', [OrchestratorController::class, 'resume'])->name('resume');

    Route::get('/wizard', [OrchestratorWizardController::class, 'show'])->name('wizard');
    Route::post('/wizard/chat', [OrchestratorWizardController::class, 'chat'])->name('wizard.chat');
    Route::post('/wizard/materialize', [OrchestratorWizardController::class, 'materialize'])->name('wizard.materialize');

    Route::prefix('/{orchestration}/runs')->name('runs.')->group(function () {
        Route::get('/', [OrchestratorRunController::class, 'index'])->name('index');
        Route::get('/{run}', [OrchestratorRunController::class, 'show'])->name('show');
    });
});
```

---

## Implementation Order for Claude Code Agent

Follow this exact sequence to avoid dependency errors:

1. **Migrations** — create and run all 4 migrations in order
2. **Models** — create `Orchestration`, `OrchestratorStep`, `OrchestratorRun`, `OrchestratorStepRun` with factories
3. **Events** — create the 3 broadcast events
4. **Services** — create `OrchestratorService` and `OrchestratorWizardService`
5. **Jobs** — create `RunOrchestration` and `ResumeOrchestratorRun`
6. **Artisan Commands** — create `orchestration:run` and `orchestration:schedule`
7. **Schedule Registration** — add `orchestration:schedule` to `routes/console.php`
8. **Form Requests** — create the 3 form request classes
9. **Controllers** — create `OrchestratorController`, `OrchestratorWizardController`, `OrchestratorRunController`
10. **Routes** — add orchestrator routes to `routes/web.php`
11. **Frontend** — create `Pages/Orchestrator/Index.jsx`, `Wizard.jsx`, `Show.jsx`, `Runs/Show.jsx`
12. **Navigation** — add nav link to `AuthenticatedLayout.jsx`
13. **Tests** — write feature tests for the main controller actions and `OrchestratorService`
14. **Pint** — run `vendor/bin/pint --dirty`
15. **Build** — run `npm run build`

---

## Key Implementation Details

### Wizard AI Call (`OrchestratorWizardService::chat`)

Use the existing `AnthropicDriver` (or whichever provider the user has configured as default). Do NOT call `RunChatSession` — instead call the driver's `streamChat()` method directly without streaming (collect the full response). The wizard conversation history is maintained client-side and sent with each request.

Inject the user's existing personas and templates into the system prompt dynamically. Parse the AI response for `<orchestration>...</orchestration>` tags; if found, return `done: true` and the parsed JSON as `orchestration_draft`.

### Conversation Creation from Step

When `RunOrchestration` processes a step, create the `Conversation` using the same logic as `ChatController::store`, but programmatically:
- Copy fields from the resolved template (persona IDs, providers, models, temperatures, max_rounds, etc.)
- Apply step-level overrides where not null
- Set `starter_message` to the resolved step input
- Set `user_id` from the orchestration's user
- Dispatch `RunChatSession` (existing job) — do NOT re-implement turn logic

Wait for conversation completion by polling `Conversation::refresh()->status` every 5 seconds. Timeout after the conversation's expected max time (rounds × 60s + buffer).

### Output Capture

`OrchestratorService::captureConversationOutput` loads the last `Message` of the conversation where `role = 'assistant'` (or the last message of the final round), returns its `content` as a string.

### Condition Evaluation

`evaluateCondition` supports these condition shapes:
```json
{"contains": "some phrase"}
{"not_contains": "phrase"}
{"equals": "exact value"}
{"regex": "pattern"}
```
If condition is null, always returns true.

### Variable Bag

The `OrchestratorRun::variables` JSON object stores named outputs across steps:
```json
{
  "step_1_output": "...",
  "custom_var": "...",
  "user.topic": "AI ethics"
}
```
Steps with `input_source = 'variable'` resolve `input_variable_name` against this bag using dot notation.

### Scheduler

`routes/console.php`:
```php
Schedule::command('orchestration:schedule')->everyMinute()->withoutOverlapping();
```

`orchestration:schedule` command queries:
```php
Orchestration::query()
    ->where('is_scheduled', true)
    ->where('status', 'idle')
    ->where('next_run_at', '<=', now())
    ->each(fn ($o) => RunOrchestration::dispatch(OrchestratorRun::create([...])));
```

After dispatching, update `last_run_at = now()` and compute `next_run_at` using `OrchestratorService::computeNextRunAt`.

---

## Tests to Write

- `OrchestratorControllerTest` — CRUD, run, pause, resume endpoints (assert DB state, auth, authorization)
- `OrchestratorWizardControllerTest` — chat endpoint returns reply; materialize creates orchestration + steps
- `OrchestratorServiceTest` — unit tests for `evaluateCondition`, `resolveStepInput`, `computeNextRunAt`, `captureConversationOutput`
- `RunOrchestrationJobTest` — mock `RunChatSession` dispatch; assert step_runs are created and statuses updated
- `OrchestratorScheduleCommandTest` — assert due orchestrations get dispatched

---

## Notes for Agent

- Follow all rules in `CLAUDE.md` strictly (PHP 8.4, Laravel 12 conventions, Pint formatting, PHPUnit tests)
- Check sibling controllers/models for exact naming conventions before creating files
- Use `php artisan make:*` for all generated files
- Run `vendor/bin/pint --dirty` before finalizing
- Run affected tests with `php artisan test --compact` after each major step
- Do not modify existing migrations — add new ones only
- Do not alter existing `RunChatSession` or `ConversationService` — compose on top of them
