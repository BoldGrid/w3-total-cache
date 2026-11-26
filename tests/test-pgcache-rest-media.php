<?php
/**
 * Page-cache media type for REST responses.
 *
 * @package W3TC
 */

/**
 * Disk Enhanced cache keys and cached response headers for REST bodies.
 *
 * @since 2.11.0
 */
class PgCache_Rest_Media_Test extends WP_UnitTestCase {

	/**
	 * Builds a Disk Enhanced grabber.
	 *
	 * @since 2.11.0
	 *
	 * @return \W3TC\PgCache_ContentGrabber
	 */
	private function grabber() {
		$config = \W3TC\Dispatcher::config();
		$config->set( 'pgcache.engine', 'file_generic' );

		return new \W3TC\PgCache_ContentGrabber();
	}

	/**
	 * Page-key extension used by both read and write lookups.
	 *
	 * @since 2.11.0
	 *
	 * @param string $content_type Response media type, or empty on a read.
	 *
	 * @return array
	 */
	private function extension( $content_type ) {
		return array(
			'useragent'    => '',
			'referrer'     => '',
			'cookie'       => '',
			'encryption'   => '',
			'compression'  => '',
			'content_type' => $content_type,
			'group'        => '',
		);
	}

	/**
	 * JSON responses must not be stored under the HTML extension.
	 *
	 * @since 2.11.0
	 *
	 * @return void
	 */
	public function test_enhanced_json_key_uses_json_extension() {
		$grabber = $this->grabber();
		$url     = 'https://example.test/wp-json/wp/v2/comments/1';

		$json_key = $grabber->_get_page_key(
			$this->extension( 'application/json; charset=UTF-8' ),
			$url
		);
		$ld_key   = $grabber->_get_page_key(
			$this->extension( 'application/ld+json' ),
			$url
		);
		$html_key = $grabber->_get_page_key(
			$this->extension( 'text/html; charset=UTF-8' ),
			$url
		);
		$read_key = $grabber->_get_page_key(
			$this->extension( '' ),
			$url
		);

		$this->assertStringEndsWith( '.json', $json_key );
		$this->assertStringEndsWith( '.json', $ld_key );
		$this->assertStringEndsWith( '.html', $html_key );
		$this->assertStringEndsWith( '.html', $read_key );
		$this->assertNotSame( $json_key, $html_key );
	}

	/**
	 * Cached non-HTML responses get a nosniff header.
	 *
	 * @since 2.11.0
	 *
	 * @return void
	 */
	public function test_cached_json_sets_nosniff() {
		$grabber = $this->grabber();

		$json_headers = $grabber->_ensure_cached_media_headers(
			array(
				'Content-Type' => 'application/json; charset=UTF-8',
			)
		);
		$html_headers = $grabber->_ensure_cached_media_headers(
			array(
				'Content-Type' => 'text/html; charset=UTF-8',
			)
		);

		$this->assertSame( 'nosniff', $json_headers['X-Content-Type-Options'] );
		$this->assertArrayNotHasKey( 'X-Content-Type-Options', $html_headers );
	}

	/**
	 * Cached REST-group responses get a nosniff header.
	 *
	 * @since 2.11.0
	 *
	 * @return void
	 */
	public function test_cached_rest_group_sets_nosniff() {
		$grabber = $this->grabber();
		$ref     = new \ReflectionObject( $grabber );
		$prop    = $ref->getProperty( '_page_key_extension' );
		\W3TC\Util_Reflection::set_accessible( $prop );
		$prop->setValue(
			$grabber,
			array(
				'group' => 'rest',
			)
		);

		$headers = $grabber->_ensure_cached_media_headers(
			array(
				'Content-Type' => 'text/html',
			)
		);

		$this->assertSame( 'nosniff', $headers['X-Content-Type-Options'] );
	}

