<?php
/**
 * File: class-w3tc-cdn-ftp-transport-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.11.1
 */

declare( strict_types = 1 );

use W3TC\Cdn_AdminNotes;
use W3TC\CdnEngine_Ftp;
use W3TC\Config;
use W3TC\ConfigCompiler;
use W3TC\ConfigKeysSchema;
use W3TC\Dispatcher;

/**
 * New FTP configs default to FTPS, and older saved configs keep their transport.
 *
 * @since 2.11.1
 */
class W3tc_Cdn_Ftp_Transport_Test extends WP_UnitTestCase {
	/**
	 * Shared config values changed by the notice tests.
	 *
	 * @since 2.11.1
	 *
	 * @var array
	 */
	private $saved_config = array();

	/**
	 * Directory holding the config files written by the load tests.
	 *
	 * @since 2.11.1
	 *
	 * @var string
	 */
	private $config_dir = '';

	/**
	 * Filters registered on the W3TC `config_filename` hook before the test.
	 *
	 * @since 2.11.1
	 *
	 * @var array|null
	 */
	private $saved_filename_filters = null;

	/**
	 * Remember the shared config values the notice tests change.
	 *
	 * @since 2.11.1
	 */
	public function set_up() {
		parent::set_up();

		$config = Dispatcher::config();

		foreach ( array( 'cdn.engine', 'cdn.ftp.type' ) as $key ) {
			$this->saved_config[ $key ] = $config->get_string( $key );
		}

		$this->saved_config['cdn.enabled'] = $config->get_boolean( 'cdn.enabled' );
	}

	/**
	 * Restore shared config values and remove config files written by the test.
	 *
	 * @since 2.11.1
	 */
	public function tear_down() {
		$config = Dispatcher::config();

		foreach ( $this->saved_config as $key => $value ) {
			$config->set( $key, $value );
		}

		unset( $_GET['page'] );

		if ( '' !== $this->config_dir ) {
			$GLOBALS['w3tc_actions']['config_filename'] = $this->saved_filename_filters;

			foreach ( (array) \glob( $this->config_dir . '/*.php' ) as $file ) {
				\unlink( $file );
			}

			\rmdir( $this->config_dir );
		}

		parent::tear_down();
	}

	/**
	 * Route W3TC config files to a directory owned by this test.
	 *
	 * @since 2.11.1
	 *
	 * @param array $files Config data to write, keyed by blog ID (0 for master).
	 */
	private function write_config_files( array $files ) {
		$this->config_dir = \get_temp_dir() . 'w3tc-ftp-transport-' . \wp_generate_password( 8, false );
		\wp_mkdir_p( $this->config_dir );

		$this->saved_filename_filters = $GLOBALS['w3tc_actions']['config_filename'] ?? null;

		$dir = $this->config_dir;

		$GLOBALS['w3tc_actions']['config_filename'] = array(
			function ( $data ) use ( $dir ) {
				$data['filename'] = $dir . '/' . \sprintf( '%06d', $data['blog_id'] ) . '.php';
				return $data;
			},
		);

		foreach ( $files as $blog_id => $data ) {
			\file_put_contents(
				Config::util_config_filename( $blog_id, false ),
				'<?php exit; ?>' . \wp_json_encode( $data )
			);
		}
	}

	/**
	 * A child blog that does not overload CDN settings uses the master transport.
	 *
	 * Child config files only store keys the child overloads, so the
	 * transport key is normally absent from them.
	 *
	 * @since 2.11.1
	 */
	public function test_child_without_cdn_overload_keeps_master_transport() {
		$this->write_config_files(
			array(
				0 => array(
					'version'      => W3TC_VERSION,
					'cdn.engine'   => 'ftp',
					'cdn.ftp.type' => 'ftps',
				),
				2 => array(
					'version' => W3TC_VERSION,
				),
			)
		);

		$config = new Config( 2 );

		$this->assertSame( 'ftps', $config->get_string( 'cdn.ftp.type' ) );
	}

