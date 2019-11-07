/share/scripts/init-image/105-apt.sh
/share/scripts/init-image/110-hosts.sh
/share/scripts/init-image/120-users.sh

# Block "Service restarted too often" message
sed -i "s/#DefaultStartLimitBurst=.*/DefaultStartLimitBurst=500/" /etc/systemd/system.conf

# Install required packages
apt-get update
apt-get update   # once is not always enough
apt-get install -y curl mc ruby pcregrep zip

/share/scripts/init-image/150-mocha.sh

# vsftpd
apt-get install vsftpd -y
echo "write_enable=YES" >> /etc/vsftpd.conf
echo "chroot_local_user=NO" >> /etc/vsftpd.conf
echo "ascii_download_enable=YES" >> /etc/vsftpd.conf
echo "ascii_upload_enable=YES" >> /etc/vsftpd.conf
echo "local_enable=YES" >> /etc/vsftpd.conf

service vsftpd restart
# class { 'vsftpd':
#   anonymous_enable  => 'NO',
#   write_enable      => 'YES',
#   ftpd_banner       => 'Sandbox FTP Server',
#   chroot_local_user => 'NO',
#   userlist_enable   => 'NO',
#   directives  => {
#     'ascii_download_enable' => 'YES',
#     'ascii_upload_enable'   => 'YES',
#   }
# } ->
# killall -HUP vsftpd

mkdir /share/reports
