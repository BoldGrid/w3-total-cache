<?php
/**
 * File: Generic_WidgetSpreadTheWord_Plugin.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Generic_WidgetSpreadTheWord_Plugin
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 */
class Generic_WidgetSpreadTheWord_Plugin {
	/**
	 * Config
	 *
	 * @var Config
	 */
	private $_config = null;

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
		if ( 'w3tc_dashboard' === Util_Admin::get_current_wp_page() ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		}

		add_action( 'w3tc_widget_setup', array( $this, 'wp_dashboard_setup' ), 900 );
		add_action( 'w3tc_network_dashboard_setup', array( $this, 'wp_dashboard_setup' ), 900 );
	}

	/**
	 * Dashboard setup action
	 *
	 * @return void
	 */
	public function wp_dashboard_setup() {
		Util_Widget::add(
			'w3tc_spreadtheword',
			'<div class="w3tc-widget-w3tc-logo"></div>' .
			'<div class="w3tc-widget-text">' .
			__( 'Spread the Word', 'w3-total-cache' ) .
			'</div>',
			array( $this, 'widget_form' ),
			null,
			'bottom'
		);
	}

	/**
	 * Widget from
	 *
	 * @return void
	 */
	public function widget_form() {
		include W3TC_DIR . '/Generic_WidgetSpreadTheWord_View.php';
	}

	/**
	 * Enqueue
	 *
	 * @return void
	 */
	public function enqueue() {
		wp_enqueue_style( 'w3tc-widget' );
		wp_enqueue_script( 'w3tc-metadata' );
		wp_enqueue_script( 'w3tc-widget' );

		wp_enqueue_script( 'w3tc_spread_the_word', plugins_url( 'Generic_WidgetSpreadTheWord.js', W3TC_FILE ), array( 'jquery' ), '1.0', false );

		wp_localize_script( 'w3tc_spread_the_word', 'w3tc_spread_the_word_product_url', array( W3TC_SUPPORT_US_PRODUCT_URL ) );
		wp_localize_script( 'w3tc_spread_the_word', 'w3tc_spread_the_word_tweet', array( W3TC_SUPPORT_US_TWEET ) );
		wp_localize_script( 'w3tc_spread_the_word', 'w3tc_spread_the_word_rate_url', array( W3TC_SUPPORT_US_RATE_URL ) );
	}
}
