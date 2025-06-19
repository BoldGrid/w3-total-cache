<?php
/**
 * File: class-util-mime-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 */

declare( strict_types = 1 );

use W3TC\Util_Mime;

/**
 * Class: Util_Mime_Test
 *
 * @since X.X.X
 */
class Util_Mime_Test extends WP_UnitTestCase {
       /**
        * Test get_mime_type() for known and unknown extensions.
        *
        * @since X.X.X
        */
       public function test_get_mime_type() {
                $css = tempnam( sys_get_temp_dir(), 'w3tc' ) . '.css';
                file_put_contents( $css, 'body{}' );
                $html = tempnam( sys_get_temp_dir(), 'w3tc' ) . '.html';
                file_put_contents( $html, '<html></html>' );
                $unknown = tempnam( sys_get_temp_dir(), 'w3tc' ) . '.foo';
                file_put_contents( $unknown, 'foo' );

                $this->assertSame( 'text/css', Util_Mime::get_mime_type( $css ) );
                $this->assertSame( 'text/html', Util_Mime::get_mime_type( $html ) );
                // Unknown extensions may vary by system; allow multiple expected values
                $expected_mime_types = [ 'text/plain', 'application/octet-stream' ];
                $this->assertContains( Util_Mime::get_mime_type( $unknown ), $expected_mime_types );

                unlink( $css );
                unlink( $html );
                unlink( $unknown );
        }

       /**
        * Test sections_to_mime_types_map() and mime_type_to_section().
        *
        * @since X.X.X
        */
       public function test_sections_and_mapping() {
                $map = Util_Mime::sections_to_mime_types_map();
                $this->assertArrayHasKey( 'cssjs', $map );
                $this->assertArrayHasKey( 'html', $map );
                $this->assertArrayHasKey( 'other', $map );

                $this->assertSame( 'cssjs', Util_Mime::mime_type_to_section( 'text/css' ) );
                $this->assertSame( 'html', Util_Mime::mime_type_to_section( 'text/html' ) );
                $this->assertNull( Util_Mime::mime_type_to_section( 'application/unknown' ) );
        }
}
