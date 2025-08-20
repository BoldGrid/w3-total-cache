<?php
/**
 * File: Extension_AiCrawler_Plugin_Admin.php
 *
 * @package W3TC
 * @since   X.X.X
 */

namespace W3TC;

/**
 * Class: Extension_AiCrawler_Plugin_Admin
 *
 * @since X.X.X
 */
class Extension_AiCrawler_Plugin_Admin {
	/**
	 * Constructor.
	 *
	 * @since X.X.X
	 */
	public function __construct() {
		// Enqueue scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
	}

	/**
	 * Enqueue scripts for the settings page.
	 *
	 * Runs on the "admin_enqueue_scripts" action.
	 *
	 * @since X.X.X
	 */
	public function admin_enqueue_scripts() {
		$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$screen_id = ( $screen && isset( $screen->id ) ) ? $screen->id : '';

		// URLs & handles (register once; enqueue conditionally).
		$css_handle = 'w3tc-aicrawler-page';
		$js_handle  = 'w3tc-aicrawler-page';
		$css_url    = plugin_dir_url( __FILE__ ) . 'Extension_AiCrawler_Page_View.css';
		$js_url     = plugin_dir_url( __FILE__ ) . 'Extension_AiCrawler_Page.js';

		// Screens that should get the CSS (settings + Site Health variants).
		$css_screens = apply_filters(
			'w3tc_aicrawler_css_screens',
			array(
				'performance_page_w3tc_aicrawler',
				'site-health',
				'site-health-network',
				'tools_page_health-check',
			)
		);

		// Enqueue CSS where needed.
		if ( in_array( $screen_id, $css_screens, true ) ) {
			wp_register_style( $css_handle, $css_url, array(), W3TC_VERSION );
			wp_enqueue_style( $css_handle );
		}

		// Settings page gets JS + localization.
		if ( 'performance_page_w3tc_aicrawler' === $screen_id ) {
			wp_register_script( $js_handle, $js_url, array( 'jquery' ), W3TC_VERSION, true );

			wp_localize_script(
				$js_handle,
				'w3tcData',
				array(
					'nonces' => array(
						'testToken'     => wp_create_nonce( 'w3tc_aicrawler_test_token' ),
						'regenerateUrl' => wp_create_nonce( 'w3tc_aicrawler_regenerate_url' ),
						'regenerateAll' => wp_create_nonce( 'w3tc_aicrawler_regenerate_all' ),
					),
					'lang'   => array(
						'test'                => __( 'Test', 'w3-total-cache' ),
						'testing'             => __( 'Testing...', 'w3-total-cache' ),
						'tokenValid'          => __( 'Token is valid', 'w3-total-cache' ),
						'tokenInvalid'        => __( 'Token is invalid', 'w3-total-cache' ),
						'error'               => __( 'Error', 'w3-total-cache' ),
						'tokenError'          => __( 'An error occurred while testing the token', 'w3-total-cache' ),
						'noUrl'               => __( 'Please specify a URL to regenerate', 'w3-total-cache' ),
						'regenerating'        => __( 'Regenerating', 'w3-total-cache' ),
						'regeneratedUrl'      => __( 'URL regenerated successfully', 'w3-total-cache' ),
						'regenerateUrlFailed' => __( 'Failed to regenerate URL', 'w3-total-cache' ),
						'regenerateUrlError'  => __( 'An error occurred while regenerating the URL', 'w3-total-cache' ),
						'regenerate'          => __( 'Regenerate', 'w3-total-cache' ),
						'regeneratedAll'      => __( 'All URLs regenerated successfully', 'w3-total-cache' ),
						'regenerateAllFailed' => __( 'Failed to regenerate all URLs', 'w3-total-cache' ),
						'regenerateAllError'  => __( 'An error occurred while regenerating all URLs', 'w3-total-cache' ),
					),
				)
			);

			wp_enqueue_script( $js_handle );
		}
	}

