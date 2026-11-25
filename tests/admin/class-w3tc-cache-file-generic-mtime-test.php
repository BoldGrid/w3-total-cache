<?php
/**
 * Disk Enhanced identical writes.
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.11.0
 */

declare( strict_types = 1 );

use W3TC\Cache_File_Cleaner_Generic;
use W3TC\Cache_File_Generic;
use W3TC\Config;
use W3TC\Extension_AlwaysCached_Plugin;
use W3TC\PgCache_Environment;
use W3TC\Util_File;

/**
 * An unchanged HTML file keeps the validator Apache derives from its mtime, as long as that does not shorten its cache lifetime.
 *
 * phpcs:disable WordPress.WP.AlternativeFunctions
 */
class W3tc_Cache_File_Generic_Mtime_Test extends WP_UnitTestCase {

	/**
	 * Lifetime used by the age tests, in seconds.
	 *
	 * @var int
	 */
	const LIFETIME = 100;

	/**
	 * Scratch cache directory for the current test, under the cache root.
	 *
	 * @var string
	 */
	private $dir = '';

	/**
	 * Creates a unique cache directory.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->dir = W3TC_CACHE_DIR . '/page-cache-identical-' . wp_generate_password( 12, false );
		wp_mkdir_p( $this->dir );
	}

	/**
	 * Removes the cache directory.
	 *
	 * @return void
	 */
	public function tear_down() {
		Util_File::rmdir( $this->dir );

		parent::tear_down();
	}

	/**
	 * Builds a cache for the scratch directory.
	 *
	 * @param int $expire Lifetime in seconds, 0 for the default.
	 *
	 * @return Cache_File_Generic
	 */
	private function cache( $expire = 0 ) {
		return new Cache_File_Generic(
			array(
				'blog_id'          => 1,
				'cache_dir'        => $this->dir,
				'expire'           => $expire,
				'use_expired_data' => true,
			)
		);
	}

	/**
	 * Builds a cache entry.
	 *
	 * @param string $content Page content.
	 * @param array  $extra   Extra entry keys.
	 *
	 * @return array
	 */
	private function entry( $content, $extra = array() ) {
		return array_merge(
			array(
				'content' => $content,
				'headers' => array(),
			),
			$extra
		);
	}

	/**
	 * Sets a file mtime in the past.
	 *
	 * @param string $path Path.
	 * @param int    $age  Seconds in the past.
	 *
	 * @return int The mtime that was set.
	 */
	private function age( $path, $age ) {
		$time = time() - $age;
		touch( $path, $time );
		clearstatcache( true, $path );

		return $time;
	}

	/**
	 * Runs the garbage collector over the scratch directory.
	 *
	 * @param array $config Extra cleaner config.
	 *
	 * @return void
	 */
	private function collect_garbage( $config = array() ) {
		$cleaner = new Cache_File_Cleaner_Generic(
			array_merge(
				array(
					'cache_dir' => $this->dir,
					'expire'    => self::LIFETIME,
				),
				$config
			)
		);
		$cleaner->clean();
	}

	/**
	 * Builds a page that ends with the footer comment Generic_Plugin::ob_callback() appends.
	 *
	 * @param string $body  Page body.
	 * @param string $time  Time in the "Served from" line.
	 * @param string $extra Extra footer lines, such as page cache debug info.
	 *
	 * @return string
	 */
	private function page( $body, $time = '2026-11-24 10:00:00', $extra = '' ) {
		return '<html><body>' . $body . '</body></html>' .
			"\r\n<!--\r\nPage Caching using Disk: Enhanced\r\n" . $extra . "\r\nServed from: example.org @ " . $time . " by W3 Total Cache\r\n-->";
	}

