<?php
/**
 * File: class-w3tc-tls-defaults-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.10.6
 */

declare( strict_types = 1 );

use W3TC\Cache_Redis;
use W3TC\Cdn_BunnyCdn_Api;
use W3TC\ConfigKeysSchema;
use W3TC\Dispatcher;

/**
 * Class: W3tc_Tls_Defaults_Test
 *
 * BunnyCDN and Redis TLS connections verify peers by default. An
 * explicit compatibility opt-out is the only disable path.
 *
 * @since 2.10.6
 */
class W3tc_Tls_Defaults_Test extends WP_UnitTestCase {

	/**
	 * Captured HTTP request args.
	 *
	 * @var array|null
	 */
	private $http_args;

	/**
	 * Captured HTTP args keyed by URL.
	 *
	 * @var array
	 */
	private $http_by_url = array();

	/**
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->http_args   = null;
		$this->http_by_url = array();
		\remove_all_filters( 'pre_http_request' );
		\remove_all_filters( 'https_ssl_verify' );
	}

	/**
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function tear_down() {
		\remove_all_filters( 'pre_http_request' );
		\remove_all_filters( 'https_ssl_verify' );
		$this->http_args   = null;
		$this->http_by_url = array();
		$this->reset_bunny_tls_config();
		parent::tear_down();
	}

	/**
	 * Schema defaults verify TLS for both BunnyCDN surfaces and Redis.
	 *
	 * @since 2.10.6
	 */
	public function test_config_keys_default_to_verified() {
		foreach (
			array(
				'cdn.bunnycdn.verify_tls_certificates',
				'cdnfsd.bunnycdn.verify_tls_certificates',
				'dbcache.redis.verify_tls_certificates',
				'objectcache.redis.verify_tls_certificates',
				'pgcache.redis.verify_tls_certificates',
				'minify.redis.verify_tls_certificates',
			) as $key
		) {
			$descriptor = ConfigKeysSchema::descriptor( $key );
			$this->assertIsArray( $descriptor, $key . ' must be in ConfigKeys' );
			$this->assertSame( 'boolean', $descriptor['type'], $key );
			$this->assertTrue( $descriptor['default'], $key . ' must default to verified' );
		}

		$this->assertSame( 'w3tc_cdn', ConfigKeysSchema::dedicated_page( 'cdn.bunnycdn.verify_tls_certificates' ) );
		$this->assertSame( 'w3tc_cdnfsd', ConfigKeysSchema::dedicated_page( 'cdnfsd.bunnycdn.verify_tls_certificates' ) );
	}

	/**
	 * Missing BunnyCDN client flag verifies; only explicit false opts out.
	 *
	 * @since 2.10.6
	 */
	public function test_bunnycdn_missing_config_verifies() {
		$this->assertTrue( Cdn_BunnyCdn_Api::verify_tls_certificates_enabled( array() ) );
		$this->assertTrue( Cdn_BunnyCdn_Api::verify_tls_certificates_enabled( array( 'account_api_key' => 'k' ) ) );
		$this->assertTrue( Cdn_BunnyCdn_Api::verify_tls_certificates_enabled( array( 'verify_tls_certificates' => true ) ) );
		$this->assertFalse( Cdn_BunnyCdn_Api::verify_tls_certificates_enabled( array( 'verify_tls_certificates' => false ) ) );
		$this->assertFalse( Cdn_BunnyCdn_Api::verify_tls_certificates_enabled( array( 'verify_tls_certificates' => 0 ) ) );
	}

