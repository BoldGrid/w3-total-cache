<?php
/**
 * File: class-w3tc-secret-clear-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      X.X.X
 */

declare( strict_types = 1 );

use W3TC\Config;
use W3TC\Dispatcher;
use W3TC\Generic_AdminActions_Default;

/**
 * Class: W3tc_Secret_Clear_Test
 *
 * Pins the three-way decision matrix for `secret`-flagged config keys
 * inside `Generic_AdminActions_Default::read_request()`:
 *
 *   POST value     companion `__w3tc_clear`     net effect
 *   ───────────────────────────────────────────────────────
 *   ''             absent / not '1'             preserve stored value
 *   ''             '1'                          set stored value to ''
 *   'new-value'    absent / not '1'             rotate to 'new-value'
 *   'new-value'    '1'                          set stored value to ''
 *
 * Without the explicit-clear path there is no way to blank a stored
 * credential through the UI once the masked input renders `value=""`
 * (the empty-POST-preserves-secret rule from #1321 swallows the
 * obvious "type nothing and Save" flow). The license-removal
 * regression that surfaced after #1321 merged is the proximate driver
 * for this test.
 *
 * @since X.X.X
 */
class W3tc_Secret_Clear_Test extends WP_UnitTestCase {

	/**
	 * Ensure the cache temp directory `Config::save()` needs is present
	 * in test fixtures that don't provision it.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$tmp = WP_CONTENT_DIR . '/cache/tmp';
		if ( ! is_dir( $tmp ) ) {
			@mkdir( $tmp, 0755, true );
		}
		$_GET  = array();
		$_POST = array();
	}

	/**
	 * Reset request superglobals between tests so a previous fixture
	 * doesn't leak into the next — `Util_Request::get_request()` reads
	 * `$_GET + $_POST` directly.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$_GET  = array();
		$_POST = array();
		parent::tearDown();
	}

	/**
	 * Helper: build a fresh, empty Config object and seed a single
	 * secret-flagged key in memory (no disk write needed).
	 *
	 * @since X.X.X
	 *
	 * @param string $key   Config key.
	 * @param string $value Stored plaintext.
	 *
	 * @return Config
	 */
	private function seeded_config( string $key, string $value ): Config {
		$config = new Config();

		$ref  = new \ReflectionObject( $config );
		$prop = $ref->getProperty( '_data' );
		$prop->setAccessible( true );
		$data         = $prop->getValue( $config );
		$data[ $key ] = $value;
		$prop->setValue( $config, $data );

		return $config;
	}

	/**
	 * Empty POST with NO clear companion MUST leave the stored secret
	 * untouched — that's the #1321 empty-POST-preserves-secret rule.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_empty_post_without_clear_preserves_secret() {
		$_POST                       = array();
		$_POST['plugin__license_key'] = '';

		$config = $this->seeded_config( 'plugin.license_key', 'KEEP-THIS-32-CHAR-LICENSE-KEYxx' );

		$admin = new Generic_AdminActions_Default();
		$admin->read_request( $config );

		$this->assertSame(
			'KEEP-THIS-32-CHAR-LICENSE-KEYxx',
			$config->get_string( 'plugin.license_key' ),
			'Saving Settings with an untouched masked field must not blank the stored credential.'
		);
	}

	/**
	 * Empty POST WITH the clear companion MUST blank the stored secret
	 * — that's the explicit "Remove on save" path users need to drop a
	 * credential through the UI.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_empty_post_with_clear_blanks_secret() {
		$_POST                                       = array();
		$_POST['plugin__license_key']                 = '';
		$_POST['plugin__license_key__w3tc_clear']     = '1';

		$config = $this->seeded_config( 'plugin.license_key', 'CLEAR-THIS-32-CHAR-LICENSE-KEYx' );

		$admin = new Generic_AdminActions_Default();
		$admin->read_request( $config );

		$this->assertSame(
			'',
			$config->get_string( 'plugin.license_key' ),
			'A submitted `__w3tc_clear=1` companion must wipe the stored credential.'
		);
	}

	/**
	 * The clear companion takes precedence over a typed-in new value
	 * — an admin who checks "Remove on save" while also typing into
	 * the field gets the clear, not the rotation. This matches the
	 * principle of least surprise (checkbox is the explicit action).
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_clear_companion_overrides_typed_value() {
		$_POST                                       = array();
		$_POST['plugin__license_key']                 = 'SHOULD-NOT-BE-WRITTEN';
		$_POST['plugin__license_key__w3tc_clear']     = '1';

		$config = $this->seeded_config( 'plugin.license_key', 'OLD-VALUE' );

		$admin = new Generic_AdminActions_Default();
		$admin->read_request( $config );

		$this->assertSame(
			'',
			$config->get_string( 'plugin.license_key' ),
			'Clear companion must win over a simultaneously-submitted new value.'
		);
	}

	/**
	 * Rotating to a new (non-empty) value continues to work — the
	 * empty-POST and clear paths must not interfere with the common
	 * credential-rotation flow.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_non_empty_post_rotates_secret() {
		$_POST                       = array();
		$_POST['plugin__license_key'] = 'NEW-32-CHAR-LICENSE-KEYxxxxxxxxx';

		$config = $this->seeded_config( 'plugin.license_key', 'OLD-VALUE' );

		$admin = new Generic_AdminActions_Default();
		$admin->read_request( $config );

		$this->assertSame(
			'NEW-32-CHAR-LICENSE-KEYxxxxxxxxx',
			$config->get_string( 'plugin.license_key' ),
			'Submitting a non-empty value must rotate the stored credential.'
		);
	}

	/**
	 * The `__w3tc_clear` request keys themselves MUST NOT be treated as
	 * standalone config keys — they're sidecars to their parent secret,
	 * processed inside the secret block.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_clear_companion_is_not_processed_as_config_key() {
		$_POST                                       = array();
		$_POST['plugin__license_key__w3tc_clear']     = '1';

		$config = $this->seeded_config( 'plugin.license_key', 'KEEP-ME' );

		$admin = new Generic_AdminActions_Default();
		$admin->read_request( $config );

		// Without a parent `plugin__license_key` post key the clear block
		// for that secret never runs, so the stored value is untouched —
		// the companion alone does nothing.
		$this->assertSame(
			'KEEP-ME',
			$config->get_string( 'plugin.license_key' ),
			'A stray clear companion without its parent must be inert (no spurious mutations).'
		);
	}
}
