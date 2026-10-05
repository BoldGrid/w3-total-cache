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
 * WordPress itself defines WP_CACHE as false before admin code runs, so these
 * tests decide from the site's config files rather than the PHP constant.
 *
 * @since 2.10.8
 */
class W3tc_Pgcache_Wp_Cache_Define_Test extends WP_UnitTestCase {

	/**
	 * Fixture root removed in tearDown.
	 *
	 * @var string
	 */
	private $fixture = '';

	/**
	 * Create a throwaway config directory.
	 *
	 * @since 2.10.8
	 */
	public function setUp(): void {
		parent::setUp();
		$this->fixture = WP_CONTENT_DIR . '/cache/tmp/wp-cache-define-' . wp_generate_password( 6, false );
		wp_mkdir_p( $this->fixture );
	}

	/**
	 * Remove the fixture directory.
	 *
	 * @since 2.10.8
	 */
	public function tearDown(): void {
		$this->remove_tree( $this->fixture );
		parent::tearDown();
	}

	/**
	 * Build wp-config contents through the private helper.
	 *
	 * @since 2.10.8
	 *
	 * @param string      $config_path Path to the config file.
	 * @param string      $config_data Current file contents.
	 * @param string|null $env         Active environment name, when the case sets one.
	 * @return string
	 */
	private function content_for( $config_path, $config_data, $env = null ) {
		if ( null === $env ) {
			$environment = new PgCache_Environment();
		} else {
			$environment      = new W3tc_Pgcache_Wp_Cache_Env_Double();
			$environment->env = $env;
		}
		$method = new ReflectionMethod( PgCache_Environment::class, 'wp_config_content_for_cache_constant' );

		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$result = $method->invoke( $environment, $config_path, $config_data );

		$this->assertIsString( $result );

		return $result;
	}

	/**
	 * Count WP_CACHE define() lines, including Config::define().
	 *
	 * @since 2.10.8
	 *
	 * @param string $config_data File contents.
	 * @return int
	 */
	private function define_count( $config_data ) {
		$count = preg_match_all( "/define\\s*\\(\\s*['\"]WP_CACHE['\"]/", $config_data );

		return false === $count ? 0 : $count;
	}

	/**
	 * Write a fixture file and return its path.
	 *
	 * @since 2.10.8
	 *
	 * @param string $relative Path relative to the fixture root.
	 * @param string $contents File contents.
	 * @return string
	 */
	private function write_fixture( $relative, $contents ) {
		$path = $this->fixture . '/' . $relative;
		wp_mkdir_p( dirname( $path ) );
		file_put_contents( $path, $contents );

		return $path;
	}

	/**
	 * A define('WP_CACHE', true) already in wp-config.php is left alone.
	 *
	 * @since 2.10.8
	 */
	public function test_already_true_in_file_does_not_write() {
		$stub   = "<?php\ndefine('WP_CACHE', true);\ndefine('DB_NAME', 'db');\n";
		$path   = $this->write_fixture( 'wp-config.php', $stub );
		$result = $this->content_for( $path, $stub );

		$this->assertSame( $stub, $result );
		$this->assertSame( 1, $this->define_count( $result ) );
	}

	/**
	 * A define('WP_CACHE', false) in wp-config.php is not rewritten to true.
	 *
	 * @since 2.10.8
	 */
	public function test_already_false_in_file_does_not_write() {
		$stub   = "<?php\ndefine('WP_CACHE', false);\n";
		$path   = $this->write_fixture( 'wp-config.php', $stub );
		$result = $this->content_for( $path, $stub );

		$this->assertSame( $stub, $result );
		$this->assertStringContainsString( "define('WP_CACHE', false)", $result );
		$this->assertStringNotContainsString( 'Added by W3 Total Cache', $result );
	}

	/**
	 * A standard install with no site-owned WP_CACHE define gets the snippet once.
	 *
	 * The require of wp-settings.php must not count as the site defining it.
	 *
	 * @since 2.10.8
	 */
	public function test_undefined_writes_snippet_once() {
		$stub = "<?php\ndefine('DB_NAME', 'db');\nrequire_once ABSPATH . 'wp-settings.php';\n";
		$path = $this->write_fixture( 'wp-config.php', $stub );

		$result = $this->content_for( $path, $stub );

		$this->assertSame( 1, $this->define_count( $result ) );
		$this->assertStringContainsString( "define('WP_CACHE', true); // Added by W3 Total Cache", $result );
		$this->assertStringContainsString( "define('DB_NAME', 'db')", $result );

		$again = $this->content_for( $path, $result );
		$this->assertSame( 1, $this->define_count( $again ) );
	}

