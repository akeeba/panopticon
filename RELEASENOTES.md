This is a minor feature release.

This release introduces time-of-day scheduling for extension, plugin, and theme updates — mirroring the existing CMS Update option — and adds filtering capabilities to the Install Extension page.

## 🖥️ System Requirements

* PHP 8.3, 8.4, or 8.5. We recommend using PHP 8.4.
* MySQL 5.7 or later, or MariaDB 10.3 or later. We recommend using MariaDB 12.
* Ability to run CRON jobs, either command-line (recommended) or URLs with a frequency of once every minute, and an execution time of at least 30 seconds (up to 180 seconds are strongly preferred).

## 🔮 What's coming next?

Development of Akeeba Panopticon takes place _in public_. You can see what we're planning, thinking of, and working on in [our issues tracker](https://github.com/akeeba/panopticon/issues).

## ✨ Highlights

**Time-of-Day Extension Updates.** Automatic extension, plugin, and theme updates can now be deferred to a specific time of day, just like CMS updates. Choose *Immediately* (the default, matching previous behaviour) or *Time of Day* in each site's Extensions Update / Plugins and Themes settings, and updates will be queued to run at (or after) your chosen time — in the application timezone. Manually-triggered updates from the UI always run immediately, regardless of this setting.

**Extension Install Filters.** The Install Extension page now supports filtering the list of target sites by CMS version, PHP version, extension name, author, author URL, and update status. Makes it much easier to target mass installs at a specific subset of sites.

## 📋 CHANGELOG

* ✨ Schedule automatic extension, plugin, and theme updates for a specific time of day, mirroring the CMS Update option.
* ✨ Filter sites by CMS version, PHP version, extension name, author, author URL, and update status in the Install Extension page [gh-962]

Legend:

* 🚨 Security update
* ‼️ Important change
* ✨ New feature
* ✂️ Removed feature
* ✏️ Miscellaneous change
* 🐞 Bug fix
