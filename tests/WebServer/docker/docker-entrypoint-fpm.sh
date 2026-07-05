#!/usr/bin/env bash
#
# Entrypoint for the Apache 2.4 + PHP-FPM 8.x web-server MCP integration test image.
# Provisions the database and admin user, then starts PHP-FPM (background) and Apache
# (foreground). Configuration comes from environment variables (PANOPTICON_USING_ENV=1),
# so there is no config.php and no setup wizard.
#
set -euo pipefail

APP_ROOT=/var/www/html
cd "$APP_ROOT"

# config.php mode is supported for completeness, but the test compose uses env-var mode.
if [ "${PANOPTICON_USING_ENV:-0}" != "1" ] && [ ! -f "$APP_ROOT/config.php" ]; then
	echo "[entrypoint] Creating config.php ..."
	php cli/panopticon.php config:create --driver mysqli --host "$PANOPTICON_DB_HOST" \
		--user "$MYSQL_USER" --password "$MYSQL_PASSWORD" --name "$MYSQL_DATABASE" \
		--prefix "$PANOPTICON_DB_PREFIX"
	php cli/panopticon.php config:set finished_setup true
fi

echo "[entrypoint] Updating the database schema ..."
for i in $(seq 1 30); do
	if php cli/panopticon.php database:update; then
		break
	fi
	echo "[entrypoint] Database not ready yet, retrying ($i/30) ..."
	sleep 2
done

echo "[entrypoint] Ensuring the admin user exists ..."
# NOTE: user:create reports FAILURE (non-zero exit) even on success, because AWF's
# Manager::saveUser() returns void and the command treats that as falsy. The user IS created,
# so we tolerate the exit code here (matching the stock assets/docker-entrypoint.sh behaviour).
php cli/panopticon.php user:create --username="$ADMIN_USERNAME" --password="$ADMIN_PASSWORD" \
	--name "$ADMIN_NAME" --email="$ADMIN_EMAIL" --overwrite \
	|| echo "[entrypoint] user:create returned non-zero (cosmetic; the user is still created)."

# PHP-FPM workers run as www-data; make sure they can write cache/tmp/log.
echo "[entrypoint] Fixing ownership for the web-server user ..."
chown -R www-data:www-data "$APP_ROOT"

echo "[entrypoint] Starting PHP-FPM ..."
php-fpm -D

echo "[entrypoint] Starting Apache in the foreground ..."
# apache2ctl sources /etc/apache2/envvars itself; we must not source it here because it
# references unset variables that would trip `set -u`.
exec apache2ctl -D FOREGROUND
