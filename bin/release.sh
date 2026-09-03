#!/usr/bin/env bash
# Release script for the W3 Total Cache WordPress plugin by BoldGrid.

# Cleanup uneeded git content.
echo 'Finding and deleting .gitignore files.'
find . -name '.git*' -type f -delete
echo 'Finding and deleting .git folders.'
find vendor/ -name '.git' -type d -print -exec rm -rf {} +

# Cleanup development and build contents (keep package.json until after yarn aliases).
rm -f .jshintrc AGENTS.md CLAUDE.md codecov coverage.xml phpcs.xml
rm -rf .claude .cursor .github qa

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
