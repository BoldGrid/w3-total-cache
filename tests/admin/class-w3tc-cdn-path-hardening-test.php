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
use W3TC\Cdn_Core_Admin;
use W3TC\Util_Environment;
use W3TC\Util_Http;
use W3TC\Util_Url;

/**
 * Exposes the import source resolver for focused boundary tests.
 *
 * @since 2.11.1
 */
class W3tc_Cdn_Core_Admin_Path_Test_Proxy extends Cdn_Core_Admin {

	/**
	 * Canonical content directory for fixture trees, or null for WP_CONTENT_DIR.
	 *
	 * @since 2.11.1
	 *
	 * @var string|null
	 */
	public $content_real_override = null;

	/**
	 * Canonical content directory, honouring the fixture override.
	 *
	 * @since 2.11.1
	 *
	 * @return string|false
	 */
	protected function import_content_dir_real() {
		if ( null !== $this->content_real_override ) {
			return $this->content_real_override;
		}

		return parent::import_content_dir_real();
	}

	/**
	 * Resolves an import source path.
	 *
	 * @since 2.11.1
	 *
	 * @param string $src  Import source URL path.
	 * @param string $mask Import file mask.
	 *
	 * @return string Resolved local source path.
	 */
	public function get_import_source_path( $src, $mask = '*.jpg;*.png' ) {
		return $this->get_import_library_source_path( $src, $mask );
	}

	/**
	 * Whether a canonical path is inside the content directory.
	 *
	 * @since 2.11.1
	 *
	 * @param string $resolved Canonical filesystem path.
	 *
	 * @return bool
	 */
	public function is_content_dir( $resolved ) {
		return $this->is_content_dir_path( $resolved );
	}
}

/**
 * Class: W3tc_Cdn_Path_Hardening_Test
 *
 * Regression coverage for CDN path containment and outbound download guards.
 *
 * @since 2.10.0
 */
class W3tc_Cdn_Path_Hardening_Test extends WP_UnitTestCase {