	/**
	 * An older overloaded child that omits the transport inherits the master's secure transport.
	 *
	 * `2.10.7` always takes the ConfigCompiler path. A child saved at
	 * the running plugin version takes the Config fast path.
	 *
	 * @since 2.11.1
	 *
	 * @dataProvider omitted_type_child_cases
	 *
	 * @param string $master_type   Master `cdn.ftp.type`.
	 * @param string $child_version Child file version.
	 */
	public function test_overloaded_child_omitting_type_inherits_secure_master_transport( string $master_type, string $child_version ) {
		$this->write_config_files(
			array(
				0 => array(
					'version'             => W3TC_VERSION,
					'common.force_master' => false,
					'cdn.engine'          => 'ftp',
					'cdn.ftp.host'        => 'ftp.example.com',
					'cdn.ftp.type'        => $master_type,
				),
				2 => array(
					'version'                      => $child_version,
					'cdn.configuration_overloaded' => true,
					'cdn.engine'                   => 'ftp',
				),
			)
		);

		$config = new Config( 2 );

		$this->assertSame( $master_type, $config->get_string( 'cdn.ftp.type' ) );
		$this->assertSame( 'ftp.example.com', $config->get_string( 'cdn.ftp.host' ) );
	}

	/**
	 * @since 2.11.1
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function omitted_type_child_cases(): array {
		return array(
			'ftps master, 2.10.7 child'       => array( 'ftps', '2.10.7' ),
			'sftp master, 2.10.7 child'       => array( 'sftp', '2.10.7' ),
			'ftps master, same-version child' => array( 'ftps', W3TC_VERSION ),
			'sftp master, same-version child' => array( 'sftp', W3TC_VERSION ),
		);
	}

	/**
	 * An older master that never configured FTP moves to FTPS on load.
	 *
	 * Older master saves store `cdn.ftp.type => ''` even when FTP was never
	 * set up. `2.11.0` takes the same-version path until the plugin version
	 * moves past it; `2.10.7` always takes the ConfigCompiler path.
	 *
	 * @since 2.11.1
	 *
	 * @dataProvider older_master_versions
	 */
	public function test_older_master_without_ftp_host_loads_ftps( string $version ) {
		$this->write_config_files(
			array(
				0 => array(
					'version'      => $version,
					'cdn.engine'   => 'maxcdn',
					'cdn.ftp.host' => '',
					'cdn.ftp.type' => '',
				),
			)
		);

		$config = new Config( 0 );

		$this->assertSame( 'ftps', $config->get_string( 'cdn.ftp.type' ) );
	}

	/**
	 * An older master with a saved FTP host keeps plain FTP on load.
	 *
	 * @since 2.11.1
	 *
	 * @dataProvider older_master_versions
	 */
	public function test_older_master_with_ftp_host_stays_plain( string $version ) {
		$this->write_config_files(
			array(
				0 => array(
					'version'      => $version,
					'cdn.engine'   => 'ftp',
					'cdn.ftp.host' => 'ftp.example.com',
					'cdn.ftp.type' => '',
				),
			)
		);

		$config = new Config( 0 );

		$this->assertSame( '', $config->get_string( 'cdn.ftp.type' ) );
	}

	/**
	 * @since 2.11.1
	 *
	 * @return array<string, array{0: string}>
	 */
	public function older_master_versions(): array {
		return array(
			'2.11.0' => array( '2.11.0' ),
			'2.10.7' => array( '2.10.7' ),
		);
	}

