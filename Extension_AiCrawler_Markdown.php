<?php
/**
 * File: Extension_AiCrawler_Markdown.php
 *
 * Handles generation of markdown content for posts and pages using the
 * AI Crawler API.
 *
 * @package W3TC
 * @since   X.X.X
 */

namespace W3TC;

/**
 * Class Extension_AiCrawler_Markdown
 *
 * Queues URLs for markdown generation and processes the queue in the
 * background using a cron event.
 *
 * @since X.X.X
 */
class Extension_AiCrawler_Markdown {
	/**
	 * Cron hook name.
	 *
	 * @since X.X.X
	 * @var   string
	 */
	const CRON_HOOK = 'w3tc_aicrawler_markdown_cron';

	/**
	 * Post meta key for original URL.
	 *
	 * @since X.X.X
	 * @var   string
	 */
	const META_SOURCE_URL = 'w3tc_aicrawler_source_url';

	/**
	 * Post meta key for processing status.
	 *
	 * @since X.X.X
	 * @var   string
	 */
	const META_STATUS = 'w3tc_aicrawler_status';

	/**
	 * Post meta key for error messages.
	 *
	 * @since X.X.X
	 * @var   string
	 */
	const META_ERROR_MESSAGE = 'w3tc_aicrawler_error_message';

	/**
	 * Post meta key where generated markdown is stored.
	 *
	 * @since X.X.X
	 * @var   string
	 */
	const META_MARKDOWN = 'w3tc_aicrawler_markdown';

	/**
	 * Post meta key for the public markdown URL.
	 *
	 * @since X.X.X
	 * @var   string
	 */
	const META_MARKDOWN_URL = 'w3tc_aicrawler_markdown_url';

	/**
	 * Post meta key for the timestamp when the URL was queued.
	 *
	 * @since X.X.X
	 * @var   string
	 */
	const META_TIMESTAMP = 'w3tc_aicrawler_timestamp';

	/**
	 * Initialize hooks for cron processing.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public static function init() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'process_queue' ) );
		add_filter( 'cron_schedules', array( __CLASS__, 'add_schedule' ) );

		if ( self::queue_has_items() ) {
			self::schedule_cron();
		}
	}

	/**
	 * Schedule the cron event if it is not already scheduled.
	 *
	 * @since X.X.X
	 *
	 * @return bool
	 */
	private static function schedule_cron() {
		static $scheduled = false;

		if ( $scheduled ) {
			return true;
		}

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			$scheduled = (bool) wp_schedule_event( time(), 'ten_seconds', self::CRON_HOOK );
		}

