# CLI setup

To set up Panopticon from the CLI you need to run the following commands:

```bash
php cli/panopticon.php config:create --driver mysqli --host localhost --user USER --pass PASS --name DBNAME \
    --prefix "ak_"
php cli/panopticon.php database:update
php cli/panopticon.php user:create --username=admin --name "Super Administrator"
```