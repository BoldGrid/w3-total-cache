<?php
/**
 * File: class-w3tc-gdrive-engine-query-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.11.1
 */

declare( strict_types = 1 );

use W3TC\CdnEngine_GoogleDrive;

/**
 * Google Drive files.list query literal escaping.
 *
 * @since 2.11.1
 */
class W3tc_Gdrive_Engine_Query_Test extends WP_UnitTestCase {
	/**
	 * Build an engine whose listFiles call is handled by a callback.
	 *
	 * @since 2.11.1
	 *
	 * @param callable $list_files_callback Receives the listFiles argument array.
	 *
	 * @return CdnEngine_GoogleDrive
	 */
	private function engine_with_list_files_callback( callable $list_files_callback ) {
		$engine = new CdnEngine_GoogleDrive(
			array(
				'client_id'                 => 'client-id',
				'refresh_token'             => 'refresh-token',
				'root_folder_id'            => "root\\'",
				'root_url'                  => 'https://example.com/drive/',
				'access_token'              => '{"access_token":"x"}',
				'new_access_token_callback' => static function () {},
			)
		);

		$list_files = new class( $list_files_callback ) {
			/**
			 * Callback.
			 *
			 * @var callable
			 */
			private $callback;

			/**
			 * @param callable $callback ListFiles handler.
			 */
			public function __construct( callable $callback ) {
				$this->callback = $callback;
			}

			/**
			 * @param array<string,mixed> $args listFiles arguments.
			 *
			 * @return array<int,object>
			 */
			public function listFiles( array $args ) {
				return ( $this->callback )( $args );
			}
		};

		$service       = new \stdClass();
		$service->files = $list_files;

		$property = new \ReflectionProperty( CdnEngine_GoogleDrive::class, '_service' );
		$property->setAccessible( true );
		$property->setValue( $engine, $service );

		return $engine;
	}

	/**
	 * list_files_chunk() wraps parent id and title in escaped single-quoted literals.
	 *
	 * @since 2.11.1
	 */
	public function test_list_files_chunk_escapes_query_literals() {
		$captured_q = '';
		$engine     = $this->engine_with_list_files_callback(
			static function ( array $args ) use ( &$captured_q ) {
				$captured_q = $args['q'];

				return array();
			}
		);

		$method = new \ReflectionMethod( CdnEngine_GoogleDrive::class, 'list_files_chunk' );
		$method->setAccessible( true );
		$method->invoke(
			$engine,
			array(
				array( 'remote_path' => "/te\\st'fi\"le.txt" ),
			),
			false
		);

		$expected_parent = CdnEngine_GoogleDrive::escape_query_literal( "root\\'" );
		$expected_title  = CdnEngine_GoogleDrive::escape_query_literal( "te\\st'fi'le.txt" );

		$this->assertStringContainsString(
			"('$expected_parent' in parents and title = '$expected_title')",
			$captured_q
		);
		$this->assertStringNotContainsString( '"', $captured_q );
	}

	/**
	 * parent_id_resolve_step() wraps root id and folder name in escaped single-quoted literals.
	 *
	 * @since 2.11.1
	 */
	public function test_parent_id_resolve_step_escapes_query_literals() {
		\delete_transient( 'w3tc_cdn_google_drive_folder_ids' );

		$captured_q = '';
		$folder_id  = 'resolved-folder-id';
		$engine     = $this->engine_with_list_files_callback(
			static function ( array $args ) use ( &$captured_q, $folder_id ) {
				$captured_q = $args['q'];

				return array( (object) array( 'id' => $folder_id ) );
			}
		);

		$method = new \ReflectionMethod( CdnEngine_GoogleDrive::class, 'parent_id_resolve_step' );
		$method->setAccessible( true );

		$root   = "id'\\x";
		$folder = "fo'\\ld";
		$id     = $method->invoke( $engine, $root, $folder );

		$this->assertSame( $folder_id, $id );

		$expected_root   = CdnEngine_GoogleDrive::escape_query_literal( $root );
		$expected_folder = CdnEngine_GoogleDrive::escape_query_literal( $folder );

		$this->assertSame(
			"'$expected_root' in parents and title = '$expected_folder' and mimeType = 'application/vnd.google-apps.folder' and trashed = false",
			$captured_q
		);
	}
}
