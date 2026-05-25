# Panopticon API — Tasks

> Part of the Panopticon JSON API. Back to [overview.md](./overview.md).

Tasks are background jobs executed by Panopticon's task runner. Each task has a `type`
(matched against the task registry), a cron expression, an enabled flag, and per-type
`params`. Tasks may belong to a site (`site_id`) or be system-wide (`site_id` null).

## Access control

- **Super-user**: full access to every endpoint, every task.
- **Non-super user with `panopticon.admin` on a site**: can list / read / create / modify
  tasks **scoped to that site only**. System tasks (`site_id` null/0) always require
  super-user.

Listing without an explicit `site_id` filter requires super-user.

## `GET /v1/tasks`

List tasks with pagination.

Query parameters:

| Name | Type | Default | Notes |
|------|------|---------|-------|
| `site_id` | int | — | Filter to a specific site. Use `0` for system tasks (interpreted as `site_id IS NULL`). |
| `type` | string | — | Filter by task type identifier. |
| `enabled` | bool | — | Filter by enabled flag. |
| `limit` | int | 50 | Clamped to `[0, 200]`. |
| `offset` | int | 0 | Clamped to `>= 0`. |

```http
GET /api/v1/tasks?site_id=4&enabled=1&limit=20 HTTP/1.1
```

| Status | Code | Notes |
|--------|------|-------|
| 200 | — | Paginated list. |
| 401 | `auth.invalid_token` | |
| 403 | `auth.forbidden` | Non-super and missing/ineligible `site_id`. |

## `GET /v1/task/{id}`

Read a single task by id.

| Status | Code | Notes |
|--------|------|-------|
| 200 | — | Task record. |
| 400 | `validation.bad_request` | `id` missing/non-positive. |
| 401 | `auth.invalid_token` | |
| 403 | `auth.forbidden` | Non-super, and lacks `panopticon.admin` on the task's site, OR target is a system task. |
| 404 | `task.not_found` | Unknown task id. |

## `PUT /v1/task`

Create a new task. Body:

```json
{
  "site_id": 4,
  "type": "joomlaupdate",
  "cron_expression": "0 3 * * *",
  "enabled": true,
  "params": {}
}
```

Omit `site_id` (or pass `null` / `0`) to create a system task — requires super-user.

| Status | Code | Notes |
|--------|------|-------|
| 201 | — | Created; response contains the new task record. |
| 400 | `validation.bad_request` | Missing `type` or `cron_expression`. |
| 401 | `auth.invalid_token` | |
| 403 | `auth.forbidden` | Insufficient privileges (system tasks; or no admin on `site_id`). |
| 422 | `task.unknown_type` | The `type` is not registered with the task registry. |
| 422 | `task.invalid_cron` | The cron expression failed validation. |

Successful creates emit an audit event `task.create` with `details: {type, site_id}`.

## `POST /v1/task/{id}`

Modify an existing task. All body fields are optional; at least one MUST be present.
Supported fields: `type`, `cron_expression`, `enabled`, `params`.

| Status | Code | Notes |
|--------|------|-------|
| 200 | — | Updated record. |
| 400 | `validation.bad_request` | No updatable fields supplied. |
| 401 | `auth.invalid_token` | |
| 403 | `auth.forbidden` | Non-super lacking `panopticon.admin` on the site. |
| 404 | `task.not_found` | Unknown task id. |
| 422 | `task.unknown_type` / `task.invalid_cron` | Validation failure. |

Successful updates emit an audit event `task.update` with `details.fields` listing the
keys that were changed.

## Audit events

| Event | Triggered by |
|-------|--------------|
| `task.create` | Successful `PUT /v1/task`. |
| `task.update` | Successful `POST /v1/task/{id}`. |

## Tests

Happy-path tests that would actually execute the task (`task:run` integration) are
skipped; only the CRUD lifecycle and ACL / validation paths are covered.

## See also

- [overview.md](./overview.md) — cross-cutting concerns (auth, envelope, error codes).
- [`../openapi.yaml`](../openapi.yaml) — machine-readable OpenAPI 3.1 specification.
