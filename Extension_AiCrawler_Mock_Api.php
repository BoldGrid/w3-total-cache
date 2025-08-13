<?php
/**
 * File: Extension_AiCrawler_Mock_Api.php
 *
 * Provides mock responses for InMotion Hosting Central API requests when the
 * real API is unavailable. The class hooks into the WordPress HTTP API and
 * returns canned data for specific endpoints used by the AiCrawler extension.
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Extension_AiCrawler_Mock_Api
 */
class Extension_AiCrawler_Mock_Api {
		/**
		 * Register the request interceptor.
		 *
		 * @return void
		 */
	public function run() {
			add_filter( 'pre_http_request', array( $this, 'intercept' ), 10, 3 );
	}

		/**
		 * Intercepts requests to the Central API and returns mock responses.
		 *
		 * @param false|array|\WP_Error $pre  The preemptive return value. Default false.
		 * @param array                 $args HTTP request arguments.
		 * @param string                $url  The request URL.
		 *
		 * @return false|array|\WP_Error Mocked response or the original pre value.
		 */
	public function intercept( $pre, $args, $url ) {
		if ( false === strpos( $url, IMH_CENTRAL_API_URL ) ) {
				return $pre;
		}

						$path     = wp_parse_url( $url, PHP_URL_PATH );
						$endpoint = trim( str_replace( 'central-crawler/', '', $path ), '/' );
						$method   = str_replace( '/', '_', $endpoint );

						$body_data = array();

		if ( ! empty( $args['body'] ) ) {
						$decoded = json_decode( $args['body'], true );
			if ( is_array( $decoded ) ) {
								$body_data = $decoded;
			}
		}

		if ( method_exists( $this, $method ) ) {
				return $this->$method( $args, $body_data );
		}

				return $this->not_found();
	}

	/**
	 * Mock handler for the /report endpoint.
	 *
	 * @param array $args      HTTP request arguments.
	 * @param array $body_data Decoded body data.
	 *
	 * @return array Mocked HTTP response.
	 */
	private function report( $args, $body_data ) {
		unset( $args, $body_data );

		$report_data = Extension_AiCrawler_Util::get_dummy_report_data();
		$data        = $report_data['all_good'];

		return array(
			'body'     => wp_json_encode( $data ),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
		);
	}

				/**
				 * Mock handler for the /convert endpoint.
				 *
				 * @param array $args      HTTP request arguments.
				 * @param array $body_data Decoded body data.
				 *
				 * @return array Mocked HTTP response.
				 */
	private function convert( $args, $body_data ) {
					$url_to_convert = isset( $body_data['url'] ) ? $body_data['url'] : 'https://example.com';
					$markdown       = "# Example Domain\n\nThis domain is for use in illustrative examples in documents.";

					$data = array(
						'success'          => true,
						'url'              => $url_to_convert,
						'markdown_content' => $markdown,
						'content_length'   => strlen( $markdown ),
						'output_format'    => 'markdown',
						'metadata'         => (object) array(),
					);

					return array(
						'body'     => wp_json_encode( $data ),
						'response' => array(
							'code'    => 200,
							'message' => 'OK',
						),
					);
	}

				/**
				 * Generates a 404 not found response.
				 *
				 * @return array Mocked HTTP 404 response.
				 */
	private function not_found() {
					$error = array(
						'success' => false,
						'error'   => array(
							'code'    => 'mock_not_implemented',
							'message' => 'No mock available for this endpoint.',
						),
					);

					return array(
						'body'     => wp_json_encode( $error ),
						'response' => array(
							'code'    => 404,
							'message' => 'Not Found',
						),
					);
	}
}
