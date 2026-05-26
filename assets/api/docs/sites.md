# Panopticon API — Sites

Read endpoints for monitored sites. Write endpoints (`PUT /v1/site`, `POST /v1/site/:id`,
refresh, fix-update) are documented in this file as they ship in Phase 2.

All requests must carry a valid API token; see [overview.md](./overview.md) for transports.

## `GET /api/v1/sites`

List sites visible to the authenticated user, with filtering and pagination.

### Query parameters

| Name      | Type    | Default | Description                                                                       |
|-----------|---------|---------|-----------------------------------------------------------------------------------|
| `search`  | string  | —       | Substring match against the site name and URL.                                    |
| `enabled` | int     | —       | Filter by enabled flag (`0` or `1`).                                              |
| `cmsType` | string  | —       | Filter by CMS type. Must be a known `CMSType` enum value (e.g. `joomla`, `wordpress`). Unknown values return `400 validation.bad_request`. |
| `limit`   | int     | 50      | Page size. Negative values are clamped to zero (returns the empty page).          |
| `offset`  | int     | 0       | Zero-based offset.                                                                |

### Success — `200 OK`

```json
{
  "success": true,
  "data": [
    {
      "id":      1,
      "name":    "example.com",
      "url":     "https://example.com/",
      "enabled": true,
      "cmsType": "joomla"
    }
  ],
  "pagination": {
    "total":  1,
    "limit":  50,
    "offset": 0
  }
}
```

### Errors

| Status | `code`                   | When                                                  |
|--------|--------------------------|-------------------------------------------------------|
| 400    | `validation.bad_request` | `cmsType` is not a known enum value.                  |
| 401    | `auth.invalid_token`     | Missing or invalid token.                             |

ACL: super-users see all sites; non-super users see sites for which they hold
`panopticon.read` (filtering is applied by the underlying `Model\Site` query).

---

## `GET /api/v1/site/:id`

Return the full record for a single site, including its complete `config` Registry.

### Path parameters

| Name | Type | Description                  |
|------|------|------------------------------|
| `id` | int  | Site primary key (positive). |

### Success — `200 OK`

```json
{
  "success": true,
  "data": {
    "id":          1,
    "name":        "example.com",
    "url":         "https://example.com/administrator/index.php?option=com_panopticon",
    "baseUrl":     "https://example.com/",
    "enabled":     true,
    "cmsType":     "joomla",
    "created_on":  "2026-01-15T12:34:56+00:00",
    "created_by":  42,
    "modified_on": "2026-04-09T09:21:10+00:00",
    "modified_by": 42,
    "notes":       "Production site",
    "config": {
      "config": {
        "downloadkey":  "…",
        "core_update":  { … },
        "extensions":   { … },
        "username":     "…",
        "password":     "…"
      }
    }
  }
}
```

