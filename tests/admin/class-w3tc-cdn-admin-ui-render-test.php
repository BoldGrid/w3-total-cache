<?php
/**
 * File: class-w3tc-cdn-admin-ui-render-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.10.6
 */

declare( strict_types = 1 );

/**
 * Class: W3tc_Cdn_Admin_Ui_Render_Test
 *
 * CDN admin JS must insert server strings as text. Logs that used
 * to concatenate path/error into HTML are built with DOM text nodes.
 *
 * @since 2.10.6
 */
class W3tc_Cdn_Admin_Ui_Render_Test extends WP_UnitTestCase {

	/**
	 * Popup log rows no longer concatenate server fields into HTML.
	 *
	 * @since 2.10.6
	 */
	public function test_popup_logs_use_dom_text_helper() {
		$popup = (string) file_get_contents( dirname( __DIR__, 2 ) . '/pub/js/popup.js' );

		$this->assertStringContainsString( 'w3tc_cdn_log_entry(path, result, error)', $popup );
		$this->assertDoesNotMatchRegularExpression(
			'/path\s*\+\s*" <strong>"\s*\+\s*error/',
			$popup
		);
		$this->assertStringContainsString( '.text(status)', $popup );
		$this->assertStringContainsString( '#cdn_rename_domain_status', $popup );
		$this->assertStringNotContainsString( 'jQuery("cdn_rename_domain_status")', $popup );
	}

	/**
	 * CDN Test / Create Container status fields use the text sink.
	 *
	 * @since 2.10.6
	 */
	public function test_cdn_test_status_uses_text_sink() {
		$options = (string) file_get_contents( dirname( __DIR__, 2 ) . '/pub/js/options.js' );
		$cdn_on  = strpos( $options, 'jQuery("#cdn_test").on("click"' );
		$mem_on  = strpos( $options, 'jQuery("#memcached_test").on("click"' );

		$this->assertNotFalse( $cdn_on );
		$this->assertNotFalse( $mem_on );
		$this->assertGreaterThan( $cdn_on, $mem_on );

		$cdn_block = substr( $options, $cdn_on, $mem_on - $cdn_on );

		$this->assertStringContainsString( 'status.text(data.error)', $cdn_block );
		$this->assertStringNotContainsString( 'status.html(data.error)', $cdn_block );
	}

	/**
	 * Rackspace service fields use the text sink.
	 *
	 * @since 2.10.6
	 */
	public function test_rackspace_status_uses_text_sink() {
		$js = (string) file_get_contents( dirname( __DIR__, 2 ) . '/Cdn_RackSpaceCdn_Page_View.js' );

		$this->assertStringContainsString( '.text(status)', $js );
		$this->assertStringContainsString( '.text(data["cname"])', $js );
		$this->assertStringContainsString( '.text(data["access_url"])', $js );
		$this->assertStringNotContainsString( '.html(data["cname"])', $js );
	}

	/**
	 * TransparentCDN failure rendering builds a link via the URL sink.
	 *
	 * @since 2.10.6
	 */
	public function test_transparentcdn_failure_uses_url_sink() {
		$js  = (string) file_get_contents( dirname( __DIR__, 2 ) . '/Cdnfsd_TransparentCDN_Page_View.js' );
		$php = (string) file_get_contents( dirname( __DIR__, 2 ) . '/Cdnfsd_TransparentCDN_Page.php' );

		$this->assertStringContainsString( 'w3tc_cdn_set_url', $js );
		$this->assertStringContainsString( 'el.textContent', $js );
		$this->assertStringNotContainsString( 'box.innerHTML', $js );
		$this->assertStringContainsString( 'support_url', $php );
		$this->assertStringNotContainsString( "test_failure' => sprintf", $php );
	}

	/**
	 * Popup windows load the shared rendering helper before popup.js.
	 *
	 * @since 2.10.6
	 */
	public function test_popup_header_loads_helper() {
		$header = (string) file_get_contents( dirname( __DIR__, 2 ) . '/inc/popup/common/header.php' );

		$this->assertStringContainsString( 'pub/js/cdn-admin-ui.js', $header );
		$helper = strpos( $header, 'cdn-admin-ui.js' );
		$popup  = strpos( $header, 'popup.js' );
		$this->assertNotFalse( $helper );
		$this->assertNotFalse( $popup );
		$this->assertLessThan( $popup, $helper );
	}
}