	/**
	 * Writing the same HTML again does not replace the file.
	 *
	 * @return void
	 */
	public function test_identical_page_cache_write_keeps_mtime() {
		$cache = $this->cache();
		$data  = $this->entry( '<html><body>unchanged</body></html>' );
		$path  = $this->dir . '/index.html';

		$this->assertTrue( $cache->set( 'index.html', $data ) );
		$this->assertFileExists( $path );
		$before = $this->age( $path, 30 );

		$this->assertTrue( $cache->set( 'index.html', $data ) );
		clearstatcache( true, $path );

		$this->assertSame( $data['content'], file_get_contents( $path ) );
		$this->assertSame( $before, filemtime( $path ) );

		$changed = $this->entry( '<html><body>changed</body></html>' );
		$this->assertTrue( $cache->set( 'index.html', $changed ) );
		clearstatcache( true, $path );
		$this->assertSame( $changed['content'], file_get_contents( $path ) );
		$this->assertGreaterThan( $before, filemtime( $path ) );
	}

	/**
	 * Compressed variants and feeds go through the same write.
	 *
	 * @return void
	 */
	public function test_identical_gzip_and_feed_writes_keep_mtime() {
		$cache = $this->cache();

		foreach ( array(
			'index.html_gzip' => gzencode( '<html><body>unchanged</body></html>' ),
			'feed.xml'        => '<?xml version="1.0"?><rss></rss>',
		) as $key => $content ) {
			$path = $this->dir . '/' . $key;
			$this->assertTrue( $cache->set( $key, $this->entry( $content ) ) );
			$before = $this->age( $path, 30 );

			$this->assertTrue( $cache->set( $key, $this->entry( $content ) ) );
			clearstatcache( true, $path );

			$this->assertSame( $before, filemtime( $path ), $key );
		}
	}

	/**
	 * A page regenerated after a purge gets the purged file back, with its mtime.
	 *
	 * @return void
	 */
	public function test_identical_write_after_purge_restores_old_file() {
		$cache = $this->cache();
		$data  = $this->entry( '<html><body>unchanged</body></html>' );
		$path  = $this->dir . '/index.html';

		$cache->set( 'index.html', $data );
		$before = $this->age( $path, 30 );

		$this->assertTrue( $cache->delete( 'index.html' ) );
		$this->assertFileDoesNotExist( $path );
		$this->assertFileExists( $path . '_old' );

		$this->assertTrue( $cache->set( 'index.html', $data ) );
		clearstatcache( true, $path );

		$this->assertSame( $data['content'], file_get_contents( $path ) );
		$this->assertSame( $before, filemtime( $path ) );
		$this->assertFileDoesNotExist( $path . '_old' );
	}

	/**
	 * Changed content after a purge replaces the old file.
	 *
	 * @return void
	 */
	public function test_changed_write_after_purge_replaces_old_file() {
		$cache = $this->cache();
		$path  = $this->dir . '/index.html';

		$cache->set( 'index.html', $this->entry( '<html><body>one</body></html>' ) );
		$before = $this->age( $path, 30 );
		$cache->delete( 'index.html' );

		$changed = $this->entry( '<html><body>two</body></html>' );
		$this->assertTrue( $cache->set( 'index.html', $changed ) );
		clearstatcache( true, $path );

		$this->assertSame( $changed['content'], file_get_contents( $path ) );
		$this->assertGreaterThan( $before, filemtime( $path ) );
		$this->assertFileDoesNotExist( $path . '_old' );
	}

	/**
	 * Garbage collection: an unchanged file with plenty of lifetime left is kept and the collector leaves it alone.
	 *
	 * @return void
	 */
	public function test_young_unchanged_file_keeps_mtime_and_survives_garbage_collection() {
		$cache = $this->cache( self::LIFETIME );
		$data  = $this->entry( '<html><body>unchanged</body></html>' );
		$path  = $this->dir . '/index.html';

		$cache->set( 'index.html', $data, self::LIFETIME );
		$before = $this->age( $path, 30 );

		$cache->set( 'index.html', $data, self::LIFETIME );
		clearstatcache( true, $path );
		$this->assertSame( $before, filemtime( $path ) );

		$this->collect_garbage();
		$this->assertFileExists( $path );
		$this->assertFileDoesNotExist( $path . '_old' );
	}

