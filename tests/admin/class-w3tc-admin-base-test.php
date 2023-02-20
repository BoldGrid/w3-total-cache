<?php
/**
 * File: class-w3tc-admin-base-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @author     BoldGrid <development@boldgrid.com>
 * @since      X.X.X
 * @link       https://www.boldgrid.com/w3-total-cache/
 */

/**
 * Class: W3tc_Admin_Base_Test
 *
 * @since X.X.X
 */
class W3tc_Admin_Base_Test extends WP_UnitTestCase {
	/**
	 * Test for WordPress.
	 *
	 * @since X.X.X
	 */
	public function test_wp() {
		$this->assertTrue( defined( 'ABSPATH' ) );
	}

	/**
	 * Test for W3 Total Cache.
	 *
	 * @since X.X.X
	 */
	public function test_w3tc() {
		$this->assertTrue( defined( 'W3TC' ) );
	}

	/**
	 * Test for definitions.
	 *
	 * @since X.X.X
	 */
	public function test_defines() {
		$definitions = array(
			'W3TC_VERSION',
			'W3TC_POWERED_BY',
			'W3TC_EMAIL',
			'W3TC_TEXT_DOMAIN',
			'W3TC_LINK_URL',
			'W3TC_LINK_NAME',
			'W3TC_FEED_URL',
			'W3TC_NEWS_FEED_URL',
			'W3TC_README_URL',
			'W3TC_SUPPORT_US_PRODUCT_URL',
			'W3TC_SUPPORT_US_RATE_URL',
			'W3TC_SUPPORT_US_TWEET',
			'W3TC_EDGE_TIMEOUT',
			'W3TC_SUPPORT_REQUEST_URL',
			'W3TC_SUPPORT_SERVICES_URL',
			'W3TC_FAQ_URL',
			'W3TC_TERMS_URL',
			'W3TC_TERMS_ACCEPT_URL',
			'W3TC_MAILLINGLIST_SIGNUP_URL',
			'W3TC_NEWRELIC_SIGNUP_URL',
			'W3TC_STACKPATH_SIGNUP_URL',
			'W3TC_STACKPATH_AUTHORIZE_URL',
			'W3TC_STACKPATH2_AUTHORIZE_URL',
			'W3TC_GOOGLE_DRIVE_AUTHORIZE_URL',
			'W3TC_LICENSE_API_URL',
			'W3TC_PURCHASE_URL',
			'W3TC_PURCHASE_PRODUCT_NAME',
			'W3TC_WIN',
			'W3TC_DIR',
			'W3TC_FILE',
			'W3TC_INC_DIR',
			'W3TC_INC_WIDGET_DIR',
			'W3TC_INC_OPTIONS_DIR',
			'W3TC_INC_LIGHTBOX_DIR',
			'W3TC_INC_POPUP_DIR',
			'W3TC_LIB_DIR',
			'W3TC_LIB_NETDNA_DIR',
			'W3TC_LIB_NEWRELIC_DIR',
			'W3TC_INSTALL_DIR',
			'W3TC_INSTALL_MINIFY_DIR',
			'W3TC_LANGUAGES_DIR',
			'WP_CONTENT_DIR',
			'W3TC_CACHE_DIR',
			'W3TC_CONFIG_DIR',
			'W3TC_CACHE_MINIFY_DIR',
			'W3TC_CACHE_PAGE_ENHANCED_DIR',
			'W3TC_CACHE_TMP_DIR',
			'W3TC_CACHE_BLOGMAP_FILENAME',
			'W3TC_CACHE_FILE_EXPIRE_MAX',
			'W3TC_CDN_COMMAND_UPLOAD',
			'W3TC_CDN_COMMAND_DELETE',
			'W3TC_CDN_COMMAND_PURGE',
			'W3TC_CDN_TABLE_QUEUE',
			'W3TC_CDN_TABLE_PATHMAP',
			'W3TC_INSTALL_FILE_ADVANCED_CACHE',
			'W3TC_INSTALL_FILE_DB',
			'W3TC_INSTALL_FILE_OBJECT_CACHE',
			'W3TC_ADDIN_FILE_ADVANCED_CACHE',
			'W3TC_ADDIN_FILE_DB',
			'W3TC_FILE_DB_CLUSTER_CONFIG',
			'W3TC_ADDIN_FILE_OBJECT_CACHE',
			'W3TC_MARKER_BEGIN_WORDPRESS',
			'W3TC_MARKER_BEGIN_PGCACHE_CORE',
			'W3TC_MARKER_BEGIN_PGCACHE_CACHE',
			'W3TC_MARKER_BEGIN_PGCACHE_WPSC',
			'W3TC_MARKER_BEGIN_BROWSERCACHE_CACHE',
			'W3TC_MARKER_BEGIN_MINIFY_CORE',
			'W3TC_MARKER_BEGIN_MINIFY_CACHE',
			'W3TC_MARKER_BEGIN_MINIFY_LEGACY',
			'W3TC_MARKER_BEGIN_CDN',
			'W3TC_MARKER_BEGIN_WEBP',
			'W3TC_MARKER_END_WORDPRESS',
			'W3TC_MARKER_END_PGCACHE_CORE',
			'W3TC_MARKER_END_PGCACHE_CACHE',
			'W3TC_MARKER_END_PGCACHE_LEGACY',
			'W3TC_MARKER_END_PGCACHE_WPSC',
			'W3TC_MARKER_END_BROWSERCACHE_CACHE',
			'W3TC_MARKER_END_MINIFY_CORE',
			'W3TC_MARKER_END_MINIFY_CACHE',
			'W3TC_MARKER_END_MINIFY_LEGACY',
			'W3TC_MARKER_END_CDN',
			'W3TC_MARKER_END_NEW_RELIC_CORE',
			'W3TC_MARKER_END_WEBP',
			'W3TC_EXTENSION_DIR',
			'W3TC_WP_JSON_URI',
			'W3TC_FEED_REGEXP',
		);

		foreach ( $definitions as $definition ) {
			$this->assertTrue( defined( $definition ) );
		}
	}

