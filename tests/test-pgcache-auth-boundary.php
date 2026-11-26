<?php
/**
 * Page-cache request authentication and response directive tests.
 *
 * @package W3TC
 */

/**
 * Tests page-cache authentication boundaries.
 *
 * @since 2.11.1
 */
class PgCache_Auth_Boundary_Test extends WP_UnitTestCase {

	/**
	 * Original server variables.
	 *
	 * @var array
	 */
	private $server;

	/**
	 * Original cookies.
	 *
	 * @var array
	 */
	private $cookies;

	/**
	 * Sets up an anonymous cacheable request.
	 *
	 * @since 2.11.1
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->server  = $_SERVER;
		$this->cookies = $_COOKIE;
		$_SERVER       = array(
			'REQUEST_METHOD' => 'GET',
			'REQUEST_URI'    => '/page-cache-boundary/',
			'HTTP_HOST'      => 'example.org',
		);
		$_COOKIE       = array();
	}

	/**
	 * Restores request variables.
	 *
	 * @since 2.11.1
	 *
	 * @return void
	 */
	public function tear_down() {
		$_SERVER = $this->server;
		$_COOKIE = $this->cookies;

		parent::tear_down();
	}

	/**
	 * Invokes a private grabber method.
	 *
	 * @since 2.11.1
	 *
	 * @param \W3TC\PgCache_ContentGrabber $grabber Grabber instance.
	 * @param string                       $method  Method name.
	 * @param array                        $args    Method arguments.
	 *
	 * @return mixed
	 */
	private function invoke( $grabber, $method, $args = array() ) {
		$reflection = new \ReflectionMethod( $grabber, $method );
		$reflection->setAccessible( true );

		return $reflection->invokeArgs( $grabber, $args );
	}

	/**
	 * Sets a private grabber property.
	 *
	 * @since 2.11.1
	 *
	 * @param \W3TC\PgCache_ContentGrabber $grabber Grabber instance.
	 * @param string                       $property Property name.
	 * @param mixed                        $value Property value.
	 *
	 * @return void
	 */
	private function set_property( $grabber, $property, $value ) {
		$reflection = new \ReflectionProperty( $grabber, $property );
		$reflection->setAccessible( true );
		$reflection->setValue( $grabber, $value );
	}

	/**
	 * Creates a grabber that may write to cache.
	 *
	 * @since 2.11.1
	 *
	 * @return \W3TC\PgCache_ContentGrabber
	 */
	private function cacheable_grabber() {
		$grabber = new \W3TC\PgCache_ContentGrabber();
		$this->set_property( $grabber, '_caching', true );

		return $grabber;
	}

	/**
	 * An empty Authorization header must not disable page cache.
	 *
	 * @since 2.11.1
	 *
	 * @return void
	 */
	public function test_empty_authorization_header_is_cacheable() {
		$_SERVER['HTTP_AUTHORIZATION'] = '';
		$grabber                       = $this->cacheable_grabber();

		$this->assertFalse( $this->invoke( $grabber, '_has_request_credentials' ) );
		$this->assertTrue( $grabber->_check_logged_in() );
		$this->assertTrue(
			$this->invoke(
				$grabber,
				'_can_write_cache',
				array( '<html>response</html>', array( 'kv' => array() ) )
			)
		);
	}

	/**
	 * An empty Authorization header does not hide the redirect copy.
	 *
	 * @since 2.11.1
	 *
	 * @return void
	 */
	public function test_redirect_authorization_is_not_shadowed_by_empty_header() {
		$_SERVER['HTTP_AUTHORIZATION']          = '';
		$_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = 'Basic dXNlcjpwYXNz';
		$grabber                                = $this->cacheable_grabber();

		$this->assertTrue( $this->invoke( $grabber, '_has_request_credentials' ) );
		$this->assertFalse( $grabber->_check_logged_in() );
		$this->assertFalse( $this->invoke( $grabber, '_can_read_cache' ) );
	}

	/**
	 * Authorization headers cannot read from or write to cache.
	 *
	 * @since 2.11.1
	 *
	 * @return void
	 */
	public function test_authorization_request_is_not_cacheable() {
		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic dXNlcjpwYXNz';
		$grabber                       = $this->cacheable_grabber();

		$this->assertFalse( $this->invoke( $grabber, '_can_read_cache' ) );
		$this->assertFalse(
			$this->invoke(
				$grabber,
				'_can_write_cache',
				array( '<html>response</html>', array( 'kv' => array() ) )
			)
		);
	}

	/**
	 * PHP basic-auth credentials cannot read from or write to cache.
	 *
	 * @since 2.11.1
	 *
	 * @return void
	 */
	public function test_php_auth_user_request_is_not_cacheable() {
		$_SERVER['PHP_AUTH_USER'] = 'cache-test-user';
		$grabber                  = $this->cacheable_grabber();

		$this->assertFalse( $this->invoke( $grabber, '_can_read_cache' ) );
		$this->assertFalse(
			$this->invoke(
				$grabber,
				'_can_write_cache',
				array( '<html>response</html>', array( 'kv' => array() ) )
			)
		);
	}

