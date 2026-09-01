<?php
/**
 * File: class-w3tc-imageservice-submit-capability-test.php
 *
 * ImageService submit is an administrative conversion action. ajax_submit
 * and Media Library bulk convert default to manage_options; edit_post
 * remains a secondary object gate. Auto-convert on upload is unchanged.
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.10.6
 */

declare( strict_types = 1 );

use W3TC\Extension_ImageService_Plugin_Admin;

/**
 * Class: W3tc_Imageservice_Submit_Capability_Test
 *
 * @since 2.10.6
 */
class W3tc_Imageservice_Submit_Capability_Test extends WP_UnitTestCase {

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
	 * @since 2.10.6
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
	 * @since 2.10.6
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
	 * @since 2.10.6
	 */
	public function tear_down() {
		\remove_filter( 'wp_die_ajax_handler', array( __CLASS__, 'ajax_die_handler' ) );
		\remove_filter( 'wp_doing_ajax', '__return_true' );
		\remove_all_filters( 'w3tc_imageservice_submit_capability' );
		\remove_all_filters( 'get_attached_file' );
		$_REQUEST = $this->saved_request;
		$_POST    = $this->saved_post;
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Dispatch ajax_submit and return the JSON payload.
	 *
	 * @since 2.10.6
	 *
	 * @return array
	 */
	private function dispatch_ajax_submit() {
		$plugin = new Extension_ImageService_Plugin_Admin();

		\ob_start();
		try {
			$plugin->ajax_submit();
			\ob_end_clean();
			$this->fail( 'Expected WPDieException from ajax_submit.' );
		} catch ( \WPDieException $e ) {
			$response = \json_decode( \ob_get_clean(), true );
			$this->assertIsArray( $response, 'ajax_submit must emit JSON.' );
			return $response;
		}
	}

	/**
	 * Mint a valid submit nonce and optional post id.
	 *
	 * @since 2.10.6
	 *
	 * @param int $post_id Attachment ID; 0 omits the field.
	 * @return void
	 */
	private function prime_submit_request( $post_id = 0 ) {
		$_REQUEST['_wpnonce'] = \wp_create_nonce( 'w3tc_imageservice_submit' );
		$_POST['_wpnonce']    = $_REQUEST['_wpnonce'];
		if ( $post_id ) {
			$_REQUEST['post_id'] = (string) $post_id;
			$_POST['post_id']    = (string) $post_id;
		} else {
			unset( $_REQUEST['post_id'], $_POST['post_id'] );
		}
	}

	/**
	 * Editors with a valid nonce and an attachment they can edit still
	 * cannot submit; the gate runs before get_attached_file().
	 *
	 * @since 2.10.6
	 */
	public function test_ajax_submit_rejects_editor_before_side_effects() {
		$editor_id     = $this->factory->user->create( array( 'role' => 'editor' ) );
		$attachment_id = $this->factory->post->create(
			array(
				'post_type'   => 'attachment',
				'post_author' => $editor_id,
				'post_status' => 'inherit',
			)
		);
		wp_set_current_user( $editor_id );
		$this->prime_submit_request( $attachment_id );

		$touched = false;
		\add_filter(
			'get_attached_file',
			static function ( $file ) use ( &$touched ) {
				$touched = true;
				return $file;
			}
		);

		$response = $this->dispatch_ajax_submit();

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Insufficient permissions.', $response['data']['error'] );
		$this->assertFalse( $touched, 'Denied submit must not reach get_attached_file().' );
	}

	/**
	 * Subscribers with a leaked nonce cannot submit.
	 *
	 * @since 2.10.6
	 */
	public function test_ajax_submit_rejects_subscriber() {
		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );
		$this->prime_submit_request( 1 );

		$response = $this->dispatch_ajax_submit();

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Insufficient permissions.', $response['data']['error'] );
	}

	/**
	 * An administrator passes the capability gate; missing post_id still
	 * fails validation before filesystem or API work.
	 *
	 * @since 2.10.6
	 */
	public function test_ajax_submit_admin_missing_post_id_is_validation() {
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );
		$this->prime_submit_request( 0 );

		$touched = false;
		\add_filter(
			'get_attached_file',
			static function ( $file ) use ( &$touched ) {
				$touched = true;
				return $file;
			}
		);

		$response = $this->dispatch_ajax_submit();

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Missing input post id.', $response['data']['error'] );
		$this->assertFalse( $touched, 'Validation failure must not reach get_attached_file().' );
	}

	/**
	 * Filter restores Author/Editor submit without lowering the default.
	 *
	 * @since 2.10.6
	 */
	public function test_ajax_submit_filter_allows_editor_past_admin_gate() {
		\add_filter(
			'w3tc_imageservice_submit_capability',
			static function () {
				return 'upload_files';
			}
		);

		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );
		$this->prime_submit_request( 0 );

		$response = $this->dispatch_ajax_submit();

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Missing input post id.', $response['data']['error'] );
	}

	/**
	 * A non-string filter result is ignored; the gate stays manage_options.
	 *
	 * @since 2.10.6
	 */
	public function test_ajax_submit_rejects_non_string_filter_capability() {
		\add_filter(
			'w3tc_imageservice_submit_capability',
			static function () {
				return array( 'read' );
			}
		);

		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );
		$this->prime_submit_request( 0 );

		$response = $this->dispatch_ajax_submit();

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Insufficient permissions.', $response['data']['error'] );
	}

	/**
	 * Media Library bulk convert uses the same submit capability, not
	 * edit_post alone.
	 *
	 * @since 2.10.6
	 */
	public function test_bulk_convert_rejects_editor() {
		$editor_id     = $this->factory->user->create( array( 'role' => 'editor' ) );
		$attachment_id = $this->factory->post->create(
			array(
				'post_type'   => 'attachment',
				'post_author' => $editor_id,
				'post_status' => 'inherit',
			)
		);
		wp_set_current_user( $editor_id );

		$plugin   = new Extension_ImageService_Plugin_Admin();
		$location = $plugin->handle_bulk_actions(
			'https://example.test/wp-admin/upload.php',
			'w3tc_imageservice_convert',
			array( $attachment_id )
		);

		$this->assertStringNotContainsString( 'w3tc_imageservice_submitted', $location );
	}

	/**
	 * Format-specific Convert links must use the submit gate, not edit_post
	 * alone — leftover of the primary Convert control fix.
	 *
	 * @since 2.10.6
	 */
	public function test_convert_format_controls_use_submit_gate() {
		$source = \file_get_contents( W3TC_DIR . '/Extension_ImageService_Plugin_Admin.php' );

		$this->assertSame(
			1,
			preg_match_all( '/\\$avif_span_class\\s+=\\s+\\$can_submit/', $source )
		);
		$this->assertSame(
			1,
			preg_match_all( '/\\$webp_span_class\\s+=\\s+\\$can_submit/', $source )
		);
		$this->assertSame(
			0,
			preg_match_all( '/\\$avif_span_class\\s+=\\s+\\$can_edit/', $source )
		);
		$this->assertSame(
			0,
			preg_match_all( '/\\$webp_span_class\\s+=\\s+\\$can_edit/', $source )
		);
	}
}
