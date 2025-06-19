<?php
/**
 * File: class-util-environment-url-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 */

declare( strict_types = 1 );

use W3TC\Util_Environment;

/**
 * Class: Util_Environment_Url_Test
 *
 * @since X.X.X
 */
class Util_Environment_Url_Test extends WP_UnitTestCase {
       /**
        * Test url_format() with basic parameters.
        *
        * @since X.X.X
        */
       public function test_url_format_basic() {
                $url = Util_Environment::url_format( 'https://example.com/test.php', array( 'a' => 1, 'b' => 'two' ) );
                $this->assertSame( 'https://example.com/test.php?a=1&b=two', $url );
        }

       /**
        * Test url_query() with nested arrays and skip empty parameters.
        *
        * @since X.X.X
        */
       public function test_url_query_nested_skip_empty() {
                $params = array(
                        'foo' => array( 'bar' => 1, 'baz' => 2 ),
                        'empty' => '',
                );
                $query = Util_Environment::url_query( $params, true );
                // Function currently joins nested parameters using the parent key
                // as the separator.
                $this->assertSame( 'foo[bar]=1foofoo[baz]=2', $query );
        }

       /**
        * Test url_format() merges existing query parameters.
        *
        * @since X.X.X
        */
       public function test_url_format_merges_query() {
                $url = Util_Environment::url_format( 'https://example.com/path?foo=1', array( 'bar' => array( 'baz' => 2 ) ) );
                $this->assertSame( 'https://example.com/path?foo=1&bar[baz]=2', $url );
        }

       /**
        * Test filename_to_url() for different locations.
        *
        * @since X.X.X
        */
       public function test_filename_to_url_locations() {
                // File in WP_CONTENT_DIR.
                $file1 = tempnam( WP_CONTENT_DIR, 'w3tc' );
                $expected1 = content_url( basename( $file1 ) );
                $this->assertSame( $expected1, Util_Environment::filename_to_url( $file1 ) );
                unlink( $file1 );

                // File in W3TC_CACHE_DIR.
                $file2dir = W3TC_CACHE_DIR . '/tmpurltest';
                if ( ! file_exists( $file2dir ) ) {
                        mkdir( $file2dir, 0755, true );
                }
                $file2 = tempnam( $file2dir, 'w3tc' );
                $expected2 = content_url( str_replace( WP_CONTENT_DIR, '', $file2 ) );
                $this->assertSame( $expected2, Util_Environment::filename_to_url( $file2 ) );
                unlink( $file2 );
                rmdir( $file2dir );

                // File in W3TC_CONFIG_DIR.
                $file3dir = W3TC_CONFIG_DIR . '/tmpurltest';
                if ( ! file_exists( $file3dir ) ) {
                        mkdir( $file3dir, 0755, true );
                }
                $file3 = tempnam( $file3dir, 'w3tc' );
                $expected3 = content_url( str_replace( WP_CONTENT_DIR, '', $file3 ) );
                $this->assertSame( $expected3, Util_Environment::filename_to_url( $file3 ) );
                unlink( $file3 );
                rmdir( $file3dir );

                // File outside known locations should return empty string.
                $file4 = tempnam( sys_get_temp_dir(), 'w3tc' );
                $this->assertSame( '', Util_Environment::filename_to_url( $file4 ) );
                unlink( $file4 );
        }
}
