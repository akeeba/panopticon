This version is a maintenance release. We implemented some new features to make your lives easier.

**Optional environment variables-only configuration of containerized Panopticon** [gh-696]. You can now configure a containerized Panopticon installation (e.g. one running in Docker) using nothing but environment variables.

**Clear the cache when relinking a site to Akeeba Backup**. Not strictly necessary, but it should alleviate the need to click on the refresh button after relinking to Akeeba Backup before you see an up-to-date list of backup records for that site.

**Do not log CMS Update Found more than once per version**. The site actions report would log the CMS update found every time Panopticon checked for an update. This was rather obnoxious and would effectively make useful information hard to find among the endless spam of that message if updates to a site were not installed right away.

## 🖥️ System Requirements

* PHP 8.1, 8.2, or 8.3. PHP 8.3 recommended.
* MySQL 5.7 or later, or MariaDB 10.3 or later. MySQL 8.0 recommended.
* Ability to run CRON jobs, either command-line (recommended) or URLs with a frequency of once every minute, and an execution time of at least 30 seconds (up to 180 seconds is strongly preferred).
* Obviously, the server it runs on must be connected to the Internet, so it can communicate with your sites.

## 🔮 What's coming next?

Development of Akeeba Panopticon takes place _in public_. You can see what we're planning, thinking of, and working on in [our issues tracker](https://github.com/akeeba/panopticon/issues).

## 📋 CHANGELOG

* ✨ Optional environment variables-only configuration of containerized Panopticon [gh-696]
* ✨ Clear the cache when relinking a site to Akeeba Backup
* ✏️ Do not log CMS Update Found more than once per version
* 🐞 Repeated emails for WordPress plugin updates
* 🐞 Wrong lang string used in WordPress plugin/theme update emails
* 🐞 PHP warnings running Connection Doctor on WordPress sites
* 🐞 Wrong "email" label on Backup options [gh-771]

Legend:

* 🚨 Security update
* ‼️ Important change
* ✨ New feature
* ✂️ Removed feature
* ✏️ Miscellaneous change
* 🐞 Bug fix