	/**
	 * Test w3tc_config().
	 *
	 * @since X.X.X
	 */
	public function test_w3tc_config() {
		$this->assertTrue( is_a( w3tc_config(), 'W3TC\Config' ) );
	}

	/**
	 * Test save config.
	 *
	 * @since X.X.X
	 */
	public function test_save_config() {
		$config = w3tc_config();

		$this->assertNull( $config->save() );
	}

	/**
	 * Test w3tc_flush_all().
	 *
	 * @since X.X.X
	 */
	public function test_w3tc_flush_all() {
		$this->assertNull( w3tc_flush_all() );
	}

	/**
	 * Test function w3tc_flush_post().
	 *
	 * @since X.X.X
	 */
	public function test_w3tc_flush_post() {
		$this->assertNull( w3tc_flush_post( 0 ) );
	}

	/**
	 * Test function w3tc_flush_posts().
	 *
	 * @since X.X.X
	 */
	public function test_w3tc_flush_posts() {
		$this->assertNull( w3tc_flush_posts() );
	}

	/**
	 * Test function w3tc_flush_url().
	 *
	 * @since X.X.X
	 */
	public function test_w3tc_flush_url() {
		$this->assertNull( w3tc_flush_url( site_url() ) );
	}

	/**
	 * Test function w3tc_flush_group().
	 *
	 * @since X.X.X
	 */
	public function test_w3tc_flush_group() {
		$this->assertNull( w3tc_flush_group( 'cookie' ) );
	}

	/**
	 * Test function w3tc_pgcache_flush().
	 *
	 * @since X.X.X
	 */
	public function test_w3tc_pgcache_flush() {
		$this->assertNull( w3tc_pgcache_flush() );
	}

	/**
	 * Test function w3tc_pgcache_flush_post().
	 *
	 * @since X.X.X
	 */
	public function test_w3tc_pgcache_flush_post() {
		$this->assertNull( w3tc_pgcache_flush_post( 0 ) );
	}

	/**
	 * Test function w3tc_pgcache_flush_url().
	 *
	 * @since X.X.X
	 */
	public function test_w3tc_pgcache_flush_url() {
		$this->assertNull( w3tc_pgcache_flush_url( site_url() ) );
	}

	/**
	 * Test function w3tc_dbcache_flush().
	 *
	 * @since X.X.X
	 */
	public function test_w3tc_dbcache_flush() {
		$this->assertNull( w3tc_dbcache_flush() );
	}

	/**
	 * Test function w3tc_minify_flush().
	 *
	 * @since X.X.X
	 */
	public function test_w3tc_minify_flush() {
		$this->assertNull( w3tc_minify_flush() );
	}

