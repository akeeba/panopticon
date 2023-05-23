# Configuration parameters

The following are the known application configuration options which can be set in `config.php`.

## System

Fundamental behaviour of the entire application.

### `session_timeout`
**Session Timeout**

How long is a login session valid for.

Default: 1440

UOM: Minutes

### `timezone`
**Time Zone**

The timezone to use for displaying information in the interface

Default: UTC

### `debug`
**Debug system**

Should system debugging be enabled? Displays more detailed error messages at runtime and enabled very detailed logging. Only enable if you are asked to.

Default: false

### `error_reporting`
**Error reporting level**

How verbose should error reporting to the browser output be? The valid options are:
* `default`. Use the PHP configuration.
* `none`. No error reporting to the browser
* `simple`. Only fatal error and warnings (core PHP or user-defined) are output to the browser.
* `maximum`. All fatal errors, warnings, notices, and deprecation notices are output.

Default: default

### `live_site`
**Panopticon installation URL**

The URL Panopticon is installed on. Used when sending emails through a CLI CRON job.

Default: (blank)

### `session_token_algorithm`
**Anti-CSRF Token Algorithm**

The hash algorithm for creating an anti-CSRF token. SHA-512 offers the best security, but may not work on some hosts because it's very long. MD5 offers the least security but is compatible with all hosts. Only change if you have problems when clicking action buttons in Panopticon.

Default: sha512

### `finished_setup`
**Have we finished installing up the application?**

_Hidden option._

This is automatically set at the end of the initial installation, after the CRON job configuration has been confirmed
(or the user chose to skip it).

## Display preferences

Controls how the HTML application displays its information.

### `darkmode`
**Dark Mode**

Should the application automatically switch to dark mode?

Default: -1 (use browser settings)

Valid settings: -1 (auto; use browser), 0 (always light), 1 (always dark)

### `fontsize`
**Font size**

Overrides the body font size.

Default: (empty; uses the browser settings)

UOM: pt (points, i.e. 1/72 inch)

### `phpwarnings`
**PHP Version Messages in Main Page**

Should I display the PHP version messages (End of Life, approaching End of Life, out of date) in the main page of the application?

Default: true

## Automation

Panopticon uses automation to keep track of what is going on with your sites, and perform any administrative work on them (e.g. update Joomla and extensions). These options control how the automation works.

### `webcron_key`
**Web CRON key**

This key must be provided in the Web CRON URL for it to work.

Default: (auto generated during installation)

### `cron_stuck_threshold`
**Stuck Task Threshold**

After how many minutes is a task considered to be “stuck”. Must be _at least_ 3 minutes.

Default: 3

UOM: Minutes

### `max_execution`
**Maximum Execution Time**

The maximum time allowed for task execution.

Default: 60

UOM: Seconds

### `execution_bias`
**Execution Time Bias**

When the current execution time exceeds this percentage of the Maximum Execution Time we will not try to execute another task to avoid a timeout.

Default: 75

UOM: %

## Site Operations

Configuration for the automation and administrative tasks in Panopticon.

### `siteinfo_freq`
**Site information update frequency**

The site information (Joomla version, update availability, PHP version) will be automatically updated after at least this many minutes since the last time.

Default: 60

UOM: minutes

Range: 15 to 1440

## Caching

Panopticon uses caching to avoid repeating the same time-consuming operations. These options control how caching works.

### `caching_time`
**Cache Time**

How long to cache data by default. Individual features may use a different cache time.

Default: 60

UOM: Minutes

Range: 1 to 527040 (one minute to one year)

### `cache_adapter`
**Cache Adapter**

Where will the cached items be stored?

Default: filesystem

