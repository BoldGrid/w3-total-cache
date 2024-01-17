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

			echo esc_html( sprintf( "\n%d ", $item['id'] ) );

			$success = self::process_item($item);
			if ( !$success ) {
				esc_html_e( 'failed', 'w3-total-cache' );
			} else {
				esc_html_e( 'ok', 'w3-total-cache' );
				Extension_AlwaysCached_Queue::pop_item_finish( $item );
				update_option( 'w3tc_alwayscached_worker_timestamp', gmdate( 'Y-m-d G:i:s' ) );
			}
		}

		echo "\n</div>\n";
	}



	static private function process_item( $item ) {
		if ( $item['page_key'] == ':flush_group.regenerate' ) {
			return self::process_item_flush_group_regenerate( $item );
		} elseif ( $item['page_key'] == ':flush_group.remainder' ) {
			return self::process_item_flush_group_remainder( $item );
		}

		return self::process_item_url( $item );
	}



	static private function process_item_url( $item ) {
		echo esc_html( sprintf(
			__( "regenerate %s... ", 'w3-total-cache' ), $item['url'] ) );

		$result = Util_Http::request(
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
			return false;
		}

		if ( empty( $result['headers'] ) || empty( $result['headers']['w3tcalwayscached'] ) ) {
			esc_html_e( "\n  no evidence of cache refresh, will reprocess on next schedule/run\n  ", 'w3-total-cache' );
			return false;
		}

		return true;
	}



	static private function process_item_flush_group_regenerate( $item ) {
		$c = Dispatcher::config();


		if ( $c->get_boolean( array( 'alwayscached', 'flush_all_home' ) ) ) {
			self::add_url_to_queue( home_url() );
		}

		$posts_count = $c->get_integer( array( 'alwayscached', 'flush_all_posts_count' ) );
		if ( $posts_count > 0 ) {
			$posts = get_posts( array(
				'post_type' => 'post',
				'post_status' => 'publish',
				'posts_per_page' => $posts_count,
				'order' => 'DESC',
				'orderby' => 'modified'
			) );

			foreach ( $posts as $post ) {
				self::add_url_to_queue( get_permalink( $post ) );
			}
		}

		$pages_count = $c->get_integer( array( 'alwayscached', 'flush_all_pages_count' ) );
		if ( $pages_count > 0 ) {
			$posts = get_posts( array(
				'post_type' => 'page',
				'post_status' => 'publish',
				'posts_per_page' => $posts_count,
				'order' => 'DESC',
				'orderby' => 'modified'
			) );

			foreach ( $posts as $post ) {
				self::add_url_to_queue( get_permalink( $post ) );
			}
		}
		return true;
	}



	static private function add_url_to_queue( $url ) {
		$provider = Dispatcher::component( 'PgCache_Flush' );
		$items = $provider->get_page_keys_for_url( array(
			'url' => $url,
			'group' => '',
			'groups_filter' => function( $groups ) {
				$groups['mobile_groups'] = array( $groups['mobile_groups'][0] );
				$groups['referrer_groups'] = array( $groups['referrer_groups'][0] );
				$groups['cookies'] = array( $groups['cookies'][0] );
				$groups['compressions'] = array( false );

				return $groups;
			}
		) );

		foreach ( $items as $i ) {
			Extension_AlwaysCached_Queue::add(
				$i['page_key'],
				$url,
				$i['page_key_extension']
			);
		}
	}



	static private function process_item_flush_group_remainder( $item ) {
		return true;
	}
}
