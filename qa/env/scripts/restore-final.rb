#! /usr/bin/env ruby

def run
	puts '--- cleanup'
	system 'echo $W3D_INSTANCE_ID'
	system 'cat /etc/hostname'
	system_assert '/share/scripts/w3tc-umount.sh'
	system_assert '/share/scripts/w3tc-pro-umount.sh'

	system 'rm -rf /var/www/wp-sandbox'
	system 'rm -rf /var/www/for-tests-sandbox/*'
	system_assert 'cp -r /var/www/backup-final-wp-sandbox /var/www/wp-sandbox'
	system_assert '/share/scripts/w3tc-mount.sh'
	system_assert '/share/scripts/w3tc-pro-mount.sh'
	system_assert 'chown -R www-data:www-data /var/www/wp-sandbox'
	system_assert 'mysql </var/www/backup-final.sql'

	# tables are not immediately available after loading dump, wait for that
	n = 0
	while !system('mysql </share/scripts/mysql-test.sql') and n < 10
		sleep(1)
		puts 'waiting for mysql to be ready'
		n += 1
	end

end



def system_assert(command)
	puts command
	ret = system command
	if !ret
		abort 'failed to execute ' + command
	end
end



run
