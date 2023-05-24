# TO-DO

## Setup: use language strings instead of hardcoded English text in view templates

## Extension updates

✅We need to provide a global default for extension updates PER EXTENSION:
* `none` None. No updates, no emails.
* `patch` Same Version Family. Only patch versions (e.g. 1.2.3 -> 1.2.4)
* `minor` Same Major Version. Patch and minor (e.g. 1.2 -> 1.3)
* `major` Any (Not Recommended). Patch, minor, and major (e.g. 1.2 -> 2.0)

The factory default is `none`.

The global default can be overridden in two levels: 
* ✅Global, per extension. For example, all versions of Akeeba Backup should be set to “Patch, Minor, and Major”. Requires new page, Extensions.
* ✅Per site, per extension. The default setting will be "Use global". Only extensions deviating from Use Global will have their preference recorded under the site param key `config.extension_update.extensions` which is an array keyed to _the extension ID_.

> **Reasoning**: Typically, extensions which are “safe” to upgrade are safe across _all_ of your sites. For example, Akeeba Backup and JCE can be updated without anything breaking, Akeeba Ticket System can only be expected to do so within the same major version, some other extension may be introducing random breaking changes all the time, therefore you don't want to ever update it automatically. However, you may have a special snowflake of a site where upgrading JCE would break some third party extension for reasons unknown. If you have set JCE to always update to any version it would break that site; hence the need to be able to say "nope, I don't want to even be told that JCE is out of date on that site".

The list of upgradeable extensions will be populated by the extensions discovered in the defined sites and continuously refined every time we update the sites' extensions list.

What to do

