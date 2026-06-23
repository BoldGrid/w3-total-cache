<?php
/**
 * File: Cdn_AdminActions.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdn_AdminActions
 *
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 */
class Cdn_AdminActions {
	/**
	 * Config
	 *
	 * @var Config $_config
	 */
	private $_config = null;

	/**
	 * Constructor for the Cdn_AdminActions class.
	 *
	 * Initializes the configuration instance used within the class.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->_config = Dispatcher::config();
	}

	/**
	 * Handles various CDN queue actions such as delete, empty, and process.
	 *
	 * Depending on the specified queue action, this method performs operations
	 * like deleting specific queue items, emptying the queue, or processing queued items.
	 * Outputs a popup with the current state of the queue.
	 *
	 * @return void
	 */
	public function w3tc_cdn_queue() {
		$w3_plugin_cdn    = Dispatcher::component( 'Cdn_Core_Admin' );
		$cdn_queue_action = Util_Request::get_string( 'cdn_queue_action' );
		$cdn_queue_tab    = Util_Request::get_string( 'cdn_queue_tab' );

		$w3tc_notes = array();

		switch ( $cdn_queue_tab ) {
			case 'upload':
			case 'delete':
			case 'purge':
				break;

			default:
				$cdn_queue_tab = 'upload';
		}

		switch ( $cdn_queue_action ) {
			case 'delete':
				$cdn_queue_id = Util_Request::get_integer( 'cdn_queue_id' );
				if ( ! empty( $cdn_queue_id ) ) {
					$w3_plugin_cdn->queue_delete( $cdn_queue_id );
					$w3tc_notes[] = __( 'File successfully deleted from the queue.', 'w3-total-cache' );
				}
				break;

			case 'empty':
				$cdn_queue_type = Util_Request::get_integer( 'cdn_queue_type' );
				if ( ! empty( $cdn_queue_type ) ) {
					$w3_plugin_cdn->queue_empty( $cdn_queue_type );
					$w3tc_notes[] = __( 'Queue successfully emptied.', 'w3-total-cache' );
				}
				break;

			case 'process':
				$w3_plugin_cdn_normal = Dispatcher::component( 'Cdn_Plugin' );
				$n                    = $w3_plugin_cdn_normal->cron_queue_process();
				$w3tc_notes[]         = sprintf(
					// Translators: 1 number of processed queue items.
					__(
						'Number of processed queue items: %1$d',
						'w3-total-cache'
					),
					$n
				);
				break;
		}

		$nonce      = Util_Nonce::create_admin( 'w3tc_cdn_queue' );
		$queue      = $w3_plugin_cdn->queue_get();
		$w3tc_title = __( 'Unsuccessful file transfer queue.', 'w3-total-cache' );

		include W3TC_INC_DIR . '/popup/cdn_queue.php';
	}

	/**
	 * Displays the Media Library export popup.
	 *
	 * This method retrieves the total count of media attachments and loads
	 * the export popup for initiating the export process.
	 *
	 * @return void
	 */
	public function w3tc_cdn_export_library() {
		$w3_plugin_cdn = Dispatcher::component( 'Cdn_Core_Admin' );

		$total      = $w3_plugin_cdn->get_attachments_count();
		$w3tc_title = __( 'Media Library export', 'w3-total-cache' );

		include W3TC_INC_DIR . '/popup/cdn_export_library.php';
	}

	/**
	 * Flushes all CDN caches.
	 *
	 * Performs a complete purge of the CDN cache and attempts to execute
	 * any delayed operations. Redirects the user with a success or error message.
	 *
	 * @return void
	 */
	public function w3tc_cdn_flush() {
		$flush = Dispatcher::component( 'CacheFlush' );
		$flush->flush_all( array( 'only' => 'cdn' ) );

		$status      = $flush->execute_delayed_operations();
		$w3tc_errors = array();
		foreach ( $status as $w3tc_i ) {
			if ( isset( $w3tc_i['error'] ) ) {
				$w3tc_errors[] = $w3tc_i['error'];
			}
		}

		if ( empty( $w3tc_errors ) ) {
			Util_Admin::redirect( array( 'w3tc_note' => 'flush_cdn' ), true );
		} else {
			Util_Admin::redirect_with_custom_messages2( array( 'errors' => array( 'Failed to purge CDN: ' . implode( ', ', $w3tc_errors ) ) ), true );
		}
	}

