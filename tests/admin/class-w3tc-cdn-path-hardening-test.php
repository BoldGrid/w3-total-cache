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
	 * Relative attachment paths retain their directory structure.
	 *
	 * @since X.X.X
	 */
	public function test_attachment_paths_accept_relative_upload_locations() {
		$core = new Cdn_Core();

		$this->assert_attachment_upload_path( $core, 'photo.jpg' );
		$this->assert_attachment_upload_path( $core, '2026/09/photo.jpg' );
		$this->assert_attachment_upload_path( $core, 'prophoto/galleries/album/photo.jpg' );
	}

	/**
	 * Absolute attachment paths under the uploads directory become relative.
	 *
	 * @since X.X.X
	 */
	public function test_attachment_paths_accept_absolute_path_under_uploads() {
		$upload_info = Util_Http::upload_info();
		if ( ! $upload_info ) {
			$this->markTestSkipped( 'Upload directory information is unavailable.' );
		}

		$core     = new Cdn_Core();
		$relative = 'prophoto/galleries/album/photo.jpg';

		$this->assert_attachment_upload_path(
			$core,
			$upload_info['basedir'] . '/' . $relative,
			$relative
		);
	}

	/**
	 * Attachment paths that leave the uploads directory are rejected.
	 *
	 * @since X.X.X
	 */
	public function test_attachment_paths_reject_parent_segments_and_outside_paths() {
		$upload_info = Util_Http::upload_info();
		if ( ! $upload_info ) {
			$this->markTestSkipped( 'Upload directory information is unavailable.' );
		}

		$core = new Cdn_Core();
		$paths = array(
			'../outside.jpg',
			'prophoto/../../outside.jpg',
			\dirname( $upload_info['basedir'] ) . '/outside.jpg',
			$upload_info['basedir'] . '-outside/photo.jpg',
		);

		foreach ( $paths as $path ) {
			$this->assertSame( '', $core->normalize_attachment_file( $path ), $path );
			$this->assertSame( array(), $core->get_files_for_upload( $path ), $path );
		}
	}

	/**
	 * Assert an attachment path resolves to the expected uploads location.
	 *
	 * @since X.X.X
	 *
	 * @param Cdn_Core $core          CDN core instance.
	 * @param string   $path          Attachment path to normalize.
	 * @param string   $expected_path Expected relative path.
	 */
	private function assert_attachment_upload_path( $core, $path, $expected_path = null ) {
		$upload_info = Util_Http::upload_info();
		if ( ! $upload_info ) {
			$this->markTestSkipped( 'Upload directory information is unavailable.' );
		}

		if ( null === $expected_path ) {
			$expected_path = $path;
		}

		$this->assertSame( $expected_path, $core->normalize_attachment_file( $path ) );

		$files = $core->get_files_for_upload( $path );
		$this->assertCount( 1, $files );
		$this->assertSame(
			$upload_info['basedir'] . '/' . $expected_path,
			$files[0]['local_path']
		);
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
	 * @since 2.10.1
	 */
	public function test_util_url_self_host_url_accepts_own_host() {
		$this->assertTrue(
			Util_Url::is_self_host_url( \home_url( '/wp-content/cache/minify/test.js' ) )
		);
	}

	/**
	 * The self-host check refuses other hosts and non-http schemes.
	 *
	 * @since 2.10.1
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
