<?php
/**
 * File: class-w3tc-ustats-access-log-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.10.0
 */

declare( strict_types = 1 );

use W3TC\UsageStatistics_Plugin_Admin;

/**
 * Class: W3tc_Ustats_Access_Log_Test
 *
 * Coverage for the access-log path validator that gates
 * `wp_ajax_ustats_access_log_test`. The validator is `private static`,
 * so the tests poke at it through Reflection — keeping the surface
 * area unchanged while still letting the boundary check be exercised
 * against a real `realpath()`.
 *
 * Regressions here re-open the  file-existence oracle.
 *
 * @since 2.10.0
 */
class W3tc_Ustats_Access_Log_Test extends WP_UnitTestCase {

	/**
	 * Invoke the private validator via Reflection.
	 *
	 * @param mixed $filepath Candidate path.
	 *
	 * @return string|false Validator return value.
	 */
	private function invoke_validator( $filepath ) {
		$ref = new \ReflectionMethod( UsageStatistics_Plugin_Admin::class, 'validate_access_log_path' );
		$ref->setAccessible( true );

		return $ref->invoke( null, $filepath );
	}

	/**
	 * Empty / non-string / null inputs return false.
	 *
	 * @since 2.10.0
	 */
	public function test_validator_refuses_empty_or_non_string() {
		$this->assertFalse( $this->invoke_validator( '' ) );
		$this->assertFalse( $this->invoke_validator( null ) );
		$this->assertFalse( $this->invoke_validator( array( '/tmp/x' ) ) );
		$this->assertFalse( $this->invoke_validator( 42 ) );
	}

	/**
	 * A non-existent path is refused (realpath returns false).
	 *
	 * @since 2.10.0
	 */
	public function test_validator_refuses_nonexistent_path() {
		$this->assertFalse(
			$this->invoke_validator( '/tmp/w3tc-this-file-does-not-exist-xyz.log' )
		);
	}

	/**
	 * A path that exists but lives outside every allowed root is
	 * refused — the load-bearing boundary check.
	 *
	 * @since 2.10.0
	 */
	public function test_validator_refuses_path_outside_allowed_roots() {
		/**
		 * `/etc/hostname` (or equivalent) exists on every Linux test host
		 * and is unambiguously outside wp-content / uploads / cache.
		 */
		if ( ! file_exists( '/etc/hostname' ) ) {
			$this->markTestSkipped( 'No /etc/hostname on this host; cannot test out-of-allowlist file.' );
		}
		$this->assertFalse( $this->invoke_validator( '/etc/hostname' ) );
	}

	/**
	 * Traversal patterns that resolve to outside-allowlist paths are
	 * refused. `realpath()` normalises away the `..`, so the validator
	 * is checking the canonical target — not the literal input string.
	 *
	 * @since 2.10.0
	 */
	public function test_validator_refuses_traversal_to_outside_root() {
		/**
		 * Build a traversal that escapes wp-content. The base is the
		 * uploads dir (a known root); the traversal target is `/etc` —
		 * outside the allowlist.
		 */
		$uploads = wp_upload_dir( null, false );
		if ( empty( $uploads['basedir'] ) || ! is_dir( $uploads['basedir'] ) ) {
			$this->markTestSkipped( 'Uploads basedir unavailable on this host.' );
		}
		if ( ! file_exists( '/etc/hostname' ) ) {
			$this->markTestSkipped( 'No /etc/hostname on this host.' );
		}

		/**
		 * `<uploads>/../../../../etc/hostname` — realpath() resolves to
		 * /etc/hostname, which is not under any allowed root.
		 */
		$traversal = $uploads['basedir'] . '/../../../../etc/hostname';
		$this->assertFalse( $this->invoke_validator( $traversal ) );
	}

