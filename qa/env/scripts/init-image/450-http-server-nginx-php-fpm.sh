
case "${W3D_PHP_VERSION}" in
    "5.3")
		apt-get install -y php5-fpm
		cp -f /share/scripts/init-image/templates/php-fpm-pool-www.conf /etc/php5/fpm/pool.d/www.conf
		service php5-fpm restart
        ;;
    "5.5")
		apt-get install -y php5-fpm
		cp -f /share/scripts/init-image/templates/php-fpm-pool-www.conf /etc/php5/fpm/pool.d/www.conf
		service php5-fpm restart
        ;;
    "5.6")
		apt-get install -y php5.6-fpm
		cp -f /share/scripts/init-image/templates/php-fpm-pool-www.conf /etc/php5/fpm/pool.d/www.conf
		service php5.6-fpm restart
        ;;
    "7.0")
		apt-get install -y php7.0-fpm
		cp -f /share/scripts/init-image/templates/php-fpm-pool-www.conf /etc/php/7.0/fpm/pool.d/www.conf
		service php7.0-fpm restart
        ;;
    "7.1")
		apt-get install -y php7.1-fpm
		cp -f /share/scripts/init-image/templates/php-fpm-pool-www.conf /etc/php/7.1/fpm/pool.d/www.conf
		service php7.1-fpm restart
        ;;
	"7.3")
		apt-get install -y php7.3-fpm
		cp -f /share/scripts/init-image/templates/php-fpm-pool-www.conf /etc/php/7.3/fpm/pool.d/www.conf
		service php7.3-fpm restart
        ;;
	"8.0")
		apt-get install -y php8.0-fpm
		cp -f /share/scripts/init-image/templates/php-fpm-pool-www.conf /etc/php/8.0/fpm/pool.d/www.conf
		service php8.0-fpm restart
        ;;
    *)
        echo "W3D_PHP_VERSION not met conditions, do nothing....."
        ;;
esac
