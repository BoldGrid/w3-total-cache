<?php
namespace W3TC;

/**
 * worker to refresh cache
 */
class Extension_AlwaysCached_Worker {
	static public function run() {
		$time_exit = time() + 60;
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
			if ( empty( $result['headers'] ) || empty( $result['headers']['w3tcalwayscached'] ) ) {
				echo "no evidence of cache refresh, will retry";
			}

			Extension_AlwaysCached_Queue::pop_item_finish( $item );
			echo " done";
		}

		echo "\n";
	}
}
