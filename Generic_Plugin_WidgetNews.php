<?php
/**
 * File: Generic_Plugin_WidgetNews.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Generic_Plugin_WidgetNews
 */
class Generic_Plugin_WidgetNews {
	/**
	 * Config.
	 *
	 * @var Config
	 */
	private $_config = null; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();
	}

	/**
	 * Runs plugin.
	 */
	public function run() {
		if ( Util_Admin::get_current_wp_page() === 'w3tc_dashboard' ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		}

		add_action( 'w3tc_widget_setup', array( $this, 'wp_dashboard_setup' ) );
		add_action( 'w3tc_network_dashboard_setup', array( $this, 'wp_dashboard_setup' ) );

		if ( is_admin() ) {
			add_action( 'wp_ajax_w3tc_widget_latest_news_ajax', array( $this, 'action_widget_latest_news_ajax' ) );
		}
	}

	/**
	 * Dashboard setup action.
	 */
	public function wp_dashboard_setup() {
		Util_Widget::add(
			'w3tc_latest_news',
			__( 'News', 'w3-total-cache' ),
			array(
				$this,
				'widget_latest',
			),
			array(
				$this,
				'widget_latest_control',
			),
			'side'
		);
	}

	/**
	 * Returns key for transient cache of "widget latest"
	 *
	 * @return string
	 */
	public function _widget_latest_cache_key() { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
		return 'dash_' . md5( 'w3tc_latest_news' );
	}

	/**
	 * Prints latest widget contents
	 *
	 * @return void
	 */
	public function widget_latest() {
		$output = get_transient( $this->_widget_latest_cache_key() );

		if ( false !== $output ) {
			echo wp_kses(
				$output,
				array(
					'a'  => array(
						'href'   => array(),
						'target' => array(),
					),
					'h4' => array(),
					'p'  => array(
						'style' => array(),
					),
				)
			);
		} else {
			include W3TC_INC_DIR . '/widget/latest_news.php';
		}
	}

	/**
	 * Prints latest widget contents.
	 */
	public function action_widget_latest_news_ajax() {
		// load content of feed.
		global $wp_version;

		$items       = array();
		$items_count = $this->_config->get_integer( 'widget.latest_news.items' );

		include_once ABSPATH . WPINC . '/feed.php';

		$feed = fetch_feed( W3TC_NEWS_FEED_URL );

		if ( ! is_wp_error( $feed ) ) {
			$feed_items = $feed->get_items( 0, $items_count );

			foreach ( $feed_items as $feed_item ) {
				$items[] = array(
					'link'  => $feed_item->get_link(),
					'title' => htmlspecialchars_decode( $feed_item->get_title() ),
				);
			}
		}

		ob_start();
		include W3TC_INC_DIR . '/widget/latest_news_ajax.php';

		// Default lifetime in cache of 12 hours (same as the feeds).
		set_transient( $this->_widget_latest_cache_key(), ob_get_flush(), 43200 );
		die();
	}

	/**
	 * Latest widget control.
	 *
	 * @param integer $widget_id   Widget id.
	 * @param array   $form_inputs Form inputs.
	 */
	public function widget_latest_control( $widget_id, $form_inputs = array() ) {
		if ( 'POST' === ( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) ) {
			$this->_config->set( 'widget.latest_news.items', Util_Request::get_integer( 'w3tc_widget_latest_news_items', 3 ) );
			$this->_config->save();
			delete_transient( $this->_widget_latest_cache_key() );
		}
		include W3TC_INC_DIR . '/widget/latest_news_control.php';
	}

	/**
	 * Enqueue scripts and styles.
	 */
	public function enqueue() {
		wp_enqueue_style( 'w3tc-widget' );
		wp_enqueue_script( 'w3tc-metadata' );
		wp_enqueue_script( 'w3tc-widget' );
	}
}
