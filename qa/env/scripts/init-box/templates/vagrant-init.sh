set -e

# Configure env scripts
cat /share/vagrant/vagrant-ami-export.sh >>/etc/environment
cat /share/vagrant/export.sh >>/etc/environment
chmod 777 /etc/environment
. /etc/environment

ln -s /share/vagrant/environments/ /share/environments

/share/scripts/init-image/100-init.sh
/share/scripts/init-image/200-db.sh
/share/scripts/init-image/300-php.sh
/share/scripts/init-image/400-http-server.sh
/share/scripts/init-image/500-varnish.sh
/share/scripts/init-image/600-wp-cli.sh
/share/scripts/init-box/400-http-server.sh
/share/scripts/init-box/600-wp-cli.sh
/share/scripts/init-box/700-wordpress.sh
/share/scripts/init-box/800-w3tc.sh
