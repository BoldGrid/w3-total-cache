<?php
/**
 * File: class-w3tc-rackspace-service-url-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.11.1
 */

declare( strict_types = 1 );

use W3TC\Cdn_RackSpace_Api_Cdn;
use W3TC\Cdn_RackSpace_Api_CloudFiles;
use W3TC\Cdn_RackSpace_Api_CloudFilesCdn;
use W3TC\Cdn_RackSpace_Api_Tokens;

/**
 * Class: W3tc_Rackspace_Service_Url_Test
 *
 * Rackspace Cloud Files catalog endpoints use `.clouddrive.com`.
 * The region allowlist must keep those URLs and still drop others.
 *
 * @since 2.11.1
 */
class W3tc_Rackspace_Service_Url_Test extends WP_UnitTestCase {

	/**
	 * API classes and the catalog key each one sanitizes.
	 *
	 * @return array
	 */
	public function provider_catalog_keys(): array {
		return array(
			'cloud files'     => array( Cdn_RackSpace_Api_CloudFiles::class, 'object-store.publicURL' ),
			'cloud files cdn' => array( Cdn_RackSpace_Api_CloudFilesCdn::class, 'object-cdn.publicURL' ),
			'pull cdn'        => array( Cdn_RackSpace_Api_Cdn::class, 'cdn.publicURL' ),
		);
	}

	/**
	 * A clouddrive catalog URL is kept.
	 *
	 * @dataProvider provider_catalog_keys
	 *
	 * @param string $class API class.
	 * @param string $key   Descriptor key.
	 *
	 * @return void
	 */
	public function test_clouddrive_catalog_url_is_kept( string $class, string $key ) {
		$url = 'https://cdn5.clouddrive.com/v1/MossoCloudFS_example';

		$descriptor = $this->sanitized_descriptor( $class, $key, $url );

		$this->assertSame( $url, $descriptor[ $key ] );
	}

	/**
	 * A foreign host is removed.
	 *
	 * @dataProvider provider_catalog_keys
	 *
	 * @param string $class API class.
	 * @param string $key   Descriptor key.
	 *
	 * @return void
	 */
	public function test_foreign_catalog_url_is_removed( string $class, string $key ) {
		$descriptor = $this->sanitized_descriptor( $class, $key, 'https://evil.example/v1/account' );

		$this->assertArrayNotHasKey( $key, $descriptor );
	}

	/**
	 * Lookalike hosts and plain http are removed.
	 *
	 * @dataProvider provider_catalog_keys
	 *
	 * @param string $class API class.
	 * @param string $key   Descriptor key.
	 *
	 * @return void
	 */
	public function test_lookalike_and_http_catalog_urls_are_removed( string $class, string $key ) {
		$urls = array(
			'https://cdn5.clouddrive.com.evil.example/v1/account',
			'https://notclouddrive.com/v1/account',
			'http://cdn5.clouddrive.com/v1/account',
		);

		foreach ( $urls as $url ) {
			$descriptor = $this->sanitized_descriptor( $class, $key, $url );
			$this->assertArrayNotHasKey( $key, $descriptor, $url );
		}
	}

	/**
	 * The shared list is the Rackspace catalog set, with leading dots.
	 *
	 * @return void
	 */
	public function test_service_url_suffixes() {
		$this->assertSame(
			array( '.rackspacecloud.com', '.rackcdn.com', '.clouddrive.com' ),
			Cdn_RackSpace_Api_Tokens::service_url_suffixes()
		);
	}

	/**
	 * Descriptor after the API constructor sanitizes it.
	 *
	 * @param string $class API class.
	 * @param string $key   Descriptor key.
	 * @param string $url   Candidate URL.
	 *
	 * @return array
	 */
	private function sanitized_descriptor( string $class, string $key, string $url ): array {
		$api  = new $class(
			array(
				'access_token'             => 'token',
				'access_region_descriptor' => array(
					$key => $url,
				),
				'new_access_required'      => static function () {
				},
			)
		);
		$prop = new ReflectionProperty( $api, '_access_region_descriptor' );
		$prop->setAccessible( true );
		$descriptor = $prop->getValue( $api );
		$this->assertIsArray( $descriptor );
		return $descriptor;
	}
}
