<?php
/**
 * File: CdnEngine_GoogleDrive.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class CdnEngine_GoogleDrive
 *
 * Google drive engine
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.PHP.DiscouragedPHPFunctions
 * phpcs:disable WordPress.WP.AlternativeFunctions
 * phpcs:disable WordPress.DB.DirectDatabaseQuery
 * phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
 */
class CdnEngine_GoogleDrive extends CdnEngine_Base {
	/**
	 * Client ID
	 *
	 * @var string
	 */
	private $_client_id;

	/**
	 * Refresh token
	 *
	 * @var string
	 */
	private $_refresh_token;

	/**
	 * Root folder ID
	 *
	 * @var string
	 */
	private $_root_folder_id;

	/**
	 * Root URL
	 *
	 * @var string
	 */
	private $_root_url;

	/**
	 * Google Service Drive object
	 *
	 * @var W3TCG_Google_Service_Drive
	 */
	private $_service;

	/**
	 * Tablename pathmap
	 *
	 * @var string
	 */
	private $_tablename_pathmap;

	/**
	 * Callback function to handle the updated access token.
	 *
	 * This callback is invoked with the new access token whenever the token is refreshed.
	 *
	 * @var callable
	 */
	private $_new_access_token_callback;

	/**
	 * Constructor to initialize the Google Drive CDN engine.
	 *
	 * @param array $config {
	 *     Configuration options for the Google Drive CDN engine.
	 *
	 *     @type string   $client_id                 The client ID for the Google Drive API.
	 *     @type string   $refresh_token             The refresh token for authentication.
	 *     @type string   $root_folder_id            The root folder ID for the Google Drive.
	 *     @type string   $root_url                  The root URL for the Google Drive CDN.
	 *     @type callable $new_access_token_callback Callback function for new access token.
	 *     @type string   $access_token              The access token for authentication.
	 * }
	 */
	public function __construct( $config = array() ) {
		parent::__construct( $config );

		$this->_client_id                 = $config['client_id'];
		$this->_refresh_token             = $config['refresh_token'];
		$this->_root_folder_id            = $config['root_folder_id'];
		$this->_root_url                  = rtrim( $config['root_url'], '/' ) . '/';
		$this->_new_access_token_callback = $config['new_access_token_callback'];

		global $wpdb;
		$this->_tablename_pathmap = $wpdb->base_prefix . W3TC_CDN_TABLE_PATHMAP;

		try {
			$this->_init_service( $config['access_token'] );
		} catch ( \Exception $e ) {
			$this->_service = null;
		}
	}

	/**
	 * Initializes the Google Drive service with the provided access token.
	 *
	 * @param string $access_token The access token used to authenticate with the Google Drive API.
	 *
	 * @throws \Exception If the client ID or access token is missing, or if the service initialization fails.
	 */
	private function _init_service( $access_token ) {
		if ( empty( $this->_client_id ) || empty( $access_token ) ) {
			throw new \Exception( \esc_html__( 'Service not configured.', 'w3-total-cache' ) );
		}

		$client = new \W3TCG_Google_Client();
		$client->setClientId( $this->_client_id );
		$client->setAccessToken( $access_token );
		$this->_service = new \W3TCG_Google_Service_Drive( $client );
	}


	/**
	 * Refreshes the access token using the stored refresh token.
	 *
	 * @throws \Exception If the refresh request fails or returns an error.
	 */
	private function _refresh_token() {
		$result = wp_remote_post(
			W3TC_GOOGLE_DRIVE_AUTHORIZE_URL,
			array(
				'body' => array(
					'client_id'     => $this->_client_id,
					'refresh_token' => $this->_refresh_token,
				),
			)
		);

		if ( is_wp_error( $result ) ) {
			throw new \Exception( esc_html( $result ) );
		} elseif ( 200 !== (int) $result['response']['code'] ) {
			throw new \Exception( wp_kses_post( $result['body'] ) );
		}

		$access_token = $result['body'];
		call_user_func( $this->_new_access_token_callback, $access_token );
		$this->_init_service( $access_token );
	}

