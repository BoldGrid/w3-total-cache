<?php
/**
 * File: FeatureShowcase_Plugin_Admin.php
 *
 * @since 2.1.0
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: FeatureShowcase_Plugin_Admin
 *
 * @since 2.1.0
 */
class FeatureShowcase_Plugin_Admin {
	/**
	 * Current page.
	 *
	 * @since  2.1.0
	 * @access private
	 *
	 * @var string
	 */
	private $_page = 'w3tc_feature_showcase'; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Constructor.
	 *
	 * @since 2.1.0
	 *
	 * @see Util_Request::get_string()
	 * @see self::enqueue_styles()
	 * @see self::set_config()
	 */
	public function __construct() {
		$page = Util_Request::get_string( 'page' );

		if ( 'w3tc_feature_showcase' === $page ) {
			add_action(
				'admin_enqueue_scripts',
				array(
					$this,
					'enqueue_styles',
				)
			);
		}
	}

	/**
	 * Run.
	 *
	 * Run by Root_Loader.
	 *
	 * @since 2.1.0
	 */
	public function run() {
	}

	/**
	 * Render the page.
	 *
	 * @since 2.1.0
	 *
	 * @see Dispatcher::config()
	 * @see self::get_cards()
	 */
	public function load() {
		$config = Dispatcher::config();
		$cards  = self::get_cards();

		require W3TC_DIR . '/FeatureShowcase_Plugin_Admin_View.php';

		// Mark unseen new features as seen.
		$this->mark_seen();
	}

	/**
	 * Enqueue styles.
	 *
	 * @since 2.1.0
	 */
	public function enqueue_styles() {
		wp_enqueue_style(
			'w3tc_feature_showcase',
			esc_url( plugin_dir_url( __FILE__ ) . 'pub/css/feature-showcase.css' ),
			array(),
			W3TC_VERSION
		);
	}

	/**
	 * Mark all new features as seen.
	 *
	 * @since 2.1.0
	 *
	 * @global $current_user WordPress current user.
	 *
	 * @see self::get_cards()
	 */
	public function mark_seen() {
		global $current_user;

		$features_seen = (array) get_user_meta( $current_user->ID, 'w3tc_features_seen', true );
		$cards         = self::get_cards();
		$updated       = false;

		foreach ( $cards as $id => $card ) {
			if ( ! empty( $card['is_new'] ) && ! in_array( $id, $features_seen, true ) ) {
				$features_seen[] = $id;
				$updated         = true;
			}
		}

		if ( $updated ) {
			sort( $features_seen );

			$features_seen = array_unique( array_filter( $features_seen ) );

			update_user_meta( $current_user->ID, 'w3tc_features_seen', $features_seen );
		}
	}


	/**
	 * Get the new feature unseen count.
	 *
	 * @since 2.1.0
	 *
	 * @static
	 *
	 * @global $current_user WordPress current user.
	 *
	 * @see self::get_cards()
	 *
	 * @return int
	 */
	public static function get_unseen_count() {
		$config              = Dispatcher::config();
		$force_master_config = $config->get_boolean( 'common.force_master' );

		if ( is_multisite() && $force_master_config && ! is_super_admin() ) {
			return 0;
		}

		global $current_user;

		$unseen_count  = 0;
		$features_seen = (array) get_user_meta( $current_user->ID, 'w3tc_features_seen', true );
		$cards         = self::get_cards();

		// Iterate through the new features and check if already seen.
		foreach ( $cards as $id => $card ) {
			if ( ! empty( $card['is_new'] ) && ! in_array( $id, $features_seen, true ) ) {
				$unseen_count++;
			}
		}

		return $unseen_count;
	}

