#!/usr/bin/env bash
# Release script for the W3 Total Cache WordPress plugin by BoldGrid.

# Cleanup uneeded git content.
echo 'Finding and deleting .gitignore files.'
find . -name '.git*' -type f -delete
echo 'Finding and deleting .git folders.'
find vendor/ -name '.git' -type d -print -exec rm -rf {} +

# Cleanup development and build contents (keep package.json until after yarn aliases).
bash bin/release-strip-dev.sh

leftover="$(ls -d ci policies rules tmp phpcs-rules qa tests .claude .cursor .github 2>/dev/null || true)"
if [[ -n "$leftover" ]]; then
	echo 'error: development-only paths still present after strip:' >&2
	printf '%s\n' "$leftover" >&2
	exit 1
fi

# Find and replace symlinks in the "vendor" directory.
for i in $(find vendor/ -type l); do \cp -f --remove-destination $(realpath $i) $i;done

# Replace leftover @since X.X.X placeholders, then regenerate the POT.
# Call the bash scripts directly; these aliases do not need yarn or node_modules.
bash bin/update-since-versions.sh
bash bin/make-pot.sh

rm -f package.* yarn.lock

# Create a tag in the Wordpress.org SVN repo when after your build succeeds via Travis.
# @link https://github.com/BoldGrid/wordpress-tag-sync
chmod +x ./node_modules/@boldgrid/wordpress-tag-sync/release.sh && ./node_modules/@boldgrid/wordpress-tag-sync/release.sh
