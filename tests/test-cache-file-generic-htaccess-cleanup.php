<?php
/**
 * Disk: Enhanced per-page .htaccess follows the latest cached headers.
 *
 * @package W3TC\Tests
 */

use W3TC\Cache_File_Generic;

/**
 * Tests for Cache_File_Generic::set() writing and removing per-directory .htaccess files.
 */
class Cache_File_Generic_Htaccess_Cleanup_Test extends WP_UnitTestCase {
	/**
	 * Scratch cache root for the test, inside the checkout and removed afterwards.
	 *
	 * @var string
	 */
	private $cache_root = '';

	/**
	 * Server software before the test.
	 *
	 * @var string|null
	 */
	private $server_software = null;

	/**
	 * Create the scratch cache root and force the Apache branch.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		// Writes must stay under W3TC_CACHE_DIR, so the scratch root lives there.
		$this->cache_root = W3TC_CACHE_DIR . '/htaccess-cleanup-' . getmypid();
		wp_mkdir_p( $this->cache_root );

		$this->server_software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : null;
		$_SERVER['SERVER_SOFTWARE'] = 'Apache/2.4';

		// Stand-in for Minify_Plugin::w3tc_pagecache_set_header(): tag preload headers for HTML only.
		add_filter( 'w3tc_pagecache_set_header', array( $this, 'tag_preload_header' ), 10, 2 );
	}

	/**
	 * Remove the scratch files and restore the environment.
	 *
	 * @return void
	 */
	public function tear_down() {
		remove_filter( 'w3tc_pagecache_set_header', array( $this, 'tag_preload_header' ), 10 );

		if ( null === $this->server_software ) {
			unset( $_SERVER['SERVER_SOFTWARE'] );
		} else {
			$_SERVER['SERVER_SOFTWARE'] = $this->server_software;
		}

		$this->remove_tree( $this->cache_root );

		parent::tear_down();
	}

	/**
	 * Filter callback: keep Link preload headers for HTML only. Other headers are dropped,
	 * as PgCache_Plugin does for the file_generic engine.
	 *
	 * @param array $header          Header being stored.
	 * @param array $header_original Original header.
	 *
	 * @return array|null
	 */
	public function tag_preload_header( $header, $header_original ) {
		if ( 'Link' !== $header_original['n'] ) {
			return null;
		}

		$header['files_match'] = '\\.html[_a-z]*$';

		return $header;
	}

	/**
	 * Build a cache object for the scratch root.
	 *
	 * @return Cache_File_Generic
	 */
	private function cache() {
		return new Cache_File_Generic(
			array(
				'blog_id'          => 0,
				'cache_dir'        => $this->cache_root,
				'section'          => 'page',
				'locking'          => false,
				'flush_timelimit'  => 0,
				'use_expired_data' => false,
				'expire'           => 3600,
			)
		);
	}

	/**
	 * Path of the .htaccess written next to the page used by the tests.
	 *
	 * @return string
	 */
	private function htaccess_path() {
		return $this->cache_root . '/example.com/search/.htaccess';
	}

	/**
	 * A page cached with a preload Link header gets a .htaccess with that header.
	 *
	 * @return void
	 */
	public function test_preload_header_is_written_to_htaccess() {
		$cache = $this->cache();
		$cache->set(
			'example.com/search/_index_slash.html',
			array(
				'content' => '<html></html>',
				'headers' => array(
					array(
						'n' => 'Link',
						'v' => '</wp-content/cache/minify/abc123.css>; rel=preload; as=style',
					),
				),
			),
			0,
			''
		);

		$this->assertFileExists( $this->htaccess_path() );
		$this->assertStringContainsString( 'Header add Link', file_get_contents( $this->htaccess_path() ) );
	}

	/**
	 * Re-caching the page with no headers removes the .htaccess left by the earlier write.
	 *
	 * @return void
	 */
	public function test_recache_without_headers_removes_stale_htaccess() {
		$cache = $this->cache();
		$cache->set(
			'example.com/search/_index_slash.html',
			array(
				'content' => '<html></html>',
				'headers' => array(
					array(
						'n' => 'Link',
						'v' => '</wp-content/cache/minify/abc123.css>; rel=preload; as=style',
					),
				),
			),
			0,
			''
		);
		$this->assertFileExists( $this->htaccess_path() );

		$cache->set(
			'example.com/search/_index_slash.html',
			array(
				'content' => '<html>new</html>',
				'headers' => array(),
			),
			0,
			''
		);

		$this->assertFileDoesNotExist( $this->htaccess_path() );
	}

	/**
	 * Re-caching a page without headers keeps a .htaccess that holds the page cache rules.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_recache_without_headers_keeps_page_cache_rules_file() {
		$rules = W3TC_MARKER_BEGIN_PGCACHE_CACHE . "\nAddEncoding gzip .html_gzip\n" . W3TC_MARKER_END_PGCACHE_CACHE . "\n";

		wp_mkdir_p( dirname( $this->htaccess_path() ) );
		file_put_contents( $this->htaccess_path(), $rules );

		$this->cache()->set(
			'example.com/search/_index_slash.html',
			array(
				'content' => '<html></html>',
				'headers' => array(),
			),
			0,
			''
		);

		$this->assertFileExists( $this->htaccess_path() );
		$this->assertSame( $rules, file_get_contents( $this->htaccess_path() ) );
	}

	/**
	 * XML pages keep their AddType rules when re-cached.
	 *
	 * @return void
	 */
	public function test_xml_page_keeps_addtype_rules() {
		$cache = $this->cache();
		$cache->set(
			'example.com/search/_index_slash.xml',
			array(
				'content' => '<xml/>',
				'headers' => array(
					'Content-Type' => 'text/xml; charset=UTF-8',
				),
			),
			0,
			''
		);

		$this->assertFileExists( $this->htaccess_path() );
		$this->assertStringContainsString( 'AddType text/xml .html', file_get_contents( $this->htaccess_path() ) );
	}

	/**
	 * Recursively remove the scratch directory.
	 *
	 * @param string $path Path to remove.
	 *
	 * @return void
	 */
	private function remove_tree( $path ) {
		if ( ! is_dir( $path ) ) {
			return;
		}

		foreach ( scandir( $path ) as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			$child = $path . '/' . $entry;
			if ( is_dir( $child ) ) {
				$this->remove_tree( $child );
			} else {
				unlink( $child );
			}
		}

		rmdir( $path );
	}
}
