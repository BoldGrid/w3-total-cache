<?php
/**
 * File: Cdn_RackSpace_Api_Tokens.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdn_RackSpace_Api_Tokens
 *
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 */
class Cdn_RackSpace_Api_Tokens {
	/**
	 * Authenticates a user with the Rackspace API using the provided credentials.
	 *
	 * This method sends a request to the Rackspace API's authentication endpoint
	 * and retrieves an access token along with service descriptors for the user.
	 *
	 * @param string $user_name The Rackspace account username.
	 * @param string $api_key   The API key for the Rackspace account.
	 *
	 * @return array An associative array containing:
	 *               - 'access_token': The authentication token.
	 *               - 'services': Service descriptors grouped by region.
	 *
	 * @throws \Exception If the response does not include the expected access token or service catalog.
	 */
	public static function authenticate( $user_name, $api_key ) {
		$request_json = array(
			'auth' => array(
				'RAX-KSKEY:apiKeyCredentials' => array(
					'username' => $user_name,
					'apiKey'   => $api_key,
				),
			),
		);

		$result = wp_remote_post(
			'https://identity.api.rackspacecloud.com/v2.0/tokens',
			array(
				'headers' => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json',
				),
				// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
				// 'sslcertificates' => dirname( __FILE__ ) .
				// '/Cdn_RackSpace_Api_CaCert.pem',
				'body'    => wp_json_encode( $request_json ),
			)
		);

		$response = self::_decode_response( $result );
		if ( ! isset( $response['access'] ) ) {
			throw new \Exception( 'Unexpected authentication response: access token not found' );
		}

		$r = $response['access'];

		// fill service descriptors by region.

		if ( ! isset( $r['serviceCatalog'] ) ) {
			throw new \Exception( 'Unexpected authentication response: serviceCatalog token not found' );
		}

		$services = $r['serviceCatalog'];

		return array(
			'access_token' => $r['token']['id'],
			'services'     => $services,
		);
	}

	/**
	 * Retrieves Rackspace Cloud Files service information grouped by region.
	 *
	 * This method processes the provided service descriptors and organizes
	 * object-store and CDN service endpoints by their respective regions.
	 *
	 * @param array $services The array of service descriptors from Rackspace.
	 *
	 * @return array {
	 *     An associative array of regions with their service endpoints, including:
	 *
	 *     @type string $object-store.publicURL   Public URL for object storage.
	 *     @type string $object-store.internalURL Internal URL for object storage.
	 *     @type string $object-cdn.publicURL     Public URL for CDN services.
	 * }
	 */
	public static function cloudfiles_services_by_region( $services ) {
		$by_region = array();

		foreach ( $services as $s ) {
			if ( 'object-store' === $s['type'] ) {
				foreach ( $s['endpoints'] as $endpoint ) {
					$region = $endpoint['region'];
					if ( ! isset( $by_region[ $region ] ) ) {
						$by_region[ $region ] = array();
					}

					$by_region[ $region ]['object-store.publicURL']   = $endpoint['publicURL'];
					$by_region[ $region ]['object-store.internalURL'] = $endpoint['internalURL'];
				}
			} elseif ( 'rax:object-cdn' === $s['type'] ) {
				foreach ( $s['endpoints'] as $endpoint ) {
					$region = $endpoint['region'];
					if ( ! isset( $by_region[ $region ] ) ) {
						$by_region[ $region ] = array();
					}

					$by_region[ $region ]['object-cdn.publicURL'] = $endpoint['publicURL'];
				}
			}
		}

		$by_region = self::_add_region_names( $by_region );
		return $by_region;
	}

	/**
	 * Retrieves Rackspace CDN service information grouped by region.
	 *
	 * This method processes the provided service descriptors and organizes
	 * CDN endpoints by their respective regions.
	 *
	 * @param array $services The array of service descriptors from Rackspace.
	 *
	 * @return array {
	 *     An associative array of regions with their CDN endpoints, including:
	 *
	 *     @type string $cdn.publicURL Public URL for CDN services.
	 * }
	 */
	public static function cdn_services_by_region( $services ) {
		$by_region = array();

		foreach ( $services as $s ) {
			if ( 'rax:cdn' === $s['type'] ) {
				foreach ( $s['endpoints'] as $endpoint ) {
					$region = $endpoint['region'];
					if ( ! isset( $by_region[ $region ] ) ) {
						$by_region[ $region ] = array();
					}

					$by_region[ $region ]['cdn.publicURL'] = $endpoint['publicURL'];
				}
			}
		}

		$by_region = self::_add_region_names( $by_region );
		return $by_region;
	}

	/**
	 * Adds human-readable names to the regions based on predefined mappings.
	 *
	 * This method associates known region codes with their human-readable names,
	 * falling back to the original code if no match is found.
	 *
	 * @param array $by_region The associative array of regions with service data.
	 *
	 * @return array The updated array of regions, including a 'name' key with the region's display name.
	 */
	private static function _add_region_names( $by_region ) {
		// try to decode region names.
		$region_names = array(
			'ORD' => 'Chicago (ORD)',
			'DFW' => 'Dallas/Ft. Worth (DFW)',
			'HKG' => 'Hong Kong (HKG)',
			'LON' => 'London (LON)',
			'IAD' => 'Northern Virginia (IAD)',
			'SYD' => 'Sydney (SYD)',
		);

		$keys = array_keys( $by_region );
		foreach ( $keys as $region ) {
			if ( isset( $region_names[ $region ] ) ) {
				$by_region[ $region ]['name'] = $region_names[ $region ];
			} else {
				$by_region[ $region ]['name'] = $region;
			}
		}

		return $by_region;
	}

	/**
	 * Decodes and validates the API response from Rackspace.
	 *
	 * This method processes the response from a Rackspace API request, verifying
	 * that the response is valid and does not contain errors.
	 *
	 * @param array $result The API response returned by wp_remote_post().
	 *
	 * @return array The decoded JSON response as an associative array.
	 *
	 * @throws \Exception If the API request fails or the response contains errors.
	 */
	private static function _decode_response( $result ) {
		if ( is_wp_error( $result ) ) {
			throw new \Exception( 'Failed to reach API endpoint' );
		}

		$response_json = @json_decode( $result['body'], true );
		if ( is_null( $response_json ) ) {
			throw new \Exception(
				sprintf(
					// Translators: 1 Result body.
					\esc_html__( 'Failed to reach API endpoint, got unexpected response: %1$s', 'w3-total-cache' ),
					\wp_kses_post( $result['body'] )
				)
			);
		}

		if ( isset( $response_json['unauthorized']['message'] ) ) {
			throw new \Exception( \esc_html( $response_json['unauthorized']['message'] ) );
		}

		if ( ! in_array( (int) $result['response']['code'], array( 200, 201 ), true ) ) {
			throw new \Exception( \wp_kses_post( $result['body'] ) );
		}

		return $response_json;
	}
}
