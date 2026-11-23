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
		$prop->setAccessible( true );
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
		$method->setAccessible( true );
		$allowed = $method->invoke( $grabber );
		$reason  = $ref->getProperty( 'cache_reject_reason' );
		$reason->setAccessible( true );
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
}
