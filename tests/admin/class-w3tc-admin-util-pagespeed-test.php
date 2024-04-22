<?php
/**
 * File: class-w3tc-admin-util-pagespeed-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @author     BoldGrid <development@boldgrid.com>
 * @since      2.3.1
 * @link       https://www.boldgrid.com/w3-total-cache/
 */

declare( strict_types = 1 );

/**
 * Class: W3tc_Admin_Util_File_Test
 *
 * @since X.X.X
 */
class W3tc_Admin_Util_PageSpeed_Test extends WP_UnitTestCase {
	/**
	 * Test get gauge angle.
	 *
	 * @since X.X.X
	 */
	public function test_get_gauge_angle() {
        $this->assertEquals( 171, round( \W3TC\Util_PageSpeed::get_gauge_angle( 95 ) ) );
        $this->assertEquals( 126, round( \W3TC\Util_PageSpeed::get_gauge_angle( 70 ) ) );
        $this->assertEquals( 54, round( \W3TC\Util_PageSpeed::get_gauge_angle( 30 ) ) );
        $this->assertEquals( 0, round( \W3TC\Util_PageSpeed::get_gauge_angle( null ) ) );
    }

	/**
	 * Test get gauge color.
	 *
	 * @since X.X.X
	 */
    public function test_get_gauge_color() {
        $this->assertEquals( '#0c6', \W3TC\Util_PageSpeed::get_gauge_color( 95 ) );
        $this->assertEquals( '#fa3', \W3TC\Util_PageSpeed::get_gauge_color( 70 ) );
        $this->assertEquals( '#f33', \W3TC\Util_PageSpeed::get_gauge_color( 30 ) );
        $this->assertEquals( '#fff', \W3TC\Util_PageSpeed::get_gauge_color( null ) );
    }

	/**
	 * Test print gauge.
	 *
	 * @since X.X.X
	 */
	public function test_print_gauge() {
		$test_config = array(
			array(
				'score' => 95,
				'icon'  => 'desktop',
				'angle' => 171,
				'color' => '#0c6',
			),
			array(
				'score' => 70,
				'icon'  => 'smartphone',
				'angle' => 126,
				'color' => '#fa3',
			),
			array(
				'score' => 30,
				'icon'  => 'desktop',
				'angle' => 54,
				'color' => '#f33',
			),
		);
	
		foreach ( $test_config as $data ) {
			ob_start();
			\W3TC\Util_PageSpeed::print_gauge( $data, $data['icon'] );
			$output = ob_get_clean();
	
			$this->assertStringContainsString( '<div class="gauge" style="width: 120px; --rotation:' . $data['angle'] . 'deg; --color:' . $data['color'] . '; --background:#888;">', $output );
			$this->assertStringContainsString( '<span class="dashicons dashicons-' . $data['icon'] . '">', $output );
			$this->assertStringContainsString( (string) $data['score'], $output );
		}
	}

	/**
	 * Test print barline.
	 *
	 * @since X.X.X
	 */
	public function test_print_barline() {
		$test_config = array(
			array(
				'score'         => 0.95,
				'flex_grow'     => 95,
				'displayValue'  => '1.1 s',
				'status'        => 'pass',
			),
			array(
				'score'         => 0.70,
				'flex_grow'     => 70,
				'displayValue'  => '1.2 s',
				'status'        => 'average',
			),
			array(
				'score'         => 0.30,
				'flex_grow'     => 30,
				'displayValue'  => '2.1 s',
				'status'        => 'fail',
			),
		);
	
		foreach ( $test_config as $data ) {
			ob_start();
			\W3TC\Util_PageSpeed::print_barline( $data );
			$output = ob_get_clean();
	
			$this->assertStringContainsString( '<div class="w3tcps_barline"><div style="flex-grow: ' . $data['flex_grow'] . '"><span class="w3tcps_range w3tcps_' . $data['status'] . '">' . $data['displayValue'] . '</span></div></div>', $output );
		}
	}
}
