#!/usr/bin/bash

echo "Initialising the Panopticon container runtime environment."

# Check whether I am running under FrankenPHP
if [ -d /app/public ]
then
  echo "This is the FrankenPHP container variant"
  # FrankenPHP
  export PANOPTICON_ROOT_FOLDER=/app/public

  # Make sure our user can write to Caddy files
  chown panopticon:panopticon -R /data/caddy
  chown panopticon:panopticon -R /config/caddy
else
  echo "This is the Apache container variant"
  # Apache
  export PANOPTICON_ROOT_FOLDER=/var/www/html
fi

# All files and folders must be owned by panopticon (our custom user Apache runs under)
chown -R panopticon *

# Initialise the application if config.php does not exist
if [ "$PANOPTICON_USING_ENV" -ne 1 -a ! -f "config.php" ]
then
	echo "  __"
	echo " /         /"
	echo "(___  ___ (___       ___"
	echo "    )|___)|    |   )|   )"
	echo " __/ |__  |__  |__/ |__/"
	echo "                    |"
	# Create a config.php file
  su panopticon -c "/usr/local/bin/php $PANOPTICON_ROOT_FOLDER/cli/panopticon.php config:create --driver mysqli --host \"$PANOPTICON_DB_HOST\" --user \"$MYSQL_USER\" --password \"$MYSQL_PASSWORD\" --name \"$MYSQL_DATABASE\" --prefix \"$PANOPTICON_DB_PREFIX\""
  # Set up the maximum CLI execution time
  su panopticon -c "php $PANOPTICON_ROOT_FOLDER/cli/panopticon.php config:set max_execution 180"
  # Mark the installation as complete
  su panopticon -c "php $PANOPTICON_ROOT_FOLDER/cli/panopticon.php config:set finished_setup true"
fi

echo "Create or update database structure..."
su panopticon -c "/usr/local/bin/php $PANOPTICON_ROOT_FOLDER/cli/panopticon.php database:update"
echo "Create an admin user (if necessary)..."
su panopticon -c "php $PANOPTICON_ROOT_FOLDER/cli/panopticon.php user:create --username=\"$ADMIN_USERNAME\" --password=\"$ADMIN_PASSWORD\" --name \"$ADMIN_NAME\" --email=\"$ADMIN_EMAIL\""
export PANOPTICON_FINISHED_SETUP=1

# Install or update the CRON jobs
echo "Installing CRON jobs"
crontab -u panopticon -l > mycron
for (( i=1; i<=$PANOPTICON_CRON_JOBS; i++ ))
do
  echo "* * * * * /usr/local/bin/php $PANOPTICON_ROOT_FOLDER/cli/panopticon.php task:run --loop" >> mycron
done
crontab -u panopticon mycron
rm mycron

# Start the CRON daemon
service cron start

if [ -d /app/public ]
then
  echo "Starting Caddy with FrankenPHP"
  # Start FrankenPHP
  su panopticon -c "frankenphp run --config /etc/caddy/Caddyfile"
else
  echo "Starting Apache"
  # Start Apache
  exec apache2-foreground
fi