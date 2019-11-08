# install http server

# create www directories
mkdir -pv /var/www/
mkdir /var/www/for-tests-sandbox
mkdir /var/www/system-sandbox
mkdir -pv /var/www/wp-sandbox
chown -R www-data:www-data /var/www/

# https part
if [ "$W3D_HTTP_SERVER_SCHEME" = "https" ]; then
  mkdir /etc/ssl/www
  openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout /etc/ssl/www/sandbox.key -out /etc/ssl/www/sandbox.crt -config /share/scripts/init-image/templates/ssl-certificate.conf -batch 2>/dev/null
  cp /etc/ssl/www/*.crt /usr/local/share/ca-certificates
  update-ca-certificates
fi

if [ "$W3D_HTTP_SERVER" = "apache" ]; then
	/share/scripts/init-image/410-http-server-apache.sh
fi

if [ "$W3D_HTTP_SERVER" = "nginx" ]; then
	/share/scripts/init-image/450-http-server-nginx-php-fpm.sh
	/share/scripts/init-image/460-http-server-nginx.sh
fi
