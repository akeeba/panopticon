# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Akeeba Panopticon is a self-hosted PHP application for remotely monitoring and managing Joomla and WordPress sites. It tracks updates, extensions, backups, SSL certificates, domain expiration, uptime, and security issues across multiple CMS installations.

## Related Connector Extensions

Panopticon requires a connector extension on each monitored site:

- **[panopticon_connector_j3](https://github.com/akeeba/panopticon_connector_j3)** — Joomla 3.9 and 3.10 only
- **[panopticon-connector](https://github.com/akeeba/panopticon-connector)** — Joomla 4.x, 5.x, and 6.x
- **[panopticon-connector-wordpress](https://github.com/akeeba/panopticon-connector-wordpress)** — WordPress 5.x and 6.x

## Documentation Wikis

GitHub Wiki working copies are checked out under `~/Projects/wiki/`:

- `~/Projects/wiki/panopticon` — [Panopticon wiki](https://github.com/akeeba/panopticon/wiki)

The same convention applies to connector extension wikis (e.g. `~/Projects/wiki/panopticon-connector`).

## Build & Development Commands

```bash
# Full setup (installs PHP deps, runs npm install, compiles SCSS, transpiles JS, generates version.php, updates DB schema)
composer install

# Update PHP dependencies (also triggers full frontend build)
composer update

# Rebuild frontend assets only (npm install + copy deps + babel + SCSS + TinyMCE langs)
composer run-script npm-deps

# Compile SCSS only
composer run-script scss

# CLI entry point
php cli/panopticon.php list                    # List all commands
php cli/panopticon.php help <command>          # Help for specific command
php cli/panopticon.php config:create           # Create configuration
php cli/panopticon.php task:run                # Execute background tasks
php cli/panopticon.php database:backup         # Backup database
php cli/panopticon.php database:update         # Update database schema
```

## Testing

PHPUnit suite in `tests/`. Requires `composer install` (dev deps) and a separate test database.

```bash
composer test               # both suites (unit + integration)
composer test:unit          # unit suite only — no DB needed, fast
composer test:integration   # integration suite only
```

Integration tests need `.env.test` (copy from `.env.test.example`). Key env vars:
- `PANOPTICON_DBNAME` — must differ from your dev/prod DB name (bootstrap enforces this)
- `PANOPTICON_SECRET` — non-empty string; generate with `openssl rand -hex 32`

Integration tests run in `BEGIN … ROLLBACK` transactions — no rows persist between tests. Never use DDL in integration tests (it implicitly commits in MySQL).

**Test helpers** (for API integration tests in `tests/Integration/Api/`):
- `invokeHandler(class, inputData)` — call a handler directly
- `dispatchApi(suffix, inputData)` — full `Api::dispatch` flow including token auth
- `loginAs(userId)` — bypass token auth in tests
- `setJsonRequestBody(body)` — install `php://input` stream wrapper

CI runs `composer test` on every push to `main` and on PRs (`.github/workflows/php.yml`).

## Architecture

### Framework
Built on **Akeeba Web Framework (AWF)** — a custom MVC framework. Not Joomla, Laravel, or Symfony (though it uses Symfony components for CLI, caching, error handling, and serialization).

### MVC Pattern
- **Controllers**: `src/Controller/` — handle HTTP requests
- **Models**: `src/Model/` — business logic and data access (extend AWF DataModel/Model)
- **View Classes**: `src/View/` — data preparation for templates
- **View Templates**: `ViewTemplates/` — Blade templates (`.blade.php`)

### Dependency Injection
`src/Container.php` extends AWF's Container. Key services registered as lazy factory closures:
- `appConfig` (Configuration), `cacheFactory`, `httpFactory`, `mailer`, `taskRegistry`, `loggerFactory`, `logger`, `queueFactory`
- Access via `Factory::getContainer()`

### Background Task System
Central to the application. Tasks run via CRON (`php cli/panopticon.php task:run`) or web CRON.
- **Task implementations**: `src/Task/` — 27 task types (backups, updates, monitoring, email, scanning)
- **Task registry**: `src/Library/Task/Registry.php` — discovers tasks via PHP 8 attributes
- **Director pattern**: "Director" tasks orchestrate per-site tasks (e.g., `JoomlaUpdateDirector` creates individual `JoomlaUpdate` tasks per site)

### CLI Commands
80+ Symfony Console commands in `src/CliCommand/`. Each uses PHP 8 attributes for metadata. Categories: config, site, user, group, task, backup/scanner schedules, mail templates, database, self-update, log rotation.

### Plugin System
`src/Plugin/` with `PluginHelper` for discovery. User-extensible via `user_code/` directory (included in PSR-4 autoload under `Akeeba\Panopticon\` namespace).

### Key Libraries (`src/Library/`)
- `Cache/` — Symfony Cache abstraction (filesystem, Redis, Memcached)
- `Http/` — Guzzle HTTP client factory with caching middleware
- `Task/` — Task queue and registry core
- `MultiFactorAuth/`, `Passkey/`, `Password/` — Authentication (WebAuthn/FIDO2, TOTP, HIBP checking)
- `Mailer/` — Email sending
- `Logger/` — Monolog-based logging with rotation
- `SelfUpdate/` — Self-update mechanism
- `Enumerations/` — PHP 8.1 enums

### Frontend
- **CSS**: Bootstrap 5.3, FontAwesome 6, custom SCSS in `media/scss/` compiled to `media/css/`
- **JS**: Vanilla JS + Petite Vue for reactivity, transpiled via Babel. Source and output both in `media/js/`
- **Editors**: TinyMCE (rich text), ACE (code editing)
- **Select fields**: Choices.js
- Node dependencies are copied to `media/` subdirectories by the Composer build script (`src/Composer/InstallationScript.php`), not served from `node_modules/`

### Database
MySQL 5.7+ or MariaDB 10.3+. Schema defined in `src/schema/mysql.xml` (Phing format). Table prefix: `pnptc_`. Drivers: PDO MySQL (preferred), MySQLi (fallback).

### Configuration
- `config.php` — main app config (generated by installer or CLI)
- `.env` files — environment variables via phpdotenv (supports `.env.{PANOPTICON_ENVIRONMENT}`)
- `defines.php` — path constants (`APATH_ROOT`, `APATH_MEDIA`, `APATH_CACHE`, etc.)
- `user_code/.env` — user override environment

### Translation
Language files in `languages/` using `.ini` format. English (GB) is the only officially maintained language.

## Coding Conventions

- **PHP**: Tabs for indentation, Allman brace style (opening braces on new line), `else`/`catch`/`finally` on new line
- **PHP version**: 8.1 minimum, 8.3 recommended. Uses typed properties, union types, named arguments, constructor promotion, match expressions, attributes, enums, nullsafe operator
- **SCSS**: 2-space indentation
- **JS**: 4-space indentation, double quotes, semicolons, Allman brace style
- **JSON**: Tab indentation
- **Line length**: 120 characters max
- **Namespace**: `Akeeba\Panopticon\{Component}` with PSR-4 autoloading from `src/` and `user_code/`
- **Security guard**: All PHP files start with `defined('AKEEBA') || die;`
- **License header**: Every PHP file has a `@package panopticon` / `@copyright` / `@license` docblock

## Release Process

Panopticon deviates from the generic Akeeba release-workflow recipe at the final "full release" step. Use:

```bash
phing all release update -Dversion=X.Y.Z
```

**Not** `phing all release docsdeploy` (the generic recipe) and **not** `phing all -Dversion=X.Y.Z` with `release`/`update` folded into `all`'s dependency chain — both are broken here.

Why: `build.xml`'s `all` target used to `depend` on `release,update`. `release` internally does `<phingcall target="github-release">` (`release.method=github` in `build/build.properties`). Phing 3.1.2 has a regression where this phingcall throws `"... task calling a target that depends on its parent target 'release'."` whenever *any* target in the project depends on the target the phingcall is nested in — regardless of which target you actually invoke from the command line (Phing parses the whole buildfile up front, so the mere existence of the dependency triggers it). `all`'s `depends` list was changed to just `git,documentation`; `release` and `update` must be invoked as separate top-level targets in the same `phing` command so their own dependency chains (`new-release`, `setup-properties`, etc.) still get satisfied once, in order, without re-triggering `new-release` (which wipes `release/`) after the package/zip has already been built.

Also, `docsdeploy` is a no-op in this repo (`common.xml`'s default "not set up" stub) — `update` is what actually matters, since it generates `release/update.json` and uploads it to getpanopticon.com via `updatejson`.

This is a **panopticon-specific** fix, not a shared-`buildfiles`/`common.xml` issue: an audit of every `build.xml` under `~/Projects/akeeba`, `~/Projects/j4-akeeba`, and `~/Projects/dionysopoulos` found panopticon was the only repo whose `all` target depended on `release`/`update`, and the only repo with a local `update` target override. Don't port this fix to `common.xml` or other repos.

## Docker

`Dockerfile` uses PHP + Apache. `docker-compose.yml` for full stack. Alternative FrankenPHP setup in `docker-compose-frankenphp.yml`. Docker detection via absence of `src/.not_docker` file.

Docker image built and pushed to GHCR automatically on `git push --tags` (normal release path).

When asked to build, test, or manually publish the Docker image to GHCR, read `assets/Docker Build.md` first — it contains the full step-by-step procedure including prerequisites, local testing with `docker-compose.override.yml`, tagging conventions, and multi-arch Buildx commands.
