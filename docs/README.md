# RocketPHP AI Developer Documentation Index

Official source-verified technical documentation for AI coding agents working with **RocketPHP** (`v1.0.0`, PHP `^8.2 || ^8.3`).

> **Start Here:** Every AI coding agent **must** read [`00.AI_AGENT_GUIDE.md`](00.AI_AGENT_GUIDE.md) before modifying code.

---

## Documentation Directory

| # | Document | Summary |
| :--- | :--- | :--- |
| **00** | [`00.AI_AGENT_GUIDE.md`](00.AI_AGENT_GUIDE.md) | **Mandatory quick-start guide**, workflow, agent rules, and common mistakes to avoid |
| **01** | [`01.ARCHITECTURE.md`](01.ARCHITECTURE.md) | Bootstrapping, request/CLI lifecycle, DI container lifetimes, and Mermaid diagrams |
| **02** | [`02.PROJECT_STRUCTURE.md`](02.PROJECT_STRUCTURE.md) | Full directory tree, core vs application ownership, and modification safety |
| **03** | [`03.ROUTING.md`](03.ROUTING.md) | HTTP verbs, groups, parameters, constraints, named URLs, resources, and caching |
| **04** | [`04.CONTROLLERS.md`](04.CONTROLLERS.md) | Base `Controller`, constructor/method DI, `Request` API, and `Response` types |
| **05** | [`05.DATABASE_AND_MODELS.md`](05.DATABASE_AND_MODELS.md) | PDO connections, Query Builder, Active Record `Model`, relations, `SoftDeletes`, migrations |
| **06** | [`06.VIEWS_AND_TEMPLATES.md`](06.VIEWS_AND_TEMPLATES.md) | `.rocket.php` directives, layouts, `<x-...>` components, slots, `@props`, Vite, `asset()` |
| **07** | [`07.MIDDLEWARE.md`](07.MIDDLEWARE.md) | Onion `Pipeline`, `MiddlewareInterface`, `Kernel` registration, route/controller scoping |
| **08** | [`08.SECURITY.md`](08.SECURITY.md) | Verified security features vs developer responsibilities and limitations |
| **09** | [`09.HELPERS_AND_SERVICES.md`](09.HELPERS_AND_SERVICES.md) | Global helpers, container bindings, `Facade` classes, `Cache`, and abstractions |
| **10** | [`10.CONFIGURATION.md`](10.CONFIGURATION.md) | `.env` parsing, `config/*.php`, debug mode, and production configuration |
| **11** | [`11.CLI_AND_COMMANDS.md`](11.CLI_AND_COMMANDS.md) | All 17 built-in `php rocket` commands, generators, storage links, custom commands |
| **12** | [`12.PLUGINS_AND_EXTENSIONS.md`](12.PLUGINS_AND_EXTENSIONS.md) | Service Provider architecture, extension points, and removed plugin system notice |
| **13** | [`13.PERFORMANCE.md`](13.PERFORMANCE.md) | Reflection/route/view optimizations, measured host benchmarks, production tuning |
| **14** | [`14.ERROR_HANDLING_AND_LOGGING.md`](14.ERROR_HANDLING_AND_LOGGING.md) | `Kernel::handleException()`, status codes, JSON/HTML error views, logging status |
| **15** | [`15.CODING_CONVENTIONS.md`](15.CODING_CONVENTIONS.md) | Strict types, PSR-4/PSR-12 standards, naming, OOP/DI rules, testing rules |
| **16** | [`16.COMMON_RECIPES.md`](16.COMMON_RECIPES.md) | Copy-ready examples for pages, CRUD, forms, auth middleware, file uploads, services |
| **17** | [`17.KNOWN_LIMITATIONS.md`](17.KNOWN_LIMITATIONS.md) | Missing subsystems, verified quirks/bugs, and unsupported functionality |

See also root [`AGENTS.md`](../AGENTS.md).
