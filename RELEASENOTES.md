> [!IMPORTANT]
> The container (Docker) image for this version includes a critical security update from the PHP project.

## Highlights

**New: Akeeba Backup JSON API v3.** Panopticon now talks to Akeeba Backup's JSON API v3 on Joomla! sites, authenticating with the Joomla! API Token it already uses for the Panopticon connector instead of the deprecated Akeeba Backup Secret Word. There is nothing to configure: the upgrade is transparent. If v3 is unavailable, Panopticon falls back to the older API versions and the Secret Word, as before. WordPress sites are unaffected. Token authentication requires Akeeba Backup 10.4.0 or later on the monitored site.

**Task queue reliability.** Mass extension installs across many sites could stall after about 30 sites, and tasks could occasionally be picked up twice by concurrent `task:run --loop` processes. Both are fixed. The long-running `task:run --loop` process also no longer leaks a log file handle for every site it processes.

**Translations.** Many messages in non-English languages showed "BAD TRANSLATION", lacked their values (e.g. Joomla! versions in Dutch), or had broken links and stray backslashes. 128 newer messages, such as those for the Connection Doctor and the Extensions view, were shown in English. If you use Panopticon in another language, you will notice the difference.

**Other fixes.** Joomla site pages no longer fail with "Error calling repeatable" after you view a WordPress site. The `user:add`, `user:create` and `user:set` CLI commands now report errors properly, and translated email templates are used for plain-text emails and the update summary email.

## Container images' security

The PHP project [announced yesterday two critical vulnerabilities](https://www.php.net/archive/2026.php#2026-10-21-5) affecting all PHP versions from 5.0 onwards. The vulnerability occurs within PHP itself, before any code written in PHP can execute. Therefore, the mere fact that Panopticon's container images include an immutable version of PHP made them vulnerable even if Panopticon itself did not have a vulnerability. This is always the catch with containerisation: _container images don't ship just an application, they ship the entire server environment_. 

Panopticon's container image for version 2.4.0 comes with PHP 8.5.12 which is not vulnerable as of this writing. Therefore, we kindly ask all of our users to update.

## CHANGELOG

* 🚨 Security hardening: CSRF token checks, TOTP secret generation, JSONP in JSON views, IIS web.config rules (AWF 1.3.1)
* ✨ Akeeba Backup JSON API v3 support, using the site's existing Joomla! API Token instead of the Secret Word
* ✏️ Docker images now use PHP 8.5.12, which includes a critical security update from the PHP project
* 🐞 [HIGH] Task queue: mass extension install stalled after about 30 sites, and tasks could run twice [gh-1060]
* 🐞 [HIGH] Sites: Joomla site pages could fail with "Error calling repeatable" after viewing a WordPress site
* 🐞 [HIGH] Setup: could never finish when config.php was not writable and had to be uploaded by hand
* 🐞 [HIGH] Setup: new installations went to the login page after creating the administrator account
* 🐞 [MEDIUM] Tasks: per-site log files were never closed, leaking file handles in task:run --loop [gh-1060]
* 🐞 [MEDIUM] Translations: broken links and stray backslashes, e.g. MFA "Print these codes" led to a 404 page [gh-1081]
* 🐞 [MEDIUM] Translations: many messages left out their values or read "BAD TRANSLATION", e.g. Joomla! versions in Dutch
* 🐞 [LOW] API token authentication triggered a PHP 8.5 deprecation notice
* 🐞 [LOW] Mass extension install: a failed extensions refresh could mark a successful install as failed [gh-1060]
* 🐞 [LOW] System Configuration: the Have I Been Pwned and Gravatar links did not open in a new tab
* 🐞 [LOW] Translations: 128 newer messages, e.g. the Connection Doctor and Extensions view, were shown in English
* 🐞 [LOW] Translations: some help texts were shortened, or lost their links and formatting, compared to English
* 🐞 [LOW] French: the Multi-factor Authentication page had a garbled title, and "authentification" was misspelt
* 🐞 [LOW] API Tokens: the token quota read "BAD TRANSLATION" instead of how many tokens you have created
* 🐞 [LOW] Joomla! update log: the message about enabling Joomla Update's extraction script showed a stray backslash
* 🐞 [LOW] The English language file could not be read by standard INI parsers and translation tools
* 🐞 [LOW] Language tools: every INI to PO conversion added one more level of escaping to double quotes [gh-1081]
* 🐞 [LOW] Sites: WordPress themes were labelled "Template" instead of "Theme" in the plugins and themes list
* 🐞 [LOW] CLI: user:create and user:set reported "Could not save user" even though the user was saved
* 🐞 [LOW] Updated view templates could keep running stale code on servers with OPcache timestamp checks disabled
* 🐞 [LOW] Emails: translated templates, e.g. German, were ignored for plain-text emails and the update summary email
* 🐞 [LOW] CLI: user:add showed a PHP stack trace instead of an error message when the user could not be saved
* 🐞 [LOW] Some view templates could be requested directly, revealing the server path when PHP errors are shown
* 🐞 [LOW] CLI: user commands showed a raw exception instead of an error message for passwords found in data breaches

Legend:
* 🚨 Security update
* ‼️ Important change
* ✨ New feature
* ✂️ Removed feature
* ✏️ Miscellaneous change
* 🐞 Bug fix