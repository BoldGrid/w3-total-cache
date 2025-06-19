<?php
/**
 * File: class-util-content-debug-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 */

declare( strict_types = 1 );

use W3TC\Util_Content;
use W3TC\Util_Debug;

/**
 * Class: Util_Content_Debug_Test
 */
class Util_Content_Debug_Test extends WP_UnitTestCase {
    /**
     * Test HTML detection helpers.
     */
    public function test_is_html_and_xml() {
        $html = "<!DOCTYPE html><html><body>Hi</body></html>";
        $xml  = "<?xml version='1.0'?><root></root>";
        $plain = "Hello";

        $this->assertTrue( Util_Content::is_html( $html ) );
        $this->assertFalse( Util_Content::is_html( $xml ) );
        $this->assertTrue( Util_Content::is_html_xml( $xml ) );
        $this->assertFalse( Util_Content::is_html_xml( $plain ) );
    }

    /**
     * Test escaping and http date formatting.
     */
    public function test_escape_and_http_date() {
        $comment = 'This -- should be escaped';
        $escaped = Util_Content::escape_comment( $comment );
        $this->assertStringNotContainsString( '--', $escaped );

        $time = 1609459200; // 2021-01-01 00:00:00 UTC
        $this->assertSame( 'Fri, 01 Jan 2021 00:00:00 GMT', Util_Content::http_date( $time ) );
    }

    /**
     * Test endpoint parsing utility.
     */
    public function test_endpoint_to_host_port() {
        $this->assertSame( array( '127.0.0.1', 80 ), Util_Content::endpoint_to_host_port( '127.0.0.1:80' ) );
        $this->assertSame( array( 'tls://127.0.0.1', 443 ), Util_Content::endpoint_to_host_port( 'tls://127.0.0.1:443' ) );
        $this->assertSame( array( 'unix:/tmp/socket', 0 ), Util_Content::endpoint_to_host_port( 'unix:/tmp/socket' ) );
    }

    /**
     * Test debug logging creates file in defined directory.
     */
    public function test_debug_log_creates_file() {
        if ( defined( 'W3TC_DEBUG_DIR' ) ) {
            $this->markTestSkipped( 'W3TC_DEBUG_DIR already defined' );
        }

        $dir = sys_get_temp_dir() . '/w3tc-debug';
        define( 'W3TC_DEBUG_DIR', $dir );
        if ( file_exists( $dir ) ) {
            \W3TC\Util_File::rmdir( $dir );
        }

        $result = Util_Debug::log( 'testmod', 'hello world' );
        $this->assertIsInt( $result );
        $files = glob( $dir . '/*/testmod.log' );
        $this->assertNotEmpty( $files );
        $log = file_get_contents( $files[0] );
        $this->assertStringContainsString( 'hello world', $log );

        \W3TC\Util_File::rmdir( $dir );
    }
}
