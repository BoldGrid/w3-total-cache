#!/usr/bin/env bash

set -a
[ -r /etc/environment ] && . /etc/environment
set +a

umount ${W3D_WP_PLUGINS_PATH}w3-total-cache >/dev/null 2>/dev/null
rm -rf ${W3D_WP_PLUGINS_PATH}w3-total-cache
