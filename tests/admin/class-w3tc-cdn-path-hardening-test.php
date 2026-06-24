<?php
/**
 * File: class-w3tc-cdn-path-hardening-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.10.0
 */

declare( strict_types = 1 );

use W3TC\Cdn_Core;
use W3TC\Util_Environment;
use W3TC\Util_Http;
use W3TC\Util_Url;

/**
 * Class: W3tc_Cdn_Path_Hardening_Test
 *
 * Regression coverage for CDN path containment and outbound download guards.
 *
 * @since 2.10.0
 */
class W3tc_Cdn_Path_Hardening_Test extends WP_UnitTestCase {

	/**
	 * Absolute paths outside document root are rejected even when readable.
	 *
	 * @since 2.10.0
	 */
	public function test_docroot_filename_rejects_absolute_outside_docroot() {
		if ( ! \is_readable( '/etc/passwd' ) ) {
			$this->markTestSkipped( '/etc/passwd not readable on this host.' );
		}

		$core = new Cdn_Core();

		$this->assertSame(
			'',
			$core->docroot_filename_to_absolute_path( '/etc/passwd' )
		);
	}

	/**
	 * Traversal segments are rejected before resolution.
	 *
	 * @since 2.10.0
	 */
	public function test_docroot_filename_rejects_traversal() {
		$core = new Cdn_Core();

		$this->assertSame(
			'',
			$core->docroot_filename_to_absolute_path( 'wp-content/../../etc/passwd' )
		);
	}

	/**
	 * A relative path under document root resolves inside the boundary.
	 *
	 * @since 2.10.0
	 */
	public function test_docroot_filename_accepts_relative_under_docroot() {
		$docroot = Util_Environment::document_root();
		if ( ! \is_string( $docroot ) || '' === $docroot ) {
			$this->markTestSkipped( 'Util_Environment::document_root() is unavailable in this runtime.' );
		}

		$docroot_real = \realpath( $docroot );
		if ( false === $docroot_real ) {
			$this->markTestSkipped( 'Document root does not resolve.' );
		}

		$core        = new Cdn_Core();
		$fixture_abs = $docroot_real . '/w3tc-docroot-path-test-' . \uniqid() . '.txt';
		\file_put_contents( $fixture_abs, 'ok' );

		$relative = \ltrim( \str_replace( $docroot_real, '', $fixture_abs ), '/\\' );
		if ( '/' !== \DIRECTORY_SEPARATOR ) {
			$relative = \str_replace( \DIRECTORY_SEPARATOR, '/', $relative );
		}

		try {
			$result = $core->docroot_filename_to_absolute_path( $relative );

			$this->assertSame(
				Util_Environment::normalize_path( $fixture_abs ),
				Util_Environment::normalize_path( $result )
			);
		} finally {
			@\unlink( $fixture_abs );
		}
	}

	/**
	 * Outbound downloads refuse non-public hosts.
	 *
	 * @since 2.10.0
	 */
	public function test_util_http_download_refuses_loopback() {
		$tmp = \sys_get_temp_dir() . '/w3tc-http-download-' . \uniqid() . '.txt';

		$this->assertFalse(
			Util_Http::download( 'http://127.0.0.1/', $tmp )
		);
		$this->assertFileDoesNotExist( $tmp );
	}

	/**
	 * URLs targeting the install's own host pass the self-host check
	 * regardless of what the hostname resolves to.
	 *
	 * @since X.X.X
	 */
	public function test_util_url_self_host_url_accepts_own_host() {
		$this->assertTrue(
			Util_Url::is_self_host_url( \home_url( '/wp-content/cache/minify/test.js' ) )
		);
	}

	/**
	 * The self-host check refuses other hosts and non-http schemes.
	 *
	 * @since X.X.X
	 */
	public function test_util_url_self_host_url_refuses_other_hosts() {
		$this->assertFalse( Util_Url::is_self_host_url( 'http://other-host.example/file.js' ) );
		$this->assertFalse( Util_Url::is_self_host_url( 'http://127.0.0.1/file.js' ) );
		$this->assertFalse(
			Util_Url::is_self_host_url(
				'file://' . \wp_parse_url( \home_url(), PHP_URL_HOST ) . '/file.js'
			)
		);
		$this->assertFalse( Util_Url::is_self_host_url( '' ) );
	}
}
