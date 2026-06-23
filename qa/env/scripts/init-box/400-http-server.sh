# box-specifics http server setup

set -a
[ -r /etc/environment ] && . /etc/environment
set +a

/share/scripts/init-box/401-ensure-php.sh
/share/scripts/init-box/402-ensure-http-server.sh
/share/scripts/init-box/403-hosts-box.sh

if [ "$W3D_HTTP_SERVER" = "nginx" ]; then
	/share/scripts/init-box/460-http-server-nginx.sh
fi

if [ "$W3D_HTTP_SERVER" = "apache" ]; then
	/share/scripts/init-box/404-apache-wp-host.sh
fi

# vsftp - update password, since its reset on each recreation
echo "ubuntu:Ilgr5UOoc7s6Gj1htaDDcQ4F6T27e3UC" | chpasswd