	/**
	 * REST cache entries are not read when Pro is inactive.
	 *
	 * @since 2.11.0
	 *
	 * @return void
	 */
	public function test_read_cache_rejects_rest_without_pro() {
		$prev_uri                  = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : null;
		$prev_method               = isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : null;
		$_SERVER['REQUEST_URI']    = '/wp-json/wp/v2/comments/1';
		$_SERVER['REQUEST_METHOD'] = 'GET';

		$config      = \W3TC\Dispatcher::config();
		$prev_rest   = $config->get_string( 'pgcache.rest' );
		$prev_type   = $config->get_string( 'plugin.type' );
		$prev_engine = $config->get_string( 'pgcache.engine' );
		$config->set( 'pgcache.rest', 'cache' );
		$config->set( 'plugin.type', '' );
		$config->set( 'pgcache.engine', 'file' );

		$grabber = new \W3TC\PgCache_ContentGrabber();
		$ref     = new \ReflectionObject( $grabber );
		$method  = $ref->getMethod( '_can_read_cache' );
		\W3TC\Util_Reflection::set_accessible( $method );
		$allowed = $method->invoke( $grabber );
		$reason  = $ref->getProperty( 'cache_reject_reason' );
		\W3TC\Util_Reflection::set_accessible( $reason );
		$why     = $reason->getValue( $grabber );

		$config->set( 'plugin.type', 'pro' );
		$pro_grabber = new \W3TC\PgCache_ContentGrabber();
		$method->invoke( $pro_grabber );
		$pro_reason = $reason->getValue( $pro_grabber );

		$config->set( 'pgcache.rest', $prev_rest );
		$config->set( 'plugin.type', $prev_type );
		$config->set( 'pgcache.engine', $prev_engine );
		if ( null === $prev_uri ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $prev_uri;
		}
		if ( null === $prev_method ) {
			unset( $_SERVER['REQUEST_METHOD'] );
		} else {
			$_SERVER['REQUEST_METHOD'] = $prev_method;
		}

		$this->assertFalse( $allowed );
		$this->assertSame( 'REST request', $why );
		$this->assertNotSame( 'REST request', $pro_reason );
	}

	/**
	 * Authenticated REST responses must not be written to the shared REST cache key.
	 *
	 * @since 2.11.0
	 *
	 * @return void
	 */
	public function test_write_cache_rejects_authenticated_rest_without_session_cookie() {
		$prev_uri       = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : null;
		$prev_method    = isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : null;
		$prev_auth      = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? $_SERVER['HTTP_AUTHORIZATION'] : null;
		$_SERVER['REQUEST_URI']        = '/wp-json/wp/v2/users/1?context=edit';
		$_SERVER['REQUEST_METHOD']     = 'GET';
		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic dXNlcjpwYXNz';

		$config = \W3TC\Dispatcher::config();
		$prev_rest   = $config->get_string( 'pgcache.rest' );
		$prev_type   = $config->get_string( 'plugin.type' );
		$prev_engine = $config->get_string( 'pgcache.engine' );
		$prev_reject = $config->get_boolean( 'pgcache.reject.logged' );
		$config->set( 'pgcache.rest', 'cache' );
		$config->set( 'plugin.type', 'pro' );
		$config->set( 'pgcache.engine', 'file' );
		$config->set( 'pgcache.reject.logged', false );

		$grabber = new \W3TC\PgCache_ContentGrabber();
		$ref     = new \ReflectionObject( $grabber );
		$caching = $ref->getProperty( '_caching' );
		\W3TC\Util_Reflection::set_accessible( $caching );
		$caching->setValue( $grabber, true );
		$method  = $ref->getMethod( '_can_write_cache' );
		\W3TC\Util_Reflection::set_accessible( $method );
		$allowed = $method->invoke(
			$grabber,
			'{"id":1}',
			array(
				'kv' => array(
					'content-type' => 'application/json; charset=UTF-8',
				),
			)
		);
		$reason  = $ref->getProperty( 'cache_reject_reason' );
		\W3TC\Util_Reflection::set_accessible( $reason );
		$why     = $reason->getValue( $grabber );

		$config->set( 'pgcache.rest', $prev_rest );
		$config->set( 'plugin.type', $prev_type );
		$config->set( 'pgcache.engine', $prev_engine );
		$config->set( 'pgcache.reject.logged', $prev_reject );
		if ( null === $prev_uri ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $prev_uri;
		}
		if ( null === $prev_method ) {
			unset( $_SERVER['REQUEST_METHOD'] );
		} else {
			$_SERVER['REQUEST_METHOD'] = $prev_method;
		}
		if ( null === $prev_auth ) {
			unset( $_SERVER['HTTP_AUTHORIZATION'] );
		} else {
			$_SERVER['HTTP_AUTHORIZATION'] = $prev_auth;
		}

		$this->assertFalse( $allowed );
		$this->assertSame( 'Request has credentials', $why );
	}

