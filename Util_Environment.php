<?php
/**
 * File: Util_Environment.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Util_Environment
 *
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
 */
class Util_Environment {
	/**
	 * Is using ,aster config.
	 *
	 * @var bool
	 *
	 * @static
	 */
	private static $is_using_master_config = null;

	/**
	 * Formats URL.
	 *
	 * @static
	 *
	 * @param string $url        URL.
	 * @param array  $params     Parameters.
	 * @param bool   $skip_empty Skip empty.
	 * @param string $separator  Separate.
	 *
	 * @return string
	 */
	public static function url_format( $url = '', $params = array(), $skip_empty = false, $separator = '&' ) {
		if ( ! empty( $url ) ) {
			$parse_url = @parse_url( $url ); // phpcs:ignore
			$url       = '';

			if ( ! empty( $parse_url['scheme'] ) ) {
				$url .= $parse_url['scheme'] . '://';

				if ( ! empty( $parse_url['user'] ) ) {
					$url .= $parse_url['user'];

					if ( ! empty( $parse_url['pass'] ) ) {
						$url .= ':' . $parse_url['pass'];
					}
				}

				if ( ! empty( $parse_url['host'] ) ) {
					$url .= $parse_url['host'];
				}

				if ( ! empty( $parse_url['port'] ) && 80 !== $parse_url['port'] ) {
					$url .= ':' . (int) $parse_url['port'];
				}
			}

			if ( ! empty( $parse_url['path'] ) ) {
				$url .= $parse_url['path'];
			}

			if ( ! empty( $parse_url['query'] ) ) {
				$old_params = array();
				parse_str( $parse_url['query'], $old_params );

				$params = array_merge( $old_params, $params );
			}

			$query = self::url_query( $params );

			if ( ! empty( $query ) ) {
				$url .= '?' . $query;
			}

			if ( ! empty( $parse_url['fragment'] ) ) {
				$url .= '#' . $parse_url['fragment'];
			}
		} else {
			$query = self::url_query( $params, $skip_empty, $separator );

			if ( ! empty( $query ) ) {
				$url = '?' . $query;
			}
		}

		return $url;
	}

	/**
	 * Formats query string.
	 *
	 * @static
	 *
	 * @param array  $params     Parameters.
	 * @param bool   $skip_empty Skip empty.
	 * @param string $separator  Separator.
	 *
	 * @return string
	 */
	public static function url_query( $params = array(), $skip_empty = false, $separator = '&' ) {
		$str          = '';
		static $stack = array();

		foreach ( (array) $params as $key => $value ) {
			if ( $skip_empty && empty( $value ) ) {
				continue;
			}

			array_push( $stack, $key );

			if ( is_array( $value ) ) {
				if ( count( $value ) ) {
					$str .= ( ! empty( $str ) ? '&' : '' ) .
						self::url_query( $value, $skip_empty, $separator );
				}
			} else {
				$name = '';

				foreach ( $stack as $key ) {
					$name .= ( ! empty( $name ) ? '[' . $key . ']' : $key );
				}
				$str .= ( ! empty( $str ) ? $separator : '' ) . $name . '=' . rawurlencode( $value );
			}

			array_pop( $stack );
		}

		return $str;
	}

	/**
	 * Returns URL from filename/dirname.
	 *
	 * @static
	 *
	 * @param string $filename Filename.
	 * @param bool   $use_site_url Use siteurl.
	 *
	 * @return string
	 */
	public static function filename_to_url( $filename, $use_site_url = false ) {
		/**
		 * Using wp-content instead of document_root as known dir since dirbased
		 * multisite wp adds blogname to the path inside site_url.
		 */
		if ( substr( $filename, 0, strlen( WP_CONTENT_DIR ) ) === WP_CONTENT_DIR ) {
			// This is the default location of the wp-content/cache directory.
			$location = WP_CONTENT_DIR;
		} elseif ( substr( $filename, 0, strlen( W3TC_CACHE_DIR ) ) === W3TC_CACHE_DIR ) {
			// This is needed in the event the cache directory is moved outside of wp-content and replace with a symbolic link.
			$location = substr( W3TC_CACHE_DIR, 0, -strlen( '/cache' ) );
		} elseif ( substr( $filename, 0, strlen( W3TC_CONFIG_DIR ) ) === W3TC_CONFIG_DIR ) {
			// This is needed in the event the cache directory is moved outside of wp-content and replace with a symbolic link.
			$location = substr( W3TC_CONFIG_DIR, 0, -strlen( '/w3tc-config' ) );
		} else {
			return '';
		}

		$uri_from_location = substr( $filename, strlen( $location ) );

		if ( '/' !== DIRECTORY_SEPARATOR ) {
			$uri_from_location = str_replace( DIRECTORY_SEPARATOR, '/', $uri_from_location );
		}

		$url = content_url( $uri_from_location );
		$url = apply_filters( 'w3tc_filename_to_url', $url );

		return $url;
	}

