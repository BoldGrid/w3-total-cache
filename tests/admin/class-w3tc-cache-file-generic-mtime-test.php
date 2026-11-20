<?php
/**
 * Disk Enhanced identical writes.
 *
 * @package W3TC
 */

declare( strict_types = 1 );

use W3TC\Cache_File_Generic;
use W3TC\Config;
use W3TC\PgCache_Environment;

/**
 * An unchanged HTML file keeps the validator Apache derives from its mtime.
 */
class W3tc_Cache_File_Generic_Mtime_Test extends WP_UnitTestCase {

	/**
	 * Writing the same HTML again does not replace the file.
	 *
	 * @return void
	 */
	public function test_identical_page_cache_write_keeps_mtime() {
		$dir = WP_CONTENT_DIR . '/cache/w3tc-eng3-4299';
		wp_mkdir_p( $dir );

		$cache = new Cache_File_Generic(
			array(
				'blog_id'   => 1,
				'cache_dir' => $dir,
			)
		);
		$data  = array(
			'content' => '<html><body>unchanged</body></html>',
			'headers' => array(),
		);

		$this->assertTrue( $cache->set( 'index.html', $data ) );

		$path = $dir . '/index.html';
		$this->assertFileExists( $path );
		touch( $path, time() - 30 );
		clearstatcache( true, $path );
		$before = filemtime( $path );

		$this->assertTrue( $cache->set( 'index.html', $data ) );
		clearstatcache( true, $path );

		$this->assertSame( $data['content'], file_get_contents( $path ) );
		$this->assertSame( $before, filemtime( $path ) );

		$changed                 = $data;
		$changed['content']      = '<html><body>changed</body></html>';
		$this->assertTrue( $cache->set( 'index.html', $changed ) );
		clearstatcache( true, $path );
		$this->assertSame( $changed['content'], file_get_contents( $path ) );
		$this->assertGreaterThan( $before, filemtime( $path ) );
	}

	/**
	 * ETag rules still use the file mtime and size.
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
