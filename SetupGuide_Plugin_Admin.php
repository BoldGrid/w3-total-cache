<?php
/**
 * File: SetupGuide_Plugin_Admin.php
 *
 * @since X.X.X
 *
 * @package    W3TC
 */

namespace W3TC;

/**
 * Class: SetupGuide_Plugin_Admin
 *
 * @since X.X.X
 */
class SetupGuide_Plugin_Admin {
	/**
	 * Current page.
	 *
	 * @since  X.X.X
	 * @access protected
	 *
	 * @var string
	 */
	protected $_page = 'w3tc_setup_guide';

	/**
	 * Wizard template.
	 *
	 * @var \W3TC\Wizard\Template
	 */
	private static $template;

	/**
	 * Constructor.
	 *
	 * @since X.X.X
	 */
	public function __construct() {
		require_once W3TC_INC_DIR . '/wizard/template.php';

		if ( is_null( self::$template ) ) {
			self::$template = new Wizard\Template( $this->get_config() );
		}
	}

	/**
	 * Run.
	 *
	 * Needed by the Root_Loader.
	 *
	 * @since X.X.X
	 */
	public function run() {
	}

	/**
	 * Display the setup guide.
	 *
	 * @since X.X.X
	 *
	 * @see \W3TC\Wizard\Template::render()
	 */
	public function load() {
		self::$template->render();
	}

	/**
	 * Admin-Ajax: Set option to skip the setup guide.
	 *
	 * @since X.X.X
	 */
	public function skip() {
		if ( wp_verify_nonce( $_POST['_wpnonce'], 'w3tc_wizard' ) ) {
			update_site_option( 'w3tc_setupguide_completed', time() );
			wp_send_json_success();
		} else {
			wp_send_json_error( 'Security violation', 403 );
		}
	}

	/**
	 * Abbreviate a URL for display in a small space.
	 *
	 * @since X.X.X
	 *
	 * @param string $url
	 * @return string
	 */
	public function abbreviate_url( $url ) {
		$url = untrailingslashit( str_replace(
			array(
				'https://',
				'http://',
				'www.',
			),
			'',
			$url
		) );

		if ( strlen( $url ) > 35 ) {
			$url = substr( $url, 0, 10 ) . '&hellip;' . substr( $url, -20 );
		}

		return $url;
	}

