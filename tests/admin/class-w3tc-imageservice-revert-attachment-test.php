<?php
/**
 * File: class-w3tc-imageservice-revert-attachment-test.php
 *
 * Image Converter revert deletes a stored child only when that child is an
 * attachment of the parent and the current user can delete it.
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.11.1
 */

declare( strict_types = 1 );

use W3TC\Extension_ImageService_Plugin_Admin;

/**
 * Class: W3tc_Imageservice_Revert_Attachment_Test
 *
 * @since 2.11.1
 */
class W3tc_Imageservice_Revert_Attachment_Test extends WP_UnitTestCase {

	/**
	 * Backup of $_REQUEST around tests.
	 *
	 * @var array
	 */
	private $saved_request;

	/**
	 * Backup of $_POST around tests.
	 *
	 * @var array
	 */
	private $saved_post;

	/**
	 * Custom AJAX die handler that throws WPDieException instead of exiting.
	 *
	 * @since 2.11.1
	 *
	 * @return callable
	 */
	public static function ajax_die_handler() {
		return static function ( $message = '' ) {
			throw new \WPDieException( (string) $message );
		};
	}

	/**
	 * Set up.
	 *
	 * @since 2.11.1
	 */
	public function set_up() {
		parent::set_up();
		$this->saved_request = $_REQUEST;
		$this->saved_post    = $_POST;
		\add_filter( 'wp_doing_ajax', '__return_true' );
		\add_filter( 'wp_die_ajax_handler', array( __CLASS__, 'ajax_die_handler' ) );
	}

	/**
	 * Tear down.
	 *
	 * @since 2.11.1
	 */
	public function tear_down() {
		\remove_filter( 'wp_die_ajax_handler', array( __CLASS__, 'ajax_die_handler' ) );
		\remove_filter( 'wp_doing_ajax', '__return_true' );
		$_REQUEST = $this->saved_request;
		$_POST    = $this->saved_post;
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Create an attachment.
	 *
	 * @since 2.11.1
	 *
	 * @param int    $author_id Author user id.
	 * @param int    $parent_id Parent post id.
	 * @param string $mime      MIME type.
	 * @return int
	 */
	private function create_attachment( $author_id, $parent_id = 0, $mime = 'image/jpeg' ) {
		return $this->factory->post->create(
			array(
				'post_type'      => 'attachment',
				'post_author'    => $author_id,
				'post_status'    => 'inherit',
				'post_mime_type' => $mime,
				'post_parent'    => $parent_id,
			)
		);
	}

	/**
	 * Mint a revert nonce for the current user.
	 *
	 * @since 2.11.1
	 *
	 * @param int $post_id Parent post id.
	 * @return void
	 */
	private function prime_revert_request( $post_id ) {
		$_REQUEST['_wpnonce'] = \wp_create_nonce( 'w3tc_imageservice_revert' );
		$_POST['_wpnonce']    = $_REQUEST['_wpnonce'];
		$_REQUEST['post_id']  = (string) $post_id;
		$_POST['post_id']     = (string) $post_id;
	}

	/**
	 * Dispatch ajax_revert and return the JSON payload.
	 *
	 * @since 2.11.1
	 *
	 * @return array
	 */
	private function dispatch_ajax_revert() {
		$plugin = new Extension_ImageService_Plugin_Admin();

		\ob_start();
		try {
			$plugin->ajax_revert();
			\ob_end_clean();
			$this->fail( 'Expected WPDieException from ajax_revert.' );
		} catch ( \WPDieException $e ) {
			$response = \json_decode( \ob_get_clean(), true );
			$this->assertIsArray( $response, 'ajax_revert must emit JSON.' );
			return $response;
		}
	}

	/**
	 * Mint the Revert All nonce for the current user.
	 *
	 * @since 2.11.1
	 *
	 * @return void
	 */
	private function prime_revert_all_request() {
		$_REQUEST['_wpnonce'] = \wp_create_nonce( 'w3tc_imageservice_submit' );
		$_POST['_wpnonce']    = $_REQUEST['_wpnonce'];
	}

	/**
	 * Dispatch ajax_revert_all and return the JSON payload.
	 *
	 * @since 2.11.1
	 *
	 * @return array
	 */
	private function dispatch_ajax_revert_all() {
		$plugin = new Extension_ImageService_Plugin_Admin();

		\ob_start();
		try {
			$plugin->ajax_revert_all();
			\ob_end_clean();
			$this->fail( 'Expected WPDieException from ajax_revert_all.' );
		} catch ( \WPDieException $e ) {
			$response = \json_decode( \ob_get_clean(), true );
			$this->assertIsArray( $response, 'ajax_revert_all must emit JSON.' );
			return $response;
		}
	}

	/**
	 * Store Image Converter ids on a parent the author can edit.
	 *
	 * @since 2.11.1
	 *
	 * @param int   $parent_id Parent post id.
	 * @param array $children  post_children map.
	 * @param int   $child_id  Legacy post_child id. 0 omits the key.
	 * @return void
	 */
	private function store_service_meta( $parent_id, array $children, $child_id = 0 ) {
		$meta = array(
			'post_children' => $children,
		);
		if ( $child_id ) {
			$meta['post_child'] = $child_id;
		}

		\update_post_meta( $parent_id, 'w3tc_imageservice', $meta );
	}

	/**
	 * Assert each post id still exists.
	 *
	 * @since 2.11.1
	 *
	 * @param int[] $post_ids Post ids.
	 * @return void
	 */
	private function assert_posts_remain( array $post_ids ) {
		foreach ( $post_ids as $post_id ) {
			$this->assertInstanceOf( \WP_Post::class, \get_post( $post_id ) );
		}
	}

	/**
	 * An author who can edit their own post cannot delete another user's
	 * attachment, a non-attachment, or their own attachment that is not a
	 * child of that post.
	 *
	 * @since 2.11.1
	 */
	public function test_ajax_revert_keeps_unrelated_posts() {
		$author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$other_id  = $this->factory->user->create( array( 'role' => 'author' ) );
		$parent_id = $this->factory->post->create(
			array(
				'post_author' => $author_id,
				'post_status' => 'publish',
			)
		);

		$foreign_attachment = $this->create_attachment( $other_id );
		$legacy_attachment  = $this->create_attachment( $other_id, 0, 'image/webp' );
		$own_unrelated      = $this->create_attachment( $author_id );
		$foreign_post       = $this->factory->post->create(
			array(
				'post_author' => $other_id,
				'post_status' => 'publish',
			)
		);

		$this->store_service_meta(
			$parent_id,
			array(
				'webp'  => $foreign_attachment,
				'avif'  => $own_unrelated,
				'jpeg'  => $foreign_post,
				'array' => array( 'nope' ),
			),
			$legacy_attachment
		);

		wp_set_current_user( $author_id );
		$this->prime_revert_request( $parent_id );

		$response = $this->dispatch_ajax_revert();

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'No converted images could be reverted.', $response['data']['error'] );
		$meta = \get_post_meta( $parent_id, 'w3tc_imageservice', true );
		$this->assertSame( $foreign_attachment, (int) $meta['post_children']['webp'] );
		$this->assertSame( $legacy_attachment, (int) $meta['post_child'] );
		$this->assert_posts_remain(
			array(
				$parent_id,
				$foreign_attachment,
				$legacy_attachment,
				$own_unrelated,
				$foreign_post,
			)
		);
	}

