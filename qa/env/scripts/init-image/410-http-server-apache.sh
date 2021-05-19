case "${W3D_PHP_VERSION}" in
    "5.3")
        apt-get install -y apache2 libapache2-mod-php5
        ;;
    "5.5")
		apt-get install -y apache2 libapache2-mod-php5
        ;;
    "5.6")
		apt-get install -y apache2 libapache2-mod-php5.6
        ;;
    "7.0")
		apt-get install -y apache2 libapache2-mod-php7.0
        ;;
    "7.1")
        apt-get install -y apache2 libapache2-mod-php7.1
        ;;
	"7.3")
        apt-get install -y apache2 libapache2-mod-php7.3
        ;;
	"8.0")
        apt-get install -y apache2 libapache2-mod-php8.0
        ;;
    *)
        echo "W3D_PHP_VERSION not met conditions, do nothing....."
        ;;
esac

a2enmod expires
a2enmod headers
a2enmod rewrite

if [ "$W3D_HTTP_SERVER_SCHEME" = "https" ]; then
	a2enmod ssl

	export W3TC_APACHE_SSL1="SSLEngine on"
    export W3TC_APACHE_SSL2="SSLCertificateFile /etc/ssl/www/sandbox.crt"
    export W3TC_APACHE_SSL3="SSLCertificateKeyFile /etc/ssl/www/sandbox.key"
fi

if [ "$W3D_HTTP_SERVER_PORT" = "443" ]; then
	sed -i "s/Listen 80//" /etc/apache2/ports.conf
fi
if [ "$W3D_HTTP_SERVER_PORT" != "80" ]; then
	sed -i "s/Listen 80/Listen $W3D_HTTP_SERVER_PORT/" /etc/apache2/ports.conf
fi

# Prevent warning Could not reliably determine the server's fully qualified domain name
echo "ServerName localhost" >> /etc/apache2/apache2.conf

a2dissite 000-default

export W3TC_APACHE_REQUIRE="Require all granted"

if [ "${W3D_OS}" = "precise" ]; then
    export W3TC_A2ENSITE_POSTFIX=".conf"
    export W3TC_APACHE_REQUIRE=""
fi

# for-tests.sandbox
envsubst </share/scripts/init-image/templates/apache-vhost-for-tests-sandbox.conf >/etc/apache2/sites-available/for-tests-sandbox.conf
a2ensite for-tests-sandbox${W3TC_A2ENSITE_POSTFIX}

# system.sandbox
envsubst </share/scripts/init-image/templates/apache-vhost-system-sandbox.conf >/etc/apache2/sites-available/system-sandbox.conf
a2ensite system-sandbox${W3TC_A2ENSITE_POSTFIX}

# wp.sandbox vhost
envsubst </share/scripts/init-image/templates/apache-vhost-wp-sandbox.conf >/etc/apache2/sites-available/wp-sandbox.conf
a2ensite wp-sandbox${W3TC_A2ENSITE_POSTFIX}

service apache2 restart
