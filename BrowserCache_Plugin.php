<?php
/**
 * File: BrowserCache_Plugin.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class BrowserCache_Plugin
 *
 * W3 ObjectCache plugin
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.PHP.IniSet.Risky
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 */
class BrowserCache_Plugin {
	/**
	 * Config
	 *
	 * @var Config
	 */
	private $_config = null;

	/**
	 * Browsercache rewrite
	 *
	 * @var bool
	 */
	private $browsercache_rewrite;

	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();
	}

	/**
	 * Runs plugin
	 *
	 * @return void
	 */
	public function run() {
		add_filter( 'w3tc_admin_bar_menu', array( $this, 'w3tc_admin_bar_menu' ) );

		if ( $this->_config->get_boolean( 'browsercache.html.w3tc' ) ) {
			add_action( 'send_headers', array( $this, 'send_headers' ) );
		}

		if ( ! $this->_config->get_boolean( 'browsercache.html.etag' ) ) {
			add_filter( 'wp_headers', array( $this, 'filter_wp_headers' ), 0, 2 );
		}

		$url_uniqualize_enabled = $this->url_uniqualize_enabled();

		if ( $this->url_clean_enabled() || $url_uniqualize_enabled ) {
			$this->browsercache_rewrite = $this->_config->get_boolean( 'browsercache.rewrite' );

			// modify CDN urls.
			add_filter( 'w3tc_cdn_url', array( $this, 'w3tc_cdn_url' ), 0, 3 );

			if ( $url_uniqualize_enabled ) {
				add_action( 'w3tc_flush_all', array( $this, 'w3tc_flush_all' ), 1050, 1 );
			}

			if ( $this->can_ob() ) {
				Util_Bus::add_ob_callback( 'browsercache', array( $this, 'ob_callback' ) );
			}
		}

		$v = $this->_config->get_string( 'browsercache.security.session.cookie_httponly' );
		if ( ! empty( $v ) ) {
			@ini_set( 'session.cookie_httponly', 'on' === $v ? '1' : '0' ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged
		}

		$v = $this->_config->get_string( 'browsercache.security.session.cookie_secure' );
		if ( ! empty( $v ) ) {
			@ini_set( 'session.cookie_secure', 'on' === $v ? '1' : '0' ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged
		}

		$v = $this->_config->get_string( 'browsercache.security.session.use_only_cookies' );
		if ( ! empty( $v ) ) {
			@ini_set( 'session.use_only_cookies', 'on' === $v ? '1' : '0' ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged
		}

		add_filter( 'w3tc_minify_http2_preload_url', array( $this, 'w3tc_minify_http2_preload_url' ), 4000 );
		add_filter( 'w3tc_cdn_config_headers', array( $this, 'w3tc_cdn_config_headers' ) );

		if ( Util_Admin::is_w3tc_admin_page() ) {
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		}
	}

	/**
	 * Check if URL clean is enabled
	 *
	 * @return bool
	 */
	private function url_clean_enabled() {
		return $this->_config->get_boolean( 'browsercache.cssjs.querystring' ) ||
			$this->_config->get_boolean( 'browsercache.html.querystring' ) ||
			$this->_config->get_boolean( 'browsercache.other.querystring' );
	}

	/**
	 * Check if URL uniqualize is enabled
	 *
	 * @return bool
	 */
	private function url_uniqualize_enabled() {
		return $this->_config->get_boolean( 'browsercache.cssjs.replace' ) ||
			$this->_config->get_boolean( 'browsercache.html.replace' ) ||
			$this->_config->get_boolean( 'browsercache.other.replace' );
	}

	/**
	 * Flush all
	 *
	 * @param array $extras Extras.
	 *
	 * @return void
	 */
	public function w3tc_flush_all( $extras = array() ) {
		if ( isset( $extras['only'] ) && 'browsercache' !== $extras['only'] ) {
			return;
		}

		update_option( 'w3tc_browsercache_flush_timestamp', wp_rand( 10000, 99999 ) . '' );
	}

	/**
	 * Check if we can start OB
	 *
	 * @return boolean
	 */
	public function can_ob() {
		/**
		 * Skip if admin
		 */
		if ( defined( 'WP_ADMIN' ) ) {
			return false;
		}

		/**
		 * Skip if doing AJAX
		 */
		if ( defined( 'DOING_AJAX' ) ) {
			return false;
		}

		/**
		 * Skip if doing cron
		 */
		if ( defined( 'DOING_CRON' ) ) {
			return false;
		}

		/**
		 * Skip if APP request
		 */
		if ( defined( 'APP_REQUEST' ) ) {
			return false;
		}

		/**
		 * Skip if XMLRPC request
		 */
		if ( defined( 'XMLRPC_REQUEST' ) ) {
			return false;
		}

		/**
		 * Check for WPMU's and WP's 3.0 short init
		 */
		if ( defined( 'SHORTINIT' ) && SHORTINIT ) {
			return false;
		}

		// Do not skip output buffering based on User-Agent (client-spoofable); see Generic_Plugin::can_ob().

		return true;
	}

	/**
	 * Output buffer callback
	 *
	 * @param string $buffer Buffer.
	 *
	 * @return mixed
	 */
	public function ob_callback( $buffer ) {
		if ( '' !== $buffer && Util_Content::is_html_xml( $buffer ) ) {
			$domain_url_regexp = Util_Environment::home_domain_root_url_regexp();

			$buffer = preg_replace_callback(
				'~(href|src|action|extsrc|asyncsrc|w3tc_load_js\()=?([\'"])((' . $domain_url_regexp . ')?(/[^\'"/][^\'"]*\.([a-z-_]+)([\?#][^\'"]*)?))[\'"]~Ui',
				array( $this, 'link_replace_callback' ),
				$buffer
			);

			// without quotes.
			$buffer = preg_replace_callback(
				'~(href|src|action|extsrc|asyncsrc)=((' . $domain_url_regexp . ')?(/[^\\s>][^\\s>]*\.([a-z-_]+)([\?#][^\\s>]*)?))([\\s>])~Ui',
				array( $this, 'link_replace_callback_noquote' ),
				$buffer
			);
		}

		return $buffer;
	}

	/**
	 * Link replace callback
	 *
	 * @param string $matches Matches.
	 *
	 * @return string
	 */
	public function link_replace_callback( $matches ) {
		list ( $w3tc_match, $attr, $quote, $w3tc_url, , , , , $w3tc_extension ) = $matches;

		$ops = $this->_get_url_mutation_operations( $w3tc_url, $w3tc_extension );
		if ( is_null( $ops ) ) {
			return $w3tc_match;
		}

		$w3tc_url = $this->mutate_url( $w3tc_url, $ops, ! $this->browsercache_rewrite );

		if ( 'w3tc_load_js(' !== $attr ) {
			return $attr . '=' . $quote . $w3tc_url . $quote;
		}

		return sprintf( '%s\'%s\'', $attr, $w3tc_url );
	}

	/**
	 * Link replace callback when no quote arount attribute value
	 *
	 * @param string $matches Matches.
	 *
	 * @return string
	 */
	public function link_replace_callback_noquote( $matches ) {
		list ( $w3tc_match, $attr, $w3tc_url, , , , , $w3tc_extension, , $delimiter ) = $matches;

		$ops = $this->_get_url_mutation_operations( $w3tc_url, $w3tc_extension );
		if ( is_null( $ops ) ) {
			return $w3tc_match;
		}

		$w3tc_url = $this->mutate_url( $w3tc_url, $ops, ! $this->browsercache_rewrite );

		return $attr . '=' . $w3tc_url . $delimiter;
	}

	/**
	 * Mutate http/2 header links
	 *
	 * @param array $w3tc_data Data.
	 *
	 * @return array
	 */
	public function w3tc_minify_http2_preload_url( $w3tc_data ) {
		if ( isset( $w3tc_data['browsercache_processed'] ) ) {
			return $w3tc_data;
		}

		$w3tc_data['browsercache_processed'] = '*';
		$w3tc_url                            = $w3tc_data['result_link'];

		// decouple extension.
		$matches = array();
		if ( ! preg_match( '/\.([a-zA-Z0-9]+)($|[\?])/', $w3tc_url, $matches ) ) {
			return $w3tc_data;
		}
		$w3tc_extension = $matches[1];

		$ops = $this->_get_url_mutation_operations( $w3tc_url, $w3tc_extension );
		if ( is_null( $ops ) ) {
			return $w3tc_data;
		}

		$mutate_by_querystring = ! $this->browsercache_rewrite;

		$w3tc_url                 = $this->mutate_url( $w3tc_url, $ops, $mutate_by_querystring );
		$w3tc_data['result_link'] = $w3tc_url;

		return $w3tc_data;
	}

	/**
	 * Link replace for CDN url
	 *
	 * @param string $w3tc_url           URL.
	 * @param string $original_url  Original URL.
	 * @param bool   $is_cdn_mirror Is CDN mirror.
	 *
	 * @return string
	 */
	public function w3tc_cdn_url( $w3tc_url, $original_url, $is_cdn_mirror ) {
		// decouple extension.
		$matches = array();
		if ( ! preg_match( '/\.([a-zA-Z0-9]+)($|[\?])/', $original_url, $matches ) ) {
			return $w3tc_url;
		}
		$w3tc_extension = $matches[1];

		$ops = $this->_get_url_mutation_operations( $original_url, $w3tc_extension );
		if ( is_null( $ops ) ) {
			return $w3tc_url;
		}

		// for push cdns each flush would require manual reupload of files.
		$mutate_by_querystring = ! $this->browsercache_rewrite || ! $is_cdn_mirror;

		$w3tc_url = $this->mutate_url( $w3tc_url, $ops, $mutate_by_querystring );

		return $w3tc_url;
	}

	/**
	 * Mutate url
	 *
	 * @param string $w3tc_url                   URL.
	 * @param array  $ops                   Operations data.
	 * @param bool   $mutate_by_querystring Mutate by querystring flag.
	 *
	 * @return string
	 */
	private function mutate_url( $w3tc_url, $ops, $mutate_by_querystring ) {
		$query_pos = strpos( $w3tc_url, '?' );
		if ( isset( $ops['querystring'] ) && false !== $query_pos ) {
			$w3tc_url  = substr( $w3tc_url, 0, $query_pos );
			$query_pos = false;
		}

		if ( isset( $ops['replace'] ) ) {
			$id = $this->get_filename_uniqualizator();

			if ( $mutate_by_querystring ) {
				if ( false !== $query_pos ) {
					$w3tc_url = substr( $w3tc_url, 0, $query_pos + 1 ) . $id . '&amp;' . substr( $w3tc_url, $query_pos + 1 );
				} else {
					$tag_pos = strpos( $w3tc_url, '#' );
					if ( false === $tag_pos ) {
						$w3tc_url .= '?' . $id;
					} else {
						$w3tc_url = substr( $w3tc_url, 0, $tag_pos ) . '?' . $id . substr( $w3tc_url, $tag_pos );
					}
				}
			} else {
				// add $id to url before extension.
				$url_query = '';
				if ( false !== $query_pos ) {
					$url_query = substr( $w3tc_url, $query_pos );
					$w3tc_url  = substr( $w3tc_url, 0, $query_pos );
				}

				$ext_pos        = strrpos( $w3tc_url, '.' );
				$w3tc_extension = substr( $w3tc_url, $ext_pos );

				$w3tc_url = substr( $w3tc_url, 0, strlen( $w3tc_url ) - strlen( $w3tc_extension ) ) .
					'.' . $id . $w3tc_extension . $url_query;
			}
		}

		return $w3tc_url;
	}

	/**
	 * Get mutatation url operations
	 *
	 * @param string $w3tc_url       URL.
	 * @param string $w3tc_extension Operations data.
	 *
	 * @return string
	 */
	public function _get_url_mutation_operations( $w3tc_url, $w3tc_extension ) {
		static $extensions = null;
		if ( null === $extensions ) {
			$core       = Dispatcher::component( 'BrowserCache_Core' );
			$extensions = $core->get_replace_querystring_extensions( $this->_config );
		}

		static $exceptions = null;
		if ( null === $exceptions ) {
			$exceptions = $this->_config->get_array( 'browsercache.replace.exceptions' );
		}

		if ( ! isset( $extensions[ $w3tc_extension ] ) ) {
			return null;
		}

		$test_url = Util_Environment::remove_query( $w3tc_url );
		foreach ( $exceptions as $exception ) {
			$escaped = str_replace( '~', '\~', $exception );
			if ( trim( $exception ) && preg_match( '~' . $escaped . '~', $test_url ) ) {
				return null;
			}
		}

		return $extensions[ $w3tc_extension ];
	}

	/**
	 * Returns replace ID
	 *
	 * @return string
	 */
	public function get_filename_uniqualizator() {
		static $cache_id = null;

		if ( null === $cache_id ) {
			$w3tc_value = get_option( 'w3tc_browsercache_flush_timestamp' );

			if ( empty( $w3tc_value ) ) {
				$w3tc_value = wp_rand( 10000, 99999 ) . '';
				update_option( 'w3tc_browsercache_flush_timestamp', $w3tc_value );
			}

			$cache_id = substr( $w3tc_value, 0, 5 );
		}

		return 'x' . $cache_id;
	}

	/**
	 * Admin bar menu
	 *
	 * @param array $menu_items Menu items.
	 *
	 * @return array
	 */
	public function w3tc_admin_bar_menu( $menu_items ) {
		$browsercache_update_media_qs = (
			$this->_config->get_boolean( 'browsercache.cssjs.replace' ) ||
			$this->_config->get_boolean( 'browsercache.other.replace' )
		);

		if ( $browsercache_update_media_qs ) {
			$current_page = Util_Request::get_string( 'page', 'w3tc_dashboard' );

			$menu_items['20190.browsercache'] = array(
				'id'     => 'w3tc_flush_browsercache',
				'parent' => 'w3tc_flush',
				'title'  => __( 'Browser Cache', 'w3-total-cache' ),
				'href'   => Util_Nonce::admin_nonce_url(
					admin_url(
						'admin.php?page=' . $current_page . '&amp;w3tc_flush_browser_cache'
					),
					'w3tc_flush_browser_cache'
				),
			);
		}

		return $menu_items;
	}

	/**
	 * Send headers
	 *
	 * @return void
	 */
	public function send_headers() {
		@header( 'X-Powered-By: ' . Util_Environment::w3tc_header() );
	}

	/**
	 * Returns headers config for CDN
	 *
	 * @param Config $w3tc_config Config.
	 *
	 * @return Config
	 */
	public function w3tc_cdn_config_headers( $w3tc_config ) {
		$sections = Util_Mime::sections_to_mime_types_map();
		foreach ( $sections as $section => $v ) {
			$w3tc_config[ $section ] = $this->w3tc_cdn_config_headers_section( $section );
		}

		return $w3tc_config;
	}

	/**
	 * Gets CDN config headers section
	 *
	 * @param string $section Section.
	 *
	 * @return Config
	 */
	private function w3tc_cdn_config_headers_section( $section ) {
		$w3tc_c   = $this->_config;
		$prefix   = 'browsercache.' . $section;
		$lifetime = $w3tc_c->get_integer( $prefix . '.lifetime' );

		$headers = array();

		if ( $w3tc_c->get_boolean( $prefix . '.w3tc' ) ) {
			$headers['X-Powered-By'] = Util_Environment::w3tc_header();
		}

		if ( $w3tc_c->get_boolean( $prefix . '.cache.control' ) ) {
			switch ( $w3tc_c->get_string( $prefix . '.cache.policy' ) ) {
				case 'cache':
					$headers['Pragma']        = 'public';
					$headers['Cache-Control'] = 'public';
					break;

				case 'cache_public_maxage':
					$headers['Pragma']        = 'public';
					$headers['Cache-Control'] = "max-age=$lifetime, public";
					break;

				case 'cache_validation':
					$headers['Pragma']        = 'public';
					$headers['Cache-Control'] = 'public, must-revalidate, proxy-revalidate';
					break;

				case 'cache_noproxy':
					$headers['Pragma']        = 'public';
					$headers['Cache-Control'] = 'private, must-revalidate';
					break;

				case 'cache_maxage':
					$headers['Pragma']        = 'public';
					$headers['Cache-Control'] = "max-age=$lifetime, public, must-revalidate, proxy-revalidate";
					break;

				case 'no_cache':
					$headers['Pragma']        = 'no-cache';
					$headers['Cache-Control'] = 'private, no-cache';
					break;

				case 'no_store':
					$headers['Pragma']        = 'no-store';
					$headers['Cache-Control'] = 'no-store';
					break;

				case 'cache_immutable':
					$headers['Pragma']        = 'public';
					$headers['Cache-Control'] = "max-age=$lifetime, public, immutable";
					break;

				case 'cache_immutable_nomaxage':
					$headers['Pragma']        = 'public';
					$headers['Cache-Control'] = 'public, immutable';
					break;
			}
		}

		return array(
			'etag'     => $w3tc_c->get_boolean( $prefix . 'etag' ),
			'expires'  => $w3tc_c->get_boolean( $prefix . '.expires' ),
			'lifetime' => $lifetime,
			'static'   => $headers,
		);
	}

	/**
	 * Filters headers set by WordPress
	 *
	 * @param array  $headers Headers.
	 * @param object $wp      WP object.
	 *
	 * @return array
	 */
	public function filter_wp_headers( $headers, $wp ) {
		if ( ! empty( $wp->query_vars['feed'] ) ) {
			unset( $headers['ETag'] );
		}

		return $headers;
	}

	/**
	 * Admin notice for Content-Security-Policy-Report-Only that displays if the feature is enabled and the report-uri/to isn't defined.
	 *
	 * @since 2.3.1
	 */
	public function admin_notices() {
		// Check if the current user is a contributor or higher.
		if (
			\user_can( \get_current_user_id(), 'manage_options' ) &&
			$this->_config->get_boolean( 'browsercache.security.cspro' ) &&
			empty( $this->_config->get_string( 'browsercache.security.cspro.reporturi' ) ) &&
			empty( $this->_config->get_string( 'browsercache.security.cspro.reportto' ) )
		) {
			$w3tc_message = '<p>' . sprintf(
				// translators: 1 opening HTML a tag to Browser Cache CSP-Report-Only settings, 2 closing HTML a tag.
				esc_html__(
					'The Content Security Policy - Report Only requires the "report-uri" and/or "report-to" directives. Please define one or both of these directives %1$shere%2$s.',
					'w3-total-cache'
				),
				'<a href="' . Util_Ui::admin_url( 'admin.php?page=w3tc_browsercache#browsercache__security__cspro' ) . '" target="_blank" alt="' . esc_attr__( 'Browser Cache Content-Security-Policy-Report-Only Settings', 'w3-total-cache' ) . '">',
				'</a>'
			);
			Util_Ui::error_box( $w3tc_message );
		}
	}
}
