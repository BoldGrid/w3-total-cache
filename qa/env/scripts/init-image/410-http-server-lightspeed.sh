sudo wget -O - http://rpms.litespeedtech.com/debian/enable_lst_debian_repo.sh | sudo bash
apt-get install -y openlitespeed

sed -i "s/user .*/user www-data/" /usr/local/lsws/conf/httpd_config.conf
sed -i "s/group .*/user www-data/" /usr/local/lsws/conf/httpd_config.conf
sed -i "s/address.*:8088/address *:80/" /usr/local/lsws/conf/httpd_config.conf
sed -i -e '/map /a\' -e 'map WpSandbox wp.sandbox' /usr/local/lsws/conf/httpd_config.conf
sed -i "s/path\s+lsphp73\/bin\/lsphp/path lsphp80\/bin\/lsphp/" /usr/local/lsws/conf/httpd_config.conf

cat /share/scripts/init-image/templates/lightspeed-root.conf >> /usr/local/lsws/conf/httpd_config.conf




case "${W3D_PHP_VERSION}" in
	"8.0")
        apt-get install -y lsphp80 lsphp80-common lsphp80-curl lsphp80-mysql lsphp80-opcache
		sed -i "s/lsphp73/lsphp80/" /usr/local/lsws/conf/httpd_config.conf

		if [ "$W3D_APC" = "apcu" ]; then
			apt-get install -y lsphp80-apcu
		fi
		if [ "$W3D_MEMCACHE" = "memcached" ]; then
			apt-get install -y lsphp80-memcached
		fi
		if [ "$W3D_REDIS" = "redis" ]; then
			apt-get install -y lsphp80-redis
		fi

        ;;
    *)
        echo "W3D_PHP_VERSION not met conditions, do nothing....."
        ;;
esac

# for-tests.sandbox
#envsubst </share/scripts/init-image/templates/apache-vhost-for-tests-sandbox.conf >/etc/apache2/sites-available/for-tests-sandbox.conf
#a2ensite for-tests-sandbox${W3TC_A2ENSITE_POSTFIX}

# system.sandbox
#envsubst </share/scripts/init-image/templates/apache-vhost-system-sandbox.conf >/etc/apache2/sites-available/system-sandbox.conf
#a2ensite system-sandbox${W3TC_A2ENSITE_POSTFIX}

# wp.sandbox vhost
envsubst </share/scripts/init-image/templates/lightspeed-vhost-wp-sandbox.conf >/usr/local/lsws/conf/vhosts/wp-sandbox.conf

touch /var/www/wp.sandbox_error.log
chown www-data:www-data /var/www/wp.sandbox_error.log

rm -rf /tmp/lshttpd
killall lsphp
systemctl restart lsws
