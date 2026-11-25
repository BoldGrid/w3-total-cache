<?php
/**
 * Mirror URL expansion for page cache purges.
 *
 * @package W3TC
 */

/**
 * Covers Util_PageUrls::complement_with_mirror_urls().
 *
 * All cases run on a single site: phpunit.xml excludes the multisite group and this
 * class is not tagged with it. The subdomain case runs in its own process with
 * SUBDOMAIN_INSTALL defined as true. That proves the guard is gone, not that a real
 * subdomain subsite expands correctly. The multisite bootstrap defines SUBDOMAIN_INSTALL
 * as false, so a true subdomain network case cannot run there.
 *
 * @since X.X.X
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
	 * @since X.X.X
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
	 * @since X.X.X
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
	 * @since X.X.X
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
	 * With SUBDOMAIN_INSTALL defined as true, aliases still expand home URLs.
	 *
	 * This is a single site with the constant defined, not a real subdomain network.
	 * Runs in its own process because SUBDOMAIN_INSTALL is a global constant.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_subdomain_network_expands_home_urls() {
		if ( defined( 'SUBDOMAIN_INSTALL' ) && ! SUBDOMAIN_INSTALL ) {
			$this->markTestSkipped( 'SUBDOMAIN_INSTALL is already defined as false.' );
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
	 * @since X.X.X
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
	 * @since X.X.X
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
	 * @since X.X.X
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

	/**
	 * Alias entries with a trailing slash are normalized and empty entries are skipped.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_alias_trailing_slash_and_empty_entries() {
		$config = \W3TC\Dispatcher::config();
		$config->set( 'pgcache.mirrors.enabled', true );
		$config->set( 'pgcache.mirrors.home_urls', array( 'https://site1.test/', '', 'https://site2.test' ) );

		$home  = trailingslashit( get_home_url() );
		$input = array( $home . 'sample-post/' );

		$this->assertSame(
			array(
				$home . 'sample-post/',
				'https://site1.test/sample-post/',
				'https://site2.test/sample-post/',
			),
			\W3TC\Util_PageUrls::complement_with_mirror_urls( $input )
		);
	}
}
