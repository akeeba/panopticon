#!/usr/bin/env bash
#
# Entrypoint of the bare installation lab. It only starts the servers; the lab installs nothing by itself.
set -euo pipefail

mkdir -p /var/log/php
touch /var/log/php/error.log
chown -R www-data:www-data /var/log/php

php-fpm -D
exec apache2ctl -D FOREGROUND
