<?php
/**
 * File: class-w3tc-objectcache-admin-last-changed-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      X.X.X
 */

declare( strict_types = 1 );

use W3TC\Dispatcher;
use W3TC\ObjectCache_WpObjectCache_Regular;

/**
 * Admin object-cache invalidation write-through.
 *
 * @since X.X.X
 */
class W3tc_Objectcache_Admin_Last_Changed_Test extends WP_UnitTestCase {
	/**
	 * Previous objectcache.enabled.
	 *
	 * @var mixed
	 */
	private $prev_enabled;

	/**
	 * Previous objectcache.engine.
	 *
	 * @var mixed
	 */
	private $prev_engine;

	/**
	 * Previous objectcache.enabled_for_wp_admin.
	 *
	 * @var mixed
	 */
	private $prev_admin;

	/**
	 * Enable file object cache for each test.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$config            = Dispatcher::config();
		$this->prev_enabled = $config->get( 'objectcache.enabled' );
		$this->prev_engine  = $config->get( 'objectcache.engine' );
		$this->prev_admin   = $config->get( 'objectcache.enabled_for_wp_admin' );

		$config->set( 'objectcache.enabled', true );
		$config->set( 'objectcache.engine', 'file' );
		$config->set( 'objectcache.enabled_for_wp_admin', false );
	}

	/**
	 * Restore object cache config.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function tear_down() {
		$config = Dispatcher::config();
		$config->set( 'objectcache.enabled', $this->prev_enabled );
		$config->set( 'objectcache.engine', $this->prev_engine );
		$config->set( 'objectcache.enabled_for_wp_admin', $this->prev_admin );

		parent::tear_down();
	}

	/**
	 * last_changed is persisted when admin persistent reads/writes are skipped.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_admin_skip_persists_last_changed_not_query_keys() {
		$admin = new ObjectCache_WpObjectCache_Regular();
		$this->simulate_admin_persistent_skip( $admin );

		$salt      = 'admin-salt-' . uniqid( '', true );
		$query_key = 'post-query-' . uniqid( '', true );

		$admin->set( 'last_changed', $salt, 'posts' );
		$admin->set( $query_key, array( 'ids' => array( 1 ) ), 'post-queries' );

		$frontend = new ObjectCache_WpObjectCache_Regular();
		$this->assertSame( $salt, $frontend->get( 'last_changed', 'posts' ) );
		$this->assertFalse( $frontend->get( $query_key, 'post-queries' ) );

		$multi_salt = 'admin-multi-salt-' . uniqid( '', true );
		$multi_key  = 'post-query-multi-' . uniqid( '', true );
		$admin->set_multiple(
			array(
				'last_changed' => $multi_salt,
				$multi_key     => array( 'ids' => array( 2 ) ),
			),
			'posts'
		);

		$frontend_multi = new ObjectCache_WpObjectCache_Regular();
		$this->assertSame( $multi_salt, $frontend_multi->get( 'last_changed', 'posts' ) );
		$this->assertFalse( $frontend_multi->get( $multi_key, 'posts' ) );
	}

	/**
	 * last_changed reads through without exposing other persistent admin keys.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_admin_skip_reads_last_changed_not_other_keys() {
		$salt     = 'frontend-salt-' . uniqid( '', true );
		$post_key = 'post-' . uniqid( '', true );
		$frontend = new ObjectCache_WpObjectCache_Regular();
		$frontend->set( 'last_changed', $salt, 'posts' );
		$frontend->set( $post_key, (object) array( 'ID' => 123 ), 'posts' );

		$admin = new ObjectCache_WpObjectCache_Regular();
		$this->simulate_admin_persistent_skip( $admin );
		$this->assertSame( $salt, $admin->get( 'last_changed', 'posts' ) );
		$this->assertFalse( $admin->get( $post_key, 'posts' ) );

		$admin_multi = new ObjectCache_WpObjectCache_Regular();
		$this->simulate_admin_persistent_skip( $admin_multi );
		$values = $admin_multi->get_multiple( array( 'last_changed', $post_key ), 'posts' );
		$this->assertSame( $salt, $values['last_changed'] );
		$this->assertFalse( $values[ $post_key ] );
	}

	/**
	 * Backend keys still delete when admin get() cannot see them.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_admin_skip_deletes_persistent_key_on_runtime_miss() {
		$frontend = new ObjectCache_WpObjectCache_Regular();
		$post_key = 'post-' . uniqid( '', true );
		$frontend->set( $post_key, (object) array( 'ID' => 123 ), 'posts' );

		$this->assertNotFalse( $frontend->get( $post_key, 'posts' ) );

		$admin = new ObjectCache_WpObjectCache_Regular();
		$this->simulate_admin_persistent_skip( $admin );

		$admin->delete( $post_key, 'posts' );

		$after = new ObjectCache_WpObjectCache_Regular();
		$this->assertFalse( $after->get( $post_key, 'posts' ) );
	}

	/**
	 * Pretend persistent cache is disabled for this instance (wp-admin skip).
	 *
	 * @since X.X.X
	 *
	 * @param ObjectCache_WpObjectCache_Regular $oc Cache instance.
	 *
	 * @return void
	 */
	private function simulate_admin_persistent_skip( ObjectCache_WpObjectCache_Regular $oc ) {
		$prop = new \ReflectionProperty( ObjectCache_WpObjectCache_Regular::class, '_can_cache_dynamic' );
		$prop->setAccessible( true );
		$prop->setValue( $oc, false );
	}
}
