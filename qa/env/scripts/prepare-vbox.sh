set -e

# Configure env scripts
chmod 777 /vagrant/prepare-exports.sh
chmod 777 /vagrant/environment-*.sh
. /vagrant/prepare-exports.sh

/share/scripts/100-init.sh
/share/scripts/200-db.sh
/share/scripts/300-php.sh
/share/scripts/400-http-server.sh
/share/scripts/500-varnish.sh
/share/scripts/600-wp-cli.sh
/share/scripts/700-wordpress.sh
/share/scripts/800-w3tc.sh
