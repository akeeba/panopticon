# Running the tests locally

Panopticon ships a PHPUnit suite under `tests/`. This document explains how to
run it on your machine. CI is set up to run the same suite on every push, but
running locally before you push catches problems faster.

## What's in the suite

- **`tests/Unit/`** — pure unit tests. No database. Fast. Safe to run anywhere.
- **`tests/Integration/`** — full-stack tests against a real MySQL/MariaDB
  database. Every test is wrapped in a `BEGIN ... ROLLBACK` so rows do not
  persist between tests. The schema is applied once at bootstrap.

Both suites share `tests/bootstrap.php`, which builds the Panopticon Container
**without** booting the Application (no template loading, no MFA prompts, no
setup redirects). That's why tests can run from the CLI with no HTTP request
in flight.

## One-time setup

### 1. Create a dedicated test database

The integration suite needs a real database. **It must be a throwaway database
— never your production / development DB.** The bootstrap will refuse to run
if the test DB name matches the dbname declared in `config.php` (production
sanity guard).

Pick whatever name + credentials you like. Example:

```sql
CREATE DATABASE panopticon_test
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE USER 'panopticon_test'@'127.0.0.1'
    IDENTIFIED BY 'change-me-locally';

GRANT ALL PRIVILEGES ON panopticon_test.*
    TO 'panopticon_test'@'127.0.0.1';

FLUSH PRIVILEGES;
```

The user needs `ALL PRIVILEGES` (or at least DDL + DML + transactions) on the
test database, because the bootstrap applies the schema via the same installer
the `database:update` CLI command uses.

### 2. Create `.env.test`

Copy the example and fill in the values for the database you just created:

```bash
cp .env.test.example .env.test
$EDITOR .env.test
```

Key points:

- `PANOPTICON_DBNAME` **must differ** from your production / development
  `$dbname` in `config.php`. Bootstrap aborts otherwise.
- `PANOPTICON_PREFIX` should also differ (defaults to `pnptctest_` in the
  example) so the test schema can't ever collide with a live install in the
  same database — defence in depth.
- `PANOPTICON_SECRET` must be a non-empty string. Generate one with
  `openssl rand -hex 32`. Token-auth tests depend on it.
- `.env.test` is in `.gitignore` — never commit it.

### 3. Install Composer dev dependencies

PHPUnit and its supporting libraries are in `require-dev`. If you've only run
`composer install --no-dev` so far, install them now:

```bash
composer install
```

Verify PHPUnit landed at `vendor/bin/phpunit`.

## Running the suite

The `composer test*` scripts are the canonical entry points:

```bash
composer test               # both suites (unit + integration)
composer test:unit          # unit suite only — fast, no DB needed
composer test:integration   # integration suite only
```

Direct PHPUnit invocation works too:

```bash
vendor/bin/phpunit                                # both suites
vendor/bin/phpunit --testsuite=unit               # unit suite only
vendor/bin/phpunit --filter=ApitokenTest          # a single test class
vendor/bin/phpunit --filter=testIsExpired         # a single test method by name
vendor/bin/phpunit tests/Unit/Model/ApitokenTest.php   # a single file
vendor/bin/phpunit --testdox                      # human-readable test names
vendor/bin/phpunit --display-warnings             # show suppressed warnings
```

Expect the full suite to finish in under 10 seconds on a modern laptop.

## What the bootstrap does

When PHPUnit fires `tests/bootstrap.php`:

1. Defines `AKEEBA` and `AKEEBA_PANOPTICON_TEST`.
2. Forces `PANOPTICON_ENVIRONMENT=test` so the live env loader picks up
   `.env.test` (rather than `.env` or `.env.production`).
3. Silences `E_DEPRECATED`. PHP 8.4 deprecated `mysqli_ping()`, which AWF
   still calls. Without this, the deprecation warning prints before any test
   runs and breaks `http_response_code()` inside the API tests.
4. Parses the test DB name out of `.env.test` and asserts it does **not**
   match the dbname declared in `.env`, `.env.production`, or `config.php`.
   Exits 2 with a clear message if they match.
5. Builds the Container, loads configuration, wires up the Panopticon
   `User` subclass + privilege plugin (`BootstrapUtilities::setUpUserManager()`).
