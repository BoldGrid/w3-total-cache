<?php
/**
 * File: CdnEngine_Azure_MI_Utility.php
 *
 * Microsoft Azure Managed Identities are available only for services running on Azure when a "system assigned" identity is enabled.
 *
 * A system assigned managed identity is restricted to one per resource and is tied to the lifecycle of a resource.
 * You can grant permissions to the managed identity by using Azure role-based access control (Azure RBAC).
 * The managed identity is authenticated with Microsoft Entra ID, so you don’t have to store any credentials in code.
 *
 * @package W3TC
 * @since   2.7.7
 */

namespace W3TC;

/**
 * Class: CdnEngine_Azure_MI_Utility
 *
 * This class defines utility functions for Azure blob storage access using Managed Identity.
 *
 * @since   2.7.7
 * @author  Zubair <zmohammed@microsoft.com>
 * @author  BoldGrid <development@boldgrid.com>
 */
class CdnEngine_Azure_MI_Utility {
	/**
	 * Entra API version.
	 *
	 * @since 2.7.7
	 *
	 * @var string
	 */
	const ENTRA_API_VERSION  = '2019-08-01';

	/**
	 * Entra resource URI.
	 *
	 * @since 2.7.7
	 *
	 * @var string
	 */
	const ENTRA_RESOURCE_URI = 'https://storage.azure.com';

	/**
	 * Blob API version.
	 *
	 * @since 2.7.7
	 *
	 * @var string
	 */
	const BLOB_API_VERSION   = '2020-10-02';