	/**
	 * Garbage collection: with less than half the lifetime left the file is rewritten, so the regenerated
	 * page is not moved to _old by the next collection.
	 *
	 * @return void
	 */
	public function test_unchanged_file_past_half_lifetime_is_rewritten_with_fresh_mtime() {
		$cache = $this->cache( self::LIFETIME );
		$data  = $this->entry( '<html><body>unchanged</body></html>' );
		$path  = $this->dir . '/index.html';

		$cache->set( 'index.html', $data, self::LIFETIME );
		$before = $this->age( $path, 80 );

		$cache->set( 'index.html', $data, self::LIFETIME );
		clearstatcache( true, $path );
		$this->assertGreaterThan( $before, filemtime( $path ) );

		$this->collect_garbage();
		$this->assertFileExists( $path );
		$this->assertFileDoesNotExist( $path . '_old' );
	}

	/**
	 * Garbage collection: a file that the collector already expired is not restored with its stale mtime.
	 *
	 * @return void
	 */
	public function test_unchanged_write_after_garbage_collection_gets_fresh_mtime() {
		$cache = $this->cache( self::LIFETIME );
		$data  = $this->entry( '<html><body>unchanged</body></html>' );
		$path  = $this->dir . '/index.html';

		$cache->set( 'index.html', $data, self::LIFETIME );
		$before = $this->age( $path, self::LIFETIME + 20 );

		$this->collect_garbage();
		$this->assertFileDoesNotExist( $path );
		$this->assertFileExists( $path . '_old' );

		$cache->set( 'index.html', $data, self::LIFETIME );
		clearstatcache( true, $path );

		$this->assertSame( $data['content'], file_get_contents( $path ) );
		$this->assertGreaterThan( $before, filemtime( $path ) );
		$this->assertFileDoesNotExist( $path . '_old' );

		$this->collect_garbage();
		$this->assertFileExists( $path );
	}

	/**
	 * Always Cached: a file older than the ahead-generation time is rewritten, since the clean after
	 * generation moves every file older than that time to _old.
	 *
	 * @return void
	 */
	public function test_file_older_than_ahead_generation_time_is_rewritten() {
		$cache = $this->cache( self::LIFETIME );
		$path  = $this->dir . '/index.html';

		$before_time = time() - 10;
		$data        = $this->entry( '<html><body>unchanged</body></html>', array( 'before_time' => $before_time ) );

		$cache->set( 'index.html', $data, self::LIFETIME );
		$before = $this->age( $path, 30 );

		$cache->set( 'index.html', $data, self::LIFETIME );
		clearstatcache( true, $path );
		$this->assertGreaterThanOrEqual( $before_time, filemtime( $path ) );

		$this->collect_garbage( array( 'time_min_valid' => $before_time ) );
		$this->assertFileExists( $path );
		$this->assertFileDoesNotExist( $path . '_old' );
	}

	/**
	 * Always Cached: a file newer than the ahead-generation time is kept.
	 *
	 * @return void
	 */
	public function test_file_newer_than_ahead_generation_time_keeps_mtime() {
		$cache = $this->cache( self::LIFETIME );
		$path  = $this->dir . '/index.html';

		$before_time = time() - 60;
		$data        = $this->entry( '<html><body>unchanged</body></html>', array( 'before_time' => $before_time ) );

		$cache->set( 'index.html', $data, self::LIFETIME );
		$before = $this->age( $path, 30 );

		$cache->set( 'index.html', $data, self::LIFETIME );
		clearstatcache( true, $path );
		$this->assertSame( $before, filemtime( $path ) );
	}

	/**
	 * Always Cached hands the ahead-generation time to the cache entry.
	 *
	 * @return void
	 */
	public function test_always_cached_passes_ahead_generation_time_to_entry() {
		$plugin   = new Extension_AlwaysCached_Plugin();
		$property = new ReflectionProperty( $plugin, 'request_queue_item_extension' );
		$property->setAccessible( true );
		$property->setValue( $plugin, array( 'before_time' => 1234567890 ) );

		$entry = $plugin->w3tc_pagecache_set( $this->entry( 'content' ) );

		$this->assertSame( 1234567890, $entry['before_time'] );
	}

