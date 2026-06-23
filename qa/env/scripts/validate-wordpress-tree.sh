#!/usr/bin/env bash

set -a
[ -r /etc/environment ] && . /etc/environment
set +a

tree_root="${1:-/var/www/wp-sandbox}"
sandbox_base="/var/www/wp-sandbox"

wp_path="${W3D_WP_PATH}"
home_path="${W3D_WP_HOME_PATH:-${W3D_WP_PATH}}"

if [ -z "$wp_path" ]; then
	echo "W3D_WP_PATH is not set"
	exit 1
fi

wp_suffix="${wp_path#${sandbox_base}}"
home_suffix="${home_path#${sandbox_base}}"

wp_index="${tree_root}${wp_suffix}index.php"
if [ ! -f "$wp_index" ]; then
	echo "WordPress index.php missing at ${wp_index}"
	exit 1
fi

if [ "$home_path" != "$wp_path" ]; then
	home_index="${tree_root}${home_suffix}index.php"
	if [ ! -f "$home_index" ]; then
		echo "WordPress front index.php missing at ${home_index}"
		exit 1
	fi
fi
