# AI Coding Agent Instructions — RocketPHP

This repository uses **RocketPHP**, a custom lightweight PHP 8.2/8.3+ MVC framework. Do **not** assume it behaves like Laravel, Symfony, or CodeIgniter.

## Mandatory Rules

1. **Read [`docs/00.AI_AGENT_GUIDE.md`](docs/00.AI_AGENT_GUIDE.md) first** before planning or writing any code.
2. **Consult relevant topic documentation** in [`docs/README.md`](docs/README.md) before modifying routes, controllers, models, views, middleware, security, or configuration.
3. **Verify every API against source code** (`src/`) before calling it. Never invent Laravel helpers, facades, validation rules, or ORM methods that do not exist in RocketPHP.
4. **Keep application code in `app/`, `routes/`, `config/`, `resources/`, and `database/`.** Do not modify framework core files (`src/`) when the same behavior can be implemented in the application layer (e.g., via `AppServiceProvider`, middleware, controllers, or custom classes).
5. **Follow existing conventions**: `declare(strict_types=1);`, PSR-4 (`Rocket\` -> `src/`, `App\` -> `app/`), explicit parameter/return types, constructor/method DI, and zero unnecessary third-party dependencies.
6. **Maintain documentation**: Update the relevant file(s) in `docs/` whenever you change framework APIs, configuration, conventions, or extension points.
7. **Run verification after edits**: Run `.\vendor\bin\phpunit` (or `composer test`) to confirm all tests pass before finishing.
