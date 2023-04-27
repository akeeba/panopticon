# Configuration parameters

The following are the known application configuration options which can be set in `config.php`.

## System

### `session_timeout`
**Session Timeout**

How long is a login session valid for

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