	/**
	 * Processes the Media Library export in chunks.
	 *
	 * Exports media library files to the CDN in a paginated fashion based on
	 * the specified limit and offset. Returns the progress and results as a JSON response.
	 *
	 * @return void
	 */
	public function w3tc_cdn_export_library_process() {
		$w3_plugin_cdn = Dispatcher::component( 'Cdn_Core_Admin' );

		$w3tc_limit  = Util_Request::get_integer( 'limit' );
		$w3tc_offset = Util_Request::get_integer( 'offset' );

		$w3tc_count = null;
		$total      = null;
		$results    = array();

		$w3_plugin_cdn->export_library( $w3tc_limit, $w3tc_offset, $w3tc_count, $total, $results, time() + 120 );

		$response = array(
			'limit'   => $w3tc_limit,
			'offset'  => $w3tc_offset,
			'count'   => $w3tc_count,
			'total'   => $total,
			'results' => $results,
		);

		echo wp_json_encode( $response );
	}

	/**
	 * Displays the Media Library import popup.
	 *
	 * Prepares the data required for importing the Media Library from the CDN,
	 * including the total count of posts and the CDN domain.
	 *
	 * @return void
	 */
	public function w3tc_cdn_import_library() {
		$w3_plugin_cdn = Dispatcher::component( 'Cdn_Core_Admin' );
		$common        = Dispatcher::component( 'Cdn_Core' );

		$cdn = $common->get_cdn();

		$total    = $w3_plugin_cdn->get_import_posts_count();
		$cdn_host = $cdn->get_domain();

		$w3tc_title = __( 'Media Library import', 'w3-total-cache' );

		include W3TC_INC_DIR . '/popup/cdn_import_library.php';
	}

	/**
	 * Processes the Media Library import in chunks.
	 *
	 * Imports media library files from the CDN in a paginated fashion based on
	 * the specified limit and offset. Returns the progress and results as a JSON response.
	 *
	 * @return void
	 */
	public function w3tc_cdn_import_library_process() {
		$w3_plugin_cdn = Dispatcher::component( 'Cdn_Core_Admin' );

		$w3tc_limit        = Util_Request::get_integer( 'limit' );
		$w3tc_offset       = Util_Request::get_integer( 'offset' );
		$import_external   = Util_Request::get_boolean( 'cdn_import_external' );
		$w3tc_config_state = Dispatcher::config_state();
		$w3tc_config_state->set( 'cdn.import.external', $import_external );
		$w3tc_config_state->save();

		$w3tc_count = null;
		$total      = null;
		$results    = array();

		@$w3_plugin_cdn->import_library( $w3tc_limit, $w3tc_offset, $w3tc_count, $total, $results );

		$response = array(
			'limit'   => $w3tc_limit,
			'offset'  => $w3tc_offset,
			'count'   => $w3tc_count,
			'total'   => $total,
			'results' => $results,
		);

		echo wp_json_encode( $response );
	}

	/**
	 * Displays the Modify Attachment URLs popup.
	 *
	 * Retrieves the total count of posts requiring URL renaming and loads the popup
	 * for initiating the domain rename process.
	 *
	 * @return void
	 */
	public function w3tc_cdn_rename_domain() {
		$w3_plugin_cdn = Dispatcher::component( 'Cdn_Core_Admin' );

		$total = $w3_plugin_cdn->get_rename_posts_count();

		$w3tc_title = __( 'Modify attachment URLs', 'w3-total-cache' );

		include W3TC_INC_DIR . '/popup/cdn_rename_domain.php';
	}

