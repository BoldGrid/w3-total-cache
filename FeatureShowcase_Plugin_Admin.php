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
		$cards  = $this->get_cards();

		require W3TC_DIR . '/FeatureShowcase_Plugin_Admin_View.php';
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
	 * Get the feature cards.
	 *
	 * @since X.X.X
	 *
	 * @access private
	 *
	 * @return array
	 */
	private function get_cards() {
		return array(
			'page_cache'          => array(
				'title'      => esc_html__( 'Page Cache', 'w3-total-cache' ),
				'icon'       => 'dashicons-format-aside',
				'text'       => esc_html__( 'Page caching decreases the website response time, making pages load faster.', 'w3-total-cache' ),
				'button'     => '',
				'link'       => '<a href="' . esc_url( admin_url( 'admin.php?page=w3tc_general#page_cache' ) ) .
					'">' . __( 'Settings', 'w3-total-cache' ) . '</a>',
				'is_premium' => false,
			),
			'setup_guide'         => array(
				'title'      => esc_html__( 'Setup Guide Wizard', 'w3-total-cache' ),
				'icon'       => 'dashicons-superhero',
				'text'       => esc_html__( 'The Setup Guide wizard quickly walks you through configuring W3 Total Cache.', 'w3-total-cache' ),
				'button'     => '',
				'link'       => '<a href="' . esc_url( admin_url( 'admin.php?page=w3tc_setup_guide' ) ) .
					'">' . __( 'Launch Wizard', 'w3-total-cache' ) . '</a>',
				'is_premium' => false,
			),
			'cdn_fsd'             => array(
				'title'      => esc_html__( 'Full Site Delivery via CDN', 'w3-total-cache' ),
				'icon'       => 'dashicons-networking',
				'text'       => esc_html__( 'Provide the best user experience possible by enhancing by hosting HTML pages and RSS feeds with (supported) CDN\'s high speed global networks.', 'w3-total-cache' ),
				'button'     => '',
				'link'       => '<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/w3-total-cache/' ) .
					'">' . __( 'More Information', 'w3-total-cache' ) . '</a>',
				'is_premium' => true,
			),
			'fragment_cache'      => array(
				'title'      => esc_html__( 'Fragment Cache', 'w3-total-cache' ),
				'icon'       => 'dashicons-chart-pie',
				'text'       => esc_html__( 'Unlocking the fragment caching module delivers enhanced performance for plugins and themes that use the WordPress Transient API.', 'w3-total-cache' ),
				'button'     => '',
				'link'       => '<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/w3-total-cache/' ) .
					'">' . __( 'More Information', 'w3-total-cache' ) . '</a>',
				'is_premium' => true,
			),
			'rest_api_cache'      => array(
				'title'      => esc_html__( 'Rest API Caching', 'w3-total-cache' ),
				'icon'       => 'dashicons-embed-generic',
				'text'       => esc_html__( 'Save server resources or add scale and performance by caching the WordPress Rest API with W3TC Pro.', 'w3-total-cache' ),
				'button'     => '',
				'link'       => '<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/w3-total-cache/' ) .
					'">' . __( 'More Information', 'w3-total-cache' ) . '</a>',
				'is_premium' => true,
			),
			'render_blocking_css' => array(
				'title'      => esc_html__( 'Eliminate Render Blocking CSS', 'w3-total-cache' ),
				'icon'       => 'dashicons-table-row-delete',
				'text'       => esc_html__( 'Render blocking CSS delays a webpage from being visible in a timely manner. Eliminate this easily with the click of a button in W3 Total Cache Pro.', 'w3-total-cache' ),
				'button'     => '',
				'link'       => '<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/w3-total-cache/' ) .
					'">' . __( 'More Information', 'w3-total-cache' ) . '</a>',
				'is_premium' => true,
			),
			'extension_framework' => array(
				'title'      => esc_html__( 'Extension Framework', 'w3-total-cache' ),
				'icon'       => 'dashicons-insert',
				'text'       => esc_html__( 'Improve the performance of your Genesis, WPML powered site, and much more. StudioPress\' Genesis Framework is up to 60% faster with W3TC Pro.', 'w3-total-cache' ),
				'button'     => '',
				'link'       => '<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/w3-total-cache/' ) .
					'">' . __( 'More Information', 'w3-total-cache' ) . '</a>',
				'is_premium' => true,
			),
			'caching_stats'       => array(
				'title'      => esc_html__( 'Caching Statistics', 'w3-total-cache' ),
				'icon'       => 'dashicons-chart-line',
				'text'       => esc_html__( 'Analytics for your WordPress and Server cache that allow you to track the size, time and hit/miss ratio of each type of cache, giving you the information needed to gain maximum performance.', 'w3-total-cache' ),
				'button'     => '',
				'link'       => '<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/w3-total-cache/' ) .
					'">' . __( 'More Information', 'w3-total-cache' ) . '</a>',
				'is_premium' => true,
			),
			'purge_logs'          => array(
				'title'      => esc_html__( 'Purge Logs', 'w3-total-cache' ),
				'icon'       => 'dashicons-search',
				'text'       => esc_html__( 'Purge Logs provide information on when your cache has been purged and what triggered it. If you are troubleshooting an issue with your cache being cleared, Purge Logs can tell you why.', 'w3-total-cache' ),
				'button'     => '',
				'link'       => '<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/w3-total-cache/' ) .
					'">' . __( 'More Information', 'w3-total-cache' ) . '</a>',
				'is_premium' => true,
			),
			'ticket_support'      => array(
				'title'      => esc_html__( 'Ticket Support', 'w3-total-cache' ),
				'icon'       => 'dashicons-sos',
				'text'       => esc_html__( 'Do not want to post your issue on a public forum? Pro users can submit a ticket to have questions answered by our performance experts, right in your WordPress Dashboard.', 'w3-total-cache' ),
				'button'     => '',
				'link'       => '<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/w3-total-cache/' ) .
					'">' . __( 'More Information', 'w3-total-cache' ) . '</a>',
				'is_premium' => true,
			),
			'premium_support'     => array(
				'title'      => esc_html__( 'Premium Support', 'w3-total-cache' ),
				'icon'       => 'dashicons-admin-users',
				'text'       => esc_html__( 'Submit a ticket to have your W3 Total Cache configuration and consultation to improve your WordPress Performance, right in your WordPress Dashboard. ', 'w3-total-cache' ),
				'button'     => '',
				'link'       => '<a target="_blank" href="' . esc_url( 'https://www.boldgrid.com/w3-total-cache/' ) .
					'">' . __( 'More Information', 'w3-total-cache' ) . '</a>',
				'is_premium' => true,
			),
		);
	}
}
