#! /usr/bin/env ruby

def run
	if ENV['W3D_VARNISH'] == 'varnish'
		system_assert '/etc/init.d/varnish restart'
	end

	if ENV['W3D_CACHE_ENGINE_LABEL'] == 'memcached'
		system_assert 'service memcached force-reload'
	end

	if ENV['W3D_CACHE_ENGINE_LABEL'] == 'redis'
		system_assert 'redis-cli flushall'
	end

	if ENV['W3D_HTTP_SERVER'] == 'apache'
		system_assert '/etc/init.d/apache2 restart'
	elsif ENV['W3D_HTTP_SERVER'] == 'lightspeed'
		system_assert 'systemctl restart lsws'
	else
		socket_filename = '/tmp/php-fpm.sock'
		# php first, nginx next - otherwise not stable
		if ENV['W3D_PHP_VERSION'] == '7.0'
			system_assert 'service php7.0-fpm restart'
		elsif ENV['W3D_PHP_VERSION'] == '7.1'
			system_assert 'service php7.1-fpm restart'
		elsif ENV['W3D_PHP_VERSION'] == '7.2'
			system_assert 'service php7.2-fpm restart'
		elsif ENV['W3D_PHP_VERSION'] == '7.3'
			system_assert 'service php7.3-fpm restart'
		elsif ENV['W3D_PHP_VERSION'] == '7.4'
			system_assert 'service php7.4-fpm restart'
		elsif ENV['W3D_PHP_VERSION'] == '8.0'
			system_assert 'service php8.0-fpm restart'
		elsif ENV['W3D_PHP_VERSION'] == '8.1'
			system_assert 'service php8.1-fpm restart'
		else
			socket_filename = '/run/php/php5.6-fpm.sock'
			system_assert 'service php5.6-fpm restart'
		end

		n = 0
		while !File.exists?(socket_filename) and n <= 10
			sleep(1)
			puts 'waiting for ' + socket_filename
			n += 1
		end

		if !File.exists?(socket_filename)
			abort 'failed waiting'
		end

		if !File.exists?(ENV['W3D_WP_HOME_PATH'] + 'nginx.conf')
			system_assert 'sudo -u www-data touch ${W3D_WP_HOME_PATH}nginx.conf'
		end

		puts 'nginx restart'
		system_assert '/etc/init.d/nginx restart'
	end

	puts 'restartHttpSuccess'
end



def system_assert(command)
	puts command
	ret = system command
	if !ret
		abort 'failed to execute ' + command
	end
end



run