	/**
	 * An older overloaded child that never configured FTP moves to FTPS.
	 *
	 * @since 2.11.1
	 */
	public function test_older_child_with_cdn_overload_without_ftp_host_loads_ftps() {
		$this->write_config_files(
			array(
				0 => array(
					'version'             => W3TC_VERSION,
					'common.force_master' => false,
					'cdn.engine'          => 'ftp',
					'cdn.ftp.host'        => '',
					'cdn.ftp.type'        => '',
				),
				2 => array(
					'version'                      => '2.10.7',
					'cdn.configuration_overloaded' => true,
					'cdn.engine'                   => 'ftp',
					'cdn.ftp.host'                 => '',
					'cdn.ftp.type'                 => '',
				),
			)
		);

		$config = new Config( 2 );

		$this->assertSame( 'ftps', $config->get_string( 'cdn.ftp.type' ) );
	}

	/**
	 * An older overloaded child that omits the host uses the master host.
	 *
	 * @since 2.11.1
	 */
	public function test_older_child_inheriting_master_ftp_host_stays_plain() {
		$this->write_config_files(
			array(
				0 => array(
					'version'             => W3TC_VERSION,
					'common.force_master' => false,
					'cdn.engine'          => 'ftp',
					'cdn.ftp.host'        => 'ftp.example.com',
					'cdn.ftp.type'        => '',
				),
				2 => array(
					'version'                      => '2.10.7',
					'cdn.configuration_overloaded' => true,
					'cdn.engine'                   => 'ftp',
					'cdn.ftp.type'                 => '',
				),
			)
		);

		$config = new Config( 2 );

		$this->assertSame( '', $config->get_string( 'cdn.ftp.type' ) );
	}

	/**
	 * A config with no saved file uses the FTPS schema default.
	 *
	 * @since 2.11.1
	 */
	public function test_new_ftp_config_defaults_to_ftps() {
		$keys = ConfigKeysSchema::get_keys();

		$this->assertSame( 'ftps', $keys['cdn.ftp.type']['default'] );
	}

	/**
	 * An older saved config that omitted the transport stays on plain FTP when a host is saved.
	 *
	 * @since 2.11.1
	 */
	public function test_omitted_ftp_type_on_older_config_with_host_stays_plain() {
		$normalized = ConfigCompiler::normalize_saved_ftp_type(
			array(
				'version'      => '2.11.0',
				'cdn.ftp.host' => 'ftp.example.com',
			)
		);

		$this->assertSame( '', $normalized['cdn.ftp.type'] );
	}

	/**
	 * An older saved config that never configured FTP moves to FTPS.
	 *
	 * @since 2.11.1
	 */
	public function test_unconfigured_ftp_on_older_config_uses_ftps() {
		$cases = array(
			'omitted type and host' => array( 'version' => '2.11.0' ),
			'empty type and host'   => array(
				'version'      => '2.11.0',
				'cdn.ftp.host' => '',
				'cdn.ftp.type' => '',
			),
		);

		foreach ( $cases as $label => $file_data ) {
			$normalized = ConfigCompiler::normalize_saved_ftp_type( $file_data );

			$this->assertSame( 'ftps', $normalized['cdn.ftp.type'], $label );
		}
	}

	/**
	 * A child file that omits the host is checked against the master host.
	 *
	 * @since 2.11.1
	 */
	public function test_older_child_omitting_host_uses_parent_host() {
		$normalized = ConfigCompiler::normalize_saved_ftp_type(
			array(
				'version'      => '2.11.0',
				'cdn.ftp.type' => '',
			),
			array( 'cdn.ftp.host' => 'ftp.example.com' )
		);

		$this->assertSame( '', $normalized['cdn.ftp.type'] );
	}