	/**
	 * Admin-Ajax: Test Page Cache.
	 *
	 * @since  X.X.X
	 *
	 * @see self::abbreviate_url()
	 * @see \W3TC\Util_Http::ttfb()
	 */
	public function test_pgcache() {
		if ( wp_verify_nonce( $_POST['_wpnonce'], 'w3tc_wizard' ) ) {
			$nocache = ! empty( $_POST['nocache'] );
			$url     = site_url();
			$results = array(
				'nocache'  => $nocache,
				'url'      => $url,
				'urlshort' => $this->abbreviate_url( $url ),
			);

			if ( ! $nocache ) {
				Util_Http::get( $url, array( 'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ) ) );
			}

			$results['ttfb'] = Util_Http::ttfb( $url, $nocache );

			wp_send_json_success( $results );
		} else {
			wp_send_json_error( esc_html__( 'Security violation', 'w3-total-cache' ), 403 );
		}
	}

	/**
	 * Admin-Ajax: Get the page cache settings.
	 *
	 * @since  X.X.X
	 *
	 * @see \W3TC\Config::get_boolean()
	 * @see \W3TC\Config::get_string()
	 */
	public function get_pgcache_settings() {
		if ( wp_verify_nonce( $_POST['_wpnonce'], 'w3tc_wizard' ) ) {
			$config = new Config();

			wp_send_json_success( array(
				'enabled' => $config->get_boolean( 'pgcache.enabled' ),
				'engine'  => $config->get_string( 'pgcache.engine' ),
			) );
		} else {
			wp_send_json_error( esc_html__( 'Security violation', 'w3-total-cache' ), 403 );
		}
	}

	/**
	 * Admin-Ajax: Configure the page cache settings.
	 *
	 * @since  X.X.X
	 *
	 * @see \W3TC\Config::get_boolean()
	 * @see \W3TC\Config::get_string()
	 * @see \W3TC\Util_Installed::$engine()
	 * @see \W3TC\Config::set()
	 * @see \W3TC\Config::save()
	 * @see \W3TC\Dispatcher::component()
	 * @see \W3TC\CacheFlush::flush_posts()
	 */
	public function config_pgcache() {
		if ( wp_verify_nonce( $_POST['_wpnonce'], 'w3tc_wizard' ) ) {
			$enable          = ! empty( $_POST['enable'] );
			$engine          = empty( $_POST['engine'] ) ? '' : esc_attr( trim( $_POST['engine'] ) );
			$is_updating     = false;
			$success         = false;
			$config          = new Config();
			$pgcache_enabled = $config->get_boolean( 'pgcache.enabled' );
			$pgcache_engine  = $config->get_string( 'pgcache.engine' );
			$allowed_engines = array(
				'',
				'file',
				'file_generic',
				'redis',
				'memcached',
				'nginx_memcached',
				'apc',
				'eaccelerator',
				'xcache',
				'wincache',
			);

			if ( in_array( $engine, $allowed_engines, true ) ) {
				if ( empty( $engine ) || 'file' === $engine || 'file_generic' === $engine || Util_Installed::$engine() ) {
					if ( $pgcache_enabled !== $enable ) {
						$config->set( 'pgcache.enabled', $enable );
						$is_updating = true;
					}

					if ( ! empty( $engine ) && $pgcache_engine !== $engine ) {
						$config->set( 'pgcache.engine', $engine );
						$is_updating = true;
					}

					if ( $is_updating ) {
						$config->save();

						$f = Dispatcher::component( 'CacheFlush' );
						$f->flush_posts();

						$e = Dispatcher::component( 'PgCache_Environment' );
						$e->fix_on_wpadmin_request( $config, true );
					}

					if ( $config->get_boolean( 'pgcache.enabled' ) === $enable &&
						( ! $enable || $config->get_string( 'pgcache.engine' ) === $engine ) ) {
							$success = true;
							$message = __( 'Settings updated', 'w3-total-cache' );
					} else {
						$message = __( 'Settings not updated', 'w3-total-cache' );
					}
				} else {
					$message = __( 'Requested cache storage engine is not available', 'w3-total-cache' );
				}
			} elseif ( ! $is_allowed_engine ) {
				$message = __( 'Requested cache storage engine is invalid', 'w3-total-cache' );
			}

			wp_send_json_success( array(
				'success'           => $success,
				'message'           => $message,
				'enable'            => $enable,
				'engine'            => $engine,
				'current_enabled'   => $config->get_boolean( 'pgcache.enabled' ),
				'current_engine'    => $config->get_string( 'pgcache.engine' ),
				'previous_enabled'  => $pgcache_enabled,
				'previous_engine'   => $pgcache_engine,
			) );
		} else {
			wp_send_json_error( esc_html__( 'Security violation', 'w3-total-cache' ), 403 );
		}
	}

	/**
	 * Admin-Ajax: Test database cache.
	 *
	 * @since X.X.X
	 *
	 * @see \W3TC\Config::get_boolean()
	 * @see \W3TC\Config::get_string()
	 *
	 * @global $wpdb WordPress database object.
	 */
	public function test_dbcache() {
		if ( wp_verify_nonce( $_POST['_wpnonce'], 'w3tc_wizard' ) ) {
			$config  = new Config();
			$results = array(
				'enabled' => $config->get_boolean( 'dbcache.enabled' ),
				'engine'  => $config->get_string( 'dbcache.engine' ),
				'elapsed' => null,
			);

			global $wpdb;

			$wpdb->flush();

			$start_time = microtime( true );
			$wpdb->timer_start();

			// Test insert, get, and delete 200 records.
			$table  = $wpdb->prefix . 'options';
			$option = 'w3tc_test_dbcache_';

			for ( $x=0; $x < 200; $x++ ) {
				$wpdb->insert(
					$table,
					array(
						'option_name'  => $option . $x,
						'option_value' => 'blah',
					)
				);

				$select = $wpdb->prepare(
					'SELECT `option_value` FROM `' . $table . '` WHERE `option_name` = %s AND `option_name` NOT LIKE %s',
					$option . $x,
					'NotAnOption'
				);

				$wpdb->get_var( $select );

				//$wpdb->flush();

				$wpdb->get_var( $select );

				$wpdb->update(
					$table,
					array( 'option_name' => $option . $x ),
					array( 'option_value' => 'This is a dummy test.' )
				);

				$wpdb->delete( $table, array( 'option_name' => $option . $x ) );
			}

			$results['wpdb_time'] = $wpdb->timer_stop();
			$results['exec_time'] = microtime( true ) - $start_time;
			$results['elapsed']   = $results['wpdb_time'];

			wp_send_json_success( $results );
		} else {
			wp_send_json_error( esc_html__( 'Security violation', 'w3-total-cache' ), 403 );
		}
	}

	/**
	 * Admin-Ajax: Get the database cache settings.
	 *
	 * @since  X.X.X
	 *
	 * @see \W3TC\Config::get_boolean()
	 * @see \W3TC\Config::get_string()
	 */
	public function get_dbcache_settings() {
		if ( wp_verify_nonce( $_POST['_wpnonce'], 'w3tc_wizard' ) ) {
			$config = new Config();

			wp_send_json_success( array(
				'enabled' => $config->get_boolean( 'dbcache.enabled' ),
				'engine'  => $config->get_string( 'dbcache.engine' ),
			) );
		} else {
			wp_send_json_error( esc_html__( 'Security violation', 'w3-total-cache' ), 403 );
		}
	}

	/**
	 * Admin-Ajax: Configure the database cache settings.
	 *
	 * @since  X.X.X
	 *
	 * @see \W3TC\Config::get_boolean()
	 * @see \W3TC\Config::get_string()
	 * @see \W3TC\Util_Installed::$engine()
	 * @see \W3TC\Config::set()
	 * @see \W3TC\Config::save()
	 * @see \W3TC\Dispatcher::component()
	 * @see \W3TC\CacheFlush::dbcache_flush()
	 */
	public function config_dbcache() {
		if ( wp_verify_nonce( $_POST['_wpnonce'], 'w3tc_wizard' ) ) {
			$enable          = ! empty( $_POST['enable'] );
			$engine          = empty( $_POST['engine'] ) ? '' : esc_attr( trim( $_POST['engine'] ) );
			$is_updating     = false;
			$success         = false;
			$config          = new Config();
			$old_enabled     = $config->get_boolean( 'dbcache.enabled' );
			$old_engine      = $config->get_string( 'dbcache.engine' );
			$allowed_engines = array(
				'',
				'file',
				'redis',
				'memcached',
				'apc',
				'eaccelerator',
				'xcache',
				'wincache',
			);

			if ( in_array( $engine, $allowed_engines, true ) ) {
				if ( empty( $engine ) || 'file' === $engine || Util_Installed::$engine() ) {
					if ( $old_enabled !== $enable ) {
						$config->set( 'dbcache.enabled', $enable );
						$is_updating = true;
					}

					if ( ! empty( $engine ) && $old_engine !== $engine ) {
						$config->set( 'dbcache.engine', $engine );
						$is_updating = true;
					}

					if ( $is_updating ) {
						$config->save();

						$f = Dispatcher::component( 'CacheFlush' );
						$f->dbcache_flush();
					}

					if ( $config->get_boolean( 'dbcache.enabled' ) === $enable &&
						( ! $enable || $config->get_string( 'dbcache.engine' ) === $engine ) ) {
							$success = true;
							$message = __( 'Settings updated', 'w3-total-cache' );
					} else {
						$message = __( 'Settings not updated', 'w3-total-cache' );
					}
				} else {
					$message = __( 'Requested cache storage engine is not available', 'w3-total-cache' );
				}
			} elseif ( ! $is_allowed_engine ) {
				$message = __( 'Requested cache storage engine is invalid', 'w3-total-cache' );
			}

			wp_send_json_success( array(
				'success'           => $success,
				'message'           => $message,
				'enable'            => $enable,
				'engine'            => $engine,
				'current_enabled'   => $config->get_boolean( 'dbcache.enabled' ),
				'current_engine'    => $config->get_string( 'dbcache.engine' ),
				'previous_enabled'  => $old_enabled,
				'previous_engine'   => $old_engine,
			) );
		} else {
			wp_send_json_error( esc_html__( 'Security violation', 'w3-total-cache' ), 403 );
		}
	}

	/**
	 * Admin-Ajax: Test object cache.
	 *
	 * @since X.X.X
	 *
	 * @see \W3TC\Config::get_boolean()
	 * @see \W3TC\Config::get_string()
	 */
	public function test_objcache() {
		if ( wp_verify_nonce( $_POST['_wpnonce'], 'w3tc_wizard' ) ) {
			$config  = new Config();
			$results = array(
				'enabled' => $config->get_boolean( 'objectcache.enabled' ),
				'engine'  => $config->get_string( 'objectcache.engine' ),
				'elapsed' => null,
			);


			$start_time = microtime( true );

			$posts = get_posts( array(
				'post_type' => array(
					'page',
					'post',
				),
			) );

			$results['elapsed'] = microtime( true ) - $start_time;
			$results['post_ct'] = count( $posts );

			wp_send_json_success( $results );
		} else {
			wp_send_json_error( esc_html__( 'Security violation', 'w3-total-cache' ), 403 );
		}
	}

	/**
	 * Admin-Ajax: Get the object cache settings.
	 *
	 * @since  X.X.X
	 *
	 * @see \W3TC\Config::get_boolean()
	 * @see \W3TC\Config::get_string()
	 */
	public function get_objcache_settings() {
		if ( wp_verify_nonce( $_POST['_wpnonce'], 'w3tc_wizard' ) ) {
			$config = new Config();

			wp_send_json_success( array(
				'enabled' => $config->get_boolean( 'objectcache.enabled' ),
				'engine'  => $config->get_string( 'objectcache.engine' ),
			) );
		} else {
			wp_send_json_error( esc_html__( 'Security violation', 'w3-total-cache' ), 403 );
		}
	}

	/**
	 * Admin-Ajax: Configure the object cache settings.
	 *
	 * @since  X.X.X
	 *
	 * @see \W3TC\Config::get_boolean()
	 * @see \W3TC\Config::get_string()
	 * @see \W3TC\Util_Installed::$engine()
	 * @see \W3TC\Config::set()
	 * @see \W3TC\Config::save()
	 * @see \W3TC\Dispatcher::component()
	 * @see \W3TC\CacheFlush::objcache_flush()
	 */
	public function config_objcache() {
		if ( wp_verify_nonce( $_POST['_wpnonce'], 'w3tc_wizard' ) ) {
			$enable          = ! empty( $_POST['enable'] );
			$engine          = empty( $_POST['engine'] ) ? '' : esc_attr( trim( $_POST['engine'] ) );
			$is_updating     = false;
			$success         = false;
			$config          = new Config();
			$old_enabled     = $config->get_boolean( 'objectcache.enabled' );
			$old_engine      = $config->get_string( 'objectcache.engine' );
			$allowed_engines = array(
				'',
				'file',
				'redis',
				'memcached',
				'apc',
				'eaccelerator',
				'xcache',
				'wincache',
			);

			if ( in_array( $engine, $allowed_engines, true ) ) {
				if ( empty( $engine ) || 'file' === $engine || Util_Installed::$engine() ) {
					if ( $old_enabled !== $enable ) {
						$config->set( 'objectcache.enabled', $enable );
						$is_updating = true;
					}

					if ( ! empty( $engine ) && $old_engine !== $engine ) {
						$config->set( 'objectcache.engine', $engine );
						$is_updating = true;
					}

					if ( $is_updating ) {
						$config->save();

						$f = Dispatcher::component( 'CacheFlush' );
						$f->objectcache_flush();
					}

					if ( $config->get_boolean( 'objectcache.enabled' ) === $enable &&
						( ! $enable || $config->get_string( 'objectcache.engine' ) === $engine ) ) {
							$success = true;
							$message = __( 'Settings updated', 'w3-total-cache' );
					} else {
						$message = __( 'Settings not updated', 'w3-total-cache' );
					}
				} else {
					$message = __( 'Requested cache storage engine is not available', 'w3-total-cache' );
				}
			} elseif ( ! $is_allowed_engine ) {
				$message = __( 'Requested cache storage engine is invalid', 'w3-total-cache' );
			}

			wp_send_json_success( array(
				'success'           => $success,
				'message'           => $message,
				'enable'            => $enable,
				'engine'            => $engine,
				'current_enabled'   => $config->get_boolean( 'objectcache.enabled' ),
				'current_engine'    => $config->get_string( 'objectcache.engine' ),
				'previous_enabled'  => $old_enabled,
				'previous_engine'   => $old_engine,
			) );
		} else {
			wp_send_json_error( esc_html__( 'Security violation', 'w3-total-cache' ), 403 );
		}
	}

	/**
	 * Admin-Ajax: Test URL addreses for Browser Cache header.
	 *
	 * @since  X.X.X
	 *
	 * @see \W3TC\CacheFlush::browsercache_flush()
	 * @see \W3TC\Util_Http::get_headers()
	 */
	public function test_browsercache() {
		if ( wp_verify_nonce( $_POST['_wpnonce'], 'w3tc_wizard' ) ) {
			$results = array();
			$urls    = array(
				trailingslashit( site_url() ),
				esc_url( plugin_dir_url( __FILE__ ) . 'pub/css/setup-guide.css' ),
				esc_url( plugin_dir_url( __FILE__ ) . 'pub/js/setup-guide.js' ),
			);

			$f = Dispatcher::component( 'CacheFlush' );
			$f->browsercache_flush();

			$header_missing = esc_html__( 'Not present', 'w3-total-cache' );

			foreach ( $urls as $url ) {
				$headers = Util_Http::get_headers( $url );

				$results[] = array(
					'url'       => $url,
					'filename'  => basename( $url ),
					'header'    => empty( $headers['cache-control'] ) ? $header_missing : $headers['cache-control'],
					'headers'   => empty( $headers ) || ! is_array( $headers ) ? array() : $headers,
				);
			}

			wp_send_json_success( $results );
		} else {
			wp_send_json_error( esc_html__( 'Security violation', 'w3-total-cache' ), 403 );
		}
	}

	/**
	 * Admin-Ajax: Get the browser cache settings.
	 *
	 * @since  X.X.X
	 *
	 * @see \W3TC\Config::get_boolean()
	 * @see \W3TC\Config::get_string()
	 */
	public function get_browsercache_settings() {
		if ( wp_verify_nonce( $_POST['_wpnonce'], 'w3tc_wizard' ) ) {
			$config = new Config();

			wp_send_json_success( array(
				'enabled'             => $config->get_boolean( 'browsercache.enabled' ),
				'cssjs.cache.control' => $config->get_boolean( 'browsercache.cssjs.cache.control' ),
				'cssjs.cache.policy'  => $config->get_string( 'browsercache.cssjs.cache.policy' ),
				'html.cache.control'  => $config->get_boolean( 'browsercache.html.cache.control' ),
				'html.cache.policy'   => $config->get_string( 'browsercache.html.cache.policy' ),
				'other.cache.control' => $config->get_boolean( 'browsercache.other.cache.control' ),
				'other.cache.policy'  => $config->get_string( 'browsercache.other.cache.policy' )
			) );
		} else {
			wp_send_json_error( esc_html__( 'Security violation', 'w3-total-cache' ), 403 );
		}
	}

	/**
	 * Admin-Ajax: Configure the browser cache settings.
	 *
	 * @since  X.X.X
	 *
	 * @see \W3TC\Dispatcher::component()
	 * @see \W3TC\Config::get_boolean()
	 * @see \W3TC\Config::set()
	 * @see \W3TC\Config::save()
	 * @see \W3TC\CacheFlush::browsercache_flush()
	 *
	 * @uses $_POST['enable']
	 */
	public function config_browsercache() {
		if ( wp_verify_nonce( $_POST['_wpnonce'], 'w3tc_wizard' ) ) {
			$enable               = ! empty( $_POST['enable'] );
			$config               = new Config();
			$browsercache_enabled = $config->get_boolean( 'browsercache.enabled' );

			if ( $browsercache_enabled !== $enable ) {
				$config->set( 'browsercache.enabled', $enable );
				$config->set( 'browsercache.cssjs.cache.control', true );
				$config->set( 'browsercache.cssjs.cache.policy', 'cache_public_maxage' );
				$config->set( 'browsercache.html.cache.control', true );
				$config->set( 'browsercache.html.cache.policy', 'cache_public_maxage' );
				$config->set( 'browsercache.other.cache.control', true );
				$config->set( 'browsercache.other.cache.policy', 'cache_public_maxage' );
				$config->save();

				$f = Dispatcher::component( 'CacheFlush' );
				$f->browsercache_flush();

				$e = Dispatcher::component( 'BrowserCache_Environment' );
				$e->fix_on_wpadmin_request( $config, true );
			}

			$is_enabled = $config->get_boolean( 'browsercache.enabled' );

			wp_send_json_success(
				array(
					'success'                => $is_enabled === $enable,
					'enable'                 => $enable,
					'browsercache_enabled'   => $config->get_boolean( 'browsercache.enabled' ),
					'browsercache_previous'  => $browsercache_enabled,
				)
			);
		} else {
			wp_send_json_error( esc_html__( 'Security violation', 'w3-total-cache' ), 403 );
		}
	}

	/**
	 * Get configuration.
	 *
	 * @since  X.X.X
	 * @access private
	 *
	 * @return array
	 */
	private function get_config() {
		$config               = new Config();
		$browsercache_enabled = $config->get_boolean( 'browsercache.enabled' );

		return array(
			'title'          => esc_html__( 'Setup Guide', 'w3-total-cache' ),
			'scripts'        => array(
				array(
					'handle'    => 'setup-guide',
					'src'       => esc_url( plugin_dir_url( __FILE__ ) . 'pub/js/setup-guide.js' ),
					'deps'      => array( 'jquery' ),
					'version'   => W3TC_VERSION,
					'in_footer' => false,
					'localize'  => array(
						'object_name' => 'W3TC_SetupGuide',
						'data'      => array(
							'test_complete_msg' => __(
								'Testing complete.  Click Next to advance to the section and see the results.',
								'w3-total-cache'
							),
							'test_error_msg' => __(
								'Could not perform this test.  Please reload the page to try again or click skip button to abort the setup guide.',
								'w3-total-cache'
							),
							'config_error_msg' => __(
								'Could not update configuration.  Please reload the page to try again or click skip button to abort the setup guide.',
								'w3-total-cache'
							),
							'unavailable_text' => __( 'Unavailable', 'w3-total-cache' ),
							'none'             => __( 'None', 'w3-total-cache' ),
							'disk'             => __( 'Disk', 'w3-total-cache' ),
							'disk_basic'       => __( 'Disk: Basic', 'w3-total-cache' ),
							'disk_enhanced'    => __( 'Disk: Enhanced', 'w3-total-cache' ),
							'enabled'          => __( 'Enabled', 'w3-total-cache' ),
						),
					),
				),
			),
			'styles'         => array(
				array(
					'handle'  => 'setup-guide',
					'src'     => esc_url( plugin_dir_url( __FILE__ ) . 'pub/css/setup-guide.css' ),
					'version' => W3TC_VERSION,
				),
			),
			'actions'        => array(
				array(
					'tag'      => 'wp_ajax_w3tc_wizard_skip',
					'function' => array(
						$this,
						'skip',
					),
				),
				array(
					'tag'      => 'wp_ajax_w3tc_get_pgcache_settings',
					'function' => array(
						$this,
						'get_pgcache_settings',
					),
				),
				array(
					'tag'      => 'wp_ajax_w3tc_test_pgcache',
					'function' => array(
						$this,
						'test_pgcache',
					),
				),
				array(
					'tag'      => 'wp_ajax_w3tc_config_pgcache',
					'function' => array(
						$this,
						'config_pgcache',
					),
				),
				array(
					'tag'      => 'wp_ajax_w3tc_get_dbcache_settings',
					'function' => array(
						$this,
						'get_dbcache_settings',
					),
				),
				array(
					'tag'      => 'wp_ajax_w3tc_test_dbcache',
					'function' => array(
						$this,
						'test_dbcache',
					),
				),
				array(
					'tag'      => 'wp_ajax_w3tc_config_dbcache',
					'function' => array(
						$this,
						'config_dbcache',
					),
				),
				array(
					'tag'      => 'wp_ajax_w3tc_get_objcache_settings',
					'function' => array(
						$this,
						'get_objcache_settings',
					),
				),
				array(
					'tag'      => 'wp_ajax_w3tc_test_objcache',
					'function' => array(
						$this,
						'test_objcache',
					),
				),
				array(
					'tag'      => 'wp_ajax_w3tc_config_objcache',
					'function' => array(
						$this,
						'config_objcache',
					),
				),
				array(
					'tag'      => 'wp_ajax_w3tc_get_browsercache_settings',
					'function' => array(
						$this,
						'get_browsercache_settings',
					),
				),
				array(
					'tag'      => 'wp_ajax_w3tc_test_browsercache',
					'function' => array(
						$this,
						'test_browsercache',
					),
				),
				array(
					'tag'      => 'wp_ajax_w3tc_config_browsercache',
					'function' => array(
						$this,
						'config_browsercache',
					),
				),
			),
			'steps_location' => 'left',
			'steps'          => array(
				array(
					'id'   => 'pgcache',
					'text' => __( 'Page Cache', 'w3-total-cache' ),
				),
				array(
					'id'   => 'dbcache',
					'text' => __( 'Database Cache', 'w3-total-cache' ),
				),
				array(
					'id'   => 'objectcache',
					'text' => __( 'Object Cache', 'w3-total-cache' ),
				),
				array(
					'id'   => 'browsercache',
					'text' => __( 'Browser Cache', 'w3-total-cache' ),
				),
				array(
					'id'   => 'lazyload',
					'text' => __( 'Lazy Load', 'w3-total-cache' ),
				),
				array(
					'id'   => 'more',
					'text' => __( 'More Caching Options', 'w3-total-cache' ),
				),
			),
			'slides'         => array(
				array( // Welcome.
					'headline' => __( 'Welcome to the W3 Total Cache Setup Guide!', 'w3-total-cache' ),
					'id'       => 'welcome',
					'markup'   => '<p>' .
						esc_html__(
							'You have selected the Performance Suite that professionals have consistently ranked #1 for options and speed improvements.',
							'w3-total-cache'
						) . '</p>
						<p><strong>' . esc_html__( 'W3 Total Cache', 'w3-total-cache' ) . '</strong>
						' . esc_html__(
							'provides many options to help your website perform faster.  While the ideal settings vary for every website, there are a few settings we recommend that you enable now.',
							'w3-total-cache'
						) . '</p>',
				),
				array( // Page Cache.
					'headline' => __( 'Page Cache', 'w3-total-cache' ),
					'id'       => 'pc1',
					'markup'   => '<p>' . sprintf(
						// translators: 1: HTML emphesis open tag, 2: HTML emphesis close tag.
						esc_html__(
							'When users visit your website, their browser must connect to your server, wait for your server to respond with the web page, and then display it.  The time it takes between your browser requesting the web page and the receiving of the very first byte of that web page is referred to as %1$sTime to First Byte%2$s.',
							'w3-total-cache'
						),
						'<em>',
						'</em>'
					) . '</p>
					<p>
						<strong>' . esc_html__( 'W3 Total Cache', 'w3-total-cache' ) . '</strong> ' .
						esc_html__( 'can help you speed up', 'w3-total-cache' ) .
						' <em>' . esc_html__( 'Time to First Byte', 'w3-total-cache' ) . '</em>.
					</p>
					<p>' .
					esc_html__(
						'This test only measures the performance of your homepage. Other pages on your site, such as a store or a forum, may have higher or lower load times. Stay tuned for future releases to include more tests!',
						'w3-total-cache'
					) . '</p>
					<p>
						<input id="w3tc-test-pgcache" class="button-primary" type="button" value="' .
						esc_html__( 'Test Page Cache', 'w3-total-cache' ) . '">
						<span class="hidden"><span class="spinner inline"></span>' . esc_html__( 'Measuring', 'w3-total-cache' ) .
						' <em>' . esc_html__( 'Time to First Byte', 'w3-total-cache' ) . '</em>&hellip;
						</span>
					</p>
					<p class="hidden">
						' . esc_html__( 'Test URL:', 'w3-total-cache' ) .' <span id="w3tc-test-url"></span>
					</p>
					<table id="w3tc-pgcache-table" class="w3tc-setupguide-table hidden">
						<thead>
							<tr>
								<th>' . esc_html__( 'Select', 'w3-total-cache' ) . '</th>
								<th>' . esc_html__( 'Storage Engine', 'w3-total-cache' ) . '</th>
								<th>' . esc_html__( 'Time (ms)', 'w3-total-cache' ) . '</th>
							</tr>
						</thead>
						<tbody></tbody>
					</table>',
				),
				array( // Database Cache.
					'headline' => __( 'Database Cache', 'w3-total-cache' ),
					'id'       => 'dbc1',
					'markup'   => '<p>' . esc_html__(
						'Many database queries are made in every dynamic page request.  A database cache may speed-up generation of dynamic pages.',
						'w3-total-cache'
						) . '</p>
						<p>' . esc_html__(
							'Database Cache serves query results directly from a storage engine.  By default, this feature is disabled.  We recommend using Redis or Memcached, otherwise leave this feature disabled as the server database engine may be faster than using disk caching.',
							'w3-total-cache'
						) . '</p>
						<p>
						<input id="w3tc-test-dbcache" class="button-primary" type="button" value="' .
						esc_html__( 'Test Database Cache', 'w3-total-cache' ) . '">
						<span class="hidden"><span class="spinner inline"></span>' . esc_html__( 'Testing', 'w3-total-cache' ) .
						' <em>' . esc_html__( 'Database Cache', 'w3-total-cache' ) . '</em>&hellip;
						</span>
						</p>
						<table id="w3tc-dbc-table" class="w3tc-setupguide-table hidden">
							<thead>
								<tr>
									<th>' . esc_html__( 'Select', 'w3-total-cache' ) . '</th>
									<th>' . esc_html__( 'Storage Engine', 'w3-total-cache' ) . '</th>
									<th>' . esc_html__( 'Time (ms)', 'w3-total-cache' ) . '</th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
						<p id="w3tc-test-dbc-query"></p>',
				),
				array( // Object Cache.
					'headline' => __( 'Object Cache', 'w3-total-cache' ),
					'id'       => 'oc1',
					'markup'   => '<p>' . esc_html__(
						'WordPress caches objects used to build pages, but does not reuse them for future page requests.',
						'w3-total-cache'
						) . '</p>
						<p><strong>' . esc_html__( 'W3 Total Cache', 'w3-total-cache' ) . '</strong> ' .
						esc_html__( 'can help you speed up dynamic pages by persistently storing objects.', 'w3-total-cache' ) .
						'</p>
						<p>
						<input id="w3tc-test-objcache" class="button-primary" type="button" value="' .
						esc_html__( 'Test Object Cache', 'w3-total-cache' ) . '">
						<span class="hidden"><span class="spinner inline"></span>' . esc_html__( 'Testing', 'w3-total-cache' ) .
						' <em>' . esc_html__( 'Object Cache', 'w3-total-cache' ) . '</em>&hellip;
						</span>
						</p>
						<table id="w3tc-objcache-table" class="w3tc-setupguide-table hidden">
							<thead>
								<tr>
									<th>' . esc_html__( 'Select', 'w3-total-cache' ) . '</th>
									<th>' . esc_html__( 'Storage Engine', 'w3-total-cache' ) . '</th>
									<th>' . esc_html__( 'Time (ms)', 'w3-total-cache' ) . '</th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>',
				),
				array( // Browser Cache.
					'headline' => __( 'Browser Cache', 'w3-total-cache' ),
					'id'       => 'bc1',
					'markup'   => '<p>' . esc_html__(
						'To render your website, browsers must download many different types of assets, including javascript files, CSS stylesheets, images, and more.  For most assets, once a browser has downloaded them, they shouldn\'t have to download them again.',
						'w3-total-cache'
						) . '</p>
						<p><strong>' . esc_html__( 'W3 Total Cache', 'w3-total-cache' ) . '</strong> ' .
						esc_html__(
							'can help ensure browsers are properly caching your assets.  Before making any changes, let\'s first review your current browser cache settings.',
							'w3-total-cache'
						) . '</p>
						<p>' . sprintf(
							// translators: 1: HTML emphesis open tag, 2: HTML emphesis close tag.
							esc_html__(
								'The %1$sCache-Control%2$s header tells your browser how it should cache specific files.  The %1$smax-age%2$s setting tells your browser how long, in seconds, it should use its cached version of a file before requesting an updated one.',
								'w3-total-cache'
							),
							'<em>',
							'</em>'
						) . '</p>
						<p>' . sprintf(
							// translators: 1: HTML emphesis open tag, 2: HTML emphesis close tag.
							esc_html__(
								'To improve %1$sBrowser Cache%2$s, we recommend enabling %1$sBrowser Cache%2$s setting.',
								'w3-total-cache',
							),
							'<em>',
							'</em>'
						) . '</p>
						<input id="w3tc-test-browsercache" class="button-primary" type="button" value="' .
						esc_html__( 'Test Browser Cache', 'w3-total-cache' ) . '">
						<span class="hidden"><span class="spinner inline"></span>' . esc_html__( 'Testing', 'w3-total-cache' ) .
						' <em>' . esc_html__( 'Browser Cache', 'w3-total-cache' ) . '</em>&hellip;
						</span>
						</p>
						<table id="w3tc-browsercache-table" class="w3tc-setupguide-table hidden">
						<thead>
						<tr>
							<th>' . esc_html__( 'Select', 'w3-total-cache' ) . '</th>
							<th>' . esc_html__( 'Setting', 'w3-total-cache' ) . '</th>
							<th>' . esc_html__( 'File', 'w3-total-cache' ) . '</th>
							<th>' . esc_html__( 'Cache-Control Header', 'w3-total-cache' ) . '</th>
						</tr>
						</thead>
						<tbody></tbody>
						</table>',
				),
				array( // Lazy load.
					'headline' => __( 'Lazy Load', 'w3-total-cache' ),
					'id'       => 'll1',
					'markup'   => '<p>' . esc_html__(
						'Pages containing iamges and other objects can have their load time reduced by deferring the loading the items until they are needed.  For example, images can be loaded when a visitor scrolls down the page to make them visible.',
						'w3-total-cache'
						) . '</p>',
				),
				array( // Setup complete.
					'headline' => __( 'Setup Complete!', 'w3-total-cache' ),
					'id'       => 'complete',
					'markup'   => '<p>' . sprintf(
							// translators: 1: HTML strong open tag, 2: HTML strong close tag.
							esc_html__(
								'%1$sTime to First Byte%2$s has change by %3$s!',
								'w3-total-cache',
							),
							'<strong>',
							'</strong>',
							'<span id="w3tc-ttfb-diff">0%</span>'
						) . '</p>
						<p>' . sprintf(
							// translators: 1: HTML strong open tag, 2: HTML strong close tag.
							esc_html__(
								'%1$sBrowser Cache%2$s headers are ' .
								( $browsercache_enabled ? 'now' : '%1$sNOT%2$s' ) .
								' being set for your JavaScript, CSS, and images.',
								'w3-total-cache',
							),
							'<strong>',
							'</strong>'
						) . '</p>
						<h3>' . esc_html__( 'What\'s Next?', 'w3-total-cache' ) . '</h3>
						<p>' . sprintf(
							// translators: 1: HTML emphesis open tag, 2: HTML emphesis close tag, 3: HTML break tag, 4: Anchor/link open tag, 5: Anchor/link close tag.
							esc_html__(
								'Your website\'s performance can still be improved by configuring %1$sminify%2$s settings, setting up a %1$sCDN%2$s, and more! %3$sPlease visit your %4$sW3TC Dashboard%5$s to learn more about these features.',
								'w3-total-cache',
							),
							'<strong>',
							'</strong>',
							'<br />',
							'<a href="' . esc_url( admin_url( 'admin.php?page=w3tc_dashboard' ) ) . '">',
							'</a>'
						) . '</p>',
				),
			),
		);
	}
}
