# TO-DO

## Custom menu

Do not let automatic menu item creation

## Add WebAuthn as an MFA method

## Connection failure detection (and point to documentation):
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

## Core updates
* Install one task per site for core version checks. The task caches the results to the site's definition.
* Install one global task for automatically installing updates.
  * Query sites with updates (use MySQL's JSON features) which do NOT already have an enabled run-once upgrade task.
  * Configurable conditions for upgrading (only stable versions? only within the same minor/major?)
  * Create (or re-enable) a "run once" task to upgrade the site. This is a high priority task.
  * If there are sites queued up for upgrade allow 2 minutes between each site's upgrade task.
* Notify user when there is a new available version and whether it will be auto-installed.
* Notify the user when the upgrade succeeds / fails.
* When the user chooses to upgrade a site, push a new (or re-enable an existing) "run once" task to upgrade the site.
* The time of the update installation should be something the user can define. Remember that sites may be used in different timezones e.g. a US-centric site is best updated around midnight CST i.e. 05:00 UTC
* In the future, we can choose whether to schedule a backup before the update
  * This requires making pre-requisite tasks, or otherwise avoid duplicating the backup logic

## Automatic generation of SBOM

composer --global require cyclonedx/cyclonedx-php-composer

CycloneDX:make-sbom --output-format=json --omit=dev --output-reproducible --validate --mc-version=0.0.1 --output-file=app/vendor/sbom.json

# Integrations

## Akeeba Backup

List backup profiles

Take backup

Download backup

Delete backup files

Delete backup record

## Admin Tools

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

# 🤔 Maybe

## User groups implementation

* We need a db table to store groups: id, name, privileges (one or more of the user privileges)
* Groups are _flat_ (no hierarchy); we have a very simple use case
* We need a db table linking users to groups (many to many)
* Page to view and edit groups
* Custom user class
  * Allows setting user groups
  * Autoloads applicable group permissions on load
  * `authorise(?int $group, string $privilege, bool $default = false)`. If $group is null returns global privilege (proxy to getPrivilege()). If the user does not belong to $group returns $default.
* Custom user manager class, using the custom user class
* db field in sites table to assign a site to _multiple_ groups
* All permission checks will go through the site table object
  * Loop through all user groups and call $user->authorise($group, $privilege). Return immediately on true.
  * If that failed to return results return $user->authorise(null, $privilege) for the global privilege.

# ✅ Done

## Run once tasks
* They need to have the `params` key `run_once`.
* If the `params` key `run_once` is `delete` the task is deleted upon completion.
* If the `params` key `run_once` is `disable` the task is disabled upon completion.

## Web view (view=cron) for task execution

## Allow installation by CLI app
    
    php cli/panopticon.php config:create [--driver=mysqli] [--host=localhost] --user=USER --pass=PASS --name=DBNAME
    [--prefix=ak_] [--encryption=0] [--sslcipher=CIPHER] [--sslca=CS] [--sslkey=KEY] [--sslcert=CERT]
    [--sslverifyservercert]
    php cli/panopticon.php database:update [--drop]
    php cli/panopticon.php user:create --username=USERNAME --password=PASS [--name="Full Name"]
    php cli/panopticon.php config:set KEY VALUE
    php cli/panopticon.php config:maxtime:test

## Manual log rotation

    php cli/panopticon.php log:rotate

## CLI script to set up the maximum execution time

    php cli/panopticon.php config:maxtime:test

## Task to benchmark max execution time (up to 3 minutes)

## Web installer
* Clear the `maxexec.lasttick` and `maxexec.done` items from `#__akeeba_common`
* Set up a `maxexec` task (replacing any existing ones)
* Tell user how to set up task execution
* Wait for the task execution by polling the `maxexec.lasttick` and `maxexec.done` every 5 seconds.
* Also allow user to skip over this step / finish setup later, removing the benchmark task
* Finally, set the config variable `finished_setup` to true in app config

## Periodic retrieval of site information

Add one task per site to run every hour (at a random minute?) to fetch the site's information (core update status, environment i.e. PHP info, and installed extensions).

Or we could add an hourly task which iterates through all sites.
* First, add all sites to a queue
* Loop
  * Fetch 5 sites -- TODO Make this configurable
  * Do parallel requests to get the information from the server, then call the code to parse their results.

## Automatic (re)installation of tasks

The following tasks must be installed at the end of the installation, and a feature provided to (re)install them if they are missing / modified:

- Once daily: `logrotate`
- Every 10 minutes: `refreshsiteinfo`
