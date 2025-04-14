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

		$notes = array();

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
					$notes[] = __( 'File successfully deleted from the queue.', 'w3-total-cache' );
				}
				break;

			case 'empty':
				$cdn_queue_type = Util_Request::get_integer( 'cdn_queue_type' );
				if ( ! empty( $cdn_queue_type ) ) {
					$w3_plugin_cdn->queue_empty( $cdn_queue_type );
					$notes[] = __( 'Queue successfully emptied.', 'w3-total-cache' );
				}
				break;

			case 'process':
				$w3_plugin_cdn_normal = Dispatcher::component( 'Cdn_Plugin' );
				$n                    = $w3_plugin_cdn_normal->cron_queue_process();
				$notes[]              = sprintf(
					// Translators: 1 number of processed queue items.
					__(
						'Number of processed queue items: %1$d',
						'w3-total-cache'
					),
					$n
				);
				break;
		}

		$nonce = wp_create_nonce( 'w3tc' );
		$queue = $w3_plugin_cdn->queue_get();
		$title = __( 'Unsuccessful file transfer queue.', 'w3-total-cache' );

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

		$total = $w3_plugin_cdn->get_attachments_count();
		$title = __( 'Media Library export', 'w3-total-cache' );

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

		$status = $flush->execute_delayed_operations();
		$errors = array();
		foreach ( $status as $i ) {
			if ( isset( $i['error'] ) ) {
				$errors[] = $i['error'];
			}
		}

		if ( empty( $errors ) ) {
			Util_Admin::redirect( array( 'w3tc_note' => 'flush_cdn' ), true );
		} else {
			Util_Admin::redirect_with_custom_messages2( array( 'errors' => array( 'Failed to purge CDN: ' . implode( ', ', $errors ) ) ), true );
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

		$limit  = Util_Request::get_integer( 'limit' );
		$offset = Util_Request::get_integer( 'offset' );

		$count   = null;
		$total   = null;
		$results = array();

		$w3_plugin_cdn->export_library( $limit, $offset, $count, $total, $results, time() + 120 );

		$response = array(
			'limit'   => $limit,
			'offset'  => $offset,
			'count'   => $count,
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

		$title = __( 'Media Library import', 'w3-total-cache' );

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

		$limit           = Util_Request::get_integer( 'limit' );
		$offset          = Util_Request::get_integer( 'offset' );
		$import_external = Util_Request::get_boolean( 'cdn_import_external' );
		$config_state    = Dispatcher::config_state();
		$config_state->set( 'cdn.import.external', $import_external );
		$config_state->save();

		$count   = null;
		$total   = null;
		$results = array();

		@$w3_plugin_cdn->import_library( $limit, $offset, $count, $total, $results );

		$response = array(
			'limit'   => $limit,
			'offset'  => $offset,
			'count'   => $count,
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

		$title = __( 'Modify attachment URLs', 'w3-total-cache' );

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

		$limit  = Util_Request::get_integer( 'limit' );
		$offset = Util_Request::get_integer( 'offset' );
		$names  = Util_Request::get_array( 'names' );

		$count   = null;
		$total   = null;
		$results = array();

		@$w3_plugin_cdn->rename_domain( $names, $limit, $offset, $count, $total, $results );

		$response = array(
			'limit'   => $limit,
			'offset'  => $offset,
			'count'   => $count,
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
				$title = __( 'Includes files export', 'w3-total-cache' );
				$files = $w3_plugin_cdn->get_files_includes();
				break;

			case 'theme':
				$title = __( 'Theme files export', 'w3-total-cache' );
				$files = $w3_plugin_cdn->get_files_theme();
				break;

			case 'minify':
				$title = __( 'Minify files export', 'w3-total-cache' );
				$files = $w3_plugin_cdn->get_files_minify();
				break;

			case 'custom':
				$title = __( 'Custom files export', 'w3-total-cache' );
				$files = $w3_plugin_cdn->get_files_custom();
				break;

			default:
				$title = __( 'Unknown files export', 'w3-total-cache' );
				$files = array();
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

		foreach ( $files as $file ) {
			$local_path        = $common->docroot_filename_to_absolute_path( $file );
			$remote_path       = $common->uri_to_cdn_uri( $common->docroot_filename_to_uri( $file ) );
			$d                 = $common->build_file_descriptor( $local_path, $remote_path );
			$d['_original_id'] = $file;
			$upload[]          = $d;
		}

		$common->upload( $upload, false, $results, time() + 5 );
		$output = array();

		foreach ( $results as $item ) {
			$file = '';
			if ( isset( $item['descriptor']['_original_id'] ) ) {
				$file = $item['descriptor']['_original_id'];
			}

			$output[] = array(
				'result' => $item['result'],
				'error'  => $item['error'],
				'file'   => $file,
			);
		}

		$response = array(
			'results' => $output,
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
		$title   = __( 'Content Delivery Network (CDN): Purge Tool', 'w3-total-cache' );
		$results = array();

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
		$title   = __( 'Content Delivery Network (CDN): Purge Tool', 'w3-total-cache' );
		$results = array();

		$files = Util_Request::get_array( 'files' );

		$purge = array();

		$common = Dispatcher::component( 'Cdn_Core' );

		foreach ( $files as $file ) {
			$local_path  = $common->docroot_filename_to_absolute_path( $file );
			$remote_path = $common->uri_to_cdn_uri( $common->docroot_filename_to_uri( $file ) );

			$purge[] = $common->build_file_descriptor( $local_path, $remote_path );
		}

		if ( count( $purge ) ) {
			$common->purge( $purge, $results );
		} else {
			$errors[] = __( 'Empty files list.', 'w3-total-cache' );
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
		$engine = Util_Request::get_string( 'engine' );
		$config = Util_Request::get_array( 'config' );

		// TODO: Workaround to support test case cdn/a04.
		if ( 'ftp' === $engine && ! isset( $config['host'] ) ) {
			$config = Util_Request::get_string( 'config' );
			$config = json_decode( $config, true );
		}

		$config = array_merge( $config, array( 'debug' => false ) );

		if ( isset( $config['domain'] ) && ! is_array( $config['domain'] ) ) {
			$config['domain'] = explode( ',', $config['domain'] );
		}

		if ( Cdn_Util::is_engine( $engine ) ) {
			$result = true;
			$error  = null;
		} else {
			$result = false;
			$error  = __( 'Incorrect engine ', 'w3-total-cache' ) . $engine;
		}
		if ( ! isset( $config['docroot'] ) ) {
			$config['docroot'] = Util_Environment::document_root();
		}

		if ( $result ) {
			if (
				'google_drive' === $engine ||
				'transparentcdn' === $engine ||
				'rackspace_cdn' === $engine ||
				'rscf' === $engine ||
				'bunnycdn' === $engine ||
				's3_compatible' === $engine
			) {
				// those use already stored w3tc config.
				$w3_cdn = Dispatcher::component( 'Cdn_Core' )->get_cdn();
			} else {
				// those use dynamic config from the page.
				$w3_cdn = CdnEngine::instance( $engine, $config );
			}

			@set_time_limit( $this->_config->get_integer( 'timelimit.cdn_test' ) );

			try {
				if ( $w3_cdn->test( $error ) ) {
					$result = true;
					$error  = __( 'Test passed', 'w3-total-cache' );
				} else {
					$result = false;
					$error  = sprintf(
						// Translators: 1 error message.
						__(
							'Error: %1$s',
							'w3-total-cache'
						),
						$error
					);
				}
			} catch ( \Exception $ex ) {
				$result = false;
				$error  = sprintf(
					// Translators: 1 error message.
					__(
						'Error: %s',
						'w3-total-cache'
					),
					$ex->getMessage()
				);
			}
		}

		$response = array(
			'result' => $result,
			'error'  => $error,
		);

		echo wp_json_encode( $response );
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
		$engine = Util_Request::get_string( 'engine' );
		$config = Util_Request::get_array( 'config' );

		$config = array_merge( $config, array( 'debug' => false ) );

		$container_id = '';

		switch ( $engine ) {
			case 's3':
			case 'cf':
			case 'cf2':
			case 'azure':
			case 'azuremi':
				$w3_cdn = CdnEngine::instance( $engine, $config );

				@set_time_limit( $this->_config->get_integer( 'timelimit.cdn_upload' ) );

				$result = false;

				try {
					$container_id = $w3_cdn->create_container();
					$result       = true;
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
				$result = false;
				$error  = __( 'Incorrect type.', 'w3-total-cache' );
		}

		$response = array(
			'result'       => $result,
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
	 * @param string $url The URL to test.
	 *
	 * @return bool True if the URL is accessible, false otherwise.
	 */
	private function test_cdn_url( $url ) {
		$response = wp_remote_get( $url );
		if ( is_wp_error( $response ) ) {
			return false;
		} else {
			$code = wp_remote_retrieve_response_code( $response );
			return 200 === $code;
		}
	}
}
