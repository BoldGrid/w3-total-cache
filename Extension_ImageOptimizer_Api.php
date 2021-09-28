<?php
/**
 * File: Extension_ImageOptimizer_Api.php
 *
 * @since X.X.X
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Extension_ImageOptimizer_Api
 *
 * @since X.X.X
 */
class Extension_ImageOptimizer_Api {
	/**
	 * API Base URL.
	 *
	 * @since X.X.X
	 * @access private
	 *
	 * @var string
	 */
	private $base_url = 'https://api2.w3-edge.com';

	/**
	 * W3TC Pro license key.
	 *
	 * @since X.X.X
	 * @access private
	 *
	 * @var string
	 */
	private $license_key;

	/**
	 * W3TC Pro licensed home URL.
	 *
	 * @since X.X.X
	 * @access private
	 *
	 * @var string
	 */
	private $home_url;

	/**
	 * W3TC Pro licensed product item name.
	 *
	 * @since X.X.X
	 * @access private
	 *
	 * @var string
	 */
	private $item_name;

	/**
	 * API endpoints.
	 *
	 * @since X.X.X
	 * @access private
	 *
	 * @var array
	 */
	private $endpoints = array(
		'convert'  => array(
			'method' => 'POST',
			'uri'    => '/image/convert',
		),
		'status'   => array(
			'method' => 'GET',
			'uri'    => '/job/status',
		),
		'download' => array(
			'method' => 'GET',
			'uri'    => '/image/download',
		),
	);

	/**
	 * Constructor.
	 *
	 * @since X.X.X
	 */
	public function __construct() {
		$config = Dispatcher::config();

		if ( Util_Environment::is_w3tc_pro( $config ) ) {
			$this->license_key = $config->get_string( 'plugin.license_key' );
			$this->home_url    = network_home_url();
			$this->item_name   = W3TC_PURCHASE_PRODUCT_NAME;
		} else {
			$this->home_url = md5( network_home_url() );
		}
	}

	/**
	 * Convert an image; submit a job request.
	 *
	 * @since X.X.X
	 *
	 * @param string $filepath Image file path.
	 * @param array  $options  Optional array of options.  Overrides settings.
	 * @return array
	 */
	public function convert( $filepath, array $options = array() ) {
		$config   = Dispatcher::config();
		$settings = $config->get_array( 'optimager' );
		$options  = array_merge(
			array(
				'optimize' => 'lossy' === $settings['compression'] ? '1' : '0',
			),
			$options
		);
		$boundary = wp_generate_password( 24 );
		$body     = '';

		$post_fields = array(
			'license_key' => $this->license_key,
			'home_url'    => $this->home_url,
			'item_name'   => $this->item_name,
			'optimize'    => $options['optimize'],
		);

		foreach ( $post_fields as $k => $v ) {
			$body .= '--' . $boundary . "\r\n";
			$body .= 'Content-Disposition: form-data; name="' . $k . '"' . "\r\n\r\n";
			$body .= $v . "\r\n";
		}

		$body .= '--' . $boundary . "\r\n";
		$body .= 'Content-Disposition: form-data; name="image"; filename="' . basename( $filepath ) . '"' . "\r\n\r\n";
		$body .= file_get_contents( $filepath ) . "\r\n" . '--' . $boundary . '--'; // phpcs:ignore WordPress.WP.AlternativeFunctions

		$response = wp_remote_request(
			$this->get_base_url() . $this->endpoints['convert']['uri'],
			array(
				'method'    => $this->endpoints['convert']['method'],
				'sslverify' => false,
				'timeout'   => 30,
				'headers'   => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
				),
				'body'      => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'error' => __( 'WP Error: ', 'w3-total-cache' ) . $response->get_error_message(),
			);
		}

		if ( 200 !== $response['response']['code'] ) {
			$result = array(
				'error' => __( 'Error: Received a non-200 response code: ', 'w3-total-cache' ) . $response['response']['code'],
			);

			$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( isset( $response_body['message'] ) ) {
				$result['message'] = esc_html( $response_body['message'] );
			}

			return $result;
		}

		// Convert response body to an array.
		$response = json_decode( wp_remote_retrieve_body( $response ), true );

		// Pass error message.
		if ( isset( $response['message'] ) ) {
			return array(
				'error' => esc_html( $response['message'] ),
			);
		}

		return $response;
	}

	/**
	 * Get job status.
	 *
	 * @param int    $job_id    Job id.
	 * @param string $signature Signature.
	 * @return array
	 */
	public function get_status( $job_id, $signature ) {
		$response = wp_remote_request(
			$this->get_base_url() . $this->endpoints['status']['uri'] . '/' . $job_id . '/' . $signature,
			array(
				'method'    => $this->endpoints['status']['method'],
				'sslverify' => false,
				'timeout'   => 10,
				'headers'   => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'error' => __( 'WP Error: ', 'w3-total-cache' ) . $response->get_error_message(),
			);
		}

		// Convert response body to an array.
		$response = json_decode( wp_remote_retrieve_body( $response ), true );

		// Pass error message.
		if ( isset( $response['error'] ) ) {
			return array(
				'error' => $response['error'],
			);
		}

		return $response;
	}

	/**
	 * Download a processed image.
	 *
	 * @param int    $job_id    Job id.
	 * @param string $signature Signature.
	 * @return array WP response array.
	 */
	public function download( $job_id, $signature ) {
		$response = wp_remote_request(
			$this->get_base_url() . $this->endpoints['download']['uri'] . '/' . $job_id . '/' . $signature,
			array(
				'method'    => $this->endpoints['download']['method'],
				'sslverify' => false,
				'timeout'   => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'error' => __( 'WP Error: ', 'w3-total-cache' ) . $response->get_error_message(),
			);
		}

		// Get the response body.
		$body = wp_remote_retrieve_body( $response );

		// Convert response body to an array.  A successful image results in a JSON decode false return.
		$json = json_decode( $body, true );

		// Pass error message.
		if ( isset( $json['error'] ) ) {
			return array(
				'error' => $json['error'],
			);
		}

		return $response;
	}

	/**
	 * Get base URL.
	 *
	 * @since X.X.X
	 * @access private
	 *
	 * @returns string
	 */
	private function get_base_url() {
		return defined( 'W3TC_API2_URL' ) && W3TC_API2_URL ?
			esc_url( W3TC_API2_URL, 'https', '' ) : $this->base_url;
	}
}