	/**
	 * Private and no-store responses are not written to cache.
	 *
	 * @since 2.11.1
	 *
	 * @return void
	 */
	public function test_private_cache_control_responses_are_not_stored() {
		foreach ( array( 'private, max-age=60', 'private="set-cookie"', 'no-store' ) as $cache_control ) {
			$grabber = $this->cacheable_grabber();

			$this->assertFalse(
				$this->invoke(
					$grabber,
					'_can_write_cache',
					array(
						'<html>response</html>',
						array( 'kv' => array( 'cache-control' => $cache_control ) ),
					)
				),
				$cache_control
			);
		}
	}

	/**
	 * A private directive in an earlier Cache-Control header still blocks storage.
	 *
	 * @since 2.11.1
	 *
	 * @return void
	 */
	public function test_private_directive_in_any_cache_control_header_is_not_stored() {
		$grabber = $this->cacheable_grabber();

		$this->assertFalse(
			$this->invoke(
				$grabber,
				'_can_write_cache',
				array(
					'<html>response</html>',
					array(
						'kv'    => array( 'cache-control' => 'max-age=60' ),
						'plain' => array(
							array(
								'name'  => 'Cache-Control',
								'value' => 'private',
							),
							array(
								'name'  => 'Cache-Control',
								'value' => 'max-age=60',
							),
						),
					),
				)
			)
		);
	}

	/**
	 * Regeneration requests only target hosts of this site or network.
	 *
	 * @since 2.11.1
	 *
	 * @return void
	 */
	public function test_regeneration_url_is_limited_to_site_hosts() {
		$this->assertTrue( \W3TC\Extension_AlwaysCached_Worker::is_regeneration_url( \home_url( '/sample-page/' ) ) );
		$this->assertFalse( \W3TC\Extension_AlwaysCached_Worker::is_regeneration_url( 'http://other-host.example/sample-page/' ) );
		$this->assertFalse( \W3TC\Extension_AlwaysCached_Worker::is_regeneration_url( 'ftp://' . \wp_parse_url( \home_url(), PHP_URL_HOST ) . '/' ) );
	}

	/**
	 * Regeneration requests may target another site in this network.
	 *
	 * @since 2.11.1
	 *
	 * @group multisite
	 *
	 * @return void
	 */
	public function test_regeneration_url_accepts_network_site_hosts() {
		if ( ! \is_multisite() ) {
			$this->markTestSkipped( 'Multisite only.' );
		}

		$_SERVER = $this->server;
		self::factory()->blog->create(
			array(
				'domain' => 'regeneration-subsite.example.org',
				'path'   => '/',
			)
		);

		$this->assertTrue( \W3TC\Extension_AlwaysCached_Worker::is_regeneration_url( 'http://regeneration-subsite.example.org/sample-page/' ) );
	}

	/**
	 * A bare regeneration header is not trusted.
	 *
	 * @since 2.11.1
	 *
	 * @return void
	 */
	public function test_bare_alwayscached_header_does_not_bypass_cache() {
		$_SERVER['HTTP_W3TCALWAYSCACHED'] = 'client-value';
		$_SERVER['REMOTE_ADDR']           = '127.0.0.1';
		$grabber                          = $this->cacheable_grabber();

		$this->assertFalse( $this->invoke( $grabber, '_is_alwayscached_worker_request' ) );

		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer 00000000000000000000000000000000';
		$this->assertFalse( $this->invoke( $grabber, '_is_alwayscached_worker_request' ) );

		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . str_repeat( 'ab', 16 ) . '.' . str_repeat( 'cd', 16 );
		$this->assertFalse( $this->invoke( $grabber, '_is_alwayscached_worker_request' ) );

		if ( defined( 'W3TC_WORKER_SECRET' ) && '' !== W3TC_WORKER_SECRET ) {
			return;
		}

		$headers = \W3TC\Util_Environment::alwayscached_request_headers( 'worker-item' );
		$this->assertNotEmpty( $headers['Authorization'] );

		$_SERVER['HTTP_W3TCALWAYSCACHED'] = 'worker-item';
		$_SERVER['HTTP_AUTHORIZATION']    = $headers['Authorization'];
		$_SERVER['REMOTE_ADDR']           = '203.0.113.5';
		$this->assertTrue( $this->invoke( $grabber, '_is_alwayscached_worker_request' ) );
		$this->assertTrue( $this->invoke( $grabber, '_is_alwayscached_worker_request' ) );
		$this->assertTrue( $grabber->_check_logged_in() );

		$replay = $this->cacheable_grabber();
		$this->assertFalse( $this->invoke( $replay, '_is_alwayscached_worker_request' ) );
	}

