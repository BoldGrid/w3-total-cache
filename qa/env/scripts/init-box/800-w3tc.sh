# w3tcQA Symlinks
ln -s /share/w3tc/qa/tests/ /root/w3tcqa
echo "alias w3test=\"/share/scripts/w3test \"" >> /root/.bash_aliases

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

cd /share/w3tc/qa
npm link puppeteer
npm link mocha
npm link mocha-logger
npm link chai

# activate w3tc
ACTIVATE_OPTIONS=""
if [ "$W3D_WP_NETWORK" = "subdomain" ]; then
	ACTIVATE_OPTIONS="--network"
fi
if [ "$W3D_WP_NETWORK" = "subdir" ]; then
	ACTIVATE_OPTIONS="--network"
fi

cd $W3D_WP_PATH
sudo -u www-data wp plugin activate w3-total-cache ${ACTIVATE_OPTIONS}

# backup final
/share/scripts/w3tc-umount.sh
cd /var/www/
rm -rf backup-final-wp-sandbox
cp -r wp-sandbox backup-final-wp-sandbox
/share/scripts/w3tc-mount.sh
mysqldump --databases wordpress --add-drop-database --skip-comments >backup-final.sql
