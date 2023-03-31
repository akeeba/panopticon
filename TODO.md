# TO-DO

Web view (view=cron ???) for task execution

Web installer
    Clear the `maxexec.lasttick` and `maxexec.done` items from `#__akeeba_common`
    Set up a `maxexec` task (replacing any existing ones)
    Tell user how to set up task execution
    Wait for the task execution by polling the `maxexec.lasttick` and `maxexec.done` every 5 seconds. 
    Also allow user to skip over this step / finish setup later, removing the benchmark task

Connector plugin for J4 + build infrastructure for it

Automatic Log rotation
    Install a daily task for it during setup
    Reinstall it by CLI `cli task:add:logrotate [--expression="@daily"]`

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

# ✅ Done

Allow installation by CLI app
    
    php cli/panopticon.php config:create [--driver=mysqli] [--host=localhost] --user=USER --pass=PASS --name=DBNAME
    [--prefix=ak_] [--encryption=0] [--sslcipher=CIPHER] [--sslca=CS] [--sslkey=KEY] [--sslcert=CERT]
    [--sslverifyservercert]
    php cli/panopticon.php database:update [--drop]
    php cli/panopticon.php user:create --username=USERNAME --password=PASS [--name="Full Name"]
    php cli/panopticon.php config:set KEY VALUE
    php cli/panopticon.php config:maxtime:test

Manual log rotation

    php cli/panopticon.php log:rotate

CLI script to set up the maximum execution time

    php cli/panopticon.php config:maxtime:test

Task to benchmark max execution time (up to 3 minutes)
