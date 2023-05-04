# Configuration parameters

The following are the known application configuration options which can be set in `config.php`.

## System

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

Default: false

### `finished_setup`
**Have we finished installing up the application?**

_Hidden option._

This is automatically set at the end of the initial installation, after the CRON job configuration has been confirmed
(or the user chose to skip it).

## Caching

### `caching_time`
**Cache Time**

How long to cache the collected site information and other generated data.

Default: 60

UOM: Minutes

Range: 1 to 527040 (one minute to one year)

### `cache_adapter`
**Cache Adapter**

Where will the cached items be stored?

Default: filesystem

Valid settings:
* `filesystem` Files in the `cache` directory. Safest and slowest option.
* `linuxfs` Files and symlinks in the `cache` directory. Only usable on Linux and macOS, as long as PHP can create symlinks.
* `db` Uses the database table `#__cache` in your database (it's created on the fly). If your `dbdriver` configuration option is anything other than `pdomysql` you will have two or more concurrent database connections to your database server per execution thread which might be problematic for some servers.
* `memcached` Use a memcached server. Requires the PHP `memcached` extension. Note that Panopticon only supports using a single server. If you want to use a cluster you'll have to override the `cacheFactory` service in the container using user-provided code. 
* `redis` Use a Redis server. Requires the PHP `redis` extension. Note that Panopticon only supports using a single server. If you want to use a cluster you'll have to override the `cacheFactory` service in the container using user-provided code.

### `caching_redis_dsn`
**Redis Data Source Name (DSN)**

How to connect to the Redis server. See [the Symfony Cache Redis adapter documentation](https://symfony.com/doc/current/components/cache/adapters/redis_adapter.html#configure-the-connection). Required when `cache_adapter` is set to `redis`.

Default: (none)

### `caching_memcached_dsn`
**Memcached Data Source Name (DSN)**

How to connect to the Memcached server. See [the Symfony Cache Memcached adapter documentation](https://symfony.com/doc/current/components/cache/adapters/memcached_adapter.html#configure-the-connection). Required when `cache_adapter` is set to `memcached`.

Default: (none)

## Display preferences

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

## Logging

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
**Rotated log files**

How many rotated log files should I keep?

Default: 3

Range: 0 to 100

### `log_backup_threshold`
**Backup log files deletion after this many days**

Backup log files will be deleted, instead of rotated, after this many days. 0 means keep forever (NOT RECOMMENDED!).

Default: 14

Range: 0 to 65535 (that is almost 179 1/2 years…)

## Task handling

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

## Database

### `dbdriver`
**Database Driver**

The PHP MYSQL database driver to use

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
