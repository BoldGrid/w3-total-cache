<?php
/**
 * File: class-util-environment-cron-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 */

declare( strict_types = 1 );

use W3TC\Util_Environment;

/**
 * Class: Util_Environment_Cron_Test
 *
 * @since X.X.X
 */
class Util_Environment_Cron_Test extends WP_UnitTestCase {
       /**
        * Test get_cron_schedule_time() matches expected timestamp.
        *
        * @since X.X.X
        */
       public function test_get_cron_schedule_time() {
                $now = new DateTime( 'now', wp_timezone() );
                $expected = clone $now;
                $expected->setTime( 1, 0 );
                if ( $expected <= $now ) {
                        $expected->modify( '+1 day' );
                }
                $expected_ts = $expected->setTimezone( new DateTimeZone( 'UTC' ) )->getTimestamp();
                $this->assertSame( $expected_ts, Util_Environment::get_cron_schedule_time( 60 ) );
        }

       /**
        * Test is_wpcron_working() success and failure via HTTP mocks.
        *
        * @since X.X.X
        */
       public function test_is_wpcron_working() {
                // Successful response mock.
                $callback = function() {
                        return array(
                                'headers'  => array(),
                                'body'     => '',
                                'response' => array( 'code' => 200, 'message' => 'OK' ),
                        );
                };
                add_filter( 'pre_http_request', $callback, 10, 3 );
                $result = Util_Environment::is_wpcron_working();
                $this->assertIsBool( $result );
                remove_filter( 'pre_http_request', $callback, 10, 3 );

                // Error response mock.
                $callback_error = function() {
                        return new WP_Error( 'fail', 'Error' );
                };
                add_filter( 'pre_http_request', $callback_error, 10, 3 );
                $this->assertFalse( Util_Environment::is_wpcron_working() );
                remove_filter( 'pre_http_request', $callback_error, 10, 3 );
        }
}