	/**
	 * Processes the modification of attachment URLs in chunks.
	 *
	 * Updates attachment URLs to use the new CDN domain in a paginated fashion
	 * based on the specified limit and offset. Returns the progress and results as a JSON response.
	 *
	 * @return void
	 */
	public function w3tc_cdn_rename_domain_process() {
		$w3_plugin_cdn = Dispatcher::component( 'Cdn_Core_Admin' );

		$w3tc_limit  = Util_Request::get_integer( 'limit' );
		$w3tc_offset = Util_Request::get_integer( 'offset' );
		$names       = Util_Request::get_array( 'names' );

		$w3tc_count = null;
		$total      = null;
		$results    = array();

		@$w3_plugin_cdn->rename_domain( $names, $w3tc_limit, $w3tc_offset, $w3tc_count, $total, $results );

		$response = array(
			'limit'   => $w3tc_limit,
			'offset'  => $w3tc_offset,
			'count'   => $w3tc_count,
			'total'   => $total,
			'results' => $results,
		);

		echo wp_json_encode( $response );
	}

	/**
	 * Handles the export of files to the Content Delivery Network (CDN).
	 *
	 * Based on the selected export type (includes, theme, minify, or custom),
	 * this method retrieves the corresponding files and prepares them for export.
	 *
	 * @return void
	 */
	public function w3tc_cdn_export() {
		$w3_plugin_cdn = Dispatcher::component( 'Cdn_Plugin' );

		$cdn_export_type = Util_Request::get_string( 'cdn_export_type', 'custom' );

		switch ( $cdn_export_type ) {
			case 'includes':
				$w3tc_title = __( 'Includes files export', 'w3-total-cache' );
				$files      = $w3_plugin_cdn->get_files_includes();
				break;

			case 'theme':
				$w3tc_title = __( 'Theme files export', 'w3-total-cache' );
				$files      = $w3_plugin_cdn->get_files_theme();
				break;

			case 'minify':
				$w3tc_title = __( 'Minify files export', 'w3-total-cache' );
				$files      = $w3_plugin_cdn->get_files_minify();
				break;

			case 'custom':
				$w3tc_title = __( 'Custom files export', 'w3-total-cache' );
				$files      = $w3_plugin_cdn->get_files_custom();
				break;

			default:
				$w3tc_title = __( 'Unknown files export', 'w3-total-cache' );
				$files      = array();
				break;
		}

		include W3TC_INC_DIR . '/popup/cdn_export_file.php';
	}

	/**
	 * Processes the file export to the CDN.
	 *
	 * This method handles the upload of files to the CDN by constructing file descriptors,
	 * performing the upload, and generating a JSON-encoded response with the results.
	 *
	 * @return void
	 */
	public function w3tc_cdn_export_process() {
		$common = Dispatcher::component( 'Cdn_Core' );
		$files  = Util_Request::get_array( 'files' );

		$upload  = array();
		$results = array();

		foreach ( $files as $w3tc_file ) {
			$local_path        = $common->docroot_filename_to_absolute_path( $w3tc_file );
			$remote_path       = $common->uri_to_cdn_uri( $common->docroot_filename_to_uri( $w3tc_file ) );
			$d                 = $common->build_file_descriptor( $local_path, $remote_path );
			$d['_original_id'] = $w3tc_file;
			$upload[]          = $d;
		}

		$common->upload( $upload, false, $results, time() + 5 );
		$w3tc_output = array();

		foreach ( $results as $w3tc_item ) {
			$w3tc_file = '';
			if ( isset( $w3tc_item['descriptor']['_original_id'] ) ) {
				$w3tc_file = $w3tc_item['descriptor']['_original_id'];
			}

			$w3tc_output[] = array(
				'result' => $w3tc_item['result'],
				'error'  => $w3tc_item['error'],
				'file'   => $w3tc_file,
			);
		}

		$response = array(
			'results' => $w3tc_output,
		);

		echo wp_json_encode( $response );
	}

	/**
	 * Displays the CDN purge tool.
	 *
	 * This method prepares data for the CDN purge tool and includes the required popup template for user interaction.
	 *
	 * @return void
	 */
	public function w3tc_cdn_purge() {
		$w3tc_title = __( 'Content Delivery Network (CDN): Purge Tool', 'w3-total-cache' );
		$results    = array();

		$path = ltrim( str_replace( get_home_url(), '', get_stylesheet_directory_uri() ), '/' );
		include W3TC_INC_DIR . '/popup/cdn_purge.php';
	}

