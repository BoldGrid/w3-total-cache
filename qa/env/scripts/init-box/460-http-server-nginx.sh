# create vhost wp.sandox
# header
if [ "$W3D_HTTP_SERVER_SCHEME" = "https" ]; then
	export NGINX_LISTEN_SSL="ssl"
	export NGINX_SSL1="ssl_certificate /etc/ssl/www/sandbox.crt;"
	export NGINX_SSL2="ssl_certificate_key /etc/ssl/www/sandbox.key;"
fi

export NGINX_PATHMOVED_FIX=""
if [ "$W3D_WP_NETWORK" = "subdir" ] || [ "$W3D_WP_NETWORK" = "subdomain" ]; then
	if [ "$W3D_WP_SITE_URI" != "$W3D_WP_HOME_URI" ]; then
		export NGINX_PATHMOVED_FIX="rewrite ^/wp-admin/network/(.*)${D} ${W3D_WP_SITE_URI}wp-admin/network/${D}1 last;"
	fi
fi

envsubst </share/scripts/init-box/templates/nginx-vhost-wp-sandbox-1.conf >/etc/nginx/sites-available/wp-sandbox-include.conf

# subdir specific
if [ "$W3D_WP_NETWORK" = "subdir" ]; then
	export NGINX_SITE_TO_HOME_URI="/"
	if [ "$W3D_WP_SITE_URI" != "$W3D_WP_HOME_URI" ]; then
	  # pathmoved- environments
	  export NGINX_SITE_TO_HOME_URI="$W3D_WP_SITE_URI"
	fi

	envsubst </share/scripts/init-box/templates/nginx-vhost-wp-sandbox-2-subdir.conf >>/etc/nginx/sites-available/wp-sandbox-include.conf
fi

envsubst </share/scripts/init-box/templates/nginx-vhost-wp-sandbox-3.conf >>/etc/nginx/sites-available/wp-sandbox-include.conf

# restart
/etc/init.d/nginx restart
