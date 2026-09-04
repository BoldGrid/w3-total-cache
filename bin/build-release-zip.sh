#!/usr/bin/env bash
# Build a production-like plugin ZIP comparable to WordPress.org downloads
# (e.g. w3-total-cache.2.9.3.zip). Does not modify the working tree.

set -euo pipefail

usage() {
	cat <<'EOF'
Usage: bin/build-release-zip.sh <version> [-o output-path]

Build a WordPress.org-style release ZIP from the current working tree.

Arguments:
  version       Required. Version string for the artifact, e.g. "2.10.0" or
                "2.10.0-alpha.1". Patches w3-total-cache.php, w3-total-cache-api.php,
                and readme.txt in the staging copy only.

Options:
  -o path       Write the ZIP to path (default: ./w3-total-cache.<version>.zip)
  -h, --help    Show this help

The artifact matches the release pipeline (bin/release.sh plus wordpress-tag-sync):
production Composer dependencies, dev files removed, @since placeholders resolved,
and languages/w3-total-cache.pot regenerated.
EOF
}

VERSION=''
OUTPUT=''

while [[ $# -gt 0 ]]; do
	case "$1" in
		-h | --help)
			usage
			exit 0
			;;
		-o)
			shift
			OUTPUT="${1:-}"
			if [[ -z "$OUTPUT" ]]; then
				echo 'error: -o requires a path' >&2
				exit 1
			fi
			;;
		-*)
			echo "error: unknown option: $1" >&2
			usage >&2
			exit 1
			;;
		*)
			if [[ -n "$VERSION" ]]; then
				echo 'error: unexpected argument: '"$1" >&2
				usage >&2
				exit 1
			fi
			VERSION="$1"
			;;
	esac
	shift
done

if [[ -z "$VERSION" ]]; then
	echo 'error: version argument is required' >&2
	usage >&2
	exit 1
fi

if [[ "$VERSION" == *"/"* || "$VERSION" == *" "* || "$VERSION" == *$'\t'* ]]; then
	echo "error: invalid version string: $VERSION" >&2
	exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
PLUGIN_SLUG='w3-total-cache'

if [[ -z "$OUTPUT" ]]; then
	OUTPUT="$PLUGIN_ROOT/${PLUGIN_SLUG}.${VERSION}.zip"
fi

OUTPUT_DIR="$(dirname "$OUTPUT")"
OUTPUT_FILE="$(basename "$OUTPUT")"
mkdir -p "$OUTPUT_DIR"
OUTPUT="$(cd "$OUTPUT_DIR" && pwd)/$OUTPUT_FILE"
mkdir -p "${PLUGIN_ROOT}/.cursor/working"

WORK_ROOT="$(mktemp -d "${PLUGIN_ROOT}/.cursor/working/build-release-zip.XXXXXX")"
STAGING="${WORK_ROOT}/${PLUGIN_SLUG}"

cleanup() {
	rm -rf "$WORK_ROOT"
}

trap cleanup EXIT

require_command() {
	if ! command -v "$1" >/dev/null 2>&1; then
		echo "error: required command not found: $1" >&2
		exit 1
	fi
}

require_command rsync
require_command composer
require_command zip
require_command php
require_command sed
require_command grep

remove_vcs_metadata() {
	find . \
		\( -name '.git' -o -name '.github' -o -name '.svn' -o -name '.hg' \) \
		-type d \
		-prune \
		-exec rm -rf '{}' +
	find . \
		\( -name '.gitignore' -o -name '.gitattributes' -o -name '.gitmodules' \) \
		-type f \
		-delete
}

verify_no_vcs_metadata() {
	local git_dir

	git_dir="$(find . -name '.git' -type d -print -quit 2>/dev/null || true)"
	if [[ -n "$git_dir" ]]; then
		echo "error: .git directory found in staging tree: ${git_dir}" >&2
		find . -name '.git' -type d >&2
		exit 1
	fi
}