	/**
	 * A revert that removes one child and skips another is not reported as complete.
	 *
	 * @since 2.11.1
	 */
	public function test_ajax_revert_partial_mix_is_not_full_success() {
		$author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$other_id  = $this->factory->user->create( array( 'role' => 'author' ) );
		$parent_id = $this->create_attachment( $author_id );
		$child_id  = $this->create_attachment( $author_id, $parent_id, 'image/webp' );
		$foreign   = $this->create_attachment( $other_id );

		\update_post_meta(
			$parent_id,
			'w3tc_imageservice',
			array(
				'post_children'   => array(
					'webp' => $child_id,
					'avif' => $foreign,
				),
				'downloads'       => array(
					'webp' => array( 'x-filesize-out' => 10 ),
					'avif' => array( 'x-filesize-out' => 20 ),
				),
				'jobs_status'     => array(
					'webp' => 'converted',
					'avif' => 'converted',
				),
				'processing_jobs' => array(
					'webp' => array( 'job_id' => 'webp-job' ),
					'avif' => array( 'job_id' => 'avif-job' ),
				),
			)
		);

		wp_set_current_user( $author_id );
		$this->prime_revert_request( $parent_id );

		$response = $this->dispatch_ajax_revert();

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Some converted images could not be reverted.', $response['data']['error'] );
		$this->assertSame( array( 'avif' ), $response['data']['remaining_formats'] );
		$this->assertNull( \get_post( $child_id ) );
		$this->assertInstanceOf( \WP_Post::class, \get_post( $foreign ) );

		$meta = \get_post_meta( $parent_id, 'w3tc_imageservice', true );
		$this->assertSame( $foreign, (int) $meta['post_children']['avif'] );
		$this->assertArrayNotHasKey( 'webp', $meta['post_children'] );
		$this->assertArrayNotHasKey( 'webp', $meta['downloads'] );
		$this->assertArrayNotHasKey( 'webp', $meta['jobs_status'] );
		$this->assertArrayNotHasKey( 'webp', $meta['processing_jobs'] );
		$this->assertSame( 20, $meta['downloads']['avif']['x-filesize-out'] );
		$this->assertSame( 'converted', $meta['jobs_status']['avif'] );
		$this->assertSame( 'avif-job', $meta['processing_jobs']['avif']['job_id'] );
	}

	/**
	 * An invalid legacy id is dropped, along with its format record.
	 *
	 * @since 2.11.1
	 */
	public function test_ajax_revert_drops_format_record_for_invalid_legacy_id() {
		$author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$other_id  = $this->factory->user->create( array( 'role' => 'author' ) );
		$parent_id = $this->create_attachment( $author_id );
		$foreign   = $this->create_attachment( $other_id );

		\update_post_meta(
			$parent_id,
			'w3tc_imageservice',
			array(
				'post_child'    => 0,
				'post_children' => array(
					'avif' => $foreign,
				),
				'downloads'     => array(
					'webp' => array( 'x-filesize-out' => 10 ),
					'avif' => array( 'x-filesize-out' => 20 ),
				),
			)
		);

		\wp_set_current_user( $author_id );
		$this->prime_revert_request( $parent_id );

		$response = $this->dispatch_ajax_revert();

		$this->assertFalse( $response['success'] );
		$meta = \get_post_meta( $parent_id, 'w3tc_imageservice', true );
		$this->assertArrayNotHasKey( 'post_child', $meta );
		$this->assertArrayNotHasKey( 'webp', $meta['downloads'] );
		$this->assertSame( 20, $meta['downloads']['avif']['x-filesize-out'] );
		$this->assertSame( $foreign, (int) $meta['post_children']['avif'] );
		$this->assertInstanceOf( \WP_Post::class, \get_post( $foreign ) );
	}

