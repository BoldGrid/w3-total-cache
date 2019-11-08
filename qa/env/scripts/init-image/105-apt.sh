# sandbox dont need any fixed install of these, install by packaging
#sed -i 's/# \(.*multiverse$\)/\1/g' /etc/apt/sources.list && \
# force apt-get list cleanup to avoid crit errors as: Hash Sum mismatch
#rm -r /var/lib/apt/lists/*

# avoid "cache too small" error
echo 'APT::Cache-Start "35000000";' >> /etc/apt/apt.conf.d/70debconf
echo 'APT::Cache-Limit "100000000";' >> /etc/apt/apt.conf.d/70debconf


sed -i "s/APT::Periodic::Update-Package-Lists \"1\"/APT::Periodic::Update-Package-Lists \"0\"/" /etc/apt/apt.conf.d/20auto-upgrades
sed -i "s/APT::Periodic::Unattended-Upgrade \"1\"/APT::Periodic::Unattended-Upgrade \"0\"/" /etc/apt/apt.conf.d/20auto-upgrades