	/**
	 * Returns true if database cluster is used.
	 *
	 * @static
	 *
	 * @param Config $config Config.
	 *
	 * @return bool
	 */
	public static function is_dbcluster( $config = null ) {
		if ( is_null( $config ) ) {
			// fallback for compatibility with older wp-content/db.php.
			$config = \W3TC\Dispatcher::config();
		}

		if ( ! self::is_w3tc_pro( $config ) ) {
			return false;
		}

		if ( isset( $GLOBALS['w3tc_dbcluster_config'] ) ) {
			return true;
		}

		return defined( 'W3TC_FILE_DB_CLUSTER_CONFIG' ) &&
			@file_exists( W3TC_FILE_DB_CLUSTER_CONFIG ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	/**
	 * Returns true if WPMU uses vhosts.
	 *
	 * @static
	 *
	 * @return bool
	 */
	public static function is_wpmu_subdomain() {
		return (
			( defined( 'SUBDOMAIN_INSTALL' ) && SUBDOMAIN_INSTALL ) ||
			( defined( 'VHOST' ) && 'yes' === VHOST )
		);
	}

	/**
	 * Returns if there is multisite mode.
	 *
	 * @static
	 *
	 * @return bool
	 */
	public static function is_wpmu() {
		static $wpmu = null;

		if ( null === $wpmu ) {
			$wpmu = (
				file_exists( ABSPATH . 'wpmu-settings.php' ) ||
				( defined( 'MULTISITE' ) && MULTISITE ) ||
				defined( 'SUNRISE' ) ||
				self::is_wpmu_subdomain()
			);
		}

		return $wpmu;
	}

	/**
	 * Is using master config.
	 *
	 * @static
	 *
	 * @return bool
	 */
	public static function is_using_master_config() {
		if ( is_null( self::$is_using_master_config ) ) {
			if ( ! self::is_wpmu() ) {
				self::$is_using_master_config = true;
			} elseif ( is_network_admin() ) {
				self::$is_using_master_config = true;
			} else {
				$blog_data = Util_WpmuBlogmap::get_current_blog_data();
				if ( is_null( $blog_data ) ) {
					self::$is_using_master_config = true;
				} else {
					self::$is_using_master_config = ( 'm' === $blog_data[0] );
				}
			}
		}

		return self::$is_using_master_config;
	}

	/**
	 * Returns header W3TC adds to responses powered by itself.
	 *
	 * @static
	 *
	 * @return string
	 */
	public static function w3tc_header() {
		return W3TC_POWERED_BY .
			'/' . W3TC_VERSION;
	}

	/**
	 * Check if URL is valid.
	 *
	 * @static
	 *
	 * @param string $url URL.
	 *
	 * @return bool
	 */
	public static function is_url( $url ) {
		return preg_match( '~^(https?:)?//~', $url );
	}

	/**
	 * Returns true if current connection is secure.
	 *
	 * @static
	 *
	 * @return bool
	 */
	public static function is_https() {
		$https                  = isset( $_SERVER['HTTPS'] ) ?
			htmlspecialchars( stripslashes( $_SERVER['HTTPS'] ) ) : ''; // phpcs:ignore
		$server_port            = isset( $_SERVER['SERVER_PORT'] ) ?
			htmlspecialchars( stripslashes( $_SERVER['SERVER_PORT'] ) ) : ''; // phpcs:ignore
		$http_x_forwarded_proto = isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ?
			htmlspecialchars( stripslashes( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) : ''; // phpcs:ignore

		switch ( true ) {
			case ( self::to_boolean( $https ) ):
			case ( 433 === (int) $server_port ):
			case ( 'https' === $http_x_forwarded_proto ):
				return true;
		}

		return false;
	}

	/**
	 * Moves user to preview-mode or opposite.
	 *
	 * @param bool $is_enabled Is enabled.
	 *
	 * @static
	 */
	public static function set_preview( $is_enabled ) {
		if ( $is_enabled ) {
			setcookie( 'w3tc_preview', '*', 0, '/' );
		} else {
			setcookie( 'w3tc_preview', '', time() - 3600, '/' );
		}
	}

	/**
	 * Retuns true if preview settings active.
	 *
	 * @static
	 *
	 * @return bool
	 */
	public static function is_preview_mode() {
		return ! empty( $_COOKIE['w3tc_preview'] );
	}

	/**
	 * Returns true if server is Apache.
	 *
	 * @static
	 *
	 * @return bool
	 */
	public static function is_apache() {
		// Assume apache when unknown, since most common.
		if ( empty( $_SERVER['SERVER_SOFTWARE'] ) ) {
			return true;
		}

		return isset( $_SERVER['SERVER_SOFTWARE'] ) && stristr( htmlspecialchars( stripslashes( $_SERVER['SERVER_SOFTWARE'] ) ), 'Apache' ) !== false; // phpcs:ignore
	}


	/**
	 * Check whether server is LiteSpeed.
	 *
	 * @static
	 *
	 * @return bool
	 */
	public static function is_litespeed() {
		return isset( $_SERVER['SERVER_SOFTWARE'] ) && stristr( htmlspecialchars( stripslashes( $_SERVER['SERVER_SOFTWARE'] ) ), 'LiteSpeed' ) !== false; // phpcs:ignore
	}

	/**
	 * Check whether Elementor is enabled.
	 *
	 * @static
	 *
	 * @return bool
	 */
	public static function is_elementor() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		if ( is_plugin_active( 'Elementor\Plugin' ) ) {
			return true;
		} elseif ( is_plugin_active( 'elementor/elementor.php' ) ) {
			// For backward compatibility with older versions of Elementor.
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Returns true if server is nginx.
	 *
	 * @static
	 *
	 * @return bool
	 */
	public static function is_nginx() {
		return isset( $_SERVER['SERVER_SOFTWARE'] ) && stristr( htmlspecialchars( stripslashes( $_SERVER['SERVER_SOFTWARE'] ) ), 'nginx' ) !== false; // phpcs:ignore
	}

	/**
	 * Returns true if server is nginx.
	 *
	 * @static
	 *
	 * @return bool
	 */
	public static function is_iis() {
		return isset( $_SERVER['SERVER_SOFTWARE'] ) && stristr( htmlspecialchars( stripslashes( $_SERVER['SERVER_SOFTWARE'] ) ), 'IIS' ) !== false; // phpcs:ignore
	}

	/**
	 * Returns host/domain from URL.
	 *
	 * @static
	 *
	 * @param string $url URL.
	 *
	 * @return string
	 */
	public static function url_to_host( $url ) {
		$a = parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url

		if ( isset( $a['host'] ) ) {
			return $a['host'];
		}

		return '';
	}

	/**
	 * Returns path from URL. Without trailing slash.
	 *
	 * @static
	 *
	 * @param string $url URL.
	 */
	public static function url_to_uri( $url ) {
		$uri = @wp_parse_url( $url, PHP_URL_PATH );

		// Convert FALSE and other return values to string.
		if ( empty( $uri ) ) {
			return '';
		}

		return rtrim( $uri, '/' );
	}

	/**
	 * Returns current blog ID.
	 *
	 * @static
	 *
	 * @return int
	 */
	public static function blog_id() {
		global $w3_current_blog_id;

		if ( ! is_null( $w3_current_blog_id ) ) {
			return $w3_current_blog_id;
		}

		if ( ! self::is_wpmu() || is_network_admin() ) {
			$w3_current_blog_id = 0;
			return $w3_current_blog_id;
		}

		$blog_data = Util_WpmuBlogmap::get_current_blog_data();

		if ( ! is_null( $blog_data ) ) {
			$w3_current_blog_id = substr( $blog_data, 1 );
		} else {
			$w3_current_blog_id = 0;
		}

		return $w3_current_blog_id;
	}

	/**
	 * Memoized version of wp_upload_dir. That function is quite slow
	 * for a number of times CDN calls it.
	 *
	 * @static
	 *
	 * @return string
	 */
	public static function wp_upload_dir() {
		static $values_by_blog = array();

		$blog_id = self::blog_id();

		if ( ! isset( $values_by_blog[ $blog_id ] ) ) {
			$values_by_blog[ $blog_id ] = wp_upload_dir();
		}

		return $values_by_blog[ $blog_id ];
	}

	/**
	 * Returns path to section's cache dir.
	 *
	 * @static
	 *
	 * @param string $section Section.
	 *
	 * @return string
	 */
	public static function cache_dir( $section ) {
		return W3TC_CACHE_DIR . DIRECTORY_SEPARATOR . $section;
	}

	/**
	 * Returns path to blog's cache dir.
	 *
	 * @static
	 *
	 * @param string $section  Section.
	 * @param int    $blog_id Blog id.
	 *
	 * @return string
	 */
	public static function cache_blog_dir( $section, $blog_id = null ) {
		if ( ! self::is_wpmu() ) {
			$postfix = '';
		} else {
			if ( is_null( $blog_id ) ) {
				$blog_id = self::blog_id();
			}

			$postfix = DIRECTORY_SEPARATOR . sprintf( '%d', $blog_id );

			if ( defined( 'W3TC_BLOG_LEVELS' ) ) {
				for ( $n = 0; $n < W3TC_BLOG_LEVELS; $n++ ) {
					$postfix = DIRECTORY_SEPARATOR .
						substr( $postfix, strlen( $postfix ) - 1 - $n, 1 ) .
						$postfix;
				}
			}
		}

		return self::cache_dir( $section ) . $postfix;
	}

	/**
	 * Cache blog minify directory.
	 *
	 * @static
	 *
	 * @return string
	 */
	public static function cache_blog_minify_dir() {
		// when minify manual used with a shared config - shared
		// minify urls has to be used too, since CDN upload is possible
		// only from network admin.
		if ( self::is_wpmu() && self::is_using_master_config() && ! Dispatcher::config()->get_boolean( 'minify.auto' ) ) {
			$path = self::cache_blog_dir( 'minify', 0 );
		} else {
			$path = self::cache_blog_dir( 'minify' );
		}

		return $path;
	}

	/**
	 * Returns URL regexp from URL.
	 *
	 * @static
	 *
	 * @param string $url URL.
	 *
	 * @return string
	 */
	public static function get_url_regexp( $url ) {
		$url = preg_replace( '~(https?:)?//~i', '', $url );
		$url = preg_replace( '~^www\.~i', '', $url );

		$regexp = '(https?:)?//(www\.)?' . self::preg_quote( $url );

		return $regexp;
	}

	/**
	 * Returns SSL URL if current connection is https.
	 *
	 * @static
	 *
	 * @param string $url URL.
	 *
	 * @return string
	 */
	public static function url_to_maybe_https( $url ) {
		if ( self::is_https() ) {
			$url = str_replace( 'http://', 'https://', $url );
		}

		return $url;
	}

	/**
	 * Get domain URL.
	 *
	 * @static
	 *
	 * @return string
	 */
	public static function home_domain_root_url() {
		$home_url  = get_home_url();
		$parse_url = @parse_url( $home_url ); // phpcs:ignore

		if ( $parse_url && isset( $parse_url['scheme'] ) && isset( $parse_url['host'] ) ) {
			$scheme     = $parse_url['scheme'];
			$host       = $parse_url['host'];
			$port       = ( isset( $parse_url['port'] ) && 80 != $parse_url['port'] ? ':' . (int) $parse_url['port'] : '' ); // phpcs:ignore
			$domain_url = sprintf( '%s://%s%s', $scheme, $host, $port );

			return $domain_url;
		}

		return false;
	}

	/**
	 * Returns domain url regexp.
	 *
	 * @static
	 *
	 * @return string
	 */
	public static function home_domain_root_url_regexp() {
		$domain_url = self::home_domain_root_url();
		$regexp     = self::get_url_regexp( $domain_url );

		return $regexp;
	}

	/**
	 * Returns SSL home url.
	 *
	 * @static
	 *
	 * @return string
	 */
	public static function home_url_maybe_https() {
		$home_url = get_home_url();
		$ssl      = self::url_to_maybe_https( $home_url );

		return $ssl;
	}

	/**
	 * Returns home url regexp.
	 *
	 * @static
	 *
	 * @return string
	 */
	public static function home_url_regexp() {
		$home_url = get_home_url();
		$regexp   = self::get_url_regexp( $home_url );

		return $regexp;
	}

	/**
	 * Copy of WordPress get_home_path, but accessible not only for wp-admin
	 * Get the absolute filesystem path to the root of the WordPress installation
	 * (i.e. filesystem path of siteurl).
	 *
	 * @static
	 *
	 * @return string Full filesystem path to the root of the WordPress installation.
	 */
	public static function site_path() {
		$home    = set_url_scheme( get_option( 'home' ), 'http' );
		$siteurl = set_url_scheme( get_option( 'siteurl' ), 'http' );

		$home_path = ABSPATH;
		if ( ! empty( $home ) && 0 !== strcasecmp( $home, $siteurl ) ) {
			$wp_path_rel_to_home = str_ireplace( $home, '', $siteurl ); // $siteurl - $home.

			// fix of get_home_path, used when index.php is moved outside of wp folder.
			$script_filename = isset( $_SERVER['SCRIPT_FILENAME'] ) ?
				htmlspecialchars( stripslashes( $_SERVER['SCRIPT_FILENAME'] ) ) : ''; // phpcs:ignore

			$pos = strripos(
				str_replace( '\\', '/', $script_filename ),
				trailingslashit( $wp_path_rel_to_home )
			);

			if ( false !== $pos ) {
				$home_path = substr( $script_filename, 0, $pos );
				$home_path = trailingslashit( $home_path );
			} elseif ( defined( 'WP_CLI' ) ) {
				$pos = strripos(
					str_replace( '\\', '/', ABSPATH ),
					trailingslashit( $wp_path_rel_to_home )
				);

				if ( false !== $pos ) {
					$home_path = substr( ABSPATH, 0, $pos );
					$home_path = trailingslashit( $home_path );
				}
			}
		}

		return str_replace( '\\', DIRECTORY_SEPARATOR, $home_path );
	}

	/**
	 * Returns absolute path to document root.
	 * No trailing slash!
	 *
	 * @static
	 *
	 * @return string
	 */
	public static function document_root() {
		static $document_root = null;

		if ( ! is_null( $document_root ) ) {
			return $document_root;
		}

		$c           = Dispatcher::config();
		$docroot_fix = $c->get_boolean( 'docroot_fix.enable' );

		if ( $docroot_fix ) {
			$document_root = untrailingslashit( ABSPATH );
			return $document_root;
		}

		if ( ! empty( $_SERVER['SCRIPT_FILENAME'] ) && ! empty( $_SERVER['PHP_SELF'] ) ) {
			$script_filename = self::normalize_path(
				htmlspecialchars( stripslashes( $_SERVER['SCRIPT_FILENAME'] ) ) // phpcs:ignore
			);
			$php_self        = self::normalize_path(
				htmlspecialchars( stripslashes( $_SERVER['PHP_SELF'] ) ) // phpcs:ignore
			);
			if ( substr( $script_filename, -strlen( $php_self ) ) === $php_self ) {
				$document_root = substr( $script_filename, 0, -strlen( $php_self ) );
				$document_root = realpath( $document_root );
				return $document_root;
			}
		}

		if ( ! empty( $_SERVER['PATH_TRANSLATED'] ) && ! empty( $_SERVER['PHP_SELF'] ) ) {
			$document_root = substr(
				self::normalize_path( htmlspecialchars( stripslashes( $_SERVER['PATH_TRANSLATED'] ) ) ), // phpcs:ignore
				0,
				-strlen( self::normalize_path( htmlspecialchars( stripslashes( $_SERVER['PHP_SELF'] ) ) ) ) // phpcs:ignore
			);
		} elseif ( ! empty( $_SERVER['DOCUMENT_ROOT'] ) ) {
			$document_root = self::normalize_path( htmlspecialchars( stripslashes( $_SERVER['DOCUMENT_ROOT'] ) ) ); // phpcs:ignore
		} else {
			$document_root = ABSPATH;
		}

		$document_root = realpath( $document_root );
		return $document_root;
	}

	/**
	 * Returns absolute path to blog install dir
	 *
	 * Example:
	 *
	 * DOCUMENT_ROOT=/var/www/vhosts/domain.com
	 * install dir=/var/www/vhosts/domain.com/site/blog
	 * return /var/www/vhosts/domain.com/site/blog
	 *
	 * No trailing slash!
	 *
	 * @static
	 *
	 * @return string
	 */
	public static function site_root() {
		$site_root = ABSPATH;
		$site_root = realpath( $site_root );
		$site_root = self::normalize_path( $site_root );

		return $site_root;
	}

	/**
	 * Returns blog path.
	 *
	 * Example:
	 *
	 * siteurl=http://domain.com/site/blog
	 * return /site/blog/
	 *
	 * With trailing slash!
	 *
	 * @static
	 *
	 * @return string
	 */
	public static function site_url_uri() {
		return self::url_to_uri( site_url() ) . '/';
	}

	/**
	 * Returns home domain.
	 *
	 * @static
	 *
	 * @return string
	 */
	public static function home_url_host() {
		$home_url  = get_home_url();
		$parse_url = @parse_url( $home_url ); // phpcs:ignore

		if ( $parse_url && isset( $parse_url['host'] ) ) {
			return $parse_url['host'];
		}

		return self::host();
	}

	/**
	 * Returns home path.
	 *
	 * Example:
	 *
	 * home=http://domain.com/site/
	 * siteurl=http://domain.com/site/blog
	 * return /site/
	 *
	 * With trailing slash!
	 *
	 * @static
	 *
	 * @return string
	 */
	public static function home_url_uri() {
		return self::url_to_uri( get_home_url() ) . '/';
	}

	/**
	 * Network home URL.
	 *
	 * @static
	 *
	 * @return string
	 */
	public static function network_home_url_uri() {
		$uri = network_home_url( '', 'relative' );

		/*
		 * There is a bug in WP where network_home_url can return
		 * a non-relative URI even though scheme is set to relative.
		 */
		if ( self::is_url( $uri ) ) {
			$uri = wp_parse_url( $uri, PHP_URL_PATH );
		}

		if ( empty( $uri ) ) {
			return '/';
		}

		return $uri;
	}

	/**
	 * Returns server hostname with port.
	 *
	 * @static
	 *
	 * @return string
	 */
	public static function host_port() {
		static $host = null;

		if ( null === $host ) {
			if ( ! empty( $_SERVER['HTTP_HOST'] ) ) {
				// HTTP_HOST sometimes is not set causing warning.
				$host = htmlspecialchars( stripslashes( $_SERVER['HTTP_HOST'] ) ); // phpcs:ignore
			} else {
				$host = '';
			}
		}

		return $host;
	}

	/**
	 * Host.
	 *
	 * @static
	 *
	 * @return string
	 */
	public static function host() {
		$host_port = self::host_port();

		$pos = strpos( $host_port, ':' );

		if ( false === $pos ) {
			return $host_port;
		}

		return substr( $host_port, 0, $pos );
	}

	/**
	 * Returns WP config file path.
	 *
	 * @static
	 *
	 * @return string
	 */
	public static function wp_config_path() {
		$search = array(
			ABSPATH . 'wp-config.php',
			dirname( ABSPATH ) . DIRECTORY_SEPARATOR . 'wp-config.php',
		);

		foreach ( $search as $path ) {
			if ( file_exists( $path ) ) {
				return $path;
			}
		}

		return false;
	}

	/**
	 * Parses path.
	 *
	 * @static
	 *
	 * @param string $path Path.
	 *
	 * @return mixed
	 */
	public static function parse_path( $path ) {
		$path = str_replace(
			array(
				'%BLOG_ID%',
				'%POST_ID%',
				'%BLOG_ID%',
				'%HOST%',
			),
			array(
				( isset( $GLOBALS['blog_id'] ) && is_numeric( $GLOBALS['blog_id'] ) ? (int) $GLOBALS['blog_id'] : 0 ),
				( isset( $GLOBALS['post_id'] ) && is_numeric( $GLOBALS['post_id'] ) ?
					(int) $GLOBALS['post_id'] : 0 ),
				self::blog_id(),
				self::host(),
			),
			$path
		);

		return $path;
	}

	/**
	 * Normalizes file name.
	 *
	 * Relative to site root!
	 *
	 * @static
	 *
	 * @param string $file File path.
	 *
	 * @return string
	 */
	public static function normalize_file( $file ) {
		if ( self::is_url( $file ) ) {
			if ( strstr( $file, '?' ) === false ) {
				$home_url_regexp = '~' . self::home_url_regexp() . '~i';
				$file            = preg_replace( $home_url_regexp, '', $file );
			}
		}

		if ( ! self::is_url( $file ) ) {
			$file = self::normalize_path( $file );
			$file = str_replace( self::site_root(), '', $file );
			$file = ltrim( $file, '/' );
		}

		return $file;
	}

	/**
	 * Normalizes file name for minify.
	 *
	 * Relative to document root!
	 *
	 * @static
	 *
	 * @param string $file File.
	 *
	 * @return string
	 */
	public static function normalize_file_minify( $file ) {
		if ( self::is_url( $file ) ) {
			if ( strstr( $file, '?' ) === false ) {
				$domain_url_regexp = '~' . self::home_domain_root_url_regexp() . '~i';
				$file              = preg_replace( $domain_url_regexp, '', $file );
			}
		}

		if ( ! self::is_url( $file ) ) {
			$file = self::normalize_path( $file );
			$file = str_replace( self::document_root(), '', $file );
			$file = ltrim( $file, '/' );
		}

		return $file;
	}

	/**
	 * Normalizes file name for minify.
	 * Relative to document root!
	 *
	 * @static
	 *
	 * @param string $url URL.
	 *
	 * @return string
	 */
	public static function url_to_docroot_filename( $url ) {
		$data = array(
			'home_url' => get_home_url(),
			'url'      => $url,
		);

		$data = apply_filters( 'w3tc_url_to_docroot_filename', $data );

		$home_url       = $data['home_url'];
		$normalized_url = $data['url'];
		$normalized_url = self::remove_query_all( $normalized_url );

		// Cut protocol.
		$normalized_url = preg_replace( '~^http(s)?://~', '//', $normalized_url );
		$home_url       = preg_replace( '~^http(s)?://~', '//', $home_url );

		if ( substr( $normalized_url, 0, strlen( $home_url ) ) !== $home_url ) {
			// Not a home url, return unchanged since cant be converted to filename.
			return null;
		}

		$path_relative_to_home = str_replace( $home_url, '', $normalized_url );
		$home                  = set_url_scheme( get_option( 'home' ), 'http' );
		$siteurl               = set_url_scheme( get_option( 'siteurl' ), 'http' );
		$home_path             = rtrim( self::site_path(), '/' );

		// Adjust home_path if site is not is home.
		if ( ! empty( $home ) && 0 !== strcasecmp( $home, $siteurl ) ) {
			// $siteurl - $home/
			$wp_path_rel_to_home = rtrim( str_ireplace( $home, '', $siteurl ), '/' );

			if ( substr( $home_path, -strlen( $wp_path_rel_to_home ) ) === $wp_path_rel_to_home ) {
				$home_path = substr( $home_path, 0, -strlen( $wp_path_rel_to_home ) );
			}
		}

		// Common encoded characters.
		$path_relative_to_home = str_replace( '%20', ' ', $path_relative_to_home );

		$full_filename = $home_path . DIRECTORY_SEPARATOR .
			trim( $path_relative_to_home, DIRECTORY_SEPARATOR );

		$docroot = self::document_root();

		if ( substr( $full_filename, 0, strlen( $docroot ) ) === $docroot ) {
			$docroot_filename = substr( $full_filename, strlen( $docroot ) );
		} else {
			$docroot_filename = $path_relative_to_home;
		}

		/*
		 * Sometimes urls (coming from other plugins/themes)
		 * contain multiple "/" like "my-folder//myfile.js" which
		 * fails to recognize by filesystem, while url is accessible.
		 */
		$docroot_filename = str_replace( '//', DIRECTORY_SEPARATOR, $docroot_filename );

		return ltrim( $docroot_filename, DIRECTORY_SEPARATOR );
	}

	/**
	 * Document root to full filename.
	 *
	 * @static
	 *
	 * @param string $docroot_filename Document filename.
	 *
	 * @return string
	 */
	public static function docroot_to_full_filename( $docroot_filename ) {
		return rtrim( self::document_root(), DIRECTORY_SEPARATOR ) .
			DIRECTORY_SEPARATOR . $docroot_filename;
	}

	/**
	 * Removes WP query string from URL.
	 *
	 * @static
	 *
	 * @param string $url URL.
	 *
	 * @return string
	 */
	public static function remove_query( $url ) {
		$url = preg_replace( '~(\?|&amp;|&#038;|&)+ver=[a-z0-9-_\.]+~i', '', $url );

		return $url;
	}

	/**
	 * Removes all query strings from url.
	 *
	 * @static
	 *
	 * @param string $url URL.
	 *
	 * @return string
	 */
	public static function remove_query_all( $url ) {
		$pos = strpos( $url, '?' );
		if ( false === $pos ) {
			return $url;
		}

		return substr( $url, 0, $pos );
	}

	/**
	 * Converts win path to unix.
	 *
	 * @static
	 *
	 * @param string $path Path.
	 *
	 * @return string
	 */
	public static function normalize_path( $path ) {
		$path = preg_replace( '~[/\\\]+~', '/', $path );
		$path = rtrim( $path, '/' );

		return $path;
	}

	/**
	 * Returns real path of given path.
	 *
	 * @static
	 *
	 * @param string $path Path.
	 *
	 * @return string
	 */
	public static function realpath( $path ) {
		$path      = self::normalize_path( $path );
		$parts     = explode( '/', $path );
		$absolutes = array();

		foreach ( $parts as $part ) {
			if ( '.' === $part ) {
				continue;
			}

			if ( '..' === $part ) {
				array_pop( $absolutes );
			} else {
				$absolutes[] = $part;
			}
		}

		return implode( '/', $absolutes );
	}

	/**
	 * Returns real path of given path.
	 *
	 * @static
	 *
	 * @param string $path Path.
	 *
	 * @return string
	 */
	public static function path_remove_dots( $path ) {
		$parts     = explode( '/', $path );
		$absolutes = array();

		foreach ( $parts as $part ) {
			if ( '.' === $part ) {
				continue;
			}
			if ( '..' === $part ) {
				array_pop( $absolutes );
			} else {
				$absolutes[] = $part;
			}
		}

		return implode( '/', $absolutes );
	}

	/**
	 * Returns full URL from relative one.
	 *
	 * @static
	 *
	 * @param string $relative_url Relative URL.
	 *
	 * @return string
	 */
	public static function url_relative_to_full( $relative_url ) {
		$relative_url = self::path_remove_dots( $relative_url );

		if ( version_compare( PHP_VERSION, '5.4.7' ) < 0 ) {
			if ( substr( $relative_url, 0, 2 ) === '//' ) {
				$relative_url = ( self::is_https() ? 'https' : 'http' ) . ':' . $relative_url;
			}
		}

		$rel = wp_parse_url( $relative_url );
		// it's full url already.
		if ( isset( $rel['scheme'] ) || isset( $rel['host'] ) ) {
			return $relative_url;
		}

		if ( ! isset( $rel['host'] ) ) {
			$home_parsed = wp_parse_url( get_home_url() );
			$rel['host'] = $home_parsed['host'];
			if ( isset( $home_parsed['port'] ) ) {
				$rel['port'] = $home_parsed['port'];
			}
		}

		$scheme = isset( $rel['scheme'] ) ? $rel['scheme'] . '://' : '//';
		$host   = isset( $rel['host'] ) ? $rel['host'] : '';
		$port   = isset( $rel['port'] ) ? ':' . $rel['port'] : '';
		$path   = isset( $rel['path'] ) ? $rel['path'] : '';
		$query  = isset( $rel['query'] ) ? '?' . $rel['query'] : '';
		return "$scheme$host$port$path$query";
	}

	/**
	 * Redirects to URL.
	 *
	 * @static
	 *
	 * @param string $url    URL.
	 * @param array  $params Parameters.
	 *
	 * @return void
	 */
	public static function redirect( $url = '', $params = array() ) {
		$url = self::url_format( $url, $params );
		if ( function_exists( 'do_action' ) ) {
			do_action( 'w3tc_redirect' );
		}

		@header( 'Location: ' . $url ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		exit();
	}

	/**
	 * Redirects to URL.
	 *
	 * @static
	 *
	 * @param string $url           URL.
	 * @param array  $params        Parameters.
	 * @param bool   $safe_redirect Safe redirect or not.
	 *
	 * @return void
	 */
	public static function safe_redirect_temp( $url = '', $params = array(), $safe_redirect = false ) {
		$url = self::url_format( $url, $params );

		if ( function_exists( 'do_action' ) ) {
			do_action( 'w3tc_redirect' );
		}

		$status_code = 302;

		$protocol = isset( $_SERVER['SERVER_PROTOCOL'] ) ?
			htmlspecialchars( stripslashes( $_SERVER['SERVER_PROTOCOL'] ) ) : ''; // phpcs:ignore

		if ( 'HTTP/1.1' === $protocol ) {
			$status_code = 307;
		}

		$text = get_status_header_desc( $status_code );
		if ( ! empty( $text ) ) {
			$status_header = "$protocol $status_code $text";
			@header( $status_header, true, $status_code );
		}

		add_action(
			'wp_safe_redirect_fallback',
			array( '\W3TC\Util_Environment', 'wp_safe_redirect_fallback' )
		);

		@header( 'Cache-Control: no-cache' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		wp_safe_redirect( $url, $status_code );
		exit();
	}

	/**
	 * Fallback for wp_sfe_redirect().
	 *
	 * @static
	 *
	 * @param string $url URL.
	 *
	 * @return string
	 */
	public static function wp_safe_redirect_fallback( $url ) {
		return home_url( '?w3tc_repeat=invalid' );
	}

	/**
	 * Detects post ID.
	 *
	 * @static
	 *
	 * @return int
	 */
	public static function detect_post_id() {
		global $posts, $comment_post_ID, $post_ID; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase

		$p_val = Util_Request::get_integer( 'p' );

		if ( $post_ID ) {
			return $post_ID;
		} elseif ( $comment_post_ID ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
			return $comment_post_ID; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
		} elseif ( ( is_single() || is_page() ) && is_array( $posts ) && isset( $posts[0]->ID ) ) {
			return $posts[0]->ID;
		} elseif ( isset( $posts->ID ) ) {
			return $posts->ID;
		} elseif ( ! empty( $p_val ) ) {
			return $p_val;
		}

		return 0;
	}

	/**
	 * Get W3TC instance id.
	 *
	 * @static
	 *
	 * @return int
	 */
	public static function instance_id() {
		if ( defined( 'W3TC_INSTANCE_ID' ) ) {
			return W3TC_INSTANCE_ID;
		}

		static $instance_id;

		if ( ! isset( $instance_id ) ) {
			$config      = Dispatcher::config();
			$instance_id = $config->get_integer( 'common.instance_id', 0 );
		}

		return $instance_id;
	}

	/**
	 * Get W3TC edition.
	 *
	 * @static
	 *
	 * @param Config $config Config.
	 *
	 * @return string
	 */
	public static function w3tc_edition( $config = null ) {
		if ( self::is_w3tc_pro( $config ) && self::is_w3tc_pro_dev() ) {
			return 'pro development';
		}

		if ( self::is_w3tc_pro( $config ) ) {
			return 'pro';
		}

		return 'community';
	}

	/**
	 * Is W3TC Pro.
	 *
	 * @static
	 *
	 * @param Config $config Config.
	 *
	 * @return bool
	 */
	public static function is_w3tc_pro( $config = null ) {
		if ( is_object( $config ) ) {
			$plugin_type = $config->get_string( 'plugin.type' );

			if ( 'pro' === $plugin_type || 'pro_dev' === $plugin_type ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Enable Pro Dev mode support.
	 *
	 * @static
	 *
	 * @return bool
	 */
	public static function is_w3tc_pro_dev() {
		return defined( 'W3TC_PRO_DEV_MODE' ) && W3TC_PRO_DEV_MODE;
	}

	/**
	 * Pro constants?
	 *
	 * @since 2.8.3
	 *
	 * @static
	 *
	 * @param Config $config Config.
	 *
	 * @return bool
	 */
	public static function is_pro_constant( $config = null ) {
		return ( defined( 'W3TC_PRO' ) && W3TC_PRO ) || ( defined( 'W3TC_ENTERPRISE' ) && W3TC_ENTERPRISE );
	}

	/**
	 * Quotes regular expression string.
	 *
	 * @static
	 *
	 * @param string $string_value String.
	 * @param string $delimiter    Delimeter.
	 *
	 * @return string
	 */
	public static function preg_quote( $string_value, $delimiter = '~' ) {
		$string_value = preg_quote( $string_value, $delimiter );
		$string_value = strtr(
			$string_value,
			array( ' ' => '\ ' )
		);

		return $string_value;
	}

	/**
	 * Returns true if zlib output compression is enabled otherwise false.
	 *
	 * @static
	 *
	 * @return bool
	 */
	public static function is_zlib_enabled() {
		return self::to_boolean( ini_get( 'zlib.output_compression' ) );
	}

	/**
	 * Recursive strips slahes from the var.
	 *
	 * @static
	 *
	 * @param mixed $value Value.
	 *
	 * @return mixed
	 */
	public static function stripslashes( $value ) {
		if ( is_string( $value ) ) {
			return stripslashes( $value );
		} elseif ( is_array( $value ) ) {
			$value = array_map( array( '\W3TC\Util_Environment', 'stripslashes' ), $value );
		}

		return $value;
	}

	/**
	 * Checks if post should be flushed or not. Returns true if it should not be flushed.
	 *
	 * @static
	 *
	 * @param object $post Post object.
	 * @param string $module Which cache module to check against (pgcache, varnish, dbcache or objectcache).
	 * @param Config $config Config.
	 *
	 * @return bool
	 */
	public static function is_flushable_post( $post, $module, $config ) {
		if ( is_numeric( $post ) ) {
			$post = get_post( $post );
		}

		$post_status = array( 'publish' );

		/**
		 * Dont flush when we have post "attachment"
		 * its child of the post and is flushed always when post is published, while not changed in fact.
		 */
		$post_type = array( 'revision', 'attachment' );
		switch ( $module ) {
			case 'pgcache':
			case 'varnish':
			case 'posts':   // Means html content of post pages.
				if ( ! $config->get_boolean( 'pgcache.reject.logged' ) ) {
					$post_status[] = 'private';
				}
				break;
			case 'dbcache':
				if ( ! $config->get_boolean( 'dbcache.reject.logged' ) ) {
					$post_status[] = 'private';
				}
				break;
		}

		$flushable = is_object( $post ) && ! in_array( $post->post_type, $post_type, true ) && in_array( $post->post_status, $post_status, true );

		return apply_filters( 'w3tc_flushable_post', $flushable, $post, $module );
	}

	/**
	 * Checks if post belongs to a custom post type.
	 *
	 * @since 2.1.7
	 *
	 * @static
	 *
	 * @param object $post Post object.
	 *
	 * @return bool
	 */
	public static function is_custom_post_type( $post ) {
		$post_type = get_post_type_object( $post->post_type );

		// post type not found belongs to default post type(s).
		if ( empty( $post_type ) ) {
			return false;
		}

		// check if custom.
		if ( false === $post_type->_builtin ) {
			return true;
		}

		return false;
	}

	/**
	 * Converts value to boolean.
	 *
	 * @static
	 *
	 * @param mixed $value Value.
	 *
	 * @return bool
	 */
	public static function to_boolean( $value ) {
		if ( is_string( $value ) ) {
			switch ( strtolower( $value ) ) {
				case '+':
				case '1':
				case 'y':
				case 'on':
				case 'yes':
				case 'true':
				case 'enabled':
					return true;

				case '-':
				case '0':
				case 'n':
				case 'no':
				case 'off':
				case 'false':
				case 'disabled':
					return false;
			}
		}

		return (bool) $value;
	}

	/**
	 * Returns the apache, nginx version.
	 *
	 * @static
	 *
	 * @return string
	 */
	public static function get_server_version() {
		$sig     = explode(
			'/',
			isset( $_SERVER['SERVER_SOFTWARE'] ) ?
				htmlspecialchars( stripslashes( $_SERVER['SERVER_SOFTWARE'] ) ) : '' // phpcs:ignore
		);
		$temp    = isset( $sig[1] ) ? explode( ' ', $sig[1] ) : array( '0' );
		$version = $temp[0];

		return $version;
	}

	/**
	 * Checks if current request is REST REQUEST.
	 *
	 * @static
	 *
	 * @param string $url URL.
	 *
	 * @return string
	 */
	public static function is_rest_request( $url ) {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		// in case when called before constant is set
		// wp filters are not available in that case.
		return preg_match( '~' . W3TC_WP_JSON_URI . '~', $url );
	}

	/**
	 * Reset microcache.
	 *
	 * @static
	 *
	 * @return void
	 */
	public static function reset_microcache() {
		global $w3_current_blog_id;
		$w3_current_blog_id = null;

		self::$is_using_master_config = null;
	}

	/**
	 * Removes blank lines, trim values, removes duplicates, and sorts array.
	 *
	 * @since 2.4.3
	 *
	 * @param array $values Array of values.
	 *
	 * @return array
	 */
	public static function clean_array( $values ) {
		if ( ! empty( $values ) && is_array( $values ) ) {
			$values = array_unique(
				array_filter(
					array_map(
						'trim',
						$values
					),
					'strlen'
				)
			);
			sort( $values );
		}

		return $values;
	}

	/**
	 * Parses textarea setting value from string to array.
	 *
	 * @since 2.4.3
	 *
	 * @param string $value Value.
	 *
	 * @return array
	 */
	public static function textarea_to_array( $value ) {
		$values_array = array();

		if ( ! empty( $value ) ) {
			$values_array = self::clean_array(
				preg_split(
					'/\R/',
					$value,
					0,
					PREG_SPLIT_NO_EMPTY
				)
			);
		}

		return $values_array;
	}

	/**
	 * Is there a partial array intersections match?
	 *
	 * Returns true if any entries between the tewo arrays match string endings or in whole.
	 *
	 * @since  2.7.4
	 *
	 * @static
	 *
	 * @param array $array1 Array 1.
	 * @param array $array2 Array 2.
	 *
	 * @return bool
	 */
	public static function array_intersect_partial( array $array1, array $array2 ): bool {
		foreach ( $array1 as $url1 ) {
			foreach ( $array2 as $url2 ) {
				/**
				 * Parse array1 URLs to handle both full URLs and relative paths.
				 * If homepage then 'path' will be null, set to '/'.
				 */
				$parsed_url1         = \wp_parse_url( \trim( $url1, '/' ) );
				$parsed_url1['path'] = $parsed_url1['path'] ?? '/';

				/**
				 * Parse array2 URLs to handle both full URLs and relative paths.
				 * If value is '/' for homepage then don't trim, otherwise tirm.
				 */
				$parsed_url2 = \wp_parse_url( '/' === $url2 ? '/' : \trim( $url2, '/' ) );

				$is_host_set = isset( $parsed_url1['host'], $parsed_url2['host'] );

				if ( $url1 === $url2 ) {
					// Direct comparison for full URLs that are identical.
					return true;
				} elseif (
					isset( $parsed_url1['path'], $parsed_url2['path'] )
					&& (
						\substr( $parsed_url1['path'], -\strlen( $parsed_url2['path'] ) ) === $parsed_url2['path'] ||
						\substr( $parsed_url2['path'], -\strlen( $parsed_url1['path'] ) ) === $parsed_url1['path']
					) && ( ! $is_host_set || ( $is_host_set && $parsed_url1['host'] === $parsed_url2['host'] ) )
				) {
					/**
					 * Check if both parsed URLs have 'path' and 'host' component and if they match.
					 * If either 'host' is not set but 'path' matches, consider it a match.
					 */
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Generates an appropriate timestamp for WP cron using a selected time during the day in the form of an integer.
	 * Adjusts for discrepacy in timezones between WP and system and adds a day if the result is in the past.
	 *
	 * @since  2.8.0
	 *
	 * @static
	 *
	 * @param int $cron_time Cron schedule time.
	 *
	 * @return int
	 */
	public static function get_cron_schedule_time( int $cron_time = 0 ): int {
		// Get the current time in WordPress timezone.
		$current_time_wp = new \DateTime( 'now', wp_timezone() );

		// Convert the selected cron time into hours and minutes.
		$hour   = floor( $cron_time / 60 );
		$minute = $cron_time % 60;

		// Create a DateTime for today at the specified hour and minute in the user's timezone.
		$scheduled_time_user = new \DateTime( 'today', wp_timezone() );
		$scheduled_time_user->setTime( $hour, $minute );

		// Convert the user's scheduled time to UTC for WordPress.
		$scheduled_time_utc = clone $scheduled_time_user;
		$scheduled_time_utc->setTimezone( new \DateTimeZone( 'UTC' ) );

		// If the selected time has already passed today in UTC, schedule for tomorrow.
		if ( $scheduled_time_utc <= $current_time_wp ) {
			$scheduled_time_utc->modify( '+1 day' );
		}

		return $scheduled_time_utc->getTimestamp();
	}

	/**
	 * Tests the WP Cron spawning system and reports back its status.
	 *
	 * This command tests the spawning system by performing the following steps:
	 *
	 * * Checks to see if the `DISABLE_WP_CRON` constant is set; errors if true
	 * because WP-Cron is disabled.
	 * * Checks to see if the `ALTERNATE_WP_CRON` constant is set; warns if true.
	 * * Attempts to spawn WP-Cron over HTTP; warns if non 200 response code is
	 * returned.
	 *
	 * @since 2.8.0
	 *
	 * @link  https://github.com/wp-cli/cron-command/blob/v2.3.1/src/Cron_Command.php#L14-L55
	 *
	 * @return bool
	 */
	public static function is_wpcron_working(): bool {
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			$errormsg = 'The DISABLE_WP_CRON constant is set to true. WP-Cron spawning is disabled.';
			return false;
		}

		if ( defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON ) {
			$errormsg = 'The ALTERNATE_WP_CRON constant is set to true. WP-Cron spawning is not asynchronous.';
			return false;
		}

		$spawn = self::get_cron_spawn();

		if ( is_wp_error( $spawn ) ) {
			$errormsg = sprintf( 'WP-Cron spawn failed with error: %s', $spawn->get_error_message() );
			return false;
		}

		$code    = wp_remote_retrieve_response_code( $spawn );
		$message = wp_remote_retrieve_response_message( $spawn );

		if ( 200 === $code ) {
			// WP-Cron spawning is working as expected.
			return true;
		} else {
			$errormsg = sprintf( 'WP-Cron spawn returned HTTP status code: %1$s %2$s', $code, $message );
			return false;
		}
	}

	/**
	 * Spawns a request to `wp-cron.php` and return the response.
	 *
	 * This function is designed to mimic the functionality in `spawn_cron()`
	 * with the addition of returning the result of the `wp_remote_post()`
	 * request.
	 *
	 * @since 2.8.0
	 *
	 * @link  https://github.com/wp-cli/cron-command/blob/v2.3.1/src/Cron_Command.php#L57-L91
	 *
	 * @global $wp_version WordPress version string.
	 *
	 * @return WP_Error|array The response or WP_Error on failure.
	 */
	protected static function get_cron_spawn() {
		global $wp_version;

		$sslverify     = version_compare( $wp_version, 4.0, '<' );
		$doing_wp_cron = sprintf( '%.22F', microtime( true ) );

		$cron_request_array = array(
			'url'  => site_url( 'wp-cron.php?doing_wp_cron=' . $doing_wp_cron ),
			'key'  => $doing_wp_cron,
			'args' => array(
				'timeout'   => 3,
				'blocking'  => true,
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Calling native WordPress hook.
				'sslverify' => apply_filters( 'https_local_ssl_verify', $sslverify ),
			),
		);

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Calling native WordPress hook.
		$cron_request = apply_filters( 'cron_request', $cron_request_array );

		// Enforce a blocking request in case something that's hooked onto the 'cron_request' filter sets it to false.
		$cron_request['args']['blocking'] = true;

		$result = wp_remote_post( $cron_request['url'], $cron_request['args'] );

		return $result;
	}

	/**
	 * Gets the BoldGrid API URL
	 *
	 * This URL can be overridden by defining W3TC_API2_URL
	 *
	 * @since 2.8.3
	 *
	 * @return string The API URL to use for requests.
	 */
	public static function get_api_base_url() {
		return defined( 'W3TC_API2_URL' ) && W3TC_API2_URL ? esc_url( W3TC_API2_URL, array( 'https' ), '' ) : 'https://api2.w3-edge.com';
	}
}