	/**
	 * The format list names every child that stayed, with WebP before AVIF.
	 *
	 * @since 2.11.1
	 */
	public function test_ajax_revert_partial_mix_lists_remaining_webp_before_avif() {
		$author_id    = $this->factory->user->create( array( 'role' => 'author' ) );
		$other_id     = $this->factory->user->create( array( 'role' => 'author' ) );
		$parent_id    = $this->create_attachment( $author_id );
		$legacy_id    = $this->create_attachment( $author_id, $parent_id, 'image/webp' );
		$foreign_webp = $this->create_attachment( $other_id );
		$foreign_avif = $this->create_attachment( $other_id );

		$this->store_service_meta(
			$parent_id,
			array(
				'avif' => $foreign_avif,
				'webp' => $foreign_webp,
			),
			$legacy_id
		);

		\wp_set_current_user( $author_id );
		$this->prime_revert_request( $parent_id );

		$response = $this->dispatch_ajax_revert();

		$this->assertFalse( $response['success'] );
		$this->assertSame( array( 'webp', 'avif' ), $response['data']['remaining_formats'] );
		$this->assertNull( \get_post( $legacy_id ) );
		$this->assertInstanceOf( \WP_Post::class, \get_post( $foreign_webp ) );
		$this->assertInstanceOf( \WP_Post::class, \get_post( $foreign_avif ) );
	}

	/**
	 * Media Library bulk revert follows the same child rules.
	 *
	 * @since 2.11.1
	 */
	public function test_bulk_revert_keeps_unrelated_attachments() {
		$author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$other_id  = $this->factory->user->create( array( 'role' => 'author' ) );
		$parent_id = $this->create_attachment( $author_id );

		$foreign_attachment = $this->create_attachment( $other_id );
		$legacy_attachment  = $this->create_attachment( $other_id, 0, 'image/webp' );
		$own_unrelated      = $this->create_attachment( $author_id );

		$this->store_service_meta(
			$parent_id,
			array(
				'webp' => $foreign_attachment,
				'avif' => $own_unrelated,
			),
			$legacy_attachment
		);

		wp_set_current_user( $author_id );

		$plugin   = new Extension_ImageService_Plugin_Admin();
		$location = $plugin->handle_bulk_actions(
			'https://example.test/wp-admin/upload.php',
			'w3tc_imageservice_revert',
			array( $parent_id )
		);

		$this->assertStringNotContainsString( 'w3tc_imageservice_reverted', $location );
		$this->assert_posts_remain(
			array(
				$parent_id,
				$foreign_attachment,
				$legacy_attachment,
				$own_unrelated,
			)
		);
	}

	/**
	 * Revert still removes converted children of a parent the user can delete.
	 *
	 * @since 2.11.1
	 */
	public function test_ajax_revert_deletes_child_attachments() {
		$author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$parent_id = $this->create_attachment( $author_id );
		$webp_id   = $this->create_attachment( $author_id, $parent_id, 'image/webp' );
		$avif_id   = $this->create_attachment( $author_id, $parent_id, 'image/avif' );

		$this->store_service_meta(
			$parent_id,
			array(
				'webp' => $webp_id,
				'avif' => $avif_id,
			),
			$webp_id
		);

		wp_set_current_user( $author_id );
		$this->prime_revert_request( $parent_id );

		$response = $this->dispatch_ajax_revert();

		$this->assertTrue( $response['success'] );
		$this->assertNull( \get_post( $webp_id ) );
		$this->assertNull( \get_post( $avif_id ) );
		$this->assertInstanceOf( \WP_Post::class, \get_post( $parent_id ) );
	}

	/**
	 * Bulk revert removes the same converted children.
	 *
	 * @since 2.11.1
	 */
	public function test_bulk_revert_deletes_child_attachments() {
		$author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$parent_id = $this->create_attachment( $author_id );
		$webp_id   = $this->create_attachment( $author_id, $parent_id, 'image/webp' );

		$this->store_service_meta(
			$parent_id,
			array(
				'webp' => (string) $webp_id,
			)
		);

		wp_set_current_user( $author_id );

		$plugin = new Extension_ImageService_Plugin_Admin();
		$plugin->handle_bulk_actions(
			'https://example.test/wp-admin/upload.php',
			'w3tc_imageservice_revert',
			array( $parent_id )
		);

		$this->assertNull( \get_post( $webp_id ) );
		$this->assertInstanceOf( \WP_Post::class, \get_post( $parent_id ) );
	}

