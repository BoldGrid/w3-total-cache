<?php
namespace W3TC;

/**
 * W3 Total Cache Root_AdminMenu
 */

class Root_AdminMenu {

	/**
	 * Current page
	 *
	 * @var string
	 */
	protected $_page = 'w3tc_dashboard';

	private $_config;

	function __construct() {
		$this->_config = Dispatcher::config();
	}

	function generate_menu_array() {
		$pages = array(
			'w3tc_dashboard' => array(
				'page_title' => __( 'Dashboard', 'w3-total-cache' ),
				'menu_text' => __( 'Dashboard', 'w3-total-cache' ),
				'visible_always' => true,
				'order' => 100
			),
			'w3tc_general' => array(
				'page_title' => __( 'General Settings', 'w3-total-cache' ),
				'menu_text' => __( 'General Settings', 'w3-total-cache' ),
				'visible_always' => false,
				'order' => 200
			),
			'w3tc_pgcache' => array(
				'page_title' => __( 'Page Cache', 'w3-total-cache' ),
				'menu_text' => __( 'Page Cache', 'w3-total-cache' ),
				'visible_always' => false,
				'order' => 300
			),
			'w3tc_minify' => array(
				'page_title' => __( 'Minify', 'w3-total-cache' ),
				'menu_text' => __( 'Minify', 'w3-total-cache' ),
				'visible_always' => false,
				'order' => 400
			),
			'w3tc_dbcache' => array(
				'page_title' => __( 'Database Cache', 'w3-total-cache' ),
				'menu_text' => __( 'Database Cache', 'w3-total-cache' ),
				'visible_always' => false,
				'order' => 500
			),
			'w3tc_objectcache' => array(
				'page_title' => __( 'Object Cache', 'w3-total-cache' ),
				'menu_text' => __( 'Object Cache', 'w3-total-cache' ),
				'visible_always' => false,
				'order' => 600
			),
			'w3tc_browsercache' => array(
				'page_title' => __( 'Browser Cache', 'w3-total-cache' ),
				'menu_text' => __( 'Browser Cache', 'w3-total-cache' ),
				'visible_always' => false,
				'order' => 700
			),
			'w3tc_mobile' => array(
				'page_title' => __( 'User Agent Groups', 'w3-total-cache' ),
				'menu_text' => __( 'User Agent Groups', 'w3-total-cache' ),
				'visible_always' => false,
				'order' => 800
			),
			'w3tc_referrer' => array(
				'page_title' => __( 'Referrer Groups', 'w3-total-cache' ),
				'menu_text' => __( 'Referrer Groups', 'w3-total-cache' ),
				'visible_always' => false,
				'order' => 900
			),
			'w3tc_cdn' => array(
				'page_title' => __( 'Content Delivery Network', 'w3-total-cache' ),
				'menu_text' => __( '<acronym title="Content Delivery Network">CDN</acronym>', 'w3-total-cache' ),
				'visible_always' => false,
				'order' => 1000
			),
			'w3tc_faq' => array(
				'page_title' => __( 'FAQ', 'w3-total-cache' ),
				'menu_text' => __( 'FAQ', 'w3-total-cache' ),
				'visible_always' => true,
				'order' => 2000,
				'redirect_faq' => '*'
			),
			'w3tc_support' => array(
				'page_title' => __( 'Support', 'w3-total-cache' ),
				'menu_text' => __( '<span style="color: red;">Support</span>', 'w3-total-cache' ),
				'visible_always' => true,
				'order' => 2100
			),
			'w3tc_install' => array(
				'page_title' => __( 'Install', 'w3-total-cache' ),
				'menu_text' => __( 'Install', 'w3-total-cache' ),
				'visible_always' => false,
				'order' => 2200
			),
			'w3tc_about' => array(
				'page_title' => __( 'About', 'w3-total-cache' ),
				'menu_text' => __( 'About', 'w3-total-cache' ),
				'visible_always' => true,
				'order' => 2300
			)
		);
		$pages = apply_filters( 'w3tc_admin_menu', $pages, $this->_config );

		return $pages;
	}