	/**
	 * Processes the purging of specific files from the CDN.
	 *
	 * This method collects files from the request, constructs purge descriptors, and processes the purge via the CDN component.
	 * Results are displayed in the purge tool.
	 *
	 * @return void
	 */
	public function w3tc_cdn_purge_files() {
		$w3tc_title = __( 'Content Delivery Network (CDN): Purge Tool', 'w3-total-cache' );
		$results    = array();

		$files = Util_Request::get_array( 'files' );

		$purge = array();

		$common = Dispatcher::component( 'Cdn_Core' );

		foreach ( $files as $w3tc_file ) {
			$local_path  = $common->docroot_filename_to_absolute_path( $w3tc_file );
			$remote_path = $common->uri_to_cdn_uri( $common->docroot_filename_to_uri( $w3tc_file ) );

			$purge[] = $common->build_file_descriptor( $local_path, $remote_path );
		}

		if ( count( $purge ) ) {
			$common->purge( $purge, $results );
		} else {
			$w3tc_errors[] = __( 'Empty files list.', 'w3-total-cache' );
		}

		$path = str_replace( get_home_url(), '', get_stylesheet_directory_uri() );
		include W3TC_INC_DIR . '/popup/cdn_purge.php';
	}

	/**
	 * Purges a specific attachment from the CDN.
	 *
	 * This method handles the purging of an attachment identified by its ID.
	 * It redirects the user with a success or error notice upon completion.
	 *
	 * @return void
	 */
	public function w3tc_cdn_purge_attachment() {
		$results       = array();
		$attachment_id = Util_Request::get_integer( 'attachment_id' );

		$w3_plugin_cdn = Dispatcher::component( 'Cdn_Core_Admin' );

		if ( $w3_plugin_cdn->purge_attachment( $attachment_id, $results ) ) {
			Util_Admin::redirect( array( 'w3tc_note' => 'cdn_purge_attachment' ), true );
		} else {
			Util_Admin::redirect( array( 'w3tc_error' => 'cdn_purge_attachment' ), true );
		}
	}

