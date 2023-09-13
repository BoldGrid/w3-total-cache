<?php
namespace W3TC;

/**
 * worker to refresh cache
 */
class Extension_AlwaysCached_Worker {
	static public function run() {
		$timeslot_seconds = 60;
		$timeslot_seconds = apply_filters(
			'w3tc_alwayscached_worker_timeslot', $timeslot_seconds );
		$time_exit = time() + $timeslot_seconds;
		for (;;) {
			if ( time() >= $time_exit ) {
				echo "\ntime slot exhaused";
				break;
			}

			$item = Extension_AlwaysCached_Queue::pop_item_begin();
			if ( empty( $item) ) {
				echo "\nqueue is empty";
				return;
			}

			echo "\nrefreshing " . $item['id'] . ': ' . $item['url'] . '...';

			$result = wp_remote_request( $item['url'], [
				'headers' => [
					'w3tcalwayscached' => $item['id']
				]
			] );
			if ( empty( $result['response'] ) ||
					empty( $result['response']['code'] ) ||
					$result['response']['code'] == 500 ) {
				error_log( 'failed to handle queue url' . $item['url'] );
				echo "failed";
				continue;
			}
			if ( empty( $result['headers'] ) ||
					empty( $result['headers']['w3tcalwayscached'] ) ) {
				echo "no evidence of cache refresh, will retry";
			}

			Extension_AlwaysCached_Queue::pop_item_finish( $item );
			update_option( 'w3tc_alwayscached_worker_timestamp',
				gmdate( 'Y-m-d G:i:s' ) );
			echo " done";
		}

		echo "\n";
	}
}
