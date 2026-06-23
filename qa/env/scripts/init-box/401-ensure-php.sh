#!/usr/bin/env bash

set -a
[ -r /etc/environment ] && . /etc/environment
set +a

ensure_php_alternative() {
	local php_bin="$1"

	if [ ! -x "$php_bin" ]; then
		return 1
	fi

	if command -v php >/dev/null 2>&1; then
		return 0
	fi

	update-alternatives --set php "$php_bin" 2>/dev/null || ln -sf "$php_bin" /usr/bin/php
}

ensure_php_zip() {
	if php -r 'exit(class_exists("ZipArchive") ? 0 : 1);'; then
		return 0
	fi

	local zip_pkg=""
	case "${W3D_PHP_VERSION}" in
		5.6) zip_pkg=php5.6-zip ;;
		7.0) zip_pkg=php7.0-zip ;;
		7.3) zip_pkg=php7.3-zip ;;
		7.4) zip_pkg=php7.4-zip ;;
		8.0) zip_pkg=php8.0-zip ;;
		8.1) zip_pkg=php8.1-zip ;;
		8.5) zip_pkg=php8.5-zip ;;
	esac

	if [ -z "$zip_pkg" ]; then
		echo "unknown W3D_PHP_VERSION for zip install: ${W3D_PHP_VERSION}"
		return 1
	fi

	echo "php zip extension missing; installing ${zip_pkg}"
	apt-get update
	apt-get install -y "$zip_pkg"

	if ! php -r 'exit(class_exists("ZipArchive") ? 0 : 1);'; then
		return 1
	fi
}

case "${W3D_PHP_VERSION}" in
	5.6) ensure_php_alternative /usr/bin/php5.6 ;;
	7.0) ensure_php_alternative /usr/bin/php7.0 ;;
	7.3) ensure_php_alternative /usr/bin/php7.3 ;;
	7.4) ensure_php_alternative /usr/bin/php7.4 ;;
	8.0) ensure_php_alternative /usr/bin/php8.0 ;;
	8.1) ensure_php_alternative /usr/bin/php8.1 ;;
	8.5) ensure_php_alternative /usr/bin/php8.5 ;;
esac

if ! command -v php >/dev/null 2>&1; then
	echo "php missing on AMI; running init-image PHP setup"
	/share/scripts/init-image/300-php.sh
fi

if ! command -v php >/dev/null 2>&1; then
	echo "php still missing after init-image/300-php.sh (W3D_PHP_VERSION=${W3D_PHP_VERSION})"
	exit 1
fi

if ! ensure_php_zip; then
	echo "php zip extension still missing after install attempt"
	exit 1
fi

php -v
