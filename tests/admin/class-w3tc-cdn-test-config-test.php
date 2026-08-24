<?php
/**
 * File: class-w3tc-cdn-test-config-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.10.6
 */

declare( strict_types = 1 );

use W3TC\Cdn_AdminActions;
use W3TC\Cdn_Util;
use W3TC\Dispatcher;

/**
 * Class: W3tc_Cdn_Test_Config_Test
 *
 * Test CDN uses saved settings. Posted `config` cannot change the
 * target, credentials, or engine.
 *
 * @since 2.10.6
 */
class W3tc_Cdn_Test_Config_Test extends WP_UnitTestCase {

	/**
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$_GET  = array();
		$_POST = array();
	}

	/**
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$_GET  = array();
		$_POST = array();

		$config = Dispatcher::config();
		$config->set( 'cdn.engine', '' );
		$config->set( 'cdn.ftp.host', '' );
		$config->set( 'cdn.ftp.user', '' );
		$config->set( 'cdn.ftp.pass', '' );
		$config->set( 'cdn.ftp.pubkey', '' );
		$config->set( 'cdn.ftp.privkey', '' );
		$config->set( 'cdn.s3.key', '' );
		$config->set( 'cdn.s3.secret', '' );
		$config->set( 'cdn.s3.bucket', '' );
		$config->set( 'cdn.s3_compatible.api_host', '' );
		$config->set( 'cdn.mirror.domain', array() );
		$config->set( 'cdn.bunnycdn.cdn_hostname', '' );
		$config->set( 'cdn.azure.user', '' );
		$config->set( 'cdn.azuremi.clientid', '' );
		$config->set( 'cdn.azuremi.user', '' );
		$config->set( 'cdn.google_drive.client_id', '' );

		parent::tearDown();
	}

	/**
	 * Empty saved engine is incomplete / invalid.
	 *
	 * @since 2.10.6
	 */
	public function test_incomplete_engine_is_rejected() {
		Dispatcher::config()->set( 'cdn.engine', '' );

		$engine = Cdn_Util::stored_test_engine( '', Dispatcher::config() );

		$this->assertTrue( is_wp_error( $engine ) );
		$this->assertSame( 'w3tc_cdn_test_engine', $engine->get_error_code() );
	}

	/**
	 * Posted engine that disagrees with saved settings is unsaved UI.
	 *
	 * @since 2.10.6
	 */
	public function test_posted_engine_mismatch_requires_save() {
		Dispatcher::config()->set( 'cdn.engine', 'ftp' );

		$engine = Cdn_Util::stored_test_engine( 's3', Dispatcher::config() );

		$this->assertTrue( is_wp_error( $engine ) );
		$this->assertSame( 'w3tc_cdn_test_unsaved', $engine->get_error_code() );
	}

	/**
	 * Posted FTP host cannot replace the saved host.
	 *
	 * @since 2.10.6
	 */
	public function test_posted_ftp_host_is_ignored() {
		$config = Dispatcher::config();
		$config->set( 'cdn.engine', 'ftp' );
		$config->set( 'cdn.ftp.host', 'example.com' );
		$config->set( 'cdn.ftp.user', 'saved-user' );

		$_POST['config'] = array(
			'host' => '127.0.0.1',
			'user' => 'attacker',
		);

		$resolved = ( new Cdn_AdminActions() )->cdn_test_resolved_config( 'ftp' );

		$this->assertTrue( $resolved['result'] );
		$this->assertSame( 'ftp', $resolved['engine'] );
		$this->assertSame( 'example.com', $resolved['config']['host'] );
		$this->assertSame( 'saved-user', $resolved['config']['user'] );
		$this->assertNotSame( '127.0.0.1', $resolved['config']['host'] );
	}

	/**
	 * Incomplete FTP still uses the empty saved host, not a posted one.
	 *
	 * @since 2.10.6
	 */
	public function test_incomplete_ftp_ignores_posted_host() {
		$config = Dispatcher::config();
		$config->set( 'cdn.engine', 'ftp' );
		$config->set( 'cdn.ftp.host', '' );

		$_POST['config'] = array( 'host' => 'evil.example' );

		$resolved = ( new Cdn_AdminActions() )->cdn_test_resolved_config( 'ftp' );

		$this->assertTrue( $resolved['result'] );
		$this->assertSame( '', $resolved['config']['host'] );
	}

