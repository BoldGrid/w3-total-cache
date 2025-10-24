<?php
/**
 * File: Generic_Plugin_Survey.php
 *
 * @since 2.8.3
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Generic_Plugin_Survey
 *
 * @since 2.8.3
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 */
class Generic_Plugin_Survey {
	/**
	 * Config
	 *
	 * @since 2.8.3
	 *
	 * @var Config
	 */
	private $_config;

	/**
	 * W3TC Pro license key.
	 *
	 * @since 2.8.3
	 *
	 * @var string
	 */
	private $license_key = '';

	/**
	 * W3TC Pro licensed home URL.
	 *
	 * @since 2.8.3
	 *
	 * @var string
	 */
	private $home_url;

	/**
	 * W3TC Pro licensed product item name.
	 *
	 * @since 2.8.3
	 *
	 * @var string
	 */
	private $item_name = '';

	/**
	 * Constructor
	 *
	 * @since 2.8.3
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();

		if ( Util_Environment::is_w3tc_pro( $this->_config ) ) {
			$this->license_key = $this->_config->get_string( 'plugin.license_key' );
			$this->item_name   = W3TC_PURCHASE_PRODUCT_NAME;
		}

		$this->home_url = network_home_url();
	}

	/**
	 * Runs plugin
	 *
	 * @since 2.8.3
	 *
	 * @return void
	 */
	public function run() {
		add_action( 'w3tc_ajax_exit_survey_render', array( $this, 'w3tc_ajax_exit_survey_render' ) );
		add_action( 'w3tc_ajax_exit_survey_submit', array( $this, 'w3tc_ajax_exit_survey_submit' ) );
		add_action( 'w3tc_ajax_exit_survey_skip', array( $this, 'w3tc_ajax_exit_survey_skip' ) );
	}

	/**
	 * Renders the exit survey lightbox content
	 *
	 * @since 2.8.3
	 *
	 * @return void
	 */
	public function w3tc_ajax_exit_survey_render() {
		if ( ! \user_can( \get_current_user_id(), 'manage_options' ) ) {
			return;
		}

		// Verify nonce.
		if ( ! wp_verify_nonce( Util_Request::get_string( '_wpnonce' ), 'w3tc' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce.' ) );
		}

		include W3TC_INC_DIR . '/lightbox/exit_survey.php';
	}

	/**
	 * Processes the exit survey submission and sends it to the API.
	 *
	 * @since 2.8.3
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
		$email            = sanitize_email( Util_Request::get_string( 'email' ) );
		$other            = sanitize_text_field( Util_Request::get_string( 'other' ) );
		$remove_data      = sanitize_text_field( Util_Request::get_string( 'remove' ) );

		// Prepare the data to send to the API.
		$data = array(
			'type'        => 'exit',
			'license_key' => $this->license_key,
			'home_url'    => $this->home_url,
			'item_name'   => $this->item_name,
			'reason'      => $uninstall_reason,
		);

		// Add 'email' to $data only if the $email is non-blank.
		if ( ! empty( $email ) ) {
			$data['email'] = $email;
		}

		// Add 'other' to $data only $other is non-blank.
		if ( ! empty( $other ) ) {
			$data['other'] = $other;
		}

		if ( Util_Environment::is_pro_constant( $this->_config ) ) {
			$data['pro_c'] = 1;
		}

		// Send the data to your API server using wp_remote_post.
		$response = wp_remote_post(
			Util_Environment::get_api_base_url() . '/surveys',
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

		if ( $api_response && 201 === wp_remote_retrieve_response_code( $response ) ) {
			if ( 'yes' === $remove_data ) {
				update_option( 'w3tc_remove_data', true );
			}

			wp_send_json_success( array( 'message' => 'Thank you for your feedback!' ) );
		} else {
			wp_send_json_error( array( 'message' => 'API error: ' . $api_response->message ) );
		}
	}

	/**
	 * Skips the exit survey and processes removing plugin data.
	 *
	 * @since 2.8.8
	 *
	 * @return void
	 */
	public function w3tc_ajax_exit_survey_skip() {
		if ( ! \user_can( \get_current_user_id(), 'manage_options' ) ) {
			return;
		}

		// Verify nonce.
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( Util_Request::get_string( '_wpnonce' ), 'w3tc' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce.' ) );
		}

		// Collect remove data flag.
		$remove_data = Util_Request::get_string( 'remove' );

		if ( 'yes' === $remove_data ) {
			update_option( 'w3tc_remove_data', true );
			wp_send_json_success( array( 'message' => 'Plugin data will be removed!' ) );
		}
	}
}
