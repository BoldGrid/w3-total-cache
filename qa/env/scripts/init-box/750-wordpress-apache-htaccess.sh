if [ "$W3D_WP_NETWORK" = "single" ]; then
  # pathmoved-single needs own custom htaccess, original has
  # incorrect RewriteBase
  if [ "$W3D_WP_SITE_URI" != "$W3D_WP_HOME_URI" ]; then
  	cp -f /share/scripts/init-box/templates/apache-htaccess-single ${W3D_WP_PATH}.htaccess
  fi
fi

if [ "$W3D_WP_NETWORK" = "subdir" ]; then
	export W3TC_SITE_TO_HOME_URI="/"

	if [ "$W3D_WP_SITE_URI" != "$W3D_WP_HOME_URI" ]; then
	  # pathmoved- environments
	  export W3TC_SITE_TO_HOME_URI="$W3D_WP_SITE_URI"
	fi

	envsubst </share/scripts/init-box/templates/apache-htaccess-subdir >${W3D_WP_PATH}.htaccess
fi

if [ "$W3D_WP_NETWORK" = "subdomain" ]; then
  # subdomain supported only when wp resides in root folder
  cp -f /share/scripts/init-box/templates/apache-htaccess-subdomain ${W3D_WP_PATH}.htaccess
fi

chown www-data:www-data ${W3D_WP_PATH}.htaccess
chmod 755 ${W3D_WP_PATH}.htaccess