	/**
	 * Saved loopback FTP host is still refused.
	 *
	 * @since 2.10.6
	 */
	public function test_stored_loopback_ftp_host_is_refused() {
		$config = Dispatcher::config();
		$config->set( 'cdn.engine', 'ftp' );
		$config->set( 'cdn.ftp.host', '127.0.0.1' );

		$resolved = ( new Cdn_AdminActions() )->cdn_test_resolved_config( 'ftp' );

		$this->assertFalse( $resolved['result'] );
		$this->assertStringContainsString( 'Refused CDN test target', $resolved['error'] );
	}

	/**
	 * Saved S3 credentials win over posted keys.
	 *
	 * @since 2.10.6
	 */
	public function test_s3_uses_stored_credentials() {
		$config = Dispatcher::config();
		$config->set( 'cdn.engine', 's3' );
		$config->set( 'cdn.s3.key', 'AKIASAVE' );
		$config->set( 'cdn.s3.secret', 'stored-secret' );
		$config->set( 'cdn.s3.bucket', 'saved-bucket' );

		$_POST['config'] = array(
			'key'    => 'AKIAATTACK',
			'secret' => 'posted-secret',
			'bucket' => 'posted-bucket',
		);

		$resolved = ( new Cdn_AdminActions() )->cdn_test_resolved_config( 's3' );

		$this->assertTrue( $resolved['result'] );
		$this->assertSame( 's3', $resolved['engine'] );
		$this->assertSame( 'AKIASAVE', $resolved['config']['key'] );
		$this->assertSame( 'stored-secret', $resolved['config']['secret'] );
		$this->assertSame( 'saved-bucket', $resolved['config']['bucket'] );
	}

	/**
	 * S3 Compatible uses the saved API host, not a posted endpoint.
	 *
	 * @since 2.10.6
	 */
	public function test_s3_compatible_uses_stored_api_host() {
		$config = Dispatcher::config();
		$config->set( 'cdn.engine', 's3_compatible' );
		$config->set( 'cdn.s3_compatible.api_host', 'example.net' );

		$_POST['config'] = array( 'api_host' => '127.0.0.1' );

		$resolved = ( new Cdn_AdminActions() )->cdn_test_resolved_config( 's3_compatible' );

		$this->assertTrue( $resolved['result'] );
		$this->assertSame( 's3_compatible', $resolved['engine'] );
		$this->assertSame( 'example.net', $resolved['config']['api_host'] );
	}

	/**
	 * Mirror uses saved domains.
	 *
	 * @since 2.10.6
	 */
	public function test_mirror_uses_stored_domain() {
		$config = Dispatcher::config();
		$config->set( 'cdn.engine', 'mirror' );
		$config->set( 'cdn.mirror.domain', array( 'example.org' ) );

		$_POST['config'] = array( 'domain' => array( 'evil.example' ) );

		$resolved = ( new Cdn_AdminActions() )->cdn_test_resolved_config( 'mirror' );

		$this->assertTrue( $resolved['result'] );
		$this->assertSame( array( 'example.org' ), $resolved['config']['domain'] );
	}

	/**
	 * BunnyCDN is testable from saved settings even though is_engine() omits it.
	 *
	 * @since 2.10.6
	 */
	public function test_bunnycdn_uses_stored_hostname() {
		$config = Dispatcher::config();
		$config->set( 'cdn.engine', 'bunnycdn' );
		$config->set( 'cdn.bunnycdn.cdn_hostname', 'pull.b-cdn.net' );

		$resolved = ( new Cdn_AdminActions() )->cdn_test_resolved_config( 'bunnycdn' );

		$this->assertTrue( $resolved['result'] );
		$this->assertSame( 'bunnycdn', $resolved['engine'] );
		$this->assertSame( 'pull.b-cdn.net', $resolved['config']['domain'] );
		$this->assertSame( 'pull.b-cdn.net', $resolved['config']['cdn_hostname'] );
	}

	/**
	 * Azure MI maps saved `cdn.azuremi.clientid` to the engine `client_id` key.
	 *
	 * @since 2.10.6
	 */
	public function test_azuremi_maps_stored_clientid_to_engine_key() {
		$config = Dispatcher::config();
		$config->set( 'cdn.engine', 'azuremi' );
		$config->set( 'cdn.azuremi.user', 'savedaccount' );
		$config->set( 'cdn.azuremi.clientid', 'saved-entra-id' );

		$_POST['config'] = array( 'client_id' => 'posted-entra-id' );

		$resolved = ( new Cdn_AdminActions() )->cdn_test_resolved_config( 'azuremi' );

		$this->assertTrue( $resolved['result'] );
		$this->assertSame( 'savedaccount', $resolved['config']['user'] );
		$this->assertSame( 'saved-entra-id', $resolved['config']['client_id'] );
		$this->assertArrayNotHasKey( 'clientid', $resolved['config'] );
	}

