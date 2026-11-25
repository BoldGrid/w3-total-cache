<?php
/**
 * The S3 Compatible CDN engine signs requests with SigV4.
 *
 * @package W3TC\Tests
 * @since   X.X.X
 */

use W3TC\CdnEngine_S3_Compatible;

if ( ! class_exists( 'S3Compatible' ) ) {
	require_once W3TC_LIB_DIR . '/S3Compatible.php';
}

/**
 * Tests for the signature version used by CdnEngine_S3_Compatible.
 *
 * @since X.X.X
 */
class Cdn_S3_Compatible_Signature_Test extends WP_UnitTestCase {
	/**
	 * Restore the client default.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function tear_down() {
		\S3Compatible::setSignatureVersion( 'v4' );
		parent::tear_down();
	}

	/**
	 * Build the engine the way Cdn_Core does for an S3 Compatible setup.
	 *
	 * @since X.X.X
	 *
	 * @param string $api_host Endpoint host.
	 *
	 * @return CdnEngine_S3_Compatible
	 */
	private function make_engine( $api_host ) {
		return new CdnEngine_S3_Compatible(
			array(
				'key'      => 'AKIAEXAMPLEKEY',
				'secret'   => 'example-secret-key',
				'bucket'   => 'example-bucket',
				'api_host' => $api_host,
			)
		);
	}

	/**
	 * Endpoints that reject SigV2.
	 *
	 * Only the AWS host carries its region in the name. The engine signs the other hosts with the default
	 * us-east-1 scope, including the Backblaze B2 host, whose own region is us-west-002.
	 *
	 * @since X.X.X
	 *
	 * @return array
	 */
	public function endpoint_provider() {
		return array(
			'cloudflare r2' => array( 'abc123.r2.cloudflarestorage.com' ),
			'aws regional'  => array( 's3.eu-west-1.amazonaws.com' ),
			'backblaze'     => array( 's3.us-west-002.backblazeb2.com' ), // Signed with us-east-1, not us-west-002.
		);
	}

	/**
	 * Constructing the engine selects v4, even after v2 was selected.
	 *
	 * @since X.X.X
	 *
	 * @dataProvider endpoint_provider
	 *
	 * @param string $api_host Endpoint host.
	 *
	 * @return void
	 */
	public function test_engine_constructor_selects_signature_v4( $api_host ) {
		\S3Compatible::setSignatureVersion( 'v2' );

		$this->make_engine( $api_host );

		$this->assertSame( 'v4', \S3Compatible::$signVer );
	}

	/**
	 * The client still accepts either version.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_client_signature_version_can_still_be_set() {
		\S3Compatible::setSignatureVersion( 'v2' );
		$this->assertSame( 'v2', \S3Compatible::$signVer );

		\S3Compatible::setSignatureVersion( 'v4' );
		$this->assertSame( 'v4', \S3Compatible::$signVer );
	}

	/**
	 * The v4 Authorization header has the SigV4 shape and a valid signature.
	 *
	 * @since X.X.X
	 *
	 * @dataProvider endpoint_provider
	 *
	 * @param string $api_host Endpoint host.
	 *
	 * @return void
	 */
	public function test_v4_authorization_header_for_engine_endpoint( $api_host ) {
		$this->make_engine( $api_host );

		$body    = 'body { color: red; }';
		$amz     = array(
			'x-amz-content-sha256' => hash( 'sha256', $body ),
			'x-amz-acl'            => 'public-read',
		);
		$headers = array(
			'Host'         => 'example-bucket.' . $api_host,
			'Date'         => gmdate( 'D, d M Y H:i:s T' ),
			'Content-MD5'  => base64_encode( md5( $body, true ) ),
			'Content-Type' => 'text/css',
		);

		$result = \S3Compatible::getSignatureV4( $amz, $headers, 'PUT', '/css/a%20b.css', $body );

		$region = \S3Compatible::getRegion();

		$this->assertMatchesRegularExpression(
			'#^AWS4-HMAC-SHA256 Credential=AKIAEXAMPLEKEY/\d{8}/' . preg_quote( $region, '#' ) . '/s3/aws4_request,SignedHeaders=[a-z0-9;-]+,Signature=[0-9a-f]{64}$#',
			$result['Authorization']
		);
		$this->assertStringNotContainsString( 'AWS AKIAEXAMPLEKEY:', $result['Authorization'] );
		$this->assertSame(
			$this->reference_authorization( $result['X-AMZ-DATE'], $region, $amz, $headers, 'PUT', '/css/a%20b.css', $body ),
			$result['Authorization']
		);
	}