	/**
	 * Uploads files to Google Drive.
	 *
	 * @param array $files         Array of file descriptors to upload. Each descriptor must contain the 'local_path' and 'remote_path' at a minimum.
	 * @param array $results       Reference to an array where the upload results will be stored.
	 * @param bool  $force_rewrite Whether to forcefully overwrite existing files on Google Drive (default: false).
	 * @param int   $timeout_time  Optional timeout time in seconds for the upload operation.
	 *
	 * @return bool|string True if the upload was successful, or 'timeout' if the upload timed out.
	 */
	public function upload( $files, &$results, $force_rewrite = false, $timeout_time = null ) {
		if ( is_null( $this->_service ) ) {
			return false;
		}

		$allow_refresh_token = true;
		$result              = true;

		$files_chunks = array_chunk( $files, 20 );
		foreach ( $files_chunks as $files_chunk ) {
			$r = $this->_upload_chunk(
				$files_chunk,
				$results,
				$force_rewrite,
				$timeout_time,
				$allow_refresh_token
			);
			if ( 'refresh_required' === $r ) {
				$allow_refresh_token = false;
				$this->_refresh_token();

				$r = $this->_upload_chunk(
					$files_chunk,
					$results,
					$force_rewrite,
					$timeout_time,
					$allow_refresh_token
				);
			}

			if ( 'success' !== $r ) {
				$result = false;
			}

			if ( 'timeout' === $r ) {
				return 'timeout';
			}
		}

		return $result;
	}

	/**
	 * Converts file properties to a Google Drive path.
	 *
	 * @param object $file The file object containing properties to convert.
	 *
	 * @return string|null The constructed path or null if no valid path properties are found.
	 */
	private function _properties_to_path( $file ) {
		$path_pieces = array();
		foreach ( $file->properties as $p ) {
			$k = ( 'path' === $p->key ) ? 'path1' : $p->key;
			if ( ! preg_match( '/^path[0-9]+$/', $k ) ) {
					continue;
			}

			$path_pieces[ $k ] = $p->value;
		}

		if ( 0 === count( $path_pieces ) ) {
			return null;
		}

		ksort( $path_pieces );
		return join( $path_pieces );
	}

	/**
	 * Converts a path string into an array of Google Drive properties.
	 *
	 * From google drive api docs:
	 *     Maximum of 124 bytes size per property (including both key and value) string in UTF-8 encoding.
	 *     Maximum of 30 private properties per file from any one application.
	 *
	 * @param string $path The path to convert into Google Drive properties.
	 *
	 * @return array An array of Google Drive property objects representing the path.
	 */
	private function _path_to_properties( $path ) {
		$chunks     = str_split( $path, 55 );
		$properties = array();
		$i          = 1;

		foreach ( $chunks as $chunk ) {
			$p            = new \W3TCG_Google_Service_Drive_Property();
			$p->key       = 'path' . $i;
			$p->value     = $chunk;
			$properties[] = $p;

			++$i;
		}

		return $properties;
	}

