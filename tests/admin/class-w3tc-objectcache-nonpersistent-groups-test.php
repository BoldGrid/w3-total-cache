<?php
/**
 * File: class-w3tc-objectcache-nonpersistent-groups-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      X.X.X
 */

declare( strict_types = 1 );

use W3TC\Dispatcher;
use W3TC\ObjectCache_WpObjectCache_Regular;

/**
 * Class: W3tc_ObjectCache_Nonpersistent_Groups_Test
 *
 * WordPress registers `counts` and `plugins` as non-persistent groups at startup.
 * A default group the admin removed from the saved list must stay persistent, while
 * groups left on the list, or registered at runtime, stay non-persistent.
 *
 * @since X.X.X
 */
class W3tc_ObjectCache_Nonpersistent_Groups_Test extends WP_UnitTestCase {

	/**
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function tearDown(): void {
		Dispatcher::reset_config();
		parent::tearDown();
	}

	/**
	 * Builds the engine with a saved non-persistent list, then applies the WordPress startup call.
	 *
	 * @since X.X.X
	 *
	 * @param array|null $saved_groups Saved list, or null to keep the default.
	 *
	 * @return array Effective non-persistent groups.
	 */
	private function groups_after_core_registration( $saved_groups ): array {
		if ( null !== $saved_groups ) {
			Dispatcher::config()->set( 'objectcache.groups.nonpersistent', $saved_groups );
		}

		$cache = new ObjectCache_WpObjectCache_Regular();
		$cache->add_nonpersistent_groups( array( 'counts', 'plugins' ) );

		$property = new ReflectionProperty( ObjectCache_WpObjectCache_Regular::class, 'nonpersistent_groups' );
		$property->setAccessible( true );

		return \array_values( $property->getValue( $cache ) );
	}

	/**
	 * A group removed from the saved list stays persistent after WordPress registers its groups.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_removed_nonpersistent_group_is_not_added_back(): void {
		$groups = $this->groups_after_core_registration( array( 'plugins' ) );

		$this->assertNotContains( 'counts', $groups );
		$this->assertContains( 'plugins', $groups );
	}

	/**
	 * Control: groups left on the saved list stay non-persistent.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_configured_group_stays_nonpersistent(): void {
		$groups = $this->groups_after_core_registration( array( 'counts', 'plugins', 'custom_group' ) );

		$this->assertContains( 'counts', $groups );
		$this->assertContains( 'plugins', $groups );
		$this->assertContains( 'custom_group', $groups );
	}

	/**
	 * With the default setting, WordPress groups are non-persistent.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_default_setting_keeps_core_groups_nonpersistent(): void {
		$groups = $this->groups_after_core_registration( null );

		$this->assertContains( 'counts', $groups );
		$this->assertContains( 'plugins', $groups );
	}

	/**
	 * Emptying the list makes both default groups persistent.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_empty_saved_list_leaves_core_groups_persistent(): void {
		$groups = $this->groups_after_core_registration( array() );

		$this->assertNotContains( 'counts', $groups );
		$this->assertNotContains( 'plugins', $groups );
	}

	/**
	 * Groups registered at runtime beyond the defaults are still honored.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_runtime_registered_group_is_honored(): void {
		Dispatcher::config()->set( 'objectcache.groups.nonpersistent', array( 'plugins' ) );

		$cache = new ObjectCache_WpObjectCache_Regular();
		$cache->add_nonpersistent_groups( array( 'counts', 'plugins' ) );
		$cache->add_nonpersistent_groups( 'runtime_group' );

		$property = new ReflectionProperty( ObjectCache_WpObjectCache_Regular::class, 'nonpersistent_groups' );
		$property->setAccessible( true );
		$groups = \array_values( $property->getValue( $cache ) );

		$this->assertContains( 'runtime_group', $groups );
		$this->assertNotContains( 'counts', $groups );
	}
}
