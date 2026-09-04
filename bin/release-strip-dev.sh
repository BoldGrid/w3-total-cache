#!/usr/bin/env bash
# Remove development-only files from the current directory (plugin root).
# Intended for Travis release.sh and local bin/build-release-zip.sh staging.
# Does not remove bin/, composer.*, or package.json (needed until after
# @since replacement and POT generation).

set -euo pipefail

if [[ ! -f w3-total-cache.php ]]; then
	echo 'error: run from the plugin root' >&2
	exit 1
fi

strip_dirs=(
	.claude
	.cursor
	.github
	qa
	ci
	policies
	rules
	tmp
	phpcs-rules
	tests
)

strip_files=(
	.jshintrc
	.sec-project.yaml
	AGENTS.md
	CLAUDE.md
	codecov
	coverage.xml
	phpcs.xml
	phpunit.xml
)

rm -rf "${strip_dirs[@]}"
rm -f "${strip_files[@]}"

leftover=()
for path in "${strip_dirs[@]}" "${strip_files[@]}"; do
	if [[ -e "$path" ]]; then
		leftover+=( "$path" )
	fi
done

if [[ ${#leftover[@]} -gt 0 ]]; then
	echo 'error: development-only paths still present after strip:' >&2
	printf '%s\n' "${leftover[@]}" >&2
	exit 1
fi