	/**
	 * An overloaded child that omits the transport keeps the master's plain FTP.
	 *
	 * The child still inherits the master's host, so assigning FTPS here
	 * would talk FTPS to a plain-FTP host.
	 *
	 * @since 2.11.1
	 */
	public function test_overloaded_child_omitting_type_keeps_master_plain_ftp() {
		$this->write_config_files(
			array(
				0 => array(
					'version'             => W3TC_VERSION,
					'common.force_master' => false,
					'cdn.engine'          => 'ftp',
					'cdn.ftp.host'        => 'ftp.example.com',
					'cdn.ftp.type'        => '',
				),
				2 => array(
					'version'                      => '2.11.1',
					'cdn.configuration_overloaded' => true,
					'cdn.engine'                   => 'ftp',
				),
			)
		);

		$config = new Config( 2 );

		$this->assertSame( '', $config->get_string( 'cdn.ftp.type' ) );
		$this->assertSame( 'ftp.example.com', $config->get_string( 'cdn.ftp.host' ) );
	}

	/**
	 * A current-version saved config that omitted the transport gets the FTPS default.
	 *
	 * @since 2.11.1
	 */
	public function test_omitted_ftp_type_on_current_config_uses_ftps() {
		$normalized = ConfigCompiler::normalize_saved_ftp_type(
			array(
				'version' => '2.11.1',
			)
		);

		$this->assertSame( 'ftps', $normalized['cdn.ftp.type'] );
	}

	/**
	 * A saved transport for a configured host is not replaced by the new default.
	 *
	 * @since 2.11.1
	 */
	public function test_saved_ftp_type_is_unchanged() {
		$cases = array( '', 'ftp', 'ftps', 'sftp' );

		foreach ( $cases as $saved ) {
			foreach ( array( '2.11.0', '2.11.1' ) as $version ) {
				$normalized = ConfigCompiler::normalize_saved_ftp_type(
					array(
						'version'      => $version,
						'cdn.ftp.host' => 'ftp.example.com',
						'cdn.ftp.type' => $saved,
					)
				);

				$this->assertSame( $saved, $normalized['cdn.ftp.type'] );
			}
		}
	}

	/**
	 * A non-empty transport is kept even when no host is saved.
	 *
	 * @since 2.11.1
	 */
	public function test_saved_non_empty_ftp_type_without_host_is_unchanged() {
		foreach ( array( 'ftp', 'sftp' ) as $saved ) {
			$normalized = ConfigCompiler::normalize_saved_ftp_type(
				array(
					'version'      => '2.11.0',
					'cdn.ftp.type' => $saved,
				)
			);

			$this->assertSame( $saved, $normalized['cdn.ftp.type'] );
		}
	}

	/**
	 * Saving an older master with no FTP host stores the FTPS default.
	 *
	 * @since 2.11.1
	 */
	public function test_save_older_master_without_host_stores_ftps() {
		$this->write_config_files(
			array(
				0 => array(
					'version'      => '2.10.7',
					'cdn.engine'   => 'maxcdn',
					'cdn.ftp.host' => '',
					'cdn.ftp.type' => '',
				),
			)
		);

		$config = new Config( 0 );
		$config->save();

		$saved = Config::util_array_from_storage( 0, false );

		$this->assertSame( 'ftps', $saved['cdn.ftp.type'] );
	}

	/**
	 * Saving an older master with an FTP host keeps plain FTP.
	 *
	 * @since 2.11.1
	 */
	public function test_save_older_master_with_host_stores_plain_ftp() {
		$this->write_config_files(
			array(
				0 => array(
					'version'      => '2.10.7',
					'cdn.engine'   => 'ftp',
					'cdn.ftp.host' => 'ftp.example.com',
					'cdn.ftp.type' => '',
				),
			)
		);

		$config = new Config( 0 );
		$config->save();

		$saved = Config::util_array_from_storage( 0, false );

		$this->assertSame( '', $saved['cdn.ftp.type'] );
	}

	/**
	 * FTPS reports a configuration error when OpenSSL FTP support is missing.
	 *
	 * @since 2.11.1
	 */
	public function test_ftps_connect_without_openssl_returns_configuration_error() {
		if ( \function_exists( 'ftp_ssl_connect' ) ) {
			$this->markTestSkipped( 'ftp_ssl_connect() is available.' );
		}

		$error  = '';
		$engine = new CdnEngine_Ftp(
			array(
				'host' => 'ftp.example.com',
				'type' => 'ftps',
			)
		);

		$this->assertFalse( $engine->_connect( $error ) );
		$this->assertSame( 'FTPS is not available. PHP must be built with OpenSSL support.', $error );
	}

