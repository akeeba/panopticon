#!/usr/bin/env bash
#
# Orchestrator of the new-installation release gate.
#
# Unzips a Panopticon release ZIP into a BARE Apache + PHP-FPM container, then walks the setup wizard in a real
# browser (Playwright, from ../buildfiles), once per combination of PHP version, database server and interface
# language. See docs/plans/2026-10-22-installation-test-design.md.
#
# It is NOT part of `composer test`: it is the slow release gate. Run it with `composer test:install`.
# Building the ZIP (`phing git-app`, only when there is none) rewrites vendor/ WITHOUT the dev dependencies and
# recompiles the language files. Run `composer dump-autoload --dev` (or `composer install`) before `composer test`
# afterwards.
#
# Usage:
#   tests/Installation/run.sh [options]       (composer test:install -- [options])
#     --zip=<path>              Test this ZIP. Default: the newest release/panopticon-*.zip, or build one
#     --php=X.Y                 Run only with this PHP version
#     --db=mysql:X.Y|mariadb:X.Y  Run only with this database server and version
#     --lang=de-DE,el-GR        Run only these languages (the others are reported, loudly)
#     --cron                    Run only the CRON benchmark path (en-GB unless --lang is given)
#     --saveconfig              Run only the manual config.php path (en-GB unless --lang is given)
#     --keep                    Leave the stack up after the last combination, for debugging
#     --down                    Tear down any leftover stack and exit
#     --headed                  Show the browser
#     --help                    This text
#
# With --php, --db, --lang, --cron or --saveconfig the matrix is replaced by exactly ONE combination. What is not
# given defaults to the newest PHP, the newest MySQL, all languages and the `normal` path.
#
set -euo pipefail

# --- Resolve paths ----------------------------------------------------------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
COMPOSE_FILE="$SCRIPT_DIR/docker/compose.yml"
BROWSER_DIR="$SCRIPT_DIR/browser"
RESULTS_DIR="$SCRIPT_DIR/results"
LAUNCHER="$ROOT_DIR/../buildfiles/playwright/run.mjs"
PROJECT="panopticon_installtest"
HTTP_PORT="${INSTALLTEST_PORT:-4291}"
BASE_URL="http://localhost:${HTTP_PORT}"

# --- The matrix bounds ------------------------------------------------------------------------
# PHP: the lowest version composer.json's require.php allows (">=8.3 <8.7" -> 8.3).
PHP_OLDEST="$(php -r '
	$c = json_decode(file_get_contents($argv[1]), true);
	echo preg_match("/>=\s*(\d+\.\d+)/", $c["require"]["php"] ?? "", $m) ? $m[1] : "";
' "$ROOT_DIR/composer.json" 2>/dev/null || true)"
# Newest stable PHP allowed by require.php; 8.6 is not released yet.
PHP_NEWEST="8.5"
# Documented minimums, see the wiki's Install-Panopticon page.
MYSQL_OLDEST="5.7"
MARIADB_OLDEST="10.3"
# Newest GA releases found on Docker Hub (docker manifest inspect) on 2026-10-22. "Newest" deliberately means the newest
# GA release, NOT the LTS (the LTS lines are MySQL 9.7 and MariaDB 12.3): breakage appears at the newest edge first.
# MySQL now numbers its releases YY.M. Re-check both when you review the matrix, and pin major.minor only.
MYSQL_NEWEST="26.10"
MARIADB_NEWEST="13.0"

# --- Defaults & argument parsing -------------------------------------------------------------
ZIP=""
OPT_PHP=""
OPT_DB=""
OPT_LANG=""
OPT_CRON=0
OPT_SAVECONFIG=0
KEEP=0
DOWN_ONLY=0
HEADED=0

usage() { sed -n '2,/^set -euo/p' "${BASH_SOURCE[0]}" | grep '^#' | sed 's/^# \{0,1\}//' ; }

