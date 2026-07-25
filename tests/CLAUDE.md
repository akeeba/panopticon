# Testing

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
