<?php
/**
 * Mirror URL expansion for page cache purges.
 *
 * @package W3TC
 */

/**
 * Covers Util_PageUrls::complement_with_mirror_urls().
 *
 * Most cases run in the shared process (subdirectory mode). The subdomain case
 * runs in its own process, where SUBDOMAIN_INSTALL can be defined as true.
 *
 * @since 2.11.0
 */
class Util_PageUrls_Mirror_Urls_Test extends WP_UnitTestCase {

	/**
	 * Saved config values, restored in tear_down.
	 *
	 * @var array
	 */
	private $previous = array();

	/**
	 * Sets up config for the test.
	 *
	 * @since 2.11.0
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$config = \W3TC\Dispatcher::config();

		$this->previous = array(
			'pgcache.mirrors.enabled'   => $config->get_boolean( 'pgcache.mirrors.enabled' ),
			'pgcache.mirrors.home_urls' => $config->get_array( 'pgcache.mirrors.home_urls' ),
		);
	}

	/**
	 * Restores config after each test.
	 *
	 * @since 2.11.0
	 *
	 * @return void
	 */
	public function tear_down() {
		$config = \W3TC\Dispatcher::config();

		foreach ( $this->previous as $key => $value ) {
			$config->set( $key, $value );
		}

		parent::tear_down();
	}

	/**
	 * With aliases enabled, a URL under the home URL gets the same path on each alias.
	 *
	 * @since 2.11.0
	 *
	 * @return void
	 */
	public function test_aliases_enabled_expands_home_urls() {
		$config = \W3TC\Dispatcher::config();
		$config->set( 'pgcache.mirrors.enabled', true );
		$config->set( 'pgcache.mirrors.home_urls', array( 'https://site1.test' ) );

		$home = trailingslashit( get_home_url() );

		$result = \W3TC\Util_PageUrls::complement_with_mirror_urls(
			array( $home, $home . 'sample-post/' )
		);

		$this->assertContains( $home, $result );
		$this->assertContains( $home . 'sample-post/', $result );
		$this->assertContains( 'https://site1.test/', $result );
		$this->assertContains( 'https://site1.test/sample-post/', $result );
	}

	/**
	 * On a subdomain network, aliases still expand the subsite URL.
	 *
	 * Runs in its own process because SUBDOMAIN_INSTALL is a global constant.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @since 2.11.0
	 *
	 * @return void
	 */
	public function test_subdomain_network_expands_home_urls() {
		if ( defined( 'SUBDOMAIN_INSTALL' ) && ! SUBDOMAIN_INSTALL ) {
			$this->markTestSkipped( 'Test suite runs in subdirectory mode.' );
		}

		if ( ! defined( 'SUBDOMAIN_INSTALL' ) ) {
			define( 'SUBDOMAIN_INSTALL', true );
		}

		$this->assertTrue( \W3TC\Util_Environment::is_wpmu_subdomain() );

		$config = \W3TC\Dispatcher::config();
		$config->set( 'pgcache.mirrors.enabled', true );
		$config->set( 'pgcache.mirrors.home_urls', array( 'https://site1.test' ) );

		$home = trailingslashit( get_home_url() );

		$result = \W3TC\Util_PageUrls::complement_with_mirror_urls(
			array( $home . 'sample-post/' )
		);

		$this->assertContains( 'https://site1.test/sample-post/', $result );
	}

	/**
	 * Regex purge patterns are expanded the same way as plain URLs.
	 *
	 * @since 2.11.0
	 *
	 * @return void
	 */
	public function test_aliases_enabled_expands_regex_patterns() {
		$config = \W3TC\Dispatcher::config();
		$config->set( 'pgcache.mirrors.enabled', true );
		$config->set( 'pgcache.mirrors.home_urls', array( 'https://site1.test' ) );

		$pattern = trailingslashit( get_home_url() ) . '.*';

		$result = \W3TC\Util_PageUrls::complement_with_mirror_urls( array( $pattern ) );

		$this->assertContains( 'https://site1.test/.*', $result );
	}

	/**
	 * With the aliases feature off, the URL list is returned unchanged.
	 *
	 * @since 2.11.0
	 *
	 * @return void
	 */
	public function test_aliases_disabled_returns_input_unchanged() {
		$config = \W3TC\Dispatcher::config();
		$config->set( 'pgcache.mirrors.enabled', false );
		$config->set( 'pgcache.mirrors.home_urls', array( 'https://site1.test' ) );

		$input = array( trailingslashit( get_home_url() ) . 'sample-post/' );

		$this->assertSame(
			$input,
			\W3TC\Util_PageUrls::complement_with_mirror_urls( $input )
		);
	}

	/**
	 * URLs outside the current home URL are not rewritten onto aliases.
	 *
	 * @since 2.11.0
	 *
	 * @return void
	 */
	public function test_urls_outside_home_are_not_expanded() {
		$config = \W3TC\Dispatcher::config();
		$config->set( 'pgcache.mirrors.enabled', true );
		$config->set( 'pgcache.mirrors.home_urls', array( 'https://site1.test' ) );

		$foreign = 'https://other.test/sample-post/';

		$result = \W3TC\Util_PageUrls::complement_with_mirror_urls( array( $foreign ) );

		$this->assertSame( array( $foreign ), $result );
	}
}
