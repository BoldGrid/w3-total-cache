<?php
/**
 * File: Cdnfsd_TotalCdn_Page.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Cdnfsd_TotalCdn_Page
 */
class Cdnfsd_TotalCdn_Page {
	/**
	 * Registers AJAX handlers.
	 *
	 * @return void
	 */
	public static function w3tc_ajax() {
		$instance = new self();

		add_action( 'w3tc_ajax_cdn_totalcdn_fsd_status_check', array( $instance, 'w3tc_ajax_cdn_totalcdn_fsd_status_check' ) );
	}

	/**
	 * Enqueue scripts for the CDN FSD metabox.
	 *
	 * @return void
	 */
	public static function admin_print_scripts_performance_page_w3tc_cdn() {
		$tests  = self::get_tests();

		wp_register_style(
			'w3tc_cdn_totalcdn_fsd_styles',
			plugins_url( 'Cdnfsd_TotalCdn_Page_View.css', W3TC_FILE ),
			array(),
			W3TC_VERSION
		);

		wp_enqueue_style( 'w3tc_cdn_totalcdn_fsd_styles' );

		wp_register_script(
			'w3tc_cdn_totalcdn_fsd',
			plugins_url( 'Cdnfsd_TotalCdn_Page_View.js', W3TC_FILE ),
			array( 'jquery' ),
			W3TC_VERSION,
			false
		);

		wp_localize_script(
			'w3tc_cdn_totalcdn_fsd',
			'w3tcCdnTotalCdnFsd',
			array(
				'ajaxAction' => 'cdn_totalcdn_fsd_status_check',
				'nonce'      => wp_create_nonce( 'w3tc' ),
				'tests'      => array_values( array_map( array( __CLASS__, 'prepare_test_for_js' ), $tests ) ),
				'button'     => array(
					'default' => esc_html__( 'Check Status', 'w3-total-cache' ),
					'testing' => esc_html__( 'Testing...', 'w3-total-cache' ),
				),
				'labels'     => array(
					'pass'     => esc_html__( 'Pass', 'w3-total-cache' ),
					'fail'     => esc_html__( 'Fail', 'w3-total-cache' ),
					'untested' => esc_html__( 'Not tested', 'w3-total-cache' ),
				),
				'errorMessage' => esc_html__( 'Unable to complete the status check. Please try again.', 'w3-total-cache' ),
			)
		);

		wp_enqueue_script( 'w3tc_cdn_totalcdn_fsd' );
	}

	/**
	 * Display the Total CDN FSD configuration metabox.
	 *
	 * @return void
	 */
	public static function w3tc_settings_box_cdnfsd() {
		$config              = Dispatcher::config();
		$tests               = self::get_tests();
		$is_totalcdn_enabled = self::is_total_cdn_enabled();

		include W3TC_DIR . '/Cdnfsd_TotalCdn_Page_View.php';
	}

	/**
	 * Determines if Total CDN FSD is enabled.
	 *
	 * @return bool
	 */
	public static function is_total_cdn_enabled() {
		$config = Dispatcher::config();

		return (
			$config->get_boolean( 'cdnfsd.enabled' ) &&
			'totalcdn' === $config->get_string( 'cdnfsd.engine' )
		);
	}

	/**
	 * Handles the Total CDN FSD status check AJAX request.
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_totalcdn_fsd_status_check() {
		$tests   = self::get_tests();
		$results = array();

		$possible_statuses = array( 'pass', 'fail', 'untested' );
		foreach ( $tests as $test ) {
			// @todo Replace placeholder status logic with real implementation.
			$status                 = $possible_statuses[ array_rand( $possible_statuses ) ];
			$results[ $test['id'] ] = $status;
		}

		$counts = array(
			'pass'     => 0,
			'fail'     => 0,
			'untested' => 0,
		);

		foreach ( $results as $status ) {
			if ( isset( $counts[ $status ] ) ) {
				$counts[ $status ]++;
			}
		}

		$notice_type    = 'info';
		$notice_message = '';

		if ( $counts['pass'] === count( $tests ) ) {
			$notice_type    = 'success';
			$notice_message = esc_html__( 'All tests passed successfully.', 'w3-total-cache' );
		} else {
			if ( $counts['fail'] > 0 ) {
				$notice_type = 'error';
			} elseif ( $counts['untested'] > 0 ) {
				$notice_type = 'warning';
			}

			$summary_parts = array(
				self::format_test_count_message( $counts['pass'], 'pass' ),
				self::format_test_count_message( $counts['fail'], 'fail' ),
				self::format_test_count_message( $counts['untested'], 'untested' ),
			);

			$notice_message = sprintf(
				esc_html__( '%1$s, %2$s, and %3$s.', 'w3-total-cache' ),
				$summary_parts[0],
				$summary_parts[1],
				$summary_parts[2]
			);
		}

		wp_send_json_success(
			array(
				'notices'	  => array(
					array(
						'type'	  => $notice_type,
						'message' => $notice_message,
					),
				),
				'test_results' => $results,
			)
		);
	}

	/**
	 * Formats a count summary message for notices.
	 *
	 * @param int    $count  Number of tests.
	 * @param string $status Status key.
	 *
	 * @return string
	 */
	protected static function format_test_count_message( $count, $status ) {
		$templates = array(
			'pass'     => _n( '%s test passed', '%s tests passed', $count, 'w3-total-cache' ),
			'fail'     => _n( '%s test failed', '%s tests failed', $count, 'w3-total-cache' ),
			'untested' => _n( '%s test not run', '%s tests not run', $count, 'w3-total-cache' ),
		);

		if ( ! isset( $templates[ $status ] ) ) {
			return '';
		}

		return sprintf(
			esc_html( $templates[ $status ] ),
			number_format_i18n( $count )
		);
	}

	/**
	 * Retrieves the configured Total CDN FSD status tests.
	 *
	 * @return array
	 */
	protected static function get_tests() {
		$tests = include W3TC_DIR . '/Cdnfsd_TotalCdn_Status_Tests.php';

		if ( ! is_array( $tests ) ) {
			return array();
		}

		foreach ( $tests as $index => $test ) {
			if ( empty( $test['filter'] ) ) {
				continue;
			}

			$tests[ $index ] = apply_filters( $test['filter'], $test );
		}

		return $tests;
	}

	/**
	 * Normalizes a test definition for localization.
	 *
	 * @param array $test Test data.
	 *
	 * @return array
	 */
	protected static function prepare_test_for_js( $test ) {
		return array(
			'id'    => isset( $test['id'] ) ? $test['id'] : '',
			'title' => isset( $test['title'] ) ? $test['title'] : '',
		);
	}
}