* Create new task type `extensionsupdate` which handles the update of the extensions of one specific site
* Install one global task of type `extupdateconductor` for automatically setting up extension update installation
  * Query sites with extension updates (use MySQL's JSON features) which do NOT already have an enabled run-once extension update task.
  * Create (or re-enable) a "run once" task to update the site's extensions.
  * Create a queue for the extensions to update on this site
  * Enqueue email for sending

The `extensionsupdate` task
* Loop through the list of extensions to update and tell the site to install the update
* After all updates have been installed (or failed to install) send an email telling the user how many updates were available, how many were installed, and how many failed to install

If a user chooses to update an extension with no auto-updates:
* If a queue for the extensions to update on this site does not exist, create it
* Add the extension ID to the queue
* Create (or re-enable) a "run once" task to update the site's extensions.

## Content-Security-Policy

Both at the application level and in a .htaccess file (name it htaccess.txt).

## Button to immediately enqueue an extension update

## Button to immediately enqueue ALL extensions update

## Edit the missing Download Keys

## Tasks page (only listing, no management)

## Log viewer page

## Add WebAuthn as an MFA method

## Automatic generation of SBOM

composer --global require cyclonedx/cyclonedx-php-composer

CycloneDX:make-sbom --output-format=json --omit=dev --output-reproducible --validate --mc-version=0.0.1 --output-file=app/vendor/sbom.json

## About page which lists the SBOM?

## Implement self-update

Both on the web and CLI

Web version needs to show a notification if there's a new version AND it's can be installed (i.e. PHP version is fine)

Best use the same dead-simple INI format as Solo.

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

## Number of changed templates

When fetching site info, get the number of changed template files and show that in the main page and the site info page.

## Buttons to reset email templates to default (all, or specific ones)

## Chunked Joomla update package uploads

Well over a year(!) since I first reported this issue, downloading update packages in the office take forever, at an average transfer rate of 50Kbps (I have a 100Mbps line and no throttling on any other site). This is 100% a problem with Joomla using S3 as the primary download source, something I have explained to them how to solve, and save money, by making one simple change in a single XML file. They won't do it.

I had made a PR to download the updates in chunks. It was rejected because some useless busybodies are not affected by this problem and, of course, it was me making a PR. Same old story.

So. Let's change the connector to try and use the CloudFlare URL for the download source AND download the archive in small chunks. As it happens, this also lets us do a HEAD request to avoid re-downloading the same update package all over again if it's already downloaded and complete in our temp folder (Gasp! Imagine that! Being efficient! Whoda thunk it!).

In short, since Joomla sucks let's work around it to make it suck less. Story of my life, ever since Joomla was called Mambo.

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

## Site report page

## Button to immediately enqueue a site update

## Database setup in the configuration page

## Core updates

✅ We need to provide a global default for core updates (`tasks_coreupdate_install`):
* ✅ `none` None. No updates, no emails
* ✅ `email` Email. No updates, only sends emails.
* ✅ `patch` Same Version Family. Only patch versions (e.g. 1.2.3 -> 1.2.4)
* ✅ `minor` Same Major Version. Patch and minor (e.g. 1.2 -> 1.3)
* ✅ `major` Any (Not Recommended). Patch, minor, and major (e.g. 1.2 -> 2.0)

✅ The factory default is `patch`.

✅ Each site has these options in `config`, editable in the site config page

* config.core_update.install: '', none, email, patch, minor, major • default: '' (use global)
* config.core_update.when: immediately, time • default: immediately
* config.core_update.time.hour: (integer 0-23) • default: 0
* config.core_update.time.minute: (integer 0-59) • default: 0
* config.core_update.email.cc: (list of email addresses to CC) • default: empty
* config.core_update.email_error: (boolean) • default: false
* config.core_update.email_after: (boolean) Only when config.core_update.install not none or email • default: false

What to do

* ✅ Create new task type `joomlaupdate` which handles the update of one specific site
* ✅ Install one global task of type `sendmail` which sends the enqueued emails
* ✅ Install one global task of type `joomlaupdatedirector` for automatically setting up core update installation
  * Query sites with updates (use MySQL's JSON features) and `core.lastUpdateInstallEnqueued` is not `core.latest.version`
  * For each site, do something depending on what `config.core_update.install` resolves to:
    * `none`. Nothing.
    * `email`. Enqueue email about available update
    * Anything else:
      * If the update is not allowed: Enqueue email about available update
      * If the update is allowed: Enqueue email about update to be installed, create (or re-enable) a "run once" task to upgrade the site.
    * For all: set `core.lastAutoUpdateVersion` to `core.latest.version`

The `joomlaupdatedirector` task
* ✅ Performs any pre-upgrade tasks (TO-DO)
* ✅ Downloads the update package
* ✅ Enables Joomla Update's restoration.php / extract.php
* ✅ Goes through the extraction and post-extraction steps
* ✅ Performs any post-upgrade tasks (TO-DO)
* ✅ Send completion email

✅ If a user chooses to upgrade a site with no auto-updates (the resolved config.core_update.install is none or email) reuse the code in `coreupdatedirector` (maybe make it a Trait?) to create or re-enable a run-once task which carries out the update.


## Refresh site information after saving it

We need to run the site information and extension information gathering in the background, e.g. schedule a run-once task with top priority.

## Email templates page

✅ Common CSS for all mail templates, handled in its own task.

✅ The edit task of a template initialises a TinyMCE editor with the common CSS added as the `content_style` option.

✅ Each mail template consists of
- Mailing Type (from a list)
- Language (hide this for now, set it to `*` i.e. all languages)
- Subject
- HTML Content
- Plaintext Content

✅ We need to document which variables (in the form `[FOOBAR]`) are supported in general, and for each mail template.

✅ We need a mail helper to find the correct email template.

✅ Create default email templates

✅ Auto-populate default email templates on database installation.

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

## Periodic retrieval of installed extensions

## Custom menu

See \Akeeba\Panopticon\Application::MAIN_MENU

## System Configuration page

## Warn if the automation is not running

If the last CRON job execution was more than a minute ago show a warning which takes you to a page to help you set up the CRON jobs.

## Sites page

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

## Check that all default tasks are installed

If any of the default tasks are missing, install them

## Check the database for consistency

## Notify about PHP versions going out of support

## Email setup

In the configuration page

## Composer installation

Explore installation through Composer with automated post-update scripts (clean tmp, upgrade database, …).

Remember that we will need an `archive` section in `composer.json` to exclude the build stuff.
