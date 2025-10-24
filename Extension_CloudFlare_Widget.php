<?php
/**
 * File: Extension_CloudFlare_Widget.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_CloudFlare_Widget
 *
 * Widget with stats
 */
class Extension_CloudFlare_Widget {
	/**
	 * Initializes the Cloudflare widget for the admin dashboard.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_print_styles-toplevel_page_w3tc_dashboard', array( $this, 'admin_print_styles_w3tc_dashboard' ) );
		add_action( 'admin_print_scripts-toplevel_page_w3tc_dashboard', array( $this, 'admin_print_scripts_w3tc_dashboard' ) );

		Util_Widget::add2(
			'w3tc_cloudflare',
			600,
			'<div class="w3tc_cloudflare_widget_logo"></div>',
			array( $this, 'widget_form' ),
			Util_Ui::admin_url( 'admin.php?page=w3tc_general#cloudflare' ),
			'normal'
		);
	}

	/**
	 * Displays the widget form with Cloudflare statistics.
	 *
	 * @return void
	 */
	public function widget_form() {
		$api      = Extension_CloudFlare_SettingsForUi::api();
		$c        = Dispatcher::config();
		$interval = $c->get_integer( array( 'cloudflare', 'widget_interval' ) );
		$v        = get_transient( 'w3tc_cloudflare_stats' );
		try {
			$key = 'dashboard-' . $interval;
			if ( ! isset( $v[ $key ] ) ) {
				if ( ! is_array( $v ) ) {
					$v = array();
				}

				$type    = 'day';
				$dataset = 'httpRequests1dGroups';
				$end     = current_time( 'Y-m-d' );
				$start   = gmdate( 'Y-m-d', strtotime( $end . ' ' . $interval . ' minutes' ) );

				if ( $interval >= -1440 ) {
					$type    = 'hour';
					$dataset = 'httpRequests1hGroups';
					$end     = current_time( 'Y-m-d\TH:i:s' ) . 'Z';
					$start   = gmdate( 'Y-m-d\TH:i:s', strtotime( $end . ' ' . $interval . ' minutes' ) ) . 'Z';
				}

				$stats = array(
					'since'            => $start,
					'until'            => $end,
					'bandwidth_all'    => 0,
					'bandwidth_cached' => 0,
					'requests_all'     => 0,
					'requests_cached'  => 0,
					'pageviews_all'    => 0,
					'uniques_all'      => 0,
					'threats_all'      => 0,
					'interval'         => $interval,
					'cached_ts'        => current_time( 'Y-m-d H:i:s' ),
					'cached_tf'        => $c->get_integer( array( 'cloudflare', 'widget_cache_mins' ) ),
				);

				$analytics_dashboard_data = $api->analytics_dashboard( $start, $end, $type );
				foreach ( $analytics_dashboard_data['viewer']['zones'][0][ $dataset ] as $data ) {
					$stats['bandwidth_all']    += $data['sum']['bytes'];
					$stats['bandwidth_cached'] += $data['sum']['cachedBytes'];
					$stats['requests_all']     += $data['sum']['requests'];
					$stats['requests_cached']  += $data['sum']['cachedRequests'];
					$stats['pageviews_all']    += $data['sum']['pageViews'];
					$stats['uniques_all']      += $data['uniq']['uniques'];
					$stats['threats_all']      += $data['sum']['threats'];
				}

				$v[ $key ] = $stats;

				set_transient(
					'w3tc_cloudflare_stats',
					$v,
					$stats['cached_tf'] * 60
				);
			}

			$stats = $v[ $key ];
		} catch ( \Exception $e ) {
			$stats = null;
		}

		include W3TC_DIR . '/Extension_CloudFlare_Widget_View.php';
	}

	/**
	 * Enqueues the styles for the Cloudflare widget on the admin dashboard.
	 *
	 * @return void
	 */
	public function admin_print_styles_w3tc_dashboard() {
		wp_enqueue_style( 'w3tc-widget' );
		wp_enqueue_style(
			'w3tc-cloudflare-widget',
			plugins_url( 'Extension_CloudFlare_Widget_View.css', W3TC_FILE ),
			array(),
			W3TC_VERSION
		);
	}

	/**
	 * Enqueues the scripts for the Cloudflare widget on the admin dashboard.
	 *
	 * @return void
	 */
	public function admin_print_scripts_w3tc_dashboard() {
		wp_enqueue_script( 'w3tc-metadata' );
		wp_enqueue_script( 'w3tc-widget' );
	}

	/**
	 * Outputs the formatted value for display in the widget.
	 *
	 * @param mixed $value The value to format and display.
	 *
	 * @return void
	 */
	private function value( $value ) {
		echo '<td class="cloudflare_td_value">';
		echo number_format( $value );
		echo "</td>\n";
	}

	/**
	 * Outputs the formatted date for display in the widget.
	 *
	 * @param string $value The date to format.
	 *
	 * @return void
	 */
	private function date( $value ) {
		echo esc_html( gmdate( 'n/j/Y', strtotime( $value ) ) );
	}

	/**
	 * Outputs the formatted date and time for display in the widget.
	 *
	 * @param string $value The date and time to format.
	 *
	 * @return void
	 */
	private function date_time( $value ) {
		echo esc_html( gmdate( 'n/j/Y g:i a', strtotime( $value ) ) );
	}

	/**
	 * Outputs the formatted date, time, and seconds for display in the widget.
	 *
	 * @param string $value The date and time to format.
	 *
	 * @return void
	 */
	private function date_time_sec( $value ) {
		echo esc_html( gmdate( 'n/j/Y g:i:s a', strtotime( $value ) ) );
	}
}