	/**
	 * A never-converted JPEG in the same selection does not hide a completed revert.
	 *
	 * @since 2.11.1
	 */
	public function test_bulk_revert_ignores_unconverted_images_in_the_selection() {
		$author_id     = $this->factory->user->create( array( 'role' => 'author' ) );
		$parent_id     = $this->create_attachment( $author_id );
		$child_id      = $this->create_attachment( $author_id, $parent_id, 'image/webp' );
		$legacy_parent = $this->create_attachment( $author_id );
		$legacy_child  = $this->create_attachment( $author_id, $legacy_parent, 'image/webp' );
		$plain_id      = $this->create_attachment( $author_id );
		$stale_id      = $this->create_attachment( $author_id );

		$this->store_service_meta(
			$parent_id,
			array(
				'webp' => $child_id,
			)
		);
		\update_post_meta(
			$legacy_parent,
			'w3tc_imageservice',
			array(
				'post_child' => $legacy_child,
			)
		);
		\update_post_meta(
			$stale_id,
			'w3tc_imageservice',
			array(
				'status' => 'processing',
			)
		);

		\wp_set_current_user( $author_id );

		$plugin   = new Extension_ImageService_Plugin_Admin();
		$location = $plugin->handle_bulk_actions(
			'https://example.test/wp-admin/upload.php',
			'w3tc_imageservice_revert',
			array( $parent_id, $legacy_parent, $plain_id, $stale_id )
		);

		$this->assertStringContainsString( 'w3tc_imageservice_reverted', $location );
		$this->assertNull( \get_post( $child_id ) );
		$this->assertNull( \get_post( $legacy_child ) );
		$this->assertSame( '', \get_post_meta( $parent_id, 'w3tc_imageservice', true ) );
		$this->assertSame( '', \get_post_meta( $legacy_parent, 'w3tc_imageservice', true ) );
		$this->assertSame( '', \get_post_meta( $stale_id, 'w3tc_imageservice', true ) );
		$this->assertInstanceOf( \WP_Post::class, \get_post( $plain_id ) );
		$this->assertInstanceOf( \WP_Post::class, \get_post( $parent_id ) );
	}

	/**
	 * A selection with no stored child ids does not report a revert.
	 *
	 * @since 2.11.1
	 */
	public function test_bulk_revert_unconverted_only_has_no_notice() {
		$author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$plain_id  = $this->create_attachment( $author_id );
		$stale_id  = $this->create_attachment( $author_id );

		\update_post_meta(
			$stale_id,
			'w3tc_imageservice',
			array(
				'status' => 'processing',
			)
		);

		\wp_set_current_user( $author_id );

		$plugin   = new Extension_ImageService_Plugin_Admin();
		$location = $plugin->handle_bulk_actions(
			'https://example.test/wp-admin/upload.php',
			'w3tc_imageservice_revert',
			array( $plain_id, $stale_id )
		);

		$this->assertStringNotContainsString( 'w3tc_imageservice_reverted', $location );
		$this->assertSame( '', \get_post_meta( $stale_id, 'w3tc_imageservice', true ) );
		$this->assertInstanceOf( \WP_Post::class, \get_post( $plain_id ) );
	}

	/**
	 * An unconverted image does not turn a refused revert into a completed one.
	 *
	 * @since 2.11.1
	 */
	public function test_bulk_revert_refusal_still_hides_notice_when_unconverted_is_selected() {
		$author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$other_id  = $this->factory->user->create( array( 'role' => 'author' ) );
		$parent_id = $this->create_attachment( $author_id );
		$child_id  = $this->create_attachment( $author_id, $parent_id, 'image/webp' );
		$refused   = $this->create_attachment( $author_id );
		$foreign   = $this->create_attachment( $other_id );
		$plain_id  = $this->create_attachment( $author_id );

		$this->store_service_meta(
			$parent_id,
			array(
				'webp' => $child_id,
			)
		);
		$this->store_service_meta(
			$refused,
			array(
				'webp' => $foreign,
			)
		);

		\wp_set_current_user( $author_id );

		$plugin   = new Extension_ImageService_Plugin_Admin();
		$location = $plugin->handle_bulk_actions(
			'https://example.test/wp-admin/upload.php',
			'w3tc_imageservice_revert',
			array( $parent_id, $refused, $plain_id )
		);

		$this->assertStringNotContainsString( 'w3tc_imageservice_reverted', $location );
		$this->assertNull( \get_post( $child_id ) );
		$this->assertInstanceOf( \WP_Post::class, \get_post( $foreign ) );
		$this->assertInstanceOf( \WP_Post::class, \get_post( $plain_id ) );
	}

	/**
	 * Force-delete of the parent removes its converted children and leaves
	 * every other stored id in place.
	 *
	 * @since 2.11.1
	 */
	public function test_cleanup_deletes_only_child_attachments() {
		$author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$other_id  = $this->factory->user->create( array( 'role' => 'author' ) );
		$parent_id = $this->create_attachment( $author_id );
		$child_id  = $this->create_attachment( $author_id, $parent_id, 'image/webp' );
		$foreign   = $this->create_attachment( $other_id );

		$this->store_service_meta(
			$parent_id,
			array(
				'webp' => $child_id,
				'avif' => $foreign,
			)
		);

		wp_set_current_user( $author_id );

		$plugin = new Extension_ImageService_Plugin_Admin();
		$plugin->cleanup_optimizations( null, \get_post( $parent_id ), true );

		$this->assertNull( \get_post( $child_id ) );
		$this->assertInstanceOf( \WP_Post::class, \get_post( $foreign ) );
		$this->assertInstanceOf( \WP_Post::class, \get_post( $parent_id ) );
	}

	/**
	 * Invoke private remove_optimization_format().
	 *
	 * @since 2.11.1
	 *
	 * @param int    $post_id    Parent post id.
	 * @param string $format_key Format key.
	 * @return void
	 */
	private function remove_format( $post_id, $format_key ) {
		$plugin = new Extension_ImageService_Plugin_Admin();
		$method = new \ReflectionMethod( $plugin, 'remove_optimization_format' );
		$method->setAccessible( true );
		$method->invoke( $plugin, $post_id, $format_key );
	}

