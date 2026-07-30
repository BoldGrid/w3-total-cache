set -a
[ -r /etc/environment ] && . /etc/environment
set +a

/share/scripts/init-image/600-wp-cli.sh

mkdir -pv ${W3D_WP_PATH}
cp /share/scripts/init-box/templates/wp-cli.yml ${W3D_WP_PATH}wp-cli.yml
chmod 666 ${W3D_WP_PATH}wp-cli.yml
