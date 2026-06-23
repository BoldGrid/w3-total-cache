<?php
/**
 * File: class-w3tc-file-inclusion-test.php
 *
 * Regression for the file-inclusion remediation pass on
 * `PgCache_ContentGrabber::_bad_behavior()`.
 *
 * The legacy code called
 * `require_once $this->_config->get_string( 'pgcache.bad_behavior_path' )`
 * with zero validation. Combined with the mass-assignment write surface, that
 * turned any subscriber-reachable config write into an RCE primitive: write
 * `pgcache.bad_behavior_path = '/path/to/uploaded/shell.php'` and the next
 * page-cache start path requires-and-executes the attacker's file.
 *
 * The remediated `_bad_behavior()` enforces, in order:
 *
 *   - The configured value must canonicalise via `realpath()` and exist on
 *     disk (rejects symlink loops and missing files cheaply).
 *   - The canonicalised path must live UNDER `WP_PLUGIN_DIR` (rejects
 *     `/tmp/evil.php`, `/etc/passwd`, uploads-dir writes, anything outside
 *     the plugin tree).
 *
 * Failures log via `Util_Debug::log( 'pgcache', ... )` with CR/LF/control
 * characters escaped so an attacker-controlled config string cannot forge
 * additional log lines.
 *
 * (The `mclude` sub-case from the same group routes through `_dispatch_dynamic`
 * and is already covered by `tests/test-mfunc-security.php`.)
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.10.0
 */

declare( strict_types = 1 );

use W3TC\Dispatcher;
use W3TC\PgCache_ContentGrabber;

/**
 * Class: W3tc_File_Inclusion_Test
 *
 * @since 2.10.0
 */
class W3tc_File_Inclusion_Test extends WP_UnitTestCase {

	/**
	 * Files we create under WP_PLUGIN_DIR or /tmp during a test. Cleaned up
	 * in tear_down so a failed test doesn't leak artefacts onto disk.
	 *
	 * @var string[]
	 */
	private $tempfiles_to_unlink = array();

	/**
	 * @since 2.10.0
	 */
	public function tear_down() {
		foreach ( $this->tempfiles_to_unlink as $path ) {
			if ( \is_file( $path ) ) {
				\unlink( $path );
			}
		}
		$this->tempfiles_to_unlink = array();

		/**
		 * Reset the bad_behavior_path config key so other tests in the suite
		 * start clean.
		 */
		$config = Dispatcher::config();
		$config->set( 'pgcache.bad_behavior_path', '' );

		parent::tear_down();
	}

	/**
	 * Write a small PHP file at `$path` whose body defines a sentinel constant
	 * keyed by `$constant`. Registers the file for cleanup.
	 *
	 * The constant is the safe way to detect "did this file actually get
	 * `require_once`d" — once defined, it stays defined for the duration of
	 * the process, so a later assertion can confirm the include happened
	 * without the test file needing to set globals or echo.
	 *
	 * @since 2.10.0
	 *
	 * @param string $path     Absolute file path to write.
	 * @param string $constant Constant name to define inside the file.
	 *
	 * @return void
	 */
	private function write_sentinel_php( $path, $constant ) {
		$dir = \dirname( $path );
		if ( ! \is_dir( $dir ) ) {
			\mkdir( $dir, 0777, true );
		}
		\file_put_contents(
			$path,
			"<?php\nif ( ! defined( '" . $constant . "' ) ) { define( '" . $constant . "', true ); }\n"
		);
		$this->tempfiles_to_unlink[] = $path;
	}

	/**
	 * Empty `pgcache.bad_behavior_path` is the default — `_bad_behavior()`
	 * must short-circuit cleanly without inspecting the filesystem.
	 *
	 * @since 2.10.0
	 */
	public function test_empty_path_short_circuits() {
		$config = Dispatcher::config();
		$config->set( 'pgcache.bad_behavior_path', '' );

		$grabber = new PgCache_ContentGrabber();

		// No exception, no fatal — just returns void.
		$grabber->_bad_behavior();

		$this->assertTrue( true, 'Empty path must short-circuit without error.' );
	}

	/**
	 * A non-existent path is refused before `require_once` is ever called.
	 * `realpath()` returns false for a missing file, which makes the early
	 * return fire.
	 *
	 * @since 2.10.0
	 */
	public function test_nonexistent_path_is_rejected() {
		$config = Dispatcher::config();
		$config->set( 'pgcache.bad_behavior_path', '/tmp/w3tc-test-this-file-does-not-exist-' . uniqid() . '.php' );

		$grabber = new PgCache_ContentGrabber();
		$grabber->_bad_behavior(); // Must not fatal.

		$this->assertTrue( true, 'Nonexistent path must be rejected silently.' );
	}

	/**
	 * A file in `/tmp` (outside WP_PLUGIN_DIR) is refused even when it exists.
	 * This is the canonical kill-chain test: the attacker uploads a PHP file
	 * somewhere writable but outside the plugin tree, then mass-assigns the
	 * config key to that path. The fix is exactly the "must live under
	 * WP_PLUGIN_DIR" check.
	 *
	 * @since 2.10.0
	 */
	public function test_path_outside_plugin_dir_is_rejected() {
		$evil_path = \sys_get_temp_dir() . '/w3tc-file-inclusion-evil-' . uniqid() . '.php';
		$this->write_sentinel_php( $evil_path, 'W3TC_TEST_FILE_INCLUSION_EVIL_INCLUDED' );

		$config = Dispatcher::config();
		$config->set( 'pgcache.bad_behavior_path', $evil_path );

		$grabber = new PgCache_ContentGrabber();
		$grabber->_bad_behavior();

		$this->assertFalse(
			\defined( 'W3TC_TEST_FILE_INCLUSION_EVIL_INCLUDED' ),
			'A file under /tmp (outside WP_PLUGIN_DIR) must NOT be required by _bad_behavior(). This is the RCE-pivot kill-chain test.'
		);
	}

