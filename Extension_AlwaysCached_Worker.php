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

			$w3tc_item = Extension_AlwaysCached_Queue::pop_item_begin();

			if ( empty( $w3tc_item ) ) {
				esc_html_e( "\nQueue is empty.", 'w3-total-cache' );
				break;
			}

			echo esc_html( sprintf( "\n%s ", $w3tc_item['key'] ) );

			$w3tc_result = self::process_item( $w3tc_item );

			if ( 'ok' === $w3tc_result ) {
				esc_html_e( 'ok', 'w3-total-cache' );
				Extension_AlwaysCached_Queue::pop_item_finish( $w3tc_item );
				update_option( 'w3tc_alwayscached_worker_timestamp', gmdate( 'Y-m-d G:i:s' ) );
			} elseif ( 'postpone' === $w3tc_result ) {
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
	 * @param array $w3tc_item Item.
	 * @param bool  $ajax Ajax flag.
	 *
	 * @return string
	 */
	public static function process_item( $w3tc_item, $ajax = false ) {
		return self::process_item_url( $w3tc_item, $ajax );
	}

	/**
	 * Process item by URL.
	 *
	 * @since 2.8.0
	 *
	 * @param array $w3tc_item Item.
	 * @param bool  $ajax Ajax flag.
	 *
	 * @return string
	 */
	private static function process_item_url( $w3tc_item, $ajax = false ) {
		if ( ! $ajax ) {
			echo esc_html(
				sprintf(
					// translators: 1 item URL.
					__( 'regenerate %s... ', 'w3-total-cache' ),
					$w3tc_item['url']
				)
			);
		}

		/**
		 * The queue is populated from admin-set URLs; an untrusted
		 * caller who reaches the queue-write surface (a config-write
		 * pathway that doesn't already validate the URL, a future
		 * schema change) could enqueue a URL pointing at AWS instance
		 * metadata, a Redis bound to localhost, or an RFC1918
		 * neighbour. Regeneration must target a host of this site or its
		 * network before the wp_remote_request fires.
		 */
		if ( ! self::is_regeneration_url( $w3tc_item['url'] ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'AlwaysCached refused URL' );
			return 'failed';
		}

		$headers = Util_Environment::alwayscached_request_headers( $w3tc_item['key'] );
		if ( empty( $headers['Authorization'] ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'AlwaysCached worker proof unavailable' );
			return 'failed';
		}

		$w3tc_result = wp_remote_request(
			$w3tc_item['url'],
			array(
				'headers'     => $headers,
				'redirection' => 0,
			)
		);

		if (
			is_wp_error( $w3tc_result )
			|| empty( $w3tc_result['response']['code'] )
			|| 500 === $w3tc_result['response']['code']
		) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'failed to handle queue url' . $w3tc_item['url'] );
			return 'failed';
		}

		if ( empty( $w3tc_result['headers'] ) || empty( $w3tc_result['headers']['w3tcalwayscached'] ) ) {
			if ( ! $ajax ) {
				esc_html_e( "\n  no evidence of cache refresh, will reprocess on next schedule/run\n  ", 'w3-total-cache' );
			}
			return 'failed';
		}

		return 'ok';
	}

	/**
	 * Whether a queued URL belongs to this site or a site in this network.
	 *
	 * The queue table is shared by the whole network, so a subsite URL may
	 * use a host other than the one handling the worker run.
	 *
	 * @since 2.11.1
	 *
	 * @param string $w3tc_url Queued URL.
	 *
	 * @return bool
	 */
	public static function is_regeneration_url( $w3tc_url ) {
		if ( Util_Url::is_self_host_url( $w3tc_url ) ) {
			return true;
		}

		if ( ! \is_multisite() || ! Util_Url::is_valid_http_scheme( $w3tc_url ) ) {
			return false;
		}

		$host = \wp_parse_url( $w3tc_url, PHP_URL_HOST );
		if ( ! \is_string( $host ) || '' === $host ) {
			return false;
		}

		$sites = \get_sites(
			array(
				'domain'     => \strtolower( $host ),
				'network_id' => \get_current_network_id(),
				'number'     => 1,
				'fields'     => 'ids',
			)
		);

		return ! empty( $sites );
	}
}
