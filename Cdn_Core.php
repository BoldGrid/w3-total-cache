<?php
/**
 * File: Cdn_Core.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Cdn_Core
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
	 * Constructor
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();
		$this->debug = $this->_config->get_boolean( 'cdn.debug' );
	}

	/**
	 * Adds file to queue.
	 *
	 * @param string $local_path  Local path.
	 * @param string $remote_path Remote path.
	 * @param int    $command     Command number.
	 * @param string $last_error  Last error.
	 * @return int
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
			if ( $row->command != $command ) {
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
	 * Returns array of array('local_path' => '', 'remote_path' => '') for specified file.
	 *
	 * @param  string $file File.
	 * @return array
	 */
	public function get_files_for_upload( $file ) {
		$files       = array();
		$upload_info = Util_Http::upload_info();

		if ( $upload_info ) {
			$file        = $this->normalize_attachment_file( $file );
			$local_file  = $upload_info['basedir'] . '/' . $file;
			$parsed      = parse_url( rtrim( $upload_info['baseurl'], '/' ) . '/' . $file );
			$local_uri   = $parsed['path'];
			$remote_uri  = $this->uri_to_cdn_uri( $local_uri );
			$remote_file = ltrim( $remote_uri, '/' );
			$files[]     = $this->build_file_descriptor( $local_file, $remote_file );
		}

		return $files;
	}

	/**
	 * Returns array of files from sizes array.
	 *
	 * @param  string $attached_file Attached file.
	 * @param  array  $sizes         Sizes.
	 * @return array
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
	 * Returns attachment files by metadata.
	 *
	 * @param  array $metadata Metadata.
	 * @return array
	 */
	public function get_metadata_files( $metadata ) {
		$files = array();

		if ( isset( $metadata['file'] ) && isset( $metadata['sizes'] ) ) {
			$files = array_merge( $files, $this->_get_sizes_files( $metadata['file'], $metadata['sizes'] ) );
		}

		return $files;
	}

	/**
	 * Returns attachment files by attachment id.
	 *
	 * @param  int $attachment_id Attachment id.
	 * @return array
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
	 * Uploads files to CDN.
	 *
	 * @param array $files        Files.
	 * @param bool  $queue_failed Is queue failed.
	 * @param array $results      Results.
	 * @param int   $timeout_time Timeout time.
	 * @return bool
	 */
	public function upload( $files, $queue_failed, &$results, $timeout_time = null ) {
		if ( $this->debug ) {
			Util_Debug::log( 'cdn', 'upload: ' . json_encode( $files, JSON_PRETTY_PRINT ) );
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
	 * Deletes files from CDN.
	 *
	 * @param array $files        Files.
	 * @param bool  $queue_failed Is queue failed.
	 * @param array $results      Results.
	 * @return bool
	 */
	public function delete( $files, $queue_failed, &$results ) {
		$cdn = $this->get_cdn();

		@set_time_limit( $this->_config->get_integer( 'timelimit.cdn_delete' ) );

		$return = $cdn->delete( $files, $results );
		if ( $this->debug ) {
			Util_Debug::log( 'cdn', 'delete: ' . json_encode( $files, JSON_PRETTY_PRINT ) );
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
	 * Purges files from CDN.
	 *
	 * @param array $files   Files; consisting of array( 'local_path'=>'', 'remote_path'=>'' ).
	 * @param array $results Results.
	 * @return bool
	 */
	public function purge( $files, &$results ) {
		if ( $this->debug ) {
			Util_Debug::log( 'cdn', 'purge: ' . json_encode( $files, JSON_PRETTY_PRINT ) );
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
	 * Purge CDN completely.
	 *
	 * @param  unknown $results Results of the purge.
	 * @return mixed
	 */
	public function purge_all( &$results ) {
		$cdn = $this->get_cdn();

		@set_time_limit( $this->_config->get_integer( 'timelimit.cdn_purge' ) );

		return $cdn->purge_all( $results );
	}

	/**
	 * Queues file upload.
	 *
	 * Links wp_cron call to do that by the end of request processing.
	 *
	 * @param string $url Url.
	 * @return void
	 */
	public function queue_upload_url( $url ) {
		$docroot_filename = Util_Environment::url_to_docroot_filename( $url );
		if ( is_null( $docroot_filename ) ) {
			return;
		}

		$filename = Util_Environment::docroot_to_full_filename( $docroot_filename );

		$a               = \parse_url( $url );
		$remote_filename = $this->uri_to_cdn_uri( $a['path'] );

		$this->queue_add( $filename, $remote_filename, W3TC_CDN_COMMAND_UPLOAD, 'Pending' );
	}

	/**
	 * Normalizes attachment file.
	 *
	 * @param  string $file Filename.
	 * @return string
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
	 * Returns CDN object.
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

				case 'highwinds':
					$state = Dispatcher::config_state();

					$engine_config = array(
						'domains'        => $c->get_array( 'cdn.highwinds.host.domains' ),
						'ssl'            => $c->get_string( 'cdn.highwinds.ssl' ),
						'api_token'      => $c->get_string( 'cdn.highwinds.api_token' ),
						'account_hash'   => $c->get_string( 'cdn.highwinds.account_hash' ),
						'host_hash_code' => $c->get_string( 'cdn.highwinds.host.hash_code' ),
					);
					break;

				case 'limelight':
					$engine_config = array(
						'short_name' => $c->get_string( 'cdn.limelight.short_name' ),
						'username'   => $c->get_string( 'cdn.limelight.username' ),
						'api_key'    => $c->get_string( 'cdn.limelight.api_key' ),
						'domains'    => $c->get_array( 'cdn.limelight.host.domains' ),
						'debug'      => $c->get_string( 'cdn.debug' ),
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

				case 'stackpath':
					$engine_config = array(
						'authorization_key' => $c->get_string( 'cdn.stackpath.authorization_key' ),
						'zone_id'           => $c->get_integer( 'cdn.stackpath.zone_id' ),
						'domain'            => $c->get_array( 'cdn.stackpath.domain' ),
						'ssl'               => $c->get_string( 'cdn.stackpath.ssl' ),
						'compression'       => false,
					);
					break;

				case 'stackpath2':
					$state = Dispatcher::config_state();

					$engine_config = array(
						'client_id'           => $c->get_string( 'cdn.stackpath2.client_id' ),
						'client_secret'       => $c->get_string( 'cdn.stackpath2.client_secret' ),
						'stack_id'            => $c->get_string( 'cdn.stackpath2.stack_id' ),
						'site_root_domain'    => $c->get_string( 'cdn.stackpath2.site_root_domain' ),
						'domain'              => $c->get_array( 'cdn.stackpath2.domain' ),
						'ssl'                 => $c->get_string( 'cdn.stackpath2.ssl' ),
						'access_token'        => $state->get_string( 'cdn.stackpath2.access_token' ),
						'on_new_access_token' => array( $this, 'on_stackpath2_new_access_token' ),
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
	 * Called when a new access token is issued by cdnengine.
	 *
	 * @param string $access_token Access token.
	 */
	public function on_google_drive_new_access_token( $access_token ) {
		$state = Dispatcher::config_state();
		$state->set( 'cdn.google_drive.access_token', $access_token );
		$state->save();
	}

	/**
	 * Called when a new access state is issued by cdnengine
	 *
	 * @param string $access_state Access state.
	 */
	public function on_rackspace_cdn_new_access_state( $access_state ) {
		$state = Dispatcher::config_state();
		$state->set( 'cdn.rackspace_cdn.access_state', $access_state );
		$state->save();
	}

	/**
	 * Called when a new access state is issued by cdnengine
	 *
	 * @param string $access_state Access state.
	 */
	public function on_rackspace_cf_new_access_state( $access_state ) {
		$state = Dispatcher::config_state();
		$state->set( 'cdn.rackspace_cf.access_state', $access_state );
		$state->save();
	}

	/**
	 * Called when a new access token is issued by cdnengine.
	 *
	 * @param string $access_token Access token.
	 */
	public function on_stackpath2_new_access_token( $access_token ) {
		$state = Dispatcher::config_state();
		$state->set( 'cdn.stackpath2.access_token', $access_token );
		$state->save();
	}

	/**
	 * Convert relative file which is relative to ABSPATH (wp folder on disc) to path uri.
	 *
	 * @param  string $file Filename.
	 * @return string
	 */
	public function docroot_filename_to_uri( $file ) {
		$file = ltrim( $file, '/' );

		// Translate multisite subsite uploads paths.
		return str_replace( basename( WP_CONTENT_DIR ) . '/blogs.dir/' . Util_Environment::blog_id() . '/', '', $file );
	}

	/**
	 * Convert a relative path (relative to ABSPATH (wp folder on disc) into a absolute path.
	 *
	 * @param  string $file Filename.
	 * @return string
	 */
	public function docroot_filename_to_absolute_path( $file ) {
		if ( is_file( $file ) ) {
			return $file;
		}

		if ( DIRECTORY_SEPARATOR != '/' ) {
			$file = str_replace( '/', DIRECTORY_SEPARATOR, $file );
		}

		return rtrim( Util_Environment::document_root(), '/\\' ) . DIRECTORY_SEPARATOR . ltrim( $file, '/\\' );
	}

	/**
	 * Convert local uri path to CDN type specific path.
	 *
	 * @param  string $local_uri Local URI.
	 * @return string
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
	 * Need to pass full URL and it's URI.
	 * URI passed to prevent redundant parsing, normally it's available for caller.
	 *
	 * @param  string $url  URL.
	 * @param  string $path Path.
	 * @return string|null
	 **/
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
	 * Takes an absolute path and converts to a relative path to root.
	 *
	 * @param  string $path Path.
	 * @return mixed
	 */
	public function abspath_to_relative_path( $path ) {
		return str_replace( Util_Environment::document_root(), '', $path );
	}

	/**
	 * Takes a root relative path and converts to a full URI.
	 *
	 * @param  string $path Path.
	 * @return string
	 */
	public function relative_path_to_url( $path ) {
		return rtrim( Util_Environment::home_domain_root_url(), '/' ) . '/' .
			$this->docroot_filename_to_uri( ltrim( $path, '/' ) );
	}

	/**
	 * Constructs a CDN file descriptor.
	 *
	 * @param string $local_path  Local path.
	 * @param string $remote_path Remote path.
	 * @return array
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
	 * Get the S3 bucket region ID.
	 *
	 * @since  2.7.2
	 * @static
	 *
	 * @param string $bucket_location S3 bucket location.
	 * @return string
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
			case 'highwinds':
				$is_cdn_authorized = ! empty( $this->_config->get_string( 'cdn.highwinds.account_hash' ) ) &&
					! empty( $this->_config->get_string( 'cdn.highwinds.api_token' ) ) &&
					! empty( $this->_config->get_string( 'cdn.highwinds.host.hash_code' ) );
				break;
			case 'limelight':
				$is_cdn_authorized = ! empty( $this->_config->get_string( 'cdn.limelight.short_name' ) ) &&
					! empty( $this->_config->get_string( 'cdn.limelight.username' ) ) &&
					! empty( $this->_config->get_string( 'cdn.limelight.api_key' ) );
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
			case 'stackpath':
				$is_cdn_authorized = ! empty( $this->_config->get_string( 'cdn.stackpath.autorization_key' ) ) &&
					! empty( $this->_config->get_string( 'cdn.stackpath.zone_id' ) );
				break;
			case 'stackpath2':
				$is_cdn_authorized = ! empty( $this->_config->get_string( 'cdn.stackpath.client_id' ) ) &&
					! empty( $this->_config->get_string( 'cdn.stackpath.client_secret' ) ) &&
					! empty( $this->_config->get_string( 'cdn.stackpath.stack_id' ) ) &&
					! empty( $this->_config->get_string( 'cdn.stackpath.site_id' ) ) &&
					! empty( $this->_config->get_string( 'cdn.stackpath.site_root_domain' ) );
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
			case 'limelight':
				$is_cdnfsd_authorized = ! empty( $this->_config->get_string( 'cdnfsd.limelight.short_name' ) ) &&
					! empty( $this->_config->get_string( 'cdnfsd.limelight.username' ) ) &&
					! empty( $this->_config->get_string( 'cdnfsd.limelight.api_key' ) );
				break;
			case 'maxcdn':
					$is_cdnfsd_authorized = ! empty( $this->_config->get_string( 'cdnfsd.maxcdn.api_key' ) ) &&
						! empty( $this->_config->get_string( 'cdnfsd.maxcdn.zone_id' ) );
				break;
			case 'stackpath':
				$is_cdnfsd_authorized = ! empty( $this->_config->get_string( 'cdnfsd.stackpath.api_key' ) ) &&
					! empty( $this->_config->get_string( 'cdnfsd.stackpath.zone_id' ) );
				break;
			case 'stackpath2':
				$is_cdnfsd_authorized = ! empty( $this->_config->get_string( 'cdnfsd.stackpath2.client_id' ) ) &&
					! empty( $this->_config->get_string( 'cdnfsd.stackpath2.client_secret' ) ) &&
					! empty( $this->_config->get_string( 'cdnfsd.stackpath2.stack_id' ) ) &&
					! empty( $this->_config->get_string( 'cdnfsd.stackpath2.site_id' ) ) &&
					! empty( $this->_config->get_string( 'cdnfsd.stackpath2.site_root_domain' ) );
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
