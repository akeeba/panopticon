#!/usr/bin/env bash
#
# Orchestrator for the web-server MCP integration test tier.
#
# Brings up a real Apache 2.4 + PHP container (PHP-FPM by default) with the MCP server enabled,
# mints an API token inside it, then runs the PHPUnit `webserver` suite against it over HTTP.
#
# By design this runs ONLY AFTER the main test suite passes: it invokes `composer test` first and
# aborts if that fails (skip with --no-pretest, e.g. in a CI job that already ran the suite).
#
# Usage:
#   tests/WebServer/run.sh [options]
#     --sapi=fpm|modphp        PHP SAPI to test (default: fpm)
#     --php-version=X.Y        PHP version tag to build (default: 8.4)
#     --port=NNNN              Host port mapped to Apache :80 (default: 4290)
#     --no-pretest             Skip the `composer test` gate
#     --keep                   Leave the container running after the tests (for debugging)
#
set -euo pipefail

# --- Resolve paths ----------------------------------------------------------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
COMPOSE_FILE="$SCRIPT_DIR/docker-compose.webserver.yml"
CONTAINER="panopticon_webtest_php"

# --- Defaults & argument parsing -------------------------------------------------------------
SAPI="fpm"
PHP_VERSION="8.4"
HTTP_PORT="4290"
RUN_PRETEST=1
KEEP=0

for arg in "$@"; do
	case "$arg" in
		--sapi=*)        SAPI="${arg#*=}" ;;
		--php-version=*) PHP_VERSION="${arg#*=}" ;;
		--port=*)        HTTP_PORT="${arg#*=}" ;;
		--no-pretest)    RUN_PRETEST=0 ;;
		--keep)          KEEP=1 ;;
		*)
			echo "Unknown option: $arg" >&2
			exit 2
			;;
	esac
done

# --- Validate inputs (a 'works on garbage' harness costs hours of debugging) ------------------
case "$SAPI" in
	fpm)    DOCKERFILE="tests/WebServer/docker/Dockerfile.fpm" ;;
	modphp) DOCKERFILE="tests/WebServer/docker/Dockerfile.modphp" ;;
	*)
		echo "Invalid --sapi '$SAPI' (expected 'fpm' or 'modphp')." >&2
		exit 2
		;;
esac

if [[ ! "$PHP_VERSION" =~ ^[0-9]+\.[0-9]+$ ]]; then
	echo "Invalid --php-version '$PHP_VERSION' (expected e.g. 8.4)." >&2
	exit 2
fi

if [[ ! "$HTTP_PORT" =~ ^[0-9]+$ ]] || (( HTTP_PORT < 1 || HTTP_PORT > 65535 )); then
	echo "Invalid --port '$HTTP_PORT'." >&2
	exit 2
fi

for tool in docker curl; do
	if ! command -v "$tool" >/dev/null 2>&1; then
		echo "Required tool '$tool' is not installed." >&2
		exit 2
	fi
done

BASE_URL="http://localhost:${HTTP_PORT}"
MCP_URL="${BASE_URL}/index.php/mcp"

echo "==> Web-server MCP test: SAPI=$SAPI, PHP=$PHP_VERSION, port=$HTTP_PORT"

# --- Gate: the main suite must pass first ----------------------------------------------------
if [[ "$RUN_PRETEST" -eq 1 ]]; then
	echo "==> Running the main test suite first (composer test) ..."
	( cd "$ROOT_DIR" && composer test )
	echo "==> Main test suite passed."
else
	echo "==> Skipping the composer test gate (--no-pretest)."
fi

# --- Export compose configuration ------------------------------------------------------------
export PANOPTICON_TEST_DOCKERFILE="$DOCKERFILE"
export PANOPTICON_TEST_PHP_VERSION="$PHP_VERSION"
export PANOPTICON_TEST_HTTP_PORT="$HTTP_PORT"

compose() { docker compose -f "$COMPOSE_FILE" "$@"; }

cleanup() {
	if [[ "$KEEP" -eq 1 ]]; then
		echo "==> Leaving the stack running (--keep). Tear it down with:"
		echo "    docker compose -f \"$COMPOSE_FILE\" down -v --remove-orphans"
		return
	fi
	echo "==> Tearing down the stack ..."
	compose down -v --remove-orphans >/dev/null 2>&1 || true
}
trap cleanup EXIT

# --- Bring up the stack ----------------------------------------------------------------------
echo "==> Building and starting the container stack ..."
compose up -d --build

# --- Wait until the MCP endpoint is answering (401 == app up, DB migrated, MCP enabled) ------
echo "==> Waiting for the MCP endpoint to come up ..."
READY=0
for i in $(seq 1 90); do
	code="$(curl -s -o /dev/null -w '%{http_code}' -X POST \
		-H 'Content-Type: application/json' \
		-d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' \
		"$MCP_URL" || true)"

	if [[ "$code" == "401" ]]; then
		READY=1
		break
	fi

	sleep 2
done

if [[ "$READY" -ne 1 ]]; then
	echo "!! MCP endpoint did not return 401 in time (last code: ${code:-none}). Container logs:" >&2
	compose logs php >&2 || true
	exit 1
fi
echo "==> Endpoint is up."

# --- Mint an API token inside the container --------------------------------------------------
echo "==> Minting an API token ..."
TOKEN="$(docker exec "$CONTAINER" php cli/panopticon.php token:create --username=admin 2>/dev/null | tr -d '\r\n')"

if [[ -z "$TOKEN" ]]; then
	echo "!! Failed to mint an API token." >&2
	compose logs php >&2 || true
	exit 1
fi

# --- Baseline: no .htaccess ------------------------------------------------------------------
docker exec "$CONTAINER" rm -f /var/www/html/.htaccess

# --- Run the PHPUnit web-server suite --------------------------------------------------------
echo "==> Running the PHPUnit web-server suite ..."
export PANOPTICON_TEST_BASE_URL="$BASE_URL"
export PANOPTICON_TEST_TOKEN="$TOKEN"
export PANOPTICON_TEST_CONTAINER="$CONTAINER"
export PANOPTICON_TEST_SAPI="$SAPI"

set +e
( cd "$ROOT_DIR" && vendor/bin/phpunit -c phpunit.webserver.xml )
STATUS=$?
set -e

if [[ "$STATUS" -eq 0 ]]; then
	echo "==> Web-server MCP tests passed."
else
	echo "!! Web-server MCP tests failed (exit $STATUS)."
fi

exit "$STATUS"
