<?php
/**
 * File: Cdn_AdminNotes.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Cdn_AdminNotes
 *
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.DB.DirectDatabaseQuery
 */
class Cdn_AdminNotes {
	/**
	 * Adds CDN-related notes to the provided array of admin notices.
	 *
	 * This method checks various conditions related to the CDN configuration,
	 * WordPress upgrades, theme changes, and other CDN settings to determine
	 * which admin notices should be displayed.
	 *
	 * phpcs:disable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
	 *
	 * @param array $notes Array of existing admin notices.
	 *
	 * @return array Updated array of admin notices.
	 */
	public function w3tc_notes( array $notes ): array {
		$config     = Dispatcher::config();
		$state      = Dispatcher::config_state();
		$cdn_engine = $config->get_string( 'cdn.engine' );
		$page       = Util_Request::get_string( 'page' );

		if ( ! Cdn_Util::is_engine_mirror( $cdn_engine ) ) {
			/**
			 * Show notification after theme change.
			 */
			if ( $state->get_boolean( 'cdn.show_note_theme_changed' ) ) {
				$notes['cdn_theme_changed'] = sprintf(
					// translators: 1: Button code, 2: Button code.
					__( 'The active theme has changed, please %1$s now to ensure proper operation. %2$s', 'w3-total-cache' ),
					Util_Ui::button_popup( __( 'upload active theme files', 'w3-total-cache' ), 'cdn_export', 'cdn_export_type=theme' ),
					Util_Ui::button_hide_note2(
						array(
							'w3tc_default_config_state' => 'y',
							'key'                       => 'cdn.show_note_theme_changed',
							'value'                     => 'false',
						)
					)
				);
			}

			/**
			 * Show notification after WP upgrade.
			 */
			if ( $state->get_boolean( 'cdn.show_note_wp_upgraded' ) ) {
				$notes['cdn_wp_upgraded'] = sprintf(
					// translators: 1: Button code, 2: Button code.
					__( 'Upgraded WordPress? Please %1$s files now to ensure proper operation. %2$s', 'w3-total-cache' ),
					Util_Ui::button_popup( 'upload wp-includes', 'cdn_export', 'cdn_export_type=includes' ),
					Util_Ui::button_hide_note2(
						array(
							'w3tc_default_config_state' => 'y',
							'key'                       => 'cdn.show_note_wp_upgraded',
							'value'                     => 'false',
						)
					)
				);
			}

			/**
			 * Show notification after CDN enable.
			 */
			if ( $state->get_boolean( 'cdn.show_note_cdn_upload' ) ||
				$state->get_boolean( 'cdn.show_note_cdn_reupload' ) ) {
				$cdn_upload_buttons = array();

				if ( $config->get_boolean( 'cdn.includes.enable' ) ) {
					$cdn_upload_buttons[] = Util_Ui::button_popup( 'wp-includes', 'cdn_export', 'cdn_export_type=includes' );
				}

				if ( $config->get_boolean( 'cdn.theme.enable' ) ) {
					$cdn_upload_buttons[] = Util_Ui::button_popup( 'theme files', 'cdn_export', 'cdn_export_type=theme' );
				}

				if (
					$config->get_boolean( 'minify.enabled' ) &&
					$config->get_boolean( 'cdn.minify.enable' ) &&
					! $config->get_boolean( 'minify.auto' )
				) {
					$cdn_upload_buttons[] = Util_Ui::button_popup( 'minify files', 'cdn_export', 'cdn_export_type=minify' );
				}

				if ( $config->get_boolean( 'cdn.custom.enable' ) ) {
					$cdn_upload_buttons[] = Util_Ui::button_popup( 'custom files', 'cdn_export', 'cdn_export_type=custom' );
				}

				if ( $state->get_boolean( 'cdn.show_note_cdn_upload' ) ) {
					$notes[] = sprintf(
						// translators: 1: Button code, 2: Button code, 3: Button code.
						__(
							'Make sure to %1$s and upload the %2$s, files to the <acronym title="Content Delivery Network">CDN</acronym> to ensure proper operation. %3$s',
							'w3-total-cache'
						),
						Util_Ui::button_popup( 'export the media library', 'cdn_export_library' ),
						implode( ', ', $cdn_upload_buttons ),
						Util_Ui::button_hide_note2(
							array(
								'w3tc_default_config_state' => 'y',
								'key'                       => 'cdn.show_note_cdn_upload',
								'value'                     => 'false',
							)
						)
					);
				}

				if ( $state->get_boolean( 'cdn.show_note_cdn_reupload' ) ) {
					$notes[] = sprintf(
						// translators: 1: Button code, 2: Button code, 3: Button code.
						__(
							'Settings that affect Browser Cache settings for files hosted by the CDN have been changed. To apply the new settings %1$s and %2$s. %3$s',
							'w3-total-cache'
						),
						Util_Ui::button_popup( __( 'export the media library', 'w3-total-cache' ), 'cdn_export_library' ),
						implode( ', ', $cdn_upload_buttons ),
						Util_Ui::button_hide_note2(
							array(
								'w3tc_default_config_state' => 'y',
								'key'                       => 'cdn.show_note_cdn_reupload',
								'value'                     => 'false',
							)
						)
					);
				}
			}
		}

		/**
		 * Check CURL extension.
		 */
		if ( ! $state->get_boolean( 'cdn.hide_note_no_curl' ) && ! function_exists( 'curl_init' ) ) {
			$notes[] = sprintf(
				// translators: 1: Button code.
				__( 'The <strong>CURL PHP</strong> extension is not available. Please install it to enable S3 or CloudFront functionality. %1$s', 'w3-total-cache' ),
				Util_Ui::button_hide_note2(
					array(
						'w3tc_default_config_state' => 'y',
						'key'                       => 'cdn.hide_note_no_curl',
						'value'                     => 'true',
					)
				)
			);
		}

		return $notes;
	}

