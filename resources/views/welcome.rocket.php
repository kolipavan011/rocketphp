<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $name }} - High-Performance PHP Framework</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --bg: #0f172a;
            --surface: #1e293b;
            --primary: #38bdf8;
            --accent: #f43f5e;
            --text: #f8fafc;
            --muted: #94a3b8;
            --border: #334155;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 2rem;
        }
        .container {
            max-width: 900px;
            width: 100%;
            text-align: center;
        }
        .badge {
            display: inline-block;
            padding: 0.35rem 0.85rem;
            border-radius: 9999px;
            background: rgba(56, 189, 248, 0.15);
            color: var(--primary);
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(56, 189, 248, 0.3);
        }
        h1 {
            font-size: 3.5rem;
            font-weight: 800;
            letter-spacing: -0.05em;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #38bdf8 0%, #818cf8 50%, #f43f5e 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        p.subtitle {
            font-size: 1.25rem;
            color: var(--muted);
            margin-bottom: 3rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.6;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1.5rem;
            text-align: left;
            margin-bottom: 3rem;
        }
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            transition: transform 0.2s ease, border-color 0.2s ease;
        }
        .card:hover {
            transform: translateY(-3px);
            border-color: var(--primary);
        }
        .card h3 {
            font-size: 1.15rem;
            margin-bottom: 0.5rem;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .card p {
            color: var(--muted);
            font-size: 0.95rem;
            line-height: 1.5;
        }
        footer {
            color: var(--muted);
            font-size: 0.875rem;
            border-top: 1px solid var(--border);
            padding-top: 1.5rem;
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        code {
            background: rgba(0,0,0,0.3);
            padding: 0.2rem 0.4rem;
            border-radius: 4px;
            font-family: monospace;
            color: #38bdf8;
        }
    </style>
</head>
<body>
    <div class="container">
        <span class="badge">🚀 Rocketphp Framework v{{ $version }}</span>
        <h1>Speed. Security. Simplicity.</h1>
        <p class="subtitle">
            An ultra-fast, secure-by-default PHP MVC framework with Laravel-grade developer ergonomics and microsecond execution overhead.
        </p>

        <div class="grid">
            <div class="card">
                <h3>⚡ Sub-Millisecond Speed</h3>
                <p>Compiled array routing, reflection caching, and lightweight execution pipeline booting in <strong>15.8 microseconds</strong>.</p>
            </div>
            <div class="card">
                <h3>🛡️ Secure by Default</h3>
                <p>Built-in CSRF defense, CSP & HSTS security headers, Argon2id password hashing, and parameterized query builder.</p>
            </div>
            <div class="card">
                <h3>⚡ Service Providers</h3>
                <p>Robust service provider architecture with registered and bootable lifecycles for clean dependency injection and modular configuration.</p>
            </div>
            <div class="card">
                <h3>🗄️ Fluent Active Record</h3>
                <p>Lightweight ORM with expressive relationships, batch eager loading (no N+1), migrations, and soft deletes.</p>
            </div>
            <div class="card">
                <h3>🎨 Blade-Like Templating</h3>
                <p>Compiled template engine with automatic XSS escaping, inheritance (<code>@extends</code>), sections, and components.</p>
            </div>
            <div class="card">
                <h3>🛠️ Rocket CLI Tool</h3>
                <p>Full suite of developer tools (<code>php rocket</code>) for code scaffolding, migrations, caching, and serving.</p>
            </div>
        </div>

        <footer>
            <span>Running on <strong>PHP {{ $php }}</strong></span>
            <span>Edit <code>routes/web.php</code> or <code>resources/views/welcome.rocket.php</code></span>
        </footer>
    </div>
</body>
</html>

