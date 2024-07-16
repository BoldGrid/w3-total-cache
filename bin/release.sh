#!/usr/bin/env bash
# Release script for the W3 Total Cache WordPress plugin by BoldGrid.

# Cleanup uneeded git content.
echo 'Finding and deleting .gitignore files.'
find . -name '.gitignore' -type f -delete
echo 'Finding and deleting .git folders.'
find vendor/ -name '.git' -type d -print -exec rm -rf {} +

# Cleanup development and build contents.
rm -f codecov coverage.xml package.* phpcs.xml
rm -rf qa

# Find and replace symlinks in the "vendor" directory.
for i in $(find vendor/ -type l); do \cp -f --remove-destination $(realpath $i) $i;done

# Install WP-CLI
wget -O /tmp/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod +x /tmp/wp

# Update the POT language file.
/tmp/wp i18n make-pot . languages/w3-total-cache.pot

# Create a tag in the Wordpress.org SVN repo when after your build succeeds via Travis.
# @link https://github.com/BoldGrid/wordpress-tag-sync
chmod +x ./node_modules/@boldgrid/wordpress-tag-sync/release.sh && ./node_modules/@boldgrid/wordpress-tag-sync/release.sh
