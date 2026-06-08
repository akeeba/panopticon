This is a feature and security release.

## 🛑 Security Notice

**Please update immediately if you have users with no MFA method configured.** This version fixes a critical authentication bypass that allowed login without a second factor for those accounts.

## 🖥️ System Requirements

* PHP 8.3, 8.4, or 8.5. We recommend using PHP 8.4.
* MySQL 5.7 or later, or MariaDB 10.3 or later. We recommend using MariaDB 12.
* Ability to run CRON jobs, either command-line (recommended) or URLs with a frequency of once every minute, and an execution time of at least 30 seconds (up to 180 seconds are strongly preferred).

## 🔮 What's coming next?

Development of Akeeba Panopticon takes place _in public_. You can see what we're planning, thinking of, and working on in [our issues tracker](https://github.com/akeeba/panopticon/issues).

## ✨ Highlights

**🚨 MFA bypass for password-only users (CRITICAL).** Users who had no MFA method configured could be authenticated without completing MFA. This has been fixed. All installations with MFA enabled should update immediately.

**🚨 Stored XSS vulnerabilities (HIGH).** Several Blade templates contained stored cross-site scripting vulnerabilities. These have been resolved. Updating is strongly recommended for all publicly accessible installations. If your installation is only accessed or be written to by trusted users (the majority of installations) there's no cause for alarm.

**JSON API (issue [#344](https://github.com/akeeba/panopticon/issues/344)).** Panopticon now ships a JSON API at `/api/*`, authenticated by API tokens you mint from the user menu under **API Tokens**. The API covers the same operations as the web UI for automation, with a stable response envelope and machine-readable error codes. Endpoint groups:

* **Sites** — list, create, read, modify, refresh, fix-Joomla-core-update, CMS update lifecycle (schedule / cancel / clear), extensions list / refresh / clear / reset, per-extension schedule/cancel updates, and download key get/set.
* **Sysconfig** — read and write application configuration parameters (super-user only; sensitive keys are hidden).
* **Tasks** — full CRUD over background tasks (list, read, create, modify).
* **Self-update** — query state, download, install, and post-install steps over the JSON API.

The full specification ships as OpenAPI 3.1 (`assets/api/openapi.yaml`); per-group reference docs live under `assets/api/docs/` and on the [GitHub wiki](https://github.com/akeeba/panopticon/wiki/API-Overview).

Having a JSON API opens up a world of possibilities. For example, right now, it is possible to create a Home Assistant `rest` sensor showing you the number of pending tasks in Panopticon's queue. Wire it to a Home Assistant automation controlling a cheap, smart LED strip, and you can have blinklights in your office showing you how your task queue is being processed. Practical? Probably not. Looks cool? You betcha!

The coolest uses for the JSON API are those we haven't even thought of yet. What are _you_ going to build?

**API token scopes (issue [#967](https://github.com/akeeba/panopticon/issues/967)).** API tokens now carry fine-grained permission scopes. You can mint read-only tokens, tokens scoped to specific endpoint groups, or fully-privileged tokens — giving you least-privilege access control for every integration.

**Configurable API token limits (issue [#965](https://github.com/akeeba/panopticon/issues/965)).** Administrators can cap how many API tokens each user may hold, with optional per-group overrides. This helps enforce your organisation's security policies at scale.

**Task log attachments in failure emails.** When a CMS update, extension/plugin update, core file integrity check, or PHP File Change Scanner task fails, the notification email now includes the relevant task log as an attachment. No more hunting through the Panopticon interface to find out why something went wrong — the evidence lands directly in your inbox. Attachment delivery is configurable per group.

**Passkey / WebAuthn library upgraded to v5.3.** The passkey and WebAuthn library has been upgraded and associated regressions in passkey login and MFA introduced by that upgrade have been fixed.

**PHP 9 compatibility.** Retired `#[ReturnTypeWillChange]` attributes have been removed and proper return types added throughout, ensuring clean operation on PHP 9 when it becomes available ([gh-985](https://github.com/akeeba/panopticon/issues/985)).
