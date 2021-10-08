<?php
/**
 * File: Extension_ImageService_Api.php
 *
 * @since X.X.X
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Extension_ImageService_Api
 *
 * @since X.X.X
 */
class Extension_ImageService_Api {
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
		'usage'    => array(
			'method' => 'GET',
			'uri'    => '/image/usage',
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
		$settings = $config->get_array( 'imageservice' );
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

		// Convert response body to an array.
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		// Update usage.
		if ( isset( $response_body['usage_hourly'] ) ) {
			set_transient(
				'w3tc_imageservice_usage',
				array(
					'updated_at'    => time(),
					'usage_hourly'  => $response_body['usage_hourly'],
					'usage_monthly' => isset( $response_body['usage_monthly'] ) ? $response_body['usage_monthly'] : null,
					'limit_hourly'  => isset( $response_body['limit_hourly'] ) ? $response_body['limit_hourly'] : null,
					'limit_monthly' => isset( $response_body['limit_monthly'] ) ? $response_body['limit_monthly'] : null,
				),
				DAY_IN_SECONDS
			);
		}

		// Handle non-200 response codes.
		if ( 200 !== $response['response']['code'] ) {
			$result = array(
				'error' => esc_html__( 'Error: Received a non-200 response code: ', 'w3-total-cache' ) . $response['response']['code'],
			);

			if ( isset( $response_body['error']['id'] ) && 'exceeded-hourly' === $response_body['error']['id'] ) {
				$result['message'] = sprintf(
					// translators: 1: Hourly request limit.
					esc_html__( 'You reached your hourly limit of %1$d; try again later%2$s.', 'w3-total-cache' ),
					esc_attr( $response_body['limit_hourly'] ),
					isset( $response_body['licensed'] ) && $response_body['licensed'] ?
						'' : esc_html__( ' or upgrade to Pro for higher limits', 'w3-total-cache' )
				);
			} elseif ( isset( $response_body['error']['id'] ) && 'exceeded-monthly' === $response_body['error']['id'] ) {
				$result['message'] = sprintf(
					// translators: 1: Monthly request limit.
					esc_html__( 'You reached your monthly limit of %1$d; try again later or upgrade to Pro for unlimited.', 'w3-total-cache' ),
					esc_attr( $response_body['limit_monthly'] )
				);
			} elseif ( isset( $response_body['error']['id'] ) && 'invalid-output-mime' === $response_body['error']['id'] ) {
				$result['message'] = esc_html__( 'Invalid output image MIME type.', 'w3-total-cache' );
			} elseif ( isset( $response_body['error']['id'] ) && 'missing-image' === $response_body['error']['id'] ) {
				$result['message'] = esc_html__( 'An image file is required.', 'w3-total-cache' );
			} elseif ( isset( $response_body['error']['id'] ) && 'invalid-image' === $response_body['error']['id'] ) {
				$result['message'] = esc_html__( 'Valid image data is required.', 'w3-total-cache' );
			} elseif ( isset( $response_body['error']['id'] ) && 'invalid-input-mime' === $response_body['error']['id'] ) {
				$result['message'] = esc_html__( 'Invalid input image MIME type.', 'w3-total-cache' );
			} elseif ( isset( $response_body['error']['message'] ) ) {
				// Unknown error message id; forward the error message.
				$result['message'] = esc_html( $response_body['error']['message'] );
			}

			return $result;
		}

		return $response_body;
	}

	/**
	 * Get job status.
	 *
	 * @since X.X.X
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
	 * @since X.X.X
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
	 * Get usage statistics.
	 *
	 * @since X.X.X
	 *
	 * @return array
	 */
	public function get_usage() {
		$error_message  = __( 'Unknown', 'w3-total-cache' );
		$error_response = array(
			'usage_hourly'  => $error_message,
			'usage_monthly' => $error_message,
			'limit_hourly'  => $error_message,
			'limit_monthly' => $error_message,
		);

		$response = wp_remote_request(
			esc_url(
				$this->get_base_url() . $this->endpoints['usage']['uri'] .
					'/' . rawurlencode( $this->license_key ) .
					'/' . urlencode( $this->item_name ) . // phpcs:ignore
					'/' . rawurlencode( $this->home_url )
			),
			array(
				'method'    => $this->endpoints['usage']['method'],
				'sslverify' => false,
				'timeout'   => 10,
				'headers'   => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $error_response;
		}

		// Convert response body to an array.
		$response = json_decode( wp_remote_retrieve_body( $response ), true );

		// If usage is not obtained, then return error response.
		if ( empty( $response['usage_hourly'] ) ) {
			return $error_response;
		} else {
			// Update usage.
			set_transient(
				'w3tc_imageservice_usage',
				array(
					'updated_at'    => time(),
					'usage_hourly'  => $response['usage_hourly'],
					'usage_monthly' => isset( $response['usage_monthly'] ) ? $response['usage_monthly'] : null,
					'limit_hourly'  => isset( $response['limit_hourly'] ) ? $response['limit_hourly'] : null,
					'limit_monthly' => isset( $response['limit_monthly'] ) ? $response['limit_monthly'] : null,
				),
				DAY_IN_SECONDS
			);

			// Ensure that the monthly limit is represented correctly.
			$response['limit_monthly'] = $response['limit_monthly'] ? $response['limit_monthly'] : __( 'Unlimited', 'w3-total-cache' );

			return $response;
		}
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