		return $scheduled;
	}

	/**
	 * Remove the cron event if scheduled.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	private static function unschedule_cron() {
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}
	}

	/**
	 * Adds a ten second cron schedule used by the queue worker.
	 *
	 * @since X.X.X
	 *
	 * @param array $schedules Existing schedules.
	 *
	 * @return array
	 */
	public static function add_schedule( array $schedules = array() ) {
		if ( ! isset( $schedules['ten_seconds'] ) ) {
			$schedules['ten_seconds'] = array(
				'interval' => 10,
				'display'  => esc_html__( 'Every Ten Seconds', 'w3-total-cache' ),
			);
		}
		return $schedules;
	}

	/**
	 * Queue a URL for markdown generation.
	 *
	 * @since X.X.X
	 *
	 * @param string $url URL to generate markdown for.
	 *
	 * @return bool True on success, false otherwise.
	 */
	public static function generate_markdown( $url ) {
		$url     = esc_url_raw( $url );
		$post_id = url_to_postid( $url );

		if ( ! $post_id || Extension_AiCrawler_Util::is_excluded( $post_id ) ) {
			return false;
		}

		update_post_meta( $post_id, self::META_STATUS, 'queued' );
		update_post_meta( $post_id, self::META_SOURCE_URL, esc_url_raw( $url ) );
		delete_post_meta( $post_id, self::META_ERROR_MESSAGE );

		self::schedule_cron();

		return true;
	}

	/**
	 * Process queued URLs and generate markdown.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public static function process_queue() {
		$posts = self::get_queue_posts();

		if ( empty( $posts ) ) {
			self::unschedule_cron();
			return;
		}

		foreach ( $posts as $post_id ) {
			$url = get_post_meta( $post_id, self::META_SOURCE_URL, true );

			// Update the timestamp when processing starts.
			update_post_meta( $post_id, self::META_TIMESTAMP, time() );

			if ( empty( $url ) ) {
				update_post_meta( $post_id, self::META_STATUS, 'error' );
				update_post_meta( $post_id, self::META_ERROR_MESSAGE, __( 'Missing URL.', 'w3-total-cache' ) );
				continue;
			}

			if ( Extension_AiCrawler_Util::is_excluded( $post_id ) ) {
				update_post_meta( $post_id, self::META_STATUS, 'excluded' );
				continue;
			}

			update_post_meta( $post_id, self::META_STATUS, 'processing' );
			delete_post_meta( $post_id, self::META_ERROR_MESSAGE );
			$response = Extension_AiCrawler_Central_Api::call( 'convert', 'POST', array( 'url' => $url ) );

			if ( empty( $response['success'] ) || empty( $response['data']['markdown_content'] ) ) {
				update_post_meta( $post_id, self::META_STATUS, 'error' );
				$message = ! empty( $response['error']['message'] ) ? $response['error']['message'] : __( 'Unknown error.', 'w3-total-cache' );
				update_post_meta( $post_id, self::META_ERROR_MESSAGE, $message );
				continue;
			}

			$markdown     = $response['data']['markdown_content'];
			$markdown_url = add_query_arg( array( 'w3tc_aicrawler_markdown' => $post_id ), home_url( '/' ) );

			update_post_meta( $post_id, self::META_MARKDOWN, $markdown );
			update_post_meta( $post_id, self::META_MARKDOWN_URL, $markdown_url );
			update_post_meta( $post_id, self::META_STATUS, 'complete' );
			delete_post_meta( $post_id, self::META_ERROR_MESSAGE );

			// Update the timestamp again when complete:
			update_post_meta( $post_id, self::META_TIMESTAMP, time() );
		}

		if ( ! self::queue_has_items() ) {
			self::unschedule_cron();
		}
	}

	/**
	 * Determine if there are queued items waiting to be processed.
	 *
	 * @since X.X.X
	 *
	 * @return bool
	 */
	private static function queue_has_items() {
		return ! empty( self::get_queue_posts() );
	}

	/**
	 * Get Queue Posts
	 *
	 * @since X.X.X
	 *
	 * @return array Array of WP_Post objects that are queued for markdown generation.
	 */
	public static function get_queue_posts() {
		$query = new \WP_Query(
			array(
				'post_type'      => 'any',
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => self::META_STATUS,
						'value' => 'queued',
					),
				),
			)
		);

		return ! empty( $query->posts ) ? $query->posts : array();
	}

	/**
	 * Get counts of items by status.
	 *
	 * @since X.X.X
	 *
	 * @return array
	 */
	public static function get_status_counts() {
		$statuses = array( 'queued', 'processing', 'complete', 'error' );
		$counts   = array();

		foreach ( $statuses as $status ) {
			$q = new \WP_Query(
				array(
					'post_type'      => 'any',
					'posts_per_page' => -1,
					'post_status'    => 'any',
					'fields'         => 'ids',
					'meta_query'     => array(
						array(
							'key'   => self::META_STATUS,
							'value' => $status,
						),
					),
				)
			);

			$counts[ $status ] = ! empty( $q->posts ) ? count( $q->posts ) : 0;
		}

		$counts['total'] = array_sum( $counts );

		return $counts;
	}

		/**
		 * Get queue items with pagination.
		 *
		 * @since X.X.X
		 *
		 * @param int $paged    Page number.
		 * @param int $per_page Items per page.
		 *
		 * @return array
		 */
	public static function get_queue_items( $paged = 1, $per_page = 20 ) {
		$query = new \WP_Query(
			array(
				'post_type'              => 'any',
				'post_status'            => 'any',
				'posts_per_page'         => $per_page,
				'paged'                  => $paged,

				// Only return IDs (faster).
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'ignore_sticky_posts'    => true,

				// Sort by timestamp.
				'meta_key'   => self::META_TIMESTAMP,
				'meta_type'  => 'UNSIGNED',        // tells WP this is numeric.
				'orderby'    => 'meta_value_num',  // use numeric compare.
				'order'      => 'DESC',            // newest first.

				'meta_compare' => 'EXISTS',
			)
		);

		return array(
			'items' => ! empty( $query->posts ) ? $query->posts : array(),
			'total' => (int) $query->found_posts,
		);
	}
}
