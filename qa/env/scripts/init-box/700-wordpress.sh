URL="${W3D_HTTP_SERVER_SCHEME}://${W3D_WP_HOST}${W3D_WP_MAYBE_COLON_PORT}${W3D_WP_SITE_URI}"
LIMITED="sudo -u www-data"

mkdir -pv ${W3D_WP_PATH}
curl -L --silent --show-error http://wordpress.org/wordpress-${W3D_WP_VERSION}.tar.gz --output ${W3D_WP_PATH}wordpress.tar.gz
cd ${W3D_WP_PATH}
tar xzf ${W3D_WP_PATH}wordpress.tar.gz --strip-components=1
chown -R www-data:www-data ${W3D_WP_PATH}

mysql -uroot <<SQL
CREATE DATABASE wordpress;
CREATE USER wordpress@localhost IDENTIFIED BY 'wordpress';
GRANT USAGE ON *.* TO wordpress@localhost;
GRANT ALL PRIVILEGES ON wordpress.* TO wordpress@localhost;
FLUSH PRIVILEGES;
SQL

$LIMITED wp core config --dbname=wordpress --dbuser=wordpress --dbpass=wordpress --extra-php <<PHP
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
PHP

cd $W3D_WP_PATH
$LIMITED wp core install --url=$URL --title=sandbox --admin_user=admin --admin_password=1 --admin_email=a@b.com
# set predefined time format (expected by QA changing post's time)
$LIMITED wp option set time_format "H:i"

# disable any automatic updates
sed -i '2idefine( \"AUTOMATIC_UPDATER_DISABLED\", true );' wp-config.php

# add header mark showing PHP was executed
sed -i '2iheader( \"w3tc_php: executed\" );' wp-config.php

# disable all api calls to check for updates
cp /share/scripts/init-box/templates/disable-wp-updates.php ./disable-wp-updates.php
chown www-data:www-data ./disable-wp-updates.php

sed -i '/require_wp_db();/a require( ABSPATH . \"/disable-wp-updates.php\" );   //w3tc test' wp-settings.php

# change url structure
$LIMITED wp rewrite structure '/%year%/%monthnum%/%day%/%postname%/'
$LIMITED wp rewrite flush --hard

# extras
if [ "$W3D_WP_NETWORK" = "subdomain" ] || [ "$W3D_WP_NETWORK" = "subdir" ]; then
	/share/scripts/init-box/720-wordpress-network.sh
fi

if [ "$W3D_HTTP_SERVER" = "apache" ]; then
	/share/scripts/init-box/750-wordpress-apache-htaccess.sh
fi

if [ "$W3D_WP_CONTENT_PATH" != "${W3D_WP_PATH}wp-content/" ]; then
	/share/scripts/init-box/760-wordpress-pathmoved.sh
fi
