<?php
/**
 * File: class-w3tc-support-page-data-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.10.6
 */

declare( strict_types = 1 );

use W3TC\Support_Page;

/**
 * Class: W3tc_Support_Page_Data_Test
 *
 * Support page localization is limited to operational fields. The
 * third-party form loads only after page, configuration, consent,
 * and click gates.
 *
 * @since 2.10.6
 */
class W3tc_Support_Page_Data_Test extends WP_UnitTestCase {

	/**
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$_GET  = array();
		$_POST = array();
		delete_site_option( 'w3tc_generic_widgetservices' );
	}

	/**
	 * Client payload contains only the allowlisted operational keys.
	 *
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function test_client_data_shape_omits_contact_fields() {
		$data = Support_Page::client_data();

		$this->assertIsArray( $data );
		$this->assertSame( Support_Page::allowed_client_keys(), array_keys( $data ) );
		$this->assertArrayNotHasKey( 'email', $data );
		$this->assertArrayNotHasKey( 'first_name', $data );
		$this->assertArrayNotHasKey( 'last_name', $data );
		$this->assertSame( Support_Page::FORM_HASH_DEFAULT, $data['form_hash'] );
		$this->assertSame( Support_Page::FORM_SCRIPT, $data['form_script'] );
		$this->assertSame( 'w3tc_support', $data['page'] );
		$this->assertSame( '', $data['field_name'] );
		$this->assertSame( '', $data['field_value'] );
		$this->assertNotSame( '', $data['postprocess'] );
		$this->assertStringNotContainsString( 'http://', $data['home_url'] );
		$this->assertStringNotContainsString( 'https://', $data['home_url'] );
	}

	/**
	 * Thank-you view does not offer the third-party form payload.
	 *
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function test_client_data_is_null_on_done_page() {
		$_GET['done'] = '1';

		$this->assertNull( Support_Page::client_data() );
	}

	/**
	 * Widget service_item may override hash and extra field when safe.
	 *
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function test_client_data_accepts_sanitized_widget_override() {
		update_site_option(
			'w3tc_generic_widgetservices',
			wp_json_encode(
				array(
					'items' => array(
						1 => array(
							'form_hash'        => 'abc123XYZ',
							'parameter_name'   => 'field12',
							'parameter_value'  => 'sku-9',
						),
					),
				)
			)
		);
		$_GET['service_item'] = '1';

		$data = Support_Page::client_data();
		$this->assertSame( 'abc123XYZ', $data['form_hash'] );
		$this->assertSame( 'field12', $data['field_name'] );
		$this->assertSame( 'sku-9', $data['field_value'] );
	}

	/**
	 * Unsafe widget values fall back instead of reaching the client.
	 *
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function test_client_data_rejects_unsafe_widget_override() {
		update_site_option(
			'w3tc_generic_widgetservices',
			wp_json_encode(
				array(
					'items' => array(
						2 => array(
							'form_hash'       => '../evil',
							'parameter_name'  => 'field6=x&field9',
							'parameter_value' => "x\ny",
						),
					),
				)
			)
		);
		$_GET['service_item'] = '2';

		$data = Support_Page::client_data();
		$this->assertSame( Support_Page::FORM_HASH_DEFAULT, $data['form_hash'] );
		$this->assertSame( '', $data['field_name'] );
		$this->assertSame( '', $data['field_value'] );
	}

	/**
	 * PHP sources no longer localize contact PII or auto-embed Wufoo.
	 *
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function test_php_sources_do_not_emit_contact_pii_or_auto_embed() {
		$page = file_get_contents( W3TC_DIR . '/Support_Page.php' );
		$view = file_get_contents( W3TC_DIR . '/Support_Page_View_PageContent.php' );

		$this->assertIsString( $page );
		$this->assertIsString( $view );

		$this->assertStringNotContainsString( 'wp_get_current_user', $page );
		$this->assertStringNotContainsString( 'admin_email', $page );
		$this->assertStringNotContainsString( 'first_name', $page );
		$this->assertStringNotContainsString( 'last_name', $page );
		$this->assertStringNotContainsString( "'email'", $page );

		$this->assertStringNotContainsString( 'wufoo.com/scripts', $view );
		$this->assertStringNotContainsString( 'first_name', $view );
		$this->assertStringNotContainsString( 'last_name', $view );
		$this->assertStringContainsString( 'w3tc-support-consent', $view );
		$this->assertStringContainsString( 'w3tc-support-load', $view );
		$this->assertStringContainsString( 'w3tc-support-fallback', $view );
		$this->assertStringContainsString( 'w3tc-support-form-mount', $view );
		$this->assertStringNotContainsString( 'wufoo-m5pom8z0qy59rm', $view );
	}

	/**
	 * Loader source encodes the four gates and never auto-loads.
	 *
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function test_support_js_gates_and_omits_contact_fields() {
		$js = file_get_contents( W3TC_DIR . '/pub/js/support.js' );
		$this->assertIsString( $js );

		$this->assertStringNotContainsString( 'first_name', $js );
		$this->assertStringNotContainsString( 'last_name', $js );
		$this->assertStringNotContainsString( 'email', $js );
		$this->assertStringNotContainsString( 'field6', $js );
		$this->assertStringNotContainsString( 'field7', $js );
		$this->assertStringNotContainsString( 'field9', $js );

		$this->assertStringContainsString( 'gatesPass', $js );
		$this->assertStringContainsString( 'w3tc_support', $js );
		$this->assertStringContainsString( 'consentChecked', $js );
		$this->assertStringContainsString( 'https://www.wufoo.com/scripts/embed/form.js', $js );
		$this->assertStringContainsString( "data.page !== \"w3tc_support\"", $js );

		$this->assertSame(
			1,
			preg_match_all( '/W3tcSupport\.loadForm\s*\(/', $js ),
			'loadForm must have exactly one caller (the click handler).'
		);
		$this->assertMatchesRegularExpression(
			'/button\.addEventListener\(\s*"click"/',
			$js
		);
		$this->assertStringContainsString( 'W3tcSupport.bind()', $js );
		$this->assertStringContainsString( 'showFallback', $js );
		$this->assertStringContainsString( 'hideFallback', $js );
		$this->assertGreaterThanOrEqual(
			2,
			preg_match_all( '/this\.hideFallback\s*\(\s*\)|W3tcSupport\.hideFallback\s*\(\s*\)/', $js ),
			'hideFallback must run when a load starts and after a successful embed.'
		);
	}

	/**
	 * Node harness covers positive and negative JS load paths.
	 *
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function test_js_load_gates_positive_and_negative_paths() {
		$node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) );
		if ( '' === $node ) {
			$this->markTestSkipped( 'node is not available.' );
		}

		$harness = W3TC_DIR . '/tests/admin/support-page-gates.js';
		$this->assertFileExists( $harness );

		$output = array();
		$code   = 0;
		exec(
			escapeshellarg( $node ) . ' ' . escapeshellarg( $harness ) . ' 2>&1',
			$output,
			$code
		);

		$this->assertSame(
			0,
			$code,
			"support-page-gates.js failed:\n" . implode( "\n", $output )
		);
	}
}
