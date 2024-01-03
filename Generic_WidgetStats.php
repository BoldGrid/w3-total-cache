<?php
/**
 * File: Generic_WidgetStats.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Generic_WidgetStats
 */
class Generic_WidgetStats {

	/**
	 * Constructor
	 */
	public function __construct() {
	}

	/**
	 * Dashboard setup action
	 *
	 * @return void
	 */
	public static function admin_init_w3tc_dashboard() {
		$config = Dispatcher::config();

		if (
			! (	$config->get_boolean( 'stats.enabled' )
			|| ! Util_Environment::is_w3tc_pro( $config )	)
			|| (
				! $config->get_boolean( 'pgcache.enabled' )
				&& ! $config->getf_boolean( 'objectcache.enabled' )
				&& ! $config->get_boolean( 'dbcache.enabled' )
			)
		) {
			return;
		}

		$o = new Generic_WidgetStats();

		add_action( 'w3tc_widget_setup', array( $o, 'wp_dashboard_setup' ), 11000 );
		add_action( 'w3tc_network_dashboard_setup', array( $o, 'wp_dashboard_setup' ), 11000 );
		wp_enqueue_script( 'w3tc-dashboard', plugins_url( 'pub/js/google-charts.js', W3TC_FILE ), array(), W3TC_VERSION, true );
		wp_enqueue_script( 'w3tc-stats-widget', plugins_url( 'Generic_WidgetStats.js', W3TC_FILE ), array(), W3TC_VERSION, true );
	}

	/**
	 * W3TC dashboard Stats widgets.
	 */
	public function wp_dashboard_setup() {
		$config = Dispatcher::config();

		if ( $config->get_boolean( 'pgcache.enabled' ) ) {
			Util_Widget::add(
				'w3tc_page_cache',
				'<div class="w3tc-widget-w3tc-logo"></div><div class="w3tc-widget-text">' . __( 'Page Cache: Hits', 'w3-total-cache' ) . '</div>',
				array( $this, 'page_cache_widget_form' ),
				null,
				'stats'
			);
		}

		if ( $config->getf_boolean( 'objectcache.enabled' ) ) {
			Util_Widget::add(
				'w3tc_object_cache',
				'<div class="w3tc-widget-w3tc-logo"></div><div class="w3tc-widget-text">' . __( 'Object Cache: Hits', 'w3-total-cache' ) . '</div>',
				array( $this, 'object_cache_widget_form' ),
				null,
				'stats'
			);
		}

		if ( $config->get_boolean( 'dbcache.enabled' ) ) {
			Util_Widget::add(
				'w3tc_database_cache',
				'<div class="w3tc-widget-w3tc-logo"></div><div class="w3tc-widget-text">' . __( 'Database Cache: Hits', 'w3-total-cache' ) . '</div>',
				array( $this, 'database_cache_widget_form' ),
				null,
				'stats'
			);
		}
	}

	/**
	 * Web Requests widget content.
	 */
	public function page_cache_widget_form() {
		?>
		<div id="page_cache_chart"></div>
		<?php
	}

	/**
	 * Object Cache widget content.
	 */
	public function object_cache_widget_form() {
		?>
		<div id="object_cache_chart"></div>
		<?php
	}

	/**
	 * Database widget content.
	 */
	public function database_cache_widget_form() {
		?>
		<div id="database_cache_chart"></div>
		<?php
	}
}
