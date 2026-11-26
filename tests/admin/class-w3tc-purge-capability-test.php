<?php
/**
 * File: class-w3tc-purge-capability-test.php
 *
 * Filterable purge capability helpers: default manage_options; settings and
 * other non-purge admin actions stay floored at manage_options.
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.10.4
 */

declare( strict_types = 1 );

use W3TC\Util_Capability;

/**
 * Class: W3tc_Purge_Capability_Test
 *
 * @since 2.10.4
 */
class W3tc_Purge_Capability_Test extends WP_UnitTestCase {

	/**
	 * Tear down filters and user.
	 *
	 * @since 2.10.4
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
	 * @since 2.10.4
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
	 * Filter flush_all to edit_posts cannot lower the administrative floor.
	 *
	 * @since 2.11.1
	 */
	public function test_filter_flush_all_does_not_grant_editor() {
		\add_filter( 'w3tc_capability_flush_all', static function () {
			return 'edit_posts';
		} );

		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );

		$this->assertFalse( Util_Capability::can_flush_all() );
		$this->assertFalse( Util_Capability::can_execute_purge( 'w3tc_flush_all' ) );
		// flush_post still defaults via admin_bar base manage_options unless filtered.
		$this->assertFalse( Util_Capability::can_flush_post() );
		$this->assertFalse( Util_Capability::can_execute_purge( 'w3tc_flush_post' ) );
	}

	/**
	 * Filter flush_post cannot lower the administrative floor.
	 *
	 * @since 2.11.1
	 */
	public function test_filter_flush_post_does_not_grant_editor() {
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
		$this->assertFalse( Util_Capability::can_flush_post() );
		$this->assertFalse( Util_Capability::can_flush_post_id( $post_id ) );
		$this->assertFalse( Util_Capability::can_flush_all() );

		$_GET['post_id'] = (string) $post_id;
		$this->assertFalse( Util_Capability::can_execute_purge( 'w3tc_flush_post' ) );
		unset( $_GET['post_id'] );

		// Author cannot edit editor's post.
		wp_set_current_user( $author_id );
		\add_filter( 'w3tc_capability_flush_post', static function () {
			return 'edit_posts';
		} );
		$this->assertFalse( Util_Capability::can_flush_post() );
		$this->assertFalse( Util_Capability::can_flush_post_id( $post_id ) );
	}

	/**
	 * A read filter cannot grant a purge capability.
	 *
	 * @since 2.11.1
	 */
	public function test_filter_read_does_not_grant_flush_all() {
		\add_filter( 'w3tc_capability_flush_all', static function () {
			return 'read';
		} );

		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$this->assertFalse( Util_Capability::can_flush_all() );
		$this->assertFalse( Util_Capability::can_execute_purge( 'w3tc_flush_all' ) );

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		\wp_set_current_user( $admin_id );
		$this->assertTrue( Util_Capability::can_flush_all() );
		$this->assertTrue( Util_Capability::can_execute_purge( 'w3tc_flush_all' ) );
	}

	/**
	 * Purge-current-page with a post_id uses the object gate (UI and action align).
	 *
	 * @since 2.10.4
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
		$this->assertFalse( Util_Capability::can_flush_post() );
		$this->assertFalse( Util_Capability::can_flush_post_id( $post_id ) );

		$_GET['post_id'] = (string) $post_id;
		$this->assertFalse( Util_Capability::can_execute_purge( 'w3tc_flush_post' ) );
		unset( $_GET['post_id'] );
	}

	/**
	 * A filtered post capability cannot lower the administrative floor.
	 *
	 * @since 2.11.1
	 */
	public function test_flush_post_without_post_id_still_requires_edit_post() {
		\add_filter( 'w3tc_capability_flush_post', static function () {
			return 'edit_posts';
		} );

		$admin_id  = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$post_id   = $this->factory->post->create(
			array(
				'post_author' => $admin_id,
				'post_status' => 'publish',
			)
		);

		wp_set_current_user( $author_id );
		$this->assertFalse( Util_Capability::can_flush_post() );
		$this->assertFalse( Util_Capability::can_flush_post_id( $post_id ) );

		$this->assertFalse( Util_Capability::can_execute_purge( 'w3tc_flush_post' ) );

		$_GET['p'] = (string) $post_id;
		$this->assertFalse( Util_Capability::can_execute_purge( 'w3tc_flush_post' ) );
		unset( $_GET['p'] );

		$own_post_id = $this->factory->post->create(
			array(
				'post_author' => $author_id,
				'post_status' => 'publish',
			)
		);

		$_GET['p'] = (string) $own_post_id;
		$this->assertFalse( Util_Capability::can_execute_purge( 'w3tc_flush_post' ) );
		unset( $_GET['p'] );
	}

	/**
	 * Invalid filter returns fall back to manage_options.
	 *
	 * @since 2.10.4
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
	 * @since 2.10.4
	 */
	public function test_subscriber_without_filter_cannot_purge() {
		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$this->assertFalse( Util_Capability::can_flush_all() );
		$this->assertFalse( Util_Capability::can_execute_purge( 'w3tc_flush_all' ) );
		$this->assertFalse( \current_user_can( 'manage_options' ) );
	}

	/**
	 * Legacy admin_bar filters cannot lower the administrative floor.
	 *
	 * @since 2.11.1
	 */
	public function test_legacy_admin_bar_filter_does_not_grant_purge() {
		\add_filter( 'w3tc_capability_admin_bar', static function () {
			return 'edit_posts';
		} );

		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );

		$this->assertFalse( Util_Capability::can_flush_all() );
		$this->assertFalse( Util_Capability::can_flush_post() );
	}

	/**
	 * Flush-all filters do not change the admin-bar parent for an editor.
	 *
	 * @since 2.11.1
	 */
	public function test_admin_bar_parent_cap_with_flush_all_only() {
		\add_filter( 'w3tc_capability_flush_all', static function () {
			return 'edit_posts';
		} );

		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );

		$this->assertFalse( Util_Capability::can_flush_all() );
		$this->assertFalse( Util_Capability::can_flush_post() );
		$this->assertSame( 'manage_options', Util_Capability::admin_bar_parent_capability() );
		$this->assertFalse( \current_user_can( Util_Capability::admin_bar_parent_capability() ) );
	}

	/**
	 * Flush-post filters do not change the admin-bar parent for an editor.
	 *
	 * @since 2.11.1
	 */
	public function test_admin_bar_parent_cap_with_flush_post_only() {
		\add_filter( 'w3tc_capability_flush_post', static function () {
			return 'edit_posts';
		} );

		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );

		$this->assertSame( 'manage_options', Util_Capability::admin_bar_parent_capability() );
		$this->assertFalse( \current_user_can( Util_Capability::admin_bar_parent_capability() ) );
	}

	/**
	 * Non-purge actions stay outside the allowlist.
	 *
	 * @since 2.10.4
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
	 * Admin-bar allowlist rejects reused ids that do not target purge actions.
	 *
	 * @since 2.10.4
	 */
	public function test_admin_bar_item_requires_purge_action_href() {
		$this->assertTrue(
			Util_Capability::is_allowed_purge_admin_bar_item(
				array(
					'id'   => 'w3tc',
					'href' => false,
				)
			)
		);
		$this->assertTrue(
			Util_Capability::is_allowed_purge_admin_bar_item(
				array(
					'id'   => 'w3tc_flush_current_page',
					'href' => 'https://example.com/wp-admin/admin.php?w3tc_flush_post=1&post_id=5',
				)
			)
		);
		$this->assertFalse(
			Util_Capability::is_allowed_purge_admin_bar_item(
				array(
					'id'   => 'w3tc_flush_current_page',
					'href' => 'https://example.com/wp-admin/admin.php?page=w3tc_dashboard&w3tc_alwayscached_regenerate=1&post_id=5',
				)
			)
		);
	}

	/**
	 * URL flush requires a same-host URL and edit_post (or flush-all).
	 *
	 * @since 2.10.4
	 */
	public function test_flush_current_page_requires_edit_post_for_url() {
		\add_filter(
			'w3tc_capability_flush_post',
			static function () {
				return 'edit_posts';
			}
		);

		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		$author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$own_id    = $this->factory->post->create(
			array(
				'post_author' => $author_id,
				'post_status' => 'publish',
				'post_name'   => 'author-purge-url-post',
			)
		);
		$other_id  = $this->factory->post->create(
			array(
				'post_author' => $editor_id,
				'post_status' => 'publish',
				'post_name'   => 'editor-purge-url-post',
			)
		);

		$this->set_permalink_structure( '/%postname%/' );
		$own_url   = \get_permalink( $own_id );
		$other_url = \get_permalink( $other_id );

		wp_set_current_user( $author_id );

		$_GET['url'] = $own_url;
		$this->assertFalse( Util_Capability::can_execute_purge( 'w3tc_flush_current_page' ) );

		$_GET['url'] = $other_url;
		$this->assertFalse( Util_Capability::can_execute_purge( 'w3tc_flush_current_page' ) );

		$_GET['url'] = 'https://evil.example/path';
		$this->assertFalse( Util_Capability::can_execute_purge( 'w3tc_flush_current_page' ) );
		unset( $_GET['url'] );

		\add_filter(
			'w3tc_capability_flush_all',
			static function () {
				return 'edit_posts';
			}
		);
		$_GET['url'] = $other_url;
		$this->assertFalse( Util_Capability::can_execute_purge( 'w3tc_flush_current_page' ) );
		unset( $_GET['url'] );
	}

	/**
	 * Purge filters do not grant a non-administrator purge access.
	 *
	 * @since 2.11.1
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

		$this->assertFalse( Util_Capability::user_can_purge_anything() );
		$this->assertFalse( \current_user_can( 'manage_options' ) );
	}
}
