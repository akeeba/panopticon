## 🔎 Release highlights

This is a features, and bug-fix release.

**✨ .env support (gh-34)**. You can now use .env files to configure Panopticon. This is an advanced feature for automated deployments. 

**✨ Anonymous usage statistics collection (gh-215)**. We periodically collect your Panopticon, PHP, and database server version. This information is collected completely anonymously and helps us understand which direction to go with future versions. You don't like it? No problem! You can opt-out, no questions asked.

**✨ Link to self-update page even without any updates found (gh-209)**. You can view the self-update page even if there are no updates found. Moreover, you can click a single button to clear the updates cache and force Panopticon to reload the update information from GitHub.

**✨ Periodic database backup (gh-223)**. Panopticon will take a daily backup of its critical database tables' contents. If something breaks, you can restore the backup using phpMyAdmin, Adminer, the MySQL command line, or any other database tool. You can also take manual database backups before makind big changes, just to be on the safe side.

## 🖥️ System Requirements

* PHP 8.1, 8.2, or 8.3. PHP 8.2 recommended.
* MySQL 5.7 or later, or MariaDB 10.3 or later. MySQL 8.0 recommended.
* Ability to run CRON jobs, either command-line (recommended) or URLs with a frequency of once every minute and an execution time of at least 30 seconds (up to 180 seconds is strongly preferred). 
* Obviously, the server it runs on must be connected to the Internet, so it can communicate with your sites.

## 📋 CHANGELOG

Changes in version 1.0.3

+ ✨ .env support (gh-34)
+ ✨ Anonymous usage statistics collection (gh-215)
+ ✨ Link to self-update page even without any updates found (gh-209)
+ ✨ Periodic database backup (gh-223)
# 🐞 🔺 Regression: can't access Setup
# 🐞 🔺 Old MariaDB versions don't support JSONPath (gh-201)
# 🐞 🔺 Very low self-update timeout (5 seconds) (gh-185)
# 🐞 🔺 Too tight permissions check
# 🐞 ➖ Users with only Add Own and Edit Own privileges cannot add sites (gh-203)
# 🐞 🔻 Some mail templates may be missing due to typo (gh-226)
# 🐞 🔻 SCSS files were excluded (gh-233)

Legend:
* 🚨 Security update
* ‼️ Important change
* ✨ New feature
* ✂️ Removed feature
* ✏️ Miscellaneous change
* 🐞 Bug fix (🔺 High priority, ➖ Medium priority, 🔻 Low priority)