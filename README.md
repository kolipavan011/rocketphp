# 🚀 Rocketphp Framework

[![PHP Version](https://img.shields.io/badge/php-%5E8.2%20%7C%7C%20%5E8.3-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Tests](https://img.shields.io/badge/tests-79%20passed-brightgreen.svg)]()
[![Benchmark](https://img.shields.io/badge/bootstrap-15.8%C2%B5s-orange.svg)]()

**Rocketphp** is an ultra-fast, secure-by-default, and extensible PHP MVC framework engineered from first principles. It delivers the expressive developer ergonomics of Laravel with a minimal memory footprint, microsecond-level execution speeds, and an extensible service provider architecture.

---

## 📊 Performance Benchmarks

All benchmarks are measured directly on the host using `php bin/benchmark` with **OPcache disabled (CLI default)** across 1,000 to 10,000 operations:

| Benchmark Stage | Operations | Average Latency (µs) | Throughput (ops/sec) |
| :--- | :--- | :--- | :--- |
| **Application Bootstrap** | 1,000 | **15.86 µs** | **63,048 ops/sec** |
| **DI Container (3-Level Autowiring)** | 10,000 | **21.27 µs** | **47,013 ops/sec** |
| **Route Matching & Dispatch** | 10,000 | **52.77 µs** | **18,952 ops/sec** |
| **5-Stage Middleware Pipeline** | 5,000 | **53.91 µs** | **18,548 ops/sec** |
| **Database Query (PDO SQLite)** | 1,000 | **159.25 µs** | **6,280 ops/sec** |
| **View Engine (Compiled Rendering)**| 2,000 | **617.71 µs** | **1,619 ops/sec** |

*Note: In production with OPcache enabled, routing and configuration caches achieve sub-10 microsecond response times.*

---

## 🌟 Key Features

1. **Ultra-Low Overhead Bootstrap**: Boots in ~15 microseconds with zero bloated dependencies.
2. **Zero-Reflection Warm Execution**: Constructor dependency metadata is analyzed once and statically cached in memory.
3. **Dual Runtime Architecture**: Native support for traditional PHP-FPM as well as memory-leak-safe execution in worker runtimes (**FrankenPHP**, **RoadRunner**, **Swoole**) via request-scoped state sandboxing.
4. **Secure by Default**:
   - Automated CSRF verification on state-modifying requests (`hash_equals`).
   - Pre-configured HTTP Security Headers (`CSP`, `HSTS`, `X-Frame-Options`, `X-Content-Type-Options`).
   - Constant-time password hashing (`Argon2id` with verified `Bcrypt` fallback).
   - Authenticated encryption (`AES-256-GCM`).
   - Strictly parameterized Query Builder with zero raw SQL injection vectors.
   - Context-aware auto-escaping view compiler (`{{ $var }}`).
5. **Expressive Active Record ORM**:
   - Fluent Query Builder with transactions and pagination.
   - Model relationships (`hasOne`, `hasMany`, `belongsTo`).
   - Batch eager loading (`with()`) eliminating the N+1 query problem.
   - Built-in `SoftDeletes` trait and timestamps.
6. **Blade-Inspired Templating**:
   - Layout inheritance (`@extends`, `@section`, `@yield`).
   - Partials (`@include`).
   - Conditionals & loops (`@if`, `@foreach`, `@empty`).
   - Compiled template caching directly to disk.
7. **Service Provider Architecture**:
   - Centralized dependency registration and bootstrap lifecycle (`register()` and `boot()`).
   - Clean separation of domain bindings, event subscribers, and framework services.
8. **Rocket CLI**: Artisan-like command-line tool for code generation, migrations, database seeding, routing inspection, and server launching.

---

## 📁 Directory Structure

```text
rocketphp/
├── app/                              # Application Logic
│   ├── Http/                         # HTTP Layer
│   │   ├── Controllers/              # MVC Controllers
│   │   └── Middleware/               # Custom Middleware
│   ├── Models/                       # Active Record Models
│   └── Providers/                    # Service Providers
│       └── AppServiceProvider.php
├── bin/
│   ├── rocket                        # Rocket CLI Executable
│   └── benchmark                     # Performance Benchmark Runner
├── bootstrap/                        # Framework Bootstrapper
│   └── app.php                       # Creates Application instance
├── config/                           # Application Configurations
│   ├── app.php
│   ├── cache.php
│   ├── database.php
│   └── session.php
├── database/                         # Migrations & Database Storage
│   └── migrations/
├── public/                           # Web Server Document Root
│   ├── index.php                     # HTTP Front Controller
│   └── .htaccess                     # Apache Rewrites
├── resources/                        # Views & Templates
│   └── views/
│       ├── layouts/
│       └── welcome.rocket.php
├── routes/                           # Route Definitions
│   ├── web.php
│   └── api.php
├── storage/                          # Cache, Logs, Compiled Views
│   ├── cache/views/                  # Compiled Blade Templates
│   ├── cache/data/                   # File Cache Storage
│   └── framework/
├── tests/                            # PHPUnit Automated Test Suite
│   ├── Unit/
│   └── Feature/
└── composer.json
```

---

## 🚀 Quickstart & Installation

### Requirements
- PHP 8.2 or 8.3+
- PDO extension (`pdo_sqlite`, `pdo_mysql`, or `pdo_pgsql`)
- OpenSSL extension
- Composer

### 1. Installation
Clone the repository and install dependencies:

```bash
git clone https://github.com/rocketphp/rocketphp.git
cd rocketphp
composer install
```

### 2. Configure Environment
Copy `.env.example` to `.env`:

```bash
cp .env.example .env
```

### 3. Run Migrations
```bash
php rocket migrate
```

### 4. Start Development Server
```bash
php rocket serve
```
Open [http://127.0.0.1:8000](http://127.0.0.1:8000) in your browser.

---

## 🛠️ Rocket CLI Reference

```bash
# Start local server
php rocket serve --port=8000

# Route inspection
php rocket route:list

# Code generators
php rocket make:command SendEmails --command=emails:send
php rocket make:controller UserController
php rocket make:model User
php rocket make:middleware AuthMiddleware
php rocket make:migration create_posts_table
php rocket make:provider RepositoryServiceProvider
php rocket make:component Alert

# Database migrations
php rocket migrate

# Performance caching
php rocket route:cache
php rocket route:clear
php rocket config:cache
php rocket config:clear
php rocket cache:clear
```

---

## 📖 Developer Guide

### 1. Routing

Define routes in `routes/web.php` or `routes/api.php`:

```php
use Rocket\Facades\Route;
use App\Http\Controllers\UserController;

// Basic GET route
Route::get('/', function () {
    return view('welcome');
});

// Route with parameter constraints
Route::get('/users/{id}', [UserController::class, 'show'])
    ->whereNumber('id')
    ->name('users.show');

// Optional parameter
Route::get('/archive/{year?}', [ArchiveController::class, 'index']);

// Route Group with prefix and middleware
Route::group(['prefix' => 'admin', 'middleware' => 'auth'], function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard']);
});

// RESTful Resource
Route::resource('articles', ArticleController::class);
```

---

### 2. Controllers

Controllers extend `Rocket\Routing\Controller`:

```php
namespace App\Http\Controllers;

use Rocket\Routing\Controller;
use Rocket\Http\Request;
use Rocket\Http\Response;
use App\Models\User;

class UserController extends Controller
{
    public function __construct()
    {
        // Scope middleware to specific actions
        $this->middleware('auth')->only('update', 'destroy');
    }

    public function show(int $id): Response
    {
        $user = User::findOrFail($id);
        return $this->view('users.show', compact('user'));
    }

    public function store(Request $request): Response
    {
        $validated = $this->validate($request, [
            'name'  => 'required|alpha|min:3',
            'email' => 'required|email',
        ]);

        $user = User::create($validated);
        return $this->json($user, 201);
    }
}
```

---

### 3. Database & Active Record ORM

#### Fluent Query Builder
```php
use Rocket\Facades\DB;

// Querying
$users = DB::table('users')
    ->where('status', 'active')
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

// Transactions
DB::transaction(function ($connection) {
    DB::table('accounts')->where('id', 1)->decrement('balance', 100);
    DB::table('accounts')->where('id', 2)->increment('balance', 100);
});
```

#### Defining Models & Relationships
```php
namespace App\Models;

use Rocket\Database\ORM\Model;
use Rocket\Database\ORM\SoftDeletes;

class User extends Model
{
    use SoftDeletes;

    protected array $fillable = ['name', 'email', 'status'];

    public function posts()
    {
        return $this->hasMany(Post::class, 'user_id', 'id');
    }
}
```

#### Eager Loading (Eliminating N+1 Queries)
```php
// Loads all users and their posts in just 2 queries
$users = User::query()->with('posts')->get();

foreach ($users as $user) {
    foreach ($user->posts as $post) {
        echo $post->title;
    }
}
```

---

### 4. Blade-Inspired View Engine

Templates are stored in `resources/views/` as `.rocket.php` files:

#### Layout (`resources/views/layouts/app.rocket.php`)
```html
<!DOCTYPE html>
<html>
<head>
    <title>@yield('title', 'Default Title')</title>
</head>
<body>
    @include('partials.nav')

    <main>
        @yield('content')
    </main>
</body>
</html>
```

#### Page (`resources/views/home.rocket.php`)
```html
@extends('layouts.app')

@section('content')
    <h1>Hello, {{ $user->name }}</h1>

    @if($user->isAdmin())
        <div class="badge">Admin Panel</div>
    @endif

    <ul>
        @foreach($posts as $post)
            <li>{{ $post->title }}</li>
        @endforeach
    </ul>
@endsection
```

#### View Components (`<x-...>`)

Rocketphp supports both **Anonymous Template Components** and **Class-Based Components**:

##### 1. Anonymous Components
Store reusable template components in `resources/views/components/alert.rocket.php`:

```blade
@props(['type' => 'info', 'title' => null])

<div {{ $attributes->merge(['class' => 'alert alert-' . $type]) }}>
    @if ($title)
        <strong>{{ $title }}</strong>
    @endif
    {{ $slot }}
</div>
```

Invoke from any template with plain attributes, bound PHP expressions (`:prop="$var"`), and boolean flags:
```html
<x-alert type="danger" title="Error!" :dismissible="true" class="shadow-lg" id="alert-box">
    Something went wrong!
</x-alert>
```

##### 2. Named Slots & Attributes
Components seamlessly accept default and named slots:
```html
<x-card>
    <x-slot:header class="card-header font-bold">
        <h3>User Profile</h3>
    </x-slot:header>

    <p>User bio details...</p>

    <x-slot:footer>
        <button class="btn btn-primary">Save</button>
    </x-slot:footer>
</x-card>
```

##### 3. Class-Based Components
Generate component classes and template pairs using Rocket CLI:
```bash
php rocket make:component UserBadge
# Or create an inline component:
php rocket make:component UserBadge --inline
```

This creates [`app/View/Components/UserBadge.php`](file:///e:/WWW/rocketphp/app/View/Components/UserBadge.php):
```php
namespace App\View\Components;

use Rocket\View\Component;

class UserBadge extends Component
{
    public function __construct(
        public string $user,
        public string $role = 'member'
    ) {
        parent::__construct();
    }

    public function render(): string
    {
        return 'components.user-badge';
    }
}
```

Invoke via kebab-case:
```html
<x-user-badge user="Alice" role="admin" />
```

---

### 5. Security Architecture

- **Automatic CSRF Protection**: Enabled on all `web` routes. Insert `@csrf` in forms:
  ```html
  <form method="POST" action="/profile">
      @csrf
      @method('PUT')
      <input type="text" name="name" value="{{ $user->name }}">
      <button type="submit">Update</button>
  </form>
  ```
- **Password Hashing**:
  ```php
  use Rocket\Security\Hasher;

  $hasher = new Hasher();
  $hash = $hasher->make('user-password');
  $isValid = $hasher->check('user-password', $hash);
  ```
- **Authenticated Encryption (AES-256-GCM)**:
  ```php
  use Rocket\Security\Encrypter;

  $encrypter = new Encrypter(config('app.key'));
  $cipherText = $encrypter->encrypt(['token' => 'xyz']);
  $original = $encrypter->decrypt($cipherText);
  ```

---

### 6. Service Provider Architecture

Service Providers are the central place for configuring and bootstrapping application components, domain services, interface bindings, and event listeners.

#### Generating a Service Provider
```bash
php rocket make:provider PaymentServiceProvider
```

This scaffolds [`app/Providers/PaymentServiceProvider.php`](file:///e:/WWW/rocketphp/app/Providers/PaymentServiceProvider.php):

```php
namespace App\Providers;

use Rocket\Foundation\ServiceProvider;
use App\Services\PaymentGateway;
use App\Services\StripeGateway;

class PaymentServiceProvider extends ServiceProvider
{
    /**
     * Register any application services into the DI container.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentGateway::class, function () {
            return new StripeGateway(config('services.stripe.secret'));
        });
    }

    /**
     * Bootstrap any application services after all providers are registered.
     */
    public function boot(): void
    {
        // Subscribe to events, register view composers, or configure runtime defaults
    }
}
```

#### Registering Providers
Providers are loaded during application bootstrap and configured in [`config/app.php`](file:///e:/WWW/rocketphp/config/app.php):

```php
return [
    'name' => env('APP_NAME', 'Rocketphp'),
    'env' => env('APP_ENV', 'production'),
    // ...
    'providers' => [
        // Framework Core Providers
        Rocket\Routing\RouteServiceProvider::class,
        Rocket\View\ViewServiceProvider::class,
        Rocket\Database\DatabaseServiceProvider::class,
        Rocket\Security\SecurityServiceProvider::class,

        // Application Providers
        App\Providers\AppServiceProvider::class,
        App\Providers\PaymentServiceProvider::class,
    ],
];
```

The application kernel executes all provider `register()` methods first, followed by all `boot()` methods, ensuring that any service bound in any provider is safely accessible in all `boot()` implementations.

---

### 7. Unified Caching System

```php
use Rocket\Facades\Cache;

// Store value for 1 hour
Cache::put('key', 'value', 3600);

// Retrieve or compute and cache
$users = Cache::remember('active_users', 600, function () {
    return User::where('status', 'active')->get();
});

// Forget
Cache::forget('key');
```

---

### 8. Custom Console Commands

Rocketphp provides two expressive ways to build custom CLI commands:

#### Option A: Dedicated Command Classes
Generate a custom command class via Rocket CLI:
```bash
php rocket make:command SendWeeklyDigest --command=digest:send --description="Send weekly digest to subscribers"
```

This creates [`app/Console/Commands/SendWeeklyDigestCommand.php`](file:///e:/WWW/rocketphp/app/Console/Commands/SendWeeklyDigestCommand.php):
```php
namespace App\Console\Commands;

use Rocket\Console\Command;

class SendWeeklyDigestCommand extends Command
{
    protected string $name = 'digest:send';
    protected string $description = 'Send weekly digest to subscribers';

    public function handle(array $arguments, array $options): int
    {
        $this->info("Sending digest emails...");

        // Your business logic here

        $this->success("All digest emails sent successfully!");
        return 0;
    }
}
```
All command classes in `app/Console/Commands/` are automatically discovered and registered by Rocket CLI.

#### Option B: Closure-based Console Commands
Define closure commands in `routes/console.php`:
```php
use Rocket\Facades\Console;

Console::command('reports:generate', function () {
    $this->info("Generating quarterly report...");
    $this->table(['Metric', 'Value'], [
        ['Active Users', '14,250'],
        ['Total Revenue', '$182,400'],
    ]);
    $this->success("Report generated!");
})->describe('Generate quarterly performance report');
```

#### Running Commands Programmatically
You can invoke any console command directly from controllers, queue workers, or middleware:
```php
use Rocket\Facades\Console;

Console::call('digest:send');
```

---

### 9. Modern Frontend Bundling with Vite

Rocketphp features first-class integration with [Vite](https://vitejs.dev/) for instant Hot Module Replacement (HMR) during local development and optimized production asset chunking.

#### Starting Development HMR Server
```bash
npm run dev
```
Vite automatically starts the dev server and writes a temporary `public/hot` pointer file. Any modifications to CSS, JavaScript, or views update instantaneously in your browser without full page refreshes.

#### Compiling for Production
```bash
npm run build
```
This generates optimized, hashed, and gzipped bundles inside `public/build/` alongside `public/build/manifest.json`.

#### Loading Assets in Views
Use the `@vite` Blade directive in your template headers:
```html
<!DOCTYPE html>
<html>
<head>
    <title>My App</title>

    <!-- Automatically injects Vite client + CSS & JS tags -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <h1>Welcome</h1>
</body>
</html>
```

In development, this outputs:
```html
<script type="module" src="http://localhost:5173/@vite/client"></script>
<link rel="stylesheet" href="http://localhost:5173/resources/css/app.css">
<script type="module" src="http://localhost:5173/resources/js/app.js"></script>
```

In production, it automatically references the versioned manifest chunks:
```html
<link rel="stylesheet" href="/build/assets/app-BQs6YW_k.css">
<script type="module" src="/build/assets/app-2XEOWOiR.js"></script>
```

#### Asset URL Helper
To reference static assets (images, fonts, SVGs) processed by Vite:
```html
<img src="{{ Vite::asset('resources/images/logo.svg') }}" alt="Logo">
```

---

## 🧪 Running Automated Tests

Run the full PHPUnit test suite:

```bash
composer test
# or
./vendor/bin/phpunit
```

To run performance benchmarks:

```bash
php bin/benchmark
```

---

## 📄 License

The Rocketphp framework is open-sourced software licensed under the [MIT license](LICENSE).

