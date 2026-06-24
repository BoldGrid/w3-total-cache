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
	private $w3tc_license_key = '';

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
			$this->w3tc_license_key = $this->_config->get_string( 'plugin.license_key' );
			$this->item_name        = W3TC_PURCHASE_PRODUCT_NAME;
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
			\wp_send_json_error( array( 'message' => 'Insufficient permissions.' ), 403 );
		}

		// Verify nonce (per-action; legacy 'w3tc' accepted as back-compat).
		if ( ! Util_Nonce::verify_admin( Util_Nonce::ajax_action( 'exit_survey_render' ) ) ) {
			\wp_send_json_error( array( 'message' => 'Invalid nonce.' ), 403 );
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
			\wp_send_json_error( array( 'message' => 'Insufficient permissions.' ), 403 );
		}

		/**
		 * Verify nonce (per-action; legacy 'w3tc' accepted as back-compat).
		 * phpcs:ignore WordPress.Security.NonceVerification.Missing -- Util_Nonce::verify_admin() IS the nonce verifier.
		 */
		if ( ! isset( $_POST['_wpnonce'] ) || ! Util_Nonce::verify_admin( Util_Nonce::ajax_action( 'exit_survey_submit' ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked via Util_Nonce::verify_admin().
			\wp_send_json_error( array( 'message' => 'Invalid nonce.' ), 403 );
		}

		// Collect survey data.
		$uninstall_reason = sanitize_text_field( Util_Request::get_string( 'reason' ) );
		$email            = sanitize_email( Util_Request::get_string( 'email' ) );
		$other            = sanitize_text_field( Util_Request::get_string( 'other' ) );
		$remove_data      = sanitize_text_field( Util_Request::get_string( 'remove' ) );

		// Prepare the data to send to the API.
		$w3tc_data = array(
			'type'        => 'exit',
			'license_key' => $this->w3tc_license_key,
			'home_url'    => $this->home_url,
			'item_name'   => $this->item_name,
			'reason'      => $uninstall_reason,
		);

		// Add 'email' to $w3tc_data only if the $email is non-blank.
		if ( ! empty( $email ) ) {
			$w3tc_data['email'] = $email;
		}

		// Add 'other' to $w3tc_data only $other is non-blank.
		if ( ! empty( $other ) ) {
			$w3tc_data['other'] = $other;
		}

		if ( Util_Environment::is_pro_constant( $this->_config ) ) {
			$w3tc_data['pro_c'] = 1;
		}

		// Send the data to your API server using wp_remote_post.
		$response = wp_remote_post(
			Util_Environment::get_api_base_url() . '/surveys',
			array(
				'method'  => 'POST',
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $w3tc_data ),
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
	 * @since 2.8.7
	 *
	 * @return void
	 */
	public function w3tc_ajax_exit_survey_skip() {
		if ( ! \user_can( \get_current_user_id(), 'manage_options' ) ) {
			\wp_send_json_error( array( 'message' => 'Insufficient permissions.' ), 403 );
		}

		/**
		 * Verify nonce (per-action; legacy 'w3tc' accepted as back-compat).
		 * phpcs:ignore WordPress.Security.NonceVerification.Missing -- Util_Nonce::verify_admin() IS the nonce verifier.
		 */
		if ( ! isset( $_POST['_wpnonce'] ) || ! Util_Nonce::verify_admin( Util_Nonce::ajax_action( 'exit_survey_skip' ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked via Util_Nonce::verify_admin().
			\wp_send_json_error( array( 'message' => 'Invalid nonce.' ), 403 );
		}

		// Collect remove data flag.
		$remove_data = Util_Request::get_string( 'remove' );

		if ( 'yes' === $remove_data ) {
			update_option( 'w3tc_remove_data', true );
			wp_send_json_success( array( 'message' => 'Plugin data will be removed!' ) );
		}
	}
}