	/**
	 * Uploads a chunk of files to Google Drive.
	 *
	 * @param array $files               Array of file descriptors to upload in the current chunk.
	 * @param array $results             Reference to an array where the upload results will be stored.
	 * @param bool  $force_rewrite       Whether to forcefully overwrite existing files on Google Drive.
	 * @param int   $timeout_time        Optional timeout time in seconds for the upload operation.
	 * @param bool  $allow_refresh_token Whether to allow refreshing the access token if necessary.
	 *
	 * @return string One of the following: 'success', 'timeout', 'refresh_required', or 'with_errors'.
	 *
	 * @throws \W3TCG_Google_Auth_Exception If the file update/insert fails.
	 */
	private function _upload_chunk( $files, &$results, $force_rewrite, $timeout_time, $allow_refresh_token ) {
		list( $result, $listed_files ) = $this->list_files_chunk( $files, $allow_refresh_token, $timeout_time );
		if ( 'success' !== $result ) {
			return $result;
		}

		$files_by_path = array();

		foreach ( $listed_files as $existing_file ) {
			$path = $this->_properties_to_path( $existing_file );
			if ( $path ) {
				$files_by_path[ $path ] = $existing_file;
			}
		}

		// check update date and upload.
		foreach ( $files as $file_descriptor ) {
			$remote_path = $file_descriptor['remote_path'];

			// process at least one item before timeout so that progress goes on.
			if ( ! empty( $results ) ) {
				if ( ! is_null( $timeout_time ) && time() > $timeout_time ) {
					return 'timeout';
				}
			}

			list( $parent_id, $title ) = $this->remote_path_to_title( $file_descriptor['remote_path'] );
			$properties                = $this->_path_to_properties( $remote_path );
			if ( isset( $file_descriptor['content'] ) ) {
				// when content specified - just upload.
				$content = $file_descriptor['content'];
			} else {
				$local_path = $file_descriptor['local_path'];
				if ( ! file_exists( $local_path ) ) {
					$results[] = $this->_get_result(
						$local_path,
						$remote_path,
						W3TC_CDN_RESULT_ERROR,
						'Source file not found.',
						$file_descriptor
					);
					continue;
				}

				$mtime = @filemtime( $local_path );

				$p            = new \W3TCG_Google_Service_Drive_Property();
				$p->key       = 'mtime';
				$p->value     = $mtime;
				$properties[] = $p;

				if ( ! $force_rewrite && isset( $files_by_path[ $remote_path ] ) ) {
					$existing_file  = $files_by_path[ $remote_path ];
					$existing_size  = $existing_file->fileSize; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					$existing_mtime = 0;
					if ( is_array( $existing_file->properties ) ) {
						foreach ( $existing_file->properties as $p ) {
							if ( 'mtime' === $p->key ) {
								$existing_mtime = $p->value;
							}
						}
					}

					$size = @filesize( $local_path );
					if ( $mtime === $existing_mtime && $size === $existing_size ) {
						$results[] = $this->_get_result(
							$file_descriptor['local_path'],
							$remote_path,
							W3TC_CDN_RESULT_OK,
							'File up-to-date.',
							$file_descriptor
						);
						continue;
					}
				}

				$content = file_get_contents( $local_path );
			}

			$file = new \W3TCG_Google_Service_Drive_DriveFile();
			$file->setTitle( $title );
			$file->setProperties( $properties );

			$parent = new \W3TCG_Google_Service_Drive_ParentReference();
			$parent->setId( $parent_id );
			$file->setParents( array( $parent ) );

			try {
				try {
					// update file if there's one already or insert.
					if ( isset( $files_by_path[ $remote_path ] ) ) {
						$existing_file = $files_by_path[ $remote_path ];

						$created_file = $this->_service->files->update(
							$existing_file->id,
							$file,
							array(
								'data'       => $content,
								'uploadType' => 'media',
							)
						);
					} else {
						$created_file = $this->_service->files->insert(
							$file,
							array(
								'data'       => $content,
								'uploadType' => 'media',
							)
						);

						$permission = new \W3TCG_Google_Service_Drive_Permission();
						$permission->setValue( '' );
						$permission->setType( 'anyone' );
						$permission->setRole( 'reader' );

						$this->_service->permissions->insert( $created_file->id, $permission );
					}
				} catch ( \W3TCG_Google_Auth_Exception $e ) {
					if ( $allow_refresh_token ) {
						return 'refresh_required';
					}

					throw $e;
				}

				$results[] = $this->_get_result(
					$file_descriptor['local_path'],
					$remote_path,
					W3TC_CDN_RESULT_OK,
					'OK',
					$file_descriptor
				);
				$this->path_set_id( $remote_path, $created_file->id );
			} catch ( \W3TCG_Google_Service_Exception $e ) {
				$errors  = $e->getErrors();
				$details = '';
				if ( count( $errors ) >= 1 ) {
					$details = wp_json_encode( $errors );
				}

				delete_transient( 'w3tc_cdn_google_drive_folder_ids' );

				$results[] = $this->_get_result(
					$file_descriptor['local_path'],
					$remote_path,
					W3TC_CDN_RESULT_ERROR,
					'Failed to upload file ' . $remote_path . ' ' . $details,
					$file_descriptor
				);
				$result    = 'with_errors';
				continue;
			} catch ( \Exception $e ) {
				delete_transient( 'w3tc_cdn_google_drive_folder_ids' );

				$results[] = $this->_get_result(
					$file_descriptor['local_path'],
					$remote_path,
					W3TC_CDN_RESULT_ERROR,
					'Failed to upload file ' . $remote_path,
					$file_descriptor
				);
				$result    = 'with_errors';
				continue;
			}
		}

		return $result;
	}

