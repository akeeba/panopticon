# Panopticon API — Stats & Site Status

Lightweight read endpoints for dashboard integrations such as Home Assistant sensors, Raspberry Pi
LED/e-ink status boards, and similar monitoring clients.

All requests must carry a valid API token; see [overview.md](./overview.md) for transports.

---

## `GET /api/v1/stats`

Return aggregated dashboard counters for the entire Panopticon instance. All values come from
aggregate SQL queries — no PHP loop over individual sites is executed.

### Authentication & ACL

Requires the `sites:read` scope **and** `panopticon.super` — counters are global and cannot be
meaningfully scoped to a subset of sites.

### Success — `200 OK`

```json
{
  "success": true,
  "data": {
    "sites": {
      "total":               42,
      "enabled":             40,
      "with_cms_update":     3,
      "with_ext_updates":    7,
      "backup_ok":           38,
      "backup_problem":      2,
      "core_checksums_ok":   39,
      "core_checksums_fail": 1,
      "file_scanner_ok":     38,
      "file_scanner_fail":   2
    },
    "tasks": {
      "total":   120,
      "pending": 14,
      "running": 2,
      "failed":  3
    }
  }
}
```

#### `sites` fields

| Field                | Meaning |
|----------------------|---------|
| `total`              | Total site records (enabled + disabled). |
| `enabled`            | Sites with `enabled = 1`. |
| `with_cms_update`    | Enabled sites where `core.canUpgrade = true`. |
| `with_ext_updates`   | Enabled sites where `extensions.hasUpdates = 1`. |
| `backup_ok`          | Enabled sites with Akeeba Backup Pro connected and latest backup meta in `ok`/`complete`/`remote`. |
| `backup_problem`     | Enabled sites with Akeeba Backup Pro connected but no backup or a bad/missing latest backup. |
| `core_checksums_ok`  | Enabled sites where core-file checksums have been run and all passed (`lastStatus = true`). |
| `core_checksums_fail`| Enabled sites where core-file checksums ran but found modified files (`lastStatus = false`). |
| `file_scanner_ok`    | Enabled sites with an Admin Tools file-scanner task whose most recent completed run exited OK. |
| `file_scanner_fail`  | Enabled sites with an Admin Tools file-scanner task whose most recent completed run failed. |

> **Note:** `file_scanner_ok` and `file_scanner_fail` are derived from the `#__tasks` table
> (task type `filescanner`). Sites without any file-scanner task are not counted in either
> bucket. The aggregate does **not** check the suspicious-file count — use
> `GET /api/v1/site/:id/status` for that level of detail.

#### `tasks` fields

| Field     | Meaning |
|-----------|---------|
| `total`   | All tasks (enabled + disabled). |
| `pending` | Enabled tasks whose `next_execution` is in the past and are not currently running. |
| `running` | Tasks currently executing (`RUNNING` or `WILL_RESUME` exit codes). |
| `failed`  | Tasks whose last exit code indicates a failure (anything other than OK, running, or initial). |

### Errors

| Status | `code`               | When                                    |
|--------|----------------------|-----------------------------------------|
| 401    | `auth.invalid_token` | Missing or invalid token.               |
| 403    | `auth.forbidden`     | Token valid but user is not super-user. |
| 403    | `auth.scope_forbidden` | Token lacks the `sites:read` scope.   |

---

## `GET /api/v1/site/:id/status`

Return a structured health summary for a single site. Each monitored area exposes a `status`
field using the four-value enum `ok` / `warning` / `error` / `unknown` and a `detail`
sub-object with raw values.

### Path parameters

| Name | Type | Description     |
|------|------|-----------------|
| `id` | int  | The site's numeric ID. |

### Authentication & ACL

Requires the `sites:read` scope and either `panopticon.super` or `panopticon.read` on the
addressed site (same gate as `GET /api/v1/site/:id`).

### Success — `200 OK`

```json
{
  "success": true,
  "data": {
    "id":      42,
    "name":    "example.com",
    "enabled": true,
    "areas": {
      "cms_update": {
        "status": "ok",
        "detail": {
          "current_version":  "5.2.1",
          "latest_version":   "5.2.1",
          "can_upgrade":      false,
          "eol":              false,
          "eol_branch":       false,
          "security_only":    false,
          "extension_available": true,
          "update_site_ok":   true
        }
      },
      "template_overrides": {
        "status": "warning",
        "detail": { "changed_count": 3 }
      },
      "php": {
        "status": "ok",
        "detail": {
          "version":          "8.3.6",
          "eol":              false,
          "security_only":    false,
          "latest_in_branch": "8.3.8",
          "is_latest_patch":  false
        }
      },
      "server": {
        "status": "ok",
        "detail": {
          "ram_used_pct":       45.12,
          "site_disk_free_pct": 63.40,
          "db_disk_free_pct":   null,
          "cpu_iowait_pct":     0.25
        }
      },
      "extensions": {
        "status": "warning",
        "detail": {
          "updates_count":       4,
          "missing_keys_count":  0,
          "missing_sites_count": 0
        }
      },
      "backup": {
        "status": "ok",
        "detail": {
          "meta":        "ok",
          "too_old":     false,
          "max_age":     168,
          "backupstart": "2024-05-20 04:00:00"
        }
      },
      "file_scanner": {
        "status": "ok",
        "detail": {
          "scan_status": "complete",
          "suspicious":  0,
          "modified":    2,
          "total_files": 18342,
          "scan_date":   "2024-05-21 03:00:00"
        }
      },
      "core_checksums": {
        "status": "ok",
        "detail": {
          "last_check":     1716256800,
          "last_status":    true,
          "modified_count": 0
        }
      }
    }
  }
}
```

