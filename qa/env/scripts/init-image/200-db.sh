# db servers
export DEBIAN_FRONTEND=noninteractive
apt-get -q -y install mysql-server

apt-get install memcached

if [ "$W3D_REDIS" = "redis" ]; then
  apt-get install -q -y redis-server
fi

if [ "${W3D_PHP_VERSION}" == "5.6" ]; then
	echo "default_authentication_plugin=mysql_native_password" >> /etc/mysql/mysql.conf.d/mysqld.cnf
	service mysql restart
fi
if [ "${W3D_PHP_VERSION}" == "7.3" ]; then
	echo "default_authentication_plugin=mysql_native_password" >> /etc/mysql/mysql.conf.d/mysqld.cnf
	service mysql restart
fi