	/**
	 * Import sources must resolve to an allowed file inside the content directory.
	 *
	 * This covers literal and encoded traversal, null bytes, core trees,
	 * symlinks that escape the tree or change the file type, encoded compound
	 * extensions, query strings, and a normal content-directory media source.
	 *
	 * @since 2.11.1
	 */
	public function test_import_library_source_path_stays_within_content_dir() {
		$docroot = Util_Environment::document_root();
		if ( ! \is_string( $docroot ) || '' === $docroot ) {
			$this->markTestSkipped( 'Util_Environment::document_root() is unavailable in this runtime.' );
		}

		$docroot_real = \realpath( $docroot );
		if ( false === $docroot_real ) {
			$this->markTestSkipped( 'Document root does not resolve.' );
		}

		if ( ! \defined( 'WP_CONTENT_DIR' ) ) {
			$this->markTestSkipped( 'WP_CONTENT_DIR is unavailable in this runtime.' );
		}

		$content_real = \realpath( WP_CONTENT_DIR );
		if ( false === $content_real ) {
			$this->markTestSkipped( 'Content directory does not resolve.' );
		}

		$docroot_prefix = Util_Environment::normalize_path( $docroot_real ) . '/';
		if ( 0 !== \strpos( Util_Environment::normalize_path( $content_real ) . '/', $docroot_prefix ) ) {
			$this->markTestSkipped( 'Content directory is outside the document root in this runtime.' );
		}

		$fixture_dir  = $content_real . '/w3tc-cdn-import-path-test-' . \uniqid();
		$media_file   = $fixture_dir . '/media.jpg';
		$outside_file = \sys_get_temp_dir() . '/w3tc-cdn-import-outside-' . \uniqid() . '.jpg';
		$symlink_file = $fixture_dir . '/outside.jpg';
		$text_file    = $fixture_dir . '/notes.txt';
		$alias_file   = $fixture_dir . '/alias.jpg';
		$compound     = $fixture_dir . '/x.php.jpg';
		$dotted       = $fixture_dir . '/photo..jpg';
		$hashed       = $fixture_dir . '/photo#summer.jpg';
		$queried      = $fixture_dir . '/photo?summer.jpg';

		\mkdir( $fixture_dir );
		\file_put_contents( $media_file, 'media' );
		\file_put_contents( $outside_file, 'outside' );
		\file_put_contents( $text_file, 'notes' );
		\file_put_contents( $compound, 'compound' );
		\file_put_contents( $dotted, 'dotted' );
		\file_put_contents( $hashed, 'hashed' );
		\file_put_contents( $queried, 'queried' );

		if ( ! \symlink( $outside_file, $symlink_file ) || ! \symlink( $text_file, $alias_file ) ) {
			@\unlink( $symlink_file );
			@\unlink( $media_file );
			@\unlink( $text_file );
			@\unlink( $compound );
			@\unlink( $dotted );
			@\unlink( $hashed );
			@\unlink( $queried );
			@\rmdir( $fixture_dir );
			@\unlink( $outside_file );
			$this->markTestSkipped( 'Unable to create a symlink on this host.' );
		}

		$relative_dir     = \substr( Util_Environment::normalize_path( $fixture_dir ), \strlen( $docroot_prefix ) );
		$relative_media   = $relative_dir . '/media.jpg';
		$relative_symlink = $relative_dir . '/outside.jpg';
		$resolver         = new W3tc_Cdn_Core_Admin_Path_Test_Proxy();

		try {
			$this->assertSame( '', $resolver->get_import_source_path( '../outside.jpg' ) );
			$this->assertSame( '', $resolver->get_import_source_path( '%2e%2e%2foutside.jpg' ) );
			$this->assertSame( '', $resolver->get_import_source_path( $relative_media . '%00.jpg' ) );
			$this->assertSame( '', $resolver->get_import_source_path( 'wp-admin/images/w-logo-blue.png' ) );
			$this->assertSame( '', $resolver->get_import_source_path( $relative_symlink ) );
			$this->assertSame( '', $resolver->get_import_source_path( $relative_dir . '/alias.jpg' ) );
			$this->assertSame( '', $resolver->get_import_source_path( $relative_dir . '/x%2ephp.jpg' ) );
			$this->assertSame( \realpath( $media_file ), $resolver->get_import_source_path( $relative_media ) );
			$this->assertSame( \realpath( $media_file ), $resolver->get_import_source_path( '/' . $relative_media . '?ver=1#top' ) );
			$this->assertSame( \realpath( $dotted ), $resolver->get_import_source_path( $relative_dir . '/photo..jpg' ) );
			$this->assertSame( \realpath( $hashed ), $resolver->get_import_source_path( $relative_dir . '/photo%23summer.jpg' ) );
			$this->assertSame( \realpath( $queried ), $resolver->get_import_source_path( $relative_dir . '/photo%3Fsummer.jpg' ) );
			$this->assertSame( '', $resolver->get_import_source_path( $relative_media, '*.png' ) );
		} finally {
			@\unlink( $symlink_file );
			@\unlink( $alias_file );
			@\unlink( $media_file );
			@\unlink( $text_file );
			@\unlink( $compound );
			@\unlink( $dotted );
			@\unlink( $hashed );
			@\unlink( $queried );
			@\rmdir( $fixture_dir );
			@\unlink( $outside_file );
		}
	}

	/**
	 * A sibling directory sharing the content directory name prefix is outside it.
	 *
	 * @since 2.11.1
	 */
	public function test_import_content_dir_check_rejects_sibling_prefix() {
		$content_real = \defined( 'WP_CONTENT_DIR' ) ? \realpath( WP_CONTENT_DIR ) : false;
		if ( false === $content_real ) {
			$this->markTestSkipped( 'Content directory does not resolve.' );
		}

		$resolver = new W3tc_Cdn_Core_Admin_Path_Test_Proxy();

		$this->assertTrue( $resolver->is_content_dir( $content_real . '/uploads/media.jpg' ) );
		$this->assertFalse( $resolver->is_content_dir( $content_real . '-old/uploads/media.jpg' ) );
		$this->assertFalse( $resolver->is_content_dir( \dirname( $content_real ) . '/wp-config.php' ) );
		$this->assertFalse( $resolver->is_content_dir( '' ) );
	}

