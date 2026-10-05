<?php
/**
 * File: class-w3tc-pgcache-wp-cache-define-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.10.8
 */

declare( strict_types = 1 );

use W3TC\PgCache_Environment;

/**
 * PgCache_Environment must not prepend a second WP_CACHE define.
 *
 * @since 2.10.8
 */
class W3tc_Pgcache_Wp_Cache_Define_Test extends WP_UnitTestCase {

	/**
	 * Build wp-config contents through the private helper.
	 *
	 * @since 2.10.8
	 *
	 * @param string $config_data     Current file contents.
	 * @param bool   $already_defined Whether WP_CACHE is already defined.
	 * @return string
	 */
	private function content_for( $config_data, $already_defined ) {
		$environment = new PgCache_Environment();
		$method      = new ReflectionMethod( PgCache_Environment::class, 'wp_config_content_for_cache_constant' );

		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$result = $method->invoke( $environment, $config_data, $already_defined );

		$this->assertIsString( $result );

		return $result;
	}

	/**
	 * Count WP_CACHE define() lines.
	 *
	 * @since 2.10.8
	 *
	 * @param string $config_data File contents.
	 * @return int
	 */
	private function define_count( $config_data ) {
		return preg_match_all( "/define\\s*\\(\\s*['\"]WP_CACHE['\"]/", $config_data );
	}

	/**
	 * An already-true constant does not get another define on the stub.
	 *
	 * @since 2.10.8
	 */
	public function test_already_true_does_not_write() {
		$stub   = "<?php\ndefine('DB_NAME', 'db');\nrequire_once dirname(__DIR__) . '/config/application.php';\n";
		$result = $this->content_for( $stub, true );

		$this->assertSame( $stub, $result );
		$this->assertSame( 0, $this->define_count( $result ) );
	}

	/**
	 * An already-false constant is left false. It is not rewritten to true.
	 *
	 * @since 2.10.8
	 */
	public function test_already_false_does_not_write() {
		$stub   = "<?php\ndefine('WP_CACHE', false);\n";
		$result = $this->content_for( $stub, true );

		$this->assertSame( $stub, $result );
		$this->assertSame( 1, $this->define_count( $result ) );
		$this->assertStringContainsString( "define('WP_CACHE', false)", $result );
		$this->assertStringNotContainsString( "define('WP_CACHE', true)", $result );
	}

	/**
	 * A standard install with no WP_CACHE constant gets the snippet once.
	 *
	 * @since 2.10.8
	 */
	public function test_undefined_writes_snippet_once() {
		$stub   = "<?php\ndefine('DB_NAME', 'db');\n";
		$result = $this->content_for( $stub, false );

		$this->assertSame( 1, $this->define_count( $result ) );
		$this->assertStringContainsString( "define('WP_CACHE', true); // Added by W3 Total Cache", $result );
		$this->assertStringContainsString( "define('DB_NAME', 'db')", $result );

		$again = $this->content_for( $result, false );
		$this->assertSame( 1, $this->define_count( $again ) );
	}

	/**
	 * The admin gate must not treat a false constant as a reason to write.
	 *
	 * @since 2.10.8
	 */
	public function test_admin_gate_does_not_write_when_constant_is_false() {
		$source = file_get_contents( W3TC_DIR . '/PgCache_Environment.php' );
		$this->assertIsString( $source );

		$start = strpos( $source, 'function fix_on_wpadmin_request' );
		$end   = strpos( $source, 'function fix_on_event' );
		$this->assertNotFalse( $start );
		$this->assertNotFalse( $end );
		$this->assertGreaterThan( $start, $end );

		$gate = substr( $source, $start, $end - $start );
		$this->assertStringContainsString( "if ( ! defined( 'WP_CACHE' ) )", $gate );
		$this->assertStringNotContainsString( '! WP_CACHE', $gate );
	}
}
