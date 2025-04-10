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

		$table = $wpdb->base_prefix . W3TC_CDN_TABLE_QUEUE;
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, command FROM ' . $table . ' WHERE local_path = %s AND remote_path = %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$local_path,
				$remote_path
			)
		);

		$already_exists = false;

		foreach ( $rows as $row ) {
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
	 * @param string $file Local file path.
	 *
	 * @return array Array of file descriptors for upload.
	 */
	public function get_files_for_upload( $file ) {
		$files       = array();
		$upload_info = Util_Http::upload_info();

		if ( $upload_info ) {
			$file        = $this->normalize_attachment_file( $file );
			$local_file  = $upload_info['basedir'] . '/' . $file;
			$parsed      = wp_parse_url( rtrim( $upload_info['baseurl'], '/' ) . '/' . $file );
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
					$file = $base_dir . '/' . $size['file'];
				} else {
					$file = $size['file'];
				}

				$files = array_merge( $files, $this->get_files_for_upload( $file ) );
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

		@set_time_limit( $this->_config->get_integer( 'timelimit.cdn_upload' ) );

		$engine = $this->_config->get_string( 'cdn.engine' );
		$return = $cdn->upload( $files, $results, $force_rewrite, $timeout_time );

		if ( ! $return && $queue_failed ) {
			foreach ( $results as $result ) {
				if ( W3TC_CDN_RESULT_OK !== $result['result'] ) {
					$this->queue_add( $result['local_path'], $result['remote_path'], W3TC_CDN_COMMAND_UPLOAD, $result['error'] );
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

		@set_time_limit( $this->_config->get_integer( 'timelimit.cdn_delete' ) );

		$return = $cdn->delete( $files, $results );
		if ( $this->debug ) {
			Util_Debug::log( 'cdn', 'delete: ' . wp_json_encode( $files, JSON_PRETTY_PRINT ) );
		}

		if ( ! $return && $queue_failed ) {
			foreach ( $results as $result ) {
				if ( W3TC_CDN_RESULT_OK !== $result['result'] ) {
					$this->queue_add( $result['local_path'], $result['remote_path'], W3TC_CDN_COMMAND_DELETE, $result['error'] );
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

			foreach ( $files as $file ) {
				$remote_path = $file['remote_path'];
				$varnish->flush_url( network_site_url( $remote_path ) );
			}
		}

		/**
		 * Purge CDN.
		 */
		$cdn = $this->get_cdn();

		@set_time_limit( $this->_config->get_integer( 'timelimit.cdn_purge' ) );

		$return = $cdn->purge( $files, $results );

		if ( ! $return ) {
			foreach ( $results as $result ) {
				if ( W3TC_CDN_RESULT_OK !== $result['result'] ) {
					$this->queue_add( $result['local_path'], $result['remote_path'], W3TC_CDN_COMMAND_PURGE, $result['error'] );
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

		@set_time_limit( $this->_config->get_integer( 'timelimit.cdn_purge' ) );

		return $cdn->purge_all( $results );
	}

	/**
	 * Adds a URL to the CDN upload queue.
	 *
	 * @param string $url URL to be queued for upload.
	 *
	 * @return void
	 */
	public function queue_upload_url( $url ) {
		$docroot_filename = Util_Environment::url_to_docroot_filename( $url );
		if ( is_null( $docroot_filename ) ) {
			return;
		}

		$filename = Util_Environment::docroot_to_full_filename( $docroot_filename );

		$a               = \wp_parse_url( $url );
		$remote_filename = $this->uri_to_cdn_uri( $a['path'] );

		$this->queue_add( $filename, $remote_filename, W3TC_CDN_COMMAND_UPLOAD, 'Pending' );
	}

	/**
	 * Normalizes the attachment file path.
	 *
	 * @param string $file The file path to normalize.
	 *
	 * @return string The normalized file path.
	 */
	public function normalize_attachment_file( $file ) {
		$upload_info = Util_Http::upload_info();

		if ( $upload_info ) {
			$file    = ltrim( str_replace( $upload_info['basedir'], '', $file ), '/\\' );
			$matches = null;

			if ( preg_match( '~(\d{4}/\d{2}/)?[^/]+$~', $file, $matches ) ) {
				$file = $matches[0];
			}
		}

		return $file;
	}

	/**
	 * Retrieves the CDN configuration based on the specified CDN engine.
	 *
	 * This method checks the current configuration settings and returns an array
	 * containing the appropriate configuration for the specified CDN engine.
	 * The configuration details are dependent on the engine selected, such as
	 * Akamai, Cloudflare, or S3, and include settings such as API keys, domain,
	 * SSL configurations, and compression options. The method caches the configuration
	 * after the first retrieval for subsequent calls.
	 *
	 * @return array|null The CDN configuration array or null if not configured.
	 */
	public function get_cdn() {
		static $cdn = null;

		if ( is_null( $cdn ) ) {
			$c           = $this->_config;
			$engine      = $c->get_string( 'cdn.engine' );
			$compression = ( $c->get_boolean( 'browsercache.enabled' ) && $c->get_boolean( 'browsercache.html.compression' ) );

			switch ( $engine ) {
				case 'akamai':
					$engine_config = array(
						'username'           => $c->get_string( 'cdn.akamai.username' ),
						'password'           => $c->get_string( 'cdn.akamai.password' ),
						'zone'               => $c->get_string( 'cdn.akamai.zone' ),
						'domain'             => $c->get_array( 'cdn.akamai.domain' ),
						'ssl'                => $c->get_string( 'cdn.akamai.ssl' ),
						'email_notification' => $c->get_array( 'cdn.akamai.email_notification' ),
						'compression'        => false,
					);
					break;

				case 'att':
					$engine_config = array(
						'account'     => $c->get_string( 'cdn.att.account' ),
						'token'       => $c->get_string( 'cdn.att.token' ),
						'domain'      => $c->get_array( 'cdn.att.domain' ),
						'ssl'         => $c->get_string( 'cdn.att.ssl' ),
						'compression' => false,
					);
					break;

				case 'azure':
					$engine_config = array(
						'user'        => $c->get_string( 'cdn.azure.user' ),
						'key'         => $c->get_string( 'cdn.azure.key' ),
						'container'   => $c->get_string( 'cdn.azure.container' ),
						'cname'       => $c->get_array( 'cdn.azure.cname' ),
						'ssl'         => $c->get_string( 'cdn.azure.ssl' ),
						'compression' => false,
					);
					break;

				case 'azuremi':
					$engine_config = array(
						'user'        => $c->get_string( 'cdn.azuremi.user' ),
						'clientid'    => $c->get_string( 'cdn.azuremi.clientid' ),
						'container'   => $c->get_string( 'cdn.azuremi.container' ),
						'cname'       => $c->get_array( 'cdn.azuremi.cname' ),
						'ssl'         => $c->get_string( 'cdn.azuremi.ssl' ),
						'compression' => false,
					);
					break;

				case 'cf':
					$engine_config = array(
						'key'             => $c->get_string( 'cdn.cf.key' ),
						'secret'          => $c->get_string( 'cdn.cf.secret' ),
						'bucket'          => $c->get_string( 'cdn.cf.bucket' ),
						'bucket_location' => self::get_region_id( $c->get_string( 'cdn.cf.bucket.location' ) ),
						'bucket_loc_id'   => $c->get_string( 'cdn.cf.bucket.location' ),
						'id'              => $c->get_string( 'cdn.cf.id' ),
						'cname'           => $c->get_array( 'cdn.cf.cname' ),
						'ssl'             => $c->get_string( 'cdn.cf.ssl' ),
						'public_objects'  => $c->get_string( 'cdn.cf.public_objects' ),
						'compression'     => $compression,
					);
					break;

				case 'cf2':
					$engine_config = array(
						'key'         => $c->get_string( 'cdn.cf2.key' ),
						'secret'      => $c->get_string( 'cdn.cf2.secret' ),
						'id'          => $c->get_string( 'cdn.cf2.id' ),
						'cname'       => $c->get_array( 'cdn.cf2.cname' ),
						'ssl'         => $c->get_string( 'cdn.cf2.ssl' ),
						'compression' => false,
					);
					break;

				case 'cotendo':
					$engine_config = array(
						'username'    => $c->get_string( 'cdn.cotendo.username' ),
						'password'    => $c->get_string( 'cdn.cotendo.password' ),
						'zones'       => $c->get_array( 'cdn.cotendo.zones' ),
						'domain'      => $c->get_array( 'cdn.cotendo.domain' ),
						'ssl'         => $c->get_string( 'cdn.cotendo.ssl' ),
						'compression' => false,
					);
					break;

				case 'edgecast':
					$engine_config = array(
						'account'     => $c->get_string( 'cdn.edgecast.account' ),
						'token'       => $c->get_string( 'cdn.edgecast.token' ),
						'domain'      => $c->get_array( 'cdn.edgecast.domain' ),
						'ssl'         => $c->get_string( 'cdn.edgecast.ssl' ),
						'compression' => false,
					);
					break;

				case 'ftp':
					$engine_config = array(
						'host'        => $c->get_string( 'cdn.ftp.host' ),
						'type'        => $c->get_string( 'cdn.ftp.type' ),
						'user'        => $c->get_string( 'cdn.ftp.user' ),
						'pass'        => $c->get_string( 'cdn.ftp.pass' ),
						'path'        => $c->get_string( 'cdn.ftp.path' ),
						'pasv'        => $c->get_boolean( 'cdn.ftp.pasv' ),
						'domain'      => $c->get_array( 'cdn.ftp.domain' ),
						'ssl'         => $c->get_string( 'cdn.ftp.ssl' ),
						'compression' => false,
						'docroot'     => Util_Environment::document_root(),
					);
					break;

				case 'google_drive':
					$state = Dispatcher::config_state();

					$engine_config = array(
						'client_id'                 => $c->get_string( 'cdn.google_drive.client_id' ),
						'access_token'              => $state->get_string( 'cdn.google_drive.access_token' ),
						'refresh_token'             => $c->get_string( 'cdn.google_drive.refresh_token' ),
						'root_url'                  => $c->get_string( 'cdn.google_drive.folder.url' ),
						'root_folder_id'            => $c->get_string( 'cdn.google_drive.folder.id' ),
						'new_access_token_callback' => array( $this, 'on_google_drive_new_access_token' ),
					);
					break;

				case 'mirror':
					$engine_config = array(
						'domain'      => $c->get_array( 'cdn.mirror.domain' ),
						'ssl'         => $c->get_string( 'cdn.mirror.ssl' ),
						'compression' => false,
					);
					break;

				case 'rackspace_cdn':
					$state = Dispatcher::config_state();

					$engine_config = array(
						'user_name'                 => $c->get_string( 'cdn.rackspace_cdn.user_name' ),
						'api_key'                   => $c->get_string( 'cdn.rackspace_cdn.api_key' ),
						'region'                    => $c->get_string( 'cdn.rackspace_cdn.region' ),
						'service_access_url'        => $c->get_string( 'cdn.rackspace_cdn.service.access_url' ),
						'service_id'                => $c->get_string( 'cdn.rackspace_cdn.service.id' ),
						'service_protocol'          => $c->get_string( 'cdn.rackspace_cdn.service.protocol' ),
						'domains'                   => $c->get_array( 'cdn.rackspace_cdn.domains' ),
						'access_state'              => $state->get_string( 'cdn.rackspace_cdn.access_state' ),
						'new_access_state_callback' => array( $this, 'on_rackspace_cdn_new_access_state' ),

					);
					break;
				case 'rscf':
					$state = Dispatcher::config_state();

					$engine_config = array(
						'user_name'                 => $c->get_string( 'cdn.rscf.user' ),
						'api_key'                   => $c->get_string( 'cdn.rscf.key' ),
						'region'                    => $c->get_string( 'cdn.rscf.location' ),
						'container'                 => $c->get_string( 'cdn.rscf.container' ),
						'cname'                     => $c->get_array( 'cdn.rscf.cname' ),
						'ssl'                       => $c->get_string( 'cdn.rscf.ssl' ),
						'compression'               => false,
						'access_state'              => $state->get_string( 'cdn.rackspace_cf.access_state' ),
						'new_access_state_callback' => array( $this, 'on_rackspace_cf_new_access_state' ),

					);
					break;

				case 's3':
					$engine_config = array(
						'key'             => $c->get_string( 'cdn.s3.key' ),
						'secret'          => $c->get_string( 'cdn.s3.secret' ),
						'bucket'          => $c->get_string( 'cdn.s3.bucket' ),
						'bucket_location' => self::get_region_id( $c->get_string( 'cdn.s3.bucket.location' ) ),
						'bucket_loc_id'   => $c->get_string( 'cdn.s3.bucket.location' ),
						'cname'           => $c->get_array( 'cdn.s3.cname' ),
						'ssl'             => $c->get_string( 'cdn.s3.ssl' ),
						'public_objects'  => $c->get_string( 'cdn.s3.public_objects' ),
						'compression'     => $compression,
					);
					break;

				case 's3_compatible':
					$engine_config = array(
						'key'         => $c->get_string( 'cdn.s3.key' ),
						'secret'      => $c->get_string( 'cdn.s3.secret' ),
						'bucket'      => $c->get_string( 'cdn.s3.bucket' ),
						'cname'       => $c->get_array( 'cdn.s3.cname' ),
						'ssl'         => $c->get_string( 'cdn.s3.ssl' ),
						'compression' => $compression,
						'api_host'    => $c->get_string( 'cdn.s3_compatible.api_host' ),
					);
					break;

				case 'bunnycdn':
					$engine_config = array(
						'account_api_key' => $c->get_string( 'cdn.bunnycdn.account_api_key' ),
						'storage_api_key' => $c->get_string( 'cdn.bunnycdn.storage_api_key' ),
						'stream_api_key'  => $c->get_string( 'cdn.bunnycdn.stream_api_key' ),
						'pull_zone_id'    => $c->get_integer( 'cdn.bunnycdn.pull_zone_id' ),
						'domain'          => $c->get_string( 'cdn.bunnycdn.cdn_hostname' ),
					);
					break;

				default:
					$engine_config = array();
					break;
			}

			$engine_config = array_merge(
				$engine_config,
				array(
					'debug'   => $c->get_boolean( 'cdn.debug' ),
					'headers' => apply_filters( 'w3tc_cdn_config_headers', array() ),
				)
			);

			$cdn = CdnEngine::instance( $engine, $engine_config );
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
	 * @param string $access_token The new access token for Google Drive.
	 *
	 * @return void
	 */
	public function on_google_drive_new_access_token( $access_token ) {
		$state = Dispatcher::config_state();
		$state->set( 'cdn.google_drive.access_token', $access_token );
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
	 * @param string $file The file path to convert.
	 *
	 * @return string The corresponding URI for the file.
	 */
	public function docroot_filename_to_uri( $file ) {
		$file = ltrim( $file, '/' );

		// Translate multisite subsite uploads paths.
		return str_replace( basename( WP_CONTENT_DIR ) . '/blogs.dir/' . Util_Environment::blog_id() . '/', '', $file );
	}

	/**
	 * Converts a file path to its absolute filesystem path.
	 *
	 * This method takes a relative file path and returns its absolute path based on the document root,
	 * ensuring proper handling of directory separators for different environments.
	 *
	 * @param string $file The file path to convert.
	 *
	 * @return string The absolute filesystem path.
	 */
	public function docroot_filename_to_absolute_path( $file ) {
		if ( is_file( $file ) ) {
			return $file;
		}

		if ( '/' !== DIRECTORY_SEPARATOR ) {
			$file = str_replace( '/', DIRECTORY_SEPARATOR, $file );
		}

		return rtrim( Util_Environment::document_root(), '/\\' ) . DIRECTORY_SEPARATOR . ltrim( $file, '/\\' );
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

		$engine = $this->_config->get_string( 'cdn.engine' );

		if ( Cdn_Util::is_engine_mirror( $engine ) ) {
			if ( Util_Environment::is_wpmu() && strpos( $local_uri, 'files' ) === 0 ) {
				$upload_dir = Util_Environment::wp_upload_dir();
				$remote_uri = $this->abspath_to_relative_path( dirname( $upload_dir['basedir'] ) ) . '/' . $local_uri;
			}
		} elseif ( Util_Environment::is_wpmu() &&
			! Util_Environment::is_wpmu_subdomain() &&
			Util_Environment::is_using_master_config() &&
			Cdn_Util::is_engine_push( $engine ) ) {
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
	 * @param string $url  The base URL to convert.
	 * @param string $path The relative path to the resource.
	 *
	 * @return string|null The full CDN URL, or null if the conversion fails.
	 */
	public function url_to_cdn_url( $url, $path ) {
		$cdn         = $this->get_cdn();
		$remote_path = $this->uri_to_cdn_uri( $path );
		$new_url     = $cdn->format_url( $remote_path );

		if ( ! $new_url ) {
			return null;
		}

		$is_engine_mirror = Cdn_Util::is_engine_mirror( $this->_config->get_string( 'cdn.engine' ) );
		$new_url          = apply_filters( 'w3tc_cdn_url', $new_url, $url, $is_engine_mirror );

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
		$file = array(
			'local_path'   => $local_path,
			'remote_path'  => $remote_path,
			'original_url' => $this->relative_path_to_url( $local_path ),
		);

		return apply_filters( 'w3tc_build_cdn_file_array', $file );
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
				$region = 'us-east-1';
				break;
			default:
				$region = $bucket_location;
				break;
		}

		return $region;
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
			case 'akamai':
				$is_cdn_authorized = ! empty( $this->_config->get_string( 'cdn.akamai.username' ) ) &&
					! empty( $this->_config->get_string( 'cdn.akamai.password' ) ) &&
					! empty( $this->_config->get_string( 'cdn.akamai.zone' ) );
				break;

			case 'att':
				$is_cdn_authorized = ! empty( $this->_config->get_string( 'cdn.att.account' ) ) &&
					! empty( $this->_config->get_string( 'cdn.att.token' ) );
				break;

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

			case 'cotendo':
				$is_cdn_authorized = ! empty( $this->_config->get_string( 'cdn.cotendo.username' ) ) &&
					! empty( $this->_config->get_string( 'cdn.cotendo.password' ) ) &&
					! empty( $this->_config->get_array( 'cdn.cotendo.domain' ) ) &&
					! empty( $this->_config->get_array( 'cdn.cotendo.zones' ) );
				break;

			case 'edgecast':
				$is_cdn_authorized = ! empty( $this->_config->get_string( 'cdn.edgecast.account' ) ) &&
					! empty( $this->_config->get_string( 'cdn.edgecast.token' ) ) &&
					! empty( $this->_config->get_array( 'cdn.edgecast.domain' ) );
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
				$is_cdnfsd_authorized = ! empty( $cloudflare_config['email'] ) &&
					! empty( $cloudflare_config['key'] ) &&
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
