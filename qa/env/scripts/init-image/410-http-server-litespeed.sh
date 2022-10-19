sudo wget -O - http://rpms.litespeedtech.com/debian/enable_lst_debian_repo.sh | sudo bash
apt-get install -y openlitespeed

sed -i "s/user .*/user www-data/" /usr/local/lsws/conf/httpd_config.conf
sed -i "s/group .*/user www-data/" /usr/local/lsws/conf/httpd_config.conf
sed -i "s/address.*:8088/address *:80/" /usr/local/lsws/conf/httpd_config.conf
sed -i "s/lsphp74/lsphp80/" /usr/local/lsws/conf/httpd_config.conf
sed -i -e '/map /a\' -e 'map WpSandbox wp.sandbox' /usr/local/lsws/conf/httpd_config.conf
sed -i -e '/map /a\' -e 'map WpSandbox b2.wp.sandbox' /usr/local/lsws/conf/httpd_config.conf
sed -i -e '/map /a\' -e 'map WpSandbox for-tests.wp.sandbox' /usr/local/lsws/conf/httpd_config.conf
sed -i -e '/map /a\' -e 'map ForTestsSandbox for-tests.sandbox' /usr/local/lsws/conf/httpd_config.conf
sed -i "s/path\s+lsphp73\/bin\/lsphp/path lsphp80\/bin\/lsphp/" /usr/local/lsws/conf/httpd_config.conf

cat /share/scripts/init-image/templates/litespeed-root.conf >> /usr/local/lsws/conf/httpd_config.conf




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
envsubst </share/scripts/init-image/templates/litespeed-vhost-for-tests-sandbox.conf >/usr/local/lsws/conf/vhosts/for-tests-sandbox.conf
chown lsadm:nogroup /usr/local/lsws/conf/vhosts/for-tests-sandbox.conf

# system.sandbox
#envsubst </share/scripts/init-image/templates/litespeed-vhost-system-sandbox.conf >/usr/local/lsws/conf/vhosts/system-sandbox.conf

# wp.sandbox vhost
envsubst </share/scripts/init-image/templates/litespeed-vhost-wp-sandbox.conf >/usr/local/lsws/conf/vhosts/wp-sandbox.conf
chown lsadm:nogroup /usr/local/lsws/conf/vhosts/wp-sandbox.conf

touch /var/www/wp.sandbox_error.log
chown www-data:www-data /var/www/wp.sandbox_error.log

# make backup of server config since it will be editable by plugin tests
cp /usr/local/lsws/conf/vhosts/wp-sandbox.conf /usr/local/lsws/conf/vhosts/wp-sandbox-backup.conf
chown lsadm:nogroup /usr/local/lsws/conf/vhosts/wp-sandbox-backup.conf
chmod 777 /usr/local/lsws/conf/vhosts/wp-sandbox-backup.conf

rm -rf /tmp/lshttpd
killall lsphp
systemctl restart lsws
