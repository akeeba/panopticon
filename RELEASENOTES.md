This is a minor feature release.

This release introduces a self-served read+write JSON API at `/api/*`, alongside time-of-day scheduling for extension, plugin, and theme updates — mirroring the existing CMS Update option — and filtering capabilities on the Install Extension page.

## 🖥️ System Requirements

* PHP 8.3, 8.4, or 8.5. We recommend using PHP 8.4.
* MySQL 5.7 or later, or MariaDB 10.3 or later. We recommend using MariaDB 12.
* Ability to run CRON jobs, either command-line (recommended) or URLs with a frequency of once every minute, and an execution time of at least 30 seconds (up to 180 seconds are strongly preferred).

## 🔮 What's coming next?

Development of Akeeba Panopticon takes place _in public_. You can see what we're planning, thinking of, and working on in [our issues tracker](https://github.com/akeeba/panopticon/issues).

## ✨ Highlights

**JSON API (issue [#344](https://github.com/akeeba/panopticon/issues/344)).** Panopticon now ships a self-served read+write JSON API at `/api/*`, authenticated by API tokens you mint from the user menu under **API Tokens**. The API covers the same operations as the web UI for automation, with a stable response envelope and machine-readable error codes. Endpoint groups:

* **Sites** — list, create, read, modify, refresh, fix-Joomla-core-update, CMS update lifecycle (schedule / cancel / clear), extensions list / refresh / clear / reset, per-extension schedule/cancel updates, and download key get/set.
* **Sysconfig** — read and write application configuration parameters (super-user only; sensitive keys are hidden).
* **Tasks** — full CRUD over background tasks (list, read, create, modify).
* **Self-update** — query state, download, install, and post-install steps over the JSON API.

The full specification ships as OpenAPI 3.1 (`assets/api/openapi.yaml`); per-group reference docs live under `assets/api/docs/` and on the [GitHub wiki](https://github.com/akeeba/panopticon/wiki/API-Overview).

**Time-of-Day Extension Updates.** Automatic extension, plugin, and theme updates can now be deferred to a specific time of day, just like CMS updates. Choose *Immediately* (the default, matching previous behaviour) or *Time of Day* in each site's Extensions Update / Plugins and Themes settings, and updates will be queued to run at (or after) your chosen time — in the application timezone. Manually-triggered updates from the UI always run immediately, regardless of this setting.

**Extension Install Filters.** The Install Extension page now supports filtering the list of target sites by CMS version, PHP version, extension name, author, author URL, and update status. Makes it much easier to target mass installs at a specific subset of sites.

## 📋 CHANGELOG

* ✨ Self-served read+write JSON API at `/api/*` authenticated by API tokens; token management UI under user menu → API Tokens [gh-344]
* ✨ Schedule automatic extension, plugin, and theme updates for a specific time of day, mirroring the CMS Update option.
* ✨ Filter sites by CMS version, PHP version, extension name, author, author URL, and update status in the Install Extension page [gh-962]

Legend:

* 🚨 Security update
* ‼️ Important change
* ✨ New feature
* ✂️ Removed feature
* ✏️ Miscellaneous change
* 🐞 Bug fix
