#!/bin/sh

# Functions
php_5x () {
    apt-get install -y php5.6-cli php5.6-mysql php5.6-curl php5.6-xml

    if [ "$W3D_APC" = "apc" ]; then
        apt-get install -y php5.6-apcu
    fi
    if [ "$W3D_APC" = "apcu" ]; then
        apt-get install -y php5.6-apcu
    fi
    if [ "$W3D_MEMCACHE" = "memcache" ]; then
        apt-get install -y php5.6-memcache
    fi
    if [ "$W3D_MEMCACHE" = "memcached" ]; then
        apt-get install -y php5.6-memcached
    fi
    if [ "$W3D_REDIS" = "redis" ]; then
        apt-get install -y php5.6-redis
    fi
    if [ "$W3D_XCACHE" = "xcache" ]; then
        apt-get install -y php5.6-xcache
    fi

}

php_ondrej_common () {
    if [ "$W3D_APC" = "apcu" ]; then
        apt-get install -y php-apcu
    fi
    if [ "$W3D_MEMCACHE" = "memcached" ]; then
        apt-get install -y php-memcached
    fi
    if [ "$W3D_REDIS" = "redis" ]; then
        apt-get install -y php-redis
    fi
}


# PHP Version detection and execution of installation
locale -a
export LANG=C.UTF-8
export | grep LANG

## Switch Case Style for PHP to maintain different versions
case "${W3D_PHP_VERSION}" in
	# php7.0-xml required by wordpress, fails otherwise on pings

    "5.3") echo "Installing PHP 5.3"
        php_5x
        ;;
    "5.5") echo "Installing PHP 5.5"
        php_5x
        ;;
    "5.6") echo "Installing PHP 5.6"
        add-apt-repository -y ppa:ondrej/php
        apt-get update
        php_5x
        ;;
    "7.0") echo "Installing PHP 7.0"
        add-apt-repository -y ppa:ondrej/php
        apt-get update
	    apt-get install -y php7.0-common php7.0-cli php7.0-mysql php7.0-curl php7.0-xml

		if [ "$W3D_APC" = "apcu" ]; then
	        apt-get install -y php7.0-apcu
	    fi
	    if [ "$W3D_MEMCACHE" = "memcached" ]; then
	        apt-get install -y php7.0-memcached
	    fi
	    if [ "$W3D_REDIS" = "redis" ]; then
	        apt-get install -y php7.0-redis
	    fi
        ;;
	"7.3") echo "Installing PHP 7.3"
		add-apt-repository -y ppa:ondrej/php
		apt-get update
        apt-get install -y php7.3-common php7.3-cli php7.3-mysql php7.3-curl php7.3-xml

		if [ "$W3D_APC" = "apcu" ]; then
	        apt-get install -y php7.3-apcu
	    fi
	    if [ "$W3D_MEMCACHE" = "memcached" ]; then
	        apt-get install -y php7.3-memcached
	    fi
	    if [ "$W3D_REDIS" = "redis" ]; then
	        apt-get install -y php7.3-redis
	    fi
        ;;
	"8.0") echo "Installing PHP 8.0"
		add-apt-repository -y ppa:ondrej/php
		apt-get update
        apt-get install -y php8.0-common php8.0-cli php8.0-mysql php8.0-curl php8.0-xml
		php_ondrej_common
        ;;
    *)
        echo "W3D_PHP_VERSION not met conditions, do nothing..... ${W3D_PHP_VERSION}"
        ;;
esac
