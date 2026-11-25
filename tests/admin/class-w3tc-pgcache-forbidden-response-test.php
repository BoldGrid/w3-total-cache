<?php
/**
 * File: class-w3tc-pgcache-forbidden-response-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      X.X.X
 */

declare( strict_types = 1 );

namespace W3TC {

	if ( ! \function_exists( __NAMESPACE__ . '\http_response_code' ) ) {
		/**
		 * Stand-in for the PHP builtin.
		 *
		 * The page-cache writer reads the status through an unqualified call, and the
		 * test process cannot change the real response code once PHPUnit has sent output.
		 *
		 * @since X.X.X
		 *
		 * @param int|null $code Status to set, or null to read.
		 * @return int
		 */
		function http_response_code( $code = null ) {
			static $current = 200;

			if ( null !== $code ) {
				$current = (int) $code;
			}

			return $current;
		}
	}

	if ( ! \class_exists( __NAMESPACE__ . '\Forbidden_Response_Test_Grabber', false ) ) {
		/**
		 * Page-cache grabber that records stores instead of writing cache entries.
		 *
		 * @since X.X.X
		 */
		class Forbidden_Response_Test_Grabber extends PgCache_ContentGrabber {
			/**
			 * Recording cache.
			 *
			 * @since X.X.X
			 *
			 * @var Forbidden_Response_Test_Cache
			 */
			public $recording_cache;

			/**
			 * Returns the recording cache for every group.
			 *
			 * @since X.X.X
			 *
			 * @param string $w3tc_group Cache group.
			 * @return Forbidden_Response_Test_Cache
			 */
			public function _get_cache( $w3tc_group = '*' ) {
				unset( $w3tc_group );

				return $this->recording_cache;
			}

			/**
			 * Replaces the configuration object.
			 *
			 * @since X.X.X
			 *
			 * @param Config $w3tc_config Configuration.
			 * @return void
			 */
			public function use_config( $w3tc_config ) {
				$this->_config = $w3tc_config;
			}
		}

		/**
		 * Records page-cache writes and removals.
		 *
		 * @since X.X.X
		 */
		class Forbidden_Response_Test_Cache {
			/**
			 * Stored entries.
			 *
			 * @since X.X.X
			 *
			 * @var array
			 */
			public $sets = array();

			/**
			 * Records a store.
			 *
			 * @since X.X.X
			 *
			 * @param string $key      Cache key.
			 * @param array  $data     Cache data.
			 * @param int    $lifetime Lifetime.
			 * @param string $group    Group.
			 * @return bool
			 */
			public function set( $key, $data, $lifetime, $group ) {
				unset( $lifetime, $group );

				$this->sets[] = array(
					'key'  => $key,
					'data' => $data,
				);

				return true;
			}

			/**
			 * Ignores removals.
			 *
			 * @since X.X.X
			 *
			 * @param string $key Cache key.
			 * @return bool
			 */
			public function hard_delete( $key ) {
				unset( $key );

				return true;
			}
		}
	}
}

namespace {

	use W3TC\Config;
	use W3TC\Forbidden_Response_Test_Cache;
	use W3TC\Forbidden_Response_Test_Grabber;
	use W3TC\PgCache_ContentGrabber;

	/**
	 * The page cache must not store a 403 response, and must keep storing 200 responses and, by option, 404 responses.
	 *
	 * GitHub issue 350.
	 *
	 * @since X.X.X
	 */
	class W3tc_Pgcache_Forbidden_Response_Test extends WP_UnitTestCase {
		/**
		 * Response body used by every test.
		 *
		 * @var string
		 */
		const BODY = '<html><body>response</body></html>';

		/**
		 * Resets the stubbed response status.
		 *
		 * @since X.X.X
		 */
		public function tear_down() {
			\W3TC\http_response_code( 200 );

			parent::tear_down();
		}

		/**
		 * A 403 response is not written, in the plain or the gzip variant.
		 *
		 * @since X.X.X
		 */
		public function test_forbidden_response_is_not_cached() {
			$grabber = $this->grabber( array() );

			$this->respond( $grabber, 403, '/' );

			$this->assertSame( array(), $grabber->recording_cache->sets );
			$this->assertSame( 'Page is 403', $this->property( $grabber, 'cache_reject_reason' ) );
			$this->assertSame( 'miss_third_party', $this->property( $grabber, 'process_status' ) );
		}

		/**
		 * The cache-404 option does not make a 403 response cacheable.
		 *
		 * @since X.X.X
		 */
		public function test_forbidden_response_is_not_cached_when_404_caching_is_enabled() {
			$grabber = $this->grabber( array( 'pgcache.cache.404' => true ) );

			$this->respond( $grabber, 403, '/' );

			$this->assertSame( array(), $grabber->recording_cache->sets );
		}

