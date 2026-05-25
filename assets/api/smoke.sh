#!/usr/bin/env bash
#
# @package   panopticon
# @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
# @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
#
# Manual smoke driver for the Panopticon JSON API authentication surface.
#
# This script automates the curl portions of the Phase 1 UI verification sub-plan,
# specifically steps 11–13 (authentication with all three token-passing methods,
# the no-token / invalid-token rejection paths, and the no-session assertion).
#
# This script is INTENDED to be run by a human (or CI) against a live Panopticon
# instance. It is not invoked by the automated test suite. Run it after you have
# minted a working API token through the Apitokens UI.
#
# Usage:
#
#     HOST="https://panopticon.example.test" TOKEN="<your-token>" \
#         bash assets/api/smoke.sh
#
# HOST must be the scheme + host (no trailing slash, no /api suffix). The script
# appends /api/v1/sites itself.
#
# Exit code 0 means every assertion passed. Any non-zero exit means a failure
# you should investigate before shipping.

set -euo pipefail

: "${HOST:?Set HOST to the Panopticon base URL, e.g. https://panopticon.example.test}"
: "${TOKEN:?Set TOKEN to a valid, enabled API token minted via the Apitokens UI}"

# Sanity-check HOST: it MUST be the site root only — the script appends /api/v1/sites itself.
# A HOST ending in /api would produce /api/api/v1/sites and silently never route to the API.
HOST="${HOST%/}"

case "$HOST" in
*/api|*/api/v1|*/api/v1/*)
	printf >&2 'ERROR: HOST=%s already includes an /api path.\n' "$HOST"
	printf >&2 '       Set HOST to the bare site root (e.g. https://panopticon.example.test).\n'
	printf >&2 '       The script appends /api/v1/sites itself.\n'
	exit 2
	;;
esac

ENDPOINT="$HOST/api/v1/sites"

pass()
{
	printf '  [PASS] %s\n' "$1"
}

fail()
{
	printf '  [FAIL] %s\n' "$1" >&2
	exit 1
}

assert_status()
{
	local label="$1"
	local expected="$2"
	local response="$3"

	# Status line is the first HTTP/x.y line in the (possibly multi-response) output.
	local status
	status="$(printf '%s\n' "$response" | grep -E '^HTTP/' | tail -n 1 | awk '{print $2}')"

	if [ "$status" = "$expected" ]
	then
		pass "$label -> HTTP $status"
	else
		printf '%s\n' "$response" >&2
		fail "$label -> expected HTTP $expected, got HTTP ${status:-<none>}"
	fi
}

assert_header_present()
{
	local label="$1"
	local pattern="$2"
	local response="$3"

	if printf '%s\n' "$response" | grep -iEq "$pattern"
	then
		pass "$label header present"
	else
		printf '%s\n' "$response" >&2
		fail "$label header missing (pattern: $pattern)"
	fi
}

assert_no_phpsessid()
{
	local label="$1"
	local response="$2"

	# Set-Cookie lines containing PHPSESSID would indicate a session leak.
	if printf '%s\n' "$response" | grep -iE '^Set-Cookie:' | grep -iq 'PHPSESSID'
	then
		printf '%s\n' "$response" >&2
		fail "$label leaks PHPSESSID Set-Cookie"
	fi

	pass "$label has no PHPSESSID Set-Cookie"
}

printf 'Panopticon API smoke against %s\n\n' "$ENDPOINT"

# 1) Bearer header → 200, no session cookie
printf '[1/6] Authorization: Bearer <token>\n'
RESP="$(curl -isS "$ENDPOINT" -H "Authorization: Bearer ${TOKEN}")"
assert_status 'Bearer header' '200' "$RESP"
assert_no_phpsessid 'Bearer header' "$RESP"

# 2) X-Panopticon-Token header → 200, no session cookie
printf '[2/6] X-Panopticon-Token: <token>\n'
RESP="$(curl -isS "$ENDPOINT" -H "X-Panopticon-Token: ${TOKEN}")"
assert_status 'X-Panopticon-Token header' '200' "$RESP"
assert_no_phpsessid 'X-Panopticon-Token header' "$RESP"

# 3) ?_panopticon_token=… → 200, no session cookie
printf '[3/6] ?_panopticon_token=<token>\n'
RESP="$(curl -isS --get "$ENDPOINT" --data-urlencode "_panopticon_token=${TOKEN}")"
assert_status 'GET parameter' '200' "$RESP"
assert_no_phpsessid 'GET parameter' "$RESP"

# 4) No token → 401 + WWW-Authenticate: Bearer realm="Panopticon API"
printf '[4/6] No token (expect 401 + WWW-Authenticate)\n'
RESP="$(curl -isS "$ENDPOINT")"
assert_status 'No token' '401' "$RESP"
assert_header_present \
	'WWW-Authenticate: Bearer realm="Panopticon API"' \
	'^WWW-Authenticate:[[:space:]]*Bearer[[:space:]]+realm="Panopticon API"' \
	"$RESP"

# 5) Invalid Bearer → 401
printf '[5/6] Authorization: Bearer invalid (expect 401)\n'
RESP="$(curl -isS "$ENDPOINT" -H 'Authorization: Bearer invalid')"
assert_status 'Invalid Bearer' '401' "$RESP"

# 6) Sanity: invalid token must also not leak a PHPSESSID cookie.
assert_no_phpsessid 'Invalid Bearer' "$RESP"

printf '\nAll API smoke assertions passed.\n'
