#!/usr/bin/env bash
#
# @package   panopticon
# @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
# @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
#
# Builds the working copy with `phing git` and then runs the PHPUnit suite.
#
# Any extra arguments are passed through verbatim to `vendor/bin/phpunit`, e.g.:
#   tests/run-tests.sh --testsuite=unit
#   tests/run-tests.sh --filter=ApitokenTest
#
set -uo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR" || exit 1

if [[ -t 1 ]]; then
	COLOR_RED=$'\033[31m'
	COLOR_GREEN=$'\033[32m'
	COLOR_YELLOW=$'\033[33m'
	COLOR_BOLD=$'\033[1m'
	COLOR_RESET=$'\033[0m'
else
	COLOR_RED=''
	COLOR_GREEN=''
	COLOR_YELLOW=''
	COLOR_BOLD=''
	COLOR_RESET=''
fi

step()
{
	echo "${COLOR_BOLD}==>${COLOR_RESET} $1"
}

ok()
{
	echo "${COLOR_GREEN}  OK${COLOR_RESET}  $1"
}

warn()
{
	echo "${COLOR_YELLOW}WARN${COLOR_RESET}  $1"
}

abort()
{
	echo "${COLOR_RED}FAIL${COLOR_RESET}  $1" >&2

	if [[ -n "${2:-}" ]]; then
		echo "" >&2
		echo "${COLOR_YELLOW}Suggestion:${COLOR_RESET} $2" >&2
	fi

	exit 1
}

# -- 1. Sanity-check the environment before doing anything expensive -----------------------------

step "Checking working copy"

if [[ ! -f "$ROOT_DIR/build.xml" ]]; then
	abort "build.xml not found at the repository root ($ROOT_DIR)." \
		"Run this script from within the panopticon working copy, or check out the repository again."
fi

ok "build.xml found"

step "Checking PHP"

if ! command -v php >/dev/null 2>&1; then
	abort "The php CLI binary was not found in PATH." \
		"Install PHP 8.3 or later and make sure it's on your PATH."
fi

PHP_VERSION="$(php -r 'echo PHP_VERSION;')"

if ! php -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);'; then
	abort "PHP $PHP_VERSION was found, but Panopticon requires PHP 8.3 or later." \
		"Install/switch to PHP 8.3+ (e.g. via phpenv, Homebrew, or your system package manager)."
fi

ok "PHP $PHP_VERSION"

for ext in mysqli mbstring json; do
	if ! php -r "exit(extension_loaded('$ext') ? 0 : 1);"; then
		abort "PHP reports extension_loaded('$ext') === false for '$(command -v php)' (PHP $PHP_VERSION, ini: $(php -r 'echo php_ini_loaded_file() ?: "(none)";'))." \
			"Enable it in the ini file shown above (extension=$ext), or in whichever conf.d file your build expects, then re-run this script."
	fi
done

ok "Required PHP extensions present (mysqli, mbstring, json)"

step "Checking Composer"

if ! command -v composer >/dev/null 2>&1; then
	abort "The composer CLI binary was not found in PATH." \
		"Install Composer: https://getcomposer.org/download/"
fi

ok "composer found"

step "Checking Phing"

PHING_BIN=""

if command -v phing >/dev/null 2>&1; then
	PHING_BIN="phing"
elif [[ -x "$HOME/.config/composer/vendor/bin/phing" ]]; then
	PHING_BIN="$HOME/.config/composer/vendor/bin/phing"
elif [[ -x "$HOME/.composer/vendor/bin/phing" ]]; then
	PHING_BIN="$HOME/.composer/vendor/bin/phing"
fi

if [[ -z "$PHING_BIN" ]]; then
	abort "phing was not found in PATH or in the usual global Composer bin directories." \
		"Install it globally with: composer global require phing/phing"
fi

ok "phing found ($PHING_BIN)"

if [[ ! -d "$HOME/Projects/akeeba/buildfiles" ]]; then
	warn "~/Projects/akeeba/buildfiles not found — build.xml imports common.xml from there."
	echo "        If 'phing git' fails to find common.xml, check out the buildfiles repository at ~/Projects/akeeba/buildfiles."
fi

# -- 2. Build the working copy with phing git -----------------------------------------------------

step "Running 'phing git' (composer install, npm install, SCSS/JS build)"

PHING_LOG="$(mktemp)"
trap 'rm -f "$PHING_LOG"' EXIT

