This is a major feature release.

This release introduces user self-registration, Web Push notifications, core file integrity checks for Joomla sites, PII self-management, and a wealth of new CLI commands. It also includes important security, compatibility, and bug fixes.

## 🖥️ System Requirements

* PHP 8.1, 8.2, 8.3, 8.4, or 8.5. PHP 8.4 recommended.
* MySQL 5.7 or later, or MariaDB 10.3 or later. MySQL 8.4 recommended.
* Ability to run CRON jobs, either command-line (recommended) or URLs with a frequency of once every minute, and an execution time of at least 30 seconds (up to 180 seconds are strongly preferred).

## 🔮 What's coming next?

Development of Akeeba Panopticon takes place _in public_. You can see what we're planning, thinking of, and working on in [our issues tracker](https://github.com/akeeba/panopticon/issues).

## ✨ Highlights

**User Self-Registration.** Users can now register for an account on your Panopticon installation. Supports multiple registration modes (immediate, email activation, admin approval), CAPTCHA providers (ALTCHA, reCAPTCHA Invisible, hCaptcha), and password complexity validation.

**Web Push Notifications.** Receive instant browser notifications for important events alongside email. Web Push works as a complementary channel, notifying you of CMS updates, backup results, security issues, and more without checking your inbox.

**Core File Integrity Check.** Verify that your Joomla site's core files haven't been tampered with by comparing SHA-256 checksums against known-good values. Detects core hacks, failed updates, and potentially compromised files. Schedulable for automatic periodic checks.

**PII Self-Management.** Users can now manage their personal data directly: view legal policies, manage consent, export their data, and delete their own account. Helps you comply with GDPR and similar privacy regulations.

**CLI Commands for Administration.** New command-line tools for managing groups, mail templates, tasks, and backup/scanner schedules, making it easier to automate and script your Panopticon administration.

## 📋 CHANGELOG

* ✨ User self-registration [gh-726]
* ✨ PII self-management: legal policies, user consent, data export, and account self-deletion
* ✨ Web Push notifications as a complementary channel alongside email
* ✨ Check integrity of Joomla core files against known-good checksums [gh-20]
* ✨ reCAPTCHA Invisible and hCaptcha CAPTCHA providers for user registration
* ✨ Administrator email notification when a new user registration awaits approval
* ✨ Password complexity validation for user registration
* ✨ CLI commands for managing groups, mail templates, tasks, and backup/scanner schedules
* ✨ Links to the Connection Troubleshooting wiki page
* ✏️ PHP 8.5 compatibility
* ✏️ Sort task type filter options alphabetically by translated name
* ✏️ Docker image updated to PHP 8.4 [gh-957]
* 🚨 npm update — fix lodash prototype pollution (CVE-2025-13465)
* 🐞 [LOW] Cannot save the "Accurate PHP-CLI path" setting.
* 🐞 [MEDIUM] Missing PHP expiration dates can cause a fatal error displaying a site.
* 🐞 [MEDIUM] Invalid dates (e.g. in backups) can cause a fatal error.
* 🐞 [HIGH] Manually enqueueing a WordPress plugin update in the UI does not schedule the plugins update task for the site.
* 🐞 [HIGH] Unpublishing a site does not disable its scheduled tasks; re-publishing restores them.

Legend:

* 🚨 Security update
* ‼️ Important change
* ✨ New feature
* ✂️ Removed feature
* ✏️ Miscellaneous change
* 🐞 Bug fix