	/**
	 * Adds AI Crawler to the extension list.
	 *
	 * @since  X.X.X
	 *
	 * @param array $extensions Extensions array.
	 *
	 * @return array
	 */
	public static function w3tc_extensions( $extensions ) {
		$extensions['aicrawler'] = array(
			'name'            => 'AI Crawler',
			'author'          => 'W3 Edge',
			'description'     => __( 'AI Crawler', 'w3-total-cache' ),
			'author_uri'      => 'https://www.boldgrid.com/',
			'extension_uri'   => 'https://www.boldgrid.com/w3-total-cache/',
			'extension_id'    => 'aicrawler',
			'settings_exists' => true,
			'version'         => '1.0',
			'enabled'         => true,
			'requirements'    => '',
			'path'            => 'w3-total-cache/Extension_AiCrawler_Plugin.php',
		);

		return $extensions;
	}

	/**
	 * Registers hooks for the admin environment.
	 *
	 * @since  X.X.X
	 *
	 * @return void
	 */
	public function run() {
		add_filter( 'w3tc_admin_menu', array( $this, 'w3tc_admin_menu' ) );
		add_filter( 'w3tc_extension_plugin_links_aicrawler', array( $this, 'w3tc_extension_plugin_links' ) );
		add_action( 'w3tc_settings_page-w3tc_aicrawler', array( $this, 'w3tc_extension_page' ) );
		add_action( 'wp_ajax_w3tc_aicrawler_regenerate_url', array( $this, 'wp_ajax_regenerate_aicrawler_url' ) );
		add_action( 'save_post', array( '\W3TC\Extension_AiCrawler_Markdown', 'generate_markdown_on_save' ), 10, 3 );

		// Site Health: STATUS card.
		add_filter(
			'site_status_tests',
			array(
				'\W3TC\Extension_AiCrawler_SiteHealth',
				'get_sitehealth_status',
			)
		);

		// Site Health: DEBUG INFO card.
		add_filter(
			'debug_information',
			array(
				'\W3TC\Extension_AiCrawler_SiteHealth',
				'get_sitehealth_debug_info',
			)
		);
	}

	/**
	 * Adds the AI Crawler settings page to the Performance menu.
	 *
	 * @since  X.X.X
	 *
	 * @param array $menu Existing menu entries.
	 *
	 * @todo Possibly add a capability check here to restrict access.
	 *       to administrators or specific user roles.
	 *
	 * @return array
	 */
	public function w3tc_admin_menu( $menu ) {
		$menu['w3tc_aicrawler'] = array(
			'page_title'     => __( 'AI Crawler', 'w3-total-cache' ),
			'menu_text'      => __( 'AI Crawler', 'w3-total-cache' ),
			'visible_always' => false,
			'order'          => 2000,
		);

		return $menu;
	}

	/**
	 * Displays the settings page.
	 *
	 * @since  X.X.X
	 *
	 * @return void
	 */
	public function w3tc_extension_page() {
		( new Extension_AiCrawler_Page() )->render_content();
	}

	/**
	 * Adds custom plugin links.
	 *
	 * @param array $links Existing array of plugin links.
	 *
	 * @return array Modified array of plugin links with New Relic settings link added.
	 */
	public function w3tc_extension_plugin_links( $links ) {
		$links   = array();
		$links[] = '<a class="edit" href="' . esc_attr( Util_Ui::admin_url( 'admin.php?page=w3tc_aicrawler' ) ) .
			'">' . __( 'Settings' ) . '</a>';

		return $links;
	}

	/**
	 * AJAX handler to queue a URL for markdown generation.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function wp_ajax_regenerate_aicrawler_url() {
		if ( ! check_ajax_referer( 'w3tc_aicrawler_regenerate_url', '_wpnonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'w3-total-cache' ) ) );
		}

		$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';

		if ( empty( $url ) ) {
			wp_send_json_error( array( 'message' => __( 'Please specify a URL to regenerate.', 'w3-total-cache' ) ) );
		}

		if ( Extension_AiCrawler_Util::is_url_excluded( $url ) ) {
			wp_send_json_error( array( 'message' => __( 'This URL is excluded from markdown generation.', 'w3-total-cache' ) ) );
		}

		$result = Extension_AiCrawler_Markdown::generate_markdown( $url );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'URL added to the markdown generation queue.', 'w3-total-cache' ) ) );
		}

		wp_send_json_error( array( 'message' => __( 'Failed to add URL to the markdown generation queue.', 'w3-total-cache' ) ) );
	}
}