> **Important.** The `config` object is returned **verbatim**, with no redaction. Every secret
> stored against the site (download keys, basic-auth credentials, custom headers, …) is in the
> response. See [overview.md § Secrets and the `config` field](./overview.md#secrets-and-the-config-field).

### Errors

| Status | `code`               | When                                                                   |
|--------|----------------------|------------------------------------------------------------------------|
| 401    | `auth.invalid_token` | Missing or invalid token.                                              |
| 403    | `auth.forbidden`     | The user lacks `panopticon.read` on this site and is not a super-user. |
| 404    | `site.not_found`     | No site exists with that id.                                           |

---

## Write endpoints

The endpoints below are the JSON equivalents of the **Add Site**, **Edit Site**, **Refresh**
and **Fix Joomla core-update site** actions in the web UI. Request bodies use the same
flat shape: top-level fields (`name`, `url`, `enabled`, `notes`, `groups`) plus a nested
`config` object whose keys are dot-paths into the site `Registry` (e.g. `config.apiKey`,
`config.core_update.time.hour`). Send only the keys you want changed; everything else is
preserved.

The `config` object is **not** redacted on output — see
[overview.md § Secrets and the `config` field](./overview.md#secrets-and-the-config-field).

---

### `PUT /api/v1/site`

Create a new monitored site.

#### Request body

```json
{
  "name":    "example.com",
  "url":     "https://example.com/api",
  "enabled": true,
  "notes":   "Production site",
  "groups":  [1, 2],
  "config":  {
    "config": {
      "cmsType":     "joomla",
      "apiKey":      "..."
    }
  }
}
```

`name` and `url` are required. `enabled` defaults to `true`. `groups` is an array of
integer group ids that becomes `config.groups`.

#### Success — `201 Created`

```json
{
  "success": true,
  "data": {
    "id":      42,
    "name":    "example.com",
    "url":     "https://example.com/api",
    "enabled": true,
    "cmsType": "joomla",
    "notes":   "Production site",
    "config":  { "config": { "cmsType": "joomla", "apiKey": "..." } }
  },
  "message": "Site created successfully."
}
```

#### Errors

| Status | `code`                       | When                                                                     |
|--------|------------------------------|--------------------------------------------------------------------------|
| 400    | `validation.bad_request`     | Missing required fields (`name`/`url`) or malformed payload types.       |
| 400    | `request.invalid_json`       | Body is not valid JSON.                                                  |
| 401    | `auth.invalid_token`         | Missing or invalid token.                                                |
| 403    | `auth.forbidden`             | Caller lacks `panopticon.super`, `panopticon.admin` and `panopticon.addown`. |
| 422    | `validation.unprocessable`   | The model's `check()` rejected the payload (e.g. empty trimmed name).    |

ACL: matches the legacy UI `onBeforeAdd`/`canAddEditOrSave` — super, global admin, or
`panopticon.addown`.

Audit: writes `site.create` to `#__audit_log` with `target_type = "site"` and
`details = {name, url}`.

---

### `POST /api/v1/site/:id`

Modify an existing site. Only the keys present in the request body are updated. `config`
keys are merged into the existing `Registry` (per-key set, not full replace).

#### Request body

```json
{
  "name":    "renamed.example.com",
  "enabled": false,
  "config":  { "config.domain.warning": 180 }
}
```

#### Success — `200 OK`

Same shape as `PUT /v1/site`.

#### Errors

| Status | `code`                       | When                                                                |
|--------|------------------------------|---------------------------------------------------------------------|
| 400    | `validation.bad_request`     | Empty body or invalid field types.                                  |
| 400    | `request.invalid_json`       | Body is not valid JSON.                                             |
| 401    | `auth.invalid_token`         | Missing or invalid token.                                           |
| 403    | `auth.forbidden`             | Caller is neither super, nor `panopticon.admin`, nor owner with `editown`. |
| 404    | `site.not_found`             | No site exists with that id.                                        |
| 422    | `validation.unprocessable`   | The model's `check()` rejected the payload.                         |

ACL: super → always allowed; otherwise `panopticon.admin` on the site, or `panopticon.editown`
when the caller is the owner (`created_by`).

Audit: writes `site.update`.

---

### `POST /api/v1/site/:id/refresh`

Synchronously refresh the site's information (the same code path as **Refresh** in the UI).
This invokes the `refreshsiteinfo` task callback inline and may take several seconds.

#### Request body

Empty.

#### Success — `200 OK`

```json
{ "success": true, "data": null, "message": "Site information refreshed successfully." }
```

#### Errors

| Status | `code`                | When                                                          |
|--------|-----------------------|---------------------------------------------------------------|
| 401    | `auth.invalid_token`  | Missing or invalid token.                                     |
| 403    | `auth.forbidden`      | Caller lacks `panopticon.read` on the site and is not super.  |
| 404    | `site.not_found`      | No site exists with that id.                                  |
| 500    | (none)                | The refresh callback raised an exception.                     |

Audit: writes `site.refresh`.

---

### `POST /api/v1/site/:id/fixjoomlacoreupdate`

Clear the "stuck Joomla core update" flag on a Joomla site. Equivalent to the UI's
**Fix Joomla core update site** button: posts to the connector's
`/v1/panopticon/core/update` endpoint. Joomla sites only.

#### Request body

Empty.

#### Success — `200 OK`

```json
{ "success": true, "data": null, "message": "Joomla core update site fixed successfully." }
```

#### Errors

| Status | `code`                | When                                                          |
|--------|-----------------------|---------------------------------------------------------------|
| 401    | `auth.invalid_token`  | Missing or invalid token.                                     |
| 403    | `auth.forbidden`      | Caller lacks `panopticon.admin` on the site and is not super. |
| 404    | `site.not_found`      | No site exists with that id.                                  |
| 422    | `site.wrong_cms`      | The site is not a Joomla site.                                |
| 500    | (none)                | The connector call raised an exception.                       |

Audit: writes `site.fix_joomla_core_update`.

---

> This file documents the read-only Sites surface. Write endpoints arrive with the Phase 2
> `phase2-sites-write` sub-plan; do not assume the absence of an endpoint here means it does not
> exist on the wire.

---

## CMS update — Lifecycle

These three endpoints schedule, cancel, and clear the per-site CMS-update task (Joomla
`joomlaupdate` or WordPress `wordpressupdate`). They reuse the same shared helpers
(`Task\Trait\EnqueueJoomlaUpdateTrait`, `EnqueueWordPressUpdateTrait`) that the UI controller
uses, so behaviour is identical to clicking **Schedule update** / **Cancel** / **Clear failed
update** in the web UI.

### `POST /api/v1/site/:id/cmsupdate`

Schedule the CMS update task for the site.

#### Request body (optional)

```json
{ "force": false }
```

`force: true` schedules the update even if the site already reports the latest version.

#### Success — `202 Accepted`

```json
{ "success": true, "data": null, "message": "CMS update scheduled successfully." }
```

#### Errors

| Status | `code`                | When                                                                      |
|--------|-----------------------|---------------------------------------------------------------------------|
| 401    | `auth.invalid_token`  | Missing or invalid token.                                                 |
| 403    | `auth.forbidden`      | Caller lacks `panopticon.run` on the site and is not a super-user.        |
| 404    | `site.not_found`      | No site exists with that id.                                              |
| 422    | `site.wrong_cms`      | CMS type is not Joomla or WordPress.                                      |

ACL: `panopticon.run`. Audit: `site.cmsupdate.schedule`.

> The integration-test happy path is **not** executed because scheduling exercises the task
> queue and indirectly the connector; only the 401/403/404/422 branches are asserted.

---

### `POST /api/v1/site/:id/cmsupdate/cancel`

Cancel a scheduled CMS update that has not started running. Mirrors the legacy
`unscheduleJoomlaUpdate` / `unscheduleWordPressUpdate`.

#### Success — `200 OK`

```json
{ "success": true, "data": null, "message": "CMS update cancelled successfully." }
```

#### Errors

| Status | `code`                | When                                                                 |
|--------|-----------------------|----------------------------------------------------------------------|
| 401    | `auth.invalid_token`  | Missing or invalid token.                                            |
| 403    | `auth.forbidden`      | Caller lacks `panopticon.run` on the site.                           |
| 404    | `site.not_found`      | No site exists with that id.                                         |
| 404    | `task.not_scheduled`  | The site has no scheduled CMS update task.                           |
| 422    | `task.running`        | The task is currently RUNNING/WILL_RESUME and cannot be cancelled.   |
| 422    | `site.wrong_cms`      | CMS type is not Joomla or WordPress.                                 |

Audit: `site.cmsupdate.cancel`.

---

### `POST /api/v1/site/:id/cmsupdate/clear`

Delete a CMS update task (typically used to clear a failed task so a new one can be scheduled).

#### Success — `200 OK`

```json
{ "success": true, "data": null, "message": "CMS update error cleared successfully." }
```

#### Errors

| Status | `code`                | When                                                  |
|--------|-----------------------|-------------------------------------------------------|
| 401    | `auth.invalid_token`  | Missing or invalid token.                             |
| 403    | `auth.forbidden`      | Caller lacks `panopticon.run` on the site.            |
| 404    | `site.not_found`      | No site exists with that id.                          |
| 404    | `task.not_scheduled`  | No CMS update task exists for this site.              |
| 422    | `site.wrong_cms`      | CMS type is not Joomla or WordPress.                  |

Audit: `site.cmsupdate.clear`.

---

## Extensions

These endpoints manage the per-site extension/plugin update flow. The "list" endpoint returns
the site's `extensions.list` registry verbatim — no redaction (see overview.md § Secrets).

### `GET /api/v1/site/:id/extensions`

List all extensions (Joomla) or plugins (WordPress) reported by the site.

#### Success — `200 OK`

```json
{
  "success": true,
  "data": {
    "extensions": [
      {
        "id":          42,
        "name":        "com_example",
        "description": "Example Component",
        "type":        "component",
        "enabled":     true,
        "downloadkey": {
          "supported":   true,
          "valid":       true,
          "prefix":      "",
          "suffix":      "",
          "updatesites": [7],
          "value":       "ABC-123-XYZ"
        }
      }
    ],
    "quickInfo": { "site_id": 1, "key": "joomla", "hasUpdates": false }
  }
}
```

#### Errors

| Status | `code`               | When                                                         |
|--------|----------------------|--------------------------------------------------------------|
| 401    | `auth.invalid_token` | Missing or invalid token.                                    |
| 403    | `auth.forbidden`     | Caller lacks `panopticon.read` on the site.                  |
| 404    | `site.not_found`     | No site exists with that id.                                 |

ACL: `panopticon.read`. No audit entry (read-only).

---

### `POST /api/v1/site/:id/extensions`

Synchronously refresh installed-extensions information by calling the
`refreshinstalledextensions` task callback inline (same code path as the UI **Refresh
extensions** action). May take several seconds.

#### Success — `200 OK`

```json
{ "success": true, "data": null, "message": "Extensions information refreshed successfully." }
```

#### Errors

| Status | `code`               | When                                                |
|--------|----------------------|-----------------------------------------------------|
| 401    | `auth.invalid_token` | Missing or invalid token.                           |
| 403    | `auth.forbidden`     | Caller lacks `panopticon.read` on the site.        |
| 404    | `site.not_found`     | No site exists with that id.                        |
| 500    | (none)               | The refresh callback raised an exception.           |

ACL: `panopticon.read`. Audit: `site.extensions.refresh`.

> Happy-path integration test is **skipped** — running the refresh callback hits the connector.

---

### `POST /api/v1/site/:id/extensions/clear`

Clear (delete) a failed extensions-update task; reschedule it if items remain in the queue.

#### Success — `200 OK`

```json
{ "success": true, "data": null, "message": "Extensions update error cleared successfully." }
```

#### Errors

| Status | `code`                | When                                                 |
|--------|-----------------------|------------------------------------------------------|
| 401    | `auth.invalid_token`  | Missing or invalid token.                            |
| 403    | `auth.forbidden`      | Caller lacks `panopticon.run` on the site.           |
| 404    | `site.not_found`      | No site exists with that id.                         |
| 404    | `task.not_scheduled`  | No extensions update task exists for this site.      |
| 422    | `site.wrong_cms`      | CMS type is not Joomla or WordPress.                 |

Audit: `site.extensions.clear`.

---

### `POST /api/v1/site/:id/extensions/reset`

Reschedule the extensions update task. With `{"resetqueue": true}` in the body, the per-site
queue is also emptied first.

#### Request body (optional)

```json
{ "resetqueue": true }
```

#### Success — `200 OK`

```json
{ "success": true, "data": null, "message": "Extensions update reset successfully." }
```

#### Errors

| Status | `code`               | When                                                |
|--------|----------------------|-----------------------------------------------------|
| 401    | `auth.invalid_token` | Missing or invalid token.                           |
| 403    | `auth.forbidden`     | Caller lacks `panopticon.run` on the site.          |
| 404    | `site.not_found`     | No site exists with that id.                        |
| 422    | `site.wrong_cms`     | CMS type is not Joomla or WordPress.                |

Audit: `site.extensions.reset`.

---

### `POST /api/v1/site/:id/extensions/scheduleupdate/:extId`

Enqueue a single extension/plugin for update and trigger the extensions task to run immediately.

#### Success — `202 Accepted`

```json
{ "success": true, "data": null, "message": "Extension update scheduled successfully." }
```

#### Errors

| Status | `code`                    | When                                                          |
|--------|---------------------------|---------------------------------------------------------------|
| 400    | `validation.bad_request`  | `extId` is not a positive integer.                            |
| 401    | `auth.invalid_token`      | Missing or invalid token.                                     |
| 403    | `auth.forbidden`          | Caller lacks `panopticon.run` on the site.                    |
| 404    | `site.not_found`          | No site exists with that id.                                  |
| 404    | `extension.not_found`     | The extension is not present in `extensions.list`.            |
| 409    | `task.already_scheduled`  | The extension is already queued.                              |
| 422    | `site.wrong_cms`          | CMS type is not Joomla or WordPress.                          |

Audit: `site.extension.scheduleupdate`.

---

### `POST /api/v1/site/:id/extensions/cancel/:extId`

Remove a single extension from the update queue.

#### Errors

| Status | `code`               | When                                            |
|--------|----------------------|-------------------------------------------------|
| 401    | `auth.invalid_token` | Missing or invalid token.                       |
| 403    | `auth.forbidden`     | Caller lacks `panopticon.run` on the site.      |
| 404    | `site.not_found`     | No site exists with that id.                    |
| 404    | `task.not_scheduled` | The extension is not in the queue.              |
| 422    | `site.wrong_cms`     | CMS type is not Joomla or WordPress.            |

Audit: `site.extension.cancelupdate`.

---

### `GET /api/v1/site/:id/extension/:extId/downloadkey`

Return the download-key info for a Joomla extension. Joomla sites only.

#### Success — `200 OK`

```json
{
  "success": true,
  "data": {
    "extensionId": 42,
    "name":        "Example Component",
    "downloadkey": {
      "supported":   true,
      "valid":       true,
      "prefix":      "",
      "suffix":      "",
      "updatesites": [7],
      "value":       "ABC-123-XYZ"
    }
  }
}
```

#### Errors

| Status | `code`                | When                                                  |
|--------|-----------------------|-------------------------------------------------------|
| 401    | `auth.invalid_token`  | Missing or invalid token.                             |
| 403    | `auth.forbidden`      | Caller lacks `panopticon.admin` on the site.          |
| 404    | `site.not_found`      | No site exists with that id.                          |
| 404    | `extension.not_found` | The extension does not exist on this site.            |
| 422    | `site.wrong_cms`      | The site is not a Joomla site.                        |

ACL: `panopticon.admin`. Audit: `site.extension.downloadkey.get`.

---

### `POST /api/v1/site/:id/extension/:extId/downloadkey`

Save a new download-key value for a Joomla extension. Delegates to
`Model\Site::saveDownloadKey()` which pushes the value to the remote connector.

#### Request body

```json
{ "key": "ABC-123-XYZ" }
```

`key` may also be `null` to clear.

#### Errors

| Status | `code`                            | When                                                       |
|--------|-----------------------------------|------------------------------------------------------------|
| 400    | `validation.bad_request`          | `key` field missing or not a string/null.                  |
| 401    | `auth.invalid_token`              | Missing or invalid token.                                  |
| 403    | `auth.forbidden`                  | Caller lacks `panopticon.admin` on the site.               |
| 404    | `site.not_found`                  | No site exists with that id.                               |
| 404    | `extension.not_found`             | The extension does not exist on this site.                 |
| 422    | `site.wrong_cms`                  | The site is not a Joomla site.                             |
| 422    | `extension.invalid_download_key`  | The extension does not support download keys / remote NACK.|

Audit: `site.extension.downloadkey.set`.

> Happy-path integration test is **skipped** — saving a download key calls the remote connector
> over HTTP. Only the auth / validation / not-found branches are exercised.

## See also

- [overview.md](./overview.md) — cross-cutting concerns (auth, envelope, error codes).
- [`../openapi.yaml`](../openapi.yaml) — machine-readable OpenAPI 3.1 specification.