	/**
	 * Plain-FTP admin warning applies for empty and legacy `ftp` transports.
	 *
	 * @since 2.11.1
	 *
	 * @dataProvider plain_ftp_notice_shown_types
	 */
	public function test_plain_ftp_notice_shown_for_insecure_transports( string $type ) {
		$config = Dispatcher::config();
		$config->set( 'cdn.enabled', true );
		$config->set( 'cdn.engine', 'ftp' );
		$config->set( 'cdn.ftp.type', $type );

		$notes  = new Cdn_AdminNotes();
		$result = $notes->plain_ftp_notice( array() );

		$this->assertArrayHasKey( 'cdn_ftp_plain', $result );
		$this->assertStringContainsString( 'plain FTP', $result['cdn_ftp_plain'] );
	}

	/**
	 * @since 2.11.1
	 *
	 * @return array<string, array{0: string}>
	 */
	public function plain_ftp_notice_shown_types(): array {
		return array(
			'empty type' => array( '' ),
			'legacy ftp' => array( 'ftp' ),
		);
	}

	/**
	 * Plain-FTP admin warning is omitted for encrypted transports.
	 *
	 * @since 2.11.1
	 *
	 * @dataProvider plain_ftp_notice_hidden_types
	 */
	public function test_plain_ftp_notice_hidden_for_secure_transports( string $type ) {
		$config = Dispatcher::config();
		$config->set( 'cdn.enabled', true );
		$config->set( 'cdn.engine', 'ftp' );
		$config->set( 'cdn.ftp.type', $type );

		$notes  = new Cdn_AdminNotes();
		$result = $notes->plain_ftp_notice( array() );

		$this->assertArrayNotHasKey( 'cdn_ftp_plain', $result );
	}

	/**
	 * A disabled plain-FTP CDN only shows the warning on the CDN page.
	 *
	 * @since 2.11.1
	 */
	public function test_plain_ftp_notice_with_cdn_disabled_shows_only_on_cdn_page() {
		$config = Dispatcher::config();
		$config->set( 'cdn.enabled', false );
		$config->set( 'cdn.engine', 'ftp' );
		$config->set( 'cdn.ftp.type', '' );

		$notes = new Cdn_AdminNotes();

		$_GET['page'] = 'w3tc_dashboard';
		$this->assertArrayNotHasKey( 'cdn_ftp_plain', $notes->plain_ftp_notice( array() ) );

		$_GET['page'] = 'w3tc_cdn';
		$this->assertArrayHasKey( 'cdn_ftp_plain', $notes->plain_ftp_notice( array() ) );
	}

	/**
	 * @since 2.11.1
	 *
	 * @return array<string, array{0: string}>
	 */
	public function plain_ftp_notice_hidden_types(): array {
		return array(
			'ftps' => array( 'ftps' ),
			'sftp' => array( 'sftp' ),
		);
	}

	/**
	 * The OAuth nonce is a 32-byte hex value, not an MD5 of the clock.
	 *
	 * @since 2.11.1
	 */
	public function test_oauth_nonce_uses_random_bytes() {
		require_once W3TC_LIB_DIR . '/OAuth/W3tcOAuth.php';

		$method = new ReflectionMethod( 'W3tcOAuthRequest', 'generate_nonce' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$first  = $method->invoke( null );
		$second = $method->invoke( null );

		$this->assertSame( 64, \strlen( $first ) );
		$this->assertSame( 1, \preg_match( '/^[a-f0-9]{64}$/', $first ) );
		$this->assertNotSame( $first, $second );
	}
}