	/**
	 * Deletes specified files from the Google Drive.
	 *
	 * This method processes the deletion of multiple files from Google Drive in chunks. It handles token refresh
	 * if necessary and updates the result of the deletion process.
	 *
	 * @param array $files   The list of file paths to be deleted.
	 * @param array $results The array to collect results of each file deletion.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function delete( $files, &$results ) {
		$allow_refresh_token = true;
		$result              = true;

		$files_chunks = array_chunk( $files, 20 );
		foreach ( $files_chunks as $files_chunk ) {
			$r = $this->_delete_chunk( $files_chunk, $results, $allow_refresh_token );
			if ( 'refresh_required' === $r ) {
				$allow_refresh_token = false;
				$this->_refresh_token();

				$r = $this->_delete_chunk( $files_chunk, $results, $allow_refresh_token );
			}

			if ( 'success' !== $r ) {
				$result = false;
			}
		}

		return $result;
	}

	/**
	 * Deletes a chunk of files from Google Drive.
	 *
	 * This method handles the deletion of a chunk of files, processes the API response, and updates the results array.
	 *
	 * @param array $files               The chunk of file paths to delete.
	 * @param array $results             The array to collect results of each file deletion.
	 * @param bool  $allow_refresh_token Flag to allow refreshing the token if necessary.
	 *
	 * @return string One of the following: 'success', 'with_errors', or 'refresh_required'.
	 */
	private function _delete_chunk( $files, &$results, $allow_refresh_token ) {
		list( $result, $listed_files ) = $this->list_files_chunk( $files, $allow_refresh_token );
		if ( 'success' !== $result ) {
			return $result;
		}

		foreach ( $listed_files->items as $item ) {
			try {
				$this->_service->files->delete( $item->id );

				$results[] = $this->_get_result(
					$item->title,
					$item->title,
					W3TC_CDN_RESULT_OK,
					'OK'
				);
			} catch ( \Exception $e ) {
				$results[] = $this->_get_result(
					'',
					'',
					W3TC_CDN_RESULT_ERROR,
					'Failed to delete file ' . $item->title
				);
				$result    = 'with_errors';
				continue;
			}
		}

		return $result;
	}

	/**
	 * Lists a chunk of files based on the provided file descriptors.
	 *
	 * This method lists files matching the specified descriptors, checking if the refresh token is required or if a
	 * timeout occurred.
	 *
	 * @param array    $files               The file descriptors to list.
	 * @param bool     $allow_refresh_token Flag to allow refreshing the token if necessary.
	 * @param int|null $timeout_time        The timeout time, if any.
	 *
	 * @return array Array containing the result status and the listed files.
	 *
	 * @throws \W3TCG_Google_Auth_Exception Throws an exception if authentication fails or the listFiles call fails.
	 */
	private function list_files_chunk( $files, $allow_refresh_token, $timeout_time = null ) {
		$titles_filter = array();

		try {
			foreach ( $files as $file_descriptor ) {
				list( $parent_id, $title ) = $this->remote_path_to_title( $file_descriptor['remote_path'] );
				$titles_filter[]           = '("' . $parent_id . '" in parents and title = "' . $title . '")';
				if ( ! is_null( $timeout_time ) && time() > $timeout_time ) {
					return array( 'timeout', array() );
				}
			}
		} catch ( \W3TCG_Google_Auth_Exception $e ) {
			if ( $allow_refresh_token ) {
				return array( 'refresh_required', array() );
			}

			throw $e;
		} catch ( \Exception $e ) {
			return array( 'with_errors', array() );
		}

		// find files.
		try {
			try {
				$listed_files = $this->_service->files->listFiles(
					array( 'q' => '(' . join( ' or ', $titles_filter ) . ') and trashed = false' )
				);
			} catch ( \W3TCG_Google_Auth_Exception $e ) {
				if ( $allow_refresh_token ) {
					return array( 'refresh_required', array() );
				}

				throw $e;
			}
		} catch ( \Exception $e ) {
			return array( 'with_errors', array() );
		}

		return array( 'success', $listed_files );
	}

