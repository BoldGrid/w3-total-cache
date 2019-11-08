LIMITED="sudo -u www-data"

if [ "$W3D_WP_NETWORK" = "subdomain" ]; then
    WP_NETWORK_OPTIONS="--subdomains"
    WP_B2_SLUG="b2"
    WP_B2_HOMEURL="${W3D_HTTP_SERVER_SCHEME}://b2.${W3D_WP_HOST}${W3D_WP_MAYBE_COLON_PORT}${W3D_WP_HOME_URI}"
    WP_B2_SITEURL="${W3D_HTTP_SERVER_SCHEME}://b2.${W3D_WP_HOST}${W3D_WP_MAYBE_COLON_PORT}${W3D_WP_SITE_URI}"
fi

if [ "$W3D_WP_NETWORK" = "subdir" ]; then
    WP_B2_SLUG="b2"
    WP_B2_HOMEURL="${W3D_HTTP_SERVER_SCHEME}://${W3D_WP_HOST}${W3D_WP_MAYBE_COLON_PORT}/b2${W3D_WP_HOME_URI}"
    WP_B2_SITEURL="${W3D_HTTP_SERVER_SCHEME}://${W3D_WP_HOST}${W3D_WP_MAYBE_COLON_PORT}/b2${W3D_WP_SITE_URI}"
fi

cd $W3D_WP_PATH
$LIMITED wp core multisite-convert ${WP_NETWORK_OPTIONS} --base=${W3D_WP_HOME_URI}
$LIMITED wp site create --slug=$WP_B2_SLUG --title=b2.wp.sandbox --email=a@b.com

# set predefined time format (expected by QA changing post's time)
$LIMITED wp option set time_format "H:i" --url=$WP_B2_HOMEURL

# when https website - homeurl/siteurl is set incorrectly by wp itself
# it has http hardcoded
if [ "$W3D_HTTP_SERVER_SCHEME" = "https" ]; then
    $LIMITED wp option update home "$WP_B2_HOMEURL" --url=$WP_B2_HOMEURL
fi

if [ "$W3D_HTTP_SERVER_SCHEME" = "https" ] ||
        [ "$W3D_WP_SITE_URI" != "$W3D_WP_HOME_URI" ]; then
    $LIMITED wp option update siteurl "$WP_B2_SITEURL" --url=$WP_B2_HOMEURL
fi