	/**
	 * Adds CDN-related errors to the provided array of admin errors.
	 *
	 * This method checks for issues such as upload queue errors, configuration
	 * inconsistencies, and missing settings. It ensures the errors array includes
	 * relevant information for resolving CDN-related problems.
	 *
	 * @param array $errors Array of existing admin errors.
	 *
	 * @return array Updated array of admin errors.
	 */
	public function w3tc_errors( array $errors ): array {
		$c          = Dispatcher::config();
		$state      = Dispatcher::config_state();
		$cdn_engine = $c->get_string( 'cdn.engine' );

		if ( Cdn_Util::is_engine_push( $cdn_engine ) ) {
			/**
			 * Show notification if upload queue is not empty.
			 */
			try {
				$error = get_transient( 'w3tc_cdn_error' );
				if ( ! $error && ! $this->_is_queue_empty() ) { // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure
					$errors['cdn_unsuccessful_queue'] = sprintf(
						// translators: 1: Button code.
						__( 'The %1$s has unresolved errors. Empty the queue to restore normal operation.', 'w3-total-cache' ),
						Util_Ui::button_popup( __( 'unsuccessful transfer queue', 'w3-total-cache' ), 'cdn_queue' )
					);
				} elseif ( $error ) {
					$errors['cdn_generic'] = $error;
				}
			} catch ( \Exception $ex ) {
				$errors[] = $ex->getMessage();
				set_transient( 'w3tc_cdn_error', $ex->getMessage(), 30 );
			}

			/**
			 * Check upload settings.
			 */
			$upload_info = Util_Http::upload_info();

			if ( ! $upload_info ) {
				$upload_path = get_option( 'upload_path' );
				$upload_path = trim( $upload_path );

				if ( empty( $upload_path ) ) {
					$upload_path = WP_CONTENT_DIR . '/uploads';

					$errors['cdn_uploads_folder_empty'] = sprintf(
						// translators: 1: Upload path.
						__( 'The uploads directory is not available. Default WordPress directories will be created: <strong>%1$s</strong>.', 'w3-total-cache' ),
						$upload_path
					);
				}

				if ( ! Util_Environment::is_wpmu() ) {
					$errors['cdn_uploads_folder_not_found'] = sprintf(
						// translators: 1: Upload path, 2: Button code.
						__(
							'The uploads path found in the database (%1$s) is inconsistent with the actual path. Please manually adjust the upload path either in miscellaneous settings or if not using a custom path %2$s automatically to resolve the issue.',
							'w3-total-cache'
						),
						$upload_path,
						Util_Ui::button_link( __( 'update the path', 'w3-total-cache' ), Util_Ui::url( array( 'w3tc_config_update_upload_path' => 'y' ) ) )
					);
				}
			}
		}

		/**
		 * Check CDN settings
		 */
		$error = '';
		switch ( true ) {
			case ( 'ftp' === $cdn_engine && ! count( $c->get_array( 'cdn.ftp.domain' ) ) ):
				$errors['cdn_ftp_empty'] = __(
					'A configuration issue prevents <acronym title="Content Delivery Network">CDN</acronym> from working: The <strong>"Replace default hostname with"</strong> field cannot be empty. Enter <acronym title="Content Delivery Network">CDN</acronym> provider hostname <a href="?page=w3tc_cdn#configuration">here</a>. <em>(This is the hostname used in order to view objects in a browser.)</em>',
					'w3-total-cache'
				);
				break;

			case ( 's3' === $cdn_engine && ( empty( $c->get_string( 'cdn.s3.key' ) ) || empty( $c->get_string( 'cdn.s3.secret' ) ) || empty( $c->get_string( 'cdn.s3.bucket' ) ) ) ):
				$error = __( 'The <strong>"Access key", "Secret key" and "Bucket"</strong> fields cannot be empty.', 'w3-total-cache' );
				break;

			case ( 'cf' === $cdn_engine && ( empty( $c->get_string( 'cdn.cf.key' ) ) || empty( $c->get_string( 'cdn.cf.secret' ) ) || empty( $c->get_string( 'cdn.cf.bucket' ) ) || ( empty( $c->get_string( 'cdn.cf.id' ) ) && empty( $c->get_array( 'cdn.cf.cname' ) ) ) ) ):
				$error = __( 'The <strong>"Access key", "Secret key", "Bucket" and "Replace default hostname with"</strong> fields cannot be empty.', 'w3-total-cache' );
				break;

			case ( 'cf2' === $cdn_engine && ( empty( $c->get_string( 'cdn.cf2.key' ) ) || empty( $c->get_string( 'cdn.cf2.secret' ) ) || ( empty( $c->get_string( 'cdn.cf2.id' ) ) && empty( $c->get_array( 'cdn.cf2.cname' ) ) ) ) ):
				$error = __( 'The <strong>"Access key", "Secret key" and "Replace default hostname with"</strong> fields cannot be empty.', 'w3-total-cache' );
				break;

			case ( 'rscf' === $cdn_engine && ( empty( $c->get_string( 'cdn.rscf.user' ) ) || empty( $c->get_string( 'cdn.rscf.key' ) ) || empty( $c->get_string( 'cdn.rscf.container' ) ) ) ):
				$error = __( 'The <strong>"Username", "API key", "Container" and "Replace default hostname with"</strong> fields cannot be empty.', 'w3-total-cache' );
				break;

			case ( 'azure' === $cdn_engine && ( empty( $c->get_string( 'cdn.azure.user' ) ) || empty( $c->get_string( 'cdn.azure.key' ) ) || empty( $c->get_string( 'cdn.azure.container' ) ) ) ):
				$error = __( 'The <strong>"Account name", "Account key" and "Container"</strong> fields cannot be empty.', 'w3-total-cache' );
				break;

			case ( 'azuremi' === $cdn_engine && empty( getenv( 'APPSETTING_WEBSITE_SITE_NAME' ) ) ):
				$error = __( 'Microsoft Azure using Managed Identities is only available for "WordPress on App Service".', 'w3-total-cache' );
				break;

			case ( 'azuremi' === $cdn_engine && ( empty( $c->get_string( 'cdn.azuremi.user' ) ) || empty( $c->get_string( 'cdn.azuremi.clientid' ) ) || empty( $c->get_string( 'cdn.azuremi.container' ) ) ) ):
				$error = __( 'The <strong>"Account name", "Entra client ID" and "Container"</strong> fields cannot be empty.', 'w3-total-cache' );
				break;

			case ( 'mirror' === $cdn_engine && empty( $c->get_array( 'cdn.mirror.domain' ) ) ):
				$error = __( 'The <strong>"Replace default hostname with"</strong> field cannot be empty.', 'w3-total-cache' );
				break;

			case ( 'cotendo' === $cdn_engine && empty( $c->get_array( 'cdn.cotendo.domain' ) ) ):
				$error = __( 'The <strong>"Replace default hostname with"</strong> field cannot be empty.', 'w3-total-cache' );
				break;

			case ( 'edgecast' === $cdn_engine && empty( $c->get_array( 'cdn.edgecast.domain' ) ) ):
				$error = __( 'The <strong>"Replace default hostname with"</strong> field cannot be empty.', 'w3-total-cache' );
				break;

			case ( 'att' === $cdn_engine && empty( $c->get_array( 'cdn.att.domain' ) ) ):
				$error = __( 'The <strong>"Replace default hostname with"</strong> field cannot be empty.', 'w3-total-cache' );
				break;

			case ( 'akamai' === $cdn_engine && empty( $c->get_array( 'cdn.akamai.domain' ) ) ):
				$error = 'The <strong>"Replace default hostname with"</strong> field cannot be empty.';
				break;
		}

		if ( $error ) {
			$errors['cdn_not_configured'] = __( 'A configuration issue prevents <acronym title="Content Delivery Network">CDN</acronym> from working: ', 'w3-total-cache' ) .
				$error . __( ' <a href="?page=w3tc_cdn#configuration">Specify it here</a>.', 'w3-total-cache' );
		}

		return $errors;
	}

