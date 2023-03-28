# TO-DO

Allow installation by CLI app
    cli config:create [--driver=mysqli] [--host=localhost] --user=USER --pass=PASS --name=DBNAME
        [--prefix=ak_] [--encryption=0] [--sslcipher=CIPHER] [--sslca=CS] [--sslkey=KEY] [--sslcert=CERT]
        [--sslverifyservercert]
    cli db:create [--drop]
    cli user:create --username=USERNAME --password=PASS [--name="Full Name"]
    cli config:set KEY VALUE

Logging in task execution, and pass logger to task callbacks
    https://packagist.org/packages/monolog/monolog

Web view (view=cron ???) for task execution

Task to benchmark max execution time (up to 3 minutes)
    This is set up by the web interface, or the CLI command `cli task:add:benchmark [--force]`
    During setup give instructions to set up the CRON task runner (CLI or web) and check that it works.
    Use a 5-second timer to do an API call which returns the last time we heard from the CRON worker.
    If it is within 70 seconds we're good.
    The CRON worker should self-test that it can run for a full 185 seconds.
    This special task updates a DB field every second. This lets us determine at which point the CLI dies.
    Set max execution 5 seconds less than the maximum we reached, with a minimum of 10 seconds. Bias = 75% (hardcoded).

Web installer
    tell user how to set up task execution
    wait for task execution and benchmark (also allow user to skip over this step / finish setup later)

Connector plugin for J4 + build infrastructure for it

Log rotation https://packagist.org/packages/cesargb/php-log-rotation
    Install a daily task for it during setup, or by CLI `cli task:add:logrotate [--expression="@daily"] [--maxsize=1048756] [--files=1] [--compress=1]`

Add WebAuthn as an MFA method

# API features

## Core Joomla

**List extensions**

GET /v1/extensions?core=false&status=1&type=component

**Get configuration parameters**

GET /v1/config/com_akeebabackup?page[limit]=200

Can be used to change the update source for core Joomla

## Own connector

**List extensions with version and update status**

GET /v1/panopticon/extensions
    Normal list, with whatever data Joomla has cached

GET /v1/panopticon/extensions?force=true
    Force reload the extensions' updates

**Install extension updates**

POST /v1/panopticon/extensions/update
eid[]=123&eid[]=345

**Reinstall the current version of an extension**

POST /v1/panopticon/extensions/reinstall/123

**Get database fix info (for all extensions, or specific extensions)**

GET /v1/panopticon/extensions/database/0

GET /v1/panopticon/extensions/database/123

**Database fix for an extension**

POST /v1/panopticon/extensions/database/123

**Re-enable a disabled, or disabled an enabled update sites**

POST /v1/panopticon/extensions/updatesite?eid[]=123
status=0 or status=1

**List core version and update availability**

GET /v1/panopticon/core
    Normal list, with whatever data Joomla has cached

GET /v1/panopticon/core?force=true
    Force reload the updates

**Get database fix info for the core**

GET /v1/panopticon/core

**Database fix for the core**

POST /v1/panopticon/core/database

**Download the core update package to the server**

POST /v1/panopticon/core/update/download
    Downloads the update package, if one exists

POST /v1/panopticon/core/update/download?reinstall=true
    Downloads the package for the _currently installed_ version of Joomla

**Enable administrator/components/com_joomlaupdate/extract.php**

POST /v1/panopticon/core/update/activate

Note: the extraction and initial post-update processing is done using extract.php

**Disable administrator/components/com_joomlaupdate/extract.php (only used for communications testing)**

POST /v1/panopticon/core/update/disable

**Run the post-update code**

POST /v1/panopticon/core/update/postupdate

## Integrations

### Akeeba Backup

List backup profiles

Take backup

Download backup

Delete backup files

Delete backup record

### Admin Tools

Run PHP File Change Scanner

List PHP File Change Scanner results

Show PHP File Change Scanner result details

Unblock an IP

Temporarily disable and re-enable Admin Tools

Create temporary Super User

# User management

Each user can be assigned to a user group. Default (and only installed) group: super. The super group is immutable. No other group can have the super privilege. Available roles for the other user groups: no access, read-only, self-service, admin (default)

Each site can assign a different role per user group: inherit (default), no access, read-only, self-service (take backups, run PHP File Change Scanner, Unblock IP), admin. If the user is super it overrides per-site roles.

Only users with the super privilege can manage application-level configuration:
- System configuration
- Mail templates
- Site management
- User management
- Log management