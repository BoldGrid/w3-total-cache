#!/usr/bin/env bash

if [ -z "${W3D_HTTP_SERVER_ERROR_LOG_FILENAME}" ]; then
	exit 0
fi

log_dir=$(dirname "${W3D_HTTP_SERVER_ERROR_LOG_FILENAME}")
mkdir -p "${log_dir}"
touch "${W3D_HTTP_SERVER_ERROR_LOG_FILENAME}"
