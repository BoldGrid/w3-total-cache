<?php
/**
 * File: FeatureShowcase_Plugin_Admin.php
 *
 * @since X.X.X
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: FeatureShowcase_Plugin_Admin
 *
 * @since X.X.X
 */
class FeatureShowcase_Plugin_Admin {
	/**
	 * Current page.
	 *
	 * @since  X.X.X
	 * @access private
	 *
	 * @var string
	 */
	private $_page = 'w3tc_feature_showcase'; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Constructor.
	 *
	 * @since X.X.X
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
	 * @since X.X.X
	 */
	public function run() {
	}

	/**
	 * Render the page.
	 *
	 * @since X.X.X
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
	 * @since X.X.X
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
	 * @since X.X.X
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
	 * @since X.X.X
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
	 * @since X.X.X
	 *
	 * @access private
	 * @static
	 *
	 * @return array
	 */
	private static function get_cards() {
		return array(
			'page_cache'          => array(
				'title'      => esc_html__( 'Page Cache', 'w3-total-cache' ),
				'icon'       => 'dashicons-format-aside',
				'text'       => esc_html__( 'Page caching decreases the website response time, making pages load faster.', 'w3-total-cache' ),
				'button'     => '',
				'link'       => '<a href="' . esc_url( admin_url( 'admin.php?page=w3tc_general#page_cache' ) ) .
					'">' . __( 'Settings', 'w3-total-cache' ) . '</a>',
				'is_premium' => false,
				'is_new'     => false,
			),
			'setup_guide'         => array(
				'title'      => esc_html__( 'Setup Guide Wizard', 'w3-total-cache' ),
				'icon'       => 'dashicons-superhero',
				'text'       => esc_html__( 'The Setup Guide wizard quickly walks you through configuring W3 Total Cache.', 'w3-total-cache' ),
				'button'     => '',
				'link'       => '<a href="' . esc_url( admin_url( 'admin.php?page=w3tc_setup_guide' ) ) .
					'">' . __( 'Launch Wizard', 'w3-total-cache' ) . '</a>',
				'is_premium' => false,
				'is_new'     => true,
			),
			'cdn_fsd'             => array(
				'title'      => esc_html__( 'Full Site Delivery via CDN', 'w3-total-cache' ),
				'icon'       => 'dashicons-networking',
				'text'       => esc_html__( 'Provide the best user experience possible by enhancing by hosting HTML pages and RSS feeds with (supported) CDN\'s high speed global networks.', 'w3-total-cache' ),
				'button'     => '',
				'link'       => '<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/w3-total-cache/' ) .
					'">' . __( 'More Information', 'w3-total-cache' ) . '</a>',
				'is_premium' => true,
				'is_new'     => false,
			),
			'fragment_cache'      => array(
				'title'      => esc_html__( 'Fragment Cache', 'w3-total-cache' ),
				'icon'       => 'dashicons-chart-pie',
				'text'       => esc_html__( 'Unlocking the fragment caching module delivers enhanced performance for plugins and themes that use the WordPress Transient API.', 'w3-total-cache' ),
				'button'     => '',
				'link'       => '<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/w3-total-cache/' ) .
					'">' . __( 'More Information', 'w3-total-cache' ) . '</a>',
				'is_premium' => true,
				'is_new'     => false,
			),
			'rest_api_cache'      => array(
				'title'      => esc_html__( 'Rest API Caching', 'w3-total-cache' ),
				'icon'       => 'dashicons-embed-generic',
				'text'       => esc_html__( 'Save server resources or add scale and performance by caching the WordPress Rest API with W3TC Pro.', 'w3-total-cache' ),
				'button'     => '',
				'link'       => '<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/w3-total-cache/' ) .
					'">' . __( 'More Information', 'w3-total-cache' ) . '</a>',
				'is_premium' => true,
				'is_new'     => false,
			),
			'render_blocking_css' => array(
				'title'      => esc_html__( 'Eliminate Render Blocking CSS', 'w3-total-cache' ),
				'icon'       => 'dashicons-table-row-delete',
				'text'       => esc_html__( 'Render blocking CSS delays a webpage from being visible in a timely manner. Eliminate this easily with the click of a button in W3 Total Cache Pro.', 'w3-total-cache' ),
				'button'     => '',
				'link'       => '<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/w3-total-cache/' ) .
					'">' . __( 'More Information', 'w3-total-cache' ) . '</a>',
				'is_premium' => true,
				'is_new'     => false,
			),
			'extension_framework' => array(
				'title'      => esc_html__( 'Extension Framework', 'w3-total-cache' ),
				'icon'       => 'dashicons-insert',
				'text'       => esc_html__( 'Improve the performance of your Genesis, WPML powered site, and much more. StudioPress\' Genesis Framework is up to 60% faster with W3TC Pro.', 'w3-total-cache' ),
				'button'     => '',
				'link'       => '<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/w3-total-cache/' ) .
					'">' . __( 'More Information', 'w3-total-cache' ) . '</a>',
				'is_premium' => true,
				'is_new'     => false,
			),
			'caching_stats'       => array(
				'title'      => esc_html__( 'Caching Statistics', 'w3-total-cache' ),
				'icon'       => 'dashicons-chart-line',
				'text'       => esc_html__( 'Analytics for your WordPress and Server cache that allow you to track the size, time and hit/miss ratio of each type of cache, giving you the information needed to gain maximum performance.', 'w3-total-cache' ),
				'button'     => '',
				'link'       => '<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/w3-total-cache/' ) .
					'">' . __( 'More Information', 'w3-total-cache' ) . '</a>',
				'is_premium' => true,
				'is_new'     => false,
			),
			'purge_logs'          => array(
				'title'      => esc_html__( 'Purge Logs', 'w3-total-cache' ),
				'icon'       => 'dashicons-search',
				'text'       => esc_html__( 'Purge Logs provide information on when your cache has been purged and what triggered it. If you are troubleshooting an issue with your cache being cleared, Purge Logs can tell you why.', 'w3-total-cache' ),
				'button'     => '',
				'link'       => '<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/w3-total-cache/' ) .
					'">' . __( 'More Information', 'w3-total-cache' ) . '</a>',
				'is_premium' => true,
				'is_new'     => false,
			),
			'ticket_support'      => array(
				'title'      => esc_html__( 'Ticket Support', 'w3-total-cache' ),
				'icon'       => 'dashicons-sos',
				'text'       => esc_html__( 'Do not want to post your issue on a public forum? Pro users can submit a ticket to have questions answered by our performance experts, right in your WordPress Dashboard.', 'w3-total-cache' ),
				'button'     => '',
				'link'       => '<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/w3-total-cache/' ) .
					'">' . __( 'More Information', 'w3-total-cache' ) . '</a>',
				'is_premium' => true,
				'is_new'     => false,
			),
			'premium_support'     => array(
				'title'      => esc_html__( 'Premium Support', 'w3-total-cache' ),
				'icon'       => 'dashicons-admin-users',
				'text'       => esc_html__( 'Submit a ticket to have your W3 Total Cache configuration and consultation to improve your WordPress Performance, right in your WordPress Dashboard. ', 'w3-total-cache' ),
				'button'     => '',
				'link'       => '<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/w3-total-cache/' ) .
					'">' . __( 'More Information', 'w3-total-cache' ) . '</a>',
				'is_premium' => true,
				'is_new'     => false,
			),
		);
	}
}