	/**
	 * A real file under /var/log is accepted (typical access-log location).
	 *
	 * @since 2.10.0
	 */
	public function test_validator_accepts_file_under_var_log() {
		$var_log_real = \realpath( '/var/log' );
		if ( false === $var_log_real ) {
			$this->markTestSkipped( '/var/log does not exist on this host.' );
		}

		/**
		 * Find any readable regular file whose canonical path stays under
		 * /var/log — a symlink resolving elsewhere would (correctly) be
		 * refused by the validator, so it is not a usable candidate.
		 */
		$candidate = false;
		foreach ( (array) \glob( '/var/log/*' ) as $file ) {
			$real = \realpath( $file );
			if (
				false !== $real
				&& \is_file( $real )
				&& \is_readable( $real )
				&& 0 === \strpos( $real, $var_log_real . DIRECTORY_SEPARATOR )
			) {
				$candidate = $file;
				break;
			}
		}

		if ( false === $candidate ) {
			$this->markTestSkipped( 'No readable regular file under /var/log on this host.' );
		}

		$result = $this->invoke_validator( $candidate );
		$this->assertIsString( $result );
		$this->assertSame( \realpath( $candidate ), $result );
	}

	/**
	 * A real file under WP_CONTENT_DIR is accepted.
	 *
	 * @since 2.10.0
	 */
	public function test_validator_accepts_file_under_wp_content_dir() {
		if ( ! defined( 'WP_CONTENT_DIR' ) || ! is_dir( WP_CONTENT_DIR ) ) {
			$this->markTestSkipped( 'WP_CONTENT_DIR not present.' );
		}

		$path = WP_CONTENT_DIR . '/' . uniqid( 'w3tc-ustats-test-', true ) . '.log';
		file_put_contents( $path, "test log line\n" );
		$this->assertFileExists( $path );

		try {
			$result = $this->invoke_validator( $path );
			$this->assertIsString( $result );
			$this->assertSame( realpath( $path ), $result );
		} finally {
			@unlink( $path );
		}
	}

	/**
	 * A real file under the uploads basedir is accepted.
	 *
	 * @since 2.10.0
	 */
	public function test_validator_accepts_file_under_uploads_basedir() {
		$uploads = wp_upload_dir( null, false );
		if ( empty( $uploads['basedir'] ) || ! is_dir( $uploads['basedir'] ) ) {
			$this->markTestSkipped( 'Uploads basedir unavailable on this host.' );
		}

		$path = $uploads['basedir'] . '/' . uniqid( 'w3tc-ustats-test-', true ) . '.log';
		file_put_contents( $path, "upload-side log\n" );
		$this->assertFileExists( $path );

		try {
			$result = $this->invoke_validator( $path );
			$this->assertIsString( $result );
			$this->assertSame( realpath( $path ), $result );
		} finally {
			@unlink( $path );
		}
	}

	/**
	 * A real file under W3TC_CACHE_DIR is accepted.
	 *
	 * @since 2.10.0
	 */
	public function test_validator_accepts_file_under_w3tc_cache_dir() {
		if ( ! defined( 'W3TC_CACHE_DIR' ) ) {
			$this->markTestSkipped( 'W3TC_CACHE_DIR is not defined in this test environment.' );
		}
		if ( ! is_dir( W3TC_CACHE_DIR ) ) {
			wp_mkdir_p( W3TC_CACHE_DIR );
		}
		if ( ! is_dir( W3TC_CACHE_DIR ) ) {
			$this->markTestSkipped( 'Cannot create W3TC_CACHE_DIR on this host.' );
		}

		$path = W3TC_CACHE_DIR . '/' . uniqid( 'w3tc-ustats-test-', true ) . '.log';
		$ok   = @file_put_contents( $path, "cache-side log\n" );
		if ( false === $ok ) {
			$this->markTestSkipped( 'Cannot write into W3TC_CACHE_DIR on this host.' );
		}

		try {
			$result = $this->invoke_validator( $path );
			$this->assertIsString( $result );
			$this->assertSame( realpath( $path ), $result );
		} finally {
			@unlink( $path );
		}
	}

	/**
	 * A directory (`is_file()` fails) is refused even when it lives
	 * under an allowed root.
	 *
	 * @since 2.10.0
	 */
	public function test_validator_refuses_directory_under_allowed_root() {
		if ( ! defined( 'WP_CONTENT_DIR' ) || ! is_dir( WP_CONTENT_DIR ) ) {
			$this->markTestSkipped( 'WP_CONTENT_DIR not present.' );
		}
		$this->assertFalse( $this->invoke_validator( WP_CONTENT_DIR ) );
	}
}
