# box-specifics http server setup

if [ "$W3D_HTTP_SERVER" = "nginx" ]; then
	/share/scripts/init-box/460-http-server-nginx.sh
fi


# update environment variables in service process

# vsftp - update password, since its reset on each recreation
echo "ubuntu:Ilgr5UOoc7s6Gj1htaDDcQ4F6T27e3UC" | chpasswd
