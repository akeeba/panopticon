Welcome to version 1.2! It took a while, but we have implemented a number of major new features and improvements.

**WordPress support**. You can now monitor WordPress sites. This feature has only been tested with WordPress 5.0 or later, with a few sites only. Please, treat it as a “beta” feature.

**Much improved Docker support**. You can now use a `.env.docker` file to configure the Docker instance instead of having to hack through the `docker-compose.yml` file. The dockerizer instance can have more than one CRON jobs running; this is user-configurable. You can upgrade the Docker instance without losing your settings just by updating the image and restarting the container. For this reason, the integrated Panopticon updater is _disabled_ when running under Docker.

**Translatable dates**. Previously, you could change the date format, but not the language the dates where in. For example, you'd get "Monday, July 1, 2024" even when your language was set to, say, Greek. Now, the day and month names are properly translatable.

**Load TinyMCE translations**. TinyMCE, the editor used for mail templates and site notes, comes with its own interface translations. Previously, only the English language was loaded. Now, we check if there's a translation which (kinda) matches your selected language and load it as well. Please note that TinyMCE's translations do not have a one-to-one mapping to Panopticon languages. We try to automatically find the best match. If this is not possible, or if the translation is partial, we fall back to English.

**Batch processing sites**. You can now select multiple sites to assign them and/or remove them from groups.

**Control email sending for scheduled backups [gh-712]**. You can choose whether an email will be sent at the end of successful or failed _scheduled_ backup.

**Auto-ban IPs after many failed login attempts**. Panopticon can temporarily block IP addresses if many failed login attempts have originated from them. This feature is enabled by default, but it can be turned off if it's a problem for you or your clients. The number of failed logins, the period they have to take place in, and the amount of time they will remain blocked is user-configurable.

**Check passwords against Have I Been Pwned [gh-728]**. Panopticon will check new passwords against the third party Have I Been Pwned service. If the password is found in online password leaks the user will be asked to use a different password. This feature can be disabled in the System Configuration, however we recommend that you _always_ keep this enabled for maximum protection of your monitored sites.

**Session data contents are now encrypted at rest**. Panopticon uses PHP's default session save path. This means the session data stored is typically placed in a world-readable directory managed by your host along with other sites under the same account or, worse, server. This is bad because potentially privileged information is stored in plaintext where they can easily be found. The contents of the session files are now encrypted with a key generated randomly for each Panopticon installation.

**Session improvements**. There's an option to force Panopticon to use the `tmp/session` folder under its root as the PHP session save path, regardless of whether your host offers a writeable PHP session path already. This addresses the issue of getting logged out of Panopticon because PHP's session garbage collection reaped your session files before your session actually expired.  Furthermore, we took a few extra security steps to make Panopticon more resistant to session hijacking, session fixing, and other similar session-related security issues.

## 🖥️ System Requirements

* PHP 8.1, 8.2, or 8.3. PHP 8.3 recommended.
* MySQL 5.7 or later, or MariaDB 10.3 or later. MySQL 8.0 recommended.
* Ability to run CRON jobs, either command-line (recommended) or URLs with a frequency of once every minute, and an execution time of at least 30 seconds (up to 180 seconds is strongly preferred).
* Obviously, the server it runs on must be connected to the Internet, so it can communicate with your sites.

## 🔮 What's coming next?

Development of Akeeba Panopticon takes place _in public_. You can see what we're planning, thinking of, and working on in [our issues tracker](https://github.com/akeeba/panopticon/issues).

## 📋 CHANGELOG

* ✨ WordPress support [gh-38]
* ✨ Much improved Docker support [gh-697]
* ✨ Translatable dates
* ✨ Load TinyMCE translations
* ✨ Batch processing sites
* ✨ Control email sending for scheduled backups [gh-712]
* ✨ Auto-ban IPs after many failed login attempts
* ✨ Check passwords against HIBP [gh-728]
* ✏️ System Configuration uses more Show On tricks to show/hide relevant settings
* ✏️ Expose the Avatars setting in System Configuration [gh-729]
* ✏️ Session data contents are now encrypted at rest
* ✏️ Session improvements
* ✏️ Expose the Behind Load Balancer configuration setting
* ✏️ Do not send a failure email if a site queued for update is already updated, or disabled
* 🐞 🔺 Some tasks would disable MySQL autocommit without restoring it, leading to weird issues
* 🐞 ➖ MaxExec task throws fatal exception when tasks are executed over the web
* 🐞 🔻 Wrong message about not having Akeeba Backup installed shown when adding a new site [gh-661]
* 🐞 🔻 Wrong language in mail Blade templates [gh-658]
* 🐞 🔻 Groups for disabled sites may not be displayed in the Sites admin page
* 🐞 🔻 Connection doctor: sometimes ends up with an error page instead of showing what is going on with the connection
* 🐞 🔻 High CPU usage warning when the server does not report CPU usage at all
* 🐞 🔻 Update failure email missing site name if site is already up-to-date
* 🐞 🔻 Update Director would claim a site is enqueued for updates when it's not
* 🐞 🔻 Per-language overrides of extension update emails might not have an effect

Legend:

* 🚨 Security update
* ‼️ Important change
* ✨ New feature
* ✂️ Removed feature
* ✏️ Miscellaneous change
* 🐞 Bug fix (🔺 High priority, ➖ Medium priority, 🔻 Low priority)