for arg in "$@"; do
	case "$arg" in
		--zip=*)      ZIP="${arg#*=}" ;;
		--php=*)      OPT_PHP="${arg#*=}" ;;
		--db=*)       OPT_DB="${arg#*=}" ;;
		--lang=*)     OPT_LANG="${arg#*=}" ;;
		--cron)       OPT_CRON=1 ;;
		--saveconfig) OPT_SAVECONFIG=1 ;;
		--keep)       KEEP=1 ;;
		--down)       DOWN_ONLY=1 ;;
		--headed)     HEADED=1 ;;
		--help|-h)    usage; exit 0 ;;
		*)
			echo "Unknown option: $arg (see --help)" >&2
			exit 2
			;;
	esac
done

# --- Validate inputs --------------------------------------------------------------------------
version_le() { [[ "$(printf '%s\n%s\n' "$1" "$2" | sort -V | head -n1)" == "$1" ]]; }

if [[ -n "$OPT_PHP" ]]; then
	if [[ ! "$OPT_PHP" =~ ^[0-9]+\.[0-9]+$ ]]; then
		echo "Invalid --php '$OPT_PHP' (expected e.g. 8.4)." >&2
		exit 2
	fi
fi

if [[ -n "$OPT_DB" && ! "$OPT_DB" =~ ^(mysql|mariadb):[0-9]+\.[0-9]+$ ]]; then
	echo "Invalid --db '$OPT_DB' (expected mysql:X.Y or mariadb:X.Y, e.g. mysql:8.4)." >&2
	exit 2
fi

if [[ -n "$OPT_LANG" && ! "$OPT_LANG" =~ ^[a-z]{2,3}-[A-Z]{2}(,[a-z]{2,3}-[A-Z]{2})*$ ]]; then
	echo "Invalid --lang '$OPT_LANG' (expected a comma-separated list such as de-DE,el-GR)." >&2
	exit 2
fi

if [[ -n "$ZIP" ]]; then
	if [[ ! -f "$ZIP" || ! -r "$ZIP" ]]; then
		echo "Invalid --zip '$ZIP': not a readable file." >&2
		exit 2
	fi
	ZIP="$(cd "$(dirname "$ZIP")" && pwd)/$(basename "$ZIP")"
fi

# --- Compose helpers --------------------------------------------------------------------------
# Compose parses the lab's variables on every call, even `down`, so they always have to be set.
export INSTALLTEST_PORT="$HTTP_PORT"
export INSTALLTEST_PHP="${PHP_NEWEST}"
export INSTALLTEST_DB_IMAGE="mysql:${MYSQL_NEWEST}"
export INSTALLTEST_DB_PLATFORM=""
export INSTALLTEST_ZIP="${ZIP:-/dev/null}"

compose() { docker compose -f "$COMPOSE_FILE" -p "$PROJECT" "$@"; }

scrub() { compose down -v --remove-orphans >/dev/null 2>&1 || true; }

if ! docker compose version >/dev/null 2>&1; then
	echo "Docker with the Compose v2 plugin is required ('docker compose version' failed)." >&2
	echo "Install Docker Desktop, or the docker-compose-plugin package, and start the daemon." >&2
	exit 2
fi

if [[ "$DOWN_ONLY" -eq 1 ]]; then
	echo "==> Tearing down any $PROJECT stack ..."
	scrub
	exit 0
fi

# --- Prerequisites ----------------------------------------------------------------------------
if ! command -v node >/dev/null 2>&1; then
	echo "'node' is not on PATH. Install Node.js (the Playwright runner needs it)." >&2
	exit 2
fi

if ! command -v curl >/dev/null 2>&1; then
	echo "'curl' is not on PATH." >&2
	exit 2
fi

if [[ ! -f "$LAUNCHER" ]]; then
	echo "The shared Playwright launcher is missing: $LAUNCHER" >&2
	echo "Check out akeeba/buildfiles next to this repository (../buildfiles) and run 'npm install' in it." >&2
	exit 2