	/**
	 * Retrieves an access token from the Managed Identity endpoint.
	 *
	 * @since 2.7.7
	 *
	 * @param string $entra_client_id Entra ID.
	 * @return string $access_token
	 * @throws \RuntimeException Runtine Exception.
	 */
	public static function get_access_token( string $entra_client_id ): string {
		// Get environment variables.
		$identity_header   = \getenv( 'IDENTITY_HEADER' );
		$identity_endpoint = \getenv( 'IDENTITY_ENDPOINT' );

		 // Validate variables.
		if ( empty( $identity_endpoint ) || empty( $identity_header ) || empty( $entra_client_id ) ) {
			throw new \RuntimeException( 'Error: get_access_token - missing required environment variables.' );
		}

		// Construct URL for cURL request.
		$url = $identity_endpoint . '?' . http_build_query(
			array(
				'api-version' => self::ENTRA_API_VERSION,
				'resource'    => self::ENTRA_RESOURCE_URI,
				'client_id'   => $entra_client_id,
			)
		);

		// Initialize and execute cURL request.
		$ch = \curl_init();

		\curl_setopt( $ch, CURLOPT_URL, $url );
		\curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'GET' );
		\curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'X-IDENTITY-HEADER: ' . $identity_header ) );
		\curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );
		\curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

		$response = \curl_exec( $ch );

		if ( \curl_errno( $ch ) ) {
			$error = \curl_error( $ch );
			\curl_close( $ch );
			throw new \RuntimeException( 'Error: get_access_token - cURL request failed: ' . \esc_html( $error ) );
		}

		$http_code = \curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		\curl_close( $ch );

		if ( 200 !== $http_code ) {
			throw new \RuntimeException( 'Error: get_access_token - HTTP request failed with status code :' . \esc_html( $http_code ) );
		}
		if ( empty( $response ) ) {
			throw new \RuntimeException( 'Error: get_access_token - invalid response data: ' . \esc_html( $response ) );
		}

		// Parse JSON response and extract access_token.
		$json_response = \json_decode( $response, true );

		if ( \json_last_error() !== JSON_ERROR_NONE ) {
			throw new \RuntimeException( 'Error: get_access_token - failed to parse the JSON response: ' . \esc_html( \json_last_error_msg() ) );
		}

		if ( empty( $json_response['access_token'] ) ) {
			throw new \RuntimeException( 'Error: get_access_token - no token found in response data: ' . \esc_html( $response ) );
		}

		return $json_response['access_token'];
	}

	/**
	 * Get Azure Blob Storage blob properties.
	 *
	 * @since 2.7.7
	 *
	 * @param string $entra_client_id Entra ID.
	 * @param string $storage_account Storage account name.
	 * @param string $container_id    Container ID.
	 * @param string $blob             Blob ID.
	 * @return array
	 * @throws \RuntimeException Runtine Exception.
	 */
	public static function get_blob_properties( string $entra_client_id, string $storage_account, string $container_id, $blob ): array {
		$ch = \curl_init();

		\curl_setopt( $ch, CURLOPT_URL, 'https://' . $storage_account . '.blob.core.windows.net/' . $container_id . '/' . $blob );
		\curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		\curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );
		\curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				'Authorization: Bearer ' . self::get_access_token( $entra_client_id ),
				'x-ms-version: ' . self::BLOB_API_VERSION,
				'x-ms-date: ' . \gmdate( 'D, d M Y H:i:s T', time() ),
			)
		);
		\curl_setopt( $ch, CURLOPT_HEADER, true );
		\curl_setopt( $ch, CURLOPT_NOBODY, true );

		$response = \curl_exec( $ch );

		if ( \curl_errno( $ch ) ) {
			$error = \curl_error( $ch );
			\curl_close( $ch );
			throw new \RuntimeException( 'Error: get_blob_properties - cURL request failed: ' . \esc_html( $error ) );
		}

		$http_code = \curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		\curl_close( $ch );

		if ( 200 !== $http_code ) {
			throw new \RuntimeException( 'Error: get_blob_properties - HTTP request failed with status code: ' . \esc_html( $http_code ) );
		}

		if ( empty( $response ) ) {
			throw new \RuntimeException( 'Error: get_blob_properties - invalid response data: ' . \esc_html( $response ) );
		}

		return self::parse_header( $response );
	}

	/**
	 * Create block blob.
	 *
	 * @since 2.7.7
	 *
	 * @param string $entra_client_id Entra ID.
	 * @param string $storage_account Storage account name.
	 * @param string $container_id    Container ID.
	 * @param string $blob            Blob ID.
	 * @param mixed  $contents        Contents.
	 * @param string $content_type    Content type.
	 * @param string $content_md5     Content MD5 hash.
	 * @param string $cache_control   Cache control header value.
	 * @return array
	 * @throws \RuntimeException Runtine Exception.
	 */
	public static function create_block_blob(
		string $entra_client_id,
		string $storage_account,
		string $container_id,
		string $blob,
		$contents,
		string $content_type = null,
		string $content_md5 = null,
		string $cache_control = null
	): array {
		$headers = array(
			'Authorization: Bearer ' . self::get_access_token( $entra_client_id ),
			'x-ms-version: ' . self::BLOB_API_VERSION,
			'x-ms-date: ' . \gmdate( 'D, d M Y H:i:s T', \time() ),
			'x-ms-blob-type: BlockBlob',
		);

		if ( $content_type ) {
			$headers[] = 'x-ms-blob-content-type: ' . $content_type;
		}
		if ( $content_md5 ) {
			$headers[] = 'x-ms-blob-content-md5: ' . $content_md5;
		}
		if ( $cache_control ) {
			$headers[] = 'x-ms-blob-cache-control: ' . $cache_control;
		}

		$ch = \curl_init();

		\curl_setopt( $ch, CURLOPT_URL, 'https://' . $storage_account . '.blob.core.windows.net/' . $container_id . '/' . $blob );
		\curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		\curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'PUT' );
		\curl_setopt( $ch, CURLOPT_POSTFIELDS, $contents );
		\curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
		\curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );
		\curl_setopt( $ch, CURLOPT_HEADER, true );

		$response = \curl_exec( $ch );

		if ( \curl_errno( $ch ) ) {
			$error = \curl_error( $ch );
			\curl_close( $ch );
			throw new \RuntimeException( 'Error: create_block_blob - cURL request failed: ' . \esc_html( $error ) );
		}

		$http_code = \curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		\curl_close( $ch );

		if ( 201 !== $http_code ) {
			throw new \RuntimeException( 'Error: create_block_blob - HTTP request failed with status code ' . \esc_html( $http_code ) );
		}

		if ( empty( $response ) ) {
			throw new \RuntimeException( 'Error: create_block_blob - invalid response data: ' . \esc_html( $response ) );
		}
		return self::parse_header( $response );
	}

	/**
	 * Delete blob.
	 *
	 * @since 2.7.7
	 *
	 * @param string $entra_client_id Entra ID.
	 * @param string $storage_account Storage account name.
	 * @param string $container_id    Container ID.
	 * @param string $blob            Blob ID.
	 * @return array
	 * @throws \RuntimeException Runtine Exception.
	 */
	public static function delete_blob( string $entra_client_id, string $storage_account, string $container_id, string $blob ) {
		$ch = \curl_init();

		\curl_setopt( $ch, CURLOPT_URL, 'https://' . $storage_account . '.blob.core.windows.net/' . $container_id . '/' . $blob );
		\curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		\curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'DELETE' );
		\curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				'Authorization: Bearer ' . self::get_access_token( $entra_client_id ),
				'x-ms-version: ' . self::BLOB_API_VERSION,
				'x-ms-date: ' . \gmdate( 'D, d M Y H:i:s T', \time() ),
			)
		);
		\curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );
		\curl_setopt( $ch, CURLOPT_HEADER, true );

		$response = \curl_exec( $ch );

		if ( \curl_errno( $ch ) ) {
			$error = \curl_error( $ch );
			\curl_close( $ch );
			throw new \RuntimeException( 'Error: delete_blob - cURL request failed: ' . \esc_html( $error ) );
		}

		$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );

		if ( 202 !== $http_code ) {
			throw new \RuntimeException( 'Error: delete_blob - HTTP request failed with status code ' . \esc_html( $http_code ) );
		}

		if ( empty( $response ) ) {
			throw new \RuntimeException( 'Error: delete_blob - invalid response data: ' . \esc_html( $response ) );
		}
		return self::parse_header( $response );
	}

	/**
	 * Create an Azure Blob Storage container/bucket.
	 *
	 * @since 2.7.7
	 *
	 * @param string $entra_client_id    Entra ID.
	 * @param string $storage_account    Storage account name.
	 * @param string $container_id       Container ID.
	 * @param string $public_access_type Public access type.
	 * @return array
	 * @throws \RuntimeException Runtine Exception.
	 */
	public static function create_container(
		string $entra_client_id,
		string $storage_account,
		string $container_id,
		string $public_access_type = 'blob'
	): array {
		$headers = array(
			'Authorization: Bearer ' . self::get_access_token( $entra_client_id ),
			'x-ms-version: ' . self::BLOB_API_VERSION,
			'x-ms-date: ' . \gmdate( 'D, d M Y H:i:s T', \time() ),
			'Content-Length: 0',
		);

		if ( $public_access_type ) {
			$headers[] = "x-ms-blob-public-access: $public_access_type";
		}

		$ch = \curl_init();

		\curl_setopt( $ch, CURLOPT_URL, 'https://' . $storage_account . '.blob.core.windows.net/' . $container_id . '?restype=container' );
		\curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		\curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'PUT' );
		\curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
		\curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );
		\curl_setopt( $ch, CURLOPT_HEADER, true );

		$response = \curl_exec( $ch );

		if ( \curl_errno( $ch ) ) {
			$error = \curl_error( $ch );
			\curl_close( $ch );
			throw new \RuntimeException( 'Error: create_container - cURL request failed: ' . \esc_html( $error ) );
		}

		$http_code = \curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		\curl_close( $ch );

		if ( 201 !== $http_code ) {
			throw new \RuntimeException( 'Error: create_container - HTTP request failed with status code: ' . \esc_html( $http_code ) );
		}

		if ( empty( $response ) ) {
			throw new \RuntimeException( 'Error: create_container - empty response.' );
		}
		return self::parse_header( $response );
	}

	/**
	 * List Azure Blob Storage containers.
	 *
	 * @since 2.7.7
	 *
	 * @param string $entra_client_id Entra ID.
	 * @param string $storage_account Storage account name.
	 * @return array
	 * @throws \RuntimeException Runtine Exception.
	 */
	public static function list_containers( string $entra_client_id, string $storage_account ): array {
		$ch = \curl_init();

		\curl_setopt( $ch, CURLOPT_URL, 'https://' . $storage_account . '.blob.core.windows.net/?comp=list' );
		\curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		\curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				'Authorization: Bearer ' . self::get_access_token( $entra_client_id ),
				'x-ms-version: ' . self::BLOB_API_VERSION,
				'x-ms-date: ' . \gmdate( 'D, d M Y H:i:s T', \time() ),
			)
		);
		\curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );

		$response = \curl_exec( $ch );

		if ( \curl_errno( $ch ) ) {
			$error = \curl_error( $ch );
			\curl_close( $ch );
			throw new \RuntimeException( 'Error: list_containers - cURL request failed: ' . \esc_html( $error ) );
		}

		$http_code = \curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		\curl_close( $ch );

		if ( 200 !== $http_code ) {
			throw new \RuntimeException( 'Error: list_containers - HTTP request failed with status code: ' . \esc_html( $http_code ) );
		}

		if ( empty( $response ) ) {
			throw new \RuntimeException( 'Error: list_containers - Invalid response data: ' . \esc_html( $response ) );
		}

		// Parse XML response to array.
		$xml      = \simplexml_load_string( $response );
		$json     = \json_encode( $xml );
		$response = \json_decode( $json, true );

		$array_response = array();

		if ( ! empty( $response['Containers']['Container'] ) ) {
			$array_response = self::get_array( $response['Containers']['Container'] );
		}

		return $array_response;
	}

	/**
	 * Get blob.
	 *
	 * @since 2.7.7
	 *
	 * @param string $entra_client_id Entra ID.
	 * @param string $storage_account Storage account name.
	 * @param string $container_id    Container ID.
	 * @param string $blob            Blob ID.
	 * @return array
	 * @throws \RuntimeException Runtine Exception.
	 */
	public static function get_blob( string $entra_client_id, string $storage_account, string $container_id, string $blob ): array {
		$ch = \curl_init();

		\curl_setopt( $ch, CURLOPT_URL, 'https://' . $storage_account . '.blob.core.windows.net/' . $container_id . '/' . $blob );
		\curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		\curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				'Authorization: Bearer ' . self::get_access_token( $entra_client_id ),
				'x-ms-version: ' . self::BLOB_API_VERSION,
				'x-ms-date: ' . \gmdate( 'D, d M Y H:i:s T', \time() ),
			)
		);
		\curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );
		\curl_setopt( $ch, CURLOPT_HEADER, true );

		$response = \curl_exec( $ch );

		if ( \curl_errno( $ch ) ) {
			$error = \curl_error( $ch );
			\curl_close( $ch );
			throw new \RuntimeException( 'Error: get_blob - cURL request failed: ' . \esc_html( $error ) );
		}

		$header_size = \curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
		$http_code   = \curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		\curl_close( $ch );

		if ( 200 !== $http_code ) {
			throw new \RuntimeException( 'Error: get_blob - HTTP request failed with status code: ' . \esc_html( $http_code ) );
		}

		if ( empty( $response ) ) {
			throw new \RuntimeException( 'Error: get_blob - Invalid response data: ' . \esc_html( $response ) );
		}

		return array(
			'headers' => self::parse_header( \substr( $response, 0, $header_size ) ),
			'data'    => \substr( $response, $header_size ),
		);
	}

	/**
	 * Get array.
	 *
	 * @since  2.7.7
	 * @access private
	 *
	 * @param mixed $var Variable used to get array.
	 * @return array
	 */
	private static function get_array( $var ): array {
		if ( empty( $var ) || ! \is_array( $var ) ) {
			return array();
		}

		foreach ( $var as $value ) {
			if ( ! \is_array( $value ) ) {
				return array( $var );
			}

			return $var;
		}
	}

	/**
	 * Parse header from string to array.
	 *
	 * @since  2.7.7
	 * @access private
	 *
	 * @param string $header Header.
	 * @return array
	 */
	private static function parse_header( string $header ): array {
		$headers      = array();
		$header_text  = \substr( $header, 0, \strpos( $header, "\r\n\r\n" ) );
		$header_parts = \explode( "\r\n", $header_text );

		foreach ( $header_parts as $header ) {
			if ( \strpos( $header, ':' ) !== false ) {
				$header_parts                = \explode( ':', $header );
				$headers[ $header_parts[0] ] = \trim( $header_parts[1] );
			}
		}

		return $headers;
	}
}
