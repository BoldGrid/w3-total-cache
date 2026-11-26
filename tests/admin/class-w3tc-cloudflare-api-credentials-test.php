<?php
/**
 * File: class-w3tc-cloudflare-api-credentials-test.php
 *
 * Cloudflare key detection stays inside the API class.
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.11.1
 */

declare( strict_types = 1 );

use W3TC\Extension_CloudFlare_Api;

/**
 * Class: W3tc_Cloudflare_Api_Credentials_Test
 *
 * @since 2.11.1
 */
class W3tc_Cloudflare_Api_Credentials_Test extends WP_UnitTestCase {

	/**
	 * The key helper and the following methods stay on the API class.
	 *
	 * @since 2.11.1
	 */
	public function test_api_class_keeps_credential_methods() {
		$this->assertTrue( method_exists( Extension_CloudFlare_Api::class, 'is_legacy_global_api_key_string' ) );
		$this->assertTrue( method_exists( Extension_CloudFlare_Api::class, 'are_api_credentials_usable' ) );
	}

	/**
	 * Legacy keys are the 37-character form and the cfk_ prefix.
	 *
	 * @since 2.11.1
	 */
	public function test_legacy_key_detection() {
		$this->assertTrue( Extension_CloudFlare_Api::is_legacy_global_api_key_string( str_repeat( 'a', 37 ) ) );
		$this->assertTrue( Extension_CloudFlare_Api::is_legacy_global_api_key_string( 'cfk_example' ) );
		$this->assertFalse( Extension_CloudFlare_Api::is_legacy_global_api_key_string( str_repeat( 'b', 40 ) ) );
		$this->assertFalse( Extension_CloudFlare_Api::is_legacy_global_api_key_string( '' ) );
	}

	/**
	 * Tokens do not need an email. Legacy keys do.
	 *
	 * @since 2.11.1
	 */
	public function test_credential_usability() {
		$this->assertTrue( Extension_CloudFlare_Api::are_api_credentials_usable( '', str_repeat( 'b', 40 ) ) );
		$this->assertFalse( Extension_CloudFlare_Api::are_api_credentials_usable( '', str_repeat( 'a', 37 ) ) );
		$this->assertFalse( Extension_CloudFlare_Api::are_api_credentials_usable( '', 'cfk_example' ) );
		$this->assertTrue( Extension_CloudFlare_Api::are_api_credentials_usable( 'user@example.com', str_repeat( 'a', 37 ) ) );
		$this->assertTrue( Extension_CloudFlare_Api::are_api_credentials_usable( 'user@example.com', 'cfk_example' ) );
		$this->assertFalse( Extension_CloudFlare_Api::are_api_credentials_usable( 'user@example.com', '' ) );
	}
}
