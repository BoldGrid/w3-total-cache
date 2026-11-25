<?php
/**
 * Browser lifetime for PHP-served page cache hits counts from access, not creation.
 *
 * @package W3TC\Tests
 */

use W3TC\Dispatcher;
use W3TC\PgCache_ContentGrabber;
use W3TC\Util_Content;

/**
 * Thrown by the capture grabber on a 304 so the exit() after _headers() is never reached.
 */
class PgCache_Html_Lifetime_Not_Modified_Exception extends Exception {
}

/**
 * Captures the headers that _send_headers() would send.
 */
class PgCache_Html_Lifetime_Capture_Grabber extends PgCache_ContentGrabber {
	/**
	 * Headers passed to _headers().
	 *
	 * @var array
	 */
	public $sent = array();

	/**
	 * Store the headers instead of sending them.
	 *
	 * @param array $headers Headers.
	 *
	 * @throws PgCache_Html_Lifetime_Not_Modified_Exception On a 304 response.
	 *
	 * @return bool
	 */
	public function _headers( $headers ) {
		$this->sent = $headers;

		if ( isset( $headers['Status'] ) && false !== strpos( $headers['Status'], '304' ) ) {
			throw new PgCache_Html_Lifetime_Not_Modified_Exception();
		}

		return true;
	}
}

/**
 * Tests for PgCache_ContentGrabber::_send_headers() browser lifetime.
 */
class Pgcache_Html_Browser_Lifetime_Test extends WP_UnitTestCase {
	/**
	 * Config values changed by the test, restored afterwards.
	 *
	 * @var array
	 */
	private $saved_config = array();

	/**
	 * Set the config used by the tests.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$config = Dispatcher::config();
		$values = array(
			'browsercache.enabled'               => true,
			'browsercache.html.expires'          => true,
			'browsercache.html.last_modified'    => true,
			'browsercache.html.cache.control'    => true,
			'browsercache.html.cache.policy'     => 'cache_public_maxage',
			'browsercache.html.etag'             => false,
			'browsercache.html.w3tc'             => false,
			'browsercache.html.lifetime'         => 30,
		);

		foreach ( $values as $key => $value ) {
			$this->saved_config[ $key ] = $config->get( $key );
			$config->set( $key, $value );
		}

		unset( $_SERVER['HTTP_IF_MODIFIED_SINCE'], $_SERVER['HTTP_IF_NONE_MATCH'] );
	}

	/**
	 * Restore the config after each test.
	 *
	 * @return void
	 */
	public function tear_down() {
		$config = Dispatcher::config();
		foreach ( $this->saved_config as $key => $value ) {
			$config->set( $key, $value );
		}

		parent::tear_down();
	}

	/**
	 * Create a grabber with the page key extension set, as init does for a request.
	 *
	 * @return PgCache_Html_Lifetime_Capture_Grabber
	 */
	private function grabber() {
		$grabber = new PgCache_Html_Lifetime_Capture_Grabber();

		$property = new ReflectionProperty( PgCache_ContentGrabber::class, '_page_key_extension' );
		$property->setAccessible( true );
		$property->setValue(
			$grabber,
			array(
				'useragent'   => false,
				'compression' => false,
				'cache'       => true,
			)
		);

		return $grabber;
	}

	/**
	 * A cache hit on an entry older than the HTML lifetime sends max-age for the full lifetime.
	 *
	 * @return void
	 */
	public function test_cache_hit_on_old_entry_uses_full_lifetime() {
		$created = time() - 60;

		$grabber = $this->grabber();
		$grabber->_send_headers( false, $created, 'etag', false, array() );

		$this->assertSame( 'max-age=30, public', $grabber->sent['Cache-Control'] );
		$this->assertEqualsWithDelta( time() + 30, strtotime( $grabber->sent['Expires'] ), 2 );
	}

	/**
	 * Last-Modified still reports when the entry was cached.
	 *
	 * @return void
	 */
	public function test_last_modified_still_uses_creation_time() {
		$created = time() - 60;

		$grabber = $this->grabber();
		$grabber->_send_headers( false, $created, 'etag', false, array() );

		$this->assertSame( Util_Content::http_date( $created ), $grabber->sent['Last-Modified'] );
	}

	/**
	 * A fresh response (created now) gets the full lifetime either way.
	 *
	 * @return void
	 */
	public function test_fresh_response_gets_full_lifetime() {
		$now = time();

		$grabber = $this->grabber();
		$grabber->_send_headers( false, $now, 'etag', false, array() );

		$this->assertSame( 'max-age=30, public', $grabber->sent['Cache-Control'] );
	}

	/**
	 * The cache_maxage policy also sends the full lifetime for an old entry.
	 *
	 * @return void
	 */
	public function test_cache_maxage_policy_on_old_entry_uses_full_lifetime() {
		Dispatcher::config()->set( 'browsercache.html.cache.policy', 'cache_maxage' );

		$created = time() - 60;

		$grabber = $this->grabber();
		$grabber->_send_headers( false, $created, 'etag', false, array() );

		$this->assertSame( 'max-age=30, public, must-revalidate, proxy-revalidate', $grabber->sent['Cache-Control'] );
		$this->assertEqualsWithDelta( time() + 30, strtotime( $grabber->sent['Expires'] ), 2 );
		$this->assertSame( Util_Content::http_date( $created ), $grabber->sent['Last-Modified'] );
	}

	/**
	 * A 304 on If-Modified-Since keeps Last-Modified at the cache creation time and lifetime from now.
	 *
	 * @return void
	 */
	public function test_not_modified_keeps_creation_time_for_last_modified() {
		$created = time() - 60;

		$_SERVER['HTTP_IF_MODIFIED_SINCE'] = Util_Content::http_date( $created );

		$grabber = $this->grabber();

		try {
			$grabber->_send_headers( false, $created, 'etag', false, array() );
			$this->fail( 'Expected a 304 response.' );
		} catch ( PgCache_Html_Lifetime_Not_Modified_Exception $e ) {
			$this->assertSame( 'HTTP/1.1 304 Not Modified', $grabber->sent['Status'] );
			$this->assertSame( Util_Content::http_date( $created ), $grabber->sent['Last-Modified'] );
			$this->assertSame( 'max-age=30, public', $grabber->sent['Cache-Control'] );
			$this->assertEqualsWithDelta( time() + 30, strtotime( $grabber->sent['Expires'] ), 2 );
		}
	}

	/**
	 * An If-Modified-Since that does not match the creation time is not a 304.
	 *
	 * @return void
	 */
	public function test_stale_if_modified_since_is_not_a_304() {
		$created = time() - 60;

		$_SERVER['HTTP_IF_MODIFIED_SINCE'] = Util_Content::http_date( $created - 3600 );

		$grabber = $this->grabber();
		$grabber->_send_headers( false, $created, 'etag', false, array() );

		$this->assertArrayNotHasKey( 'Status', $grabber->sent );
		$this->assertSame( Util_Content::http_date( $created ), $grabber->sent['Last-Modified'] );
	}
}