if ! "$PHING_BIN" git 2>&1 | tee "$PHING_LOG"; then
	echo ""
	abort "'phing git' failed — see the build output above for details." \
		"Common causes: missing/misconfigured ~/Projects/build.properties, no network access for composer/npm, or a broken node_modules/vendor tree. Try running '$PHING_BIN git' directly to see the full output."
fi

ok "phing git completed"

# -- 3. Verify the test environment is ready -------------------------------------------------------

# 'phing git' runs composer-install with the akeeba/buildfiles default composer.dev_argument=--no-dev
# (production-like package build), which strips require-dev packages such as phpunit. Restore them
# with a plain, full 'composer install' before running the suite.
step "Restoring Composer dev dependencies (phing git installs with --no-dev)"

if ! composer install 2>&1 | tee -a "$PHING_LOG"; then
	echo ""
	abort "'composer install' failed — see the output above for details." \
		"Check network access and that composer.lock is consistent with composer.json."
fi

ok "composer install completed"

if [[ ! -x "$ROOT_DIR/vendor/bin/phpunit" ]]; then
	abort "vendor/bin/phpunit still not found after 'composer install'." \
		"Check that phpunit/phpunit is present under require-dev in composer.json, and that composer install actually completed successfully above."
fi

ok "vendor/bin/phpunit present"

step "Checking tests/.env.test"

ENV_TEST="$ROOT_DIR/.env.test"
ENV_TEST_EXAMPLE="$ROOT_DIR/.env.test.example"

if [[ ! -f "$ENV_TEST" ]]; then
	warn ".env.test not found — integration tests need it (unit tests will still run)."

	if [[ -f "$ENV_TEST_EXAMPLE" ]]; then
		echo "        A template exists at .env.test.example. Create your copy with:"
		echo ""
		echo "          cp .env.test.example .env.test"
		echo "          \$EDITOR .env.test   # set PANOPTICON_DBNAME/DBUSER/DBPASS and PANOPTICON_SECRET"
		echo ""
		echo "        Generate a secret with: openssl rand -hex 32"
	else
		warn ".env.test.example is also missing — see tests/TESTING.md for the required keys."
	fi
else
	ok ".env.test present"

	# Light sanity checks only — tests/bootstrap.php is the authority on DB-name collisions.
	if ! grep -qE '^PANOPTICON_DBNAME=.+' "$ENV_TEST"; then
		warn "PANOPTICON_DBNAME looks empty in .env.test — integration tests will refuse to run."
	fi

	SECRET_LINE="$(grep -E '^PANOPTICON_SECRET=' "$ENV_TEST" || true)"

	if [[ -z "$SECRET_LINE" || "$SECRET_LINE" == "PANOPTICON_SECRET=" ]]; then
		warn "PANOPTICON_SECRET looks empty in .env.test — token-auth integration tests will fail."
		echo "        Generate one with: openssl rand -hex 32"
	fi

	CONFIG_PHP="$ROOT_DIR/config.php"

	if [[ -f "$CONFIG_PHP" ]]; then
		TEST_DBNAME="$(grep -E '^PANOPTICON_DBNAME=' "$ENV_TEST" | head -n1 | cut -d= -f2-)"
		PROD_DBNAME="$(php -r '
			$configPath = $argv[1];
			require $configPath;
			echo class_exists("AConfig", false) ? (new AConfig())->dbname ?? "" : "";
		' "$CONFIG_PHP" 2>/dev/null || true)"

		if [[ -n "$TEST_DBNAME" && -n "$PROD_DBNAME" && "$TEST_DBNAME" == "$PROD_DBNAME" ]]; then
			abort "PANOPTICON_DBNAME in .env.test ($TEST_DBNAME) matches config.php's dbname." \
				"Point .env.test at a dedicated throwaway test database, never your dev/prod one."
		fi
	fi
fi

# -- 4. Run the test suite --------------------------------------------------------------------------

step "Running the PHPUnit suite"

if ! "$ROOT_DIR/vendor/bin/phpunit" "$@"; then
	echo ""
	abort "PHPUnit reported failures — see the output above." \
		"For integration-test-only failures, double-check .env.test (dedicated DB reachable, PANOPTICON_SECRET set) per tests/TESTING.md. Re-run a single case with: vendor/bin/phpunit --filter=<TestName>"
fi

ok "All tests passed"
