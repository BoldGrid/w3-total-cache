<?php
/**
 * Apache type and encoding rules for compressed Disk: Enhanced page cache files.
 *
 * @package W3TC\Tests
 */

/**
 * Tests that compressed page cache files are served with their type and encoding.
 *
 * @since X.X.X
 */
class PgCache_Encoded_Variant_Rules_Test extends WP_UnitTestCase {

	/**
	 * Original server software.
	 *
	 * @var string|null
	 */
	private $server_software = null;

	/**
	 * Forces the Apache branch.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->server_software      = isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : null;
		$_SERVER['SERVER_SOFTWARE'] = 'Apache/2.4';
	}

	/**
	 * Restores the server software.
	 *
	 * @return void
	 */
	public function tear_down() {
		if ( null === $this->server_software ) {
			unset( $_SERVER['SERVER_SOFTWARE'] );
		} else {
			$_SERVER['SERVER_SOFTWARE'] = $this->server_software;
		}

		parent::tear_down();
	}

	/**
	 * Builds a Disk: Enhanced config with HTML compression.
	 *
	 * @param bool $brotli Whether brotli is enabled.
	 *
	 * @return \W3TC\Config
	 */
	private function config( $brotli = false ) {
		$config = new \W3TC\Config();
		$config->set( 'pgcache.enabled', true );
		$config->set( 'pgcache.engine', 'file_generic' );
		$config->set( 'browsercache.enabled', true );
		$config->set( 'browsercache.html.compression', true );
		$config->set( 'browsercache.html.brotli', $brotli );

		return $config;
	}

	/**
	 * Calls a private generator.
	 *
	 * @param string       $method Method name.
	 * @param \W3TC\Config $config Config.
	 *
	 * @return string
	 */
	private function generate( $method, $config ) {
		$reflection = new \ReflectionMethod( \W3TC\PgCache_Environment::class, $method );
		$reflection->setAccessible( true );

		return (string) $reflection->invoke( new \W3TC\PgCache_Environment(), $config );
	}

	/**
	 * The cache folder rules still map the gzip and brotli files.
	 *
	 * @return void
	 */
	public function test_cache_rules_map_encoded_files() {
		$rules = $this->generate( 'rules_cache_generate_apache', $this->config( true ) );

		$this->assertStringContainsString( "    AddType text/html .html_gzip\n    AddEncoding gzip .html_gzip\n", $rules );
		$this->assertStringContainsString( "    SetEnvIfNoCase Request_URI \\.html_gzip$ no-gzip\n", $rules );
		$this->assertStringContainsString( "    AddType text/html .html_br\n    AddEncoding br .html_br\n", $rules );
	}

	/**
	 * On multisite the core rules map the compressed files they rewrite to.
	 *
	 * @return void
	 */
	public function test_multisite_core_rules_map_encoded_files() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only.' );
		}

		$rules = $this->generate( 'rules_core_generate_apache', $this->config( true ) );
		$end   = strpos( $rules, W3TC_MARKER_END_PGCACHE_CORE );

		$this->assertNotFalse( $end );
		$this->assertStringContainsString( '[E=W3TC_ENC:_gzip]', $rules );

		$core = substr( $rules, 0, $end );
		$this->assertStringContainsString( "    AddEncoding gzip .html_gzip\n", $core );
		$this->assertStringContainsString( "    SetEnvIfNoCase Request_URI \\.html_gzip$ no-gzip\n", $core );
		$this->assertStringContainsString( "    AddEncoding br .html_br\n", $core );
	}

	/**
	 * On multisite the core rules add no type rules when HTML compression is off.
	 *
	 * @return void
	 */
	public function test_multisite_core_rules_without_compression_add_no_types() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only.' );
		}

		$config = $this->config();
		$config->set( 'browsercache.html.compression', false );

		$this->assertStringNotContainsString( 'AddEncoding', $this->generate( 'rules_core_generate_apache', $config ) );
	}

	/**
	 * Single-site core rules are unchanged.
	 *
	 * @return void
	 */
	public function test_single_site_core_rules_add_no_types() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Single site only.' );
		}

		$this->assertStringNotContainsString( 'AddEncoding', $this->generate( 'rules_core_generate_apache', $this->config( true ) ) );
	}
}
