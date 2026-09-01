<?php
/**
 * File: class-w3tc-util-java-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.10.0
 */

declare( strict_types = 1 );

use W3TC\Util_Java;

/**
 * Class: W3tc_Util_Java_Test
 *
 * Unit tests for the Java executable path allowlist and minifier
 * option sanitizers in `W3TC\Util_Java`.
 *
 * @since 2.10.0
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
	 * @since 2.10.0
	 */
	public function test_validate_rejects_empty_and_non_string() {
		$this->assertFalse( Util_Java::validate( '' ) );
		$this->assertFalse( Util_Java::validate( null ) );
		$this->assertFalse( Util_Java::validate( 0 ) );
		$this->assertFalse( Util_Java::validate( array( '/usr/bin/env' ) ) );
	}

	/**
	 * Shell-metacharacter inputs are refused before realpath() runs.
	 *
	 * @since 2.10.0
	 */
	public function test_validate_rejects_shell_metacharacters() {
		$this->require_valid_binary();

		/**
		 * Each of these is a character or sequence that has special
		 * meaning to a POSIX shell.
		 */
		$inputs = array(
			self::$valid_binary . '; foo',
			self::$valid_binary . ' | bar',
			self::$valid_binary . ' && baz',
			self::$valid_binary . ' `foo`',
			self::$valid_binary . ' $(foo)',
			self::$valid_binary . " 'foo'",
			self::$valid_binary . ' "foo"',
			self::$valid_binary . ' >/tmp/out',
			self::$valid_binary . ' <hello',
			self::$valid_binary . ' *',
			self::$valid_binary . ' ?',
		);

		foreach ( $inputs as $input ) {
			$this->assertFalse(
				Util_Java::validate( $input ),
				'Expected metacharacter input to be refused: ' . $input
			);
		}
	}

	/**
	 * A non-existent path must be refused.
	 *
	 * @since 2.10.0
	 */
	public function test_validate_rejects_non_existent_path() {
		$this->assertFalse( Util_Java::validate( '/usr/bin/nope_this_does_not_exist_xyz_w3tc' ) );
	}

	/**
	 * A real file outside the default allowlist (e.g. in /tmp) is
	 * refused even when it canonicalizes and is executable.
	 *
	 * @since 2.10.0
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
	 * @since 2.10.0
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
	 * @since 2.10.0
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
	 * @since 2.10.0
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
			/**
			 * `/usr/bin/java` is a symlink chain on most distros that
			 * resolves through `/etc/alternatives/java` under
			 * `/usr/lib/jvm`; the realpath-based validator needs the JVM
			 * root in the default allowlist or the documented config
			 * fails on every stock install.
			 */
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
	 * @since 2.10.0
	 */
	public function test_allowed_dirs_override_constant_uses_path_separator() {
		$override = '/opt/openjdk-17/bin' . PATH_SEPARATOR . '/usr/lib/jvm/java-17/bin';
		define( 'W3TC_JAVA_BIN_ALLOWED_DIRS', $override );

		/**
		 * The isolated subprocess re-bootstraps the test suite; pull the
		 * autoload again so we can reference the namespaced class.
		 */
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
	 * @since 2.10.0
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
	 * @since 2.10.0
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
	 * @since 2.10.0
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

	/**
	 * sanitize_yui_options() coerces numeric stack-size to an integer
	 * and drops non-numeric values. Kept in lockstep with the vendored
	 * Minify_YUICompressor::_getCmd() interpolation of `-Xss<value>`.
	 *
	 * @since 2.10.0
	 */
	public function test_sanitize_yui_options_stack_size() {
		$out = Util_Java::sanitize_yui_options(
			array( 'stack-size' => '256k; rm -rf /' )
		);
		$this->assertArrayNotHasKey( 'stack-size', $out );

		$out = Util_Java::sanitize_yui_options( array( 'stack-size' => '512' ) );
		$this->assertSame( 512, $out['stack-size'] );
	}

	/**
	 * Write a minimal ZIP/JAR (empty EOCD) to $path.
	 *
	 * @since 2.10.6
	 *
	 * @param string $path Destination path ending in .jar.
	 *
	 * @return void
	 */
	private static function write_dummy_jar( $path ) {
		$data = "PK\x05\x06" . str_repeat( "\x00", 18 );
		file_put_contents( $path, $data );
	}

	/**
	 * Non-string / empty JAR inputs must be refused.
	 *
	 * @since 2.10.6
	 */
	public function test_validate_jar_rejects_empty_and_non_string() {
		$this->assertFalse( Util_Java::validate_jar( '' ) );
		$this->assertFalse( Util_Java::validate_jar( null ) );
		$this->assertFalse( Util_Java::validate_jar( 0 ) );
		$this->assertFalse( Util_Java::validate_jar( array( '/tmp/x.jar' ) ) );
	}

	/**
	 * Shell-metacharacter JAR inputs are refused before realpath() runs.
	 *
	 * @since 2.10.6
	 */
	public function test_validate_jar_rejects_shell_metacharacters() {
		$inputs = array(
			'/usr/share/java/foo.jar; id',
			'/usr/share/java/foo.jar | bar',
			'/usr/share/java/foo.jar && baz',
			'/usr/share/java/foo.jar `foo`',
			'/usr/share/java/foo.jar $(foo)',
			"/usr/share/java/foo.jar 'foo'",
			'/usr/share/java/foo.jar "foo"',
		);

		foreach ( $inputs as $input ) {
			$this->assertFalse(
				Util_Java::validate_jar( $input ),
				'Expected metacharacter JAR input to be refused: ' . $input
			);
		}
	}

	/**
	 * A missing JAR path must be refused.
	 *
	 * @since 2.10.6
	 */
	public function test_validate_jar_rejects_missing_file() {
		$this->assertFalse( Util_Java::validate_jar( '/usr/share/java/nope_w3tc_missing.jar' ) );
	}

	/**
	 * A real ZIP named .jar outside the default allowlist is refused.
	 *
	 * @since 2.10.6
	 */
	public function test_validate_jar_rejects_path_outside_allowlist() {
		$tmp = sys_get_temp_dir() . '/w3tc-java-test-' . wp_generate_password( 8, false ) . '.jar';
		self::write_dummy_jar( $tmp );

		try {
			$this->assertFalse( Util_Java::validate_jar( $tmp ) );
		} finally {
			@unlink( $tmp );
		}
	}

	/**
	 * Wrong file types under an allowlisted directory are refused.
	 *
	 * @since 2.10.6
	 */
	public function test_validate_jar_rejects_wrong_file_type() {
		$dir = W3TC_DIR . '/tests';
		$php_named_jar = $dir . '/w3tc-java-test-evil.jar';
		$zip_named_php = $dir . '/w3tc-java-test-evil.php';
		$dir_named_jar = $dir . '/w3tc-java-test-dir.jar';

		file_put_contents( $php_named_jar, "<?php echo 'nope';\n" );
		self::write_dummy_jar( $zip_named_php );
		mkdir( $dir_named_jar );

		try {
			$this->assertFalse( Util_Java::validate_jar( $php_named_jar ), 'PHP content named .jar must be refused.' );
			$this->assertFalse( Util_Java::validate_jar( $zip_named_php ), 'ZIP named .php must be refused.' );
			$this->assertFalse( Util_Java::validate_jar( $dir_named_jar ), 'Directory named .jar must be refused.' );
		} finally {
			@unlink( $php_named_jar );
			@unlink( $zip_named_php );
			@rmdir( $dir_named_jar );
		}
	}

	/**
	 * A readable ZIP .jar under W3TC_DIR is accepted and canonicalized.
	 *
	 * @since 2.10.6
	 */
	public function test_validate_jar_accepts_allowlisted_jar() {
		$path = W3TC_DIR . '/tests/w3tc-java-test-ok.jar';
		self::write_dummy_jar( $path );

		try {
			$result = Util_Java::validate_jar( $path );
			$this->assertSame( realpath( $path ), $result );
		} finally {
			@unlink( $path );
		}
	}

	/**
	 * Traversal that resolves outside the allowlist is refused.
	 *
	 * @since 2.10.6
	 */
	public function test_validate_jar_rejects_traversal_outside_allowlist() {
		$outside = sys_get_temp_dir() . '/w3tc-java-test-outside-' . wp_generate_password( 8, false ) . '.jar';
		self::write_dummy_jar( $outside );
		$traversed = W3TC_DIR . '/tests/../../../../../../..' . $outside;

		try {
			$this->assertFalse( Util_Java::validate_jar( $traversed ) );
		} finally {
			@unlink( $outside );
		}
	}

	/**
	 * A symlink whose target leaves the allowlist is refused.
	 *
	 * @since 2.10.6
	 */
	public function test_validate_jar_rejects_symlink_escaping_allowlist() {
		if ( '\\' === DIRECTORY_SEPARATOR ) {
			$this->markTestSkipped( 'Symlink escape coverage is POSIX-only.' );
		}

		$outside = sys_get_temp_dir() . '/w3tc-java-test-link-target-' . wp_generate_password( 8, false ) . '.jar';
		$link    = W3TC_DIR . '/tests/w3tc-java-test-escape.jar';
		self::write_dummy_jar( $outside );
		if ( ! symlink( $outside, $link ) ) {
			@unlink( $outside );
			$this->markTestSkipped( 'Could not create symlink for JAR escape test.' );
		}

		try {
			$this->assertFalse( Util_Java::validate_jar( $link ) );
		} finally {
			@unlink( $link );
			@unlink( $outside );
		}
	}

	/**
	 * A symlink that stays inside the allowlist is accepted as its canonical target.
	 *
	 * @since 2.10.6
	 */
	public function test_validate_jar_accepts_symlink_inside_allowlist() {
		if ( '\\' === DIRECTORY_SEPARATOR ) {
			$this->markTestSkipped( 'Symlink coverage is POSIX-only.' );
		}

		$target = W3TC_DIR . '/tests/w3tc-java-test-link-target.jar';
		$link   = W3TC_DIR . '/tests/w3tc-java-test-link.jar';
		self::write_dummy_jar( $target );
		if ( ! symlink( $target, $link ) ) {
			@unlink( $target );
			$this->markTestSkipped( 'Could not create symlink for in-allowlist JAR test.' );
		}

		try {
			$this->assertSame( realpath( $target ), Util_Java::validate_jar( $link ) );
		} finally {
			@unlink( $link );
			@unlink( $target );
		}
	}

	/**
	 * Default JAR allowlist includes the plugin directory.
	 *
	 * @since 2.10.6
	 */
	public function test_allowed_jar_dirs_includes_plugin_dir() {
		if ( defined( 'W3TC_JAVA_JAR_ALLOWED_DIRS' ) ) {
			$this->markTestSkipped( 'W3TC_JAVA_JAR_ALLOWED_DIRS is already defined; cannot test defaults in-process.' );
		}

		$dirs     = Util_Java::allowed_jar_dirs();
		$expected = realpath( W3TC_DIR );
		$this->assertContains( false !== $expected ? $expected : W3TC_DIR, $dirs );
	}

	/**
	 * JAR allowlist override is parsed with PATH_SEPARATOR and canonicalized.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 * @since 2.10.6
	 */
	public function test_allowed_jar_dirs_override_constant() {
		$override = '/opt/w3tc-no-such-jars-a' . PATH_SEPARATOR . '/opt/w3tc-no-such-jars-b';
		define( 'W3TC_JAVA_JAR_ALLOWED_DIRS', $override );

		require_once dirname( __DIR__, 2 ) . '/Util_Java.php';

		$dirs = \W3TC\Util_Java::allowed_jar_dirs();
		$this->assertSame(
			array( '/opt/w3tc-no-such-jars-a', '/opt/w3tc-no-such-jars-b' ),
			$dirs
		);
	}

	/**
	 * validate_tools() requires both a valid Java binary and a valid JAR.
	 *
	 * @since 2.10.6
	 */
	public function test_validate_tools_requires_both_paths() {
		$this->require_valid_binary();

		$jar = W3TC_DIR . '/tests/w3tc-java-test-tools.jar';
		self::write_dummy_jar( $jar );

		try {
			$ok = Util_Java::validate_tools( self::$valid_binary, $jar, 'unit-test' );
			$this->assertIsArray( $ok );
			$this->assertSame( self::$valid_binary, $ok['java'] );
			$this->assertSame( realpath( $jar ), $ok['jar'] );

			$this->assertFalse(
				Util_Java::validate_tools( self::$valid_binary, '/tmp/nope-w3tc.jar', 'unit-test' )
			);
			$this->assertFalse(
				Util_Java::validate_tools( '/no/such/java', $jar, 'unit-test' )
			);
		} finally {
			@unlink( $jar );
		}
	}
}
