Happy New Year!

We are happy to present you with Panopticon 1.1.0, nicknamed “Dawn”. 

✨ **Dashboard layout for Sites Overview** [gh-395] The main page of Panopticon, the Sites Overview, now comes with two alternate styles. On one hand we have the classic tabular format you love, or love to hate. On the other hand we have the brand new, much more compact, Dashboard format which can also reload periodically – really useful when you're keeping tabs on dozens of sites being updated. This feature is currently in beta. Most importantly, it paves the way for auto-refreshing page areas in future releases, by introducing [Petite Vue](https://github.com/vuejs/petite-vue) to our workflow. Yes, there are plans to make parts of the Site Information page dynamic in the future.

✨ **Scheduled Site Action Report Emails** [gh-303] You can now have an excerpt of the Site Action Reports (normally available from Overview, Site Reports) for a specific site and time period emailed to you on a schedule. This is useful if you want to receive a daily, weekly, or monthly recap of all the automatic and manual actions which took place on your site through Panopticon.

✨ **Basic uptime monitoring** [gh-491] Panopticon ships with a (very basic) uptime monitoring system. It is only meant to notify you when a site goes down and comes back up _as seen from Panopticon's server_. It will not provide any kind of history, or public status page, or multiple locations, or service integrations, etc. It also needs extra CRON jobs on your server to handle the load, which is why it's _disabled by default_. The architecture does, however, offer plugin events so that integrations with third party services will be possible. 

✨ **Plugin system**. We have introduced a basic plugin system, allowing to write code which hooks into the plugin events offered by Panopticon. What use is running your own monitoring if you can't extend it, right? Please remember that since plugins are included by and make use of Panopticon's code they MUST be licensed under the GNU AGPLv3.

✨ **SSL/TLS certificate information display, and sending expiration warning emails** [gh-397] All of our sites are now using HTTPS – if not, they should. Most of them use automatically renewing, free of charge TLS (incorrectly called SSL; SSL has been dead for nearly 30 years) certificates issued by Let's Encrypt, CloudFlare, etc. Some sites don't have their certificates auto-renew. In others, the automation breaks. When a certificate expires without being replaced the site starts throwing errors for its visitors. Don't panic! Panopticon will now show you the TLS certificate status and warn you, including by email, when it's about to expire. You get to choose when to get a reminder.

✨ **Report latest backup status** [gh-396] You can now quickly see the latest backup status in the Sites Overview page. This is updated when the site information is updated (by default: every 15'), and will show you the status of your backups even if the backup is taken outside of Panopticon, e.g. CLI CRON jobs, Joomla! Scheduled Tasks etc. You will also be warned if the backup is older than a certain amount of time, which can be configured per site.

✨ **Support for site favicons** Panopticon will now display the favicons of the sites in the Sites Overview and Site Information pages, helping you visually identify which site is which.

✨ **Select language in Setup** [gh-384] You can now choose the language to display the installer in when installing Panopticon. _Enfin!_

✨ **Language selection after logging in** [gh-490] Have you ever logged into Panopticon only to find yourself starting at the wrong language? Fear not. You can now switch to a different language without having to log out.

✨ **Change the rotated log names** [gh-398] The rotated log files are now given more reasonable names, without showing up as log files for an unlreated site.

✨ **Preload hints, and HTTP 103 Early Hints** [gh-458] As long as your web server supports it, Panopticon will send HTTP/2 and HTTP/3 preload hints. If you are using [FrankenPHP](https://frankenphp.dev/) it will also send HTTP 103 Early Hint headers with that information, making the page load even faster.

✨ **Access a site's logs and tasks directly from the Site Information page**. Wanna take a look at the log entries and the scheduled tasks for a site? You no longer need to hunt for that site. The link is under the new Troubleshooting menu item at the top of the Site Information page.

✨ **Additional colour themes (CSS) and easier theme selection**. Panopticon now comes with additional colour themes (CSS files), and a drop-down to select them:

* Aegean. Deep blue and white, the colours of the Aegean Sea.
* Minty (by Bootswatch). Pastel greens and pink, like a mint candy.
* Scuderia. Legendary racing livery.
* WinterCandy. Pastel blues and plum.

All additional colour themes, except those marked as “by Bootswatch”, were created by us. Additional colour themes, except Aegean, are not very good for accessibility and/or look weird in Dark Mode.

**Loads of accessibility and design tweaks**. A lot of time and effort was spent by @brianteeman to tweak a lot of views for design consistency and accessibility. Hats off to Brian for his rigorous testing and prolific PRs!

Last, but not least, we identified and terminated a number of bugs _with extreme prejudice_. Quality of life matters.

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

* ✨ Dashboard layout for Sites Overview [gh-395]
* ✨ Scheduled Site Action Report Emails [gh-303]
* ✨ Basic uptime monitoring [gh-491]
* ✨ Plugin system
* ✨ SSL/TLS certificate information display, and sending expiration warning emails [gh-397]
* ✨ Select language in Setup [gh-384]
* ✨ Change the rotated log names [gh-398]
* ✨ Report latest backup status [gh-396]
* ✨ Support for site favicons
* ✨ Preload hints, and HTTP 103 Early Hints [gh-458]
* ✨ Language selection after logging in [gh-490]
* ✨ Additional colour themes (CSS) and easier theme selection
* ✨ Access a site's logs and tasks directly from the Site Information page
* ✏️ Running `composer install` will now always create the `version.php` file
* ✏️ Don't show backup and scanner scheduling buttons unless corresponding software installed [gh-413]
* ✏️ More accessible ID column labels [gh-446]
* 🐞 🔺 The Joomla! Update state could appear to be inconsistent
* 🐞 🔺 Users should not be able to be copied [gh-481]
* 🐞 ➖ Sending emails with the default language results in untranslated variables
* 🐞 🔻 PHP error when the browser returns invalid data during WebAuthn [gh-406]
* 🐞 🔻 TinyMCE content always dark [gh-410]
* 🐞 🔻 Backup not Pro when extension not installed [gh-414]
* 🐞 🔻 Date/time parsing on reports view [gh-419]
* 🐞 🔻 MFA method setup has non-functional toolbar buttons [gh-468]
* 🐞 🔻 Filtering the log files by site name did not work consistently


Legend:

* 🚨 Security update
* ‼️ Important change
* ✨ New feature
* ✂️ Removed feature
* ✏️ Miscellaneous change
* 🐞 Bug fix (🔺 High priority, ➖ Medium priority, 🔻 Low priority)
