This is a feature release.

The major and only change in this version is the introduction of a JSON API at `/api/*`. You can use it to automate site management and Panopticon configuration without having to resort to the CLI application. This may be desirable in many use cases where using infrastructure-as-code tools (such as Ansible) is either not possible, or quite simply a complete overkill for the requirements.

The JSON API is documented in detail in the documentation wiki, as well as the `assets/api` directory in our repository. We ship an OpenAPI 3.1 specification file at `assets/api/openapi.yaml`. The specification can be imported into Postman, or used with tools such as `openapi-generator` to generate client libraries.

Having a JSON API opens up a world of possibilities. For example, right now, it is possible to create a Home Assistant `rest` sensor showing you the number of pending tasks in Panopticon's queue. Wire it to a Home Assistant automation controlling a cheap, smart LED strip, and you can have blinklights in your office showing you how your task queue is being processed. Practical? Probably not. Looks cool? You betcha!

The coolest uses for the JSON API are those we haven't even thought of yet. What are _you_ going to build?

## 🖥️ System Requirements

* PHP 8.3, 8.4, or 8.5. We recommend using PHP 8.4.
* MySQL 5.7 or later, or MariaDB 10.3 or later. We recommend using MariaDB 12.
* Ability to run CRON jobs, either command-line (recommended) or URLs with a frequency of once every minute, and an execution time of at least 30 seconds (up to 180 seconds are strongly preferred).

## 🔮 What's coming next?

Development of Akeeba Panopticon takes place _in public_. You can see what we're planning, thinking of, and working on in [our issues tracker](https://github.com/akeeba/panopticon/issues).

## ✨ Highlights

**JSON API (issue [#344](https://github.com/akeeba/panopticon/issues/344)).** Panopticon now ships a JSON API at `/api/*`, authenticated by API tokens you mint from the user menu under **API Tokens**. The API covers the same operations as the web UI for automation, with a stable response envelope and machine-readable error codes. Endpoint groups:

* **Sites** — list, create, read, modify, refresh, fix-Joomla-core-update, CMS update lifecycle (schedule / cancel / clear), extensions list / refresh / clear / reset, per-extension schedule/cancel updates, and download key get/set.
* **Sysconfig** — read and write application configuration parameters (super-user only; sensitive keys are hidden).
* **Tasks** — full CRUD over background tasks (list, read, create, modify).
* **Self-update** — query state, download, install, and post-install steps over the JSON API.

The full specification ships as OpenAPI 3.1 (`assets/api/openapi.yaml`); per-group reference docs live under `assets/api/docs/` and on the [GitHub wiki](https://github.com/akeeba/panopticon/wiki/API-Overview).

## 📋 CHANGELOG

* ✨ Add JSON API [gh-344]

Legend:

* 🚨 Security update
* ‼️ Important change
* ✨ New feature
* ✂️ Removed feature
* ✏️ Miscellaneous change
* 🐞 Bug fix
