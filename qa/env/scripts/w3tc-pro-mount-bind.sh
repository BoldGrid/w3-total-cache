#!/usr/bin/env bash

rm -rf ${W3D_WP_PLUGINS_PATH}/w3-total-cache-pro
mkdir ${W3D_WP_PLUGINS_PATH}/w3-total-cache-pro
mount --bind /share/w3tc-pro ${W3D_WP_PLUGINS_PATH}/w3-total-cache-pro/
chown -R www-data:www-data ${W3D_WP_PLUGINS_PATH}/w3-total-cache-pro
exit $?
