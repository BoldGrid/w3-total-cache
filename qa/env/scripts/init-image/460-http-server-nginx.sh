# install nginx
apt-get install -y nginx

cp -f /share/scripts/init-image/templates/nginx-fastcgi_params /etc/nginx/fastcgi_params
chmod 777 /etc/nginx/fastcgi_params

rm /etc/nginx/sites-enabled/default

# create vhost wp.sandox
# header
if [ "$W3D_HTTP_SERVER_SCHEME" = "https" ]; then
	export NGINX_LISTEN_SSL="ssl"
	export NGINX_SSL1="ssl_certificate /etc/ssl/www/sandbox.crt;"
	export NGINX_SSL2="ssl_certificate_key /etc/ssl/www/sandbox.key;"
fi

envsubst </share/scripts/init-image/templates/nginx-vhost-wp-sandbox.conf >/etc/nginx/sites-available/wp-sandbox.conf
touch /etc/nginx/sites-available/wp-sandbox-include.conf
ln -s /etc/nginx/sites-available/wp-sandbox.conf /etc/nginx/sites-enabled/

# for-tests vhost
envsubst </share/scripts/init-image/templates/nginx-vhost-for-tests-sandbox.conf >/etc/nginx/sites-available/for-tests-sandbox.conf
ln -s /etc/nginx/sites-available/for-tests-sandbox.conf /etc/nginx/sites-enabled/

# system vhost
envsubst </share/scripts/init-image/templates/nginx-vhost-system-sandbox.conf >/etc/nginx/sites-available/system-sandbox.conf
ln -s /etc/nginx/sites-available/system-sandbox.conf /etc/nginx/sites-enabled/

# restart
/etc/init.d/nginx restart