		/**
		 * A 200 response is still written, in the plain and the gzip variant.
		 *
		 * @since X.X.X
		 */
		public function test_successful_response_is_cached() {
			$grabber = $this->grabber( array() );

			$this->respond( $grabber, 200, '/' );

			$sets = $grabber->recording_cache->sets;

			$this->assertCount( 2, $sets );
			$this->assertSame( 200, $sets[0]['data']['headers']['Status-Code'] );
			$this->assertSame( 200, $sets[1]['data']['headers']['Status-Code'] );
			$this->assertSame( 'gzip', $sets[1]['data']['c'] );
			$this->assertSame( 'miss_fill', $this->property( $grabber, 'process_status' ) );
		}

		/**
		 * A 404 response is not written while the cache-404 option is off.
		 *
		 * @since X.X.X
		 */
		public function test_not_found_response_is_not_cached_by_default() {
			$grabber = $this->grabber( array() );

			$this->respond( $grabber, 404, '/?p=999999' );

			$this->assertSame( array(), $grabber->recording_cache->sets );
			$this->assertSame( 'Page is 404', $this->property( $grabber, 'cache_reject_reason' ) );
			$this->assertSame( 'miss_404', $this->property( $grabber, 'process_status' ) );
		}

		/**
		 * A 404 response is still written when the cache-404 option is on.
		 *
		 * @since X.X.X
		 */
		public function test_not_found_response_is_cached_when_option_is_enabled() {
			$grabber = $this->grabber( array( 'pgcache.cache.404' => true ) );

			$this->respond( $grabber, 404, '/?p=999999' );

			$sets = $grabber->recording_cache->sets;

			$this->assertCount( 2, $sets );
			$this->assertSame( 404, $sets[0]['data']['headers']['Status-Code'] );
			$this->assertSame( 'miss_fill', $this->property( $grabber, 'process_status' ) );
		}

		/**
		 * Builds a grabber that is ready to run its output-buffer callback.
		 *
		 * @since X.X.X
		 *
		 * @param array $settings Configuration overrides.
		 * @return Forbidden_Response_Test_Grabber
		 */
		private function grabber( array $settings ) {
			$_SERVER['REQUEST_METHOD'] = 'GET';
			$_SERVER['REQUEST_URI']    = '/';

			$w3tc_config = new Config();
			$w3tc_config->set( 'browsercache.enabled', true );
			$w3tc_config->set( 'browsercache.html.compression', true );
			$w3tc_config->set( 'pgcache.cache.404', false );

			foreach ( $settings as $name => $value ) {
				$w3tc_config->set( $name, $value );
			}

			$grabber                  = new Forbidden_Response_Test_Grabber();
			$grabber->recording_cache = new Forbidden_Response_Test_Cache();
			$grabber->use_config( $w3tc_config );

			$this->set_property( $grabber, '_caching', true );
			$this->set_property(
				$grabber,
				'_page_key_extension',
				array(
					'useragent'    => '',
					'referrer'     => '',
					'cookie'       => '',
					'encryption'   => '',
					'group'        => '',
					'content_type' => 'text/html',
					'compression'  => false,
				)
			);
			$this->set_property(
				$grabber,
				'_request_url_fragments',
				array(
					'host'        => 'example.org',
					'path'        => '/',
					'querystring' => '',
				)
			);

			return $grabber;
		}

		/**
		 * Runs the output-buffer callback for a response with the given status.
		 *
		 * @since X.X.X
		 *
		 * @param Forbidden_Response_Test_Grabber $grabber Grabber.
		 * @param int                             $status  HTTP status.
		 * @param string                          $url     Request URL that WordPress resolves.
		 * @return void
		 */
		private function respond( $grabber, $status, $url ) {
			$this->go_to( $url );
			\W3TC\http_response_code( $status );

			$grabber->ob_callback( self::BODY );
		}

		/**
		 * Reads a private grabber property.
		 *
		 * @since X.X.X
		 *
		 * @param Forbidden_Response_Test_Grabber $grabber  Grabber.
		 * @param string                          $property Property name.
		 * @return mixed
		 */
		private function property( $grabber, $property ) {
			$reflection = new ReflectionProperty( PgCache_ContentGrabber::class, $property );
			$reflection->setAccessible( true );

			return $reflection->getValue( $grabber );
		}

		/**
		 * Sets a private grabber property.
		 *
		 * @since X.X.X
		 *
		 * @param Forbidden_Response_Test_Grabber $grabber  Grabber.
		 * @param string                          $property Property name.
		 * @param mixed                           $value    Value.
		 * @return void
		 */
		private function set_property( $grabber, $property, $value ) {
			$reflection = new ReflectionProperty( PgCache_ContentGrabber::class, $property );
			$reflection->setAccessible( true );
			$reflection->setValue( $grabber, $value );
		}
	}
}
