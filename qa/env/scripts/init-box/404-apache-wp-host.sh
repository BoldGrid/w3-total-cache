#!/usr/bin/env bash

set -a
[ -r /etc/environment ] && . /etc/environment
set +a

if [ "$W3D_HTTP_SERVER" != "apache" ]; then
	exit 0
fi

if [ -z "$W3D_WP_HOST" ] || [ "$W3D_WP_HOST" = "wp.sandbox" ]; then
	exit 0
fi

conf=/etc/apache2/sites-available/wp-sandbox.conf
if [ -f "$conf" ] && ! grep -qF "ServerAlias ${W3D_WP_HOST}" "$conf"; then
	sed -i "/ServerName wp.sandbox/a\\\tServerAlias ${W3D_WP_HOST}" "$conf"
	service apache2 reload
fi
