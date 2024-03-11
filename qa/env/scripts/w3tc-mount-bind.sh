#!/usr/bin/env bash

umount ${W3D_WP_PLUGINS_PATH}w3-total-cache >/dev/null 2>/dev/null
rm -rf ${W3D_WP_PLUGINS_PATH}/w3-total-cache
mkdir ${W3D_WP_PLUGINS_PATH}/w3-total-cache
mount --bind /share/w3tc ${W3D_WP_PLUGINS_PATH}/w3-total-cache/
chown -R www-data:www-data ${W3D_WP_PLUGINS_PATH}/w3-total-cache
exit $?