	/**
	 * A Bedrock stub is not given a second define when application.php owns it.
	 *
	 * @since 2.10.8
	 */
	public function test_included_application_define_does_not_write() {
		$this->write_fixture(
			'config/application.php',
			"<?php\nConfig::define('WP_CACHE', true);\n"
		);
		$stub = "<?php\nrequire_once dirname(__DIR__) . '/config/application.php';\nrequire_once ABSPATH . 'wp-settings.php';\n";
		$path = $this->write_fixture( 'web/wp-config.php', $stub );

		$result = $this->content_for( $path, $stub );

		$this->assertSame( $stub, $result );
		$this->assertSame( 0, $this->define_count( $result ) );
	}

	/**
	 * A false constant in the active environment file is not overwritten.
	 *
	 * @since 2.10.8
	 */
	public function test_active_environment_false_does_not_write() {
		$this->write_fixture(
			'config/application.php',
			"<?php\n\$env_config = __DIR__ . '/environments/' . WP_ENV . '.php';\nrequire_once \$env_config;\n"
		);
		$this->write_fixture(
			'config/environments/development.php',
			"<?php\nConfig::define('WP_CACHE', false);\n"
		);
		$stub = "<?php\nrequire_once dirname(__DIR__) . '/config/application.php';\n";
		$path = $this->write_fixture( 'web/wp-config.php', $stub );

		$result = $this->content_for( $path, $stub, 'development' );

		$this->assertSame( $stub, $result );
		$this->assertStringNotContainsString( 'Added by W3 Total Cache', $result );
	}

	/**
	 * A WP_CACHE define in an inactive environment file does not block the snippet.
	 *
	 * @since 2.10.8
	 */
	public function test_inactive_environment_define_does_not_block_write() {
		$this->write_fixture(
			'config/application.php',
			"<?php\n\$env_config = __DIR__ . '/environments/' . WP_ENV . '.php';\nrequire_once \$env_config;\n"
		);
		$this->write_fixture(
			'config/environments/development.php',
			"<?php\nConfig::define('WP_CACHE', false);\n"
		);
		$this->write_fixture(
			'config/environments/production.php',
			"<?php\nConfig::define('WP_DEBUG', false);\n"
		);
		$stub = "<?php\nrequire_once dirname(__DIR__) . '/config/application.php';\n";
		$path = $this->write_fixture( 'web/wp-config.php', $stub );

		$result = $this->content_for( $path, $stub, 'production' );

		$this->assertSame( 1, $this->define_count( $result ) );
		$this->assertStringContainsString( "define('WP_CACHE', true); // Added by W3 Total Cache", $result );
	}

	/**
	 * The admin gate still runs when core has defaulted WP_CACHE to false.
	 *
	 * @since 2.10.8
	 */
	public function test_admin_gate_checks_false_constant_against_site_files() {
		$source = file_get_contents( W3TC_DIR . '/PgCache_Environment.php' );
		$this->assertIsString( $source );

		$start = strpos( $source, 'function fix_on_wpadmin_request' );
		$end   = strpos( $source, 'function fix_on_event' );
		$this->assertNotFalse( $start );
		$this->assertNotFalse( $end );

		$gate = substr( $source, $start, $end - $start );
		$this->assertStringContainsString( "if ( ! defined( 'WP_CACHE' ) || ! WP_CACHE )", $gate );
		$this->assertStringContainsString( 'wp_config_content_for_cache_constant', $source );
		$this->assertStringNotContainsString( 'glob(', $source );
	}

	/**
	 * Delete a directory tree created for a fixture.
	 *
	 * @since 2.10.8
	 *
	 * @param string $directory Directory to remove.
	 */
	private function remove_tree( $directory ) {
		if ( ! is_dir( $directory ) ) {
			return;
		}

		$items = scandir( $directory );
		if ( ! is_array( $items ) ) {
			return;
		}

		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $directory . '/' . $item;
			if ( is_dir( $path ) ) {
				$this->remove_tree( $path );
			} else {
				unlink( $path );
			}
		}

		rmdir( $directory );
	}
}

/**
 * Test double that supplies the active environment name.
 *
 * @since 2.10.8
 */
class W3tc_Pgcache_Wp_Cache_Env_Double extends PgCache_Environment {

	/**
	 * Environment name for this case.
	 *
	 * @var string
	 */
	public $env = '';

	/**
	 * Return the case's environment name instead of the WP_ENV constant.
	 *
	 * @since 2.10.8
	 *
	 * @return string
	 */
	protected function active_wp_env() {
		return $this->env;
	}
}