	/**
	 * Google Drive uses saved client id, not request config.
	 *
	 * @since 2.10.6
	 */
	public function test_google_drive_uses_stored_client_id() {
		$config = Dispatcher::config();
		$config->set( 'cdn.engine', 'google_drive' );
		$config->set( 'cdn.google_drive.client_id', 'saved-client' );

		$_POST['config'] = array( 'client_id' => 'posted-client' );

		$resolved = ( new Cdn_AdminActions() )->cdn_test_resolved_config( 'google_drive' );

		$this->assertTrue( $resolved['result'] );
		$this->assertSame( 'saved-client', $resolved['config']['client_id'] );
	}

	/**
	 * Azure uses the saved account name.
	 *
	 * @since 2.10.6
	 */
	public function test_azure_uses_stored_user() {
		$config = Dispatcher::config();
		$config->set( 'cdn.engine', 'azure' );
		$config->set( 'cdn.azure.user', 'savedaccount' );

		$_POST['config'] = array( 'user' => 'postedaccount' );

		$resolved = ( new Cdn_AdminActions() )->cdn_test_resolved_config( 'azure' );

		$this->assertTrue( $resolved['result'] );
		$this->assertSame( 'savedaccount', $resolved['config']['user'] );
	}

	/**
	 * Saved FTP SSH key paths are part of the engine config.
	 *
	 * @since 2.10.6
	 */
	public function test_ftp_includes_stored_ssh_keys() {
		$config = Dispatcher::config();
		$config->set( 'cdn.engine', 'ftp' );
		$config->set( 'cdn.ftp.host', 'example.com' );
		$config->set( 'cdn.ftp.pubkey', '/home/user/.ssh/id_rsa.pub' );
		$config->set( 'cdn.ftp.privkey', '/home/user/.ssh/id_rsa' );

		$resolved = ( new Cdn_AdminActions() )->cdn_test_resolved_config( 'ftp' );

		$this->assertTrue( $resolved['result'] );
		$this->assertSame( '/home/user/.ssh/id_rsa.pub', $resolved['config']['pubkey'] );
		$this->assertSame( '/home/user/.ssh/id_rsa', $resolved['config']['privkey'] );
	}

	/**
	 * The test handler no longer reads request config.
	 *
	 * @since 2.10.6
	 */
	public function test_handler_does_not_read_request_config() {
		$src = (string) file_get_contents( dirname( __DIR__, 2 ) . '/Cdn_AdminActions.php' );

		$this->assertSame( 1, preg_match( '/public function w3tc_cdn_test\(\) \{.*?\n\tpublic function cdn_test_resolved_config/s', $src, $m ) );

		$handler = $m[0];
		$this->assertStringNotContainsString( "get_array( 'config' )", $handler );
		$this->assertStringNotContainsString( 'json_decode', $handler );
		$this->assertStringContainsString( 'cdn_test_resolved_config', $handler );
	}

	/**
	 * The Test button posts engine + nonce only.
	 *
	 * @since 2.10.6
	 */
	public function test_browser_does_not_post_form_config() {
		$options = (string) file_get_contents( dirname( __DIR__, 2 ) . '/pub/js/options.js' );
		$cdn_on  = strpos( $options, 'jQuery("#cdn_test").on("click"' );
		$create  = strpos( $options, 'jQuery("#cdn_create_container").on("click"' );

		$this->assertNotFalse( $cdn_on );
		$this->assertNotFalse( $create );
		$this->assertGreaterThan( $cdn_on, $create );

		$block = substr( $options, $cdn_on, $create - $cdn_on );

		$this->assertStringContainsString( 'engine: metadata.type', $block );
		$this->assertStringNotContainsString( 'config[host]', $block );
		$this->assertStringNotContainsString( 'config[secret]', $block );
		$this->assertStringNotContainsString( 'config[key]', $block );
		$this->assertStringNotContainsString( 'w3tc_cdn_get_cnames', $block );
	}
}
