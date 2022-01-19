<?php
namespace W3TC;

class Root_Loader {
	/**
	 * Enabled Plugins that has been run
	 *
	 * @var W3_Plugin[]
	 */
	private $_loaded_plugins = array();
	/**
	 * Enabled extensions that has been run
	 *
	 * @var W3_Plugin[]
	 */
	private $_loaded_extensions = array();

	function __construct() {
		$c = Dispatcher::config();

		$plugins   = array();
		$plugins[] = new Generic_Plugin();

		if ( Util_Environment::is_w3tc_pro( $c ) ) {
			$plugins[] = new Update_Plugin();
		}

		if ( $c->get_boolean( 'dbcache.enabled' ) )
			$plugins[] = new DbCache_Plugin();
		if ( $c->get_boolean( 'objectcache.enabled' ) )
			$plugins[] = new ObjectCache_Plugin();
		if ( $c->get_boolean( 'pgcache.enabled' ) )
			$plugins[] = new PgCache_Plugin();
		if ( $c->get_boolean( 'cdn.enabled' ) )
			$plugins[] = new Cdn_Plugin();
		if ( $c->get_boolean( 'cdnfsd.enabled' ) )
			$plugins[] = new Cdnfsd_Plugin();
		if ( $c->get_boolean( 'lazyload.enabled' ) )
			$plugins[] = new UserExperience_LazyLoad_Plugin();
		if ( $c->get_boolean( 'browsercache.enabled' ) )
			$plugins[] = new BrowserCache_Plugin();
		if ( $c->get_boolean( 'minify.enabled' ) )
			$plugins[] = new Minify_Plugin();
		if ( $c->get_boolean( 'varnish.enabled' ) )
			$plugins[] = new Varnish_Plugin();
		if ( $c->get_boolean( 'stats.enabled' ) )
			$plugins[] = new UsageStatistics_Plugin();

		if ( is_admin() ) {
			$plugins[] = new Generic_Plugin_Admin();
			$plugins[] = new BrowserCache_Plugin_Admin();
			$plugins[] = new DbCache_Plugin_Admin();
			$plugins[] = new UserExperience_Plugin_Admin();
			$plugins[] = new ObjectCache_Plugin_Admin();
			$plugins[] = new PgCache_Plugin_Admin();
			$plugins[] = new Minify_Plugin_Admin();
			$plugins[] = new Generic_WidgetSpreadTheWord_Plugin();
			$plugins[] = new Generic_Plugin_WidgetNews();
			$plugins[] = new Generic_Plugin_WidgetForum();
			$plugins[] = new SystemOpCache_Plugin_Admin();

			$plugins[] = new Cdn_Plugin_Admin();
			$plugins[] = new Cdnfsd_Plugin_Admin();
			$cdn_engine = $c->get_string( 'cdn.engine' );
			if ( $cdn_engine == 'maxcdn' ) {
				$plugins[] = new Cdn_Plugin_WidgetMaxCdn();
			}

			if ( $c->get_boolean( 'widget.pagespeed.enabled' ) )
				$plugins[] = new PageSpeed_Plugin_Widget();

			$plugins[] = new Generic_Plugin_AdminCompatibility();
			$plugins[] = new Licensing_Plugin_Admin();

			if ( $c->get_boolean( 'pgcache.enabled' ) ||
				$c->get_boolean( 'varnish.enabled' ) )
				$plugins[] = new Generic_Plugin_AdminRowActions();

			$plugins[] = new Extensions_Plugin_Admin();
			$plugins[] = new Generic_Plugin_AdminNotifications();
			$plugins[] = new UsageStatistics_Plugin_Admin();
			$plugins[] = new SetupGuide_Plugin_Admin();
			$plugins[] = new FeatureShowcase_Plugin_Admin();
		} else {
			if ( $c->get_boolean( 'jquerymigrate.disabled' ) ) {
				$plugins[] = new UserExperience_Plugin_Jquery();
			}
		}

		$this->_loaded_plugins = $plugins;

		register_activation_hook( W3TC_FILE, array(
				$this,
				'activate'
			) );

		register_deactivation_hook( W3TC_FILE, array(
				$this,
				'deactivate'
			) );
	}

	/**
	 * Run plugins
	 */
	function run() {
		foreach ( $this->_loaded_plugins as $plugin ) {
			$plugin->run();
		}

		if ( method_exists( $GLOBALS['wpdb'], 'on_w3tc_plugins_loaded' ) ) {
			$o = $GLOBALS['wpdb'];
			$o->on_w3tc_plugins_loaded();
		}

		$this->run_extensions();
	}

	/**
	 * Activation action hook
	 */
	public function activate( $network_wide ) {
		Root_AdminActivation::activate( $network_wide );
	}

	/**
	 * Deactivation action hook
	 */
	public function deactivate() {
		Root_AdminActivation::deactivate();
	}

	/**
	 * Loads extensions stored in config
	 */
	function run_extensions() {
		$c = Dispatcher::config();
		$extensions = $c->get_array( 'extensions.active' );

		$frontend = $c->get_array( 'extensions.active_frontend' );
		foreach ( $frontend as $extension => $nothing ) {
			if ( isset( $extensions[$extension] ) ) {
				$path = $extensions[$extension];
				$filename = W3TC_EXTENSION_DIR . '/' .
					str_replace( '..', '', trim( $path, '/' ) );

				if ( file_exists( $filename ) ) {
					include_once( $filename );
				}
			}
		}

		if ( is_admin() ) {
			foreach ( $extensions as $extension => $path ) {
				$filename = W3TC_EXTENSION_DIR . '/' .
					str_replace( '..', '', trim( $path, '/' ) );

				if ( file_exists( $filename ) ) {
					include_once( $filename );
				}
			}
		}

		w3tc_do_action( 'wp_loaded' );
		do_action( 'w3tc_extension_load' );
		if ( is_admin() ) {
			do_action( 'w3tc_extension_load_admin' );
		}

		// Hide Image Service converted images.
		$settings   = $c->get_array( 'imageservice' );
		$visibility = isset( $settings['visibility'] ) ? $settings['visibility'] : 'never';

		if ( 'never' === $visibility || ( 'extension' === $visibility && ! isset( $extensions['imageservice'] ) ) ) {
			add_action(
				'pre_get_posts',
				function( $query ) {
					if ( ! is_admin() || ! $query->is_main_query() ) {
						return;
					}

					$screen = get_current_screen();

					if ( ! $screen || 'upload' !== $screen->id || 'attachment' !== $screen->post_type ) {
						return;
					}

					$query->set(
						'meta_query',
						array(
							array(
								'key'     => 'w3tc_imageservice_file',
								'compare' => 'NOT EXISTS',
							),
						)
					);

					return;
				}
			);

			add_filter(
				'ajax_query_attachments_args',
				function( $args ) {
					if ( ! is_admin() ) {
						return;
					}

					// Modify the query.
					$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'     => 'w3tc_imageservice_file',
							'compare' => 'NOT EXISTS',
						),
					);

					return $args;
				}
			);
		}
	}
}

global $w3tc_root;
if ( is_null( $w3tc_root ) ) {
	$w3tc_root = new \W3TC\Root_Loader();
	$w3tc_root->run();
}
