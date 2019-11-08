#!/usr/bin/env bash

. /etc/environment

echo '--- cleanup'
bash /share/scripts/w3tc-umount.sh
rm -rf /var/www/wp-sandbox
cp -r /var/www/backup-w3tc-inactive-wp-sandbox /var/www/wp-sandbox
mkdir ${W3D_WP_PLUGINS_PATH}w3-total-cache
bash /share/scripts/w3tc-mount.sh
chown -R www-data:www-data /var/www/wp-sandbox
mysql < /var/www/backup-w3tc-inactive.sql
service mysql restart
