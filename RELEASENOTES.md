## Release highlights

**Security update: TinyMCE 6.7.1** TinyMCE 6.7.0 had three moderate severity mXSS vulnerabilities. Their impact in the context of Panopticon, as it's only used for managing mail templates which is an operation that can only be carried out by the most privileged users of the system. As a result, the impact on Panopticon is a case of “Me or the people I trust with absolute access to my installation can hack ourselves”. In other words, the impact is minimal at worst, practically non-existent for most installations.

## System Requirements

* PHP 8.1, 8.2, or 8.3. PHP 8.2 recommended.
* MySQL 5.7 or later, or MariaDB 10.3 or later. MySQL 8.0 recommended.
* Ability to run CRON jobs, either command-line (recommended) or URLs with a frequency of once every minute and an execution time of at least 30 seconds (up to 180 seconds is strongly preferred). 
* Obviously, the server it runs on must be connected to the Internet, so it can communicate with your sites.