	/**
	 * Get the feature cards.
	 *
	 * @since 2.1.0
	 *
	 * @access private
	 * @static
	 *
	 * @return array
	 */
	private static function get_cards() {
		return array(
			'setup_guide'         => array(
				'title'      => esc_html__( 'Setup Guide Wizard', 'w3-total-cache' ),
				'icon'       => 'dashicons-superhero',
				'text'       => esc_html__( 'The Setup Guide wizard quickly walks you through configuring W3 Total Cache.', 'w3-total-cache' ),
				'button'     => '<button class="button" onclick="window.location=\'' .
					esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_setup_guide' ) ) . '\'">' .
					__( 'Launch', 'w3-total-cache' ) . '</button>',
				'link'       => '<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/setup-guide-wizard/?utm_source=w3tc&utm_medium=feature_showcase&utm_campaign=setup_guide' ) .
					'">' . __( 'More info', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a>',
				'is_premium' => false,
				'is_new'     => true,
			),
			'lazyload_gmaps'      => array(
				'title'      => esc_html__( 'Lazy Load Google Maps', 'w3-total-cache' ),
				'icon'       => 'dashicons-admin-site',
				'text'       => esc_html__( 'Defer loading offscreen Google Maps, making pages load faster.', 'w3-total-cache' ),
				'button'     => '<button class="button" onclick="window.location=\'' .
					esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_userexperience' ) ) . '\'">' .
					__( 'Settings', 'w3-total-cache' ) . '</button>',
				'link'       => '<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/lazy-load-google-maps/?utm_source=w3tc&utm_medium=feature_showcase&utm_campaign=pro_lazyload_googlemaps' ) .
					'">' . __( 'More info', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a>',
				'is_premium' => true,
				'is_new'     => true,
			),
			'cdn_fsd'             => array(
				'title'      => esc_html__( 'Full Site Delivery via CDN', 'w3-total-cache' ),
				'icon'       => 'dashicons-networking',
				'text'       => esc_html__( 'Provide the best user experience possible by enhancing by hosting HTML pages and RSS feeds with (supported) CDN\'s high speed global networks.', 'w3-total-cache' ),
				'button'     => '<button class="button" onclick="window.location=\'' .
					esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_general#cdn' ) ) . '\'">' .
					__( 'Settings', 'w3-total-cache' ) . '</button>',
				'link'       => '<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/cdn-full-site-delivery/?utm_source=w3tc&utm_medium=feature_showcase&utm_campaign=pro_cdn_fsd' ) .
					'">' . __( 'More info', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a>',
				'is_premium' => true,
				'is_new'     => false,
			),
			'render_blocking_css' => array(
				'title'      => esc_html__( 'Eliminate Render Blocking CSS', 'w3-total-cache' ),
				'icon'       => 'dashicons-table-row-delete',
				'text'       => esc_html__( 'Render blocking CSS delays a webpage from being visible in a timely manner. Eliminate this easily with the click of a button in W3 Total Cache Pro.', 'w3-total-cache' ),
				'button'     => '<button class="button" onclick="window.location=\'' .
					esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_minify#css' ) ) . '\'">' .
					__( 'Settings', 'w3-total-cache' ) . '</button>',
				'link'       => '<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/how-to-use-manual-minify-for-css-and-js/?utm_source=w3tc&utm_medium=feature_showcase&utm_campaign=pro_minify_CSS' ) .
					'">' . __( 'More info', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a>',
				'is_premium' => true,
				'is_new'     => false,
			),
			'extension_framework' => array(
				'title'      => esc_html__( 'Extension Framework', 'w3-total-cache' ),
				'icon'       => 'dashicons-insert',
				'text'       => esc_html__( 'Improve the performance of your Genesis, WPML powered site, and much more. StudioPress\' Genesis Framework is up to 60% faster with W3TC Pro.', 'w3-total-cache' ),
				'button'     => '<button class="button" onclick="window.location=\'' .
					esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_extensions' ) ) . '\'">' .
					__( 'Settings', 'w3-total-cache' ) . '</button>',
				'link'       => '<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/extension-framework-pro/?utm_source=w3tc&utm_medium=feature_showcase&utm_campaign=pro_extensions' ) .
					'">' . __( 'More info', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a>',
				'is_premium' => true,
				'is_new'     => false,
			),
			'fragment_cache'      => array(
				'title'      => esc_html__( 'Fragment Cache', 'w3-total-cache' ),
				'icon'       => 'dashicons-chart-pie',
				'text'       => esc_html__( 'Unlocking the fragment caching module delivers enhanced performance for plugins and themes that use the WordPress Transient API.', 'w3-total-cache' ),
				'button'     => '<button class="button" onclick="window.location=\'' .
					esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_general#fragmentcache' ) ) . '\'">' .
					__( 'Settings', 'w3-total-cache' ) . '</button>',
				'link'       => '<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/choosing-a-fragment-caching-method-for-w3-total-cache/?utm_source=w3tc&utm_medium=feature_showcase&utm_campaign=pro_fragment_cache' ) .
					'">' . __( 'More info', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a>',
				'is_premium' => true,
				'is_new'     => false,
			),
			'rest_api_cache'      => array(
				'title'      => esc_html__( 'Rest API Caching', 'w3-total-cache' ),
				'icon'       => 'dashicons-embed-generic',
				'text'       => esc_html__( 'Save server resources or add scale and performance by caching the WordPress Rest API with W3TC Pro.', 'w3-total-cache' ),
				'button'     => '<button class="button" onclick="window.location=\'' .
					esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_pgcache#rest' ) ) . '\'">' .
					__( 'Settings', 'w3-total-cache' ) . '</button>',
				'link'       => '<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/achieve-ultimate-wordpress-performance-with-w3-total-cache-pro/?utm_source=w3tc&utm_medium=feature_showcase&utm_campaign=pro_rest_api_caching' ) .
					'">' . __( 'More info', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a>',
				'is_premium' => true,
				'is_new'     => false,
			),
			'caching_stats'       => array(
				'title'      => esc_html__( 'Caching Statistics', 'w3-total-cache' ),
				'icon'       => 'dashicons-chart-line',
				'text'       => esc_html__( 'Analytics for your WordPress and Server cache that allow you to track the size, time and hit/miss ratio of each type of cache, giving you the information needed to gain maximum performance.', 'w3-total-cache' ),
				'button'     => '<button class="button" onclick="window.location=\'' .
					esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_stats' ) ) . '\'">' .
					__( 'Settings', 'w3-total-cache' ) . '</button>',
				'link'       => '<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/configuring-w3-total-cache-statistics-to-give-detailed-information-about-your-cache/?utm_source=w3tc&utm_medium=feature_showcase&utm_campaign=pro_stats' ) .
					'">' . __( 'More info', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a>',
				'is_premium' => true,
				'is_new'     => false,
			),
			'purge_logs'          => array(
				'title'      => esc_html__( 'Purge Logs', 'w3-total-cache' ),
				'icon'       => 'dashicons-search',
				'text'       => esc_html__( 'Purge Logs provide information on when your cache has been purged and what triggered it. If you are troubleshooting an issue with your cache being cleared, Purge Logs can tell you why.', 'w3-total-cache' ),
				'button'     => '<button class="button" onclick="window.location=\'' .
					esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_general#debug' ) ) . '\'">' .
					__( 'Settings', 'w3-total-cache' ) . '</button>',
				'link'       => '<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/purge-cache-log/?utm_source=w3tc&utm_medium=feature_showcase&utm_campaign=pro_purge_logs' ) .
					'">' . __( 'More info', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a>',
				'is_premium' => true,
				'is_new'     => false,
			),
			'page_cache'          => array(
				'title'      => esc_html__( 'Page Cache', 'w3-total-cache' ),
				'icon'       => 'dashicons-format-aside',
				'text'       => esc_html__( 'Page caching decreases the website response time, making pages load faster.', 'w3-total-cache' ),
				'button'     => '<button class="button" onclick="window.location=\'' .
					esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_general#page_cache' ) ) . '\'">' .
					__( 'Settings', 'w3-total-cache' ) . '</button>',
				'link'       => '<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/configuring-page-caching-in-w3-total-cache-for-shared-hosting/?utm_source=w3tc&utm_medium=feature_showcase&utm_campaign=page_cache' ) .
					'">' . __( 'More info', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a>',
				'is_premium' => false,
				'is_new'     => false,
			),
			'minify'              => array(
				'title'      => esc_html__( 'Minify', 'w3-total-cache' ),
				'icon'       => 'dashicons-media-text',
				'text'       => esc_html__( 'Reduce load time by decreasing the size and number of CSS and JS files.', 'w3-total-cache' ),
				'button'     => '<button class="button" onclick="window.location=\'' .
					esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_general#minify' ) ) . '\'">' .
					__( 'Settings', 'w3-total-cache' ) . '</button>',
				'link'       => '<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/choosing-a-minification-method-for-w3-total-cache/?utm_source=w3tc&utm_medium=feature_showcase&utm_campaign=minify' ) .
					'">' . __( 'More info', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a>',
				'is_premium' => false,
				'is_new'     => false,
			),
			'lazyload'            => array(
				'title'      => esc_html__( 'Lazy Load Images', 'w3-total-cache' ),
				'icon'       => 'dashicons-format-image',
				'text'       => esc_html__( 'Defer loading offscreen images, making pages load faster.', 'w3-total-cache' ),
				'button'     => '<button class="button" onclick="window.location=\'' .
					esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_general#userexperience' ) ) . '\'">' .
					__( 'Settings', 'w3-total-cache' ) . '</button>',
				'link'       => '<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/configuring-lazy-loading-for-your-wordpress-website-with-w3-total-cache/?utm_source=w3tc&utm_medium=feature_showcase&utm_campaign=lazyload' ) .
					'">' . __( 'More info', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a>',
				'is_premium' => false,
				'is_new'     => false,
			),
			'cdn'                 => array(
				'title'      => esc_html__( 'Content Delivery Network (CDN)', 'w3-total-cache' ),
				'icon'       => 'dashicons-format-gallery',
				'text'       => esc_html__( 'Host static files with a CDN to reduce page load time.', 'w3-total-cache' ),
				'button'     => '<button class="button" onclick="window.location=\'' .
					esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_general#cdn' ) ) . '\'">' .
					__( 'Settings', 'w3-total-cache' ) . '</button>',
				'link'       => '<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/configuring-w3-total-cache-with-stackpath-for-cdn-objects/?utm_source=w3tc&utm_medium=feature_showcase&utm_campaign=cdn' ) .
					'">' . __( 'More info', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a>',
				'is_premium' => false,
				'is_new'     => false,
			),
			'opcode_cache'        => array(
				'title'      => esc_html__( 'Opcode Cache', 'w3-total-cache' ),
				'icon'       => 'dashicons-performance',
				'text'       => esc_html__( 'Improves PHP performance by storing precompiled script bytecode in shared memory.', 'w3-total-cache' ),
				'button'     => '<button class="button" onclick="window.location=\'' .
					esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_general#system_opcache' ) ) . '\'">' .
					__( 'Settings', 'w3-total-cache' ) . '</button>',
				'link'       => '<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/choosing-an-opcode-caching-method-with-w3-total-cache/?utm_source=w3tc&utm_medium=feature_showcase&utm_campaign=opcode_cache' ) .
					'">' . __( 'More info', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a>',
				'is_premium' => false,
				'is_new'     => false,
			),
			'db_cache'            => array(
				'title'      => esc_html__( 'Database Cache', 'w3-total-cache' ),
				'icon'       => 'dashicons-database-view',
				'text'       => esc_html__( 'Persistently store data to reduce post, page and feed creation time.', 'w3-total-cache' ),
				'button'     => '<button class="button" onclick="window.location=\'' .
					esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_general#database_cache' ) ) . '\'">' .
					__( 'Settings', 'w3-total-cache' ) . '</button>',
				'link'       => '<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/choosing-a-database-caching-method-in-w3-total-cache/?utm_source=w3tc&utm_medium=feature_showcase&utm_campaign=database_cache' ) .
					'">' . __( 'More info', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a>',
				'is_premium' => false,
				'is_new'     => false,
			),
			'object_cache'        => array(
				'title'      => esc_html__( 'Object Cache', 'w3-total-cache' ),
				'icon'       => 'dashicons-archive',
				'text'       => esc_html__( 'Persistently store objects to reduce execution time for common operations.', 'w3-total-cache' ),
				'button'     => '<button class="button" onclick="window.location=\'' .
					esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_general#object_cache' ) ) . '\'">' .
					__( 'Settings', 'w3-total-cache' ) . '</button>',
				'link'       => '<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/configuring-object-caching-methods-in-w3-total-cache/?utm_source=w3tc&utm_medium=feature_showcase&utm_campaign=object_cache' ) .
					'">' . __( 'More info', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a>',
				'is_premium' => false,
				'is_new'     => false,
			),
			'browser_cache'       => array(
				'title'      => esc_html__( 'Browser Cache', 'w3-total-cache' ),
				'icon'       => 'dashicons-welcome-widgets-menus',
				'text'       => esc_html__( 'Reduce server load and decrease response time by using the cache available in site visitor\'s web browser.', 'w3-total-cache' ),
				'button'     => '<button class="button" onclick="window.location=\'' .
					esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_general#browser_cache' ) ) . '\'">' .
					__( 'Settings', 'w3-total-cache' ) . '</button>',
				'link'       => '<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/configuring-browser-caching-in-w3-total-cache/?utm_source=w3tc&utm_medium=feature_showcase&utm_campaign=browser_cache' ) .
					'">' . __( 'More info', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a>',
				'is_premium' => false,
				'is_new'     => false,
			),
			'extensions'          => array(
				'title'      => esc_html__( 'Extensions', 'w3-total-cache' ),
				'icon'       => 'dashicons-editor-kitchensink',
				'text'       => esc_html__( 'Additional features to extend the functionality of W3 Total Cache, such as Accelerated Mobile Pages (AMP) for Minify and support for New Relic.', 'w3-total-cache' ),
				'button'     => '<button class="button" onclick="window.location=\'' .
					esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_extensions' ) ) . '\'">' .
					__( 'Settings', 'w3-total-cache' ) . '</button>',
				'link'       => '<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/extension-framework/?utm_source=w3tc&utm_medium=feature_showcase&utm_campaign=extensions' ) .
					'">' . __( 'More info', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a>',
				'is_premium' => false,
				'is_new'     => false,
			),
			'cache_groups'        => array(
				'title'      => esc_html__( 'Cache Groups', 'w3-total-cache' ),
				'icon'       => 'dashicons-image-filter',
				'text'       => esc_html__( 'Manage cache groups for user agents, referrers, and cookies.', 'w3-total-cache' ),
				'button'     => '<button class="button" onclick="window.location=\'' .
					esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_cachegroups' ) ) . '\'">' .
					__( 'Settings', 'w3-total-cache' ) . '</button>',
				'link'       => '<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/cache-groups/?utm_source=w3tc&utm_medium=feature_showcase&utm_campaign=cache_groups' ) .
					'">' . __( 'More info', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a>',
				'is_premium' => false,
				'is_new'     => false,
			),
		);
	}
}
