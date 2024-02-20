#!/usr/bin/env bash

cat $W3D_HTTP_SERVER_ERROR_LOG_FILENAME |\
	grep -v "class-simplepie.php" |\
	grep -v "in /var/www/wp-sandbox/.*/post.php"  |\
	grep -v "File does not exist:.*404.jpg" |\
	grep -v "File does not exist: /var/www/wp-sandbox/.*b2$" |\
	grep -v "open().*404.jpg.*failed.*No such file or directory" |\
	grep -v "wp-includes/pluggable.php on line 216" |\
	pcregrep -v -M "Call to undefined function esc_attr[()]+ in .*PgCache_ContentGrabber.php.*eval(\n|.)*?request" |\
	grep -v "Call to undefined function esc_attr() in .*PgCache_ContentGrabber.php.*eval()" |\
	grep -v "script '/var/www/wp-sandbox/wp-signup.php' not found or unable to stat" |\
	grep -v "wp-admin/includes/dashboard.php on line 1227" |\
	grep -v "WP_Community_Events::maybe_log_events_response" |\
	grep -v "wp-includes/Requests/Transport/cURL.php:422" |\
	grep -v "Undefined index: recommended_version in /var/www/.*/class-wp-site-health.php" |\
	grep -v "Undefined index: no_update in /var/www/.*/update.php" |\
	grep -v "Only the first byte will be assigned to the string offset in /var/www/.*/class.wp-scripts.php" |\
	grep -v "Undefined array key .no_update. in /var/www/.*/update.php" |\
	grep -v "Undefined array key \"recommended_version\" in /var/www/.*/class-wp-site-health.php" |\
	grep -v 'Function wp_update_https_detection_errors is deprecated'

if [ -f "${W3D_WP_CONTENT_PATH}debug.log" ]; then
	cat "${W3D_WP_CONTENT_PATH}debug.log" |\
		grep -v "class-simplepie.php" |\
		grep -v "in /var/www/wp-sandbox/.*/post.php"  |\
		grep -v "File does not exist:.*404.jpg" |\
		grep -v "File does not exist: /var/www/wp-sandbox/.*b2$" |\
		grep -v "open().*404.jpg.*failed.*No such file or directory" |\
		grep -v "wp-includes/pluggable.php on line 216" |\
		pcregrep -v -M "Call to undefined function esc_attr[()]+ in .*PgCache_ContentGrabber.php.*eval(\n|.)*?request" |\
		grep -v "Call to undefined function esc_attr() in .*PgCache_ContentGrabber.php.*eval()" |\
		grep -v "script '/var/www/wp-sandbox/wp-signup.php' not found or unable to stat" |\
		grep -v "wp-admin/includes/dashboard.php on line 1227" |\
		grep -v "WP_Community_Events::maybe_log_events_response" |\
		grep -v "wp-includes/Requests/Transport/cURL.php:422" |\
		grep -v "Undefined index: recommended_version in /var/www/.*/class-wp-site-health.php" |\
		grep -v "Undefined index: no_update in /var/www/.*/update.php" |\
		grep -v "Only the first byte will be assigned to the string offset in /var/www/.*/class.wp-scripts.php" |\
		grep -v "Undefined array key .no_update. in /var/www/.*/update.php" |\
		grep -v "Undefined array key \"recommended_version\" in /var/www/.*/class-wp-site-health.php" |\
		grep -v 'Function wp_update_https_detection_errors is deprecated'
fi
# esc_attr in eval - pagecache/late-init test
# in php7.0-fpm call stack is printed so should be eliminated

# File does not exist: /var/www/wp-sandbox/.*b2
# happens on /b2/404.jpg request (404 response expected)
# and is fine because no b2 folder exists for this url

# script '/var/www/wp-sandbox/wp-signup.php' not found or unable to stat
# happens in cdn/srcset in pathmoved boxes

# "wp-admin/includes/dashboard.php on line 1227"
# news loader gets some wrong url
