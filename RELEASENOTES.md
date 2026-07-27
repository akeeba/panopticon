> [!IMPORTANT]
> **This is a security update**. A security issue was addressed. Please refer to the Security section below.

## Highlights

**New: Extensions view.** A single view lists every extension installed across all monitored sites, making it easy to spot outdated or vulnerable extensions without visiting each site individually.

**New: Joomla Update Doctor.** Diagnoses why a Joomla core update failed or got stuck, instead of leaving you to guess from log files.

**New: `token:create` CLI command.** Mint an API token for a user directly from the command line, without going through the web UI.

**Sticky page header and toolbar.** Optionally, keep the page header and toolbar visible while scrolling, configurable application-wide and per user. Toolbar buttons now collapse into a menu on narrow displays.

**Group colours.** Groups can be assigned a colour, which is then shown on site badges, making it easier to tell at a glance which group a site belongs to.

**Other changes.** Reports are now sorted by date, most recent first.

**Reliability fixes.** This release fixes several high-priority issues: occasional PHP errors on group edit and permission checks; the per-group API token limit not being enforced; system-level emails crashing silently instead of being sent; the MCP server and JSON API rejecting valid tokens (401 errors) when a server stripped the `Authorization` header or when `config.php` was missing its secret; the short `/mcp` endpoint 404ing due to a missing rewrite rule; and the log viewer showing a blank or error page in non-English interface languages. It also fixes duplicate scheduled update summary emails, Web Push notifications not respecting each recipient's language, and completed one-shot tasks never being cleaned up. Code was also refactored to avoid false-positive detections by some anti-malware scanners (e.g. Immunify360).

## Security

> [!NOTE]
> For the full details, please consult the [security advisory](https://github.com/akeeba/panopticon/security/advisories/GHSA-6234-p3mh-7j3x).

An untrusted user who can add or edit sites could exploit the Connection Doctor features to perform server-side request forgery (SSRF). In practical terms, this only affects you if both of the following conditions are true:
- You give add own site, edit own site, add site, edit site, or Super User privileges to people you don't trust, i.e. you're offering a self-service site monitoring service to people you don't necessarily trust.
- Your Panopticon instance is on a server which has unauthenticated local network endpoints divulging privileged information (e.g. [AWS EC2 metadata](https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/instancedata-data-retrieval.html)) **AND / OR** internal network or internal infrastructure endpoints which can divulge privileged information or take actions without authentication.

If both conditions are true, it's a high-severity issue as it allows the attacker to look into your internal architecture: the Connection Doctor shows the response HTTP headers and content. In practice, we do not expect anyone currently using Panopticon to be affected by this. 

The same issue applies to the Uptime Monitor, when only the second condition is true, as long as an attacker is able to make the Uptime Monitor URL issue a redirect or has taken control of the site's DNS to make the domain itself resolve to an internal IP address. This _MAY_ affect some current installations. However, we want to clarify that it's of lower severity because the attacker cannot see the resulting HTTP headers or content upon accessing the Uptime Monitor URL, only if it resulted in an HTTP 200 OK.

 If you expect to be affected by this issue, we have introduced an option to control who can access the Connection Doctor, and an IP deny list which will block URLs – in the site's endpoint and uptime monitor – which resolve or redirect to any of these denied IPs.

## CHANGELOG

* 🚨 SSRF via Connection Doctor and Uptime Monitor [GHSA-6234-p3mh-7j3x]
* ✨ Extensions view: all extensions across all sites [gh-1015]
* ✨ Joomla Update Doctor: diagnoses why a Joomla core update fails or gets stuck
* ✨ `token:create` CLI command to mint an API token for a user
* ✨ Optional sticky page header and toolbar, configurable application-wide and per user [gh-1025]
* ✨ Toolbar buttons collapse into a menu on narrow displays
* ✨ Groups can be given a colour, shown on the site badges [gh-1023]
* ✏️ Reports are now sorted by date descending [gh-1032]
* ✨ 🐞 [HIGH] Occasional PHP error on group edit and permission checks
* ✨ 🐞 [HIGH] Per-group API token limit wasn't applied [gh-965]
* ✨ 🐞 [HIGH] System-level emails crashed with a TypeError and were never sent [gh-1009]
* ✨ 🐞 [HIGH] MCP server and JSON API returned 401 when server stripped HTTP Authorization header [gh-1010]
* ✨ 🐞 [HIGH] MCP server and JSON API token authentication failed with "no_secret" on config.php missing secret [gh-1010]
* ✨ 🐞 [HIGH] Short /mcp endpoint returned a 404 due to no rewrite rule in htaccess.txt
* ✨ 🐞 [HIGH] The _panopticon_token query parameter failed for Base64 tokens containing "+"
* ✨ 🐞 [HIGH] Refactored code to avoid false positives in some anti-malware scanners e.g. Immunify360
* ✨ 🐞 [MEDIUM] Web Push notifications were not sent in each recipient's own language as intended [gh-1027]
* ✨ 🐞 [MEDIUM] Scheduled update summary emails re-sent duplicate notifications on every run
* ✨ 🐞 [HIGH] Log viewer showed a blank or error page when the interface language was not English [gh-1035]
* ✨ 🐞 [LOW] Completed one-shot tasks (e.g. extension install) were never deleted [gh-1034]
* ✨ 🐞 [LOW] Log viewer dropped the first line of any log read in full, showing an empty table for a one-line log [gh-1036]

Legend:
* 🚨 Security update
* ‼️ Important change
* ✨ New feature
* ✂️ Removed feature
* ✏️ Miscellaneous change
* 🐞 Bug fix