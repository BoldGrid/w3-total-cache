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

		echo '<div style="white-space: pre-line;">';

		esc_html_e( "Processing queue.\n", 'w3-total-cache' );

		for ( ; ; ) {
			if ( time() >= $time_exit ) {
				esc_html_e( "\n\nQueue worker time slot exhaused.", 'w3-total-cache' );
				break;
			}

			$item = Extension_AlwaysCached_Queue::pop_item_begin();

			if ( empty( $item ) ) {
				esc_html_e( "\n\nQueue is empty.", 'w3-total-cache' );
				break;
			}

			echo esc_html__( "\nrefreshing... [ ", 'w3-total-cache' ) . esc_html( $item['id'] ) . ' : ' . esc_html( $item['url'] ) . ' ] ...';

			$result = wp_remote_request(
				$item['url'],
				array(
					'headers' => array(
						'w3tcalwayscached' => $item['id'],
					),
				)
			);

			if (
				is_wp_error( $result )
				|| empty( $result['response']['code'] )
				|| 500 === $result['response']['code']
			) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'failed to handle queue url' . $item['url'] );
				esc_html_e( 'failed', 'w3-total-cache' );
				continue;
			}

			if ( empty( $result['headers'] ) || empty( $result['headers']['w3tcalwayscached'] ) ) {
				esc_html_e( 'no evidence of cache refresh, will reprocess on next schedule/run', 'w3-total-cache' );
				continue;
			}

			Extension_AlwaysCached_Queue::pop_item_finish( $item );

			update_option( 'w3tc_alwayscached_worker_timestamp', gmdate( 'Y-m-d G:i:s' ) );

			esc_html_e( 'refreshed', 'w3-total-cache' );
		}

		echo '</div>';
	}
}
