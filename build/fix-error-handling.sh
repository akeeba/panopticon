#!/usr/bin/env bash
#
# @package   panopticon
# @copyright Copyright (c)2023-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
# @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
#
# Re-syncs patches/symfony-error-handler/*.php against a newer vendor/symfony/error-handler release.
#
# tests/Unit/Application/ErrorHandlerPatchesTest.php pins the SHA-256 of the upstream files our
# patches/ copies are based on (see patches/README.md for why they're vendored instead of patched
# at runtime). When `composer update` pulls a new symfony/error-handler, that test starts failing.
#
# This script detects the drift, hands the actual re-sync work to a coding agent (it requires
# judgement — re-applying our AKEEBA PANOPTICON CUSTOMISATION blocks onto the new upstream source,
# not a mechanical patch/diff apply), then verifies the result.
#
# Usage:
#   build/fix-error-handling.sh [--agent claude|codex|qwen]
#
# Invoked as: phing fix-error-handling
#
set -uo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR" || exit 1

AGENT="claude"

while [[ $# -gt 0 ]]; do
	case "$1" in
		--agent)
			AGENT="$2"
			shift 2
			;;
		--agent=*)
			AGENT="${1#--agent=}"
			shift
			;;
		*)
			echo "Unknown argument: $1" >&2
			exit 1
			;;
	esac
done

TEST_FILE="$ROOT_DIR/tests/Unit/Application/ErrorHandlerPatchesTest.php"
PATCH_DIR="$ROOT_DIR/patches/symfony-error-handler"
README="$ROOT_DIR/patches/README.md"

VENDOR_FLATTEN="$ROOT_DIR/vendor/symfony/error-handler/Exception/FlattenException.php"
VENDOR_RENDERER="$ROOT_DIR/vendor/symfony/error-handler/ErrorRenderer/HtmlErrorRenderer.php"
PATCH_FLATTEN="$PATCH_DIR/FlattenException.php"
PATCH_RENDERER="$PATCH_DIR/HtmlErrorRenderer.php"

# -- 1. Detect drift -------------------------------------------------------------------------------

if [[ ! -f "$VENDOR_FLATTEN" || ! -f "$VENDOR_RENDERER" ]]; then
	echo "vendor/symfony/error-handler is not installed. Run composer install first." >&2
	exit 1
fi

# UPSTREAM_HASHES is a plain const array literal in the test file; grep it rather than bootstrapping
# the whole application just to reflect the class (the class also requires the AKEEBA guard constant).
EXPECTED_FLATTEN="$(grep "Exception/FlattenException.php" "$TEST_FILE" | grep -oE "[0-9a-f]{64}" | head -n1)"
EXPECTED_RENDERER="$(grep "ErrorRenderer/HtmlErrorRenderer.php" "$TEST_FILE" | grep -oE "[0-9a-f]{64}" | head -n1)"

ACTUAL_FLATTEN="$(php -r 'echo hash_file("sha256", $argv[1]);' "$VENDOR_FLATTEN")"
ACTUAL_RENDERER="$(php -r 'echo hash_file("sha256", $argv[1]);' "$VENDOR_RENDERER")"

DRIFTED=()

[[ "$ACTUAL_FLATTEN" != "$EXPECTED_FLATTEN" ]] && DRIFTED+=("FlattenException.php")
[[ "$ACTUAL_RENDERER" != "$EXPECTED_RENDERER" ]] && DRIFTED+=("HtmlErrorRenderer.php")

