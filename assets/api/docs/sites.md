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

> This file documents the read-only Sites surface. Write endpoints arrive with the Phase 2
> `phase2-sites-write` sub-plan; do not assume the absence of an endpoint here means it does not
> exist on the wire.
