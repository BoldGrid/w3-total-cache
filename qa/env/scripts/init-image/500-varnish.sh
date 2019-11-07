
# varnish
if [ "$W3D_VARNISH" = "varnish" ]; then
  apt-get install  -q -y varnish
  sed -i "s/DAEMON_OPTS=\"-a :6081/DAEMON_OPTS=\"-a :80/" /etc/default/varnish
  cp /share/scripts/init-image/templates/varnish.vcl /etc/varnish/default.vcl
  service varnish restart
fi