	/**
	 * Saved config helper uses the BunnyCDN (or FSD) key and defaults true.
	 *
	 * @since 2.10.6
	 */
	public function test_bunnycdn_client_config_from_saved_defaults_true() {
		$config = Dispatcher::config();
		$config->set( 'cdn.bunnycdn.verify_tls_certificates', true );
		$config->set( 'cdnfsd.bunnycdn.verify_tls_certificates', false );

		$cdn = Cdn_BunnyCdn_Api::client_config_from_saved( 'acct-key', $config );
		$this->assertSame( 'acct-key', $cdn['account_api_key'] );
		$this->assertTrue( $cdn['verify_tls_certificates'] );

		$fsd = Cdn_BunnyCdn_Api::client_config_from_saved(
			'acct-key',
			$config,
			'cdnfsd.bunnycdn.verify_tls_certificates'
		);
		$this->assertFalse( $fsd['verify_tls_certificates'] );

		$config->set( 'cdn.bunnycdn.verify_tls_certificates', true );
		$config->set( 'cdnfsd.bunnycdn.verify_tls_certificates', true );
	}

	/**
	 * Default BunnyCDN client sends sslverify true and never forces the
	 * https_ssl_verify filter off.
	 *
	 * @since 2.10.6
	 */
	public function test_bunnycdn_request_verifies_by_default() {
		$api = new Cdn_BunnyCdn_Api( array( 'account_api_key' => 'test-key' ) );
		$this->assertTrue( $api->https_ssl_verify( false ) );
		$this->assertTrue( $api->https_ssl_verify( true ) );

		$this->mock_http_ok();
		$api->list_pull_zones();

		$this->assertIsArray( $this->http_args );
		$this->assertTrue( $this->http_args['sslverify'] );
	}

	/**
	 * Explicit BunnyCDN opt-out is the only path that disables sslverify.
	 *
	 * @since 2.10.6
	 */
	public function test_bunnycdn_explicit_opt_out_disables_verify() {
		$api = new Cdn_BunnyCdn_Api(
			array(
				'account_api_key'         => 'test-key',
				'verify_tls_certificates' => false,
			)
		);
		$this->assertFalse( $api->https_ssl_verify( true ) );

		$this->mock_http_ok();
		$api->list_pull_zones();

		$this->assertIsArray( $this->http_args );
		$this->assertFalse( $this->http_args['sslverify'] );
	}

