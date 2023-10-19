<?php
/**
 * File: Extension_AlwaysCached_Worker.php
 *
 * AlwaysCached worker model.
 *
 * @since 2.5.1
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * AlwaysCached worker model.
 *
 * @since 2.5.1
 */
class Extension_AlwaysCached_Worker {

	/**
	 * Run method for AlwaysCached worker.
	 *
	 * @since 2.5.1
	 *
	 * @return void
	 */
	public static function run() {
		$timeslot_seconds = 60;
		$timeslot_seconds = apply_filters(
			'w3tc_alwayscached_worker_timeslot',
			$timeslot_seconds
		);

		$time_exit = time() + $timeslot_seconds;

		for ( ; ; ) {
			if ( time() >= $time_exit ) {
				esc_html_e( "\ntime slot exhaused", 'w3-total-cache' );
				break;
			}

			$item = Extension_AlwaysCached_Queue::pop_item_begin();

			if ( empty( $item ) ) {
				esc_html_e( "\nqueue is empty", 'w3-total-cache' );
				return;
			}

			echo esc_html__( "\nrefreshing ", 'w3-total-cache' ) . esc_html( $item['id'] ) . ':' . esc_html( $item['url'] ) . '...';

			$result = wp_remote_request(
				$item['url'],
				array(
					'headers' => array(
						'w3tcalwayscached' => $item['id'],
					),
				)
			);

			if (
				empty( $result['response'] )
				|| empty( $result['response']['code'] )
				|| 500 === $result['response']['code']
			) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'failed to handle queue url' . $item['url'] );
				esc_html_e( 'failed', 'w3-total-cache' );
				continue;
			}

			if ( empty( $result['headers'] ) || empty( $result['headers']['w3tcalwayscached'] ) ) {
				esc_html_e( 'no evidence of cache refresh, will retry', 'w3-total-cache' );
			}

			Extension_AlwaysCached_Queue::pop_item_finish( $item );

			update_option( 'w3tc_alwayscached_worker_timestamp', gmdate( 'Y-m-d G:i:s' ) );

			esc_html_e( 'done', 'w3-total-cache' );
		}
	}
}
