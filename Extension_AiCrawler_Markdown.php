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
	 * Initialize hooks for cron processing.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public static function init() {
				add_action( self::CRON_HOOK, array( __CLASS__, 'process_queue' ) );
				add_filter( 'cron_schedules', array( __CLASS__, 'add_schedule' ) );
				add_action( 'update_option_permalink_structure', array( __CLASS__, 'refresh_markdown_urls' ), 10, 0 );

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
		 * Regenerate stored markdown URLs when permalink structure changes.
		 *
		 * @since X.X.X
		 *
		 * @return void
		 */
	public static function refresh_markdown_urls() {
			$query = new \WP_Query(
				array(
					'post_type'      => 'any',
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'meta_query'     => array(
						array(
							'key'     => self::META_MARKDOWN_URL,
							'compare' => 'EXISTS',
						),
					),
				)
			);

		if ( empty( $query->posts ) ) {
				return;
		}

		foreach ( $query->posts as $post_id ) {
				$markdown_url = Extension_AiCrawler_Markdown_Server::get_markdown_url( $post_id );
				update_post_meta( $post_id, self::META_MARKDOWN_URL, $markdown_url );
		}
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
		update_post_meta( $post_id, self::META_SOURCE_URL, $url );

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
			if ( empty( $url ) ) {
				update_post_meta( $post_id, self::META_STATUS, 'error' );
				continue;
			}

			if ( Extension_AiCrawler_Util::is_excluded( $post_id ) ) {
				update_post_meta( $post_id, self::META_STATUS, 'excluded' );
				continue;
			}

			update_post_meta( $post_id, self::META_STATUS, 'processing' );
			$response = Extension_AiCrawler_Central_Api::call( 'convert', 'POST', array( 'url' => $url ) );

			if ( empty( $response['success'] ) || empty( $response['data']['markdown_content'] ) ) {
				update_post_meta( $post_id, self::META_STATUS, 'error' );
				continue;
			}

			$markdown     = $response['data']['markdown_content'];
			$markdown_url = Extension_AiCrawler_Markdown_Server::get_markdown_url( $post_id );

			update_post_meta( $post_id, self::META_MARKDOWN, $markdown );
			update_post_meta( $post_id, self::META_MARKDOWN_URL, $markdown_url );
			update_post_meta( $post_id, self::META_STATUS, 'complete' );
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
}