	/**
	 * Removing one format leaves a legacy child of another format in place.
	 *
	 * @since 2.11.1
	 */
	public function test_single_format_removal_keeps_other_legacy_child() {
		$author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$parent_id = $this->create_attachment( $author_id );
		$webp_id   = $this->create_attachment( $author_id, $parent_id, 'image/webp' );
		$avif_id   = $this->create_attachment( $author_id, $parent_id, 'image/avif' );

		\update_post_meta(
			$parent_id,
			'w3tc_imageservice',
			array(
				'post_child'    => $webp_id,
				'post_children' => array(
					'avif' => $avif_id,
				),
			)
		);

		wp_set_current_user( $author_id );
		$this->remove_format( $parent_id, 'avif' );

		$this->assertNull( \get_post( $avif_id ) );
		$this->assertInstanceOf( \WP_Post::class, \get_post( $webp_id ) );

		$meta = \get_post_meta( $parent_id, 'w3tc_imageservice', true );
		$this->assertSame( $webp_id, (int) $meta['post_child'] );
		$this->assertArrayNotHasKey( 'avif', $meta['post_children'] );
	}

	/**
	 * A legacy id that names the same attachment as the mapped child is cleared
	 * with that mapping, so per-format records for the removed format are dropped.
	 *
	 * @since 2.11.1
	 */
	public function test_single_format_removal_clears_matching_legacy_child() {
		$author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$parent_id = $this->create_attachment( $author_id );
		$webp_id   = $this->create_attachment( $author_id, $parent_id, 'image/webp' );
		$avif_id   = $this->create_attachment( $author_id, $parent_id, 'image/avif' );

		\update_post_meta(
			$parent_id,
			'w3tc_imageservice',
			array(
				'post_child'      => (string) $webp_id,
				'post_children'   => array(
					'webp' => $webp_id,
					'avif' => $avif_id,
				),
				'downloads'       => array(
					'webp' => array( 'x-filesize-out' => 10 ),
					'avif' => array( 'x-filesize-out' => 20 ),
				),
				'jobs_status'     => array(
					'webp' => 'completed',
					'avif' => 'completed',
				),
				'processing_jobs' => array(
					'webp' => array( 'job' => 1 ),
					'avif' => array( 'job' => 2 ),
				),
			)
		);

		wp_set_current_user( $author_id );
		$this->remove_format( $parent_id, 'webp' );

		$this->assertNull( \get_post( $webp_id ) );
		$this->assertInstanceOf( \WP_Post::class, \get_post( $avif_id ) );

		$meta = \get_post_meta( $parent_id, 'w3tc_imageservice', true );
		$this->assertArrayNotHasKey( 'post_child', $meta );
		$this->assertArrayNotHasKey( 'webp', $meta['post_children'] );
		$this->assertSame( $avif_id, (int) $meta['post_children']['avif'] );
		$this->assertArrayNotHasKey( 'webp', $meta['downloads'] );
		$this->assertArrayNotHasKey( 'webp', $meta['jobs_status'] );
		$this->assertArrayNotHasKey( 'webp', $meta['processing_jobs'] );
		$this->assertSame( 20, $meta['downloads']['avif']['x-filesize-out'] );
		$this->assertSame( 'completed', $meta['jobs_status']['avif'] );
		$this->assertSame( 2, $meta['processing_jobs']['avif']['job'] );
	}

	/**
	 * A partial-revert row restores a convert action only when that format is enabled.
	 *
	 * @since 2.11.1
	 */
	public function test_partial_revert_row_restores_only_enabled_formats() {
		$js    = (string) \file_get_contents( dirname( __DIR__, 2 ) . '/Extension_ImageService_Plugin_Admin.js' );
		$start = \strpos( $js, 'function syncRowAfterPartialRevert' );
		$end   = \strpos( $js, 'function toggleButtons' );

		$this->assertNotFalse( $start );
		$this->assertNotFalse( $end );
		$this->assertGreaterThan( $start, $end );

		$body = \substr( $js, $start, $end - $start );

		$this->assertSame( 1, \preg_match_all( '/ensureConvertLink\(\s*\$itemTd,\s*"avif"/', $body ) );
		$this->assertSame( 1, \preg_match_all( '/ensureConvertLink\(\s*\$itemTd,\s*"webp"/', $body ) );
		$this->assertSame(
			1,
			\preg_match( '/isFormatEnabled\(\s*"avif"\s*\)[\s\S]{0,160}ensureConvertLink\(\s*\$itemTd,\s*"avif"/', $body )
		);
		$this->assertSame(
			1,
			\preg_match( '/isFormatEnabled\(\s*"webp"\s*\)[\s\S]{0,160}ensureConvertLink\(\s*\$itemTd,\s*"webp"/', $body )
		);
	}

	/**
	 * A shared legacy id that no longer names an attachment is dropped with its format records.
	 *
	 * @since 2.11.1
	 */
	public function test_single_format_removal_clears_matching_legacy_id_when_attachment_is_gone() {
		$author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$parent_id = $this->create_attachment( $author_id );

		\update_post_meta(
			$parent_id,
			'w3tc_imageservice',
			array(
				'post_child'      => '99999999',
				'post_children'   => array(
					'webp' => '99999999',
					'avif' => 0,
				),
				'downloads'       => array(
					'webp' => array( 'x-filesize-out' => 10 ),
					'avif' => array( 'x-filesize-out' => 20 ),
				),
				'jobs_status'     => array(
					'webp' => 'completed',
					'avif' => 'completed',
				),
				'processing_jobs' => array(
					'webp' => array( 'job' => 1 ),
					'avif' => array( 'job' => 2 ),
				),
			)
		);

		wp_set_current_user( $author_id );
		$this->remove_format( $parent_id, 'webp' );

		$meta = \get_post_meta( $parent_id, 'w3tc_imageservice', true );
		$this->assertArrayNotHasKey( 'post_child', $meta );
		$this->assertArrayNotHasKey( 'webp', $meta['post_children'] );
		$this->assertArrayNotHasKey( 'webp', $meta['downloads'] );
		$this->assertArrayNotHasKey( 'webp', $meta['jobs_status'] );
		$this->assertArrayNotHasKey( 'webp', $meta['processing_jobs'] );
		$this->assertSame( 20, $meta['downloads']['avif']['x-filesize-out'] );
		$this->assertSame( 'completed', $meta['jobs_status']['avif'] );
		$this->assertSame( 2, $meta['processing_jobs']['avif']['job'] );
	}

