#!/usr/bin/env bash

sleep 1 # Wait 1 for the last request to finish before deleting the plugin files.
rm -rf ${W3D_WP_PLUGINS_PATH}/w3-total-cache
cp -R /share/w3tc ${W3D_WP_PLUGINS_PATH}/w3-total-cache
chown -R www-data:www-data ${W3D_WP_PLUGINS_PATH}/w3-total-cache
exit $?
