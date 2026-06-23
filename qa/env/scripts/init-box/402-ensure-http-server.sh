#!/usr/bin/env bash

set -a
[ -r /etc/environment ] && . /etc/environment
set +a

if [ "$W3D_HTTP_SERVER" = "apache" ]; then
	if ! command -v apache2 >/dev/null 2>&1; then
		echo "apache2 missing on AMI; running init-image http-server setup"
		/share/scripts/init-image/400-http-server.sh
	fi
elif [ "$W3D_HTTP_SERVER" = "nginx" ]; then
	if ! command -v nginx >/dev/null 2>&1; then
		echo "nginx missing on AMI; running init-image http-server setup"
		/share/scripts/init-image/400-http-server.sh
	fi
fi