	/**
	 * Transport failures include the HTTP API error so TLS issues are
	 * actionable.
	 *
	 * @since 2.10.6
	 */
	public function test_bunnycdn_transport_error_is_actionable() {
		\add_filter(
			'pre_http_request',
			static function () {
				return new \WP_Error( 'http_request_failed', 'cURL error 60: SSL certificate problem' );
			},
			10,
			3
		);

		$api = new Cdn_BunnyCdn_Api( array( 'account_api_key' => 'test-key' ) );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'SSL certificate problem' );
		$api->list_pull_zones();
	}

	/**
	 * Missing Redis flag verifies; only explicit false opts out.
	 *
	 * @since 2.10.6
	 */
	public function test_redis_missing_config_verifies() {
		$this->assertTrue( Cache_Redis::verify_tls_certificates_enabled( array() ) );
		$this->assertTrue( Cache_Redis::verify_tls_certificates_enabled( array( 'servers' => array( 'tls://127.0.0.1:6379' ) ) ) );
		$this->assertTrue( Cache_Redis::verify_tls_certificates_enabled( array( 'verify_tls_certificates' => true ) ) );
		$this->assertFalse( Cache_Redis::verify_tls_certificates_enabled( array( 'verify_tls_certificates' => false ) ) );
	}

	/**
	 * TLS Redis URIs set verify_peer explicitly; non-TLS URIs do not.
	 *
	 * @since 2.10.6
	 */
	public function test_redis_tls_stream_context_is_explicit() {
		$plain = Cache_Redis::tls_stream_context( '127.0.0.1:6379', true );
		$this->assertSame( array(), $plain );

		$verified = Cache_Redis::tls_stream_context( 'tls://redis.example:6379', true );
		$this->assertTrue( $verified['stream']['verify_peer'] );
		$this->assertTrue( $verified['stream']['verify_peer_name'] );

		$opt_out = Cache_Redis::tls_stream_context( 'tls://redis.example:6379', false );
		$this->assertFalse( $opt_out['stream']['verify_peer'] );
		$this->assertFalse( $opt_out['stream']['verify_peer_name'] );
	}

	/**
	 * Existing Redis engine config that omits the flag still verifies.
	 *
	 * @since 2.10.6
	 */
	public function test_redis_constructor_does_not_silently_downgrade() {
		$cache = new Cache_Redis(
			array(
				'blog_id'  => 0,
				'servers'  => array( 'tls://redis.example:6379' ),
				'password' => '',
				'dbid'     => 0,
			)
		);

		$ref    = new \ReflectionClass( $cache );
		$prop   = $ref->getProperty( '_verify_tls_certificates' );
		$prop->setAccessible( true );
		$this->assertTrue( $prop->getValue( $cache ) );

		$method = $ref->getMethod( 'build_connect_args' );
		$method->setAccessible( true );
		$args = $method->invoke( $cache, 'tls://redis.example:6379' );

		if ( false !== \phpversion( 'redis' ) && \version_compare( \phpversion( 'redis' ), '5.3.2', '>=' ) ) {
			$context = end( $args );
			$this->assertTrue( $context['stream']['verify_peer'] );
			$this->assertTrue( $context['stream']['verify_peer_name'] );
		}
	}

	/**
	 * Full purge applies the FSD TLS opt-out to the FSD zone, not the
	 * CDN-object engine flag.
	 *
	 * @since 2.10.6
	 */
	public function test_purge_all_uses_per_surface_tls_setting() {
		$config = Dispatcher::config();
		$config->set( 'cdn.enabled', true );
		$config->set( 'cdn.engine', 'bunnycdn' );
		$config->set( 'cdn.bunnycdn.pull_zone_id', 11 );
		$config->set( 'cdn.bunnycdn.verify_tls_certificates', true );
		$config->set( 'cdnfsd.enabled', true );
		$config->set( 'cdnfsd.engine', 'bunnycdn' );
		$config->set( 'cdnfsd.bunnycdn.pull_zone_id', 22 );
		$config->set( 'cdnfsd.bunnycdn.verify_tls_certificates', false );

		$engine = new \W3TC\CdnEngine_Mirror_BunnyCdn(
			array(
				'account_api_key'         => 'test-key',
				'cdn_hostname'            => 'example.b-cdn.net',
				'verify_tls_certificates' => true,
			)
		);

		$this->mock_http_ok();
		$results = array();
		$engine->purge_all( $results );

		$this->assertNotEmpty( $this->http_by_url );
		$cdn_url = 'https://api.bunny.net/pullzone/11/purgeCache';
		$fsd_url = 'https://api.bunny.net/pullzone/22/purgeCache';
		$this->assertArrayHasKey( $cdn_url, $this->http_by_url );
		$this->assertArrayHasKey( $fsd_url, $this->http_by_url );
		$this->assertTrue( $this->http_by_url[ $cdn_url ]['sslverify'] );
		$this->assertFalse( $this->http_by_url[ $fsd_url ]['sslverify'] );

		$config->set( 'cdn.enabled', false );
		$config->set( 'cdn.engine', '' );
		$config->set( 'cdn.bunnycdn.pull_zone_id', 0 );
		$config->set( 'cdn.bunnycdn.verify_tls_certificates', true );
		$config->set( 'cdnfsd.enabled', false );
		$config->set( 'cdnfsd.engine', '' );
		$config->set( 'cdnfsd.bunnycdn.pull_zone_id', 0 );
		$config->set( 'cdnfsd.bunnycdn.verify_tls_certificates', true );
	}

	/**
	 * URL purge uses the FSD TLS flag when a leftover CDN zone id is stored.
	 *
	 * @since 2.10.6
	 */
	public function test_url_purge_honors_fsd_opt_out_with_stale_cdn_zone_id() {
		$config = Dispatcher::config();
		$config->set( 'cdn.enabled', false );
		$config->set( 'cdn.engine', '' );
		$config->set( 'cdn.bunnycdn.pull_zone_id', 11 );
		$config->set( 'cdn.bunnycdn.verify_tls_certificates', true );
		$config->set( 'cdnfsd.enabled', true );
		$config->set( 'cdnfsd.engine', 'bunnycdn' );
		$config->set( 'cdnfsd.bunnycdn.pull_zone_id', 22 );
		$config->set( 'cdnfsd.bunnycdn.verify_tls_certificates', false );

		$this->assertFalse( Cdn_BunnyCdn_Api::url_purge_verify_tls_certificates( $config ) );

		$this->mock_http_ok();
		$api = new Cdn_BunnyCdn_Api(
			array(
				'account_api_key'         => 'test-key',
				'verify_tls_certificates' => Cdn_BunnyCdn_Api::url_purge_verify_tls_certificates( $config ),
			)
		);
		$api->purge(
			array(
				'url'   => 'https://example.com/asset.js',
				'async' => true,
			)
		);

		$this->assertNotNull( $this->http_args );
		$this->assertFalse( $this->http_args['sslverify'] );

		$config->set( 'cdn.enabled', true );
		$config->set( 'cdn.engine', 'ftp' );
		$this->assertFalse( Cdn_BunnyCdn_Api::url_purge_verify_tls_certificates( $config ) );
	}

	/**
	 * URL purge requires both flags when CDN and FSD Bunny engines are enabled.
	 *
	 * @since 2.10.6
	 */
	public function test_url_purge_requires_both_flags_when_both_surfaces_enabled() {
		$config = Dispatcher::config();
		$config->set( 'cdn.enabled', true );
		$config->set( 'cdn.engine', 'bunnycdn' );
		$config->set( 'cdn.bunnycdn.pull_zone_id', 11 );
		$config->set( 'cdn.bunnycdn.verify_tls_certificates', true );
		$config->set( 'cdnfsd.enabled', true );
		$config->set( 'cdnfsd.engine', 'bunnycdn' );
		$config->set( 'cdnfsd.bunnycdn.pull_zone_id', 22 );
		$config->set( 'cdnfsd.bunnycdn.verify_tls_certificates', false );

		$this->assertFalse( Cdn_BunnyCdn_Api::url_purge_verify_tls_certificates( $config ) );

		$config->set( 'cdnfsd.bunnycdn.verify_tls_certificates', true );
		$this->assertTrue( Cdn_BunnyCdn_Api::url_purge_verify_tls_certificates( $config ) );
	}

	/**
	 * Restore Bunny TLS-related config keys after each test.
	 *
	 * @since 2.10.6
	 *
	 * @return void
	 */
	private function reset_bunny_tls_config() {
		$config = Dispatcher::config();
		$config->set( 'cdn.enabled', false );
		$config->set( 'cdn.engine', '' );
		$config->set( 'cdn.bunnycdn.pull_zone_id', 0 );
		$config->set( 'cdn.bunnycdn.verify_tls_certificates', true );
		$config->set( 'cdnfsd.enabled', false );
		$config->set( 'cdnfsd.engine', '' );
		$config->set( 'cdnfsd.bunnycdn.pull_zone_id', 0 );
		$config->set( 'cdnfsd.bunnycdn.verify_tls_certificates', true );
	}

	/**
	 * Mock a successful Bunny CDN JSON response and capture args.
	 *
	 * @since 2.10.6
	 *
	 * @return void
	 */
	private function mock_http_ok() {
		\add_filter( 'pre_http_request', array( $this, 'capture_http_ok' ), 10, 3 );
	}

	/**
	 * pre_http_request mock that records args and returns an empty JSON 200.
	 *
	 * @since 2.10.6
	 *
	 * @param false|array|\WP_Error $preempt Short-circuit value.
	 * @param array                 $args    Request args.
	 * @param string                $url     Request URL.
	 *
	 * @return array
	 */
	public function capture_http_ok( $preempt, $args, $url ) {
		$this->http_args           = $args;
		$this->http_by_url[ $url ] = $args;

		return array(
			'headers'  => array(),
			'body'     => '{}',
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
		);
	}
}
