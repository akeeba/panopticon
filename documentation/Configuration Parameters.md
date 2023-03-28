# Configuration parameters

## System

### `session_timeout`
Session Timeout
How long is a login session valid for
Default: 1440
UOM: Minutes

### `timezone`
Time Zone
The timezone to use for displaying information in the interface
Default: UTC

## Task handling

### `cron_stuck_threshold`
Stuck Task Threshold
After how many minutes is a task considered to be “stuck”. Must be _at least_ 3 minutes.
Default: 3
UOM: Minutes

### `max_execution`
Maximum Execution Time
The maximum time allowed for task execution.
Default: 60
UOM: Seconds

### `execution_bias`
Execution Time Bias
When the current execution time exceeds this percentage of the Maximum Execution Time we will not try to execute another task to avoid a timeout. 
Default: 75
UOM: %

## Database

### `dbdriver`
Database Driver
The PHP MYSQL database driver to use
Default: mysqli
Allowed: mysqli, pdomysql

### `dbhost`
Database Hostname
The hostname of the MYSQL database
Default: localhost

### `dbuser`
Database Username
The username to connect to your database
Default:

### `dbpass`
Database Password
The password to connect to your database
Default:

### `dbname`
Database Name
The name of the MySQL database
Default:

### `dbprefix`
Database Prefix
A naming prefix to use for Akeeba Panopticon tables. Ideally 2 to 5 characters long, followed by an underscore.
Default: ak_

### `dbencryption`
Database Encryption
Should I use an encrypted connection to the MySQL database server? 
Default: false

### `dbsslca`

Default:

### `dbsslkey`

Default:

### `dbsslcert`

Default:

### `dbsslverifyservercert`

Default: 
