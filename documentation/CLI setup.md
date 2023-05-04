# CLI setup

Let's the installation down to discrete steps.

## Create a configuration file

Before you start installing Akeeba Panopticon you need to create a MySQL database, and a user which can access it. The database user needs to have the following privileges on this database: ALTER, CREATE, CREATE TEMPORARY TABLES, CREATE VIEW, DELETE, DROP, EXECUTE, INDEX, INSERT, LOCK TABLES, REFERENCES, SELECT, SHOW VIEW, UPDATE.

Then, you need to run the following command:

```bash
php cli/panopticon.php config:create --driver mysqli --host localhost --user USER --pass PASS --name DBNAME \
    --prefix "ak_"
```

The `driver` can be one of `mysqli` (using the PHP `mysqli` / `mysqlnd` extension) or `pdomysql` (using the PHP `pdo` extension).

The rest of the parameters are your database connection parameters.

The `prefix` must be one to five **lowercase** alphanumeric characters (a-z, 0-9) followed by an underscore. It does not have any special meaning. It's used to allow Panopticon to be installed alongside other software sharing the same database.

Upon successful execution of this command the `config.php` file will be created in Panopticon's installation root.

## Create the database table

Run the command

```bash
php cli/panopticon.php database:update
```

This will automatically connect to the database and create the necessary database tables.

**Tip**: You can use the same command after updating Panopticon to update the database tables, if necessary.

## Create an administrator user

You need to create a user to access Akeeba Panopticon's web interface. For example:

```bash
php cli/panopticon.php user:create --username=admin --password="MyP@s5w0rD" --name "Super Administrator" --email="foo@example.com"
```

**Tip**: If you ommit the `--password` parameter you will be asked to type in your password.

## Update the CRON jobs timing settings

Assuming that you will be running the Panopticon CRON jobs from a CLI context, run the following command:

```bash
php cli/panopticon.php config:maxtime:test
```

You will then need to edit config.php and set

```injectablephp
	public $max_execution = 180;
```

the number being the maximum number of seconds reported by the previous command.