if [[ ${#DRIFTED[@]} -eq 0 ]]; then
	echo "patches/symfony-error-handler/ is already in sync with vendor/symfony/error-handler. Nothing to do."
	exit 0
fi

echo "Upstream drift detected in: ${DRIFTED[*]}"

VENDOR_VERSION="$(php -r '
	$data = json_decode(file_get_contents($argv[1]), true);
	foreach ($data["packages"] ?? [] as $p) {
		if ($p["name"] === "symfony/error-handler") { echo $p["version"]; break; }
	}
' "$ROOT_DIR/vendor/composer/installed.json")"

# -- 2. Hand the re-sync to a coding agent ----------------------------------------------------------

PROMPT_FILE="$(mktemp)"
trap 'rm -f "$PROMPT_FILE"' EXIT

# Written straight to a file rather than captured via "$(cat <<EOF ... )" — the unbalanced
# parentheses in the prose below trip up /bin/bash 3.2's (macOS default) paren-counting when a
# heredoc appears inside a $(...) command substitution.
cat <<EOF > "$PROMPT_FILE"
Re-sync the vendored, locally-modified Symfony error-handler copies in patches/symfony-error-handler/
against the newer upstream release now installed at vendor/symfony/error-handler (version ${VENDOR_VERSION:-unknown}).

Background: read patches/README.md first — it explains why these files are vendored copies (loaded in
place of the upstream classes by Akeeba\\Panopticon\\Application\\BootstrapUtilities::overrideHtmlErrorRenderer()
before Composer's autoloader runs) and lists exactly what our customisation to each file is.

Files that drifted this run: ${DRIFTED[*]}

For EACH drifted file, do this precisely:
1. Read the current patches/symfony-error-handler/<File>.php to see our existing
   AKEEBA PANOPTICON CUSTOMISATION / END AKEEBA PANOPTICON CUSTOMISATION block(s) and the header comment.
2. Read the new vendor/symfony/error-handler/.../<File>.php (the upstream source of truth for this run).
3. Copy the new upstream file over the patches/ copy, verbatim.
4. Re-insert the header comment block (the "AKEEBA PANOPTICON — VENDORED, LOCALLY MODIFIED COPY" block)
   right after the upstream license header, updating the pinned version number to ${VENDOR_VERSION:-the new version}.
5. Re-apply each customisation from the OLD patches/ copy at the same logical location in the new file,
   wrapped in the same AKEEBA PANOPTICON CUSTOMISATION / END AKEEBA PANOPTICON CUSTOMISATION markers, with
   the same explanatory comment. If upstream's surrounding code changed shape (not just the parts we
   override), adapt the customisation to fit — do not silently drop it or leave it out of place.
6. Run: php -l patches/symfony-error-handler/<File>.php  — it must report no syntax errors.

Then, for tests/Unit/Application/ErrorHandlerPatchesTest.php:
- Update the UPSTREAM_HASHES entries for the file(s) you resynced to the SHA-256 of the CURRENT
  vendor/symfony/error-handler file (compute with: php -r 'echo hash_file("sha256", "<path>");').
- Update the "SHA-256 of each pinned upstream file (symfony/error-handler vX.Y.Z)" docblock comment
  above UPSTREAM_HASHES to the new version.

Then update patches/README.md's "Contents" table row(s) for the file(s) you resynced to the new
upstream package version.

Finally run: vendor/bin/phpunit --filter=ErrorHandlerPatchesTest
All four test methods (testUpstreamSourceHasNotDrifted, testLocalCopyExists,
testLocalCopyKeepsCustomisationMarkers, testLocalCopyIsValidPhp) must pass for both files. If anything
fails, fix it and re-run until green. Do not weaken the test to make it pass.
EOF

echo ""
echo "Delegating the re-sync to '$AGENT'..."
echo ""

case "$AGENT" in
	claude)
		claude -p "$(cat "$PROMPT_FILE")" --dangerously-skip-permissions
		;;
	codex)
		codex exec --sandbox workspace-write --cd "$ROOT_DIR" "$(cat "$PROMPT_FILE")"
		;;
	qwen)
		qwen -p "$(cat "$PROMPT_FILE")"
		;;
	*)
		echo "Unknown agent '$AGENT'. Supported: claude, codex, qwen." >&2
		exit 1
		;;
esac

AGENT_STATUS=$?

# -- 3. Verify -----------------------------------------------------------------------------------

echo ""
echo "Verifying with: vendor/bin/phpunit --filter=ErrorHandlerPatchesTest"
echo ""

if [[ ! -x "$ROOT_DIR/vendor/bin/phpunit" ]]; then
	echo "vendor/bin/phpunit not found — run composer install (with dev deps) and re-run this script to verify." >&2
	exit "$AGENT_STATUS"
fi

"$ROOT_DIR/vendor/bin/phpunit" --filter=ErrorHandlerPatchesTest
PHPUNIT_STATUS=$?

if [[ $PHPUNIT_STATUS -ne 0 ]]; then
	echo "" >&2
	echo "ErrorHandlerPatchesTest is still failing after the '$AGENT' re-sync attempt." >&2
	echo "Review the diff in patches/symfony-error-handler/ and tests/Unit/Application/ErrorHandlerPatchesTest.php by hand," >&2
	echo "following patches/README.md, or retry with a different agent: build/fix-error-handling.sh --agent codex" >&2
	exit $PHPUNIT_STATUS
fi

echo ""
echo "patches/symfony-error-handler/ is back in sync. Review the diff (git diff patches/ tests/Unit/Application/ErrorHandlerPatchesTest.php)"
echo "before committing."