	/**
	 * A different "Served from" time does not replace the file, and the stored footer stays.
	 *
	 * @return void
	 */
	public function test_footer_only_difference_keeps_file_and_mtime() {
		$cache = $this->cache();
		$first = $this->entry( $this->page( 'unchanged' ) );
		$path  = $this->dir . '/index.html';

		$cache->set( 'index.html', $first );
		$before = $this->age( $path, 30 );

		$cache->set( 'index.html', $this->entry( $this->page( 'unchanged', '2026-11-24 10:00:01' ) ) );
		clearstatcache( true, $path );

		$this->assertSame( $before, filemtime( $path ) );
		$this->assertSame( $first['content'], file_get_contents( $path ) );
		$this->assertStringContainsString( '@ 2026-11-24 10:00:00 by W3 Total Cache', file_get_contents( $path ) );
	}

	/**
	 * Debug lines inside the footer comment do not matter either.
	 *
	 * @return void
	 */
	public function test_footer_debug_lines_do_not_replace_file() {
		$cache = $this->cache();
		$path  = $this->dir . '/index.html';

		$cache->set( 'index.html', $this->entry( $this->page( 'unchanged', '2026-11-24 10:00:00', "\r\nPage cache debug info:\r\nCreation Time: 1.000s" ) ) );
		$before = $this->age( $path, 30 );

		$cache->set( 'index.html', $this->entry( $this->page( 'unchanged', '2026-11-24 10:00:05', "\r\nPage cache debug info:\r\nCreation Time: 2.500s" ) ) );
		clearstatcache( true, $path );

		$this->assertSame( $before, filemtime( $path ) );
	}

	/**
	 * Control: a changed body replaces the file even when the footer is the same shape.
	 *
	 * @return void
	 */
	public function test_body_change_with_footer_replaces_file() {
		$cache = $this->cache();
		$path  = $this->dir . '/index.html';

		$cache->set( 'index.html', $this->entry( $this->page( 'one' ) ) );
		$before = $this->age( $path, 30 );

		$changed = $this->entry( $this->page( 'two', '2026-11-24 10:00:01' ) );
		$cache->set( 'index.html', $changed );
		clearstatcache( true, $path );

		$this->assertSame( $changed['content'], file_get_contents( $path ) );
		$this->assertGreaterThan( $before, filemtime( $path ) );
	}

	/**
	 * A footer that is added or removed counts as a change.
	 *
	 * @return void
	 */
	public function test_footer_added_or_removed_replaces_file() {
		$cache = $this->cache();
		$path  = $this->dir . '/index.html';

		$cache->set( 'index.html', $this->entry( '<html><body>same</body></html>' ) );
		$before = $this->age( $path, 30 );

		$with_footer = $this->entry( $this->page( 'same' ) );
		$cache->set( 'index.html', $with_footer );
		clearstatcache( true, $path );
		$this->assertSame( $with_footer['content'], file_get_contents( $path ) );
		$this->assertGreaterThan( $before, filemtime( $path ) );

		$before = $this->age( $path, 30 );
		$cache->set( 'index.html', $this->entry( '<html><body>same</body></html>' ) );
		clearstatcache( true, $path );
		$this->assertGreaterThan( $before, filemtime( $path ) );
	}

	/**
	 * Only the last comment is the footer: a "Served from" comment followed by more markup is page content.
	 *
	 * @return void
	 */
	public function test_footer_comment_before_more_markup_is_page_content() {
		$cache = $this->cache();
		$path  = $this->dir . '/index.html';

		$cache->set( 'index.html', $this->entry( $this->page( 'same', '2026-11-24 10:00:00' ) . '<p>tail</p>' ) );
		$before = $this->age( $path, 30 );

		$cache->set( 'index.html', $this->entry( $this->page( 'same', '2026-11-24 10:00:01' ) . '<p>tail</p>' ) );
		clearstatcache( true, $path );

		$this->assertGreaterThan( $before, filemtime( $path ) );
	}

