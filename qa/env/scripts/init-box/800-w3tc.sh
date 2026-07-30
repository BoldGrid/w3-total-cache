# w3tcQA Symlinks
set -e
set -a
[ -r /etc/environment ] && . /etc/environment
set +a

# Match 700/720: www-data must inherit PATH so wp-cli uses the box PHP binary.
LIMITED="sudo -u www-data --preserve-env=PATH"

ln -s /share/w3tc/qa/tests/ /root/w3tcqa
echo "alias w3test=\"/share/scripts/w3test \"" >> /root/.bash_aliases

/share/scripts/init-box/115-sandbox-hosts-rfc1918.sh 2>/dev/null || \
	/share/w3tc/qa/env/scripts/init-box/115-sandbox-hosts-rfc1918.sh

# ask w3tc to use debug GA profile
cd $W3D_WP_PATH
sed -i '2idefine( \"W3TC_DEBUG\", true );' wp-config.php
sed -i '2idefine( \"W3TC_DEVELOPER\", true );' wp-config.php

# backup before w3tc
/share/scripts/w3tc-umount.sh
cd /var/www/
rm -rf backup-w3tc-inactive-wp-sandbox
cp -r wp-sandbox backup-w3tc-inactive-wp-sandbox
/share/scripts/w3tc-mount.sh
mysqldump --databases wordpress --add-drop-database --skip-comments >backup-w3tc-inactive.sql

# mount w3tc
mkdir -p ${W3D_WP_PLUGINS_PATH}w3-total-cache/
chmod 750 ${W3D_WP_PLUGINS_PATH}w3-total-cache/
chown www-data:www-data ${W3D_WP_PLUGINS_PATH}w3-total-cache/
/share/scripts/w3tc-mount.sh

# qa/package.json keeps npm link local; otherwise npm resolves ../package.json and runs a full install.
cd /share/w3tc/qa
npm link puppeteer mocha mocha-logger chai
# Fail box init here if globals did not link (box-valid used to die later with "Cannot find module 'chai'").
node -e "require('chai'); require('mocha'); require('mocha-logger');"

# activate w3tc
ACTIVATE_OPTIONS=""
if [ "$W3D_WP_NETWORK" = "subdomain" ]; then
	ACTIVATE_OPTIONS="--network"
fi
if [ "$W3D_WP_NETWORK" = "subdir" ]; then
	ACTIVATE_OPTIONS="--network"
fi

cd $W3D_WP_PATH
$LIMITED wp plugin activate w3-total-cache ${ACTIVATE_OPTIONS}

# Optional QA PHP settings (.user.ini / Apache htaccess). No-op unless W3D_QA_PHP_OUTPUT_BUFFERING_OFF=1.
/share/scripts/init-box/755-w3tcqa-php-output-buffering.sh

# backup final
/share/scripts/w3tc-umount.sh
cd /var/www/
rm -rf backup-final-wp-sandbox
cp -r wp-sandbox backup-final-wp-sandbox
/share/scripts/w3tc-mount.sh
/share/scripts/validate-wordpress-tree.sh /var/www/backup-final-wp-sandbox
mysqldump --databases wordpress --add-drop-database --skip-comments >backup-final.sql

/share/scripts/ensure-http-error-log.sh
if [ "$W3D_HTTP_SERVER" = "apache" ]; then
	/etc/init.d/apache2 restart
elif [ "$W3D_HTTP_SERVER" = "nginx" ]; then
	/etc/init.d/nginx restart
fi

/share/scripts/validate-wordpress-tree.sh /var/www/wp-sandbox