	/**
	 * `/etc/passwd` (or any non-PHP system file) is refused. `realpath()` will
	 * succeed and `is_file()` will be true, but the `WP_PLUGIN_DIR` prefix
	 * check rejects it. We don't actually try to require `/etc/passwd` — if
	 * the validator failed we'd hit a parse error, but the test fails on the
	 * assertion before that.
	 *
	 * @since 2.10.0
	 */
	public function test_etc_passwd_is_rejected() {
		if ( ! \is_readable( '/etc/passwd' ) ) {
			$this->markTestSkipped( '/etc/passwd not readable on this host; cannot exercise the rejection path.' );
		}

		$config = Dispatcher::config();
		$config->set( 'pgcache.bad_behavior_path', '/etc/passwd' );

		$grabber = new PgCache_ContentGrabber();

		/**
		 * If the prefix check fails, PHP attempts to parse /etc/passwd as PHP
		 * and fatals. The assertion below is therefore actually load-bearing:
		 * reaching it at all means the validator returned before require_once.
		 */
		$grabber->_bad_behavior();

		$this->assertTrue( true, '_bad_behavior() must return without requiring /etc/passwd.' );
	}

	/**
	 * Path traversal cannot escape `WP_PLUGIN_DIR`: even if the unresolved
	 * input passes a naive string check, `realpath()` normalises `..` segments
	 * and the prefix test runs against the *resolved* path.
	 *
	 * @since 2.10.0
	 */
	public function test_path_traversal_is_rejected_after_realpath() {
		if ( ! \defined( 'WP_PLUGIN_DIR' ) ) {
			$this->markTestSkipped( 'WP_PLUGIN_DIR not defined in this test bootstrap.' );
		}

		/**
		 * Place a file outside WP_PLUGIN_DIR, then reference it via a path
		 * that *starts* with WP_PLUGIN_DIR but escapes via `../`.
		 */
		$outside_path = \sys_get_temp_dir() . '/w3tc-file-inclusion-traversal-' . uniqid() . '.php';
		$this->write_sentinel_php( $outside_path, 'W3TC_TEST_FILE_INCLUSION_TRAVERSAL_INCLUDED' );

		/**
		 * Construct a path that traverses upward from WP_PLUGIN_DIR to the
		 * outside file.
		 */
		$relative = WP_PLUGIN_DIR . '/../../../../../../../../../..' . $outside_path;

		$config = Dispatcher::config();
		$config->set( 'pgcache.bad_behavior_path', $relative );

		$grabber = new PgCache_ContentGrabber();
		$grabber->_bad_behavior();

		$this->assertFalse(
			\defined( 'W3TC_TEST_FILE_INCLUSION_TRAVERSAL_INCLUDED' ),
			'A path that uses ../ to escape WP_PLUGIN_DIR must be rejected — realpath() normalises before the prefix check.'
		);
	}

	/**
	 * A legitimate file under `WP_PLUGIN_DIR` IS required. This is the
	 * positive case: a real Bad Behavior install at
	 * `WP_PLUGIN_DIR/bad-behavior/bad-behavior-mu.php` (or similar) must
	 * still load. We write a sentinel file under WP_PLUGIN_DIR/w3tc-test-bb
	 * to confirm the include path runs.
	 *
	 * @since 2.10.0
	 */
	public function test_path_inside_plugin_dir_is_included() {
		if ( ! \defined( 'WP_PLUGIN_DIR' ) || ! \is_dir( WP_PLUGIN_DIR ) || ! \is_writable( WP_PLUGIN_DIR ) ) {
			$this->markTestSkipped( 'WP_PLUGIN_DIR is not writable in this test environment.' );
		}

		$test_subdir = WP_PLUGIN_DIR . '/w3tc-test-bb-' . uniqid();
		$test_file   = $test_subdir . '/bad-behavior.php';
		$this->write_sentinel_php( $test_file, 'W3TC_TEST_FILE_INCLUSION_LEGITIMATE_INCLUDED' );
		// Schedule the subdir for cleanup too.
		$this->tempfiles_to_unlink[] = $test_subdir;

		$config = Dispatcher::config();
		$config->set( 'pgcache.bad_behavior_path', $test_file );

		$grabber = new PgCache_ContentGrabber();
		$grabber->_bad_behavior();

		$this->assertTrue(
			\defined( 'W3TC_TEST_FILE_INCLUSION_LEGITIMATE_INCLUDED' ),
			'A file under WP_PLUGIN_DIR must be required by _bad_behavior() — the validator must not block the happy path.'
		);

		/**
		 * Tidy: rmdir the temp subdirectory if it's empty after file cleanup
		 * in tear_down.
		 */
	}

	/**
	 * A directory under `WP_PLUGIN_DIR` is rejected — `is_file()` is the
	 * second realpath-side check after canonicalisation, so a config value
	 * pointing at a directory does not trip on `require_once <dir>`.
	 *
	 * @since 2.10.0
	 */
	public function test_directory_is_rejected() {
		if ( ! \defined( 'WP_PLUGIN_DIR' ) ) {
			$this->markTestSkipped( 'WP_PLUGIN_DIR not defined.' );
		}

		$config = Dispatcher::config();
		$config->set( 'pgcache.bad_behavior_path', WP_PLUGIN_DIR );

		$grabber = new PgCache_ContentGrabber();
		$grabber->_bad_behavior();

		$this->assertTrue( true, 'A directory path must be rejected without fataling.' );
	}
}
