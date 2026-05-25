# Panopticon API — Sysconfig

> Part of the Panopticon JSON API. Back to [overview.md](./overview.md).

The Sysconfig endpoints expose Panopticon's own application configuration
(`config.php` / `.env` keys). They are the API counterpart of the
**System ▸ System Configuration** screen.

All Sysconfig endpoints require an authenticated **super-user**. Non-super tokens
receive `403 auth.forbidden`.

## Sensitive keys

A subset of configuration keys is classified as **sensitive** and treated specially:

| Key | Reason |
|-----|--------|
| `dbpass` | Database password. |
| `secret` | The application secret used for HMAC / signing. |
| `smtpuser` | SMTP authentication username. |
| `smtppass` | SMTP authentication password. |
| `caching_redis_dsn` | May embed credentials. |
| `caching_memcached_dsn` | May embed credentials. |
| `webcron_key` | Authentication token for web-cron callbacks. |
| `captcha_recaptcha_secret_key` | Captcha provider secret. |
| `captcha_hcaptcha_secret_key` | Captcha provider secret. |

Sensitive keys are **completely omitted** from the list response (`GET /v1/sysconfig`)
and yield `404 sysconfig.unknown_param` from the single-get endpoint
(`GET /v1/sysconfig/{paramName}`) so their existence is not signalled. They are also
**write-blocked** on `POST /v1/sysconfig/{paramName}` — even for super-users — with
`403 auth.forbidden`.

This is the **one** place the master plan's "no redaction" rule does not apply:
sysconfig is application-level secrets, not per-site config, and the legacy UI already
gates these keys behind super-user editing. The API matches that.

## `GET /v1/sysconfig`

List every non-sensitive sysconfig key.

```http
GET /api/v1/sysconfig HTTP/1.1
Authorization: Bearer ...
```

Response (truncated):

```json
{
  "success": true,
  "data": {
    "timezone": "UTC",
    "debug": false,
    "session_timeout": 1440,
    "max_execution": 60
  }
}
```

| Status | Code | Notes |
|--------|------|-------|
| 200 | — | Success. |
| 401 | `auth.invalid_token` | Missing/invalid token. |
| 403 | `auth.forbidden` | Not a super-user. |

## `GET /v1/sysconfig/{paramName}`

Read a single non-sensitive key. Sensitive or unknown keys both return
`404 sysconfig.unknown_param` so a caller cannot probe which sensitive keys exist.

```http
GET /api/v1/sysconfig/timezone HTTP/1.1
```

```json
{ "success": true, "data": { "timezone": "UTC" } }
```

| Status | Code | Notes |
|--------|------|-------|
| 200 | — | Success. |
| 400 | `validation.bad_request` | `paramName` missing/empty. |
| 401 | `auth.invalid_token` | |
| 403 | `auth.forbidden` | Not a super-user. |
| 404 | `sysconfig.unknown_param` | Unknown OR sensitive key. |

## `POST /v1/sysconfig/{paramName}`

Set a single non-sensitive key. Body: `{ "value": ... }`. The value is validated by
`Model\Sysconfig::validateValue()`; rejected values return
`422 sysconfig.invalid_value`.

```http
POST /api/v1/sysconfig/debug HTTP/1.1
Content-Type: application/json

{ "value": true }
```

```json
{ "success": true, "data": { "debug": true } }
```

| Status | Code | Notes |
|--------|------|-------|
| 200 | — | Updated; response carries the post-filter value. |
| 400 | `validation.bad_request` | Missing `paramName` or missing `value` field. |
| 401 | `auth.invalid_token` | |
| 403 | `auth.forbidden` | Not a super-user, OR a sensitive key was targeted. |
| 404 | `sysconfig.unknown_param` | Unknown key. |
| 422 | `sysconfig.invalid_value` | Value rejected by validation. |

Successful writes emit an audit event `sysconfig.set` with `details: {"param": name}`.
**The new value is never logged** — it could itself be a credential.

## Audit events

| Event | Triggered by |
|-------|--------------|
| `sysconfig.set` | Successful `POST /v1/sysconfig/{paramName}`. |

## Tests

- Happy path for `set` (toggling `debug`) is skipped at integration level because it
  writes to `config.php`; the `403` / `404` / `422` failure paths are covered instead.

## See also

- [overview.md](./overview.md) — cross-cutting concerns (auth, envelope, error codes).
- [`../openapi.yaml`](../openapi.yaml) — machine-readable OpenAPI 3.1 specification.