fi

if [[ -z "$PHP_OLDEST" ]]; then
	echo "Could not read the oldest PHP version from composer.json's require.php (is 'php' on PATH?)." >&2
	exit 2
fi

if [[ -n "$OPT_PHP" ]] && ! { version_le "$PHP_OLDEST" "$OPT_PHP" && version_le "$OPT_PHP" "$PHP_NEWEST"; }; then
	echo "Invalid --php '$OPT_PHP': Panopticon supports PHP $PHP_OLDEST to $PHP_NEWEST (composer.json require.php)." >&2
	exit 2
fi

# --- Resolve the ZIP --------------------------------------------------------------------------
newest_zip() { ls -t "$ROOT_DIR"/release/panopticon-*.zip 2>/dev/null | head -n1 || true; }

if [[ -z "$ZIP" ]]; then
	ZIP="$(newest_zip)"

	if [[ -z "$ZIP" ]]; then
		if ! command -v phing >/dev/null 2>&1; then
			echo "There is no release/panopticon-*.zip and 'phing' is not on PATH to build one." >&2
			echo "Pass --zip=<path>, or install Phing and run 'phing git-app'." >&2
			exit 2
		fi
		# This rewrites vendor/ without the dev dependencies: run `composer dump-autoload --dev` before `composer test`.
		echo "==> No release ZIP found. Building one with 'phing git-app' ..."
		( cd "$ROOT_DIR" && phing git-app )
		ZIP="$(newest_zip)"

		if [[ -z "$ZIP" ]]; then
			echo "'phing git-app' did not produce a release/panopticon-*.zip." >&2
			exit 2
		fi
	fi
	export INSTALLTEST_ZIP="$ZIP"
fi

ZIP_VERSION="$(basename "$ZIP" .zip)"
ZIP_VERSION="${ZIP_VERSION#panopticon-}"
echo "==> Testing ZIP: $ZIP"
echo "==> ZIP version: $ZIP_VERSION"
echo "==> PHP $PHP_OLDEST to $PHP_NEWEST; MySQL $MYSQL_OLDEST to $MYSQL_NEWEST; MariaDB $MARIADB_OLDEST to $MARIADB_NEWEST"

# --- Browser, once ----------------------------------------------------------------------------
echo "==> Making sure the Playwright browser is installed ..."
node "$LAUNCHER" install

# --- The combinations -------------------------------------------------------------------------
# Each entry: PHP|database image|languages|paths. Languages: "all", "all-but-en-GB" or a comma-separated list.
COMBOS=()

if [[ -n "$OPT_PHP" || -n "$OPT_DB" || -n "$OPT_LANG" || "$OPT_CRON" -eq 1 || "$OPT_SAVECONFIG" -eq 1 ]]; then
	paths=()
	[[ "$OPT_CRON" -eq 1 ]] && paths+=("cron")
	[[ "$OPT_SAVECONFIG" -eq 1 ]] && paths+=("saveconfig")
	path_list="normal"
	langs="all"

	if [[ "${#paths[@]}" -gt 0 ]]; then
		path_list="$(IFS=,; echo "${paths[*]}")"
		langs="en-GB"
	fi

	[[ -n "$OPT_LANG" ]] && langs="$OPT_LANG"
	COMBOS+=("${OPT_PHP:-$PHP_NEWEST}|${OPT_DB:-mysql:$MYSQL_NEWEST}|$langs|$path_list")
else
	COMBOS+=(
		"$PHP_OLDEST|mysql:$MYSQL_OLDEST|all|normal"
		# The CRON benchmark and the manual config.php run ARE the en-GB installs of the newest edge.
		"$PHP_NEWEST|mysql:$MYSQL_NEWEST|all-but-en-GB|normal"
		"$PHP_NEWEST|mysql:$MYSQL_NEWEST|en-GB|cron,saveconfig"
		"$PHP_OLDEST|mariadb:$MARIADB_OLDEST|en-GB|normal"
		"$PHP_NEWEST|mariadb:$MARIADB_NEWEST|en-GB|normal"
	)