	/**
	 * Without a configured region, the signing scope is us-east-1.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_v4_scope_uses_default_region_for_non_aws_endpoint() {
		$this->make_engine( 'abc123.r2.cloudflarestorage.com' );

		$this->assertSame( 'us-east-1', \S3Compatible::getRegion() );
	}

	/**
	 * A region in an AWS endpoint host is used for the signing scope.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_v4_scope_uses_region_from_aws_endpoint() {
		$this->make_engine( 's3.eu-west-1.amazonaws.com' );

		$this->assertSame( 'eu-west-1', \S3Compatible::getRegion() );
	}

	/**
	 * Empty headers are not sent, so they must not be signed.
	 *
	 * HEAD and DELETE requests carry empty Content-MD5 and Content-Type.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_v4_does_not_sign_empty_headers() {
		$this->make_engine( 'abc123.r2.cloudflarestorage.com' );

		$headers = array(
			'Host'         => 'example-bucket.abc123.r2.cloudflarestorage.com',
			'Date'         => gmdate( 'D, d M Y H:i:s T' ),
			'Content-MD5'  => '',
			'Content-Type' => '',
		);

		$result = \S3Compatible::getSignatureV4( array(), $headers, 'HEAD', '/css/a.css', false );

		$this->assertSame( 1, preg_match( '#SignedHeaders=([^,]+),#', $result['Authorization'], $match ) );
		$this->assertSame( 'date;host;x-amz-content-sha256;x-amz-date', $match[1] );
		$this->assertSame(
			$this->reference_authorization( $result['X-AMZ-DATE'], 'us-east-1', array(), $headers, 'HEAD', '/css/a.css', false ),
			$result['Authorization']
		);
	}

	/**
	 * Every x-amz-* header that is sent must be signed.
	 *
	 * GET, HEAD and DELETE have no body, but the client still sends x-amz-content-sha256 with the hash of
	 * an empty payload, so SigV4 requires it in the signed headers.
	 *
	 * @since X.X.X
	 *
	 * @dataProvider bodyless_method_provider
	 *
	 * @param string $method HTTP method.
	 *
	 * @return void
	 */
	public function test_v4_signs_payload_hash_header_it_sends( $method ) {
		$this->make_engine( 'abc123.r2.cloudflarestorage.com' );

		$headers = array(
			'Host'         => 'example-bucket.abc123.r2.cloudflarestorage.com',
			'Date'         => gmdate( 'D, d M Y H:i:s T' ),
			'Content-MD5'  => '',
			'Content-Type' => '',
		);

		$result = \S3Compatible::getSignatureV4( array(), $headers, $method, '/css/a.css', false );

		// The sha256 of the empty string, per the AWS documentation.
		$empty_hash = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

		$this->assertSame( $empty_hash, $result['x-amz-content-sha256'] );
		$this->assertSame( 1, preg_match( '#SignedHeaders=([^,]+),#', $result['Authorization'], $match ) );
		$this->assertContains( 'x-amz-content-sha256', explode( ';', $match[1] ) );
		$this->assertSame(
			$this->reference_authorization( $result['X-AMZ-DATE'], 'us-east-1', array(), $headers, $method, '/css/a.css', false ),
			$result['Authorization']
		);
	}

	/**
	 * Methods without a request body.
	 *
	 * @since X.X.X
	 *
	 * @return array
	 */
	public function bodyless_method_provider() {
		return array(
			'get'    => array( 'GET' ),
			'head'   => array( 'HEAD' ),
			'delete' => array( 'DELETE' ),
		);
	}

	/**
	 * Empty x-amz-* headers are not sent, so they must not be signed.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_v4_does_not_sign_empty_amz_headers() {
		$this->make_engine( 'abc123.r2.cloudflarestorage.com' );

		$headers = array(
			'Host' => 'example-bucket.abc123.r2.cloudflarestorage.com',
			'Date' => gmdate( 'D, d M Y H:i:s T' ),
		);
		$amz     = array( 'x-amz-meta-empty' => '' );

		$result = \S3Compatible::getSignatureV4( $amz, $headers, 'GET', '/css/a.css', false );

		$this->assertSame( 1, preg_match( '#SignedHeaders=([^,]+),#', $result['Authorization'], $match ) );
		$this->assertSame( 'date;host;x-amz-content-sha256;x-amz-date', $match[1] );
	}

	/**
	 * Independent SigV4 computation for the request the client signs.
	 *
	 * @since X.X.X
	 *
	 * @param string       $amz_date Value of the X-AMZ-DATE the client used.
	 * @param string       $region   Region.
	 * @param array        $amz      x-amz-* headers.
	 * @param array        $headers  HTTP headers.
	 * @param string       $method   HTTP method.
	 * @param string       $uri      Encoded request path.
	 * @param string|false $body     Request body.
	 *
	 * @return string Authorization header value.
	 */
	private function reference_authorization( $amz_date, $region, $amz, $headers, $method, $uri, $body ) {
		$all = array( 'x-amz-date' => $amz_date );
		foreach ( array_merge( $headers, $amz ) as $name => $value ) {
			if ( '' !== (string) $value ) {
				$all[ strtolower( $name ) ] = trim( $value );
			}
		}
		ksort( $all, SORT_STRING );

		// The payload hash is sent as x-amz-content-sha256, so it is a signed header as well.
		$payload_hash                = isset( $all['x-amz-content-sha256'] ) ? $all['x-amz-content-sha256'] : hash( 'sha256', (string) $body );
		$all['x-amz-content-sha256'] = $payload_hash;
		ksort( $all, SORT_STRING );

		$signed       = implode( ';', array_keys( $all ) );
		$canonical    = $method . "\n" . $uri . "\n\n";
		foreach ( $all as $name => $value ) {
			$canonical .= $name . ':' . $value . "\n";
		}
		$canonical .= "\n" . $signed . "\n" . $payload_hash;

		$day   = substr( $amz_date, 0, 8 );
		$scope = $day . '/' . $region . '/s3/aws4_request';
		$sts   = "AWS4-HMAC-SHA256\n" . $amz_date . "\n" . $scope . "\n" . hash( 'sha256', $canonical );

		$key = hash_hmac( 'sha256', 'aws4_request', hash_hmac( 'sha256', 's3', hash_hmac( 'sha256', $region, hash_hmac( 'sha256', $day, 'AWS4example-secret-key', true ), true ), true ), true );

		return 'AWS4-HMAC-SHA256 Credential=AKIAEXAMPLEKEY/' . $scope . ',SignedHeaders=' . $signed . ',Signature=' . hash_hmac( 'sha256', $sts, $key );
	}
}
