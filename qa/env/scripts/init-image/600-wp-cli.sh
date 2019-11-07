mkdir -p /usr/local/src/wp-cli/bin/

case "${W3D_PHP_VERSION}" in
    "5.3") echo "Installing wp-cli for PHP 5.3"
        curl --silent -o /usr/local/src/wp-cli/bin/wp -L https://github.com/wp-cli/wp-cli/releases/download/v1.5.1/wp-cli-1.5.1.phar
        ;;
    *)
        curl --silent -o /usr/local/src/wp-cli/bin/wp -L https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
        ;;
esac


chmod a+x /usr/local/src/wp-cli/bin/wp
ln -s /usr/local/src/wp-cli/bin/wp /usr/bin/wp

# wp-completion
curl -L --silent "https://github.com/wp-cli/wp-cli/raw/master/utils/wp-completion.bash" --output /root/.wp-completion.bash
echo "source /root/.wp-completion.bash" >> /root/.bashrc
echo "alias wp=\"/usr/bin/wp --allow-root \"" >> /root/.bash_aliases

cp -f /root/.wp-completion.bash /home/ubuntu/.wp-completion.bash
echo "source /home/ubuntu/.wp-completion.bash" >> /home/ubuntu/.bashrc
echo "alias wp=\"/usr/bin/wp --allow-root \"" >> /home/ubuntu/.bash_aliases

cp -f /root/.wp-completion.bash /var/www/.wp-completion.bash
echo "source /var/www/.wp-completion.bash" >> /var/www/.bashrc
echo "alias wp=\"/usr/bin/wp --allow-root \"" >> /var/www/.bash_aliases
