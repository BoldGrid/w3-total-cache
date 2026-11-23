<?php
/**
 * File: Generic_WidgetStats.php
 *
 * @since   2.7.0
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Generic_WidgetStats
 */
class Generic_WidgetStats {
	/**
	 * Dashboard setup action (administrators only).
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public static function admin_init_w3tc_dashboard() {
		if ( \user_can( \get_current_user_id(), 'manage_options' ) ) {
			$w3tc_o = new Generic_WidgetStats();

			add_action( 'w3tc_widget_setup', array( $w3tc_o, 'wp_dashboard_setup' ), 700 );
			add_action( 'w3tc_network_dashboard_setup', array( $w3tc_o, 'wp_dashboard_setup' ), 700 );

			// Without stats collection there is no chart data; skip the polling script.
			$w3tc_config = Dispatcher::config();
			if ( $w3tc_config->get_boolean( 'stats.enabled' ) ) {
				wp_enqueue_script( 'w3tc-dashboard', plugins_url( 'pub/js/google-charts.js', W3TC_FILE ), array(), W3TC_VERSION, true );
				// wp-admin/index.php is outside W3TC options pages; localize ustats_get for the widget script.
				Util_Nonce::enqueue_ajax_nonces( array( 'ustats_get' ) );
				wp_register_script( 'w3tc-stats-widget', plugins_url( 'Generic_WidgetStats.js', W3TC_FILE ), array( 'w3tc-nonce' ), W3TC_VERSION, true );
				wp_localize_script(
					'w3tc-stats-widget',
					'w3tcWidgetStatsData',
					array(
						'pgcacheFileGeneric' => 'file_generic' === $w3tc_config->get_string( 'pgcache.engine' ),
					)
				);
				wp_enqueue_script( 'w3tc-stats-widget' );
			}
		}
	}

	/**
	 * W3TC dashboard Stats widgets.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function wp_dashboard_setup() {
		Util_Widget::add(
			'w3tc_page_cache',
			'<div class="w3tc-widget-w3tc-logo"></div><div class="w3tc-widget-text">' . __( 'Page Cache: Hits', 'w3-total-cache' ) . '</div>',
			array( $this, 'page_cache_widget_form' ),
			null,
			'normal'
		);

		Util_Widget::add(
			'w3tc_object_cache',
			'<div class="w3tc-widget-w3tc-logo"></div><div class="w3tc-widget-text">' . __( 'Object Cache: Hits', 'w3-total-cache' ) . '</div>',
			array( $this, 'object_cache_widget_form' ),
			null,
			'normal'
		);

		Util_Widget::add(
			'w3tc_database_cache',
			'<div class="w3tc-widget-w3tc-logo"></div><div class="w3tc-widget-text">' . __( 'Database Cache: Hits', 'w3-total-cache' ) . '</div>',
			array( $this, 'database_cache_widget_form' ),
			null,
			'normal'
		);
	}

	/**
	 * Web Requests widget content.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function page_cache_widget_form() {
		$w3tc_config = Dispatcher::config();

		// Disk: Enhanced hits are served by the web server and are only countable via the access log source.
		$unavailable_message = '';
		if ( 'file_generic' === $w3tc_config->get_string( 'pgcache.engine' ) && ! $w3tc_config->get_boolean( 'stats.access_log.enabled' ) ) {
			$unavailable_message = __( 'Cache hits are served directly by the web server when using Disk: Enhanced, so they are not counted here. Enable the Access Log data source in the Statistics settings to collect them.', 'w3-total-cache' );
		}

		$chart = self::get_chart_content(
			array(
				'chart_id'            => 'page_cache_chart',
				'setting_key'         => 'pgcache.enabled',
				'cache_enable_label'  => __( 'Enable PageCache', 'w3-total-cache' ),
				'cache_enable_url'    => Util_Ui::admin_url( 'admin.php?page=w3tc_general#page_cache' ),
				'unavailable_message' => $unavailable_message,
			)
		);
		echo wp_kses( $chart, self::get_allowed_tags() );
	}

	/**
	 * Object Cache widget content.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function object_cache_widget_form() {
		$chart = self::get_chart_content(
			array(
				'chart_id'           => 'object_cache_chart',
				'setting_key'        => 'objectcache.enabled',
				'cache_enable_label' => __( 'Enable ObjectCache', 'w3-total-cache' ),
				'cache_enable_url'   => Util_Ui::admin_url( 'admin.php?page=w3tc_general#object_cache' ),
			)
		);
		echo wp_kses( $chart, self::get_allowed_tags() );
	}

	/**
	 * Database widget content.
	 *
	 * @since 2.7.0
	 *
	 * @return void
	 */
	public function database_cache_widget_form() {
		$chart = self::get_chart_content(
			array(
				'chart_id'           => 'database_cache_chart',
				'setting_key'        => 'dbcache.enabled',
				'cache_enable_label' => __( 'Enable DBCache', 'w3-total-cache' ),
				'cache_enable_url'   => Util_Ui::admin_url( 'admin.php?page=w3tc_general#database_cache' ),
			)
		);
		echo wp_kses( $chart, self::get_allowed_tags() );
	}

	/**
	 * Get button link allowed tags.
	 *
	 * @since 2.7.0
	 *
	 * @param array $chart_config Chart configuration array.
	 *
	 * @return string
	 */
	public static function get_chart_content( $chart_config ) {
		$w3tc_config   = Dispatcher::config();
		$chart_id      = $chart_config['chart_id'];
		$chart_content = '';
		if ( \user_can( \get_current_user_id(), 'manage_options' ) && ! Util_Environment::is_w3tc_pro( $w3tc_config ) ) {
			$chart_id     .= '_ad';
			$chart_content = '<input type="button" class="button-primary button-buy-plugin {nonce: \'' . esc_attr( Util_Nonce::create_admin( 'w3tc_licensing_upgrade' ) ) . '\'}" data-src="' . $chart_id . '_cache_chart" value="' . esc_html__( 'Unlock Feature', 'w3-total-cache' ) . '" />';
		} elseif ( ! $w3tc_config->get_boolean( 'stats.enabled' ) ) {
			$chart_id     .= '_enable';
			$chart_content = Util_Ui::button_link( __( 'Enable Statistics', 'w3-total-cache' ), Util_Ui::admin_url( 'admin.php?page=w3tc_general#stats' ), false, 'button-primary' );
		} elseif ( ! $w3tc_config->get_boolean( $chart_config['setting_key'] ) ) {
			$chart_id     .= '_subenable';
			$chart_content = Util_Ui::button_link( $chart_config['cache_enable_label'], $chart_config['cache_enable_url'], false, 'button-primary' );
		} elseif ( ! empty( $chart_config['unavailable_message'] ) ) {
			$chart_id     .= '_nodata';
			$chart_content = '<p>' . esc_html( $chart_config['unavailable_message'] ) . '</p>' .
				Util_Ui::button_link( __( 'Statistics Settings', 'w3-total-cache' ), Util_Ui::admin_url( 'admin.php?page=w3tc_general#stats' ), false, 'button-primary' );
		}
		return '<div id="' . $chart_id . '">' . $chart_content . '</div>';
	}

	/**
	 * Get button link allowed tags.
	 *
	 * @since 2.7.0
	 *
	 * @return array
	 */
	public static function get_allowed_tags() {
		return array(
			'div'   => array(
				'id' => array(),
			),
			'p'     => array(),
			'input' => array(
				'type'     => array(),
				'name'     => array(),
				'class'    => array(),
				'value'    => array(),
				'onclick'  => array(),
				'data-src' => array(),
			),
		);
	}
}
