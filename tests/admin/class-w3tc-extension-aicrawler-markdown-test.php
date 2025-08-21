<?php
/**
 * File: class-w3tc-extension-aicrawler-markdown-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      X.X.X
 */

declare( strict_types = 1 );

use W3TC\Extension_AiCrawler_Markdown_Server;

/**
 * Class: Extension_AiCrawler_Markdown_Test
 *
 * @since X.X.X
 */
class W3tc_Extension_AiCrawler_Markdown_Test extends WP_UnitTestCase {
        /**
         * Test markdown URL for pretty permalinks.
         *
         * @since X.X.X
         */
        public function test_get_markdown_url_pretty() {
                update_option( 'permalink_structure', '/%postname%/' );
                flush_rewrite_rules();

                $post_id = self::factory()->post->create();
                $expected = untrailingslashit( get_permalink( $post_id ) ) . '.md';

                $this->assertSame( $expected, Extension_AiCrawler_Markdown_Server::get_markdown_url( $post_id ) );
        }

        /**
         * Test markdown URL for plain permalinks.
         *
         * @since X.X.X
         */
        public function test_get_markdown_url_plain() {
                update_option( 'permalink_structure', '' );
                flush_rewrite_rules();

                $post_id = self::factory()->post->create();
                $expected = add_query_arg( array( 'w3tc_aicrawler_markdown' => $post_id ), home_url( '/' ) );

                $this->assertSame( $expected, Extension_AiCrawler_Markdown_Server::get_markdown_url( $post_id ) );
        }
}