### Area status rules

#### `cms_update`

| Status    | Condition |
|-----------|-----------|
| `ok`      | Version current, not EOL. |
| `warning` | Update available and site can upgrade (`core.canUpgrade = true`). |
| `error`   | EOL branch or major, or extension/update-site missing. |
| `unknown` | No CMS version data collected yet. |

**WordPress note:** `eol_branch`, `security_only`, `extension_available`, `update_site_ok` fields
are Joomla-only; they are absent from the `detail` for WordPress sites.

#### `template_overrides`

| Status    | Condition |
|-----------|-----------|
| `ok`      | No changed template overrides (`changed_count = 0`). |
| `warning` | One or more changed overrides (`changed_count > 0`). |
| `unknown` | WordPress site (not applicable) or no data. |

#### `php`

| Status    | Condition |
|-----------|-----------|
| `ok`      | Not EOL, not security-only, running the latest patch within the branch. |
| `warning` | Security-only support phase, or a patch update is available within the branch. |
| `error`   | PHP version is EOL. |
| `unknown` | No PHP version data collected, or version unrecognised. |

#### `server`

| Status    | Condition |
|-----------|-----------|
| `ok`      | All metrics within thresholds. |
| `warning` | RAM ≥ 70 % OR disk free ≤ 10 % OR CPU I/O-wait ≥ 5 %. |
| `error`   | RAM ≥ 85 %. |
| `unknown` | Server-info data not collected (connector does not report it). |

#### `extensions`

| Status    | Condition |
|-----------|-----------|
| `ok`      | No pending updates, no missing download keys, no missing update sites. |
| `warning` | Updates available but no blocking issues. |
| `error`   | Missing download keys (`missing_keys_count > 0`) or missing update sites (`missing_sites_count > 0`). |
| `unknown` | No extension data collected yet. |

#### `backup`

| Status    | Condition |
|-----------|-----------|
| `ok`      | Latest backup meta is `ok`/`complete`/`remote` and is not too old (within `max_age` hours). |
| `error`   | No backup record, bad meta (`fail`, `obsolete`, `pending`), or backup is older than `max_age`. |
| `unknown` | Akeeba Backup Professional not linked to this site. |

The `max_age` threshold comes from `config.backup.max_age` (default 168 hours / 7 days).

#### `file_scanner`

| Status    | Condition |
|-----------|-----------|
| `ok`      | Latest scan completed successfully and found 0 suspicious files. |
| `error`   | Latest scan failed or found suspicious files (`suspicious > 0`). |
| `unknown` | Admin Tools Professional not installed, API error, or no scans on record. |

#### `core_checksums`

| Status    | Condition |
|-----------|-----------|
| `ok`      | Last check ran and all checksums matched. |
| `warning` | Last check found modified core files. |
| `unknown` | Not a Joomla site (WordPress not applicable), or the check has never run. |

### Errors

| Status | `code`                 | When                                              |
|--------|------------------------|---------------------------------------------------|
| 401    | `auth.invalid_token`   | Missing or invalid token.                         |
| 403    | `auth.forbidden`       | Token valid but user lacks read access to site.   |
| 403    | `auth.scope_forbidden` | Token lacks the `sites:read` scope.               |
| 404    | `site.not_found`       | No site with the given `id`.                      |

---

## Home Assistant integration example

The two endpoints above are designed to work out-of-the-box as Home Assistant `rest:` sensors.
Add the following blocks to your `configuration.yaml` (adjust `BASE_URL` and `TOKEN`):

