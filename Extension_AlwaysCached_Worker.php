<?php
/**
 * File: Extension_AlwaysCached_Worker.php
 *
 * AlwaysCached worker model.
 *
 * @since X.X.X
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * AlwaysCached worker model.
 *
 * @since X.X.X
 */
class Extension_AlwaysCached_Worker {

	/**
	 * Run method for AlwaysCached worker.
	 *
	 * @since X.X.X
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

		echo "\n</div>\n";
	}

	/**
	 * Process item.
	 *
	 * @since X.X.X
	 *
	 * @param array $item Item.
	 * @param bool  $ajax Ajax flag.
	 *
	 * @return string
	 */
	public static function process_item( $item, $ajax = false ) {
		if ( ':flush_group.regenerate' === $item['key'] ) {
			return self::process_item_flush_group_regenerate( $item, $ajax );
		} elseif ( ':flush_group.remainder' === $item['key'] ) {
			return self::process_item_flush_group_remainder( $item, $ajax );
		}

		return self::process_item_url( $item, $ajax );
	}

	/**
	 * Process item by URL.
	 *
	 * @since X.X.X
	 *
	 * @param array $item Item.
	 * @param bool  $ajax Ajax flag.
	 *
	 * @return string
	 */
	private static function process_item_url( $item, $ajax = false ) {
		if ( ! $ajax) {
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

	/**
	 * Process item flush group regenerate.
	 *
	 * @since X.X.X
	 *
	 * @param array $item Item.
	 * @param bool  $ajax Ajax flag.
	 *
	 * @return string
	 */
	private static function process_item_flush_group_regenerate( $item, $ajax = false ) {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
		$item_extension = @unserialize( $item['extension'] );

		$c = Dispatcher::config();

		if ( ! $ajax ) {
			esc_html_e( "\n  building purge-all urls to regenerate\n  ", 'w3-total-cache' );
		}

		if ( $c->get_boolean( array( 'alwayscached', 'flush_all_home' ) ) ) {
			Extension_AlwaysCached_Queue::add( rtrim( home_url(), '/' ) . '/', $item_extension );
		}

		$posts_count = $c->get_integer( array( 'alwayscached', 'flush_all_posts_count' ) );
		if ( $posts_count > 0 ) {
			$posts = get_posts(
				array(
					'post_type'      => 'post',
					'post_status'    => 'publish',
					'posts_per_page' => $posts_count,
					'order'          => 'DESC',
					'orderby'        => 'modified',
				)
			);

			foreach ( $posts as $post ) {
				Extension_AlwaysCached_Queue::add( get_permalink( $post ), $item_extension );
			}
		}

		$pages_count = $c->get_integer( array( 'alwayscached', 'flush_all_pages_count' ) );
		if ( $pages_count > 0 ) {
			$posts = get_posts(
				array(
					'post_type'      => 'page',
					'post_status'    => 'publish',
					'posts_per_page' => $pages_count,
					'order'          => 'DESC',
					'orderby'        => 'modified',
				)
			);

			foreach ( $posts as $post ) {
				Extension_AlwaysCached_Queue::add( get_permalink( $post ), $item_extension );
			}
		}

		return 'ok';
	}

	/**
	 * Process item flush group remainder.
	 *
	 * @since X.X.X
	 *
	 * @param array $item Item.
	 * @param bool  $ajax Ajax flag.
	 *
	 * @return string
	 */
	private static function process_item_flush_group_remainder( $item, $ajax = false ) {
		/**
		 * Cant flush when something is still going to be regenerated
		 * in order to prevent pages which are going to be regenerated
		 * to became uncached
		 */
		if ( Extension_AlwaysCached_Queue::exists_higher_priority( $item ) ) {
			return 'postpone';
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
		$extension = @unserialize( $item['extension'] );

		$o = Dispatcher::component( 'PgCache_Flush' );
		$o->flush_group_after_ahead_generation(
			empty( $extension['group'] ) ? '' : $extension['group'],
			$extension
		);

		return 'ok';
	}
}