	/**
	 * Single-format removal does not delete an attachment that is not a child.
	 *
	 * @since 2.11.1
	 */
	public function test_single_format_removal_keeps_unrelated_attachment() {
		$author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$other_id  = $this->factory->user->create( array( 'role' => 'author' ) );
		$parent_id = $this->create_attachment( $author_id );
		$foreign   = $this->create_attachment( $other_id, 0, 'image/webp' );

		\update_post_meta(
			$parent_id,
			'w3tc_imageservice',
			array(
				'post_child'    => $foreign,
				'post_children' => array(
					'webp' => $foreign,
				),
				'downloads'     => array(
					'webp' => array( 'x-filesize-out' => 10 ),
				),
			)
		);

		wp_set_current_user( $author_id );
		$this->remove_format( $parent_id, 'webp' );

		$this->assertInstanceOf( \WP_Post::class, \get_post( $foreign ) );
		$meta = \get_post_meta( $parent_id, 'w3tc_imageservice', true );
		$this->assertSame( $foreign, (int) $meta['post_child'] );
		$this->assertSame( $foreign, (int) $meta['post_children']['webp'] );
		$this->assertSame( 10, $meta['downloads']['webp']['x-filesize-out'] );
	}

	/**
	 * A stored id that is already gone is dropped and the service record is cleared.
	 *
	 * @since 2.11.1
	 */
	public function test_revert_clears_record_when_child_is_already_gone() {
		$author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$parent_id = $this->create_attachment( $author_id );

		\update_post_meta(
			$parent_id,
			'w3tc_imageservice',
			array(
				'status'        => 'converted',
				'post_child'    => 99999999,
				'post_children' => array(
					'webp' => 99999999,
				),
			)
		);

		wp_set_current_user( $author_id );

		$plugin = new Extension_ImageService_Plugin_Admin();
		$this->assertTrue( $plugin->remove_optimizations( $parent_id ) );
		$this->assertSame( '', \get_post_meta( $parent_id, 'w3tc_imageservice', true ) );
		$this->assertInstanceOf( \WP_Post::class, \get_post( $parent_id ) );
	}

	/**
	 * Pickup replacement only treats an attachment of the parent as the
	 * existing converted file.
	 *
	 * @since 2.11.1
	 */
	public function test_converted_child_attachment_requires_parent() {
		$author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$other_id  = $this->factory->user->create( array( 'role' => 'author' ) );
		$parent_id = $this->create_attachment( $author_id );
		$child_id  = $this->create_attachment( $author_id, $parent_id, 'image/webp' );
		$foreign   = $this->create_attachment( $other_id );

		$child = Extension_ImageService_Plugin_Admin::converted_child_attachment( $parent_id, (string) $child_id );
		$this->assertInstanceOf( \WP_Post::class, $child );
		$this->assertSame( $child_id, $child->ID );
		$this->assertNull( Extension_ImageService_Plugin_Admin::converted_child_attachment( $parent_id, $foreign ) );
		$this->assertNull( Extension_ImageService_Plugin_Admin::converted_child_attachment( $parent_id, array( $child_id ) ) );
		$this->assertInstanceOf( \WP_Post::class, \get_post( $foreign ) );
		$this->assertInstanceOf( \WP_Post::class, \get_post( $child_id ) );
	}

	/**
	 * Mark an attachment as a file created by Image Converter.
	 *
	 * @since 2.11.1
	 *
	 * @param int $child_id Child attachment id.
	 * @return void
	 */
	private function mark_converted_file( $child_id ) {
		\update_post_meta(
			$child_id,
			'w3tc_imageservice',
			array(
				'is_converted_file' => true,
			)
		);
	}

	/**
	 * Create an attachment while no user is logged in.
	 *
	 * @since 2.11.1
	 *
	 * @param int    $parent_id Parent post id.
	 * @param string $mime      MIME type.
	 * @return int
	 */
	private function create_unassigned_attachment( $parent_id, $mime = 'image/webp' ) {
		\wp_set_current_user( 0 );
		$child_id = $this->create_attachment( 0, $parent_id, $mime );
		$this->assertSame( 0, (int) \get_post( $child_id )->post_author );

		return $child_id;
	}

	/**
	 * An author can revert a converted child that pickup stored with no author.
	 *
	 * @since 2.11.1
	 */
	public function test_ajax_revert_deletes_converted_child_with_no_author() {
		$author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$parent_id = $this->create_attachment( $author_id );
		$child_id  = $this->create_unassigned_attachment( $parent_id );
		$this->mark_converted_file( $child_id );

		\update_post_meta(
			$parent_id,
			'w3tc_imageservice',
			array(
				'post_children' => array(
					'webp' => $child_id,
				),
				'post_child'    => $child_id,
			)
		);

		\wp_set_current_user( $author_id );
		$this->prime_revert_request( $parent_id );

		$response = $this->dispatch_ajax_revert();

		$this->assertTrue( $response['success'] );
		$this->assertNull( \get_post( $child_id ) );
		$this->assertSame( '', \get_post_meta( $parent_id, 'w3tc_imageservice', true ) );
		$this->assertInstanceOf( \WP_Post::class, \get_post( $parent_id ) );
	}

