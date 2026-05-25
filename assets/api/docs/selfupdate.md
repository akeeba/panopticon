# Panopticon API — Self-update

> Part of the Panopticon JSON API. Back to [overview.md](./overview.md).

The Self-update endpoints expose Panopticon's own integrated update mechanism — the same
flow that the **System ▸ Self-update** page drives — over the JSON API. They are the
counterpart of `src/Controller/Selfupdate.php`.

> **All four endpoints require an authenticated super-user.** Non-super tokens receive
> `403 auth.forbidden`. This is enforced on every handler with no exceptions.

The endpoints map to the four steps of the legacy upgrade flow:

1. `GET /v1/selfupdate` — query state. Returns the installed version and the latest
   available version. No side effects (the update channel is hit only when the on-disk
   cache is stale, or `?force=1` is passed).
2. `GET /v1/selfupdate/download` — download the update package into the local staging
   area (`<APATH_TMP>/update.zip`).
3. `GET /v1/selfupdate/install` — extract the staged package over `APATH_ROOT`,
   invalidate OPcache for the replaced PHP files, and clear precompiled Blade templates.
4. `GET /v1/selfupdate/postinstall` — run the database schema update and post-update
   bookkeeping (default tasks check, obsolete-file cleanup, cache invalidation).

The four endpoints are intended to be called **in that order**. `install` returns
`409 selfupdate.not_downloaded` if step 2 has not run.

The legacy controller refuses to operate under Docker (per
`PANOPTICON_SELFUPDATE_ERR_UNDERDOCKER`). The API mirrors that behaviour transitively
because `Model\Selfupdate::download()` and `extract()` will fail when invoked in a
read-only application root, but the API does not pre-emptively refuse the call.

## `GET /v1/selfupdate`

Returns the current self-update status.

```http
GET /api/v1/selfupdate HTTP/1.1
Authorization: Bearer ...
```

Query parameters:

| Param   | Type | Notes                                                             |
|---------|------|-------------------------------------------------------------------|
| `force` | int  | Set to `1` to bust the update cache before reading (default `0`). |

Response:

```json
{
  "success": true,
  "data": {
    "installed_version":  "1.3.5",
    "latest_version":     "1.4.0",
    "has_update":         true,
    "release_date":       "2026-05-01T10:00:00Z",
    "release_notes_url":  null,
    "release_notes":      "* Lots of bug fixes\n* PHP 8.1 minimum",
    "download_url":       "https://github.com/akeeba/panopticon/releases/download/1.4.0/panopticon-1.4.0.zip",
    "loaded_update":      true,
    "stuck":              false,
    "error":              null
  }
}
```

| Status | Code                       | Notes                                                 |
|--------|----------------------------|-------------------------------------------------------|
| 200    | —                          | Success.                                              |
| 401    | `auth.invalid_token`       | Missing/invalid token.                                |
| 403    | `auth.forbidden`           | Not a super-user.                                     |
| 500    | `selfupdate.info_failed`   | Unhandled error while fetching update info.           |

## `GET /v1/selfupdate/download`

Downloads the latest release archive into the local staging area. The response carries
`path` (the **basename** of the staged file — server paths are not leaked), `size`, and
`sha256` so a caller can verify the package.

```http
GET /api/v1/selfupdate/download HTTP/1.1
Authorization: Bearer ...
```

```json
{
  "success": true,
  "data": {
    "path":   "update.zip",
    "size":   8123456,
    "sha256": "f2c1…"
  },
  "message": "Update package downloaded successfully."
}
```

| Status | Code                              | Notes                                                            |
|--------|-----------------------------------|------------------------------------------------------------------|
| 200    | —                                 | Package staged.                                                  |
| 401    | `auth.invalid_token`              |                                                                  |
| 403    | `auth.forbidden`                  | Not a super-user.                                                |
| 409    | `selfupdate.no_update_available`  | `hasUpdate()` returned false. Call `/v1/selfupdate` first.       |
| 502    | `selfupdate.download_failed`      | Network error or update channel unreachable.                     |

Audit: `selfupdate.download`, `details: {from_version, to_version, size}`.

## `GET /v1/selfupdate/install`

Installs the previously-downloaded package: extracts the ZIP over the application root,
invalidates OPcache, and clears compiled Blade templates. Implemented by
`Model\Selfupdate::performInstall()`, which is also used by the legacy controller.

```http
GET /api/v1/selfupdate/install HTTP/1.1
Authorization: Bearer ...
```

```json
{ "success": true, "data": null, "message": "Update package installed successfully." }
```

| Status | Code                          | Notes                                                            |
|--------|-------------------------------|------------------------------------------------------------------|
| 200    | —                             | Package extracted and OPcache invalidated.                       |
| 401    | `auth.invalid_token`          |                                                                  |
| 403    | `auth.forbidden`              | Not a super-user.                                                |
| 409    | `selfupdate.not_downloaded`   | No staged package; call `/v1/selfupdate/download` first.         |
| 500    | `selfupdate.install_failed`   | Extraction failed (corrupt ZIP, no write permission, …).         |

Audit: `selfupdate.install`, `details: {from_version, to_version}`.

> **The server replaces its own running PHP files.** The very next HTTP request runs on
> the new code. Some PHP-FPM setups need an explicit pool reload before the swap is
> visible; that's an operational concern outside the API's control.

## `GET /v1/selfupdate/postinstall`

Runs the post-install bookkeeping: database schema update, default task check, obsolete
file/folder cleanup, and cache pool invalidation. Safe to call repeatedly.

```http
GET /api/v1/selfupdate/postinstall HTTP/1.1
Authorization: Bearer ...
```

```json
{ "success": true, "data": null, "message": "Post-installation completed successfully." }
```

| Status | Code                              | Notes                                                       |
|--------|-----------------------------------|-------------------------------------------------------------|
| 200    | —                                 | Post-install ran cleanly.                                   |
| 401    | `auth.invalid_token`              |                                                             |
| 403    | `auth.forbidden`                  | Not a super-user.                                           |
| 500    | `selfupdate.postinstall_failed`   | Schema migration or cleanup failed; see message.            |

Audit: `selfupdate.postinstall`, `details: {from_version}`.

## Audit events

| Event                     | Triggered by                                |
|---------------------------|---------------------------------------------|
| `selfupdate.info`         | `GET /v1/selfupdate`                        |
| `selfupdate.download`     | `GET /v1/selfupdate/download` (on success). |
| `selfupdate.install`      | `GET /v1/selfupdate/install` (on success).  |
| `selfupdate.postinstall`  | `GET /v1/selfupdate/postinstall`.           |

`details` always includes `from_version` (the running version at the time of the call)
and, where known, `to_version` (the target version from the update channel).

## Tests

The integration suite covers the **forbidden-for-non-super** case for every endpoint
(the master-plan-mandated test) plus the **401-without-token** case. The happy paths for
`download` / `install` / `postinstall` are **not** integration-tested because they touch
the outbound network, the local filesystem under `APATH_ROOT`, and may replace running
PHP files. `Info` is also skipped at the happy-path level because it consults a
network-backed cache; the failure-path tests cover its auth wiring.

## See also

- [overview.md](./overview.md) — cross-cutting concerns (auth, envelope, error codes).
- [`../openapi.yaml`](../openapi.yaml) — machine-readable OpenAPI 3.1 specification.
