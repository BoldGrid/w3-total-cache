<?php
/**
 * File: Cdn_BunnyCdn_Widget.php
 *
 * @since   X.X.X
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Cdn_BunnyCdn_Widget
 *
 * @since X.X.X
 */
class Cdn_BunnyCdn_Widget {
	/**
	 * Initialize the WP Admin Dashboard.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public static function admin_init_w3tc_dashboard() {
		$o = new Cdn_BunnyCdn_Widget();

		add_action( 'admin_print_styles', array( $o, 'admin_print_styles' ) );
		add_action( 'admin_print_scripts', array( $o, 'admin_print_scripts' ) );

		Util_Widget::add2(
			'w3tc_bunnycdn',
			2000,
			'<div class="w3tc-widget-bunnycdn-logo"></div>',
			array( $o, 'widget_form' ),
			Util_Ui::admin_url( 'admin.php?page=w3tc_cdn' ),
			'normal'
		);
	}

	/**
	 * Print widget form.
	 *
	 * @since X.X.X
	 *
	 * return void
	 */
	public function widget_form() {
		$c          = Dispatcher::config();
		$authorized = $c->get_string( 'cdn.bunnycdn.client_id' ) != '' && $c->get_string( 'cdn.engine' ) === 'bunnycdn';

		if ( $authorized ) {
			include __DIR__ . DIRECTORY_SEPARATOR . 'Cdn_BunnyCdn_Widget_View_Authorized.php';
		} else {
			include __DIR__ . DIRECTORY_SEPARATOR . 'Cdn_BunnyCdn_Widget_View_Unauthorized.php';
		}
	}

	/**
	 * W3TC AJAX: Get data for the widget.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public static function w3tc_ajax_cdn_bunnycdn_widgetdata() {
		$c  = Dispatcher::config();
		$cs = Dispatcher::config_state();

		$api = new Cdn_BunnyCdn_Api(
			array(
				'client_id'     => $c->get_string( 'cdn.bunnycdn.client_id' ),
				'client_secret' => $c->get_string( 'cdn.bunnycdn.client_secret' ),
				'stack_id'      => $c->get_string( 'cdn.bunnycdn.stack_id' ),
				'access_token'  => $cs->get_string( 'cdn.bunnycdn.access_token' ),
			)
		);

		$stack_id = $c->get_string( 'cdn.bunnycdn.stack_id' );
		$site_id  = $c->get_string( 'cdn.bunnycdn.site_id' );
		$response = array();

		try {
			$r         = $api->site_metrics( $site_id, 7 );
			$series    = $r['series'][0];
			$keys      = $series['metrics'];
			$key_count = count( $keys );
			$stats     = array();

			foreach ( $series['samples'] as $sample ) {
				$row = array();

				for ( $n = 0; $n < $key_count; $n++ ) {
					$row[ $keys[ $n ] ] = $sample['values'][ $n ];
				}

				$stats[] = $row;
			}

			$total_mb = 0;
			$total_requests = 0;
			$chart_mb = array( array( 'Date', 'MB', 'Requests' ) );

			$dd = new \DateTime();

			foreach ( $stats as $r ) {
				$total_mb += $r['xferUsedTotalMB'];
				$total_requests += $r['requestsCountTotal'];
				$d = $dd->setTimestamp( (int) $r['usageTime'] );
				$chart_mb[] = array(
					$d->format( 'M/d' ),
					$r['xferUsedTotalMB'],
					$r['requestsCountTotal'],
				);
			}

			$response['summary_mb']       = sprintf( '%.2f MB', $total_mb );
			$response['summary_requests'] = $total_requests;
			$response['chart_mb']         = $chart_mb;

			$response['url_manage'] = esc_url(
				'https://@todo/'
			);

			$response['url_reports'] = esc_url(
				'https://@todo/'
			);
		} catch ( \Exception $ex ) {
			$response['error'] = $ex->getMessage();
		}

		echo json_encode( $response );
	}

	/**
	 * Enqueue styles.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function admin_print_styles() {
		wp_enqueue_style( 'w3tc-widget' );

		wp_enqueue_style(
			'w3tc-bunnycdn-widget',
			plugins_url( 'Cdn_BunnyCdn_Widget_View.css', W3TC_FILE ),
			array(),
			W3TC_VERSION
		);
	}

	/**
	 * Enqueue scripts.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function admin_print_scripts() {
		wp_enqueue_script( 'google-jsapi', 'https://www.google.com/jsapi' );
		wp_enqueue_script( 'w3tc-metadata' );
		wp_enqueue_script( 'w3tc-widget' );
		wp_enqueue_script(
			'w3tc-bunnycdn-widget',
			plugins_url( 'Cdn_BunnyCdn_Widget_View.js', W3TC_FILE ),
			array( 'google-jsapi' ),
			W3TC_VERSION
		);
	}
}
