#!/usr/bin/env bash

set -a
[ -r /etc/environment ] && . /etc/environment
set +a

if [ -z "$W3D_WP_HOST" ]; then
	exit 0
fi

if ! grep -qF "$W3D_WP_HOST" /etc/hosts; then
	host_lower=$(printf '%s' "$W3D_WP_HOST" | tr '[:upper:]' '[:lower:]')
	if [[ "$host_lower" == *.sandbox ]]; then
		SANDBOX_IP='10.127.0.1'
		ip addr add "${SANDBOX_IP}/32" dev lo 2>/dev/null || true
		echo "${SANDBOX_IP} $W3D_WP_HOST" >>/etc/hosts
	else
		echo "127.0.0.1 $W3D_WP_HOST" >>/etc/hosts
	fi
fi
