#!/bin/bash
# Deploy or remove QA PHP settings in the WordPress docroot (output buffering).
#
# - W3D_QA_PHP_OUTPUT_BUFFERING_OFF=1 — install .user.ini and (Apache) htaccess snippet.
# - W3D_QA_PHP_OUTPUT_BUFFERING_OFF=0 — remove what this script installed (idempotent).
# - Unset or any other value — no-op (exit 0).
#
# See qa/env/README.md.

set -e
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TPL_DIR="${SCRIPT_DIR}/templates"
UINI_SRC="${TPL_DIR}/w3tcqa-user.ini"
HTA_SNIP="${TPL_DIR}/w3tcqa-htaccess-php-output.snip"

w3tcqa_uninstall() {
	if [ -z "${W3D_WP_PATH}" ]; then
		echo '755-w3tcqa-php-output-buffering.sh: W3D_WP_PATH is not set' >&2
		exit 1
	fi

	local uini="${W3D_WP_PATH}.user.ini"
	if [ -f "${uini}" ] && grep -q 'W3TC_QA_USER_INI' "${uini}" 2>/dev/null; then
		rm -f "${uini}"
		echo "755-w3tcqa-php-output-buffering.sh: removed ${uini}"
	fi

	local hta="${W3D_WP_PATH}.htaccess"
	if [ ! -f "${hta}" ]; then
		return 0
	fi

	if grep -q '^# W3TC_QA_PHP_OUTPUT_BUFFERING_BEGIN$' "${hta}" 2>/dev/null &&
		grep -q '^# W3TC_QA_PHP_OUTPUT_BUFFERING_END$' "${hta}" 2>/dev/null; then
		sed -i '/^# W3TC_QA_PHP_OUTPUT_BUFFERING_BEGIN$/,/^# W3TC_QA_PHP_OUTPUT_BUFFERING_END$/d' "${hta}"
		chown www-data:www-data "${hta}" 2>/dev/null || true
		echo "755-w3tcqa-php-output-buffering.sh: removed W3TC QA block from ${hta}"
		return 0
	fi

	# Legacy block (before BEGIN/END markers): three IfModule sections after our comment line.
	if grep -q '# W3TC QA: php output_buffering' "${hta}" 2>/dev/null &&
		! grep -q 'W3TC_QA_PHP_OUTPUT_BUFFERING_BEGIN' "${hta}" 2>/dev/null; then
		if command -v perl >/dev/null 2>&1; then
			perl -i -ne '
				BEGIN { $skip = 0; $count = 0 }
				if ($skip) {
					if (m{</IfModule>}) {
						$count++;
						if ($count == 3) { $skip = 0; }
					}
					next;
				}
				if (m{# W3TC QA: php output_buffering}) {
					$skip = 1;
					$count = 0;
					next;
				}
				print;
			' "${hta}"
			chown www-data:www-data "${hta}" 2>/dev/null || true
			echo "755-w3tcqa-php-output-buffering.sh: removed legacy W3TC QA htaccess block from ${hta}"
		else
			echo '755-w3tcqa-php-output-buffering.sh: perl not found; remove legacy htaccess block manually' >&2
		fi
	fi
}

w3tcqa_install() {
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
		if [ -f "${W3D_WP_PATH}.htaccess" ] &&
			! grep -q '^# W3TC_QA_PHP_OUTPUT_BUFFERING_BEGIN$' "${W3D_WP_PATH}.htaccess" 2>/dev/null; then
			cat "${HTA_SNIP}" >>"${W3D_WP_PATH}.htaccess"
			chown www-data:www-data "${W3D_WP_PATH}.htaccess"
		fi
	fi
}

case "${W3D_QA_PHP_OUTPUT_BUFFERING_OFF}" in
	1)
		w3tcqa_install
		;;
	0)
		w3tcqa_uninstall
		;;
	*)
		exit 0
		;;
esac
