## 🔎 Release highlights

This is a features, and bug-fix release.

**✨ Detect stuck extension updates and allow rescheduling, or cancelling** [gh-304]. Panopticon can now detect that extension updates are stuck – if the task has been idle longer than the configured CRON stuck time (default: 3 minutes) with extension updates still in the queue – and will notify you. You can choose to either reschedule the updates, or cancel them altogether. In case you have deleted the site's extensions update task manually, effectively having the same effect of extension updates being scheduled without being able to install them, you will be notified and given the same options.

## 🖥️ System Requirements

* PHP 8.1, 8.2, or 8.3. PHP 8.2 recommended.
* MySQL 5.7 or later, or MariaDB 10.3 or later. MySQL 8.0 recommended.
* Ability to run CRON jobs, either command-line (recommended) or URLs with a frequency of once every minute and an execution time of at least 30 seconds (up to 180 seconds is strongly preferred). 
* Obviously, the server it runs on must be connected to the Internet, so it can communicate with your sites.

## 📋 CHANGELOG

[//]: # (TODO)

Legend:
* 🚨 Security update
* ‼️ Important change
* ✨ New feature
* ✂️ Removed feature
* ✏️ Miscellaneous change
* 🐞 Bug fix (🔺 High priority, ➖ Medium priority, 🔻 Low priority)