fi

# --- Cleanup ----------------------------------------------------------------------------------
cleanup() {
	if [[ "$KEEP" -eq 1 ]]; then
		echo "==> Leaving the stack as it is (--keep). Tear it down with: tests/Installation/run.sh --down"
		return
	fi
	echo "==> Tearing down the stack ..."
	scrub
}
trap cleanup EXIT
trap 'exit 130' INT TERM

# Never trust what an earlier, possibly crashed, run left behind.
echo "==> Scrubbing any leftover stack ..."
scrub
rm -rf "$RESULTS_DIR"
mkdir -p "$RESULTS_DIR"

# --- Run --------------------------------------------------------------------------------------
INFRA_FAILURES=()
STACK_KEY=""

# The languages the installer offers, read from the welcome page of the fresh install.
discover_languages() {
	curl -fsSL "$BASE_URL/" \
		| tr '\n\r\t' '   ' \
		| sed -n 's/.*<select[^>]*id="language"\(.*\)/\1/p' \
		| sed 's/<\/select>.*//' \
		| grep -o '<option[^>]*value="[^"]*"' \
		| sed 's/.*value="\([^"]*\)"/\1/' \
		| grep -v '^$' || true
}

copy_logs() {
	local dir="$1"
	compose exec -T php cat /var/log/php/error.log > "$dir/php-error.log" 2>/dev/null || true
	compose exec -T php cat /var/log/apache2/error.log > "$dir/apache-error.log" 2>/dev/null || true
	compose exec -T php cat /var/log/apache2/access.log > "$dir/apache-access.log" 2>/dev/null || true
	compose logs --no-color > "$dir/container-output.log" 2>&1 || true
}

stack_down() {
	[[ -n "$STACK_KEY" ]] && scrub
	STACK_KEY=""
}

