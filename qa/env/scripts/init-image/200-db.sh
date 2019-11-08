# db servers
export DEBIAN_FRONTEND=noninteractive
apt-get -q -y install mysql-server

apt-get install memcached

if [ "$W3D_REDIS" = "redis" ]; then
  apt-get install -q -y redis-server
fi