	function generate( $base_capability ) {
		$pages = $this->generate_menu_array();

		uasort( $pages, function($a, $b) {
				return ($a['order'] - $b['order']);
			}
		);

		add_menu_page( __( 'Performance', 'w3-total-cache' ),
			__( 'Performance', 'w3-total-cache' ),
			apply_filters( 'w3tc_capability_menu_w3tc_dashboard',
				$base_capability ),
			'w3tc_dashboard', '', 'data:image/svg+xml;base64,' . base64_encode('<svg version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" preserveAspectRatio="xMidYMid meet" viewBox="0 0 16 16" width="16" height="16"><defs><path d="M9.57 6.44C9.97 6.65 10.44 6.65 10.84 6.45C11.53 6.11 12.92 5.42 13.6 5.09C13.89 4.95 13.89 4.55 13.61 4.4C12.3 3.71 9.05 2 7.74 1.31C7.29 1.07 6.75 1.05 6.27 1.24C5.51 1.55 4.03 2.15 3.29 2.45C2.96 2.58 2.94 3.04 3.25 3.2C4.64 3.92 8.15 5.71 9.57 6.44Z" id="a1ibI1mTkr"></path><path d="M8.2 14.33C7.88 14.51 7.51 14.19 7.64 13.85C8.15 12.51 9.44 9.06 9.99 7.61C10.1 7.32 10.31 7.08 10.59 6.93C11.35 6.55 13.07 5.66 13.8 5.29C14.01 5.18 14.24 5.38 14.16 5.6C13.73 6.91 12.63 10.23 12.2 11.54C12.07 11.94 11.8 12.27 11.43 12.48C10.6 12.96 8.96 13.89 8.2 14.33Z" id="a3oZBniNh8"></path><path d="M12.14 15.15C12.78 15.22 13.43 15.03 13.94 14.62C14.47 14.21 15.28 13.57 15.81 13.15C16.07 12.94 15.94 12.51 15.6 12.49C14.86 12.44 13.2 12.34 12.36 12.29C12.06 12.28 11.77 12.34 11.52 12.49C10.75 12.93 8.94 13.96 8.2 14.39C8.03 14.48 8.09 14.74 8.28 14.76C9.26 14.86 11.24 15.06 12.14 15.15Z" id="aeIjeZCLN"></path><path d="M3.13 3.44L9.42 6.66L9.57 6.75L9.68 6.88L9.76 7.03L9.8 7.19L9.8 7.36L9.76 7.54L7.41 13.77L7.32 13.92L7.2 14.04L7.05 14.12L6.88 14.16L6.71 14.14L6.54 14.07L0.82 10.71L0.57 10.53L0.38 10.29L0.25 10.02L0.18 9.73L0.18 9.43L0.25 9.13L2.22 3.8L2.31 3.64L2.44 3.51L2.6 3.42L2.77 3.38L2.95 3.38L3.13 3.44ZM2.93 9.13L3.84 8.08L3.84 9.68L4.24 9.86L5.41 8.66L6.56 9.26L5.97 9.61L6.09 9.79L6.16 9.95L6.19 10.07L6.18 10.17L6.13 10.23L6.02 10.28L5.92 10.26L5.8 10.18L5.66 10.04L5.48 9.86L5.32 9.85L5.19 9.85L5.07 9.85L4.96 9.85L4.88 9.86L5.28 10.4L5.65 10.78L6 11.02L6.32 11.1L6.63 11.02L6.94 10.77L7.06 10.49L7.04 10.22L6.93 9.99L6.79 9.86L6.87 9.78L7 9.69L7.15 9.57L7.35 9.42L7.57 9.26L5.35 7.93L4.4 8.9L4.31 7.4L3.91 7.19L3.08 8.16L3.08 6.64L2.46 6.32L2.46 8.83L2.93 9.13Z" id="b1H2p6I96S"></path></defs><g><g><g><use xlink:href="#a1ibI1mTkr" opacity="0.8" fill="black" fill-opacity="1"></use></g><g><use xlink:href="#a3oZBniNh8" opacity="0.7" fill="black" fill-opacity="1"></use></g><g><use xlink:href="#aeIjeZCLN" opacity="0.1" fill="black" fill-opacity="1"></use></g><g><use xlink:href="#b1H2p6I96S" opacity="1" fill="black" fill-opacity="1"></use></g></g></g></svg>') );

		$submenu_pages = array();
		$is_master = ( is_network_admin() || !Util_Environment::is_wpmu() );
		$remaining_visible = !$this->_config->get_boolean( 'common.force_master' );

		foreach ( $pages as $slug => $titles ) {
			if ( $is_master || $titles['visible_always'] || $remaining_visible ) {
				$hook = add_submenu_page( 'w3tc_dashboard',
					$titles['page_title'] . ' | W3 Total Cache',
					$titles['menu_text'],
					apply_filters( 'w3tc_capability_menu_' . $slug,
						$base_capability ),
					$slug,
					array( $this, 'options' )
				);
				$submenu_pages[] = $hook;

				if ( isset( $titles['redirect_faq'] ) ) {
					add_action( 'load-' . $hook, array( $this, 'redirect_faq' ) );
				}
			}
		}
		return $submenu_pages;
	}

