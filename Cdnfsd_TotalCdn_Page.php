<?php
/**
 * File: Cdnfsd_TotalCdn_Page.php
 *
 * @since X.X.X
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Cdnfsd_TotalCdn_Page
 *
 * @since X.X.X
 */
class Cdnfsd_TotalCdn_Page {
	/**
	 * Registers AJAX handlers.
	 *
	 * @since X.X.X
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
	 * @since X.X.X
	 *
	 * @return void
	 */
	public static function admin_print_scripts_performance_page_w3tc_cdn() {
		$tests = self::get_tests();

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
				'ajaxAction'   => 'cdn_totalcdn_fsd_status_check',
				'nonce'        => wp_create_nonce( 'w3tc' ),
				'tests'        => array_values( array_map( array( __CLASS__, 'prepare_test_for_js' ), $tests ) ),
				'button'       => array(
					'default' => esc_html__( 'Check Status', 'w3-total-cache' ),
					'testing' => esc_html__( 'Testing...', 'w3-total-cache' ),
				),
				'labels'       => array(
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
	 * @since X.X.X
	 *
	 * @return void
	 */
	public static function w3tc_settings_box_cdnfsd() {
		$config              = Dispatcher::config();
		$tests               = self::get_tests();
		$is_totalcdn_enabled = self::is_total_cdnfsd_enabled();

		include W3TC_DIR . '/Cdnfsd_TotalCdn_Page_View.php';
	}

	/**
	 * Determines if Total CDN FSD is enabled.
	 *
	 * @since X.X.X
	 *
	 * @return bool
	 */
	public static function is_total_cdnfsd_enabled() {
		$config       = Dispatcher::config();
		$config_state = Dispatcher::config_state();

		$account_api_key = $config->get_string( 'cdn.totalcdn.account_api_key' );
		$status          = $config_state->get( 'cdn.totalcdn.status', 'inactive.no_key' );

		return (
			$config->get_boolean( 'cdnfsd.enabled' ) &&
			'totalcdn' === $config->get_string( 'cdnfsd.engine' ) &&
			! empty( $account_api_key ) &&
			true === strpos( $status, 'active' )
		);
	}

	/**
	 * Handles the Total CDN FSD status check AJAX request.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function w3tc_ajax_cdn_totalcdn_fsd_status_check() {
		$tests   = self::get_tests();
		$results = array();
		$errors  = array();

		foreach ( $tests as $test ) {
			$test_result            = self::execute_test( $test );
			$results[ $test['id'] ] = $test_result['status'];

			if ( 'fail' === $test_result['status'] ) {
				$errors[] = self::format_test_error_message( $test, $test_result['message'] );
				break;
			}
		}

		$notices = array();
		if ( ! empty( $errors ) ) {
			foreach ( $errors as $error ) {
				$notices[] = array(
					'type'    => 'error',
					'message' => $error,
				);
			}
		} elseif (
			! empty( $results )
			&& count( array_unique( $results ) ) === 1
			&& 'untested' === reset( $results )
		) {
			$notices[] = array(
				'type'    => 'warning',
				'message' => __( 'No tests were run.', 'w3-total-cache' ),
			);
		}

		wp_send_json_success(
			array(
				'notices'      => $notices,
				'test_results' => $results,
			)
		);
	}

	/**
	 * Executes a Total CDN FSD status test and normalizes the result.
	 *
	 * @since X.X.X
	 *
	 * @param array $test Test configuration.
	 *
	 * @return array{
	 *     status: string,
	 *     message: string
	 * }
	 */
	protected static function execute_test( $test ) {
		$default_status = 'untested';
		$status         = '';
		$message        = '';

		$result = apply_filters( $test['filter'], $default_status, $test );

		if ( is_wp_error( $result ) ) {
			$status  = 'fail';
			$message = $result->get_error_message();
		} elseif ( is_array( $result ) ) {
			if ( isset( $result['status'] ) ) {
				$status = $result['status'];
			}

			if ( isset( $result['message'] ) ) {
				$message = $result['message'];
			}
		} else {
			$status = $default_status;
		}

		return array(
			'status'  => $status,
			'message' => $message,
		);
	}

	/**
	 * Generates the display message for a failed test.
	 *
	 * @since X.X.X
	 *
	 * @param array  $test    Test definition.
	 * @param string $message Failure message returned by the test.
	 *
	 * @return string
	 */
	protected static function format_test_error_message( $test, $message ) {
		$title = $test['title'];

		if ( '' !== $message ) {
			$message = wp_strip_all_tags( $message );

			return sprintf(
				/* translators: 1: Total CDN test title. 2: Failure reason. */
				esc_html__( '%1$s: %2$s', 'w3-total-cache' ),
				esc_html( $title ),
				esc_html( $message )
			);
		}

		return sprintf(
			/* translators: 1: Total CDN test title. */
			esc_html__( '%s failed.', 'w3-total-cache' ),
			esc_html( $title )
		);
	}

	/**
	 * Retrieves the configured Total CDN FSD status tests.
	 *
	 * @since X.X.X
	 *
	 * @return array
	 */
	protected static function get_tests() {
		$tests = include W3TC_DIR . '/Cdnfsd_TotalCdn_Status_Tests.php';

		return $tests;
	}

	/**
	 * Normalizes a test definition for localization.
	 *
	 * @since X.X.X
	 *
	 * @param array $test Test data.
	 *
	 * @return array
	 */
	protected static function prepare_test_for_js( $test ) {
		return array(
			'id'    => $test['id'],
			'title' => $test['title'],
		);
	}
}