	/**
	 * Bulk revert removes the same unassigned converted child.
	 *
	 * @since 2.11.1
	 */
	public function test_bulk_revert_deletes_converted_child_with_no_author() {
		$author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$parent_id = $this->create_attachment( $author_id );
		$child_id  = $this->create_unassigned_attachment( $parent_id );
		$this->mark_converted_file( $child_id );

		\update_post_meta(
			$parent_id,
			'w3tc_imageservice',
			array(
				'post_children' => array(
					'webp' => $child_id,
				),
			)
		);

		\wp_set_current_user( $author_id );

		$plugin   = new Extension_ImageService_Plugin_Admin();
		$location = $plugin->handle_bulk_actions(
			'https://example.test/wp-admin/upload.php',
			'w3tc_imageservice_revert',
			array( $parent_id )
		);

		$this->assertStringContainsString( 'w3tc_imageservice_reverted', $location );
		$this->assertNull( \get_post( $child_id ) );
		$this->assertSame( '', \get_post_meta( $parent_id, 'w3tc_imageservice', true ) );
	}

	/**
	 * A child with no author is kept when Image Converter did not create it.
	 *
	 * @since 2.11.1
	 */
	public function test_ajax_revert_keeps_unassigned_child_without_conversion_marker() {
		$author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$parent_id = $this->create_attachment( $author_id );
		$child_id  = $this->create_unassigned_attachment( $parent_id );

		\update_post_meta(
			$parent_id,
			'w3tc_imageservice',
			array(
				'post_child' => $child_id,
			)
		);

		\wp_set_current_user( $author_id );
		$this->prime_revert_request( $parent_id );

		$response = $this->dispatch_ajax_revert();

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'No converted images could be reverted.', $response['data']['error'] );
		$child = \get_post( $child_id );
		$this->assertInstanceOf( \WP_Post::class, $child );
		$this->assertSame( 0, (int) $child->post_author );
		$meta = \get_post_meta( $parent_id, 'w3tc_imageservice', true );
		$this->assertSame( $child_id, (int) $meta['post_child'] );
	}

	/**
	 * Revert does not assign a converted file that belongs to another parent.
	 *
	 * @since 2.11.1
	 */
	public function test_ajax_revert_does_not_claim_converted_child_of_another_parent() {
		$author_id    = $this->factory->user->create( array( 'role' => 'author' ) );
		$other_id     = $this->factory->user->create( array( 'role' => 'author' ) );
		$parent_id    = $this->create_attachment( $author_id );
		$other_parent = $this->create_attachment( $other_id );
		$child_id     = $this->create_unassigned_attachment( $other_parent );
		$this->mark_converted_file( $child_id );

		\update_post_meta(
			$parent_id,
			'w3tc_imageservice',
			array(
				'post_child' => $child_id,
			)
		);

		\wp_set_current_user( $author_id );
		$this->prime_revert_request( $parent_id );

		$response = $this->dispatch_ajax_revert();

		$this->assertFalse( $response['success'] );
		$child = \get_post( $child_id );
		$this->assertInstanceOf( \WP_Post::class, $child );
		$this->assertSame( 0, (int) $child->post_author );
		$this->assertSame( $other_parent, (int) $child->post_parent );
		$meta = \get_post_meta( $parent_id, 'w3tc_imageservice', true );
		$this->assertSame( $child_id, (int) $meta['post_child'] );
	}

	/**
	 * Logged-out insert keeps the parent attachment author on the child.
	 *
	 * @since 2.11.1
	 */
	public function test_logged_out_insert_keeps_parent_author() {
		$author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$parent_id = $this->create_attachment( $author_id );

		$this->assertSame( $author_id, Extension_ImageService_Plugin_Admin::converted_child_author( $parent_id ) );
		$this->assertSame( 0, Extension_ImageService_Plugin_Admin::converted_child_author( 0 ) );

		\wp_set_current_user( 0 );
		$child_id = \wp_insert_attachment(
			array(
				'post_mime_type' => 'image/webp',
				'post_title'     => 'converted',
				'post_status'    => 'inherit',
				'post_parent'    => $parent_id,
				'post_author'    => Extension_ImageService_Plugin_Admin::converted_child_author( $parent_id ),
			),
			false,
			$parent_id,
			false,
			false
		);

		$this->assertGreaterThan( 0, $child_id );
		$this->assertSame( $author_id, (int) \get_post( $child_id )->post_author );
	}

	/**
	 * A legacy record that only stores post_child is removed with that child.
	 *
	 * @since 2.11.1
	 */
	public function test_revert_clears_legacy_post_child_record() {
		$author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$parent_id = $this->create_attachment( $author_id );
		$child_id  = $this->create_attachment( $author_id, $parent_id, 'image/webp' );

		\update_post_meta(
			$parent_id,
			'w3tc_imageservice',
			array(
				'post_child' => $child_id,
			)
		);

		\wp_set_current_user( $author_id );

		$plugin = new Extension_ImageService_Plugin_Admin();
		$this->assertTrue( $plugin->remove_optimizations( $parent_id ) );
		$this->assertNull( \get_post( $child_id ) );
		$this->assertSame( '', \get_post_meta( $parent_id, 'w3tc_imageservice', true ) );
	}

	/**
	 * A legacy record that only stores a missing post_child is removed.
	 *
	 * @since 2.11.1
	 */
	public function test_revert_clears_legacy_post_child_when_id_is_missing() {
		$author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$parent_id = $this->create_attachment( $author_id );

		\update_post_meta(
			$parent_id,
			'w3tc_imageservice',
			array(
				'post_child' => 99999999,
			)
		);

		\wp_set_current_user( $author_id );

		$plugin = new Extension_ImageService_Plugin_Admin();
		$this->assertTrue( $plugin->remove_optimizations( $parent_id ) );
		$this->assertSame( '', \get_post_meta( $parent_id, 'w3tc_imageservice', true ) );
		$this->assertInstanceOf( \WP_Post::class, \get_post( $parent_id ) );
	}

	/**
	 * A record with no positive child id is not treated as a conversion.
	 *
	 * @since 2.11.1
	 */
	public function test_ajax_revert_rejects_records_without_a_stored_child_id() {
		$author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$parent_id = $this->create_attachment( $author_id );
		$meta      = array(
			'post_children' => array(
				'webp' => 0,
				'avif' => array( 'nope' ),
			),
			'post_child'    => 0,
		);

		\update_post_meta( $parent_id, 'w3tc_imageservice', $meta );
		\wp_set_current_user( $author_id );
		$this->prime_revert_request( $parent_id );

		$response = $this->dispatch_ajax_revert();

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'No converted images found to revert.', $response['data']['error'] );
		$this->assertSame( $meta, \get_post_meta( $parent_id, 'w3tc_imageservice', true ) );
	}

	/**
	 * Revert All reports success when every stored child is removed.
	 *
	 * A record with no stored child is cleaned up and is not a failure.
	 *
	 * @since 2.11.1
	 */
	public function test_ajax_revert_all_succeeds_when_stored_children_are_removed() {
		$admin_id  = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$parent_id = $this->create_attachment( $admin_id );
		$child_id  = $this->create_attachment( $admin_id, $parent_id, 'image/webp' );
		$stale_id  = $this->create_attachment( $admin_id );

		$this->store_service_meta(
			$parent_id,
			array(
				'webp' => $child_id,
			)
		);
		\update_post_meta(
			$stale_id,
			'w3tc_imageservice',
			array(
				'status' => 'processing',
			)
		);

		\wp_set_current_user( $admin_id );
		$this->prime_revert_all_request();

		$response = $this->dispatch_ajax_revert_all();

		$this->assertTrue( $response['success'] );
		$this->assertSame( 1, $response['data']['revert_count'] );
		$this->assertNull( \get_post( $child_id ) );
		$this->assertSame( '', \get_post_meta( $parent_id, 'w3tc_imageservice', true ) );
		$this->assertSame( '', \get_post_meta( $stale_id, 'w3tc_imageservice', true ) );
	}

	/**
	 * Revert All does not report success while a stored child remains.
	 *
	 * A record with no stored child is still cleaned up and is not counted
	 * as a failed removal.
	 *
	 * @since 2.11.1
	 */
	public function test_ajax_revert_all_reports_incomplete_child_removal() {
		$admin_id  = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$other_id  = $this->factory->user->create( array( 'role' => 'author' ) );
		$parent_id = $this->create_attachment( $admin_id );
		$child_id  = $this->create_attachment( $admin_id, $parent_id, 'image/webp' );
		$refused   = $this->create_attachment( $admin_id );
		$foreign   = $this->create_attachment( $other_id );
		$stale_id  = $this->create_attachment( $admin_id );

		$this->store_service_meta(
			$parent_id,
			array(
				'webp' => $child_id,
			)
		);
		$this->store_service_meta(
			$refused,
			array(
				'webp' => $foreign,
			)
		);
		\update_post_meta(
			$stale_id,
			'w3tc_imageservice',
			array(
				'status' => 'processing',
			)
		);

		\wp_set_current_user( $admin_id );
		$this->prime_revert_all_request();

		$response = $this->dispatch_ajax_revert_all();

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Some converted images could not be reverted.', $response['data']['error'] );
		$this->assertSame( 1, $response['data']['revert_count'] );
		$this->assertNull( \get_post( $child_id ) );
		$this->assertInstanceOf( \WP_Post::class, \get_post( $foreign ) );
		$this->assertSame( '', \get_post_meta( $stale_id, 'w3tc_imageservice', true ) );
		$this->assertSame( $foreign, (int) \get_post_meta( $refused, 'w3tc_imageservice', true )['post_children']['webp'] );
	}

	/**
	 * Records with no stored child do not make Revert All fail.
	 *
	 * @since 2.11.1
	 */
	public function test_ajax_revert_all_ignores_records_without_stored_children() {
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$stale_id = $this->create_attachment( $admin_id );

		\update_post_meta(
			$stale_id,
			'w3tc_imageservice',
			array(
				'status' => 'processing',
			)
		);

		\wp_set_current_user( $admin_id );
		$this->prime_revert_all_request();

		$response = $this->dispatch_ajax_revert_all();

		$this->assertTrue( $response['success'] );
		$this->assertSame( 0, $response['data']['revert_count'] );
		$this->assertSame( '', \get_post_meta( $stale_id, 'w3tc_imageservice', true ) );
	}
}
