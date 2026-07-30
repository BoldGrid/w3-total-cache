<?php
/**
 * File: Cdn_Core.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Cdn_Core
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.DB.DirectDatabaseQuery
 */
class Cdn_Core {
	/**
	 * Config.
	 *
	 * @var Config
	 */
	private $_config = null;

	/**
	 * Debug.
	 *
	 * @var bool
	 */
	private $debug;

	/**
	 * Constructor method for initializing the class.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();
		$this->debug   = $this->_config->get_boolean( 'cdn.debug' );
	}

	/**
	 * Adds a file to the CDN queue.
	 *
	 * @param string $local_path  Local file path.
	 * @param string $remote_path Remote file path.
	 * @param int    $command     Command type (upload, delete, etc.).
	 * @param string $last_error  Last error message, if any.
	 *
	 * @return bool True if the file was successfully added or already exists.
	 */
	public function queue_add( $local_path, $remote_path, $command, $last_error ) {
		global $wpdb;

		$table     = $wpdb->base_prefix . W3TC_CDN_TABLE_QUEUE;
		$w3tc_rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, command FROM ' . $table . ' WHERE local_path = %s AND remote_path = %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$local_path,
				$remote_path
			)
		);

		$already_exists = false;

		foreach ( $w3tc_rows as $row ) {
			if ( $row->command !== $command ) {
				$wpdb->query(
					$wpdb->prepare(
						'DELETE FROM ' . $table . ' WHERE id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
						$row->id
					)
				);
			} else {
				$already_exists = true;
			}
		}

		if ( $already_exists ) {
			return true;
		}

		// Insert if not yet there.
		return $wpdb->query(
			$wpdb->prepare(
				'INSERT INTO ' . $table . ' (local_path, remote_path, command, last_error, date) VALUES (%s, %s, %d, %s, NOW())', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$local_path,
				$remote_path,
				$command,
				$last_error
			)
		);
	}

	/**
	 * Retrieves a list of files to be uploaded based on a local file path.
	 *
	 * @param string $w3tc_file Local file path.
	 *
	 * @return array Array of file descriptors for upload.
	 */
	public function get_files_for_upload( $w3tc_file ) {
		$files       = array();
		$upload_info = Util_Http::upload_info();

		if ( $upload_info ) {
			$w3tc_file   = $this->normalize_attachment_file( $w3tc_file );
			$local_file  = $upload_info['basedir'] . '/' . $w3tc_file;
			$parsed      = wp_parse_url( rtrim( $upload_info['baseurl'], '/' ) . '/' . $w3tc_file );
			$local_uri   = $parsed['path'];
			$remote_uri  = $this->uri_to_cdn_uri( $local_uri );
			$remote_file = ltrim( $remote_uri, '/' );
			$files[]     = $this->build_file_descriptor( $local_file, $remote_file );
		}

		return $files;
	}

	/**
	 * Retrieves a list of size-specific files for upload based on the attachment file and its sizes.
	 *
	 * @param string $attached_file Path to the attached file.
	 * @param array  $sizes         Array of sizes for the attached file.
	 *
	 * @return array Array of file descriptors for each size.
	 */
	public function _get_sizes_files( $attached_file, $sizes ) {
		$files    = array();
		$base_dir = Util_File::dirname( $attached_file );

		foreach ( (array) $sizes as $size ) {
			if ( isset( $size['file'] ) ) {
				if ( $base_dir ) {
					$w3tc_file = $base_dir . '/' . $size['file'];
				} else {
					$w3tc_file = $size['file'];
				}

				$files = array_merge( $files, $this->get_files_for_upload( $w3tc_file ) );
			}
		}

		return $files;
	}

	/**
	 * Retrieves all files associated with an attachment, including its sizes and metadata.
	 *
	 * @param array $metadata Metadata for the attachment.
	 *
	 * @return array Array of file descriptors for the attachment and its sizes.
	 */
	public function get_metadata_files( $metadata ) {
		$files = array();

		if ( isset( $metadata['file'] ) && isset( $metadata['sizes'] ) ) {
			$files = array_merge( $files, $this->_get_sizes_files( $metadata['file'], $metadata['sizes'] ) );
		}

		return $files;
	}

	/**
	 * Retrieves a list of files associated with a given attachment.
	 *
	 * @param int $attachment_id The ID of the attachment.
	 *
	 * @return array Array of file descriptors for the attachment and its sizes.
	 */
	public function get_attachment_files( $attachment_id ) {
		$files = array();

		// Get attached file.
		$attached_file = get_post_meta( $attachment_id, '_wp_attached_file', true );

		if ( ! empty( $attached_file ) ) {
			$files = array_merge( $files, $this->get_files_for_upload( $attached_file ) );

			// Get backup sizes files.
			$attachment_backup_sizes = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );

			if ( is_array( $attachment_backup_sizes ) ) {
				$files = array_merge( $files, $this->_get_sizes_files( $attached_file, $attachment_backup_sizes ) );
			}
		}

		// Get files from metadata.
		$attachment_metadata = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );

		if ( is_array( $attachment_metadata ) ) {
			$files = array_merge( $files, $this->get_metadata_files( $attachment_metadata ) );
		}

		return $files;
	}

	/**
	 * Uploads files to the CDN.
	 *
	 * @param array $files         List of files to upload.
	 * @param bool  $queue_failed  Whether to queue failed uploads.
	 * @param array $results       Array to store the results of the upload.
	 * @param int   $timeout_time  Optional timeout time for the upload.
	 *
	 * @return bool True if the upload was successful, false otherwise.
	 */
	public function upload( $files, $queue_failed, &$results, $timeout_time = null ) {
		if ( $this->debug ) {
			Util_Debug::log( 'cdn', 'upload: ' . wp_json_encode( $files, JSON_PRETTY_PRINT ) );
		}

		$cdn           = $this->get_cdn();
		$force_rewrite = $this->_config->get_boolean( 'cdn.force.rewrite' );

		@set_time_limit( $this->_config->get_integer( 'timelimit.cdn_upload' ) ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged

		$w3tc_engine = $this->_config->get_string( 'cdn.engine' );
		$return      = $cdn->upload( $files, $results, $force_rewrite, $timeout_time );

		if ( ! $return && $queue_failed ) {
			foreach ( $results as $w3tc_result ) {
				if ( W3TC_CDN_RESULT_OK !== $w3tc_result['result'] ) {
					$this->queue_add( $w3tc_result['local_path'], $w3tc_result['remote_path'], W3TC_CDN_COMMAND_UPLOAD, $w3tc_result['error'] );
				}
			}
		}

		return $return;
	}

	/**
	 * Deletes files from the CDN.
	 *
	 * @param array $files        List of files to delete.
	 * @param bool  $queue_failed Whether to queue failed deletions.
	 * @param array $results      Array to store the results of the deletion.
	 *
	 * @return bool True if the deletion was successful, false otherwise.
	 */
	public function delete( $files, $queue_failed, &$results ) {
		$cdn = $this->get_cdn();

		@set_time_limit( $this->_config->get_integer( 'timelimit.cdn_delete' ) ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged

		$return = $cdn->delete( $files, $results );
		if ( $this->debug ) {
			Util_Debug::log( 'cdn', 'delete: ' . wp_json_encode( $files, JSON_PRETTY_PRINT ) );
		}

		if ( ! $return && $queue_failed ) {
			foreach ( $results as $w3tc_result ) {
				if ( W3TC_CDN_RESULT_OK !== $w3tc_result['result'] ) {
					$this->queue_add( $w3tc_result['local_path'], $w3tc_result['remote_path'], W3TC_CDN_COMMAND_DELETE, $w3tc_result['error'] );
				}
			}
		}

		return $return;
	}

	/**
	 * Purges files from the CDN.
	 *
	 * @param array $files   List of files to purge.
	 * @param array $results Array to store the results of the purge.
	 *
	 * @return bool True if the purge was successful, false otherwise.
	 */
	public function purge( $files, &$results ) {
		if ( $this->debug ) {
			Util_Debug::log( 'cdn', 'purge: ' . wp_json_encode( $files, JSON_PRETTY_PRINT ) );
		}

		/**
		 * Purge varnish servers before mirror purging.
		 */
		if ( Cdn_Util::is_engine_mirror( $this->_config->get_string( 'cdn.engine' ) ) && $this->_config->get_boolean( 'varnish.enabled' ) ) {
			$varnish = Dispatcher::component( 'Varnish_Flush' );

			foreach ( $files as $w3tc_file ) {
				$remote_path = $w3tc_file['remote_path'];
				$varnish->flush_url( network_site_url( $remote_path ) );
			}
		}

		/**
		 * Purge CDN.
		 */
		$cdn = $this->get_cdn();

		@set_time_limit( $this->_config->get_integer( 'timelimit.cdn_purge' ) ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged

		$return = $cdn->purge( $files, $results );

		if ( ! $return ) {
			foreach ( $results as $w3tc_result ) {
				if ( W3TC_CDN_RESULT_OK !== $w3tc_result['result'] ) {
					$this->queue_add( $w3tc_result['local_path'], $w3tc_result['remote_path'], W3TC_CDN_COMMAND_PURGE, $w3tc_result['error'] );
				}
			}
		}

		return $return;
	}

	/**
	 * Purges all files from the CDN.
	 *
	 * @param array $results Array to store the results of the purge.
	 *
	 * @return bool True if the purge was successful, false otherwise.
	 */
	public function purge_all( &$results ) {
		$cdn = $this->get_cdn();

		@set_time_limit( $this->_config->get_integer( 'timelimit.cdn_purge' ) ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged

		return $cdn->purge_all( $results );
	}

	/**
	 * Adds a URL to the CDN upload queue.
	 *
	 * @param string $w3tc_url URL to be queued for upload.
	 *
	 * @return void
	 */
	public function queue_upload_url( $w3tc_url ) {
		$docroot_filename = Util_Environment::url_to_docroot_filename( $w3tc_url );
		if ( is_null( $docroot_filename ) ) {
			return;
		}

		$filename = Util_Environment::docroot_to_full_filename( $docroot_filename );

		$w3tc_a          = \wp_parse_url( $w3tc_url );
		$remote_filename = $this->uri_to_cdn_uri( $w3tc_a['path'] );

		$this->queue_add( $filename, $remote_filename, W3TC_CDN_COMMAND_UPLOAD, 'Pending' );
	}

	/**
	 * Normalizes the attachment file path.
	 *
	 * @param string $w3tc_file The file path to normalize.
	 *
	 * @return string The normalized file path.
	 */
	public function normalize_attachment_file( $w3tc_file ) {
		$upload_info = Util_Http::upload_info();

		if ( $upload_info ) {
			$w3tc_file = ltrim( str_replace( $upload_info['basedir'], '', $w3tc_file ), '/\\' );
			$matches   = null;

			if ( preg_match( '~(\d{4}/\d{2}/)?[^/]+$~', $w3tc_file, $matches ) ) {
				$w3tc_file = $matches[0];
			}
		}

		return $w3tc_file;
	}

	/**
	 * Retrieves the CDN configuration based on the specified CDN engine.
	 *
	 * This method checks the current configuration settings and returns an array
	 * containing the appropriate configuration for the specified CDN engine.
	 * The configuration details are dependent on the engine selected, such as
	 * Cloudflare or S3, and include settings such as API keys, domain,
	 * SSL configurations, and compression options. The method caches the configuration
	 * after the first retrieval for subsequent calls.
	 *
	 * @return array|null The CDN configuration array or null if not configured.
	 */
	public function get_cdn() {
		static $cdn = null;

		if ( is_null( $cdn ) ) {
			$w3tc_c      = $this->_config;
			$w3tc_engine = $w3tc_c->get_string( 'cdn.engine' );
			$compression = ( $w3tc_c->get_boolean( 'browsercache.enabled' ) && $w3tc_c->get_boolean( 'browsercache.html.compression' ) );

			switch ( $w3tc_engine ) {
				case 'azure':
					$engine_config = array(
						'user'        => $w3tc_c->get_string( 'cdn.azure.user' ),
						'key'         => $w3tc_c->get_string( 'cdn.azure.key' ),
						'container'   => $w3tc_c->get_string( 'cdn.azure.container' ),
						'cname'       => $w3tc_c->get_array( 'cdn.azure.cname' ),
						'ssl'         => $w3tc_c->get_string( 'cdn.azure.ssl' ),
						'compression' => false,
					);
					break;

				case 'azuremi':
					$engine_config = array(
						'user'        => $w3tc_c->get_string( 'cdn.azuremi.user' ),
						'clientid'    => $w3tc_c->get_string( 'cdn.azuremi.clientid' ),
						'container'   => $w3tc_c->get_string( 'cdn.azuremi.container' ),
						'cname'       => $w3tc_c->get_array( 'cdn.azuremi.cname' ),
						'ssl'         => $w3tc_c->get_string( 'cdn.azuremi.ssl' ),
						'compression' => false,
					);
					break;

				case 'cf':
					$engine_config = array(
						'key'             => $w3tc_c->get_string( 'cdn.cf.key' ),
						'secret'          => $w3tc_c->get_string( 'cdn.cf.secret' ),
						'bucket'          => $w3tc_c->get_string( 'cdn.cf.bucket' ),
						'bucket_location' => self::get_region_id( $w3tc_c->get_string( 'cdn.cf.bucket.location' ) ),
						'bucket_loc_id'   => $w3tc_c->get_string( 'cdn.cf.bucket.location' ),
						'id'              => $w3tc_c->get_string( 'cdn.cf.id' ),
						'cname'           => $w3tc_c->get_array( 'cdn.cf.cname' ),
						'ssl'             => $w3tc_c->get_string( 'cdn.cf.ssl' ),
						'public_objects'  => $w3tc_c->get_string( 'cdn.cf.public_objects' ),
						'compression'     => $compression,
					);
					break;

				case 'cf2':
					$engine_config = array(
						'key'         => $w3tc_c->get_string( 'cdn.cf2.key' ),
						'secret'      => $w3tc_c->get_string( 'cdn.cf2.secret' ),
						'id'          => $w3tc_c->get_string( 'cdn.cf2.id' ),
						'cname'       => $w3tc_c->get_array( 'cdn.cf2.cname' ),
						'ssl'         => $w3tc_c->get_string( 'cdn.cf2.ssl' ),
						'compression' => false,
					);
					break;

				case 'ftp':
					$engine_config = array(
						'host'        => $w3tc_c->get_string( 'cdn.ftp.host' ),
						'type'        => $w3tc_c->get_string( 'cdn.ftp.type' ),
						'user'        => $w3tc_c->get_string( 'cdn.ftp.user' ),
						'pass'        => $w3tc_c->get_string( 'cdn.ftp.pass' ),
						'path'        => $w3tc_c->get_string( 'cdn.ftp.path' ),
						'pasv'        => $w3tc_c->get_boolean( 'cdn.ftp.pasv' ),
						'domain'      => $w3tc_c->get_array( 'cdn.ftp.domain' ),
						'ssl'         => $w3tc_c->get_string( 'cdn.ftp.ssl' ),
						'compression' => false,
						'docroot'     => Util_Environment::document_root(),
					);
					break;

				case 'google_drive':
					$state = Dispatcher::config_state();

					$engine_config = array(
						'client_id'                 => $w3tc_c->get_string( 'cdn.google_drive.client_id' ),
						'access_token'              => $state->get_string( 'cdn.google_drive.access_token' ),
						'refresh_token'             => $w3tc_c->get_string( 'cdn.google_drive.refresh_token' ),
						'root_url'                  => $w3tc_c->get_string( 'cdn.google_drive.folder.url' ),
						'root_folder_id'            => $w3tc_c->get_string( 'cdn.google_drive.folder.id' ),
						'new_access_token_callback' => array( $this, 'on_google_drive_new_access_token' ),
					);
					break;

				case 'mirror':
					$engine_config = array(
						'domain'      => $w3tc_c->get_array( 'cdn.mirror.domain' ),
						'ssl'         => $w3tc_c->get_string( 'cdn.mirror.ssl' ),
						'compression' => false,
					);
					break;

				case 'rackspace_cdn':
					$state = Dispatcher::config_state();

					$engine_config = array(
						'user_name'                 => $w3tc_c->get_string( 'cdn.rackspace_cdn.user_name' ),
						'api_key'                   => $w3tc_c->get_string( 'cdn.rackspace_cdn.api_key' ),
						'region'                    => $w3tc_c->get_string( 'cdn.rackspace_cdn.region' ),
						'service_access_url'        => $w3tc_c->get_string( 'cdn.rackspace_cdn.service.access_url' ),
						'service_id'                => $w3tc_c->get_string( 'cdn.rackspace_cdn.service.id' ),
						'service_protocol'          => $w3tc_c->get_string( 'cdn.rackspace_cdn.service.protocol' ),
						'domains'                   => $w3tc_c->get_array( 'cdn.rackspace_cdn.domains' ),
						'access_state'              => $state->get_string( 'cdn.rackspace_cdn.access_state' ),
						'new_access_state_callback' => array( $this, 'on_rackspace_cdn_new_access_state' ),

					);
					break;
				case 'rscf':
					$state = Dispatcher::config_state();

					$engine_config = array(
						'user_name'                 => $w3tc_c->get_string( 'cdn.rscf.user' ),
						'api_key'                   => $w3tc_c->get_string( 'cdn.rscf.key' ),
						'region'                    => $w3tc_c->get_string( 'cdn.rscf.location' ),
						'container'                 => $w3tc_c->get_string( 'cdn.rscf.container' ),
						'cname'                     => $w3tc_c->get_array( 'cdn.rscf.cname' ),
						'ssl'                       => $w3tc_c->get_string( 'cdn.rscf.ssl' ),
						'compression'               => false,
						'access_state'              => $state->get_string( 'cdn.rackspace_cf.access_state' ),
						'new_access_state_callback' => array( $this, 'on_rackspace_cf_new_access_state' ),

					);
					break;

				case 's3':
					$engine_config = array(
						'key'             => $w3tc_c->get_string( 'cdn.s3.key' ),
						'secret'          => $w3tc_c->get_string( 'cdn.s3.secret' ),
						'bucket'          => $w3tc_c->get_string( 'cdn.s3.bucket' ),
						'bucket_location' => self::get_region_id( $w3tc_c->get_string( 'cdn.s3.bucket.location' ) ),
						'bucket_loc_id'   => $w3tc_c->get_string( 'cdn.s3.bucket.location' ),
						'cname'           => $w3tc_c->get_array( 'cdn.s3.cname' ),
						'ssl'             => $w3tc_c->get_string( 'cdn.s3.ssl' ),
						'public_objects'  => $w3tc_c->get_string( 'cdn.s3.public_objects' ),
						'compression'     => $compression,
					);
					break;

				case 's3_compatible':
					$engine_config = array(
						'key'         => $w3tc_c->get_string( 'cdn.s3.key' ),
						'secret'      => $w3tc_c->get_string( 'cdn.s3.secret' ),
						'bucket'      => $w3tc_c->get_string( 'cdn.s3.bucket' ),
						'cname'       => $w3tc_c->get_array( 'cdn.s3.cname' ),
						'ssl'         => $w3tc_c->get_string( 'cdn.s3.ssl' ),
						'compression' => $compression,
						'api_host'    => $w3tc_c->get_string( 'cdn.s3_compatible.api_host' ),
					);
					break;

				case 'bunnycdn':
					$engine_config = array(
						'account_api_key' => $w3tc_c->get_string( 'cdn.bunnycdn.account_api_key' ),
						'storage_api_key' => $w3tc_c->get_string( 'cdn.bunnycdn.storage_api_key' ),
						'stream_api_key'  => $w3tc_c->get_string( 'cdn.bunnycdn.stream_api_key' ),
						'pull_zone_id'    => $w3tc_c->get_integer( 'cdn.bunnycdn.pull_zone_id' ),
						'domain'          => $w3tc_c->get_string( 'cdn.bunnycdn.cdn_hostname' ),
					);
					break;

				default:
					$engine_config = array();
					break;
			}

			$engine_config = array_merge(
				$engine_config,
				array(
					'debug'   => $w3tc_c->get_boolean( 'cdn.debug' ),
					'headers' => apply_filters( 'w3tc_cdn_config_headers', array() ),
				)
			);

			$cdn = CdnEngine::instance( $w3tc_engine, $engine_config );
		}

		return $cdn;
	}

	/**
	 * Handles the storage of a new Google Drive access token in the configuration state.
	 *
	 * This method sets the provided Google Drive access token into the configuration state
	 * for later use, ensuring that the token is saved and accessible for operations related
	 * to Google Drive integration.
	 *
	 * @param string $w3tc_access_token The new access token for Google Drive.
	 *
	 * @return void
	 */
	public function on_google_drive_new_access_token( $w3tc_access_token ) {
		$state = Dispatcher::config_state();
		$state->set( 'cdn.google_drive.access_token', $w3tc_access_token );
		$state->save();
	}

	/**
	 * Handles the storage of a new Rackspace CDN access state in the configuration state.
	 *
	 * This method sets the provided access state into the configuration state for Rackspace CDN,
	 * ensuring that the state is saved and accessible for future operations related to Rackspace CDN.
	 *
	 * @param string $access_state The new access state for Rackspace CDN.
	 *
	 * @return void
	 */
	public function on_rackspace_cdn_new_access_state( $access_state ) {
		$state = Dispatcher::config_state();
		$state->set( 'cdn.rackspace_cdn.access_state', $access_state );
		$state->save();
	}

	/**
	 * Handles the storage of a new Rackspace Cloud Files access state in the configuration state.
	 *
	 * This method sets the provided access state into the configuration state for Rackspace Cloud Files,
	 * ensuring that the state is saved and accessible for future operations related to Rackspace Cloud Files.
	 *
	 * @param string $access_state The new access state for Rackspace Cloud Files.
	 *
	 * @return void
	 */
	public function on_rackspace_cf_new_access_state( $access_state ) {
		$state = Dispatcher::config_state();
		$state->set( 'cdn.rackspace_cf.access_state', $access_state );
		$state->save();
	}

	/**
	 * Converts a file path to its corresponding URI, removing multisite-specific path components.
	 *
	 * This method transforms a given file path to a URI by stripping off any multisite subsite path
	 * and ensuring the result is a valid URI format.
	 *
	 * @param string $w3tc_file The file path to convert.
	 *
	 * @return string The corresponding URI for the file.
	 */
	public function docroot_filename_to_uri( $w3tc_file ) {
		$w3tc_file = ltrim( $w3tc_file, '/' );

		// Translate multisite subsite uploads paths.
		return str_replace( basename( WP_CONTENT_DIR ) . '/blogs.dir/' . Util_Environment::blog_id() . '/', '', $w3tc_file );
	}

	/**
	 * Converts a file path to its absolute filesystem path under document root.
	 *
	 * Relative paths are resolved against {@see Util_Environment::document_root()}.
	 * Absolute paths are accepted only when they resolve inside the document root.
	 * Traversal (`..`) and paths outside the document root return an empty string.
	 *
	 * @since 2.10.0
	 *
	 * @param string $w3tc_file The file path to convert.
	 *
	 * @return string Absolute path when valid, empty string when rejected.
	 */
	public function docroot_filename_to_absolute_path( $w3tc_file ) {
		if ( ! \is_string( $w3tc_file ) || '' === $w3tc_file ) {
			return '';
		}

		if ( false !== \strpos( $w3tc_file, '..' ) ) {
			return '';
		}

		if ( '/' !== \DIRECTORY_SEPARATOR ) {
			$w3tc_file = \str_replace( '/', \DIRECTORY_SEPARATOR, $w3tc_file );
		}

		$document_root = Util_Environment::document_root();
		if ( ! \is_string( $document_root ) || '' === $document_root ) {
			return '';
		}

		$docroot_real = \realpath( $document_root );
		if ( false === $docroot_real ) {
			return '';
		}

		if ( \function_exists( 'path_is_absolute' ) && \path_is_absolute( $w3tc_file ) ) {
			$candidate = $w3tc_file;
		} else {
			$candidate = $docroot_real . \DIRECTORY_SEPARATOR . \ltrim( $w3tc_file, '/\\' );
		}

		$resolved = \realpath( $candidate );
		if ( false === $resolved ) {
			$resolved = Util_Environment::realpath( $candidate );
		}

		$docroot_norm   = Util_Environment::normalize_path( $docroot_real );
		$resolved_norm  = Util_Environment::normalize_path( $resolved );
		$docroot_prefix = $docroot_norm . '/';

		if ( $resolved_norm !== $docroot_norm && 0 !== \strpos( $resolved_norm, $docroot_prefix ) ) {
			return '';
		}

		return $resolved;
	}

	/**
	 * Converts a local URI to a corresponding CDN URI based on the environment and configuration.
	 *
	 * This method converts a local URI to a CDN URI by considering various conditions such as
	 * the use of WordPress multisite and specific CDN engine configurations.
	 *
	 * @param string $local_uri The local URI to convert.
	 *
	 * @return string The corresponding CDN URI.
	 */
	public function uri_to_cdn_uri( $local_uri ) {
		$local_uri  = ltrim( $local_uri, '/' );
		$remote_uri = $local_uri;

		if ( Util_Environment::is_wpmu() && defined( 'DOMAIN_MAPPING' ) && DOMAIN_MAPPING ) {
			$remote_uri = str_replace( site_url(), '', $local_uri );
		}

		$w3tc_engine = $this->_config->get_string( 'cdn.engine' );

		if ( Cdn_Util::is_engine_mirror( $w3tc_engine ) ) {
			if ( Util_Environment::is_wpmu() && strpos( $local_uri, 'files' ) === 0 ) {
				$upload_dir = Util_Environment::wp_upload_dir();
				$remote_uri = $this->abspath_to_relative_path( dirname( $upload_dir['basedir'] ) ) . '/' . $local_uri;
			}
		} elseif ( Util_Environment::is_wpmu() &&
			! Util_Environment::is_wpmu_subdomain() &&
			Util_Environment::is_using_master_config() &&
			Cdn_Util::is_engine_push( $w3tc_engine ) ) {
			/**
			 * In common config mode files are uploaded for network home url so mirror will not contain /subblog/ path in uri.
			 * Since upload process is not blog-specific and wp-content/plugins/../*.jpg files are common.
			 */
			$home         = trim( home_url( '', 'relative' ), '/' ) . '/';
			$network_home = trim( network_home_url( '', 'relative' ), '/' ) . '/';

			if ( $home !== $network_home && substr( $local_uri, 0, strlen( $home ) ) === $home ) {
				$remote_uri = $network_home . substr( $local_uri, strlen( $home ) );
			}
		}

		return apply_filters( 'w3tc_uri_cdn_uri', ltrim( $remote_uri, '/' ) );
	}

	/**
	 * Converts a local URL and path to a full CDN URL.
	 *
	 * This method combines a base CDN URL with the corresponding CDN path, resulting in a full URL
	 * that points to the resource on the CDN. The path is first converted to a CDN URI using
	 * the `uri_to_cdn_uri()` method.
	 *
	 * @param string $w3tc_url  The base URL to convert.
	 * @param string $path The relative path to the resource.
	 *
	 * @return string|null The full CDN URL, or null if the conversion fails.
	 */
	public function url_to_cdn_url( $w3tc_url, $path ) {
		$cdn         = $this->get_cdn();
		$remote_path = $this->uri_to_cdn_uri( $path );
		$new_url     = $cdn->format_url( $remote_path );

		if ( ! $new_url ) {
			return null;
		}

		$is_engine_mirror = Cdn_Util::is_engine_mirror( $this->_config->get_string( 'cdn.engine' ) );
		$new_url          = apply_filters( 'w3tc_cdn_url', $new_url, $w3tc_url, $is_engine_mirror );

		return $new_url;
	}

	/**
	 * Converts an absolute filesystem path to a relative path based on the document root.
	 *
	 * This method removes the document root from the provided path, returning a relative path
	 * that can be used in a URL or CDN context.
	 *
	 * @param string $path The absolute filesystem path to convert.
	 *
	 * @return string The corresponding relative path.
	 */
	public function abspath_to_relative_path( $path ) {
		return str_replace( Util_Environment::document_root(), '', $path );
	}

	/**
	 * Converts a relative path to a full URL based on the site's home domain.
	 *
	 * This method constructs a full URL from a relative path by appending the path to the
	 * site's home domain root URL.
	 *
	 * @param string $path The relative path to convert.
	 *
	 * @return string The corresponding full URL.
	 */
	public function relative_path_to_url( $path ) {
		return rtrim( Util_Environment::home_domain_root_url(), '/' ) . '/' .
			$this->docroot_filename_to_uri( ltrim( $path, '/' ) );
	}

	/**
	 * Builds a file descriptor array with local and remote paths, as well as the original URL.
	 *
	 * This method creates an array that describes a file, including its local and remote paths
	 * and the original URL for the file. The array is filtered through the `w3tc_build_cdn_file_array` filter.
	 *
	 * @param string $local_path  The local path of the file.
	 * @param string $remote_path The remote path of the file.
	 *
	 * @return array The file descriptor array with local, remote, and URL information.
	 */
	public function build_file_descriptor( $local_path, $remote_path ) {
		$w3tc_file = array(
			'local_path'   => $local_path,
			'remote_path'  => $remote_path,
			'original_url' => $this->relative_path_to_url( $local_path ),
		);

		return apply_filters( 'w3tc_build_cdn_file_array', $w3tc_file );
	}

	/**
	 * Returns the region ID corresponding to a given bucket location.
	 *
	 * This static method translates a given bucket location into its corresponding region ID.
	 * For example, it converts 'us-east-1-e' to 'us-east-1'.
	 *
	 * @since  2.7.2
	 *
	 * @param string $bucket_location The location of the bucket.
	 *
	 * @return string The corresponding region ID.
	 */
	public static function get_region_id( $bucket_location ) {
		switch ( $bucket_location ) {
			case 'us-east-1-e':
				$w3tc_region = 'us-east-1';
				break;
			default:
				$w3tc_region = $bucket_location;
				break;
		}

		return $w3tc_region;
	}

	/**
	 * Is the configured CDN authorized?
	 *
	 * @since 2.8.5
	 *
	 * @return bool
	 */
	public function is_cdn_authorized() {
		switch ( $this->_config->get_string( 'cdn.engine' ) ) {
			case 'azure':
				$is_cdn_authorized = ! empty( $this->_config->get_string( 'cdn.azure.user' ) ) &&
					! empty( $this->_config->get_string( 'cdn.azure.key' ) ) &&
					! empty( $this->_config->get_string( 'cdn.azure.container' ) ) &&
					! empty( $this->_config->get_array( 'cdn.azure.cname' ) );
				break;

			case 'azuremi':
				$is_cdn_authorized = ! empty( $this->_config->get_string( 'cdn.azuremi.user' ) ) &&
					! empty( $this->_config->get_string( 'cdn.azuremi.clientid' ) ) &&
					! empty( $this->_config->get_string( 'cdn.azure.container' ) ) &&
					! empty( $this->_config->get_array( 'cdn.azure.cname' ) );
				break;

			case 'bunnycdn':
				$is_cdn_authorized = ! empty( $this->_config->get_string( 'cdn.bunnycdn.account_api_key' ) ) &&
					! empty( $this->_config->get_string( 'cdn.bunnycdn.pull_zone_id' ) );
				break;

			case 'cf':
				$is_cdn_authorized = ! empty( $this->_config->get_string( 'cdn.cf.key' ) ) &&
					! empty( $this->_config->get_string( 'cdn.cf.secret' ) ) &&
					! empty( $this->_config->get_string( 'cdn.cf.bucket' ) ) &&
					! empty( $this->_config->get_string( 'cdn.cf.bucket.location' ) );
				break;

			case 'cf2':
				$is_cdn_authorized = ! empty( $this->_config->get_string( 'cdn.cf2.key' ) ) &&
					! empty( $this->_config->get_string( 'cdn.cf2.secret' ) ) &&
					! empty( $this->_config->get_string( 'cdn.cf2.id' ) );
				break;

			case 'ftp':
				$is_cdn_authorized = ! empty( $this->_config->get_string( 'cdn.ftp.host' ) ) &&
					! empty( $this->_config->get_string( 'cdn.ftp.type' ) ) &&
					! empty( $this->_config->get_string( 'cdn.ftp.user' ) ) &&
					! empty( $this->_config->get_string( 'cdn.ftp.pass' ) );
				break;

			case 'google_drive':
				$is_cdn_authorized = ! empty( $this->_config->get_string( 'cdn.google_drive.client_id' ) ) &&
					! empty( $this->_config->get_string( 'cdn.google_drive.refresh_token' ) ) &&
					! empty( $this->_config->get_string( 'cdn.google_drive.folder.id' ) );
				break;

			case 'mirror':
				$is_cdn_authorized = ! empty( $this->_config->get_array( 'cdn.mirror.domain' ) );
				break;

			case 'rackspace_cdn':
				$is_cdn_authorized = ! empty( $this->_config->get_string( 'cdn.rackspace_cdn.user_name' ) ) &&
					! empty( $this->_config->get_string( 'cdn.rackspace_cdn.api_key' ) ) &&
					! empty( $this->_config->get_string( 'cdn.rackspace_cdn.region' ) ) &&
					! empty( $this->_config->get_string( 'cdn.rackspace_cdn.service.id' ) );
				break;

			case 'rscf':
				$is_cdn_authorized = ! empty( $this->_config->get_string( 'cdn.rscf.user' ) ) &&
					! empty( $this->_config->get_string( 'cdn.rscf.key' ) ) &&
					! empty( $this->_config->get_string( 'cdn.rscf.container' ) );
				break;

			case 's3':
			case 's3_compatible':
					$is_cdn_authorized = ! empty( $this->_config->get_string( 'cdn.s3.key' ) ) &&
					! empty( $this->_config->get_string( 'cdn.s3.secret' ) ) &&
					! empty( $this->_config->get_string( 'cdn.s3.bucket' ) ) &&
					! empty( $this->_config->get_string( 'cdn.s3.bucket.location' ) );
				break;

			default:
				$is_cdn_authorized = false;
				break;
		}

		return $is_cdn_authorized;
	}

	/**
	 * Is the configured CDN FSD authorized?
	 *
	 * @since 2.8.5
	 *
	 * @return bool
	 */
	public function is_cdnfsd_authorized() {
		$cloudflare_config = $this->_config->get_array( 'cloudflare' );

		switch ( $this->_config->get_string( 'cdnfsd.engine' ) ) {
			case 'bunnycdn':
				$is_cdnfsd_authorized = ! empty( $this->_config->get_string( 'cdn.bunnycdn.account_api_key' ) ) &&
					! empty( $this->_config->get_string( 'cdnfsd.bunnycdn.pull_zone_id' ) );
				break;

			case 'cloudflare':
				$is_cdnfsd_authorized = Extension_CloudFlare_Api::are_api_credentials_usable(
					isset( $cloudflare_config['email'] ) ? $cloudflare_config['email'] : '',
					isset( $cloudflare_config['key'] ) ? $cloudflare_config['key'] : ''
				) &&
					! empty( $cloudflare_config['zone_id'] ) &&
					! empty( $cloudflare_config['zone_name'] );
				break;

			case 'cloudfront':
				$is_cdnfsd_authorized = ! empty( $this->_config->get_string( 'cdnfsd.cloudfront.access_key' ) ) &&
					! empty( $this->_config->get_string( 'cdnfsd.cloudfront.secret_key' ) ) &&
					! empty( $this->_config->get_string( 'cdnfsd.cloudfront.distribution_id' ) );
				break;

			case 'transparentcdn':
				$is_cdnfsd_authorized = ! empty( $this->_config->get_string( 'cdnfsd.transparentcdn.client_id' ) ) &&
					! empty( $this->_config->get_string( 'cdnfsd.transparentcdn.client_secret' ) ) &&
					! empty( $this->_config->get_string( 'cdnfsd.transparentcdn.company_id' ) );
				break;

			default:
				$is_cdnfsd_authorized = false;
				break;
		}

		return $is_cdnfsd_authorized;
	}
}