	/**
	 * The authenticated Always Cached worker can regenerate cache entries.
	 *
	 * @since 2.11.1
	 *
	 * @return void
	 */
	public function test_authenticated_alwayscached_worker_can_regenerate() {
		if ( ! defined( 'W3TC_WORKER_SECRET' ) ) {
			define( 'W3TC_WORKER_SECRET', 'pgcache-auth-boundary-test-secret' );
		}

		$_SERVER['HTTP_W3TCALWAYSCACHED'] = 'worker-item';
		$_SERVER['HTTP_AUTHORIZATION']    = 'Bearer ' . W3TC_WORKER_SECRET;
		$grabber                          = $this->cacheable_grabber();

		$this->assertTrue( $this->invoke( $grabber, '_is_alwayscached_worker_request' ) );
		$this->assertTrue( $grabber->_check_logged_in() );
		$this->set_property( $grabber, '_page_key_extension', array( 'alwayscached' => true ) );
		$this->assertNull( $grabber->_extract_cached_page( false ) );

		$_SERVER['HTTP_AUTHORIZATION']          = '';
		$_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = 'Bearer ' . W3TC_WORKER_SECRET;
		$redirected                             = $this->cacheable_grabber();

		$this->assertTrue( $this->invoke( $redirected, '_is_alwayscached_worker_request' ) );
	}

	/**
	 * The authenticated worker passes the REST logged-in check when REST caching is on.
	 *
	 * @since 2.11.1
	 *
	 * @return void
	 */
	public function test_authenticated_alwayscached_worker_passes_rest_logged_in_check() {
		if ( ! defined( 'W3TC_WORKER_SECRET' ) ) {
			define( 'W3TC_WORKER_SECRET', 'pgcache-auth-boundary-test-secret' );
		}

		$config    = \W3TC\Dispatcher::config();
		$prev_rest = $config->get_string( 'pgcache.rest' );
		$prev_type = $config->get_string( 'plugin.type' );
		$config->set( 'pgcache.rest', 'cache' );
		$config->set( 'plugin.type', 'pro' );

		$_SERVER['REQUEST_URI']           = '/wp-json/wp/v2/posts';
		$_SERVER['HTTP_W3TCALWAYSCACHED'] = 'worker-item';
		$_SERVER['HTTP_AUTHORIZATION']    = 'Bearer ' . W3TC_WORKER_SECRET;

		try {
			$worker = $this->cacheable_grabber();
			$this->assertTrue( $worker->_check_logged_in() );

			unset( $_SERVER['HTTP_W3TCALWAYSCACHED'] );
			$client = $this->cacheable_grabber();
			$this->assertFalse( $client->_check_logged_in() );
		} finally {
			$config->set( 'pgcache.rest', $prev_rest );
			$config->set( 'plugin.type', $prev_type );
		}
	}

	/**
	 * The early database connection reads DB_HOST the way WordPress does.
	 *
	 * @since 2.11.1
	 *
	 * @return void
	 */
	public function test_early_database_host_parsing_matches_wordpress() {
		$this->assertTrue( class_exists( '\W3TC\ConfigDbStorage' ) );

		$hosts = array(
			'localhost',
			'127.0.0.1:3307',
			'localhost:/var/run/mysqld/mysqld.sock',
			'localhost:3307:/var/run/mysqld/mysqld.sock',
			'::1',
			'[::1]:3306',
			'[2001:db8::10]:3307',
			'db.example.org:3306',
		);

		foreach ( $hosts as $host ) {
			$this->assertSame(
				$GLOBALS['wpdb']->parse_db_host( $host ),
				\W3TC\_WpdbEssentials::parse_db_host( $host ),
				$host
			);
		}
	}

	/**
	 * Disk: Enhanced rules send requests with credentials to PHP.
	 *
	 * @since 2.11.1
	 *
	 * @return void
	 */
	public function test_disk_enhanced_rules_skip_requests_with_credentials() {
		$config      = new \W3TC\Config();
		$environment = new \W3TC\PgCache_Environment();

		$apache = $this->invoke( $environment, 'rules_core_generate_apache', array( $config ) );
		$this->assertStringContainsString( "RewriteCond %{HTTP:Authorization} =\"\"\n", $apache );

		$nginx = $this->invoke( $environment, 'rules_core_generate_nginx', array( $config ) );
		$this->assertStringContainsString( "if (\$http_authorization != \"\") {\n    set \$w3tc_rewrite 0;\n}\n", $nginx );
	}

	/**
	 * An anonymous request remains readable and writable by page cache.
	 *
	 * @since 2.11.1
	 *
	 * @return void
	 */
	public function test_anonymous_request_remains_cacheable() {
		$grabber = $this->cacheable_grabber();

		$this->assertTrue( $grabber->_check_logged_in() );
		$this->assertTrue(
			$this->invoke(
				$grabber,
				'_can_write_cache',
				array( '<html>response</html>', array( 'kv' => array() ) )
			)
		);
	}
}
