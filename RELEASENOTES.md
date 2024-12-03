This version is a maintenance and bugfix release. 

**Better worded messages when you have Akeeba Backup Core instead of Professional**. The previous message would have you believe that Panopticon thought you didn't have Akeeba Backup installed at all. It now makes it clear that the Core edition was found, but cannot be used with Panopticon.

**Make accurate PHP CLI path detection optional**. The accurate PHP CLI detection may not work with some hosts (e.g. IONOS) who seem to block your IP address because of Panopticon trying to verify the PHP-CLI executable. Confusingly, the support of some of the affected hosts will tell you it's because of “too many requests” which has nothing to do with the problem, really. If you get that meaningless reply from your host's support, head to the the system configuration page and disable accurate PHP CLI detection.

## 🖥️ System Requirements

* PHP 8.1, 8.2, or 8.3. PHP 8.3 recommended. PHP 8.4 support is considered experimental.
* MySQL 5.7 or later, or MariaDB 10.3 or later. MySQL 8.0 recommended.
* Ability to run CRON jobs, either command-line (recommended) or URLs with a frequency of once every minute, and an execution time of at least 30 seconds (up to 180 seconds is strongly preferred).
* Obviously, the server it runs on must be connected to the Internet, so it can communicate with your sites.

## 🔮 What's coming next?

Development of Akeeba Panopticon takes place _in public_. You can see what we're planning, thinking of, and working on in [our issues tracker](https://github.com/akeeba/panopticon/issues).

## 📋 CHANGELOG

* ✨ Better worded messages when you have Akeeba Backup Core instead of Professional
* ✏️ Make accurate PHP CLI path detection optional
* 🐞 CRON jobs didn't work correctly in the Docker image [gh-856]
* 🐞 Cannot set up update preferences for Joomla! extensions whose name does nor conform to Joomla's standards (even though Joomla allows them to be installed, because it fails to enforce its own naming standards!)
* 🐞 Site Information doesn't show extension errors
* 🐞 PHP error trying to log in with a username that doesn't exist
* 🐞 Logs and Tasks views: warning if there's a log/task belonging to a deleted site
* 🐞 If an extension's short name starts with the character `a`, that letter is cut off

Legend:

* 🚨 Security update
* ‼️ Important change
* ✨ New feature
* ✂️ Removed feature
* ✏️ Miscellaneous change
* 🐞 Bug fix
