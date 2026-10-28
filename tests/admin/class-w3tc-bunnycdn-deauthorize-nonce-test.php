<?php
/**
 * File: class-w3tc-bunnycdn-deauthorize-nonce-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.11.1
 */

declare( strict_types = 1 );

/**
 * Class: W3tc_Bunnycdn_Deauthorize_Nonce_Test
 *
 * The Deauthorize button must mint the nonce for the action it requests.
 * Sending the authorize nonce makes the AJAX hub return 403.
 *
 * @since 2.11.1
 */
class W3tc_Bunnycdn_Deauthorize_Nonce_Test extends WP_UnitTestCase {

	/**
	 * CDN and Full Site Delivery deauthorize openers.
	 *
	 * @return array
	 */
	public function provider_deauthorize_openers(): array {
		return array(
			'cdn' => array(
				'Cdn_BunnyCdn_Page_View.js',
				'cdn_bunnycdn_deauthorization',
			),
			'fsd' => array(
				'Cdnfsd_BunnyCdn_Page_View.js',
				'cdn_bunnycdn_fsd_deauthorization',
			),
		);
	}

	/**
	 * The opener nonce key matches the requested action.
	 *
	 * @dataProvider provider_deauthorize_openers
	 *
	 * @param string $file   Page script.
	 * @param string $action AJAX sub-action.
	 *
	 * @return void
	 */
	public function test_deauthorize_opener_uses_its_own_nonce( string $file, string $action ) {
		$src = file_get_contents( W3TC_DIR . '/' . $file );
		$this->assertNotFalse( $src );

		$pair = 'w3tcGetAjaxNonce("' . $action . '") +' . "\n" .
			'          "&w3tc_action=' . $action . '"';

		$this->assertStringContainsString( $pair, $src );
	}
}