6. Applies the schema (`Awf\Database\Installer` against `src/schema/`). The
   bootstrap is idempotent — re-running tests doesn't re-create tables.

## How integration tests stay isolated

`AbstractIntegrationTestCase::setUp()` opens a database transaction.
`tearDown()` rolls it back. As long as a test sticks to DML (INSERT / UPDATE /
DELETE), nothing it writes survives.

**Watch out:** DDL (CREATE TABLE, ALTER TABLE, TRUNCATE) implicitly commits in
MySQL and breaks the rollback. Integration tests must not run DDL. The schema
is applied once at bootstrap and stays put.

If you need to set up DB state before a test runs, use the helpers on
`AbstractIntegrationTestCase`:

- `createUser(array $overrides = []): Awf\User\User`
- `createApiToken(int $userId, array $overrides = []): array{token: string, row: Apitoken}`

For API handlers, additional helpers live in
`tests/Integration/Api/AbstractApiIntegrationTestCase`:

- `invokeHandler(string $handlerClass, array $inputData = []): array{status, body, raw, headers}`
- `dispatchApi(string $handlerSuffix, array $inputData = []): array{...}` — invokes the full
  `Api::dispatch` flow including token auth.
- `loginAs(int $userId): void` — sets `userManager->currentUser` directly without going
  through token auth.
- `setJsonRequestBody(array|string $body): void` — installs a `php://input` stream wrapper so
  handlers that read JSON bodies see the right payload.
- `forceUnknownCmsType(int $siteId): void` — direct SQL to bypass `Site::check()` normalisation.

## Adding new tests

- Unit tests go in `tests/Unit/<package-path>/`. Extend
  `Akeeba\Panopticon\Tests\AbstractUnitTestCase`. No database. The class
  namespace is `Akeeba\Panopticon\Tests\Unit\…`.
- Integration tests go in `tests/Integration/<package-path>/`. Extend
  `AbstractIntegrationTestCase` (or, for API endpoints, the
  `AbstractApiIntegrationTestCase` subclass). Namespace is
  `Akeeba\Panopticon\Tests\Integration\…`.

The test autoload is configured under `autoload-dev` in `composer.json` —
`Akeeba\Panopticon\Tests\` → `tests/`. Run `composer dump-autoload` if you
add a new top-level directory under `tests/`.

## Common problems

### `REFUSING to run tests: loaded dbname ... matches production`

Your `.env.test` is pointing at the same database as your real install. Edit
`.env.test` and set `PANOPTICON_DBNAME` to something else. Re-check that the
test DB exists and the credentials are right.

### `Cannot override frozen service "input"`

You touched `$container->input` in a test without unset-then-re-register. Use
the `AbstractApiIntegrationTestCase` helpers — they handle the Pimple
frozen-service guard.

### `Test code or tested code closed output buffers other than its own`

API handlers call `@ob_end_clean()` internally before echoing JSON. Tests
that invoke a handler must wrap it in two stacked `ob_start()` levels (the
inner gets eaten, the outer captures the JSON). The `invokeHandler` /
`dispatchApi` helpers already do this — call them rather than rolling your own.

### `Call to undefined method Awf\User\User::authorise()`

`tests/bootstrap.php` calls `BootstrapUtilities::setUpUserManager()` which
swaps in the Panopticon `User` subclass. If you've rewritten the bootstrap
or the userManager binding, restore that call.

### `"The password you are trying to use is present in online password leaks"`

The HIBP check fired against a test password. `createUser` disables HIBP and
generates a random non-leaked password — use that helper rather than calling
`saveUser` directly with a hardcoded password.

### `PANOPTICON_*_ERR_..._EMPTY`

A DataModel `NOT NULL` field is empty. For `created_by` specifically, make
sure you've called `loginAs()` before saving — `Site::check()` reads the
user id from `userManager->getUser()`.

## Continuous integration

`.github/workflows/php.yml` runs `composer test` on every push. The workflow
provisions a MariaDB service, copies `.env.test.example` to `.env.test` with
credentials matching the service, and runs the same `composer test` you run
locally. If CI fails on a test that passes locally, the most common cause is
a stale `.env.test` differing from CI's — check what `.env.test.example`
expects.