```yaml
# configuration.yaml

rest:
  # ── Global Panopticon counters ─────────────────────────────────────────────
  - resource: "https://panopticon.example.com/api/v1/stats"
    method: GET
    headers:
      Authorization: "Bearer YOUR_API_TOKEN_HERE"
    scan_interval: 300   # 5 minutes
    sensor:
      - name: "Panopticon Sites Total"
        unique_id: panopticon_sites_total
        value_template: "{{ value_json.data.sites.total }}"
        state_class: measurement
        icon: mdi:web

      - name: "Panopticon Sites Enabled"
        unique_id: panopticon_sites_enabled
        value_template: "{{ value_json.data.sites.enabled }}"
        state_class: measurement
        icon: mdi:web-check

      - name: "Panopticon Sites With CMS Update"
        unique_id: panopticon_sites_cms_update
        value_template: "{{ value_json.data.sites.with_cms_update }}"
        state_class: measurement
        icon: mdi:update

      - name: "Panopticon Sites With Extension Updates"
        unique_id: panopticon_sites_ext_updates
        value_template: "{{ value_json.data.sites.with_ext_updates }}"
        state_class: measurement
        icon: mdi:puzzle

      - name: "Panopticon Backup OK"
        unique_id: panopticon_backup_ok
        value_template: "{{ value_json.data.sites.backup_ok }}"
        state_class: measurement
        icon: mdi:backup-restore

      - name: "Panopticon Backup Problem"
        unique_id: panopticon_backup_problem
        value_template: "{{ value_json.data.sites.backup_problem }}"
        state_class: measurement
        icon: mdi:backup-restore

      - name: "Panopticon File Scanner OK"
        unique_id: panopticon_scanner_ok
        value_template: "{{ value_json.data.sites.file_scanner_ok }}"
        state_class: measurement
        icon: mdi:shield-check

      - name: "Panopticon File Scanner Fail"
        unique_id: panopticon_scanner_fail
        value_template: "{{ value_json.data.sites.file_scanner_fail }}"
        state_class: measurement
        icon: mdi:shield-alert

      - name: "Panopticon Tasks Running"
        unique_id: panopticon_tasks_running
        value_template: "{{ value_json.data.tasks.running }}"
        state_class: measurement
        icon: mdi:run

      - name: "Panopticon Tasks Failed"
        unique_id: panopticon_tasks_failed
        value_template: "{{ value_json.data.tasks.failed }}"
        state_class: measurement
        icon: mdi:alert-circle

  # ── Per-site status (repeat this block for each site you want to monitor) ──
  - resource: "https://panopticon.example.com/api/v1/site/1/status"
    method: GET
    headers:
      Authorization: "Bearer YOUR_API_TOKEN_HERE"
    scan_interval: 300
    sensor:
      - name: "My Site CMS Status"
        unique_id: panopticon_site1_cms
        value_template: "{{ value_json.data.areas.cms_update.status }}"
        icon: >-
          {% set s = value_json.data.areas.cms_update.status %}
          {% if s == 'ok' %}mdi:check-circle
          {% elif s == 'warning' %}mdi:alert-circle-outline
          {% elif s == 'error' %}mdi:alert-circle
          {% else %}mdi:help-circle{% endif %}

      - name: "My Site Backup Status"
        unique_id: panopticon_site1_backup
        value_template: "{{ value_json.data.areas.backup.status }}"

      - name: "My Site PHP Status"
        unique_id: panopticon_site1_php
        value_template: "{{ value_json.data.areas.php.status }}"

      - name: "My Site Extensions Status"
        unique_id: panopticon_site1_extensions
        value_template: "{{ value_json.data.areas.extensions.status }}"

binary_sensor:
  - platform: template
    sensors:
      panopticon_any_backup_problem:
        friendly_name: "Panopticon — Any Backup Problem"
        value_template: "{{ states('sensor.panopticon_backup_problem') | int(0) > 0 }}"
        device_class: problem
        icon_template: >-
          {% if states('sensor.panopticon_backup_problem') | int(0) > 0 %}
            mdi:backup-restore
          {% else %}
            mdi:backup-restore
          {% endif %}

      panopticon_any_scanner_fail:
        friendly_name: "Panopticon — File Scanner Failure"
        value_template: "{{ states('sensor.panopticon_scanner_fail') | int(0) > 0 }}"
        device_class: problem

      panopticon_site1_has_cms_update:
        friendly_name: "My Site — CMS Update Available"
        value_template: >-
          {{ states('sensor.my_site_cms_status') in ['warning', 'error'] }}
        device_class: update
```

### Automation example — notify on backup failure

```yaml
automation:
  - alias: "Panopticon — Backup problem alert"
    trigger:
      - platform: numeric_state
        entity_id: sensor.panopticon_backup_problem
        above: 0
    condition: []
    action:
      - service: notify.mobile_app_my_phone
        data:
          title: "🔴 Panopticon Backup Alert"
          message: >-
            {{ states('sensor.panopticon_backup_problem') }} site(s) have a backup problem.
            Check Panopticon for details.
```

### Tips

- **Token scope:** Create a dedicated API token with the `sites:read` scope. The `GET /api/v1/stats`
  endpoint additionally requires the token's user to have `panopticon.super` privilege.
- **Polling interval:** `scan_interval: 300` (5 minutes) is a good starting point. Panopticon
  refreshes site data on its own schedule; polling more frequently than every 2 minutes is unlikely
  to reveal new information.
- **Multiple sites:** Duplicate the `GET /api/v1/site/:id/status` resource block for each site you
  want individual sensors for. Keep the `unique_id` values unique across blocks.
- **LED/e-ink displays:** Use the `sites:read`-scoped token, poll `/api/v1/stats` from a script on
  the Pi, and drive LEDs or an e-ink panel from the returned counts. The counters are intentionally
  scalar so they can drive GPIO pins without JSON parsing in a shell script.
