#!/usr/bin/env bash

rm -rf ${W3D_WP_PLUGINS_PATH}/w3-total-cache
mkdir ${W3D_WP_PLUGINS_PATH}/w3-total-cache
mount --bind /share/w3tc ${W3D_WP_PLUGINS_PATH}/w3-total-cache/
chown -R www-data:www-data ${W3D_WP_PLUGINS_PATH}/w3-total-cache
exit $?