	public function redirect_faq() {
		wp_redirect( W3TC_FAQ_URL );
		exit;
	}

	/**
	 * Options page
	 *
	 * @return void
	 */
	function options() {
		$this->_page = Util_Request::get_string( 'page' );
		if ( !Util_Admin::is_w3tc_admin_page() )
			$this->_page = 'w3tc_dashboard';

		/*
		 * Hidden pages
		 */
		if ( isset( $_REQUEST['w3tc_dbcluster_config'] ) ) {
			$options_dbcache = new DbCache_Page();
			$options_dbcache->dbcluster_config();
		}

		/**
		 * Show tab
		 */
		switch ( $this->_page ) {
		case 'w3tc_dashboard':
			$options_dashboard = new Generic_Page_Dashboard();
			$options_dashboard->options();
			break;

		case 'w3tc_general':
			$options_general = new Generic_Page_General();
			$options_general->options();
			break;

		case 'w3tc_pgcache':
			$options_pgcache = new PgCache_Page();
			$options_pgcache->options();
			break;

		case 'w3tc_minify':
			$options_minify = new Minify_Page();
			$options_minify->options();
			break;

		case 'w3tc_dbcache':
			$options_dbcache = new DbCache_Page();
			$options_dbcache->options();
			break;

		case 'w3tc_objectcache':
			$options_objectcache = new ObjectCache_Page();
			$options_objectcache->options();
			break;

		case 'w3tc_browsercache':
			$options_browsercache = new BrowserCache_Page();
			$options_browsercache->options();
			break;

		case 'w3tc_mobile':
			$options_mobile = new Mobile_Page_UserAgentGroups();
			$options_mobile->options();
			break;

		case 'w3tc_referrer':
			$options_referrer = new Mobile_Page_ReferrerGroups();
			$options_referrer->options();
			break;

		case 'w3tc_cdn':
			$options_cdn = new Cdn_Page();
			$options_cdn->options();
			break;

		case 'w3tc_support':
			$options_support = new Support_Page();
			$options_support->options();
			break;

		case 'w3tc_install':
			$options_install = new Generic_Page_Install();
			$options_install->options();
			break;

		case 'w3tc_about':
			$options_about = new Generic_Page_About();
			$options_about->options();
			break;
		default:
			// placeholder to make it the only way to show pages
			// with the time
			$view = new Base_Page_Settings();
			$view->options();

			do_action( "w3tc_settings_page-{$this->_page}" );

			$view->render_footer();

			break;
		}
	}
}
