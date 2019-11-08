# Set passwords for users: vagrant / www-data / root to get ability to use FTP
echo "root:Rq2A75b53dP6YlPlvvqEmQnBz4dYe7yP" | chpasswd
echo "ubuntu:Ilgr5UOoc7s6Gj1htaDDcQ4F6T27e3UC" | chpasswd
echo "www-data:sEqo5dBaOL4lSIa3NxZW4ToNM7TznzuU" | chpasswd

chsh -s /bin/bash www-data
echo "www-data ALL=NOPASSWD: ALL" >> /etc/sudoers

# env variables common for whole box
#copying it to /etc/environment now

# root user
cp -f /root/.bashrc /root/.bashrc_bkp

# ubuntu user
if [ -z "$(getent passwd ubuntu)" ]; then
    useradd -m -s /bin/bash -U ubuntu
    cp -f /root/.bashrc_bkp /home/ubuntu/.bashrc
    cp -f /root/.profile /home/ubuntu/.profile
    chown -R ubuntu:ubuntu /home/ubuntu/
    echo "ubuntu ALL=(ALL) NOPASSWD:ALL" > /etc/sudoers.d/ubuntu
fi

# www-data user
mkdir -p /var/www/
cp -f /root/.profile /var/www/.profile