	/**
	 * With wp-content resolving outside the document root, an image under the resolved tree imports.
	 *
	 * Simulates wp-content as a symlink to a directory outside the document root. A
	 * document-root symlink points at a fixture tree outside the docroot, and the
	 * fixture content directory is the override. Escapes and sibling prefixes still fail.
	 *
	 * @since 2.11.1
	 */
	public function test_import_source_accepts_content_dir_resolved_outside_docroot() {
		$docroot = Util_Environment::document_root();
		if ( ! \is_string( $docroot ) || '' === $docroot ) {
			$this->markTestSkipped( 'Util_Environment::document_root() is unavailable in this runtime.' );
		}

		$docroot_real = \realpath( $docroot );
		if ( false === $docroot_real ) {
			$this->markTestSkipped( 'Document root does not resolve.' );
		}

		$base         = \sys_get_temp_dir() . '/w3tc-import-wpc-' . \uniqid();
		$content      = $base . '/wp-content';
		$media        = $content . '/uploads/media.jpg';
		$secret       = $base . '/secret.jpg';
		$escape       = $content . '/escape.jpg';
		$sibling      = $base . '/wp-content-evil/evil.jpg';
		$docroot_link = $docroot_real . '/w3tc-import-wpc-link-' . \uniqid();

		$cleanup = function () use ( $base, $docroot_link, $escape, $media, $secret, $sibling ) {
			@\unlink( $docroot_link );
			@\unlink( $escape );
			@\unlink( $media );
			@\unlink( $secret );
			@\unlink( $sibling );
			@\rmdir( \dirname( $media ) );
			@\rmdir( $base . '/wp-content' );
			@\rmdir( \dirname( $sibling ) );
			@\rmdir( $base );
		};

		$made = \is_dir( \dirname( $media ) ) || \mkdir( \dirname( $media ), 0755, true );
		$made = $made && ( \is_dir( \dirname( $sibling ) ) || \mkdir( \dirname( $sibling ), 0755, true ) );
		if ( ! $made ) {
			$cleanup();
			$this->markTestSkipped( 'Unable to create the fixture tree on this host.' );
		}

		\file_put_contents( $media, 'media' );
		\file_put_contents( $secret, 'secret' );
		\file_put_contents( $sibling, 'evil' );

		$linked = @\symlink( $base, $docroot_link ) && @\symlink( $secret, $escape );
		if ( ! $linked ) {
			$cleanup();
			$this->markTestSkipped( 'Unable to create a symlink on this host.' );
		}

		$relative_link = \basename( $docroot_link );
		$resolver      = new W3tc_Cdn_Core_Admin_Path_Test_Proxy();
		$resolver->content_real_override = \realpath( $content );

		try {
			$this->assertSame( \realpath( $media ), $resolver->get_import_source_path( $relative_link . '/wp-content/uploads/media.jpg' ) );
			$this->assertSame( '', $resolver->get_import_source_path( $relative_link . '/wp-content/escape.jpg' ) );
			$this->assertSame( '', $resolver->get_import_source_path( $relative_link . '/wp-content-evil/evil.jpg' ) );
			$this->assertSame( '', $resolver->get_import_source_path( $relative_link . '/../wp-content/uploads/media.jpg' ) );
		} finally {
			$cleanup();
		}
	}

