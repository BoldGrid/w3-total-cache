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

# Development, CI, and editor trees — not required at runtime.
rm -rf \
	.claude \
	.cursor \
	.github \
	qa \
	ci \
	policies \
	rules \
	tmp \
	phpcs-rules \
	tests

rm -f \
	.jshintrc \
	.sec-project.yaml \
	AGENTS.md \
	CLAUDE.md \
	codecov \
	coverage.xml \
	phpcs.xml \
	phpunit.xml
