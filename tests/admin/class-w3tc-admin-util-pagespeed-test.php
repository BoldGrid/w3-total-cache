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
				'score'        => 0.95,
				'flex_grow'    => 95,
				'displayValue' => '1.1 s',
				'status'       => 'pass',
			),
			array(
				'score'        => 0.70,
				'flex_grow'    => 70,
				'displayValue' => '1.2 s',
				'status'       => 'average',
			),
			array(
				'score'        => 0.30,
				'flex_grow'    => 30,
				'displayValue' => '2.1 s',
				'status'       => 'fail',
			),
		);

		foreach ( $test_config as $data ) {
			ob_start();
			\W3TC\Util_PageSpeed::print_barline( $data );
			$output = ob_get_clean();

			$this->assertStringContainsString( '<div class="w3tcps_barline"><div style="flex-grow: ' . $data['flex_grow'] . '"><span class="w3tcps_range w3tcps_' . $data['status'] . '">' . $data['displayValue'] . '</span></div></div>', $output );
		}
	}

	/**
	 * Test print barline with icon.
	 *
	 * @since X.X.X
	 */
	public function test_print_bar_combined_with_icon() {
		$test_config = array(
			array(
				'data'   => array(
					'desktop' => array(
						'first-contentful-paint' => array(
							'score'            => 0.43,
							'scoreDisplayMode' => 'numeric',
							'displayValue'     => '1.7 s',
						),
					),
					'mobile'  => array(
						'first-contentful-paint' => array(
							'score'            => 0.17,
							'scoreDisplayMode' => 'numeric',
							'displayValue'     => '4.3 s',
						),
					),
				),
				'metric' => 'first-contentful-paint',
				'name'   => 'First Contentful Paint',
			),
			array(
				'data'   => array(
					'desktop' => array(
						'largest-contentful-paint' => array(
							'score'            => 0.36,
							'scoreDisplayMode' => 'numeric',
							'displayValue'     => '2.9 s',
						),
					),
					'mobile'  => array(
						'largest-contentful-paint' => array(
							'score'            => 0.02,
							'scoreDisplayMode' => 'numeric',
							'displayValue'     => '8.0 s',
						),
					),
				),
				'metric' => 'largest-contentful-paint',
				'name'   => 'Largest Contentful Paint',
			),
			array(
				'data'   => array(
					'desktop' => array(
						'speed-index' => array(
							'score'            => 0.36,
							'scoreDisplayMode' => 'numeric',
							'displayValue'     => '2.9 s',
						),
					),
					'mobile'  => array(
						'speed-index' => array(
							'score'            => 0.02,
							'scoreDisplayMode' => 'numeric',
							'displayValue'     => '8.0 s',
						),
					),
				),
				'metric' => 'speed-index',
				'name'   => 'Speed Index',
			),
		);

		foreach ( $test_config as $data ) {
			ob_start();
			\W3TC\Util_PageSpeed::print_bar_combined_with_icon( $data['data'], $data['metric'], $data['name'], true );
			$output = ob_get_clean();

			$this->assertStringContainsString( '<h3 class="w3tcps_metric_title">' . $data['name'] . '</h3>', $output );
			$this->assertStringContainsString( '<span class="dashicons dashicons-desktop"></span>', $output );
			$this->assertStringContainsString( '<div style="flex-grow: ' . ( $data['data']['desktop'][ $data['metric'] ]['score'] * 100 ) . '">', $output );
			$this->assertStringContainsString( '<span class="w3tcps_range w3tcps_fail">' . $data['data']['desktop'][ $data['metric'] ]['displayValue'] . '</span>', $output );
			$this->assertStringContainsString( '<div style="flex-grow: ' . ( $data['data']['mobile'][ $data['metric'] ]['score'] * 100 ) . '">', $output );
			$this->assertStringContainsString( '<span class="w3tcps_range w3tcps_fail">' . $data['data']['mobile'][ $data['metric'] ]['displayValue'] . '</span>', $output );
		}
	}

	/**
	 * Test print barline with no icon.
	 *
	 * @since X.X.X
	 */
	public function test_print_bar_single_no_icon() {
		$test_config = array(
			array(
				'data'   => array(
					'first-contentful-paint' => array(
						'score'            => 0.43,
						'scoreDisplayMode' => 'numeric',
						'displayValue'     => '1.7 s',
					),
				),
				'metric' => 'first-contentful-paint',
				'name'   => 'First Contentful Paint',
			),
			array(
				'data'   => array(
					'first-contentful-paint' => array(
						'score'            => 0.17,
						'scoreDisplayMode' => 'numeric',
						'displayValue'     => '4.3 s',
					),
				),
				'metric' => 'first-contentful-paint',
				'name'   => 'First Contentful Paint',
			),
			array(
				'data'   => array(
					'largest-contentful-paint' => array(
						'score'            => 0.36,
						'scoreDisplayMode' => 'numeric',
						'displayValue'     => '2.9 s',
					),
				),
				'metric' => 'largest-contentful-paint',
				'name'   => 'Largest Contentful Paint',
			),
		);

		foreach ( $test_config as $data ) {
			ob_start();
			\W3TC\Util_PageSpeed::print_bar_single_no_icon( $data['data'], $data['metric'], $data['name'], true );
			$output = ob_get_clean();

			$this->assertStringContainsString( '<h3 class="w3tcps_metric_title">' . $data['name'] . '</h3>', $output );
			$this->assertStringContainsString( '<div style="flex-grow: ' . ( $data['data'][ $data['metric'] ]['score'] * 100 ) . '">', $output );
			$this->assertStringContainsString( '<span class="w3tcps_range w3tcps_fail">' . $data['data'][ $data['metric'] ]['displayValue'] . '</span>', $output );
		}
	}

	/**
	 * Test get breakdown background color.
	 *
	 * @since X.X.X
	 */
	public function test_get_breakdown_bg() {
		$test_config = array(
			array(
				'score'       => 'invalid',
				'displayMode' => 'invalid',
				'result'      => 'notice notice-info inline',
			),
			array(
				'score'       => 95,
				'displayMode' => 'metricSavings',
				'result'      => 'notice notice-success inline',
			),
			array(
				'score'       => 70,
				'displayMode' => 'metric',
				'result'      => 'notice notice-warning inline',
			),
			array(
				'score'       => 30,
				'displayMode' => 'metricSavings',
				'result'      => 'notice notice-error inline',
			),
		);

		foreach ( $test_config as $data ) {
			$this->assertEquals( $data['result'], \W3TC\Util_PageSpeed::get_breakdown_bg( $data['score'], $data['displayMode'] ) );
		}
	}

	/**
	 * Test get breakdown grade.
	 *
	 * @since X.X.X
	 */
	public function test_get_breakdown_grade() {
		$test_config = array(
			array(
				'score'       => 'invalid',
				'displayMode' => 'invalid',
				'result'      => 'w3tcps_blank',
			),
			array(
				'score'       => 95,
				'displayMode' => 'metricSavings',
				'result'      => 'w3tcps_pass',
			),
			array(
				'score'       => 70,
				'displayMode' => 'metric',
				'result'      => 'w3tcps_average',
			),
			array(
				'score'       => 30,
				'displayMode' => 'metricSavings',
				'result'      => 'w3tcps_fail',
			),
		);

		foreach ( $test_config as $data ) {
			$this->assertEquals( $data['result'], \W3TC\Util_PageSpeed::get_breakdown_grade( $data['score'], $data['displayMode'] ) );
		}
	}

	/**
	 * Test print final screenshot.
	 *
	 * @since X.X.X
	 */
	public function test_print_final_screenshot() {
		$test_config = array(
			array(
				'screenshots' => array(
					'final' => array(
						'title'      => 'Final Screenshot',
						'screenshot' => 'data:image/jpeg;base64,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',
					),
				),
			),
			array(
				'screenshots' => array(
					'final' => array(
						'title'      => 'Final Screenshot',
						'screenshot' => 'data:image/jpeg;base64,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',
					),
				),
			),
		);

		foreach ( $test_config as $data ) {
			ob_start();
			\W3TC\Util_PageSpeed::print_final_screenshot( $data );
			$output = ob_get_clean();

			$this->assertStringContainsString( '<img src="' . $data['screenshots']['final']['screenshot'] . '" alt="' . $data['screenshots']['final']['title'] . '"/>', $output );
		}
	}

	/**
	 * Test print final screenshots.
	 *
	 * @since X.X.X
	 */
	public function test_print_screenshots() {
		$test_config = array(
			'screenshots' => array(
				'other' => array(
					'screenshots' => array(
						array(
							'data' => 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEABALDA4MChAODQ4SERATGCgaGBYWGDEjJR0oOjM9PDkzODdASFxOQERXRTc4UG1RV19iZ2hnPk1xeXBkeFxlZ2MBERISGBUYLxoaL2NCOEJjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY//AABEIAVwB9AMBEQACEQEDEQH/xAGiAAABBQEBAQEBAQAAAAAAAAAAAQIDBAUGBwgJCgsQAAIBAwMCBAMFBQQEAAABfQECAwAEEQUSITFBBhNRYQcicRQygZGhCCNCscEVUtHwJDNicoIJChYXGBkaJSYnKCkqNDU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6g4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2drh4uPk5ebn6Onq8fLz9PX29/j5+gEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoLEQACAQIEBAMEBwUEBAABAncAAQIDEQQFITEGEkFRB2FxEyIygQgUQpGhscEJIzNS8BVictEKFiQ04SXxFxgZGiYnKCkqNTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqCg4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2dri4+Tl5ufo6ery8/T19vf4+fr/2gAMAwEAAhEDEQA/APQKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoA//9k='
						),
						array(
							'data' => 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEABALDA4MChAODQ4SERATGCgaGBYWGDEjJR0oOjM9PDkzODdASFxOQERXRTc4UG1RV19iZ2hnPk1xeXBkeFxlZ2MBERISGBUYLxoaL2NCOEJjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY//AABEIAVwB9AMBEQACEQEDEQH/xAGiAAABBQEBAQEBAQAAAAAAAAAAAQIDBAUGBwgJCgsQAAIBAwMCBAMFBQQEAAABfQECAwAEEQUSITFBBhNRYQcicRQygZGhCCNCscEVUtHwJDNicoIJChYXGBkaJSYnKCkqNDU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6g4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2drh4uPk5ebn6Onq8fLz9PX29/j5+gEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoLEQACAQIEBAMEBwUEBAABAncAAQIDEQQFITEGEkFRB2FxEyIygQgUQpGhscEJIzNS8BVictEKFiQ04SXxFxgZGiYnKCkqNTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqCg4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2dri4+Tl5ufo6ery8/T19vf4+fr/2gAMAwEAAhEDEQA/APQKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoA//9k='
						),
						array(
							'data' => 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEABALDA4MChAODQ4SERATGCgaGBYWGDEjJR0oOjM9PDkzODdASFxOQERXRTc4UG1RV19iZ2hnPk1xeXBkeFxlZ2MBERISGBUYLxoaL2NCOEJjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY//AABEIAVwB9AMBEQACEQEDEQH/xAGiAAABBQEBAQEBAQAAAAAAAAAAAQIDBAUGBwgJCgsQAAIBAwMCBAMFBQQEAAABfQECAwAEEQUSITFBBhNRYQcicRQygZGhCCNCscEVUtHwJDNicoIJChYXGBkaJSYnKCkqNDU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6g4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2drh4uPk5ebn6Onq8fLz9PX29/j5+gEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoLEQACAQIEBAMEBwUEBAABAncAAQIDEQQFITEGEkFRB2FxEyIygQgUQpGhscEJIzNS8BVictEKFiQ04SXxFxgZGiYnKCkqNTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqCg4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2dri4+Tl5ufo6ery8/T19vf4+fr/2gAMAwEAAhEDEQA/APQKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoAKACgAoA//9k='
						),
						array(
							'data' => 'data:image/jpeg;base64,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'
						),
					),
				),
			),
		);

		ob_start();
		\W3TC\Util_PageSpeed::print_screenshots( $test_config );
		$output = ob_get_clean();

		$this->assertStringContainsString( '<img src="' . esc_attr( $test_config['screenshots']['other']['screenshots'][0]['data'] ) . '" alt="' . esc_attr__( 'Other Screenshot', 'w3-total-cache' ) . '"/>', $output );
		$this->assertStringContainsString( '<img src="' . esc_attr( $test_config['screenshots']['other']['screenshots'][1]['data'] ) . '" alt="' . esc_attr__( 'Other Screenshot', 'w3-total-cache' ) . '"/>', $output );
		$this->assertStringContainsString( '<img src="' . esc_attr( $test_config['screenshots']['other']['screenshots'][2]['data'] ) . '" alt="' . esc_attr__( 'Other Screenshot', 'w3-total-cache' ) . '"/>', $output );
		$this->assertStringContainsString( '<img src="' . esc_attr( $test_config['screenshots']['other']['screenshots'][3]['data'] ) . '" alt="' . esc_attr__( 'Other Screenshot', 'w3-total-cache' ) . '"/>', $output );
	}

	/**
	 * Test get value recursively.
	 *
	 * @since X.X.X
	 */
	public function test_get_value_recursive() {
		$test_config = array(
			'captchaResult'    => 'CAPTCHA_NOT_NEEDED',
			'environment'      => array(
				'benchmarkIndex' => 416.5,
			),
			'lighthouseResult' => array(
				'audits'     => array(
					'first-contentful-paint' => array(
						'score' => 0.07,
					),
					'unminified-javascript'  => array(
						'details' => array(
							'items' => array(
								array(
									'wastedBytes' => 42943,
								),
								array(
									'url' => 'https://somedomain.com/test.js',
								),
							),
						),
					),
				),
				'categories' => array(
					'performance' => array(
						'score' => 0.61,
					),
				),
			),
		);

		$this->assertEquals( 'CAPTCHA_NOT_NEEDED', \W3TC\Util_PageSpeed::get_value_recursive( $test_config, array( 'captchaResult' ) ) );
		$this->assertEquals( 416.5, \W3TC\Util_PageSpeed::get_value_recursive( $test_config, array( 'environment', 'benchmarkIndex' ) ) );
		$this->assertEquals( 0.07, \W3TC\Util_PageSpeed::get_value_recursive( $test_config, array( 'lighthouseResult', 'audits', 'first-contentful-paint', 'score' ) ) );
		$this->assertEquals( array( array( 'wastedBytes' => 42943 ), array( 'url' => 'https://somedomain.com/test.js' ) ), \W3TC\Util_PageSpeed::get_value_recursive( $test_config, array( 'lighthouseResult', 'audits', 'unminified-javascript', 'details', 'items' ) ) );
		$this->assertEquals( 0.61, \W3TC\Util_PageSpeed::get_value_recursive( $test_config, array( 'lighthouseResult', 'categories', 'performance', 'score' ) ) );
	}

	/**
	 * Test get allowed tags.
	 *
	 * @since X.X.X
	 */
	public function test_get_allowed_tags() {
		$this->assertEquals(
			array(
				'div'   => array(
					'id'    => array(),
					'class' => array(),
				),
				'span'  => array(
					'id'      => array(),
					'class'   => array(),
					'title'   => array(),
					'gatitle' => array(),
					'copyurl' => array(),
				),
				'p'     => array(
					'id'    => array(),
					'class' => array(),
				),
				'table' => array(
					'id'    => array(),
					'class' => array(),
				),
				'tr'    => array(
					'id'    => array(),
					'class' => array(),
				),
				'td'    => array(
					'id'    => array(),
					'class' => array(),
				),
				'th'    => array(
					'id'    => array(),
					'class' => array(),
				),
				'b'     => array(
					'id'    => array(),
					'class' => array(),
				),
				'br'    => array(),
				'a'     => array(
					'id'     => array(),
					'class'  => array(),
					'href'   => array(),
					'target' => array(),
					'rel'    => array(),
					'title'  => array(),
				),
				'link'  => array(
					'id'    => array(),
					'class' => array(),
					'href'  => array(),
					'rel'   => array(),
					'as'    => array(),
					'type'  => array(),
				),
				'code'  => array(
					'id'    => array(),
					'class' => array(),
				),
				'img'   => array(
					'id'     => array(),
					'class'  => array(),
					'srcset' => array(),
					'src'    => array(),
					'alt'    => array(),
				),
				'ul'    => array(
					'id'    => array(),
					'class' => array(),
				),
				'ol'    => array(
					'id'    => array(),
					'class' => array(),
				),
				'li'    => array(
					'id'    => array(),
					'class' => array(),
				),
				'h3'    => array(
					'id'    => array(),
					'class' => array(),
				),
			),
			\W3TC\Util_PageSpeed::get_allowed_tags()
		);
	}

	/**
	 * Test get cache life.
	 *
	 * @since X.X.X
	 */
	public function test_get_cache_life() {
		$this->assertEquals( 3600, \W3TC\Util_PageSpeed::get_cache_life() );
	}

	/**
	 * Test seconds to string (seconds to human readable).
	 *
	 * @since X.X.X
	 */
	public function test_seconds_to_str() {
		$this->assertEquals( '30 seconds', \W3TC\Util_PageSpeed::seconds_to_str( 30 ) );
		$this->assertEquals( '1 minute', \W3TC\Util_PageSpeed::seconds_to_str( 60 ) );
		$this->assertEquals( '2 minutes, 30 seconds', \W3TC\Util_PageSpeed::seconds_to_str( 150 ) );
		$this->assertEquals( '1 hour', \W3TC\Util_PageSpeed::seconds_to_str( 3600 ) );
		$this->assertEquals( '2 hours, 45 minutes, 30 seconds', \W3TC\Util_PageSpeed::seconds_to_str( 9930 ) );
		$this->assertEquals( '1 day', \W3TC\Util_PageSpeed::seconds_to_str( 86400 ) );
		$this->assertEquals( '2 days, 2 hours, 55 minutes, 30 seconds', \W3TC\Util_PageSpeed::seconds_to_str( 183330 ) );
	}
}
