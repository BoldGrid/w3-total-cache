exports.homePath = process.env['W3D_WP_HOME_PATH'];   // path of http://wp.sandbox/ url
exports.wpPath = process.env['W3D_WP_PATH'];   // path of wp installation
exports.wpContentPath = process.env['W3D_WP_CONTENT_PATH'];
exports.wpPluginsPath = process.env['W3D_WP_PLUGINS_PATH'];
exports.blogId = process.env['W3D_WP_BLOG_ID'];
exports.adminLogin = 'admin';
exports.adminPassword = '1';
exports.wpSiteUri = process.env['W3D_WP_SITE_URI'];
exports.phpVersion = process.env['W3D_PHP_VERSION'];
exports.ftpHost = '';
exports.ftpUsername = '';
exports.ftpPassword = '';

exports.scheme = process.env['W3D_HTTP_SERVER_SCHEME'];
// warn - that's not where WordPress available
exports.httpServerPort = process.env['W3D_HTTP_SERVER_PORT'];

exports.wpMaybeColonPort = process.env['W3D_WP_MAYBE_COLON_PORT'];


// wp-level
exports.isWpmu = (process.env['W3D_WP_NETWORK'] != 'single');

// blog-level
exports.boxName = process.env['W3D_BOX_NAME'];
exports.blogHost = process.env['W3D_WP_BLOG_HOST'];
exports.blogHomeUri = process.env['W3D_WP_BLOG_HOME_URI'];
exports.blogSiteUri = process.env['W3D_WP_BLOG_SITE_URI'];
exports.blogWpContentUri = blogWpContentUri();
exports.blogWpContentUrl = exports.scheme + '://' + exports.blogHost +
  exports.wpMaybeColonPort + exports.blogWpContentUri;
exports.blogPluginsUri = blogPluginsUri();
exports.blogPluginsUrl = exports.scheme + '://' + exports.blogHost +
  exports.wpMaybeColonPort + exports.blogPluginsUri;
exports.wpUrl = exports.scheme + '://' + process.env['W3D_WP_HOST'] +
	exports.wpMaybeColonPort + exports.wpSiteUri;

exports.homeUrl = process.env['W3D_WP_BLOG_HOME_URL'];
// todo: replace with host+siteuri vars
exports.adminUrl = process.env['W3D_WP_BLOG_ADMIN_URL'];
exports.networkAdminUrl = process.env['W3D_WPADMIN_NETWORK_URL'];
exports.cachedUrl = ( exports.isWpmu ? exports.homeUrl + '?repeat=w3tc' : '');
exports.blogSiteUrl = exports.scheme + '://' + exports.blogHost +
  exports.wpMaybeColonPort + exports.blogSiteUri;

// cache-engine mutations
exports.cacheEngineLabel = process.env['W3D_CACHE_ENGINE_LABEL'];
exports.cacheEngineName = process.env['W3D_CACHE_ENGINE_NAME'];
exports.wpVersion = process.env['W3D_WP_VERSION'];

// cdn ftp
exports.cdnFtpExportDir = '/var/www/for-tests-sandbox';
exports.cdnFtpExportHostPort = 'for-tests.sandbox' +
  (exports.scheme == 'http' && exports.httpServerPort != 80 ? ':' + exports.httpServerPort : '') +
  (exports.scheme == 'https' && exports.httpServerPort != 443 ? ':' + exports.httpServerPort : '');



function blogWpContentUri() {
	var homeUri = process.env['W3D_WP_HOME_URI'];
	var wpContentUri = process.env['W3D_WP_CONTENT_URI'];

	var blogWpContentPostfixUri;
	if (wpContentUri.substr(0, homeUri.length) == homeUri)
		blogWpContentPostfixUri = wpContentUri.substr(homeUri.length);
	else
		blogWpContentPostfixUri = wpContentUri;

	return exports.blogHomeUri + blogWpContentPostfixUri;
}



function blogPluginsUri() {
	var homeUri = process.env['W3D_WP_HOME_URI'];
	var pluginsUri = process.env['W3D_WP_PLUGINS_URI'];

	var blogPluginsPostfixUri;
	if (pluginsUri.substr(0, homeUri.length) == homeUri)
		blogPluginsPostfixUri = pluginsUri.substr(homeUri.length);
	else
		blogPluginsPostfixUri = pluginsUri;

	return exports.blogHomeUri + blogPluginsPostfixUri;
}