	/**
	 * Converts a remote file path to its title and parent ID.
	 *
	 * This method extracts the title and parent folder ID from a remote file path.
	 *
	 * @param string $remote_path The remote file path.
	 *
	 * @return array An array containing the parent ID and file title.
	 */
	private function remote_path_to_title( $remote_path ) {
		$title = substr( $remote_path, 1 );
		$pos   = strrpos( $remote_path, '/' );
		if ( false === $pos ) {
			$path  = '';
			$title = $remote_path;
		} else {
			$path  = substr( $remote_path, 0, $pos );
			$title = substr( $remote_path, $pos + 1 );
		}

		$title     = str_replace( '"', "'", $title );
		$parent_id = $this->path_to_parent_id( $this->_root_folder_id, $path );

		return array( $parent_id, $title );
	}

	/**
	 * Resolves the parent folder ID for a given path.
	 *
	 * This method recursively resolves the parent folder ID for a given path within the Google Drive hierarchy.
	 *
	 * @param string $root_id The root folder ID.
	 * @param string $path    The folder path to resolve.
	 *
	 * @return string The resolved parent folder ID.
	 */
	private function path_to_parent_id( $root_id, $path ) {
		if ( empty( $path ) ) {
			return $root_id;
		}

		$path = ltrim( $path, '/' );
		$pos  = strpos( $path, '/' );
		if ( false === $pos ) {
			$top_folder     = $path;
			$remaining_path = '';
		} else {
			$top_folder     = substr( $path, 0, $pos );
			$remaining_path = substr( $path, $pos + 1 );
		}

		$new_root_id = $this->parent_id_resolve_step( $root_id, $top_folder );
		return $this->path_to_parent_id( $new_root_id, $remaining_path );
	}

	/**
	 * Resolves the folder ID for a given folder within a parent folder.
	 *
	 * This method checks if the folder exists, creates it if necessary, and resolves its ID.
	 *
	 * @param string $root_id The parent folder ID.
	 * @param string $folder  The folder name.
	 *
	 * @return string The resolved folder ID.
	 */
	private function parent_id_resolve_step( $root_id, $folder ) {
		// decode top folder.
		$ids_string = get_transient( 'w3tc_cdn_google_drive_folder_ids' );
		$ids        = @unserialize( $ids_string );

		if ( isset( $ids[ $root_id . '_' . $folder ] ) ) {
			return $ids[ $root_id . '_' . $folder ];
		}

		// find folder.
		$items = $this->_service->files->listFiles(
			array(
				'q' => '"' . $root_id . '" in parents and title = "' . $folder . '" and mimeType = "application/vnd.google-apps.folder" and trashed = false',
			)
		);

		if ( count( $items ) > 0 ) {
			$id = $items[0]->id;
		} else {
			// create folder.
			$file = new \W3TCG_Google_Service_Drive_DriveFile(
				array(
					'title'    => $folder,
					'mimeType' => 'application/vnd.google-apps.folder',
				)
			);

			$parent = new \W3TCG_Google_Service_Drive_ParentReference();
			$parent->setId( $root_id );
			$file->setParents( array( $parent ) );

			$created_file = $this->_service->files->insert( $file );
			$id           = $created_file->id;

			$permission = new \W3TCG_Google_Service_Drive_Permission();
			$permission->setValue( '' );
			$permission->setType( 'anyone' );
			$permission->setRole( 'reader' );

			$this->_service->permissions->insert( $id, $permission );
		}

		if ( ! is_array( $ids ) ) {
			$ids = array();
		}

		$ids[ $root_id . '_' . $folder ] = $id;
		set_transient( 'w3tc_cdn_google_drive_folder_ids', serialize( $ids ) );

		return $id;
	}

	/**
	 * Runs a test by uploading and then deleting a test file on Google Drive.
	 *
	 * This method uploads a test file, and if successful, deletes it, returning an error if either operation fails.
	 *
	 * @param string $error The variable to store error messages if any operation fails.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function test( &$error ) {
		$test_content = '' . wp_rand();

		$file    = array(
			'local_path'  => 'n/a',
			'remote_path' => '/folder/test.txt',
			'content'     => $test_content,
		);
		$results = array();

		if ( ! $this->upload( array( $file ), $results ) ) {
			$error = sprintf( 'Unable to upload file %s', $file['remote_path'] );
			return false;
		}
		if ( ! $this->delete( array( $file ), $results ) ) {
			$error = sprintf( 'Unable to delete file %s', $file['remote_path'] );
			return false;
		}

		return true;
	}

	/**
	 * Returns the domains supported by the Google Drive CDN.
	 *
	 * This method returns an empty array since the current implementation does not support specific domains.
	 *
	 * @return array An empty array.
	 */
	public function get_domains() {
		return array();
	}