	/**
	 * Checks if the CDN queue is empty.
	 *
	 * This method checks if there are any items in the CDN queue by querying the corresponding table in the database.
	 * If the queue is empty, it returns true. If the table doesn't exist or there is another database error, an exception
	 * is thrown with a detailed error message.
	 *
	 * @global wpdb $wpdb WordPress database object.
	 *
	 * @throws \Exception If there is a database error or the table doesn't exist.
	 *
	 * @return bool True if the queue is empty, false otherwise.
	 */
	private function _is_queue_empty(): bool {
		global $wpdb;
		$wpdb->hide_errors();

		$result = $wpdb->get_var( sprintf( 'SELECT COUNT(`id`) FROM `%s`', $wpdb->base_prefix . W3TC_CDN_TABLE_QUEUE ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$error = $wpdb->last_error;
		if ( $error ) {
			if ( strpos( $error, "doesn't exist" ) !== false ) {
				$url = is_network_admin() ? network_admin_url( 'admin.php?page=w3tc_install' ) : admin_url( 'admin.php?page=w3tc_install' );
				throw new \Exception(
					sprintf(
						// translators: 1: Error message, 2: Install link.
						esc_html__( 'Encountered issue with CDN: %1$s. See %2$s for instructions of creating correct table.', 'w3-total-cache' ),
						esc_html( $wpdb->last_error ),
						'<a href="' . esc_url( $url ) . '">' . esc_html__( 'Install page', 'w3-total-cache' ) . '</a>'
					)
				);
			} else {
				throw new \Exception(
					sprintf(
						// translators: 1: Error message.
						esc_html__( 'Encountered issue with CDN: %s.', 'w3-total-cache' ),
						esc_html( $wpdb->last_error )
					)
				);
			}
		}

		return empty( $result );
	}
}
