#!/usr/bin/env bash

set -e

set -a
[ -r /etc/environment ] && . /etc/environment
set +a

LIMITED="sudo -u www-data --preserve-env=PATH"

cd $W3D_WP_PATH
$LIMITED wp option update home "${W3D_HTTP_SERVER_SCHEME}://${W3D_WP_HOST}${W3D_WP_MAYBE_COLON_PORT}${W3D_WP_HOME_URI}"

mkdir -p /var/www/wp-sandbox/moved-folders
chown www-data:www-data /var/www/wp-sandbox/moved-folders
chmod 755 /var/www/wp-sandbox/moved-folders
mv ${W3D_WP_PATH}wp-content/plugins $W3D_WP_PLUGINS_PATH

mv ${W3D_WP_PATH}wp-content $W3D_WP_CONTENT_PATH
cp ${W3D_WP_PATH}index.php /var/www/wp-sandbox${W3D_WP_HOME_URI}index.php

sed -i "2idefine( \"WP_CONTENT_DIR\", rtrim( \"$W3D_WP_CONTENT_PATH\", \"/\" ) );\\ndefine( \"WP_CONTENT_URL\", rtrim( \"$W3D_HTTP_SERVER_SCHEME://\" . \$_SERVER[\"HTTP_HOST\"] . \"$W3D_WP_CONTENT_URI\", \"/\" ) );\\ndefine( \"WP_PLUGIN_DIR\", rtrim( \"$W3D_WP_PLUGINS_PATH\", \"/\" ) );\\ndefine( \"WP_PLUGIN_URL\", rtrim( \"$W3D_HTTP_SERVER_SCHEME://\" . \$_SERVER[\"HTTP_HOST\"] . \"$W3D_WP_PLUGINS_URI\", \"/\" ) );" wp-config.php

cd /var/www/wp-sandbox${W3D_WP_HOME_URI}
sed -i "s%dirname( __FILE__ )%\"${W3D_WP_PATH}\"%" index.php
sed -i "s%__DIR__%\"${W3D_WP_PATH}\"%" index.php

front_index="/var/www/wp-sandbox${W3D_WP_HOME_URI}index.php"
if [ ! -f "$front_index" ]; then
	echo "pathmoved front index.php missing at ${front_index}"
	exit 1
fi

if [ "$W3D_HTTP_SERVER" = 'apache' ]; then
	mv ${W3D_WP_PATH}.htaccess /var/www/wp-sandbox/
fi

# fix of network-admin urls
if [ "$W3D_WP_NETWORK" = "subdomain" ] || [ "$W3D_WP_NETWORK" = "subdir" ]; then
	mkdir -pv /var/www/wp-sandbox/wp-admin
	chown www-data:www-data /var/www/wp-sandbox/wp-admin
	chmod 755 /var/www/wp-sandbox/wp-admin

	echo "RewriteEngine On" >/var/www/wp-sandbox/wp-admin/.htaccess
	echo "RewriteRule ^(.*)${D} /wp/wp-admin/${D}1 [L]" >>/var/www/wp-sandbox/wp-admin/.htaccess
	chown www-data:www-data /var/www/wp-sandbox/wp-admin/.htaccess
	chmod 755 /var/www/wp-sandbox/wp-admin/.htaccess
fi
