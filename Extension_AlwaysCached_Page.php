<?php
/**
 * File: Extension_AlwaysCached_Page.php
 *
 * Controls the AlwaysCached settings page.
 *
 * @since 2.8.0
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * AlwaysCached Page.
 *
 * @since 2.8.0
 */
class Extension_AlwaysCached_Page {

	/**
	 * Prints the admin scripts.
	 *
	 * @since 2.8.0
	 *
	 * @return void
	 */
	public static function admin_print_scripts() {
		if ( 'alwayscached' === Util_Request::get_string( 'extension' ) ) {
			wp_register_script( 'w3tc_extension_alwayscached', plugins_url( 'Extension_AlwaysCached_Page_View.js', W3TC_FILE ), array( 'jquery' ), W3TC_VERSION, true );

			wp_localize_script(
				'w3tc_extension_alwayscached',
				'W3TCAlwaysCachedData',
				array(
					'lang' => array(
						'processQueueItemSuccess'   => __( 'Successfully regenerated entry.', 'w3-total-cache' ),
						'processQueueItemFail'      => __( 'Failed to process queue item.', 'w3-total-cache' ),
						'processQueueItemFailAlert' => __( 'An unknown error occured!', 'w3-total-cache' ),
						'queueItemRegenerate'       => __( 'Regenerate', 'w3-total-cache' ),
						'queueItemCommand'          => __( 'command', 'w3-total-cache' ),
						'queuePageLabel'            => __( 'Pages:', 'w3-total-cache' ),
						'queuePageJump'             => __( 'Page #', 'w3-total-cache' ),
						'queuePageJumpSubmit'       => __( 'Go', 'w3-total-cache' ),
						'queueLoadFailAlert'        => __( 'An unknown error occured!', 'w3-total-cache' ),
					),
				)
			);

			wp_enqueue_script( 'w3tc_extension_alwayscached' );
		}
	}

	/**
	 * Prints the settings page.
	 *
	 * @since 2.8.0
	 *
	 * @return void
	 */
	public static function w3tc_extension_page_alwayscached() {
		$config = Dispatcher::config();
		include W3TC_DIR . '/Extension_AlwaysCached_Page_View.php';
	}

	/**
	 * Adds AJAX actions.
	 *
	 * @since 2.8.0
	 *
	 * @return void
	 */
	public static function w3tc_ajax() {
		add_action(
			'w3tc_ajax_extension_alwayscached_queue',
			array(
				'\W3TC\Extension_AlwaysCached_Page',
				'w3tc_ajax_extension_alwayscached_queue',
			)
		);

		add_action(
			'w3tc_ajax_extension_alwayscached_queue_filter',
			array(
				'\W3TC\Extension_AlwaysCached_Page',
				'w3tc_ajax_extension_alwayscached_queue_filter',
			)
		);

		add_action(
			'w3tc_ajax_extension_alwayscached_process_queue_item',
			array(
				'\W3TC\Extension_AlwaysCached_Page',
				'w3tc_ajax_extension_alwayscached_process_queue_item',
			)
		);
	}

	/**
	 * Regenerates queue item.
	 *
	 * @since 2.8.0
	 *
	 * @return void
	 */
	public static function w3tc_ajax_extension_alwayscached_process_queue_item() {
		$item_url = Util_Request::get_string( 'item_url' );
		$item     = Extension_AlwaysCached_Queue::get_by_url( $item_url );
		$result   = Extension_AlwaysCached_Worker::process_item( $item, true );

		if ( 'ok' === $result ) {
			Extension_AlwaysCached_Queue::pop_item_finish( $item, true );
			wp_send_json_success( 'ok' );
		} else {
			wp_send_json_error( 'failed' );
		}
	}

	/**
	 * Prints the queue content via AJAX.
	 *
	 * @since 2.8.0
	 *
	 * @return void
	 */
	public static function w3tc_ajax_extension_alwayscached_queue() {
		include W3TC_DIR . '/Extension_AlwaysCached_Page_Queue_View.php';
		exit();
	}

	/**
	 * Queue filter & pagination.
	 *
	 * @since 2.8.0
	 *
	 * @return void
	 */
	public static function w3tc_ajax_extension_alwayscached_queue_filter() {
		$queue_mode   = Util_Request::get_string( 'mode' );
		$search_query = Util_Request::get_string( 'search', '' );
		$current_page = Util_Request::get_integer( 'page', 1 );
		$limit        = 15;
		$offset       = ( $current_page - 1 ) * $limit;
		$rows         = Extension_AlwaysCached_Queue::rows( $queue_mode, $offset, $limit, $search_query );

		if ( 'postponed' === $queue_mode ) {
			$total_rows = Extension_AlwaysCached_Queue::row_count_postponed( $search_query );
		} else {
			$total_rows = Extension_AlwaysCached_Queue::row_count_pending( $search_query );
		}

		wp_send_json(
			array(
				'rows'         => $rows,
				'total_pages'  => ceil( $total_rows / $limit ),
				'current_page' => $current_page,
			)
		);
	}
}