	/**
	 * Test function w3tc_objectcache_flush().
	 *
	 * @since X.X.X
	 */
	public function test_w3tc_objectcache_flush() {
		$this->assertNull( w3tc_objectcache_flush() );
	}

	/**
	 * Test function w3tc_cdn_purge_files().
	 *
	 * @since X.X.X
	 */
	public function test_w3tc_cdn_purge_files() {
		$this->assertNull( w3tc_cdn_purge_files( array( 'notexists.php' ) ) );
	}

	/**
	 * Test function w3tc_minify_script_group().
	 *
	 * @since X.X.X
	 */
	public function test_w3tc_minify_script_group() {
		$this->assertEmpty( w3tc_minify_script_group( 'default' ) );
	}

	/**
	 * Test function w3tc_minify_style_group().
	 *
	 * @since X.X.X
	 */
	public function test_w3tc_minify_style_group() {
		$this->assertEmpty( w3tc_minify_style_group( 'default' ) );
	}

	/**
	 * Test function w3tc_save_user_agent_group().
	 *
	 * @since X.X.X
	 */
	public function test_w3tc_save_user_agent_group() {
		$this->assertNull( w3tc_save_user_agent_group( 'test' ) );
	}

	/**
	 * Test function w3tc_delete_user_agent_group().
	 *
	 * @since X.X.X
	 */
	public function test_w3tc_delete_user_agent_group() {
		$this->assertNull( w3tc_delete_user_agent_group( 'test' ) );
	}

	/**
	 * Test function w3tc_get_user_agent_group().
	 *
	 * @since X.X.X
	 */
	public function test_w3tc_get_user_agent_group() {
		$this->assertIsArray( w3tc_get_user_agent_group( 'tablets' ) );
	}

	/**
	 * Test function w3tc_save_referrer_group().
	 *
	 * @since X.X.X
	 */
	public function test_w3tc_save_referrer_group() {
		$this->assertNull( w3tc_save_referrer_group( 'test' ) );
	}

	/**
	 * Test function w3tc_delete_referrer_group().
	 *
	 * @since X.X.X
	 */
	public function test_w3tc_delete_referrer_group() {
		$this->assertNull( w3tc_delete_referrer_group( 'test' ) );
	}

	/**
	 * Test function w3tc_get_referrer_group().
	 *
	 * @since X.X.X
	 */
	public function test_w3tc_get_referrer_group() {
		$this->assertIsArray( w3tc_get_referrer_group( 'tablets' ) );
	}

	/**
	 * Test function w3_instance().
	 *
	 * @since X.X.X
	 */
	public function test_w3_instance() {
		$this->assertTrue( is_a( w3_instance( 'W3_Config' ), 'W3TC\Config' ) );
	}

	/**
	 * Test function w3tc_e().
	 *
	 * @since X.X.X
	 */
	public function test_w3tc_e() {
		// Use output buffering to check output.
		ob_start();
		w3tc_e( 'cdn.stackpath.signUpAndSave', 'default' );
		$output = ob_get_contents();
		ob_end_clean();

		$this->assertIsString( $output );
	}

	/**
	 * Test function w3tc_er().
	 *
	 * @since X.X.X
	 */
	public function test_w3tc_er() {
		$this->assertIsString( w3tc_er( 'cdn.stackpath.signUpAndSave', 'default' ) );
	}

	/**
	 * Test function w3tc_add_action().
	 *
	 * @since X.X.X
	 */
	public function test_w3tc_add_action() {
		$this->assertNull(
			w3tc_add_action(
				'w3tc_test_action',
				function() {
					echo 'Test.';
				}
			)
		);
	}

	/**
	 * Test function w3tc_do_action().
	 *
	 * @since X.X.X
	 */
	public function test_w3tc_do_action() {
		// Use output buffering to check output.
		ob_start();
		$this->assertNull( w3tc_do_action( 'w3tc_test_action' ) );
		$output = ob_get_contents();
		ob_end_clean();

		$this->assertIsString( $output );
		$this->assertEquals( 'Test.', $output );
	}

	/**
	 * Test function w3tc_apply_filters().
	 *
	 * @since X.X.X
	 */
	public function test_w3tc_apply_filters() {
		w3tc_add_action(
			'w3tc_test_filter',
			function() {
				return 'Filter test.';
			}
		);

		$content = w3tc_apply_filters( 'w3tc_test_filter', 'FAIL' );

		$this->assertIsString( $content );
		$this->assertEquals( 'Filter test.', $content );
	}
}
