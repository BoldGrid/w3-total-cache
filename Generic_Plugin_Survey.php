<?php
/**
 * File: Generic_Plugin_Survey.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Generic_Plugin_Survey
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 */
class Generic_Plugin_Survey {
	/**
	 * Config
	 *
	 * @var Config
	 */
	private $_config = null;

	/**
	 * API Base URL.
	 *
	 * @since X.X.X
	 *
	 * @var string
	 */
	private $base_url = 'https://api2.w3-edge.com';

	/**
	 * W3TC Pro license key.
	 *
	 * @since X.X.X
	 *
	 * @var string
	 */
	private $license_key = '';

	/**
	 * W3TC Pro licensed home URL.
	 *
	 * @since X.X.X
	 *
	 * @var string
	 */
	private $home_url;

	/**
	 * W3TC Pro licensed product item name.
	 *
	 * @since X.X.X
	 *
	 * @var string
	 */
	private $item_name = '';

	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();

		if ( Util_Environment::is_w3tc_pro( $this->_config ) ) {
			$this->license_key = $this->_config->get_string( 'plugin.license_key' );
			$this->home_url    = network_home_url();
			$this->item_name   = W3TC_PURCHASE_PRODUCT_NAME;
		} else {
			$this->home_url = network_home_url();
		}
	}

	/**
	 * Runs plugin
	 *
	 * @return void
	 */
	public function run() {
		add_action( 'w3tc_ajax_exit_survey_render', array( $this, 'w3tc_ajax_exit_survey_render' ) );
		add_action( 'w3tc_ajax_exit_survey_submit', array( $this, 'w3tc_ajax_exit_survey_submit' ) );
	}

	/**
	 * Get API base URL.
	 *
	 * @since X.X.X
	 *
	 * @access private
	 */
	private function get_base_url() {
		return defined( 'W3TC_API2_URL' ) && W3TC_API2_URL ? esc_url( W3TC_API2_URL, 'https', '' ) : $this->base_url;
	}

	/**
	 * Renders the exit survey lightbox content
	 *
	 * @return void
	 */
	public function w3tc_ajax_exit_survey_render() {
		if ( ! \user_can( \get_current_user_id(), 'manage_options' ) ) {
			return;
		}

		include W3TC_INC_DIR . '/lightbox/exit_survey.php';
	}

	/**
	 * Processes the exit survey submission and sends it to the API.
	 *
	 * @return void
	 */
	public function w3tc_ajax_exit_survey_submit() {
		if ( ! \user_can( \get_current_user_id(), 'manage_options' ) ) {
			return;
		}

		// Verify nonce.
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( Util_Request::get_string( '_wpnonce' ), 'w3tc' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce.' ) );
		}

		// Collect survey data.
		$uninstall_reason = sanitize_text_field( Util_Request::get_string( 'reason' ) );
		$other_reason     = sanitize_text_field( Util_Request::get_string( 'other' ) );

		// Prepare the data to send to the API.
		$data = array(
			'type'        => 'exit',
			'license_key' => $this->license_key,
			'home_url'    => $this->home_url,
			'item_name'   => $this->item_name,
			'reason'      => $uninstall_reason,
			'other'       => $other_reason,
		);

		if ( ( defined( 'W3TC_PRO' ) && W3TC_PRO ) || ( defined( 'W3TC_ENTERPRISE' ) && W3TC_ENTERPRISE ) ) {
			$data['pro_c'] = 1;
		}

		// Send the data to your API server using wp_remote_post.
		$response = wp_remote_post(
			$this->get_base_url() . '/surveys',
			array(
				'method'  => 'POST',
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $data ),
			)
		);

		// Check the API response.
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => 'Failed to submit data to the API.' ) );
		}

		// Handle API response.
		$response_body = wp_remote_retrieve_body( $response );
		$api_response  = json_decode( $response_body );

		if ( $api_response && isset( $api_response->status ) && 'Created' === $api_response->status ) {
			wp_send_json_success( array( 'message' => 'Thank you for your feedback!' ) );
		} else {
			wp_send_json_error( array( 'message' => 'API error: ' . $api_response->message ) );
		}
	}
}
