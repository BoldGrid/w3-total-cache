#!/usr/bin/env bash
# Regenerate languages/w3-total-cache.pot with whichever WP-CLI is available.

set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

TMP_WP_CLI=''

cleanup() {
	if [[ -n "$TMP_WP_CLI" ]]; then
		rm -f "$TMP_WP_CLI"
	fi
}
trap cleanup EXIT

if [[ -n "${WP_CLI_BIN:-}" ]]; then
	if [[ ! -f "$WP_CLI_BIN" ]]; then
		echo "error: WP_CLI_BIN is set but not found: $WP_CLI_BIN" >&2
		exit 1
	fi
elif command -v wp >/dev/null 2>&1; then
	WP_CLI_BIN="$(command -v wp)"
elif [[ -f /tmp/wp ]]; then
	WP_CLI_BIN='/tmp/wp'
else
	TMP_WP_CLI="$(mktemp -t wp-cli.XXXXXX.phar)"
	WP_CLI_BIN="$TMP_WP_CLI"
	if command -v curl >/dev/null 2>&1; then
		curl -fsSL -o "$WP_CLI_BIN" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
	elif command -v wget >/dev/null 2>&1; then
		wget -q -O "$WP_CLI_BIN" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
	else
		echo 'error: wp-cli not found and neither curl nor wget is available' >&2
		exit 1
	fi
	chmod +x "$WP_CLI_BIN"
fi

# "xdebug.max_nesting_level=512" avoids nesting errors while parsing the plugin.
php -d xdebug.max_nesting_level=512 "$WP_CLI_BIN" i18n make-pot . languages/w3-total-cache.pot