	/**
	 * A stored anonymous REST response is not read for an authenticated caller
	 * when logged-in page caching is allowed.
	 *
	 * @since 2.11.0
	 *
	 * @return void
	 */
	public function test_read_cache_rejects_authenticated_rest_when_logged_in_pages_are_cached() {
		list( $allowed, $why ) = $this->read_cache_decision( 'Basic dXNlcjpwYXNz' );

		$this->assertFalse( $allowed );
		$this->assertSame( 'Request has credentials', $why );
	}

	/**
	 * A logged-in REST caller is not read from the shared cache when no
	 * Authorization header is present and logged-in page caching is allowed.
	 *
	 * @since 2.11.0
	 *
	 * @return void
	 */
	public function test_read_cache_rejects_logged_in_rest_user_when_logged_in_pages_are_cached() {
		$user_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		try {
			list( $allowed, $why ) = $this->read_cache_decision( null );
		} finally {
			wp_set_current_user( 0 );
		}

		$this->assertFalse( $allowed );
		$this->assertSame( 'User is logged in', $why );
	}

	/**
	 * An anonymous REST read is not rejected by the logged-in check when
	 * logged-in page caching is allowed.
	 *
	 * PHPUnit runs in CLI, so the read still stops for console mode. That
	 * reason means the request passed the logged-in REST gate and can be
	 * served on a web request when a cache entry exists.
	 *
	 * @since 2.11.0
	 *
	 * @return void
	 */
	public function test_read_cache_allows_anonymous_rest_when_logged_in_pages_are_cached() {
		list( $allowed, $why ) = $this->read_cache_decision( null );

		$this->assertNotSame( 'User is logged in', $why );
		if ( 'cli' === PHP_SAPI ) {
			$this->assertFalse( $allowed );
			$this->assertSame( 'Console mode', $why );
		} else {
			$this->assertTrue( $allowed );
		}
	}

	/**
	 * Runs _can_read_cache() for a REST request with logged-in page caching on.
	 *
	 * @since 2.11.0
	 *
	 * @param string|null $authorization Authorization header, or null to clear it.
	 *
	 * @return array{0:bool,1:string}
	 */
	private function read_cache_decision( $authorization ) {
		$prev_uri    = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : null;
		$prev_method = isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : null;
		$prev_auth   = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? $_SERVER['HTTP_AUTHORIZATION'] : null;
		$prev_redir  = isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] : null;

		$_SERVER['REQUEST_URI']    = '/wp-json/wp/v2/users/1';
		$_SERVER['REQUEST_METHOD'] = 'GET';
		if ( null === $authorization ) {
			unset( $_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] );
		} else {
			$_SERVER['HTTP_AUTHORIZATION'] = $authorization;
		}

		$config      = \W3TC\Dispatcher::config();
		$prev_rest   = $config->get_string( 'pgcache.rest' );
		$prev_type   = $config->get_string( 'plugin.type' );
		$prev_engine = $config->get_string( 'pgcache.engine' );
		$prev_reject = $config->get_boolean( 'pgcache.reject.logged' );
		$config->set( 'pgcache.rest', 'cache' );
		$config->set( 'plugin.type', 'pro' );
		$config->set( 'pgcache.engine', 'file' );
		$config->set( 'pgcache.reject.logged', false );

		$grabber = new \W3TC\PgCache_ContentGrabber();
		$ref     = new \ReflectionObject( $grabber );
		$method  = $ref->getMethod( '_can_read_cache' );
		\W3TC\Util_Reflection::set_accessible( $method );
		$allowed = $method->invoke( $grabber );
		$reason  = $ref->getProperty( 'cache_reject_reason' );
		\W3TC\Util_Reflection::set_accessible( $reason );
		$why     = $reason->getValue( $grabber );

		$config->set( 'pgcache.rest', $prev_rest );
		$config->set( 'plugin.type', $prev_type );
		$config->set( 'pgcache.engine', $prev_engine );
		$config->set( 'pgcache.reject.logged', $prev_reject );
		if ( null === $prev_uri ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $prev_uri;
		}
		if ( null === $prev_method ) {
			unset( $_SERVER['REQUEST_METHOD'] );
		} else {
			$_SERVER['REQUEST_METHOD'] = $prev_method;
		}
		if ( null === $prev_auth ) {
			unset( $_SERVER['HTTP_AUTHORIZATION'] );
		} else {
			$_SERVER['HTTP_AUTHORIZATION'] = $prev_auth;
		}
		if ( null === $prev_redir ) {
			unset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] );
		} else {
			$_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = $prev_redir;
		}

		return array( $allowed, $why );
	}
}
