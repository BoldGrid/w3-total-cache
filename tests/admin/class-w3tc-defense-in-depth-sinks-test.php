<?php
/**
 * File: class-w3tc-defense-in-depth-sinks-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.11.1
 */

declare( strict_types = 1 );

use W3TC\Generic_AdminActions_Config;
use W3TC\Util_PageUrls;

/**
 * Class: W3tc_Defense_In_Depth_Sinks_Test
 *
 * @since 2.11.1
 */
class W3tc_Defense_In_Depth_Sinks_Test extends WP_UnitTestCase {
	/**
	 * Minify cache directory.
	 *
	 * @var string
	 */
	private $minify_cache_dir;

	/**
	 * Sets up the minify cache directory.
	 *
	 * @since 2.11.1
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->minify_cache_dir = \sys_get_temp_dir() . '/w3tc-minify-cache-' . \uniqid( '', true );
		\mkdir( $this->minify_cache_dir, 0777, true );

		require_once W3TC_DIR . '/lib/Minify/Minify/Cache/File.php';
	}

	/**
	 * Removes files created for minify cache tests.
	 *
	 * @since 2.11.1
	 *
	 * @return void
	 */
	public function tear_down() {
		foreach ( \glob( $this->minify_cache_dir . '/*' ) as $path ) {
			\unlink( $path );
		}
		\rmdir( $this->minify_cache_dir );

		parent::tear_down();
	}

	/**
	 * Non-array metadata is treated as an empty metadata array.
	 *
	 * @since 2.11.1
	 *
	 * @return void
	 */
	public function test_minify_cache_ignores_non_array_metadata() {
		$cache = new \W3TCL\Minify\Minify_Cache_File( $this->minify_cache_dir );

		\file_put_contents( $this->minify_cache_dir . '/asset.css', 'body {}' );
		\file_put_contents( $this->minify_cache_dir . '/asset.css_meta', \serialize( new \stdClass() ) );
		\file_put_contents( $this->minify_cache_dir . '/stats.css_meta', \serialize( new \stdClass() ) );

		$fetched = $cache->fetch( 'asset.css' );
		$stats   = $cache->get_stats_size( \time() + 5 );

		$this->assertIsArray( $fetched );
		$this->assertSame( 'body {}', $fetched['content'] );
		$this->assertSame( 0, $stats['css']['items'] );
	}

	/**
	 * Archive counts preserve normal date and post-type matching.
	 *
	 * @since 2.11.1
	 *
	 * @return void
	 */
	public function test_archive_posts_count_matches_normal_archive() {
		$post_type = 'w3tc_archive_test';

		self::factory()->post->create(
			array(
				'post_type'   => $post_type,
				'post_status' => 'publish',
				'post_date'   => '2026-04-05 12:00:00',
			)
		);
		self::factory()->post->create(
			array(
				'post_type'   => $post_type,
				'post_status' => 'draft',
				'post_date'   => '2026-04-05 12:00:00',
			)
		);

		$this->assertSame(
			1,
			Util_PageUrls::get_archive_posts_count( 2026, 4, 5, $post_type )
		);
	}

	/**
	 * Configuration imports only accept JSON filenames.
	 *
	 * @since 2.11.1
	 *
	 * @return void
	 */
	public function test_config_import_requires_json_filename() {
		$this->assertTrue( Generic_AdminActions_Config::is_json_config_filename( 'settings.json' ) );
		$this->assertTrue( Generic_AdminActions_Config::is_json_config_filename( 'settings.JSON' ) );
		$this->assertFalse( Generic_AdminActions_Config::is_json_config_filename( 'settings.json.php' ) );
		$this->assertFalse( Generic_AdminActions_Config::is_json_config_filename( 'settings.txt' ) );
	}
}
