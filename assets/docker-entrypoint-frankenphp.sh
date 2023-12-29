#!/usr/bin/bash

# Initialise the application if config.php does not exist
if [[ ! -f "/app/public/config.php" ]]
then
	echo "  __"
	echo " /         /"
	echo "(___  ___ (___       ___"
	echo "    )|___)|    |   )|   )"
	echo " __/ |__  |__  |__/ |__/"
	echo "                    |"
	# All files and folders must be owned by panopticon (our custom user Apache runs under)
	chown -R panopticon *
	# Create a config.php file
	su panopticon -c "/usr/local/bin/php /app/public/cli/panopticon.php config:create --driver mysqli --host \"$PANOPTICON_DB_HOST\" --user \"$MYSQL_USER\" --password \"$MYSQL_PASSWORD\" --name \"$MYSQL_DATABASE\" --prefix \"$PANOPTICON_DB_PREFIX\""
	# Create the database tables
	su panopticon -c "/usr/local/bin/php /app/public/cli/panopticon.php database:update"
	# Create an admin user
	su panopticon -c "php /app/public/cli/panopticon.php user:create --username=\"$ADMIN_USERNAME\" --password=\"$ADMIN_PASSWORD\" --name \"$ADMIN_NAME\" --email=\"$ADMIN_EMAIL\" --overwrite"
	# Set up the maximum CLI execution time
	su panopticon -c "php /app/public/cli/panopticon.php config:set max_execution 180"
	# Mark the installation as complete
	su panopticon -c "php /app/public/cli/panopticon.php config:set finished_setup true"
	# Install a CRON job
	crontab -u panopticon -l > mycron
	echo "* * * * * /usr/local/bin/php /app/public/cli/panopticon.php task:run --loop" >> mycron
	crontab -u panopticon mycron
	rm mycron
else
	# Update the database structure
	su panopticon -c "/usr/local/bin/php /app/public/cli/panopticon.php database:update"
fi

# Start the CRON daemon
service cron start

# Make sure our user can write to Caddy files
chown panopticon:panopticon -R /data/caddy
chown panopticon:panopticon -R /config/caddy

# Start FrankenPHP
su panopticon -c "frankenphp run --config /etc/caddy/Caddyfile"