	/**
	 * Returns the type of headers supported by the CDN.
	 *
	 * This method returns a constant indicating that no custom headers are supported.
	 *
	 * @return string One of the constants indicating header support (e.g., `W3TC_CDN_HEADER_NONE`).
	 */
	public function headers_support() {
		return W3TC_CDN_HEADER_NONE;
	}


	/**
	 * Purges all cached files from the Google Drive CDN.
	 *
	 * This method does not support purging all cached files and will always return false.
	 *
	 * @param array $results The array to collect results of the purging operation.
	 *
	 * @return bool Always returns false.
	 */
	public function purge_all( &$results ) {
		return false;
	}

	/**
	 * Sets the remote ID for a given file path.
	 *
	 * This method stores the remote ID associated with a file path in the database.
	 *
	 * @param string $path The local file path.
	 * @param string $id   The remote file ID.
	 *
	 * @return void
	 */
	private function path_set_id( $path, $id ) {
		global $wpdb;
		$md5 = md5( $path );
		if ( ! $id ) {
			$sql = "INSERT INTO $this->_tablename_pathmap 
				(path, path_hash, remote_id)
				VALUES (%s, %s, NULL)
				ON DUPLICATE KEY UPDATE remote_id = NULL";
			$wpdb->query( $wpdb->prepare( $sql, $path, $md5 ) );
		} else {
			$sql = "INSERT INTO $this->_tablename_pathmap
				(path, path_hash, remote_id)
				VALUES (%s, %s, %s)
				ON DUPLICATE KEY UPDATE remote_id = %s";
			$wpdb->query( $wpdb->prepare( $sql, $path, $md5, $id, $id ) );
		}
	}

	/**
	 * Gets the remote ID associated with a file path.
	 *
	 * This method retrieves the remote ID for a file path, either from the database or by querying Google Drive.
	 *
	 * @param string $path             The local file path.
	 * @param bool   $allow_refresh_token Flag to allow refreshing the token if necessary.
	 *
	 * @return string|null The remote file ID or null if not found.
	 *
	 * @throws \W3TCG_Google_Auth_Exception Throws an exception if authentication fails or the listFiles call fails.
	 */
	private function path_get_id( $path, $allow_refresh_token = true ) {
		global $wpdb;
		$md5     = md5( $path );
		$sql     = "SELECT remote_id FROM $this->_tablename_pathmap WHERE path_hash = %s";
		$query   = $wpdb->prepare( $sql, $md5 );
		$results = $wpdb->get_results( $query );
		if ( count( $results ) > 0 ) {
			return $results[0]->remote_id;
		}

		$props = $this->_path_to_properties( $path );
		$q     = 'trashed = false';
		foreach ( $props as $prop ) {
				$key   = $prop->key;
				$value = str_replace( "'", "\\'", $prop->value );
				$q    .= " and properties has { key='$key' and value='$value' and visibility='PRIVATE' }";
		}

		try {
			$items = $this->_service->files->listFiles( array( 'q' => $q ) );
		} catch ( \W3TCG_Google_Auth_Exception $e ) {
			if ( $allow_refresh_token ) {
				$this->_refresh_token();
				return $this->path_get_id( $path, false );
			}

			throw $e;
		}

		$id = ( 0 === count( $items ) ) ? null : $items[0]->id;
		$this->path_set_id( $path, $id );

		return $id;
	}

	/**
	 * Formats a URL for accessing a file on Google Drive.
	 *
	 * This method returns a URL to access a file on Google Drive based on its remote ID.
	 *
	 * @param string $path             The local file path.
	 * @param bool   $allow_refresh_token Flag to allow refreshing the token if necessary.
	 *
	 * @return string|null The formatted URL or null if the ID could not be retrieved.
	 */
	public function format_url( $path, $allow_refresh_token = true ) {
		$id = $this->path_get_id( Util_Environment::remove_query( $path ) );
		if ( is_null( $id ) ) {
			return null;
		}

		return 'https://drive.google.com/uc?id=' . $id;
	}
}
