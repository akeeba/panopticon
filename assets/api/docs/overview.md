# Panopticon API — Overview

The Akeeba Panopticon JSON API exposes the same operations as the web UI for automation. It is
versioned under `/api/v1/…` and uses a stable response envelope with machine-readable error codes
so clients can switch on `code` rather than parse human messages.

This document is the authoritative reference for cross-cutting concerns (authentication, the
response envelope, error codes, pagination, security policy). Per-endpoint documentation lives
under `assets/api/docs/<group>.md` and is mirrored to the GitHub wiki.

## Endpoint URLs

With `mod_rewrite` (recommended) or an nginx equivalent:

```
https://panopticon.example.com/api/v1/sites
```

Without URL rewriting (e.g. a barebones Apache without `mod_rewrite`):

```
https://panopticon.example.com/index.php/api/v1/sites
```

Both forms route to the same dispatcher; tests in the wild can use whichever the host supports.

## Authentication

Every API request must carry an API token. Tokens are minted from the web UI: log in, open
**System ▸ API tokens**, click **New**, copy the generated token (shown once — Panopticon stores
only its seed). Three transports are supported; the first found wins.

### 1. `Authorization: Bearer …` header (recommended)

```http
GET /api/v1/sites HTTP/1.1
Authorization: Bearer eW91ci10b2tlbi1oZXJl
```

This is the recommended form for production automations: it is not logged by default, it is not
echoed in browser `Referer` headers, and it works with every HTTP client.

> **Apache + PHP-FPM gotcha.** Apache strips the `Authorization` header before it reaches PHP-FPM
> unless `CGIPassAuth On` is enabled or the supplied `SetEnvIf Authorization "(.*)"
> HTTP_AUTHORIZATION=$1` rule (present in the shipped `htaccess.txt`) is active. If Bearer auth
> consistently returns 401 from a working token, check this first.

### 2. `X-Panopticon-Token: …` header

```http
GET /api/v1/sites HTTP/1.1
X-Panopticon-Token: eW91ci10b2tlbi1oZXJl
```

Useful in environments where you cannot set an `Authorization` header (some legacy reverse
proxies, some logging-pipeline constraints).

### 3. `_panopticon_token` query parameter

```
GET /api/v1/sites?_panopticon_token=eW91ci10b2tlbi1oZXJl
```

> **Security warning.** Query-string tokens are written to web-server access logs in plain text,
> copied into the browser `Referer` header on cross-origin navigation, and frequently end up in
> support tickets and shell history. This transport exists to make ad-hoc testing trivial. **Do
> not use it in production.** If you must, scope the token to a dedicated low-privilege user and
> rotate it aggressively.

### 401 vs 403

- **401 Unauthorized** — no token, malformed token, expired token, or token that no longer
  matches a row in the database. Every 401 response carries
  `WWW-Authenticate: Bearer realm="Panopticon API"`.
- **403 Forbidden** — token is valid and the user is identified, but the user lacks the
  privilege required for the operation.

## Response envelope

### Success

```json
{
  "success": true,
  "data": { … or [ … ] },
  "message": "Optional human-readable note",
  "pagination": {
    "total":  123,
    "limit":  50,
    "offset": 0
  }
}
```

`pagination` is present on list endpoints only. `message` is present when the server has a
notable informational message; clients MAY display it but MUST NOT switch on its contents.

### Error

```json
{
  "success": false,
  "code":    "auth.invalid_token",
  "message": "Invalid or missing API token."
}
```

`code` is a stable, machine-readable identifier in dotted form (`group.specific`). New codes are
strictly additive within a major API version. Clients SHOULD switch on `code`, never on
`message`.

## Error codes

The codes below are emitted by the Phase 1 (read-only Sites) surface and the token management
endpoints. Phase 2 sub-plans extend this table.

| Code                    | HTTP | Meaning                                                                       |
|-------------------------|------|-------------------------------------------------------------------------------|
| `auth.required`         | 401  | Authentication required (helper alias).                                       |
| `auth.invalid_token`    | 401  | Token missing, malformed, expired, disabled, or unknown.                      |
| `auth.forbidden`        | 403  | Authenticated, but lacks the required privilege (per-site ACL or super-user). |
| `route.not_found`       | 404  | The URL did not map to a known handler.                                       |
| `site.not_found`        | 404  | A site was addressed by id and does not exist.                                |
| `validation.bad_request`| 400  | A query/body parameter failed validation (e.g. unknown `cmsType`).            |
| `request.invalid_json`  | 400  | The JSON body could not be parsed.                                            |
| `token.limit_exceeded`  | 409  | Per-user enabled-token cap (currently 50) reached.                            |

## Pagination

List endpoints accept `limit` and `offset` query parameters. Default `limit` is 50; both are
clamped at zero (no negatives). The response carries `pagination.total` (rows matching the
filter, ignoring `limit`/`offset`), `pagination.limit`, and `pagination.offset` so clients can
compute next/previous pages.

## Secrets and the `config` field

Site responses include the **complete** site configuration Registry — download keys, basic-auth
credentials, custom HTTP headers and every other secret the UI lets you set against a site. The
chosen trust model is:

> Token confidentiality is sufficient. There is no in-API redaction layer.

Consequences:

- Mint tokens **only** for automations you trust at the same level as the user account that
  owns the token.
- Treat a leaked token as a leak of every site's full configuration for that user, immediately.
- The web UI shows a token's plain-text value **once** at creation. Store it in a secret manager,
  not in shell history or chat tools.

## HTTP status codes

| Status | When                                                                        |
|--------|------------------------------------------------------------------------------|
| 200    | Success with a body.                                                         |
| 201    | A resource was created. The response body carries the new resource.          |
| 204    | Success with no body (rare; used by some idempotent POSTs).                  |
| 400    | Validation error in the request (see `validation.bad_request`).              |
| 401    | Missing or invalid token. Carries `WWW-Authenticate`.                        |
| 403    | Authenticated, but forbidden.                                                |
| 404    | No such route or resource.                                                   |
| 409    | A precondition failed (`token.limit_exceeded`, optimistic-concurrency, …).   |
| 422    | Semantically valid request, but cannot be processed in the current state.    |
| 500    | Server error. Body is best-effort JSON.                                      |

## Token expiry, rate limits, and audit logging

- Tokens may carry an `expires_at` timestamp set by the UI. Expired tokens fail authentication
  with `auth.invalid_token`. Clients receive no extra signal — rotate proactively.
- There is no global rate limiter; per-IP login-failure counting still applies, so spamming
  invalid tokens from one IP will trip the standard lockout machinery.
- Every authentication attempt (success or failure) is written to `#__audit_log` with the
  action `apitoken.auth_success` / `apitoken.auth_failure` and the binary client IP.
- Token CRUD operations from the UI emit `apitoken.create`, `apitoken.toggle`,
  `apitoken.delete`, and `apitoken.view`.

## Versioning

`v1` is the current major version. Within `v1` we only make additive changes (new fields, new
endpoints, new optional parameters). Breaking changes require a new `v2` namespace and overlap
with `v1` for a deprecation window.
