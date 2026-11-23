<?php
/**
 * File: class-w3tc-elementor-clear-cache-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.11.1
 */

declare( strict_types = 1 );

use W3TC\Util_AttachToActions;

/**
 * Class: W3tc_Elementor_Clear_Cache_Test
 *
 * Elementor clearing generated CSS must ask for a page-cache flush.
 *
 * @since 2.11.1
 */
class W3tc_Elementor_Clear_Cache_Test extends WP_UnitTestCase {

	/**
	 * The Elementor clear-cache action requests a post flush.
	 *
	 * @return void
	 */
	public function test_elementor_clear_cache_requests_a_post_flush() {
		Util_AttachToActions::flush_posts_on_actions();

		$this->assertSame(
			0,
			has_action( 'elementor/core/files/clear_cache', 'w3tc_flush_posts' )
		);

		$requested = false;
		add_filter(
			'w3tc_preflush_posts',
			static function () use ( &$requested ) {
				$requested = true;
				return false;
			}
		);

		do_action( 'elementor/core/files/clear_cache' );

		$this->assertTrue( $requested );
	}
}