patch_version_in_staging() {
	sed -i -E "s/^ \* Version:[[:space:]]+.*/ * Version:           ${VERSION}/" w3-total-cache.php
	sed -i -E "s/^define\\( 'W3TC_VERSION', '[^']+' \\);/define( 'W3TC_VERSION', '${VERSION}' );/" w3-total-cache-api.php
	sed -i -E "s/^Stable tag: .*/Stable tag: ${VERSION}/" readme.txt

	if ! grep -Fq " * Version:           ${VERSION}" w3-total-cache.php; then
		echo 'error: failed to patch Version in w3-total-cache.php' >&2
		exit 1
	fi
	if ! grep -Fq "define( 'W3TC_VERSION', '${VERSION}' );" w3-total-cache-api.php; then
		echo 'error: failed to patch W3TC_VERSION in w3-total-cache-api.php' >&2
		exit 1
	fi
}

echo "Staging release tree for ${PLUGIN_SLUG} ${VERSION}..."

mkdir -p "$STAGING"
rsync -a \
	--exclude='.git' \
	--exclude='.git/' \
	--exclude='.github/' \
	--exclude='node_modules/' \
	--exclude='.claude/' \
	--exclude='.cursor/' \
	--exclude='.cursor/working/' \
	--exclude='vendor/' \
	--exclude='qa/' \
	--exclude='ci/' \
	--exclude='policies/' \
	--exclude='rules/' \
	--exclude='tmp/' \
	--exclude='phpcs-rules/' \
	--exclude='tests/' \
	--exclude="${PLUGIN_SLUG}."*.zip \
	--exclude='w3-total-cache.zip' \
	"$PLUGIN_ROOT/" "$STAGING/"

cd "$STAGING"
remove_vcs_metadata

echo "Patching version to ${VERSION} in staging copy..."
patch_version_in_staging

echo 'Installing production Composer dependencies...'
composer install --no-dev --no-interaction --prefer-dist -o

echo 'Applying release cleanup (bin/release-strip-dev.sh)...'
remove_vcs_metadata
bash bin/release-strip-dev.sh

while IFS= read -r -d '' link; do
	target="$(readlink -f "$link" || realpath "$link")"
	cp -f --remove-destination "$target" "$link"
done < <(find vendor/ -type l -print0 2>/dev/null || true)

remove_vcs_metadata

echo 'Updating @since placeholders...'
bash bin/update-since-versions.sh

echo 'Regenerating languages/w3-total-cache.pot...'
bash bin/make-pot.sh

rm -f package.* yarn.lock

echo 'Applying wordpress-tag-sync cleanup...'
rm -rf node_modules bin
remove_vcs_metadata
rm -f \
	.travis.yml \
	yarn.lock \
	package.json \
	composer.json \
	composer.lock \
	README.md \
	.editorconfig \
	stylelint.config.js \
	.stylelintignore

verify_no_vcs_metadata

rm -f "$OUTPUT"
(
	cd "$WORK_ROOT"
	zip -rq "$OUTPUT" "$PLUGIN_SLUG"
)

if unzip -l "$OUTPUT" | grep -E '/\.git/|/\.git$' | grep -v '/\.github' >/dev/null 2>&1; then
	echo 'error: ZIP contains .git paths' >&2
	unzip -l "$OUTPUT" | grep -E '/\.git/|/\.git$' | grep -v '/\.github' >&2 || true
	exit 1
fi

forbidden="$(
	unzip -l "$OUTPUT" | awk '{print $4}' | grep -E "^${PLUGIN_SLUG}/(ci|policies|rules|tmp|phpcs-rules|qa|tests|bin|\.claude|\.cursor|\.github)(/|$)" || true
)"
if [[ -n "$forbidden" ]]; then
	echo 'error: ZIP contains development-only paths' >&2
	printf '%s\n' "$forbidden" >&2
	exit 1
fi

forbidden_files="$(
	unzip -l "$OUTPUT" | awk '{print $4}' | grep -E "^${PLUGIN_SLUG}/(\.sec-project\.yaml|phpcs\.xml|phpunit\.xml|AGENTS\.md|CLAUDE\.md)$" || true
)"
if [[ -n "$forbidden_files" ]]; then
	echo 'error: ZIP contains development-only files' >&2
	printf '%s\n' "$forbidden_files" >&2
	exit 1
fi

echo "Created: $OUTPUT"
echo "Version in artifact: ${VERSION} (w3-total-cache.php, w3-total-cache-api.php, readme.txt)"
echo 'Install note: uploading via WP admin over an existing git checkout of this plugin can fail on .git/objects permissions — use a clean plugins directory or delete the old copy first.'
