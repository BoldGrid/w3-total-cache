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
	 * Location of any wp_redirect.
	 *
	 * @since 2.2.0
	 * @access private
	 * @static
	 *
	 * @var string
	 */
	private static $wp_redirect_location;

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
		add_action(
			'admin_enqueue_scripts',
			array(
				$this,
				'enqueue_styles',
			)
		);

		// Check if being redirected.
		add_filter(
			'wp_redirect',
			function ( $location ) {
				FeatureShowcase_Plugin_Admin::$wp_redirect_location = $location;
				return $location;
			}
		);
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
		$w3tc_config = Dispatcher::config();
		$cards_data  = self::get_cards();

		require W3TC_DIR . '/FeatureShowcase_Plugin_Admin_View.php';

		// Mark unseen new features as seen, if not redirecting to the Setup Guide wizard.
		if ( ! self::$wp_redirect_location ) {
			$this->mark_seen();
		}
	}

	/**
	 * Enqueue styles.
	 *
	 * @since 2.1.0
	 */
	public function enqueue_styles() {
		$page = Util_Request::get_string( 'page' );

		wp_enqueue_style(
			'w3tc_feature_counter',
			esc_url( plugin_dir_url( __FILE__ ) . 'pub/css/feature-counter.css' ),
			array(),
			W3TC_VERSION
		);

		if ( 'w3tc_feature_showcase' === $page ) {
			wp_enqueue_style(
				'w3tc_feature_showcase',
				esc_url( plugin_dir_url( __FILE__ ) . 'pub/css/feature-showcase.css' ),
				array(),
				W3TC_VERSION
			);
		}
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
		$cards_data    = self::get_cards();
		$updated       = false;

		foreach ( $cards_data as $type => $w3tc_cards ) {
			foreach ( $w3tc_cards as $id => $w3tc_card ) {
				if ( ! empty( $w3tc_card['is_new'] ) && ! in_array( $id, $features_seen, true ) ) {
					$features_seen[] = $id;
					$updated         = true;
				}
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
		$w3tc_config         = Dispatcher::config();
		$force_master_config = $w3tc_config->get_boolean( 'common.force_master' );

		if ( is_multisite() && $force_master_config && ! is_super_admin() ) {
			return 0;
		}

		global $current_user;

		$unseen_count  = 0;
		$features_seen = (array) get_user_meta( $current_user->ID, 'w3tc_features_seen', true );
		$cards_data    = self::get_cards();

		// Iterate through the new features and check if already seen.
		foreach ( $cards_data as $type => $w3tc_cards ) {
			foreach ( $w3tc_cards as $id => $w3tc_card ) {
				if ( ! empty( $w3tc_card['is_new'] ) && ! in_array( $id, $features_seen, true ) ) {
					++$unseen_count;
				}
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
		$w3tc_c                   = Dispatcher::config();
		$extensions               = $w3tc_c->get_array( 'extensions.active' );
		$is_imageservice_active   = isset( $extensions['imageservice'] );
		$imageservice_button_text = $is_imageservice_active ?
			( is_network_admin() ? __( 'Available in sites', 'w3-total-cache' ) : __( 'Settings', 'w3-total-cache' ) ) :
			( is_network_admin() || ! is_multisite() ? __( 'Activate', 'w3-total-cache' ) : '' );
		$imageservice_button_link = $is_imageservice_active ?
			( is_network_admin() ? 'network/sites.php' : 'upload.php?page=w3tc_extension_page_imageservice' ) :
			( is_network_admin() || ! is_multisite() ? 'admin.php?page=w3tc_extensions&action=activate&extension=imageservice' : '' );

		$imageservice_description = __(
			'Adds the ability to convert images into the modern formats (like WebP or AVIF) for better performance using our remote API service.',
			'w3-total-cache'
		);

		$cards = array(
			'old' => array(
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
					'is_new'     => false,
				),
				'imageservice'        => array(
					'title'      => esc_html__( 'Image Converter', 'w3-total-cache' ),
					'icon'       => 'dashicons-embed-photo',
					'text'       => esc_html( $imageservice_description ),
					'button'     => empty( $imageservice_button_text ) ? '' :
						( '<button class="button" onclick="window.location=\'' .
						esc_url( Util_Ui::admin_url( $imageservice_button_link ) ) . '\'">' .
						esc_html( $imageservice_button_text ) . '</button>' ),
					'link'       => '<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/image-service/?utm_source=w3tc&utm_medium=feature_showcase&utm_campaign=imageservice' ) .
						'">' . __( 'More info', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a>',
					'is_premium' => false,
					'is_new'     => false,
				),
				'pagespeed'           => array(
					'title'      => esc_html__( 'Google Page Speed', 'w3-total-cache' ),
					'icon'       => 'dashicons-analytics',
					'text'       => esc_html__( "Adds the ability to analyze the website's homepage and provide a detailed breakdown of performance metrics including potential issues and proposed solutions.", 'w3-total-cache' ),
					'button'     => '<button class="button" onclick="window.location=\'' .
						esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_pagespeed' ) ) . '\'">' .
						__( 'Launch', 'w3-total-cache' ) . '</button>',
					'link'       => '<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/google-pagespeed-tool/?utm_source=w3tc&utm_medium=feature_showcase&utm_campaign=pagespeed-tool' ) .
						'">' . __( 'More info', 'w3-total-cache' ) . '<span class="dashicons dashicons-external"></span></a>',
					'is_premium' => false,
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
					'link'       => '<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/support/w3-total-cache/bunny-cdn-setup/?utm_source=w3tc&utm_medium=feature_showcase&utm_campaign=cdn' ) .
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
			),
		);

		foreach ( $cards as $type => $group ) {
			$cards[ $type ] = array_filter(
				$group,
				static function ( $card ) {
					return empty( $card['is_premium'] );
				}
			);
			if ( empty( $cards[ $type ] ) ) {
				unset( $cards[ $type ] );
			}
		}

		return apply_filters( 'w3tc_feature_showcase_cards', $cards );
	}
}
