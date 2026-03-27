#!/bin/bash
# Deploy QA PHP settings to turn off implicit output buffering in the WordPress docroot.
#
# Opt-in only: does nothing unless W3D_QA_PHP_OUTPUT_BUFFERING_OFF=1 (set in the box
# environment file under /share/environments/, or export before running manually).
# This avoids changing PHP behavior for the full default test matrix.
#
# - .user.ini: php-fpm (and cgi) typically honor this per user_ini.filename when the
#   docroot is the WordPress install path (works behind Apache or Nginx).
# - .htaccess snippet: mod_php only, when AllowOverride allows php_value.
#
# Invoked from 800-w3tc.sh (harmless when the flag is unset). See qa/env/README.md.
#
# Requires templates next to this script (AMI copies qa/env/scripts/init-box to /share/scripts/init-box).

set -e

if [ "${W3D_QA_PHP_OUTPUT_BUFFERING_OFF}" != "1" ]; then
	exit 0
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TPL_DIR="${SCRIPT_DIR}/templates"
UINI_SRC="${TPL_DIR}/w3tcqa-user.ini"
HTA_SNIP="${TPL_DIR}/w3tcqa-htaccess-php-output.snip"

if [ -z "${W3D_WP_PATH}" ]; then
	echo '755-w3tcqa-php-output-buffering.sh: W3D_WP_PATH is not set' >&2
	exit 1
fi

if [ ! -f "${UINI_SRC}" ]; then
	echo "755-w3tcqa-php-output-buffering.sh: missing ${UINI_SRC}" >&2
	exit 1
fi

cp -f "${UINI_SRC}" "${W3D_WP_PATH}.user.ini"
chown www-data:www-data "${W3D_WP_PATH}.user.ini"
chmod 644 "${W3D_WP_PATH}.user.ini"

if [ "${W3D_HTTP_SERVER}" = 'apache' ] && [ -f "${HTA_SNIP}" ]; then
	if [ -f "${W3D_WP_PATH}.htaccess" ] && ! grep -q 'W3TC QA: php output_buffering' "${W3D_WP_PATH}.htaccess" 2>/dev/null; then
		cat "${HTA_SNIP}" >>"${W3D_WP_PATH}.htaccess"
		chown www-data:www-data "${W3D_WP_PATH}.htaccess"
	fi
fi
