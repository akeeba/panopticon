#!/usr/bin/env bash
#
# Entrypoint for the Apache 2.4 + mod_php web-server MCP integration test image (the
# secondary, non-default SAPI). Provisions the database and admin user, then runs Apache
# in the foreground. Configuration comes from environment variables (PANOPTICON_USING_ENV=1).
#
set -euo pipefail

APP_ROOT=/var/www/html
cd "$APP_ROOT"

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

# Apache runs as the "panopticon" user; make sure it can write cache/tmp/log.
echo "[entrypoint] Fixing ownership for the web-server user ..."
chown -R panopticon:panopticon "$APP_ROOT"

echo "[entrypoint] Starting Apache in the foreground ..."
exec apache2-foreground
