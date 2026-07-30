<?php
/**
 * File: class-w3tc-purge-capability-test.php
 *
 * Filterable purge capability helpers: default manage_options; settings and
 * other non-purge admin actions stay floored at manage_options.
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      X.X.X
 */

declare( strict_types = 1 );

use W3TC\Util_Capability;

/**
 * Class: W3tc_Purge_Capability_Test
 *
 * @since X.X.X
 */
class W3tc_Purge_Capability_Test extends WP_UnitTestCase {

	/**
	 * Tear down filters and user.
	 *
	 * @since X.X.X
	 */
	public function tear_down() {
		\remove_all_filters( 'w3tc_capability_flush_all' );
		\remove_all_filters( 'w3tc_capability_flush_post' );
		\remove_all_filters( 'w3tc_capability_admin_bar' );
		\remove_all_filters( 'w3tc_capability_row_action_w3tc_flush_post' );
		\remove_all_filters( 'w3tc_capability_favorite_action_flush_all' );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Default (no filters): only manage_options can purge.
	 *
	 * @since X.X.X
	 */
	public function test_default_admin_can_purge_editor_cannot() {
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );
		$this->assertTrue( Util_Capability::can_flush_all() );
		$this->assertTrue( Util_Capability::can_flush_post() );
		$this->assertSame( 'manage_options', Util_Capability::flush_all_capability() );

		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );
		$this->assertFalse( Util_Capability::can_flush_all() );
		$this->assertFalse( Util_Capability::can_flush_post() );
		$this->assertFalse( Util_Capability::user_can_purge_anything() );
	}

	/**
	 * Filter flush_all to edit_posts: editor can purge all, not implied for post-only separation.
	 *
	 * @since X.X.X
	 */
	public function test_filter_flush_all_grants_editor() {
		\add_filter( 'w3tc_capability_flush_all', static function () {
			return 'edit_posts';
		} );

		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );

		$this->assertTrue( Util_Capability::can_flush_all() );
		$this->assertTrue( Util_Capability::can_execute_purge( 'w3tc_flush_all' ) );
		// flush_post still defaults via admin_bar base manage_options unless filtered.
		$this->assertFalse( Util_Capability::can_flush_post() );
		$this->assertFalse( Util_Capability::can_execute_purge( 'w3tc_flush_post' ) );
	}

	/**
	 * Filter flush_post only: editor can purge posts they can edit.
	 *
	 * @since X.X.X
	 */
	public function test_filter_flush_post_requires_edit_post() {
		\add_filter( 'w3tc_capability_flush_post', static function () {
			return 'edit_posts';
		} );

		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		$author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$post_id   = $this->factory->post->create(
			array(
				'post_author' => $editor_id,
				'post_status' => 'publish',
			)
		);

		wp_set_current_user( $editor_id );
		$this->assertTrue( Util_Capability::can_flush_post() );
		$this->assertTrue( Util_Capability::can_flush_post_id( $post_id ) );
		$this->assertFalse( Util_Capability::can_flush_all() );

		$_GET['post_id'] = (string) $post_id;
		$this->assertTrue( Util_Capability::can_execute_purge( 'w3tc_flush_post' ) );
		unset( $_GET['post_id'] );

		// Author cannot edit editor's post.
		wp_set_current_user( $author_id );
		\add_filter( 'w3tc_capability_flush_post', static function () {
			return 'edit_posts';
		} );
		$this->assertTrue( Util_Capability::can_flush_post() );
		$this->assertFalse( Util_Capability::can_flush_post_id( $post_id ) );
	}

	/**
	 * Low-cap filter is honored (ill-advised but possible).
	 *
	 * @since X.X.X
	 */
	public function test_filter_read_honored_for_flush_all() {
		\add_filter( 'w3tc_capability_flush_all', static function () {
			return 'read';
		} );

		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$this->assertTrue( Util_Capability::can_flush_all() );
		$this->assertTrue( Util_Capability::can_execute_purge( 'w3tc_flush_all' ) );
	}

	/**
	 * Purge-current-page with a post_id uses the object gate (UI and action align).
	 *
	 * @since X.X.X
	 */
	public function test_flush_post_with_post_id_requires_edit_post() {
		\add_filter( 'w3tc_capability_flush_post', static function () {
			return 'read';
		} );

		$admin_id      = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$post_id       = $this->factory->post->create(
			array(
				'post_author' => $admin_id,
				'post_status' => 'publish',
			)
		);

		wp_set_current_user( $subscriber_id );
		$this->assertTrue( Util_Capability::can_flush_post() );
		$this->assertFalse( Util_Capability::can_flush_post_id( $post_id ) );

		$_GET['post_id'] = (string) $post_id;
		$this->assertFalse( Util_Capability::can_execute_purge( 'w3tc_flush_post' ) );
		unset( $_GET['post_id'] );
	}

	/**
	 * Invalid filter returns fall back to manage_options.
	 *
	 * @since X.X.X
	 */
	public function test_invalid_filter_returns_manage_options() {
		\add_filter( 'w3tc_capability_flush_all', static function () {
			return '';
		} );
		$this->assertSame( 'manage_options', Util_Capability::flush_all_capability() );

		\remove_all_filters( 'w3tc_capability_flush_all' );
		\add_filter( 'w3tc_capability_flush_all', static function () {
			return true;
		} );
		$this->assertSame( 'manage_options', Util_Capability::flush_all_capability() );
	}

	/**
	 * Subscriber without filter cannot purge even with a valid mental model of nonce.
	 *
	 * @since X.X.X
	 */
	public function test_subscriber_without_filter_cannot_purge() {
		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$this->assertFalse( Util_Capability::can_flush_all() );
		$this->assertFalse( Util_Capability::can_execute_purge( 'w3tc_flush_all' ) );
		$this->assertFalse( \current_user_can( 'manage_options' ) );
	}

	/**
	 * Legacy admin_bar filter grants both purge caps (historical agency pattern).
	 *
	 * @since X.X.X
	 */
	public function test_legacy_admin_bar_filter_grants_purge() {
		\add_filter( 'w3tc_capability_admin_bar', static function () {
			return 'edit_posts';
		} );

		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );

		$this->assertTrue( Util_Capability::can_flush_all() );
		$this->assertTrue( Util_Capability::can_flush_post() );
	}

	/**
	 * Flush-all-only must still resolve an admin-bar parent cap the editor can meet.
	 *
	 * @since X.X.X
	 */
	public function test_admin_bar_parent_cap_with_flush_all_only() {
		\add_filter( 'w3tc_capability_flush_all', static function () {
			return 'edit_posts';
		} );

		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );

		$this->assertTrue( Util_Capability::can_flush_all() );
		$this->assertFalse( Util_Capability::can_flush_post() );
		$this->assertSame( 'edit_posts', Util_Capability::admin_bar_parent_capability() );
		$this->assertTrue( \current_user_can( Util_Capability::admin_bar_parent_capability() ) );
	}

	/**
	 * Flush-post-only parent cap uses flush-post.
	 *
	 * @since X.X.X
	 */
	public function test_admin_bar_parent_cap_with_flush_post_only() {
		\add_filter( 'w3tc_capability_flush_post', static function () {
			return 'edit_posts';
		} );

		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );

		$this->assertSame( 'edit_posts', Util_Capability::admin_bar_parent_capability() );
		$this->assertTrue( \current_user_can( Util_Capability::admin_bar_parent_capability() ) );
	}

	/**
	 * Non-purge actions stay outside the allowlist.
	 *
	 * @since X.X.X
	 */
	public function test_non_purge_actions_not_allowlisted() {
		$this->assertFalse( Util_Capability::is_purge_action( 'w3tc_flush_pgcache' ) );
		$this->assertFalse( Util_Capability::is_purge_action( 'w3tc_save_options' ) );
		$this->assertFalse( Util_Capability::can_execute_purge( 'w3tc_flush_pgcache' ) );
		$this->assertTrue( Util_Capability::is_purge_action( 'w3tc_flush_all' ) );
		$this->assertTrue( Util_Capability::is_purge_action( 'w3tc_flush_post' ) );
		$this->assertTrue( Util_Capability::is_purge_action( 'w3tc_flush_current_page' ) );
	}

	/**
	 * Editor with purge filters still lacks manage_options (settings stay sealed).
	 *
	 * @since X.X.X
	 */
	public function test_purge_filter_does_not_grant_manage_options() {
		\add_filter( 'w3tc_capability_flush_all', static function () {
			return 'edit_posts';
		} );
		\add_filter( 'w3tc_capability_flush_post', static function () {
			return 'edit_posts';
		} );

		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );

		$this->assertTrue( Util_Capability::user_can_purge_anything() );
		$this->assertFalse( \current_user_can( 'manage_options' ) );
	}
}
