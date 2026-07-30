# to allow host to talk to website hosted by it; RFC1918 (not loopback) so
# W3TC CDN test-button host validation accepts *.sandbox targets
SANDBOX_IP='10.127.0.1'
ip addr add "${SANDBOX_IP}/32" dev lo 2>/dev/null || true
echo "${SANDBOX_IP} wp.sandbox" >>/etc/hosts
echo "${SANDBOX_IP} b2.wp.sandbox" >>/etc/hosts
echo "${SANDBOX_IP} for-tests.wp.sandbox" >>/etc/hosts
echo "${SANDBOX_IP} for-tests.sandbox" >>/etc/hosts
echo "${SANDBOX_IP} system.sandbox" >>/etc/hosts
echo "127.0.0.1 api.twitter.com www.gravityhelp.com planet.wordpress.org blogsearch.google.com gravityhelp.com" >> /etc/hosts
