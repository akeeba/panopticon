This version is a maintenance release. We implemented some new features to make your lives easier.

**Domain registration and expiration warnings**. You can now see when the domain itself was registered, and when it's expiring. You can receive an email notification before the expiration of your domain name, so you have enough time to renew it.

**Force MFA for specific user groups, superusers, or administrators [gh-723]**. You can tell Panopticon to enforce use of Multi-factor Authentication for Superusers, Administrators, or specific user groups. Users with forced MFA who have not yet set up MFA on their accounts will be taken to a captive page which requires them to set up MFA before being allowed to proceed any further.

**Option to treat MFA failures as login failures [gh-723]**. You now have the option to treat Multi-factor Authentication failures as login failures for the purposes of automated IP blocking. This ensures that a malicious actor who has subverted the login information of a user will be locked out after a number of failed MFA attempts, preventing them from brute-forcing a weaker MFA method (e.g. six digit authenticator codes).

**Enforce a maximum number of MFA attempts [gh-723]**. You can now set a limit on how many times a user can fail to provide a valid MFA method. Once that limit is reached the user is logged out. This ensures that a malicious actor who has subverted the login information of a user will not be able to brute force their way through a weaker MFA method (e.g. six digit authenticator codes) by adding this hurdle which greatly increases the necessary time and complexity of an attack to something impractical.

**Accurate PHP CLI path in the CRON job setup page**. In the past we were using the generic placeholder `/path/to/php` to indicate that you needed to replace this with the path to PHP CLI given to you by your host. Unfortunately, many hosts have under-trained first level support staff which can't provide this information, and does not understand the difference between PHP CLI and PHP CGI. We have now added code which tries to identify the PHP CLI binary automatically using our experience of where these files are usually to be found on a very large sample of live and local server environments across all major operating systems (Windows, Linux, macOS, FreeBSD etc.). In most cases, the command line you are given will be one you can just copy and paste into your host's CRON management page without having to do any thinking, or contacting your host. Simplicity, yay!

## 🖥️ System Requirements

* PHP 8.1, 8.2, or 8.3. PHP 8.3 recommended. Experimental support for the upcoming PHP 8.4 release.
* MySQL 5.7 or later, or MariaDB 10.3 or later. MySQL 8.0 recommended.
* Ability to run CRON jobs, either command-line (recommended) or URLs with a frequency of once every minute, and an execution time of at least 30 seconds (up to 180 seconds is strongly preferred).
* Obviously, the server it runs on must be connected to the Internet, so it can communicate with your sites.

## 🔮 What's coming next?

Development of Akeeba Panopticon takes place _in public_. You can see what we're planning, thinking of, and working on in [our issues tracker](https://github.com/akeeba/panopticon/issues).

## 📋 CHANGELOG

**TBD**

Legend:

* 🚨 Security update
* ‼️ Important change
* ✨ New feature
* ✂️ Removed feature
* ✏️ Miscellaneous change
* 🐞 Bug fix
