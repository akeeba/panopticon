This is mostly a bugfix version, but we also did manage to sneak in a new feature.

✨ **Send scheduled reports to specific groups** [gh-521] When you set up a scheduled email task, you can (optionally) select one or more user groups to send emails to. This allows you to fine-tune who receives the emails by creating and assigning user groups.

## 🖥️ System Requirements

* PHP 8.1, 8.2, or 8.3. PHP 8.2 recommended.
* MySQL 5.7 or later, or MariaDB 10.3 or later. MySQL 8.0 recommended.
* Ability to run CRON jobs, either command-line (recommended) or URLs with a frequency of once every minute and an
  execution time of at least 30 seconds (up to 180 seconds is strongly preferred).
* Obviously, the server it runs on must be connected to the Internet, so it can communicate with your sites.

## 🔮 What's coming next?

Development of Akeeba Panopticon takes place _in public_. You can see what we're planning, thinking of, and working on in [our issues tracker](https://github.com/akeeba/panopticon/issues).

Kindly remember that the order and timeframe for implementation largely depends on our available time, our assessment of expected complexity, and interdependencies between features. Security issues and bugs always take priority over new features; there's no point polishing a broken glass. Thank you for your understanding!

## 📋 CHANGELOG

* ✨ Send scheduled reports to specific groups [gh-521]
* ✨ Connection doctor: detect Akeeba Backup Core for Joomla! 3
* ✨ Improve the X-Mailer and Reply-To headers in sent emails
* ✨ Internal support for sending email only to selected user groups
* 🐞 🔺 Cannot launch installation due to a missing character
* 🐞 🔺 Tasks would be picked up by multiple task runners running in parallel (MySQL race condition)
* 🐞 ➖ No visible error message when the site information update fails [gh-523]
* 🐞 ➖ PHPmailer throws a simple RuntimeException in some cases, which was not being caught
* 🐞 ➖ Custom CLI commands in user_code where not autoloaded
* 🐞 ➖ Custom tasks in user_code where not autoloaded
* 🐞 🔻 Extraneous slash in mail messages' `[URL]` variable [gh-519]
* 🐞 🔻 Joomla update failures could result in the wrong error message displayed
* 🐞 🔻 Missing or small favicons can create layout issues [gh-522]
* 🐞 🔻 Connection to Akeeba Backup reset when saving site without changing connection information [gh-534]

Legend:

* 🚨 Security update
* ‼️ Important change
* ✨ New feature
* ✂️ Removed feature
* ✏️ Miscellaneous change
* 🐞 Bug fix (🔺 High priority, ➖ Medium priority, 🔻 Low priority)
