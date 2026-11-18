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
	 * @return bool
	 */
	public function _headers( $headers ) {
		$this->sent = $headers;

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
}
