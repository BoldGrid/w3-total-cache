<?php
/**
 * File: Extension_AlwaysCached_Worker.php
 *
 * AlwaysCached worker model.
 *
 * @since 2.8.0
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * AlwaysCached worker model.
 *
 * @since 2.8.0
 */
class Extension_AlwaysCached_Worker {

	/**
	 * Run method for AlwaysCached worker.
	 *
	 * @since 2.8.0
	 *
	 * @param bool $html Print HTML.
	 *
	 * @return void
	 */
	public static function run( $html = true ) {
		$timeslot_seconds = 60;
		$timeslot_seconds = apply_filters(
			'w3tc_alwayscached_worker_timeslot',
			$timeslot_seconds
		);

		$time_exit = time() + $timeslot_seconds;

		if ( $html ) {
			echo '<div style="white-space: pre-line;">';
		}

		esc_html_e( 'Processing queue.', 'w3-total-cache' );

		// Infinite loop to process queue items until the time slot is exhausted or the queue is empty.
		while ( true ) {
			if ( time() >= $time_exit ) {
				esc_html_e( "\nQueue worker time slot exhaused.", 'w3-total-cache' );
				break;
			}

			$item = Extension_AlwaysCached_Queue::pop_item_begin();

			if ( empty( $item ) ) {
				esc_html_e( "\nQueue is empty.", 'w3-total-cache' );
				break;
			}

			echo esc_html( sprintf( "\n%s ", $item['key'] ) );

			$result = self::process_item( $item );

			if ( 'ok' === $result ) {
				esc_html_e( 'ok', 'w3-total-cache' );
				Extension_AlwaysCached_Queue::pop_item_finish( $item );
				update_option( 'w3tc_alwayscached_worker_timestamp', gmdate( 'Y-m-d G:i:s' ) );
			} elseif ( 'postpone' === $result ) {
				esc_html_e( 'postponed', 'w3-total-cache' );
			} else {
				esc_html_e( 'failed', 'w3-total-cache' );
			}
		}

		if ( $html ) {
			echo "\n</div>\n";
		} else {
			echo "\n";
		}
	}

	/**
	 * Process item.
	 *
	 * @since 2.8.0
	 *
	 * @param array $item Item.
	 * @param bool  $ajax Ajax flag.
	 *
	 * @return string
	 */
	public static function process_item( $item, $ajax = false ) {
		return self::process_item_url( $item, $ajax );
	}

	/**
	 * Process item by URL.
	 *
	 * @since 2.8.0
	 *
	 * @param array $item Item.
	 * @param bool  $ajax Ajax flag.
	 *
	 * @return string
	 */
	private static function process_item_url( $item, $ajax = false ) {
		if ( ! $ajax ) {
			echo esc_html(
				sprintf(
					// translators: 1 item URL.
					__( 'regenerate %s... ', 'w3-total-cache' ),
					$item['url']
				)
			);
		}

		$result = wp_remote_request(
			$item['url'],
			array(
				'headers' => array(
					'w3tcalwayscached' => $item['key'],
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
			return 'failed';
		}

		if ( empty( $result['headers'] ) || empty( $result['headers']['w3tcalwayscached'] ) ) {
			if ( ! $ajax ) {
				esc_html_e( "\n  no evidence of cache refresh, will reprocess on next schedule/run\n  ", 'w3-total-cache' );
			}
			return 'failed';
		}

		return 'ok';
	}
}
