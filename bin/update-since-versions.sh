#!/usr/bin/env bash
# Replace @since X.X.X placeholders with the plugin version from w3-total-cache.php.

set -euo pipefail

PLUGIN_FILE='w3-total-cache.php'

if [[ ! -f "$PLUGIN_FILE" ]]; then
	echo "error: $PLUGIN_FILE not found" >&2
	exit 1
fi

W3TC_VERSION="$(grep -F 'Version:' "$PLUGIN_FILE" | grep -Eo '[0-9]+.+$' | head -1)"

if [[ -z "$W3TC_VERSION" ]]; then
	echo "error: could not read Version from $PLUGIN_FILE" >&2
	exit 1
fi

echo "Updating @since X.X.X placeholders to $W3TC_VERSION"

mapfile -t FILES < <(
	grep --exclude-dir={.cursor,node_modules,vendor} \
		--exclude={AGENTS.md,CLAUDE.md,update-since-versions.sh} \
		-ERil "@since[[:space:]]+[Xx]\\.[Xx]\\.[Xx]|'[Xx]\\.[Xx]\\.[Xx]'" \
		--include='*.php' \
		--include='*.js' \
		. 2>/dev/null || true
)

if [[ ${#FILES[@]} -eq 0 ]]; then
	echo 'No X.X.X placeholders found.'
	exit 0
fi

for file in "${FILES[@]}"; do
	sed -i -E \
		-e "s/(@since[[:space:]]+)[Xx]\.[Xx]\.[Xx]/\1${W3TC_VERSION}/gi" \
		-e "s/'[Xx]\.[Xx]\.[Xx]'/'${W3TC_VERSION}'/g" \
		"$file"
done

echo "Updated ${#FILES[@]} file(s)."
