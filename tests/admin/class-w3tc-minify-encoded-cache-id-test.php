<?php
/**
 * Encoded minify entries are read with the id they were stored under.
 *
 * @package W3TC
 */

/**
 * Records minify cache ids without a remote cache.
 */
class W3tc_Recording_Minify_Cache {
	/**
	 * Stored ids.
	 *
	 * @var string[]
	 */
	public $stores = array();

	/**
	 * Ids passed to isValid.
	 *
	 * @var string[]
	 */
	public $checks = array();

	/**
	 * Stored payloads.
	 *
	 * @var array
	 */
	public $data = array();

	/**
	 * Store one entry.
	 *
	 * @param string $id   Cache id.
	 * @param array  $data Payload.
	 * @return bool
	 */
	public function store( $id, $data ) {
		$this->stores[]    = $id;
		$this->data[ $id ] = $data;
		return true;
	}

	/**
	 * Whether an id was stored.
	 *
	 * @param string $id        Cache id.
	 * @param int    $src_mtime Source mtime, unused.
	 * @return bool
	 */
	public function isValid( $id, $src_mtime ) {
		unset( $src_mtime );
		$this->checks[] = $id;
		return isset( $this->data[ $id ] );
	}

	/**
	 * Fetch a stored entry.
	 *
	 * @param string $id Cache id.
	 * @return array|false
	 */
	public function fetch( $id ) {
		return isset( $this->data[ $id ] ) ? $this->data[ $id ] : false;
	}

	/**
	 * Display is not used in quiet mode.
	 *
	 * @param string $id Cache id.
	 * @return void
	 */
	public function display( $id ) {
		unset( $id );
	}

	/**
	 * Size of a stored entry.
	 *
	 * @param string $id Cache id.
	 * @return int|false
	 */
	public function getSize( $id ) {
		return isset( $this->data[ $id ]['content'] ) ? strlen( $this->data[ $id ]['content'] ) : false;
	}
}

/**
 * Gzip minify cache ids.
 */
class W3tc_Minify_Encoded_Cache_Id_Test extends WP_UnitTestCase {

	/**
	 * An uncompressed entry is stored and checked under one id.
	 *
	 * @return void
	 */
	public function test_identity_encoding_uses_one_cache_id() {
		$cache = new W3tc_Recording_Minify_Cache();
		$this->serve_js( $cache, '' );

		$this->assertNotEmpty( $cache->stores );
		$this->assertContains( $cache->stores[0], $cache->checks );
	}

	/**
	 * A second gzip request is served from the stored id.
	 *
	 * @return void
	 */
	public function test_encoded_minify_lookup_uses_the_stored_cache_id() {
		$cache = new W3tc_Recording_Minify_Cache();
		$this->serve_js( $cache, 'gzip' );
		$this->serve_js( $cache, 'gzip' );

		$stored = array_values(
			array_filter(
				$cache->stores,
				static function ( $id ) {
					return (bool) preg_match( '/gzip$/', $id );
				}
			)
		);
		$checked = array_values(
			array_filter(
				$cache->checks,
				static function ( $id ) {
					return (bool) preg_match( '/gzip$/', $id );
				}
			)
		);

		$this->assertCount( 1, $stored );
		$this->assertNotEmpty( $checked );
		foreach ( $checked as $id ) {
			$this->assertContains( $id, $stored );
		}
	}

	/**
	 * Serve one quiet minify request.
	 *
	 * @param W3tc_Recording_Minify_Cache $cache         Recording cache.
	 * @param string                      $encode_method Encoding name, or empty.
	 * @return void
	 */
	private function serve_js( $cache, $encode_method ) {
		$dir = wp_tempnam( 'w3tc-minify-id' );
		unlink( $dir );
		wp_mkdir_p( $dir );
		$file = $dir . '/app.js';
		file_put_contents( $file, 'var triage = 1;' );

		\W3TCL\Minify\Minify::setCache( $cache );
		\W3TCL\Minify\Minify::setCacheId( 'triage362' );

		try {
			\W3TCL\Minify\Minify::serve(
				'Files',
				array(
					'files'            => array( $file ),
					'quiet'            => true,
					'encodeMethod'     => $encode_method,
					'encodeOutput'     => true,
					'maxAge'           => 0,
					'debug'            => false,
					'disable_304'      => true,
					'lastModifiedTime' => 0,
					'cacheHeaders'     => array(
						'use_etag'             => false,
						'expires_enabled'      => false,
						'cacheheaders_enabled' => false,
						'cacheheaders'         => 'cache_validation',
					),
				)
			);
		} finally {
			unlink( $file );
			rmdir( $dir );
		}
	}
}