Valid settings:
* `filesystem` (Files) Files in the `cache` directory. Safest and slowest option.
* `linuxfs` (Files and Symlinks) Files and symlinks in the `cache` directory. Only usable on Linux and macOS, as long as PHP can create symlinks.
* `db` (Database) Uses the database table `#__cache` in your database (it's created on the fly). If your `dbdriver` configuration option is anything other than `pdomysql` you will have two or more concurrent database connections to your database server per execution thread which might be problematic for some servers.
* `memcached` (memcached) Use a memcached server. Requires the PHP `memcached` extension. Note that Panopticon only supports using a single server. If you want to use a cluster you'll have to override the `cacheFactory` service in the container using user-provided code. 
* `redis` (Redis) Use a Redis server. Requires the PHP `redis` extension. Note that Panopticon only supports using a single server. If you want to use a cluster you'll have to override the `cacheFactory` service in the container using user-provided code.

### `caching_redis_dsn`
**Redis Data Source Name (DSN)**

How to connect to the Redis server. See [the Symfony Cache Redis adapter documentation](https://symfony.com/doc/current/components/cache/adapters/redis_adapter.html#configure-the-connection). Required when `cache_adapter` is set to `redis`.

Default: (none)

### `caching_memcached_dsn`
**Memcached Data Source Name (DSN)**

How to connect to the Memcached server. See [the Symfony Cache Memcached adapter documentation](https://symfony.com/doc/current/components/cache/adapters/memcached_adapter.html#configure-the-connection). Required when `cache_adapter` is set to `memcached`.

Default: (none)

## Logging

Panopticon keeps a log of its actions. You can choose how the log works.

### `log_level`
**Minimum log level**

What is the minimum severity level for messages to be kept in the logs. Please note that enabling Debug System will always result in all messages to be logged, as if you had set this option to Debug.

Default: warning

Valid settings: `emergency`, `alert`, `critical`, `error`, `warning`, `notice`, `info`, `debug`

### `log_rotate_compress`
**Compress rotated logs**

Should the log files which have been rotated be compressed with GZip?

Default: true

### `log_rotate_files`
**Rotated log files to keep**

How many rotated log files should I keep?

Default: 3

Range: 0 to 100

### `log_backup_threshold`
**Backup log files deletion after this many days**

Backup log files will be deleted, instead of rotated, after this many days. 0 means keep forever (NOT RECOMMENDED!).

Default: 14

Range: 0 to 65535 (that is almost 179 1/2 years…)

## Database

Panopticon uses a MySQL database to store its information. You are advised not to change this configuration after installing Panopticon unless you know what you're doing and have a _very_ good reason to do it.

### `dbdriver`
**Database Driver**

The PHP MySQL database driver to use

Default: mysqli

Valid settings: `mysqli`, `pdomysql`

### `dbhost`
**Database Hostname**

The hostname of the MYSQL database

Default: localhost

### `dbuser`
**Database Username**

The username to connect to your database

Default:

### `dbpass`
**Database Password**

The password to connect to your database

Default:

### `dbname`
**Database Name**

The name of the MySQL database

Default:

### `dbprefix`
**Database Prefix**

A naming prefix to use for Akeeba Panopticon tables. Ideally 2 to 5 characters long, followed by an underscore.

Default: ak_

### `dbcharset`
**Database Connection Character Set**

Hidden. Only applies to the `pdomysql` driver.

The character set of the connection to the database. This must always be `utf8mb4` on all supported database server versions.

Default: `utf8mb4`

### `dbencryption`
**Database Encryption**

Should I use an encrypted connection to the MySQL database server?

Default: false

### `dbsslca`
**Path to the SSL/TLS CA certificate**

Absolute path to the SSL CA for encrypted database connections

Default: Empty (Uses the default Certification Authority store configured in PHP itself)

### `dbsslkey`
**Path to the SSL/TLS key file**

Absolute path to the SSL/TLS key file (PEM format) for encrypted database connections

Default:

### `dbsslcert`
**Path to the SSL/TLS certificate file**

Absolute path to the SSL/TLS certificate file (PEM format) for encrypted database connections

Default:

### `dbsslverifyservercert`
**Verify SSL/TLS Server Certificates**

Should I verify the SSL/TLS server certificates against the SSL/TLS CA?

Default: true

## Email

Tells Panopticon how to send emails

### `mail_online`
**Mail Sending**

Is Panopticon allowed to send email? 

Default: false

### `mail_inline_images`
**Inline Images in Email**

Should Panopticon try to attach images in the emails it sends? When disabled the images will be linked to. When enabled the images are included in the email as inline attachments.

Default: false

### `mailer`
**Mail handler**

How will Panopticon send emails?

One of:
* `smtp` Use the configured SMTP server.
* `sendmail` Use `sendmail`, as configured in PHP (see [`sendmail_path`](https://www.php.net/manual/en/mail.configuration.php#ini.sendmail-path)).
* `mail` Use the built-in PHP `mail()` function. For its configuration please consult [PHP's Mail configuration options page](https://www.php.net/manual/en/mail.configuration.php#ini.sendmail-path).

Default: mail

### `mailfrom`
**Sender address**

The sender email address for any email sent by Panopticon.

Default: (blank; must be configured)

### `fromname`
**Sender name**

The sender email name for any email sent by Panopticon.

Default: "Panopticon"

### `smtphost`
**SMTP Host**

Only when you use the SMTP mail handler. The host name of your SMTP server, e.g. `mail.example.com`

Default: `localhost`

### `smtpport`
**SMTP Port**

Only when you use the SMTP mail handler. The TCP/IP port used to connect to your SMTP server. Usual ports are 25 (unencrypted SMTP), 587 (SMTP over TLS), and 465 (SMTP over SSL).

Default: 25

### `smtpsecure`
**SMTP Security**

Only when you use the SMTP mail handler. Should an encryption method be applied when contacting your SMTP server?

One of:
* `none` No security. Usernames, passwords, and the emails themselves are transmitted unencrypted to the SMTP server. Not recommended.
* `ssl` Use SMTP over SSL. The SSL encryption standard has been obsolete since 1996. Some odd hosts may still use it. Not recommended.
* `tls` Use SMTP over TLS. The most modern encryption standard, used by most commercial hosts.

Default: `none`

### `smtpauth`
**SMTP Authentication**

Only when you use the SMTP mail handler. Does your SMTP server require authentication?

Default: false

### `smtpuser`
**SMTP Username**

Only when you use the SMTP mail handler and SMTP Authentication is enabled. The username to connect to your SMTP server. Usually it's the same as your email address.

Default: (blank)

### `smtppass`
**SMTP Password**

Only when you use the SMTP mail handler and SMTP Authentication is enabled. The password to connect to your SMTP server. Usually it's the same as the password you use to receive email from the same address.

Default: (blank)