	/**
	 * After a purge a footer-only difference restores the _old file, and a body change replaces it.
	 *
	 * @return void
	 */
	public function test_footer_only_difference_after_purge_restores_old_file() {
		$cache = $this->cache();
		$first = $this->entry( $this->page( 'unchanged' ) );
		$path  = $this->dir . '/index.html';

		$cache->set( 'index.html', $first );
		$before = $this->age( $path, 30 );
		$cache->delete( 'index.html' );
		$this->assertFileExists( $path . '_old' );

		$cache->set( 'index.html', $this->entry( $this->page( 'unchanged', '2026-11-24 10:00:01' ) ) );
		clearstatcache( true, $path );

		$this->assertSame( $before, filemtime( $path ) );
		$this->assertSame( $first['content'], file_get_contents( $path ) );
		$this->assertFileDoesNotExist( $path . '_old' );

		// Control: the body changed, so the purged file is not restored.
		$cache->delete( 'index.html' );
		$changed = $this->entry( $this->page( 'different', '2026-11-24 10:00:02' ) );
		$cache->set( 'index.html', $changed );
		clearstatcache( true, $path );

		$this->assertSame( $changed['content'], file_get_contents( $path ) );
		$this->assertGreaterThan( $before, filemtime( $path ) );
		$this->assertFileDoesNotExist( $path . '_old' );
	}

	/**
	 * Compressed variants are compared on their decompressed page.
	 *
	 * @return void
	 */
	public function test_compressed_variant_footer_only_difference_keeps_file() {
		$cache = $this->cache();
		$path  = $this->dir . '/index.html_gzip';

		$first = $this->entry( gzencode( $this->page( 'unchanged' ) ), array( 'c' => 'gzip' ) );
		$cache->set( 'index.html_gzip', $first );
		$before = $this->age( $path, 30 );

		$cache->set( 'index.html_gzip', $this->entry( gzencode( $this->page( 'unchanged', '2026-11-24 10:00:01' ) ), array( 'c' => 'gzip' ) ) );
		clearstatcache( true, $path );

		$this->assertSame( $before, filemtime( $path ) );
		$this->assertSame( $first['content'], file_get_contents( $path ) );

		// Control: a changed body replaces the compressed file.
		$changed = $this->entry( gzencode( $this->page( 'different', '2026-11-24 10:00:02' ) ), array( 'c' => 'gzip' ) );
		$cache->set( 'index.html_gzip', $changed );
		clearstatcache( true, $path );

		$this->assertSame( $changed['content'], file_get_contents( $path ) );
		$this->assertGreaterThan( $before, filemtime( $path ) );
	}

	/**
	 * The footer-only difference still follows the lifetime guard.
	 *
	 * @return void
	 */
	public function test_footer_only_difference_past_half_lifetime_is_rewritten() {
		$cache = $this->cache( self::LIFETIME );
		$path  = $this->dir . '/index.html';

		$cache->set( 'index.html', $this->entry( $this->page( 'unchanged' ) ), self::LIFETIME );
		$before = $this->age( $path, 80 );

		$newer = $this->entry( $this->page( 'unchanged', '2026-11-24 10:01:20' ) );
		$cache->set( 'index.html', $newer, self::LIFETIME );
		clearstatcache( true, $path );

		$this->assertSame( $newer['content'], file_get_contents( $path ) );
		$this->assertGreaterThan( $before, filemtime( $path ) );
	}

	/**
	 * Guard, not proof of the fix: ETag rules still use the file mtime and size.
	 *
	 * @return void
	 */
	public function test_apache_rules_keep_file_etag_mtime_size() {
		$config = new Config();
		$config->set( 'browsercache.enabled', true );
		$config->set( 'browsercache.html.etag', 1 );

		$environment = new PgCache_Environment();
		$method      = new ReflectionMethod( $environment, 'rules_cache_generate_apache' );
		$method->setAccessible( true );

		$rules = $method->invoke( $environment, $config );

		$this->assertStringContainsString( "FileETag MTime Size\n", $rules );
	}
}