	/**
	 * Tests the connection and configuration for a specified CDN engine.
	 *
	 * This method validates the CDN configuration by performing a test against the specified engine.
	 * A JSON-encoded response is generated with the result and any error message.
	 *
	 * @return void
	 */
	public function w3tc_cdn_test() {
		$w3tc_engine = Util_Request::get_string( 'engine' );
		$w3tc_config = Util_Request::get_array( 'config' );

		// TODO: Workaround to support test case cdn/a04.
		if ( 'ftp' === $w3tc_engine && ! isset( $w3tc_config['host'] ) ) {
			$w3tc_config = Util_Request::get_string( 'config' );
			$w3tc_config = json_decode( $w3tc_config, true );
		}

		$w3tc_config = array_merge( $w3tc_config, array( 'debug' => false ) );
		$w3tc_config = self::_cdn_test_merge_stored_secrets( $w3tc_engine, $w3tc_config );

		if ( isset( $w3tc_config['domain'] ) && ! is_array( $w3tc_config['domain'] ) ) {
			$w3tc_config['domain'] = explode( ',', $w3tc_config['domain'] );
		}

		if ( Cdn_Util::is_engine( $w3tc_engine ) ) {
			$w3tc_result = true;
			$error       = null;
		} else {
			$w3tc_result = false;
			$error       = __( 'Incorrect engine ', 'w3-total-cache' ) . $w3tc_engine;
		}
		if ( ! isset( $w3tc_config['docroot'] ) ) {
			$w3tc_config['docroot'] = Util_Environment::document_root();
		}

		if ( $w3tc_result ) {
			if (
				'google_drive' === $w3tc_engine ||
				'transparentcdn' === $w3tc_engine ||
				'rackspace_cdn' === $w3tc_engine ||
				'rscf' === $w3tc_engine ||
				'bunnycdn' === $w3tc_engine ||
				's3_compatible' === $w3tc_engine
			) {
				// those use already stored w3tc config.
				$w3_cdn = Dispatcher::component( 'Cdn_Core' )->get_cdn();
			} else {
				/**
				 * Those use dynamic config from the page — the test
				 * handler runs whatever host the admin has just
				 * entered, before save. Refuse outbound to a non-
				 * routable target so an admin (or anyone driving the
				 * nonce via a separate primitive) cannot point this at
				 * AWS instance metadata / 127.0.0.1:6379 / etc.
				 */
				$bad_host = self::_unsafe_test_host( $w3tc_engine, $w3tc_config );
				if ( null !== $bad_host ) {
					$w3tc_result = false;
					$error       = sprintf(
						// Translators: 1 — rejected host literal.
						__(
							'Refused CDN test target: %1$s resolves to a loopback, link-local, or reserved IP address.',
							'w3-total-cache'
						),
						$bad_host
					);
				} else {
					$w3_cdn = CdnEngine::instance( $w3tc_engine, $w3tc_config );
				}
			}

			/**
			 * `$w3tc_result` was flipped to false above if the destination
			 * host check refused the test, in which case $w3_cdn is
			 * unset. Re-check before invoking the test so we don't
			 * fatal on the next line.
			 */
			if ( $w3tc_result ) {
				@set_time_limit( $this->_config->get_integer( 'timelimit.cdn_test' ) ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged

				try {
					if ( $w3_cdn->test( $error ) ) {
						$w3tc_result = true;
						$error       = __( 'Test passed', 'w3-total-cache' );
					} else {
						$w3tc_result = false;
						$error       = sprintf(
							// Translators: 1 error message.
							__(
								'Error: %1$s',
								'w3-total-cache'
							),
							$error
						);
					}
				} catch ( \Exception $ex ) {
					$w3tc_result = false;
					$error       = sprintf(
						// Translators: 1 error message.
						__(
							'Error: %s',
							'w3-total-cache'
						),
						$ex->getMessage()
					);
				}
			}
		}

		$response = array(
			'result' => $w3tc_result,
			'error'  => $error,
		);

		echo wp_json_encode( $response );
	}

	/**
	 * Fill empty secret fields in a CDN test/create POST from stored config.
	 *
	 * Masked {@see Util_Ui::secret_input()} fields always submit `value=""`;
	 * the Test / Create Container handlers must preserve stored credentials
	 * the same way {@see Generic_AdminActions_Default::read_request()} does
	 * on save.
	 *
	 * @since 2.10.0
	 *
	 * @param string $w3tc_engine CDN engine identifier.
	 * @param array  $w3tc_config Dynamic config from the request.
	 *
	 * @return array
	 */
	private static function _cdn_test_merge_stored_secrets( $w3tc_engine, array $w3tc_config ) {
		$map = self::_cdn_test_stored_secret_keys( $w3tc_engine );
		if ( empty( $map ) ) {
			return $w3tc_config;
		}

		$w3tc_c = Dispatcher::config();
		foreach ( $map as $config_key => $stored_key ) {
			if ( ! isset( $w3tc_config[ $config_key ] ) ) {
				continue;
			}
			if ( ! is_string( $w3tc_config[ $config_key ] ) || '' !== $w3tc_config[ $config_key ] ) {
				continue;
			}
			$stored = $w3tc_c->get_string( $stored_key );
			if ( '' !== $stored ) {
				$w3tc_config[ $config_key ] = $stored;
			}
		}

		return $w3tc_config;
	}

	/**
	 * Map dynamic CDN test config keys to dotted stored-secret keys.
	 *
	 * @since 2.10.0
	 *
	 * @param string $w3tc_engine CDN engine identifier.
	 *
	 * @return array<string, string>
	 */
	private static function _cdn_test_stored_secret_keys( $w3tc_engine ) {
		switch ( $w3tc_engine ) {
			case 'ftp':
				return array(
					'pass'    => 'cdn.ftp.pass',
					'privkey' => 'cdn.ftp.privkey',
				);
			case 's3':
				return array( 'secret' => 'cdn.s3.secret' );
			case 's3_compatible':
				return array( 'secret' => 'cdn.s3_compatible.secret' );
			case 'cf':
				return array( 'secret' => 'cdn.cf.secret' );
			case 'cf2':
				return array( 'secret' => 'cdn.cf2.secret' );
			case 'rscf':
				return array( 'key' => 'cdn.rscf.key' );
			case 'azure':
				return array( 'key' => 'cdn.azure.key' );
			default:
				return array();
		}
	}

	/**
	 * Scan the dynamic CDN-test config for any host field that resolves
	 * to a loopback / link-local / reserved-future address and return the
	 * first offender, or NULL when every host looks routable.
	 *
	 * The CDN test handler accepts a `config` array from the admin form
	 * *before* it has been saved, so the values here are whatever the
	 * client posted. Allowing loopback or link-local targets turns the
	 * test handler into a port scanner against the WP host (Redis on
	 * 127.0.0.1:6379, AWS metadata on 169.254.169.254, etc.).
	 *
	 * Policy: RFC1918 hosts are allowed — operators legitimately run
	 * internal FTP / mirror endpoints on `10.x` / `192.168.x`. Only the
	 * dangerous ranges (loopback, link-local incl. metadata, multicast,
	 * reserved-future) are refused. See {@see Util_Url::is_safe_internal_ip()}
	 * for the exact range list.
	 *
	 * Keys inspected match the engine surface in {@see CdnEngine::instance()}'s
	 * dynamic-config branch:
	 *  - `host`     — FTP / SFTP target.
	 *  - `api_host` — defensive control-endpoint field; no current engine
	 *                 posts it, kept so the denylist stays a superset.
	 *  - `endpoint` — S3 compatible alternate endpoint.
	 *  - `account`  — Azure account name (resolves to `<account>.blob.core.windows.net`,
	 *                 but the field also accepts a literal host for testing).
	 *  - `domain`   — Mirror CDN domain (may be array or comma-string).
	 *
	 * Unknown engines / keys are not inspected — the helper is a
	 * positive denylist, not an allowlist. The cost of a missed key is
	 * the test handler reaches a non-listed target; the cost of an
	 * over-eager allowlist is breaking legitimate operator setups.
	 *
	 * @since 2.10.0
	 *
	 * @param string $w3tc_engine CDN engine identifier (unused today;
	 *                        accepted so future per-engine policy can
	 *                        diverge without a signature break).
	 * @param array  $w3tc_config Dynamic-config array as posted.
	 *
	 * @return string|null   Rejected host literal, or null if all clear.
	 */
	private static function _unsafe_test_host( $w3tc_engine, $w3tc_config ) {
		unset( $w3tc_engine );
		if ( ! \is_array( $w3tc_config ) ) {
			return null;
		}

		$candidates = array();
		foreach ( array( 'host', 'api_host', 'endpoint', 'account' ) as $w3tc_key ) {
			if ( isset( $w3tc_config[ $w3tc_key ] ) && \is_string( $w3tc_config[ $w3tc_key ] ) && '' !== $w3tc_config[ $w3tc_key ] ) {
				$candidates[] = $w3tc_config[ $w3tc_key ];
			}
		}
		if ( isset( $w3tc_config['domain'] ) ) {
			if ( \is_array( $w3tc_config['domain'] ) ) {
				foreach ( $w3tc_config['domain'] as $d ) {
					if ( \is_string( $d ) && '' !== $d ) {
						$candidates[] = $d;
					}
				}
			} elseif ( \is_string( $w3tc_config['domain'] ) && '' !== $w3tc_config['domain'] ) {
				foreach ( \explode( ',', $w3tc_config['domain'] ) as $d ) {
					$d = \trim( $d );
					if ( '' !== $d ) {
						$candidates[] = $d;
					}
				}
			}
		}

		foreach ( $candidates as $w3tc_value ) {
			/**
			 * Strip scheme/path if a full URL leaked in; leave bare
			 * hostnames and `host:port` alone.
			 */
			$host = $w3tc_value;
			if ( false !== \stripos( $host, '://' ) ) {
				$parsed = \wp_parse_url( $host, PHP_URL_HOST );
				if ( \is_string( $parsed ) && '' !== $parsed ) {
					$host = $parsed;
				}
			}
			// Drop a trailing `:port` (but preserve IPv6 brackets).
			if ( '[' !== \substr( $host, 0, 1 ) ) {
				$colon = \strpos( $host, ':' );
				if ( false !== $colon ) {
					$host = \substr( $host, 0, $colon );
				}
			}
			if ( '' === $host ) {
				continue;
			}
			if ( ! Util_Url::host_resolves_safe_internal( $host ) ) {
				return $w3tc_value;
			}
		}

		return null;
	}

	/**
	 * Handles the creation of a CDN container.
	 *
	 * This method is responsible for creating a new container for supported CDN engines
	 * such as Amazon S3, CloudFront, Azure, and others. It retrieves configuration details
	 * from the request, attempts to create the container, and outputs a JSON-encoded response
	 * with the result and any error message.
	 *
	 * @return void
	 */
	public function w3tc_cdn_create_container() {
		$w3tc_engine = Util_Request::get_string( 'engine' );
		$w3tc_config = Util_Request::get_array( 'config' );

		$w3tc_config = array_merge( $w3tc_config, array( 'debug' => false ) );
		$w3tc_config = self::_cdn_test_merge_stored_secrets( $w3tc_engine, $w3tc_config );

		$container_id = '';

		switch ( $w3tc_engine ) {
			case 's3':
			case 'cf':
			case 'cf2':
			case 'azure':
			case 'azuremi':
				$w3_cdn = CdnEngine::instance( $w3tc_engine, $w3tc_config );

				@set_time_limit( $this->_config->get_integer( 'timelimit.cdn_upload' ) ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged

				$w3tc_result = false;

				try {
					$container_id = $w3_cdn->create_container();
					$w3tc_result  = true;
					$error        = __( 'Created successfully.', 'w3-total-cache' );
				} catch ( \Exception $ex ) {
					$error = sprintf(
						// Translators: 1 error message.
						__(
							'Error: %1$s',
							'w3-total-cache'
						),
						$ex->getMessage()
					);
				}

				break;

			default:
				$w3tc_result = false;
				$error       = __( 'Incorrect type.', 'w3-total-cache' );
		}

		$response = array(
			'result'       => $w3tc_result,
			'error'        => $error,
			'container_id' => $container_id,
		);

		echo wp_json_encode( $response );
	}


	/**
	 * Redirects to the BunnyCDN signup page and tracks the signup event.
	 *
	 * This method logs the time of the BunnyCDN signup action and redirects the user
	 * to the BunnyCDN signup URL. Any errors during state saving are ignored.
	 *
	 * @since 2.6.0
	 *
	 * @return void
	 */
	public function w3tc_cdn_bunnycdn_signup() {
		try {
			$state = Dispatcher::config_state();
			$state->set( 'track.bunnycdn_signup', time() );
			$state->save();
		} catch ( \Exception $ex ) {} // phpcs:ignore
		Util_Environment::redirect( W3TC_BUNNYCDN_SIGNUP_URL );
	}

	/**
	 * Tests the accessibility of a given CDN URL.
	 *
	 * This private method checks the accessibility of a specified CDN URL by sending a GET request
	 * and verifying the response code. It returns true if the URL is accessible (HTTP 200 response),
	 * or false otherwise.
	 *
	 * @param string $w3tc_url The URL to test.
	 *
	 * @return bool True if the URL is accessible, false otherwise.
	 */
	private function test_cdn_url( $w3tc_url ) {
		$response = wp_remote_get( $w3tc_url );
		if ( is_wp_error( $response ) ) {
			return false;
		} else {
			$code = wp_remote_retrieve_response_code( $response );
			return 200 === $code;
		}
	}
}