index=0
for entry in "${COMBOS[@]}"; do
	index=$((index + 1))
	IFS='|' read -r c_php c_db c_langs c_paths <<< "$entry"
	c_db_name="${c_db%%:*}"
	c_db_version="${c_db#*:}"
	[[ "$c_db_name" == "mysql" ]] && db_label="MySQL" || db_label="MariaDB"
	label="PHP $c_php · $db_label $c_db_version"
	slug="php${c_php}-${c_db_name}${c_db_version}-${c_paths//,/-}"
	out_dir="$RESULTS_DIR/$slug"
	mkdir -p "$out_dir"

	echo
	echo "==> [$index/${#COMBOS[@]}] $label · languages: $c_langs · paths: $c_paths"

	# The newest edge has two entries on the same stack: do not rebuild it in between.
	key="$c_php|$c_db"
	if [[ "$key" != "$STACK_KEY" ]]; then
		stack_down
		export INSTALLTEST_PHP="$c_php"
		export INSTALLTEST_DB_IMAGE="$c_db"
		# mysql:5.7 has no arm64 image.
		if [[ "$c_db" == "mysql:5.7" ]]; then
			export INSTALLTEST_DB_PLATFORM="linux/amd64"
		else
			export INSTALLTEST_DB_PLATFORM=""
		fi

		echo "==> Starting the stack ..."
		if ! compose up -d --build --wait; then
			echo "!! The stack for $label did not come up." >&2
			compose logs --no-color > "$out_dir/container-output.log" 2>&1 || true
			INFRA_FAILURES+=("$label: the Docker stack did not start (see results/$slug/container-output.log)")
			scrub
			continue
		fi
		STACK_KEY="$key"
	fi

	if ! compose exec -T php php /lab/lab-reset.php | grep -q 'RESET OK'; then
		echo "!! The lab did not reset for $label." >&2
		INFRA_FAILURES+=("$label: lab-reset.php did not report RESET OK")
		copy_logs "$out_dir"
		stack_down
		continue
	fi

	# Expand the languages from what the installer actually offers.
	discovered="$(discover_languages | paste -sd, -)"
	if [[ -z "$discovered" ]]; then
		echo "!! Could not discover the installer's languages from $BASE_URL/." >&2
		INFRA_FAILURES+=("$label: could not discover the installer's languages")
		copy_logs "$out_dir"
		stack_down
		continue
	fi
	echo "==> The installer offers: $discovered"

	case "$c_langs" in
		all)           langs="$discovered" ;;
		all-but-en-GB) langs="$(echo "$discovered" | tr ',' '\n' | grep -vx 'en-GB' | paste -sd, -)" ;;
		*)             langs="$c_langs" ;;
	esac

	bad_langs=""
	for l in ${langs//,/ }; do
		[[ ",$discovered," == *",$l,"* ]] || bad_langs="$bad_langs $l"
	done
	if [[ -n "$bad_langs" ]]; then
		echo "!! Language(s) not offered by the installer:$bad_langs" >&2
		INFRA_FAILURES+=("$label: requested language(s) not offered by the installer:$bad_langs")
		stack_down
		continue
	fi

	if [[ -n "$OPT_LANG" ]]; then
		skipped_langs=""
		for l in ${discovered//,/ }; do
			[[ ",$langs," == *",$l,"* ]] || skipped_langs="${skipped_langs:+$skipped_langs, }$l"
		done
		if [[ -n "$skipped_langs" ]]; then
			echo "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!" >&2
			echo "!! PARTIAL RUN: --lang left out ${skipped_langs}" >&2
			echo "!! These languages are NOT tested. A green result does not cover them." >&2
			echo "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!" >&2
		fi
	fi

	pw_args=(test)
	[[ "$HEADED" -eq 1 ]] && pw_args+=(--headed)

	set +e
	(
		cd "$BROWSER_DIR"
		INSTALLTEST_BASE_URL="$BASE_URL" \
		INSTALLTEST_COMBO="$label" \
		INSTALLTEST_LANGS="$langs" \
		INSTALLTEST_PATHS="$c_paths" \
		INSTALLTEST_RESULTS_DIR="$out_dir" \
		INSTALLTEST_COMPOSE_FILE="$COMPOSE_FILE" \
		INSTALLTEST_PROJECT="$PROJECT" \
			node "$LAUNCHER" "${pw_args[@]}"
	)
	pw_status=$?
	set -e

	[[ "$pw_status" -ne 0 ]] && echo "!! Playwright exited with status $pw_status for $label."

	copy_logs "$out_dir"
done

# Down after the last combination, unless --keep (the EXIT trap then leaves it alone).
if [[ "$KEEP" -ne 1 ]]; then
	stack_down
fi

# --- Summary ----------------------------------------------------------------------------------
STATUS=0
echo
echo "==> Summary"

if [[ "${#INFRA_FAILURES[@]}" -gt 0 ]]; then
	STATUS=1
	echo "INFRASTRUCTURE FAILURES (${#INFRA_FAILURES[@]}):"
	for failure in "${INFRA_FAILURES[@]}"; do
		echo "  - $failure"
	done
fi

reports=()
for report in "$RESULTS_DIR"/*/report.json; do
	[[ -f "$report" ]] && reports+=("$report")
done

if [[ "${#reports[@]}" -gt 0 ]]; then
	( cd "$SCRIPT_DIR" && node summary.mjs "${reports[@]#"$SCRIPT_DIR"/}" ) || STATUS=1
else
	echo "No test report was produced: nothing ran."
	STATUS=1
fi

echo "==> Evidence (traces, screenshots, logs): $RESULTS_DIR"
exit "$STATUS"
