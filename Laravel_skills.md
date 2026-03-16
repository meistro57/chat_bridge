# Laravel Skills — Complete Reference

> **Mark's full Laravel superpowers skill library in one file.**  
> Covers everything from daily workflow to hexagonal architecture, chunking, queues, testing, and full Laravel 12 expertise.

---

## Table of Contents

1. [Using Laravel Superpowers (Start Here)](#1-using-laravel-superpowers-start-here)
2. [Bootstrap Check — Sail vs Non-Sail](#2-bootstrap-check--sail-vs-non-sail)
3. [Daily Workflow Checklist](#3-daily-workflow-checklist)
4. [Laravel 12 Expert Guide](#4-laravel-12-expert-guide)
5. [Brainstorming & Design Refinement](#5-brainstorming--design-refinement)
6. [Controller Cleanup](#6-controller-cleanup)
7. [Controller Tests](#7-controller-tests)
8. [API Resources & Pagination](#8-api-resources--pagination)
9. [API Surface Evolution](#9-api-surface-evolution)
10. [Blade Components & Layouts](#10-blade-components--layouts)
11. [Ports and Adapters (Hexagonal Architecture)](#11-ports-and-adapters-hexagonal-architecture)
12. [Queues & Horizon](#12-queues--horizon)
13. [Data Chunking for Large Datasets](#13-data-chunking-for-large-datasets)
14. [Complexity Guardrails](#14-complexity-guardrails)
15. [Constants & Configuration](#15-constants--configuration)
16. [Config, ENV & Storage (S3/R2/MinIO/CDN)](#16-config-env--storage-s3r2minioc-dn)
17. [Custom Helpers](#17-custom-helpers)
18. [Debugging Prompts](#18-debugging-prompts)
19. [Code Review Requests](#19-code-review-requests)

---

## 1. Using Laravel Superpowers (Start Here)

This plugin adds Laravel-aware guidance while staying platform-agnostic. Works in any Laravel app with or without Sail.

### Runner Selection

```bash
alias sail='sh $([ -f sail ] && echo sail || echo vendor/bin/sail)'

sail artisan test           # with Sail
php artisan test            # without Sail
```

### Core Workflows

| Goal | Skill to Use |
|------|-------------|
| TDD first | `laravel:tdd-with-pest` |
| Database changes | `laravel:migrations-and-factories` |
| Quality gates | `laravel:quality-checks` (Pint, Insights/PHPStan) |
| Queues and Horizon | `laravel:queues-and-horizon` |
| Architecture | `laravel:ports-and-adapters`, `laravel:template-method-and-plugins` |
| Keep complexity low | `laravel:complexity-guardrails` |

### Philosophy

- Favor small, testable services; avoid fat controllers/commands/jobs
- DTOs, typed Collections, and Enums when they clarify intent
- Prefer model factories in tests and model scopes for complex queries
- Verify before completion—run tests and linters clean

### Slash Commands

```
/superpowers-laravel:brainstorm
/superpowers-laravel:write-plan
/superpowers-laravel:execute-plan
```

---

## 2. Bootstrap Check — Sail vs Non-Sail

Quickly determine if the project should run with Sail or host tools, then list the correct commands for this session.

### Detect Runner

```bash
if [ -f sail ] || [ -x vendor/bin/sail ]; then
  echo "Sail detected. Use: sail artisan|composer|pnpm ...";
else
  echo "Sail not found. Use host tools: php artisan, composer, pnpm ...";
fi
```

Optional portable alias:

```bash
alias sail='sh $([ -f sail ] && echo sail || echo vendor/bin/sail)'
```

### Command Pairs

| Sail | Non-Sail |
|------|----------|
| `sail artisan about` | `php artisan about` |
| `sail artisan test` | `php artisan test` |
| `sail artisan migrate` | `php artisan migrate` |
| `sail composer install` | `composer install` |
| `sail pnpm install` | `pnpm install` |
| `sail pnpm run dev` | `pnpm run dev` |

### Service Smoke Checks

```bash
# DB
sail mysql -e 'select 1'     # or: mysql -e 'select 1'

# Cache
sail redis ping               # or: redis-cli ping
```

---

## 3. Daily Workflow Checklist

Run through this at the start of a session or before handoff.

```bash
# Start services
sail up -d && sail ps                     # Sail
# or (non-Sail): ensure PHP/DB are running locally

# Schema as needed
sail artisan migrate                      # or: php artisan migrate

# Queue worker if required
sail artisan queue:work --tries=3         # or: php artisan queue:work --tries=3

# Quality gates
sail pint --test && sail pint             # or: vendor/bin/pint --test && vendor/bin/pint
sail artisan test --parallel              # or: php artisan test --parallel

# Frontend (if present)
sail pnpm run lint && sail pnpm run types # or: pnpm run lint && pnpm run types
```

---

## 4. Laravel 12 Expert Guide

Comprehensive Laravel 12.x guidance with modern best practices, new features, and architectural patterns.

### Core Philosophy

Laravel 12 emphasizes:
- **Agent-ready development** — Structured conventions optimized for AI-assisted coding
- **Progressive framework** — Grows with developer skill level
- **Modern PHP** — Leverages PHP 8.2–8.4 features (JIT, attributes, enums)
- **Quality of life** — Continuous improvements without breaking changes

---

### Project Setup

```bash
# Using Laravel installer (recommended)
laravel new project-name

# Start development
cd project-name
composer run dev  # Starts Laravel dev server, queue worker, Vite
```

### Starter Kits (New in Laravel 12)

Laravel 12 replaces Breeze/Jetstream with three modern starter kits:

**React Starter Kit**
- Inertia.js 2 + React 19, TypeScript, shadcn/ui, Tailwind CSS
- Built-in auth (login, register, password reset, email verification)

**Vue Starter Kit**
- Inertia.js 2 + Vue 3, TypeScript, shadcn-vue, Tailwind CSS

**Livewire Starter Kit**
- Livewire 3, Laravel Volt, Flux UI components, Tailwind CSS

**WorkOS AuthKit Variant** (all kits)
- Social auth, Passkeys, SSO, free up to 1M MAU

```bash
laravel new project-name --stack=react
laravel new project-name --stack=vue
laravel new project-name --stack=livewire
```

### Directory Structure

```
app/
├── Models/
├── Http/
│   ├── Controllers/
│   └── Middleware/
└── Providers/
bootstrap/
├── app.php
└── providers.php
config/
database/
├── migrations/
├── factories/
└── seeders/
resources/
├── views/
├── js/
└── css/
routes/
├── web.php
├── api.php
└── console.php
tests/
├── Feature/
└── Unit/
```

---

### Laravel Boost (AI-Assisted Development)

```bash
composer require laravel/boost --dev
php artisan boost:install
```

Features:
- 15+ specialized AI tools (query DB, search docs, generate tests, run Tinker)
- 17,000+ pieces of vectorized ecosystem documentation
- Auto-detects IDE and AI agents
- Custom guidelines: add `.blade.php` or `.md` files to `.ai/guidelines/*`

---

### Eloquent ORM Best Practices

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Product extends Model
{
    protected $fillable = ['name', 'price', 'category_id'];

    protected $casts = [
        'price'        => 'decimal:2',
        'is_active'    => 'boolean',
        'published_at' => 'datetime',
        'metadata'     => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    // Accessor/Mutator (Laravel 9+ syntax)
    protected function price(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => $value * 100,
        );
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
```

### Query Optimization

```php
// N+1 Prevention — eager load
$products = Product::with('category', 'reviews')->get();

// Load counts without full relationships
$categories = Category::withCount('products')->get();

// Conditional eager loading
$products = Product::query()
    ->when($includeReviews, fn($q) => $q->with('reviews'))
    ->get();

// Chunk large result sets
Product::chunk(100, function ($products) {
    foreach ($products as $product) {
        // process
    }
});

// Lazy collections
Product::lazy()->each(function ($product) {
    // one at a time
});
```

---

### Routing

#### Traditional (routes/web.php)

```php
Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::resource('products', ProductController::class);
Route::apiResource('products', ProductController::class);
Route::resource('categories.products', ProductController::class); // nested
```

#### PHP 8 Attribute Routing (New in Laravel 12)

```php
use Illuminate\Routing\Attribute\Get;
use Illuminate\Routing\Attribute\Post;
use Illuminate\Routing\Attribute\Middleware;

#[Middleware('auth')]
class ProductController extends Controller
{
    #[Get('/products', name: 'products.index')]
    public function index() { ... }

    #[Post('/products', name: 'products.store')]
    #[Middleware('throttle:60,1')]
    public function store(Request $request) { ... }
}
```

---

### Validation & Form Requests

```php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Product::class);
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'price'       => ['required', 'numeric', 'min:0'],
            'category_id' => ['required', Rule::exists('categories', 'id')],
            'sku'         => ['required', 'unique:products,sku'],
            'tags'        => ['array'],
            'tags.*'      => ['string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'price.min' => 'Price cannot be negative.',
        ];
    }
}

// Controller usage
public function store(StoreProductRequest $request)
{
    $product = Product::create($request->validated());
    return redirect()->route('products.show', $product);
}
```

---

### Queue & Jobs

```php
php artisan make:job ProcessOrderPayment

class ProcessOrderPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries   = 3;
    public $timeout = 120;
    public $backoff = [60, 120, 300]; // 1min, 2min, 5min

    public function __construct(public Order $order) {}

    public function handle(): void
    {
        PaymentGateway::charge($this->order);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Payment processing failed', [
            'order_id' => $this->order->id,
            'error'    => $exception->getMessage(),
        ]);
    }
}

// Dispatch options
ProcessOrderPayment::dispatch($order);
ProcessOrderPayment::dispatch($order)->delay(now()->addMinutes(5));
ProcessOrderPayment::dispatch($order)->chain([
    new SendOrderConfirmation($order),
    new UpdateInventory($order),
]);
```

#### Failover Queue Driver (New in Laravel 12)

```php
// config/queue.php
'failover' => [
    'driver'      => 'failover',
    'connections' => ['redis', 'database'],
],
```

#### SQS Fair Queues (New in Laravel 12)

```php
'sqs' => [
    'driver' => 'sqs',
    'fair'   => true,
    'key'    => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'queue'  => env('SQS_QUEUE'),
    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
],
```

---

### API Development

#### API Resources

```php
php artisan make:resource ProductResource

class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'price'          => $this->price,
            'category'       => new CategoryResource($this->whenLoaded('category')),
            'reviews_count'  => $this->when($this->reviews_count, $this->reviews_count),
            'created_at'     => $this->created_at->toIso8601String(),
        ];
    }
}

// Usage
return ProductResource::collection(Product::paginate(15));
return new ProductResource($product);
```

#### API Authentication (Sanctum)

```bash
php artisan install:api
```

```php
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('products', ProductController::class);
});

$token = $user->createToken('api-token')->plainTextToken;
$user->tokens()->delete(); // revoke all
```

---

### Real-Time Features (Reverb)

```bash
php artisan install:broadcasting
```

```php
class OrderShipped implements ShouldBroadcast
{
    use InteractsWithSockets;

    public function __construct(public Order $order) {}

    public function broadcastOn(): Channel
    {
        return new Channel('orders.' . $this->order->id);
    }
}

// Frontend (Laravel Echo)
Echo.channel(`orders.${orderId}`)
    .listen('OrderShipped', (e) => {
        console.log('Order shipped:', e.order);
    });
```

---

### Testing

#### Feature Tests

```php
class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_product(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/products', [
            'name'        => 'Test Product',
            'price'       => 99.99,
            'category_id' => Category::factory()->create()->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('products', ['name' => 'Test Product']);
    }
}
```

#### Unit Tests

```php
public function test_product_price_is_formatted_correctly(): void
{
    $product = new Product(['price' => 1999]);
    $this->assertEquals(19.99, $product->price);
}
```

---

### Performance & Caching

```php
// Cache remember
$products = Cache::remember('products.active', 3600, function () {
    return Product::active()->get();
});

// Cache tags
Cache::tags(['products', 'category:' . $categoryId])
    ->put('products.list', $products, 3600);

Cache::tags(['products'])->flush();

// Async caching (Laravel 12)
Cache::async()->put('key', 'value', 3600);

// Select specific columns
$products = Product::select('id', 'name', 'price')->get();

// Indexes in migrations
$table->index(['category_id', 'is_active']);
$table->unique('sku');
```

---

### Deployment

```bash
# Production .env essentials
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...
QUEUE_CONNECTION=redis
CACHE_DRIVER=redis
SESSION_DRIVER=redis

# Optimization commands
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Clear all caches
php artisan optimize:clear
```

#### Health Checks (New in Laravel 12)

```php
Route::get('/health', function () {
    return [
        'database' => DB::connection()->getPdo() !== null,
        'cache'    => Cache::has('health-check'),
        'queue'    => Queue::size() < 1000,
    ];
});
```

---

### Upgrading from Laravel 11

```bash
composer require laravel/framework:^12.0
composer update
php artisan migrate
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Key changes:
- Starter kits: Breeze/Jetstream deprecated; use React/Vue/Livewire kits
- PHP: Requires 8.2+ (8.4 supported)

---

### Common Patterns

#### Service Layer

```php
class OrderService
{
    public function createOrder(User $user, array $items): Order
    {
        $order = DB::transaction(function () use ($user, $items) {
            $order = Order::create([
                'user_id' => $user->id,
                'total'   => $this->calculateTotal($items),
            ]);

            foreach ($items as $item) {
                $order->items()->create($item);
            }

            return $order;
        });

        event(new OrderPlaced($order));
        return $order;
    }

    private function calculateTotal(array $items): float
    {
        return collect($items)->sum(fn($item) => $item['price'] * $item['quantity']);
    }
}
```

#### Repository Pattern

```php
class ProductRepository
{
    public function findActive(): Collection
    {
        return Product::where('is_active', true)->with('category')->get();
    }

    public function findByCategory(int $categoryId): Collection
    {
        return Product::where('category_id', $categoryId)->active()->get();
    }
}
```

---

## 5. Brainstorming & Design Refinement

Use when shaping features or refactors. Ask one at a time, then propose a design.

### Questions to Ask

- **Goal** — What outcome should users achieve?
- **Domain** — Which bounded contexts or packages are involved?
- **Data** — New models/relations? Required queries and invariants?
- **Interfaces** — HTTP/API/CLI? Required inputs/outputs? AuthZ?
- **Side-effects** — Email, storage, queues, external systems?
- **Performance** — Throughput, latency, pagination, N+1 risks?
- **Observability** — Logs, metrics, events, failure handling?
- **Testing** — TDD entry point, fixtures/factories, edge cases?
- **Environment** — Sail or host? DB/cache/mail/storage availability?

### Design Proposal Format (200–300 words)

- Routes/contracts, validation, DTOs/transformers
- Services (ports+adapters, strategies/pipelines)
- Data model changes and migrations
- Jobs/events/listeners where relevant
- Test strategy (feature/unit), factories and seeds
- Quality gates and rollout plan

---

## 6. Controller Cleanup

Keep controllers small and focused on orchestration.

### Move Auth/Validation to Form Requests

```bash
php artisan make:request StoreUserRequest
```

```php
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool { return auth()->check(); }
    public function rules(): array { return ['email' => 'required|email']; }
}
```

### Extract Business Logic to Actions

```php
final class CreateUserAction
{
    public function __invoke(CreateUserDTO $dto): User
    {
        return User::create([
            'name'     => $dto->name,
            'email'    => $dto->email,
            'password' => bcrypt($dto->password),
        ]);
    }
}
```

### Prefer Resource or Single-Action Controllers

```php
// Resource controller
Route::resource('products', ProductController::class);

// Single-action controller
Route::post('/checkout', CheckoutController::class);

class CheckoutController extends Controller
{
    public function __invoke(CheckoutRequest $request, CreateOrderAction $action)
    {
        $order = $action(CreateOrderDTO::fromRequest($request));
        return redirect()->route('orders.show', $order);
    }
}
```

### Testing

- Write feature tests for controller routes
- Unit test Actions/Services independently with DTOs

---

## 7. Controller Tests

### Feature Tests for Endpoints

```php
it('rejects empty email', function () {
    $this->post('/register', ['email' => ''])->assertSessionHasErrors('email');
});

it('creates a product when authenticated', function () {
    $user = User::factory()->create();
    $this->actingAs($user)
         ->post('/products', ['name' => 'Widget', 'price' => 10])
         ->assertRedirect();
    expect(Product::count())->toBe(1);
});
```

### Best Practices

- Move validation to Form Requests; assert errors from request class
- Extract business logic into Actions; unit test them directly
- Use factories for realistic data; avoid heavy mocking

---

## 8. API Resources & Pagination

Represent models via Resources; keep transport concerns out of Eloquent.

```bash
php artisan make:resource PostResource
```

```php
class PostResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'title'        => $this->title,
            'author'       => new UserResource($this->whenLoaded('author')),
            'published_at' => optional($this->published_at)->toAtomString(),
        ];
    }
}

// Controller usage
return PostResource::collection(
    Post::with('author')->latest()->paginate(20)
);
```

### Patterns

- Prefer `Resource::collection($query->paginate())` over manual arrays
- Use `when()` / `mergeWhen()` for conditional fields
- Keep pagination cursors/links intact for clients
- Version resources when contracts change; avoid breaking fields silently

---

## 9. API Surface Evolution

Design for change without breaking clients.

### Versioning Strategy

- Choose explicit versioning (URI `/v1/...` or header negotiation)
- Default to additive changes; never break a released contract

### DTOs & Transformers

- Define versioned DTOs; map from models/services via transformers
- Keep controller thin — validate → transform → respond

### Deprecations

- Mark fields as deprecated in docs and responses (e.g., headers)
- Provide sunset timelines; add metrics to track remaining usage

### Testing

- Contract tests per version (request/response shapes)
- Backward compatibility tests for commonly used flows

---

## 10. Blade Components & Layouts

Encapsulate markup and behavior with components; prefer slots over includes.

```bash
php artisan make:component Alert
```

```blade
{{-- Component usage --}}
<x-alert type="warning" :message="$msg" class="mb-4" />

{{-- Layouts + stacks --}}
@extends('layouts.app')
@push('scripts')
    <script>/* page script */</script>
@endpush
```

### Component Class

```php
class Alert extends Component
{
    public function __construct(
        public string $type = 'info',
        public string $message = ''
    ) {}

    public function render()
    {
        return view('components.alert');
    }
}
```

### Patterns

- Keep components dumb: pass data in, emit markup out
- Use `merge()` to honor passed classes/attributes
- Prefer named slots for readability
- Extract small reusable atoms rather than giant organisms

---

## 11. Ports and Adapters (Hexagonal Architecture)

Abstract integrations behind stable interfaces. Keep vendor SDKs out of your domain code.

### Shape

- **Port** — PHP interface that expresses only what the app needs
- **Adapters** — one per provider, wrapping SDK quirks
- **Selection** — choose adapter via config/env/service provider

### Example (Email)

```php
interface MailPort
{
    public function send(string $to, string $subject, string $html): void;
}

final class SesMailAdapter implements MailPort
{
    public function __construct(private \Aws\Ses\SesClient $ses) {}

    public function send(string $to, string $subject, string $html): void
    {
        // wrap SES specifics here
    }
}

// Composition in AppServiceProvider
$this->app->singleton(MailPort::class, function () {
    return match (config('mail.driver')) {
        'ses'   => new SesMailAdapter(app('aws.ses')),
        default => new SmtpMailAdapter(/* ... */),
    };
});
```

### Tips

- Normalize SDK data into your own types/DTOs
- Expose only portable capabilities via the port
- Keep adapters thin and well-tested

---

## 12. Queues & Horizon

Run workers safely, verify execution, and test job behavior.

### Commands

```bash
# Run queue worker
sail artisan queue:work --queue=high,default --tries=3 --backoff=5

# Run Horizon
sail artisan horizon

# Inspect failures
sail artisan queue:failed
sail artisan queue:retry all
```

### Patterns

- Use named queues for prioritization; keep defaults sane
- Add actionable `Log::warning` / `Log::error` with context in jobs
- **Idempotency** — make jobs safe to retry
- Emit metrics where possible; observe in Horizon or your APM

### Testing Jobs

```php
// Assert dispatching in unit tests
Bus::fake();
ProcessOrderPayment::dispatch($order);
Bus::assertDispatched(ProcessOrderPayment::class);

// Integration tests verify side-effects (DB/IO)
```

### Job Best Practices

```php
class SendWelcomeEmail implements ShouldQueue
{
    public $tries          = 3;
    public $backoff        = [30, 60, 120];
    public $deleteWhenMissingModels = true;

    public function __construct(public User $user) {}

    public function handle(): void
    {
        // Always check state first (idempotency)
        if ($this->user->welcome_sent_at) {
            return;
        }

        Mail::to($this->user)->send(new WelcomeMail($this->user));
        $this->user->update(['welcome_sent_at' => now()]);
    }
}
```

---

## 13. Data Chunking for Large Datasets

Process large datasets efficiently by breaking them into manageable chunks.

### The Problem

```php
// BAD: Loading all records into memory
$users = User::all(); // Could be millions of records!
```

### Methods

#### `chunk()`

```php
User::chunk(100, function ($users) {
    foreach ($users as $user) {
        $user->calculateStatistics();
        $user->save();
    }
});
```

#### `chunkById()` — Safer for Updates

```php
User::where('newsletter_sent', false)
    ->chunkById(100, function ($users) {
        foreach ($users as $user) {
            $user->update(['newsletter_sent' => true]);
        }
    });
```

#### `lazy()` Collections

```php
User::where('created_at', '>=', now()->subDays(30))
    ->lazy()
    ->each(function ($user) {
        $user->recalculateScore();
    });
```

#### `cursor()` — Forward-Only

```php
foreach (User::where('active', true)->cursor() as $user) {
    $user->updateLastSeen();
}
```

### Choosing the Right Method

| Method | Use Case | Memory | Notes |
|--------|----------|--------|-------|
| `chunk()` | General processing | Moderate | May skip/duplicate if modifying filter columns |
| `chunkById()` | Updates during iteration | Moderate | Safer for modifications |
| `lazy()` | Large result processing | Low | Returns LazyCollection |
| `cursor()` | Simple forward iteration | Lowest | Returns Generator |

### Performance Tips

1. Select only needed columns
2. Use indexes on WHERE clause columns
3. Disable Eloquent events when appropriate
4. Use raw queries for bulk updates
5. Queue large operations

### Common Pitfalls

- Modifying filter columns during `chunk()` — use `chunkById()` instead
- Not handling chunk callback returns (return `false` to stop)
- Ignoring database connection timeouts for long operations

---

## 14. Complexity Guardrails

Design to keep complexity low from day one.

### Targets

- Cyclomatic complexity per function ≤ 7 (start splitting at 5)
- Function length ≤ 80 lines (aim for ≤ 30)
- One responsibility per function; one axis of variation per module

### Tactics

- Use early returns and guard clauses; avoid deep nesting
- Extract branch bodies into named helpers
- Replace long if/else/switch with tables (maps) or strategies
- Separate phases: **parse → validate → normalize → act**

### Before (Complex)

```php
public function processOrder($order, $user, $payment)
{
    if ($order && $user && $payment) {
        if ($user->isActive()) {
            if ($payment->isValid()) {
                if ($order->stock > 0) {
                    // ... 50 more lines
                }
            }
        }
    }
}
```

### After (Flattened)

```php
public function processOrder(Order $order, User $user, Payment $payment): void
{
    $this->guardOrderIsProcessable($order, $user, $payment);
    $this->chargePayment($payment, $order);
    $this->fulfillOrder($order);
    $this->notifyUser($user, $order);
}

private function guardOrderIsProcessable(Order $order, User $user, Payment $payment): void
{
    throw_unless($user->isActive(), OrderException::inactiveUser());
    throw_unless($payment->isValid(), OrderException::invalidPayment());
    throw_unless($order->hasStock(), OrderException::outOfStock());
}
```

### Signs to Refactor Now

- Hard-to-test code paths
- Repeated conditionals with subtle differences
- Mixed concerns (IO, validation, transformation) in one method

---

## 15. Constants & Configuration

Avoid hardcoded values throughout your codebase.

### PHP 8.1+ Enums

```php
enum OrderStatus: string
{
    case PENDING    = 'pending';
    case PROCESSING = 'processing';
    case SHIPPED    = 'shipped';
    case DELIVERED  = 'delivered';
    case CANCELLED  = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::PENDING    => 'Pending Payment',
            self::PROCESSING => 'Processing',
            self::SHIPPED    => 'Shipped',
            self::DELIVERED  => 'Delivered',
            self::CANCELLED  => 'Cancelled',
        };
    }

    public function canTransitionTo(self $newStatus): bool
    {
        return match($this) {
            self::PENDING    => in_array($newStatus, [self::PROCESSING, self::CANCELLED]),
            self::PROCESSING => in_array($newStatus, [self::SHIPPED, self::CANCELLED]),
            self::SHIPPED    => $newStatus === self::DELIVERED,
            default          => false,
        };
    }
}

// Model with enum casting
class Order extends Model
{
    protected $casts = ['status' => OrderStatus::class];
}
```

### Configuration Files

```php
// config/app.php
return [
    'cache_ttl' => [
        'short'  => 60,
        'medium' => 300,
        'long'   => 3600,
        'day'    => 86400,
    ],
    'pagination' => ['default' => 20, 'max' => 100],
    'business'   => [
        'tax_rate'           => 0.08,
        'shipping_threshold' => 50.00,
    ],
];

// Usage
Cache::remember('products', config('app.cache_ttl.long'), fn() => Product::all());
```

### Cache Key Constants

```php
class CacheService
{
    public const USER_KEY = 'user:%d';
    public const TAG_USERS = 'users';

    public static function getUserKey(int $userId): string
    {
        return sprintf(self::USER_KEY, $userId);
    }
}
```

### Best Practices

1. Group related constants together
2. Use descriptive names (`API_TIMEOUT_SECONDS` not `TIMEOUT`)
3. Document units and meanings in comments
4. Validate against constants in setters
5. Use config for environment-specific values (never constants)
6. Create helper functions for complex constant logic

---

## 16. Config, ENV & Storage (S3/R2/MinIO/CDN)

Configure storage once; switch providers via env.

### ENV Variables

```bash
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=auto
AWS_BUCKET=...
AWS_ENDPOINT=https://r2.example.com       # for R2/MinIO
AWS_USE_PATH_STYLE_ENDPOINT=true          # if required
MEDIA_CDN_URL=https://cdn.example.com     # optional CDN
```

### config/filesystems.php

```php
's3' => [
    'driver'                  => 's3',
    'key'                     => env('AWS_ACCESS_KEY_ID'),
    'secret'                  => env('AWS_SECRET_ACCESS_KEY'),
    'region'                  => env('AWS_DEFAULT_REGION', 'us-east-1'),
    'bucket'                  => env('AWS_BUCKET'),
    'url'                     => env('AWS_URL'),
    'endpoint'                => env('AWS_ENDPOINT'),
    'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
    'throw'                   => false,
],
```

### Tips

- Prefer pre-signed URLs for uploads/downloads when possible
- For CDN, prefix public URLs with `MEDIA_CDN_URL`
- Use path-style only when necessary; some providers require it

### Testing

```php
// Fake storage in unit tests
Storage::fake('s3');

Storage::disk('s3')->put('file.txt', 'contents');
Storage::disk('s3')->assertExists('file.txt');
```

---

## 17. Custom Helpers

Create small, pure helper functions when they improve clarity.

### Create a Helper File

```php
// app/Support/helpers.php

function money(int $cents): string
{
    return '$' . number_format($cents / 100, 2);
}

function initials(string $name): string
{
    return collect(explode(' ', $name))
        ->map(fn($part) => strtoupper($part[0]))
        ->implode('');
}

function excerpt(string $text, int $length = 100): string
{
    return Str::limit(strip_tags($text), $length);
}
```

### Autoload via Composer

```json
{
    "autoload": {
        "files": ["app/Support/helpers.php"]
    }
}
```

```bash
composer dump-autoload
```

### Guidelines

- Keep helpers small and pure; avoid hidden IO/state
- Prefer static methods on value objects when domain-specific
- Test helpers like any other function — they're just PHP

---

## 18. Debugging Prompts

Debugging with AI requires complete information. Missing context means generic suggestions.

### The Golden Rule

**Complete errors → actionable feedback.** Vague descriptions → generic advice.

### Error Report Template

```
Error:       [Full error message]
Stack trace: [Complete stack trace]
File/Line:   [Where error occurs]
Context:     [Laravel version, packages, environment]
Data:        [Input data causing error]
Expected:    [What should happen]
Actual:      [What actually happened]
Attempted:   [What you already tried]
```

### Performance Issue Template

```
Problem:     [Describe slow operation]
Metrics:     [Response time, query count, memory usage]
Query log:   [Slow queries from Debugbar/Telescope]
Code:        [Code causing performance issue]
Dataset:     [Number of records involved]
Attempted:   [Optimizations already tried]
```

### Include in Every Debug Request

- Complete error messages and stack traces
- Expected vs actual behavior with examples
- Relevant code snippets
- Database state, variable values
- Laravel version, Sail/host, packages
- Logs from `storage/logs/laravel.log`

### Useful Debug Commands

```bash
# Tail logs
tail -f storage/logs/laravel.log

# Enable query logging
DB::enableQueryLog();
// ... your code ...
dd(DB::getQueryLog());

# Tinker for live debugging
php artisan tinker

# Telescope (if installed)
php artisan telescope:install
```

---

## 19. Code Review Requests

Focused review requests get actionable feedback. Vague requests get generic advice.

### Key Principles

- **Specify focus** — Security, performance, architecture, conventions
- **Provide context** — Purpose, scale, patterns, constraints
- **Ask specific questions** — Don't just ask "is this good?"
- **Reference Laravel** — Ask about framework-specific patterns
- **Set depth** — Junior needs explanations; senior needs quick feedback

### Security Review Template

```
Focus:    Security vulnerabilities and best practices
Code:     [attach code]
Context:  [authentication method, data sensitivity, user roles]
Concerns:
- SQL injection risks
- XSS vulnerabilities
- Authorization checks
- Data validation
- Sensitive data exposure
```

### Performance Review Template

```
Focus:           Performance and scalability
Code:            [attach code]
Current metrics: [response times, query counts]
Expected load:   [requests/day, concurrent users]
Concerns:
- N+1 queries
- Missing indexes
- Inefficient algorithms
- Caching opportunities
- Memory usage
```

### Laravel Conventions Review Template

```
Focus:       Laravel best practices and conventions
Code:        [attach code]
Laravel:     12.x
Concerns:
- Following framework conventions
- Using appropriate Laravel features
- Eloquent relationship correctness
- Validation approach
- Resource/response formatting
```

---

## Quick Reference Card

```bash
# ─── Artisan ──────────────────────────────────────────────
php artisan make:model Product -mfsc   # model + migration + factory + seeder + controller
php artisan make:job ProcessPayment
php artisan make:resource ProductResource
php artisan make:request StoreProductRequest
php artisan make:component Alert
php artisan make:event OrderPlaced
php artisan make:listener SendOrderConfirmation
php artisan make:policy ProductPolicy --model=Product
php artisan make:middleware EnsureUserIsSubscribed

# ─── Database ─────────────────────────────────────────────
php artisan migrate
php artisan migrate:fresh --seed
php artisan db:seed --class=ProductSeeder
php artisan tinker

# ─── Queue ────────────────────────────────────────────────
php artisan queue:work --queue=high,default --tries=3
php artisan queue:failed
php artisan queue:retry all
php artisan queue:flush

# ─── Optimization ─────────────────────────────────────────
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan optimize:clear

# ─── Testing ──────────────────────────────────────────────
php artisan test --parallel
php artisan test --filter=ProductTest
php artisan test --coverage
```

---

*Generated from Mark's Laravel superpowers skill library — all 18 skills compiled.*
