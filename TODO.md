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

Connection failure detection (and point to documentation):
* Connection error (TCP/IP, SSL, …) — explain host firewalls, check spelling of site, DNS resolution may take time
* HTTP !== 401 when accessing /api/index.php/v1/extensions unauthenticated — make sure you can access the /api folder
* HTTP 403 — make sure the server does not block our User Agent or payload
* HTTP 401 when accessing /api/index.php/v1/extensions **authenticated** — Invalid Joomla API key, instructions to retrieve it
* Connector plugin not installed / not activated — instructions to install and activate the plugin
  * Run https://boot4.local.web/api/index.php/v1/config/com_panopticon?page[limit]=200 and make sure the result is not empty
* (Warning) Akeeba Backup Pro component not installed / not activated and/or Web Services - Akeeba Backup plugin (if version >= 9.6.0) not installed / not activated — you must install and activate Akeeba Backup Pro and specific plugins for full features
* (Warning) Cannot get list of profiles — you must install and activate Akeeba Backup Pro and specific plugins for full features
* (Warning) Admin Tools Pro component and/or Web Services - Admin Tools plugin (if version >= 7.4.0) not installed / not activated — you must install and activate Admin Tools Pro and specific plugins for full features
* (Warning) Cannot list WAF settings — you must install and activate Admin Tools Pro and specific plugins for full features

# API features

## Core Joomla

### Get configuration parameters

#### GET /v1/config/com_akeebabackup?page[limit]=200

Can be used to change the update source for core Joomla

## Own connector

### List extensions with version and update status

#### ✅ GET /v1/panopticon/extensions
    
List information about installed extensions and their update availability.

Filters:

* `updatable` Display only items with / without an update (default: null)
* `protected` Display only items which are / are not protected (default: 0)
* `id` Display only a specific extension (default: null)
* `core` Include / exclude core Joomla extensions (default: null)

#### ✅ GET /v1/panopticon/extension/123

Get information about extension with ID 123

#### ✅ GET /v1/panopticon/extension/com_foobar

Get information about an extension given its Joomla extension element e.g. com_example, plg_system_example, tpl_example, etc.

### Update handling

#### ✅ POST /v1/panopticon/updates

Tell Joomla to fetch update information.

Filters:

* `force` Should I force-reload all updates?

#### ✅ POST /v1/panopticon/update

Install updates for specific extension

POST parameters:

```eid[]=123&eid[]=345```

#### ✅ GET /v1/panopticon/updatesites

List update sites

Filters:
* `enabled` Filter by published / unpublished sites
* `eid[]` Filter by extension ID (array, multiple elements allowed)

##### ✅ PATCH /v1/panopticon/updatesite/123

Modify an update site

##### ✅ DELETE /v1/panopticon/updatesite/123

Delete an update site

##### ✅ POST /v1/panopticon/updatesites/rebuild

Rebuild the updates sites

### Joomla Core Update

#### ✅ GET /v1/panopticon/core/update

List core version and update availability

#### POST /v1/panopticon/core/update/download

Download the core update package to the server

POST parameters:
* `reinstall` Set to 1 to reinstall the current version (only if an update is unavailable)

#### POST /v1/panopticon/core/update/activate

Enable `administrator/components/com_joomlaupdate/extract.php` or `administrator/components/com_joomlaupdate/restore.php`

Note: the extraction and initial post-update processing is done using extract.php

#### POST /v1/panopticon/core/update/disable

Disable `administrator/components/com_joomlaupdate/extract.php` or `administrator/components/com_joomlaupdate/restore.php`

This should only be used for testing the communication with this file. Otherwise, it will be disabled automatically by Joomla.

#### POST /v1/panopticon/core/update/postupdate

Run the post-update code

### Database fix

#### GET /v1/panopticon/database

Get database fix info for all extensions.

#### GET /v1/panopticon/database/123

Get database fix info for an extension, given its ID

#### GET /v1/panopticon/database/pkg_something

Get database fix info for an extension, given its element

#### POST /v1/panopticon/database/123 

Apply database fix for an extension given its ID

#### POST /v1/panopticon/database/pkg_example

Apply database fix for an extension given its element

### Reinstall / refresh extensions

#### POST /v1/panopticon/reinstall/123 ❓

Reinstall the current version of an extension, given its extension ID

#### POST /v1/panopticon/reinstall/pkg_something ❓

Reinstall the current version of an extension, given its element

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