	/**
	 * Media library import copies only local sources under the content directory.
	 *
	 * Drives import_library() on a published post so the local-copy call site is
	 * covered, not only the resolver.
	 *
	 * @since 2.11.1
	 */
	public function test_import_library_copies_only_content_dir_sources() {
		$docroot = Util_Environment::document_root();
		if ( ! \is_string( $docroot ) || '' === $docroot || false === \realpath( $docroot ) ) {
			$this->markTestSkipped( 'Document root does not resolve in this runtime.' );
		}

		$content_real = \defined( 'WP_CONTENT_DIR' ) ? \realpath( WP_CONTENT_DIR ) : false;
		if ( false === $content_real ) {
			$this->markTestSkipped( 'Content directory does not resolve.' );
		}

		$docroot_prefix = Util_Environment::normalize_path( \realpath( $docroot ) ) . '/';
		if ( 0 !== \strpos( Util_Environment::normalize_path( $content_real ) . '/', $docroot_prefix ) ) {
			$this->markTestSkipped( 'Content directory is outside the document root in this runtime.' );
		}

		$logo = ABSPATH . WPINC . '/images/w-logo-blue.png';
		if ( ! \is_file( $logo ) ) {
			$this->markTestSkipped( 'Core logo image is unavailable in this runtime.' );
		}

		$unique       = \uniqid();
		$fixture_dir  = $content_real . '/w3tc-cdn-import-library-test-' . $unique;
		$media_file   = $fixture_dir . '/media-' . $unique . '.png';
		$outside_name = 'w3tc-cdn-import-outside-' . $unique . '.jpg';
		$outside_file = \realpath( $docroot ) . '/' . $outside_name;

		\mkdir( $fixture_dir );
		\copy( $logo, $media_file );
		\copy( $logo, $outside_file );

		$content_rel = \substr( Util_Environment::normalize_path( $content_real ), \strlen( $docroot_prefix ) );
		$fixture_rel = \substr( Util_Environment::normalize_path( $fixture_dir ), \strlen( $docroot_prefix ) );
		$sources     = array(
			'/' . $fixture_rel . '/' . \basename( $media_file ),
			'/' . $outside_name,
			'/' . WPINC . '/images/w-logo-blue.png',
			'/' . $content_rel . '/../' . $outside_name,
		);

		$post_content = '';
		foreach ( $sources as $source ) {
			$post_content .= '<img src="' . $source . '" />';
		}

		$post_id = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_type'    => 'post',
				'post_content' => $post_content,
			)
		);

		$config    = \W3TC\Dispatcher::config();
		$old_mask  = $config->get( 'cdn.import.files' );
		$old_rules = $GLOBALS['wp_rewrite'];
		$config->set( 'cdn.import.files', '*.jpg;*.png' );

		$attachments = array(
			'post_parent' => $post_id,
			'post_type'   => 'attachment',
			'fields'      => 'ids',
		);

		try {
			$count   = 0;
			$total   = 0;
			$results = array();

			( new Cdn_Core_Admin() )->import_library( 0, 0, $count, $total, $results );

			$rows = array();
			foreach ( $results as $row ) {
				$rows[ $row['src'] ] = $row;
			}

			$attachment_ids = \get_children( $attachments );

			$keys = array();
			foreach ( $sources as $source ) {
				$key = \ltrim( $source, '/' );
				$this->assertArrayHasKey( $key, $rows, 'Missing import row for ' . $source );
				$keys[] = $key;
			}

			$this->assertTrue( $rows[ $keys[0] ]['result'] );
			$this->assertSame( 'OK', $rows[ $keys[0] ]['error'] );

			foreach ( \array_slice( $keys, 1 ) as $key ) {
				$this->assertFalse( $rows[ $key ]['result'], $key );
				$this->assertSame( 'Source path is not an allowed import location', $rows[ $key ]['error'], $key );
			}

			$this->assertCount( 1, $attachment_ids );
			$copied = \get_attached_file( (int) \reset( $attachment_ids ) );
			$this->assertIsString( $copied );
			$this->assertFileExists( $copied );
			$this->assertSame( \file_get_contents( $media_file ), \file_get_contents( $copied ) );

			$updated = \get_post_field( 'post_content', $post_id );
			$this->assertStringNotContainsString( 'src="' . $sources[0] . '"', $updated );
			$this->assertStringContainsString( 'src="' . \wp_get_attachment_url( (int) \reset( $attachment_ids ) ) . '"', $updated );
			foreach ( \array_slice( $sources, 1 ) as $source ) {
				$this->assertStringContainsString( 'src="' . $source . '"', $updated );
			}
		} finally {
			foreach ( \get_children( $attachments ) as $attachment_id ) {
				\wp_delete_attachment( (int) $attachment_id, true );
			}
			$config->set( 'cdn.import.files', $old_mask );
			$GLOBALS['wp_rewrite'] = $old_rules;
			@\unlink( $media_file );
			@\rmdir( $fixture_dir );
			@\unlink( $outside_file );
		}
	}

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
	 * @since 2.10.7
	 */
	public function test_attachment_paths_accept_relative_upload_locations() {
		$core = new Cdn_Core();

		$this->assert_attachment_upload_path( $core, 'photo.jpg' );
		$this->assert_attachment_upload_path( $core, '2026/09/photo.jpg' );
		$this->assert_attachment_upload_path( $core, 'prophoto/galleries/album/photo.jpg' );
		$this->assert_attachment_upload_path(
			$core,
			'prophoto\\galleries\\album\\photo.jpg',
			'prophoto/galleries/album/photo.jpg'
		);
	}

	/**
	 * Absolute attachment paths under the uploads directory become relative.
	 *
	 * @since 2.10.7
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
	 * @since 2.10.7
	 */
	public function test_attachment_paths_reject_parent_segments_and_outside_paths() {
		$upload_info = Util_Http::upload_info();
		if ( ! $upload_info ) {
			$this->markTestSkipped( 'Upload directory information is unavailable.' );
		}

		$core  = new Cdn_Core();
		$paths = array(
			'../outside.jpg',
			'prophoto/../../outside.jpg',
			'prophoto\\..\\..\\outside.jpg',
			\dirname( $upload_info['basedir'] ) . '/outside.jpg',
			$upload_info['basedir'] . '-outside/photo.jpg',
		);

		foreach ( $paths as $path ) {
			$this->assertSame( '', $core->normalize_attachment_file( $path ), $path );
			$this->assertSame( array(), $core->get_files_for_upload( $path ), $path );
		}
	}

	/**
	 * Nested thumbnail metadata keeps the custom parent directory.
	 *
	 * @since 2.10.7
	 */
	public function test_metadata_files_preserve_nested_thumbnail_paths() {
		$core           = new Cdn_Core();
		$attached_file  = 'prophoto/galleries/album/photo.jpg';
		$thumbnail_file = 'photo-150x150.jpg';
		$expected_path  = 'prophoto/galleries/album/photo-150x150.jpg';
		$sizes          = array(
			'thumbnail' => array(
				'file' => $thumbnail_file,
			),
		);

		$size_files = $core->_get_sizes_files( $attached_file, $sizes );
		$this->assert_attachment_file_descriptor( $core, $size_files, $expected_path );

		$metadata_files = $core->get_metadata_files(
			array(
				'file'  => $attached_file,
				'sizes' => $sizes,
			)
		);
		$this->assert_attachment_file_descriptor( $core, $metadata_files, $expected_path );
	}

	/**
	 * Windows drive-letter case does not drop a contained nested path.
	 *
	 * @since 2.10.7
	 */
	public function test_attachment_paths_accept_windows_drive_letter_case() {
		$core     = new Cdn_Core();
		$relative = 'prophoto/galleries/album/photo.jpg';
		$method   = new \ReflectionMethod( Cdn_Core::class, 'normalize_attachment_file_under_basedir' );
		\W3TC\Util_Reflection::set_accessible( $method );

		$this->assertSame(
			$relative,
			$method->invoke(
				$core,
				'C:/wordpress/wp-content/uploads',
				'c:/wordpress/wp-content/uploads/' . $relative
			)
		);
		$this->assertSame(
			'',
			$method->invoke(
				$core,
				'C:/wordpress/wp-content/uploads',
				'C:/wordpress/wp-content/uploads-outside/' . $relative
			)
		);
		$this->assertSame(
			'',
			$method->invoke(
				$core,
				'C:/wordpress/wp-content/uploads',
				'C:/wordpress/wp-content/Uploads/' . $relative
			)
		);
	}

	/**
	 * Assert an attachment path resolves to the expected uploads location.
	 *
	 * @since 2.10.7
	 *
	 * @param Cdn_Core $core          CDN core instance.
	 * @param string   $path          Attachment path to normalize.
	 * @param string   $expected_path Expected relative path.
	 */
	private function assert_attachment_upload_path( $core, $path, $expected_path = null ) {
		if ( null === $expected_path ) {
			$expected_path = $path;
		}

		$this->assertSame( $expected_path, $core->normalize_attachment_file( $path ) );
		$this->assert_attachment_file_descriptor(
			$core,
			$core->get_files_for_upload( $path ),
			$expected_path
		);
	}

	/**
	 * Assert upload descriptors keep nested local and remote paths.
	 *
	 * @since 2.10.7
	 *
	 * @param Cdn_Core $core          CDN core instance.
	 * @param array    $files         File descriptors.
	 * @param string   $expected_path Expected relative path under uploads.
	 */
	private function assert_attachment_file_descriptor( $core, $files, $expected_path ) {
		$upload_info = Util_Http::upload_info();
		if ( ! $upload_info ) {
			$this->markTestSkipped( 'Upload directory information is unavailable.' );
		}

		$this->assertCount( 1, $files );
		$this->assertSame(
			$upload_info['basedir'] . '/' . $expected_path,
			$files[0]['local_path']
		);
		$this->assertSame(
			$this->expected_upload_remote_path( $core, $expected_path ),
			$files[0]['remote_path']
		);
		$this->assertStringEndsWith( $expected_path, $files[0]['remote_path'] );
	}

	/**
	 * Expected CDN remote path for an uploads-relative file.
	 *
	 * @since 2.10.7
	 *
	 * @param Cdn_Core $core          CDN core instance.
	 * @param string   $expected_path Expected relative path under uploads.
	 *
	 * @return string
	 */
	private function expected_upload_remote_path( $core, $expected_path ) {
		$upload_info = Util_Http::upload_info();
		$parsed      = \wp_parse_url( \rtrim( $upload_info['baseurl'], '/' ) . '/' . $expected_path );

		return \ltrim( $core->uri_to_cdn_uri( $parsed['path'] ), '/' );
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
