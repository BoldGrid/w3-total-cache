<?php
/**
 * File: class-util-request-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 */

declare( strict_types = 1 );

use W3TC\Util_Request;

/**
 * Class: Util_Request_Test
 */
class Util_Request_Test extends WP_UnitTestCase {
        protected function setUp(): void {
                parent::setUp();
                $_GET  = array();
                $_POST = array();
        }

        /**
         * Test retrieving different types from request.
         */
        public function test_get_various_types() {
                $_GET['str']     = ' value ';
                $_POST['int']    = '5';
                $_POST['double'] = '3.14';
                $_GET['bool']    = '1';
                $_GET['array']   = "one,two";

                $this->assertSame( 'value', Util_Request::get_string( 'str' ) );
                $this->assertSame( 5, Util_Request::get_integer( 'int' ) );
                $this->assertSame( 3.14, Util_Request::get_double( 'double' ) );
                $this->assertTrue( Util_Request::get_boolean( 'bool' ) );
                $this->assertSame( array( 'one', 'two' ), Util_Request::get_array( 'array' ) );
        }

        /**
         * Test get_as_array() for prefixed parameters.
         */
        public function test_get_as_array() {
                $_GET['pre_one'] = '1';
                $_GET['pre_two'] = '2';
                $expected = array( '_one' => '1', '_two' => '2' );
                $this->assertSame( $expected, Util_Request::get_as_array( 'pre' ) );
        }
}
