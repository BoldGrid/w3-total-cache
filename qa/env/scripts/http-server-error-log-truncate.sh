#!/usr/bin/env bash

echo > $W3D_HTTP_SERVER_ERROR_LOG_FILENAME
echo > "${W3D_WP_CONTENT_PATH}debug.log"
chmod 666 "${W3D_WP_CONTENT_PATH}debug.log"
/share/scripts/restart-http.rb
echo truncated
