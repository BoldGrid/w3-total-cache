<?php
/**
 * File: UsageStatistics_Plugin_Admin.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class UsageStatistics_Plugin_Admin
 */
class UsageStatistics_Plugin_Admin {
	/**
	 * Initializes and registers various hooks for the plugin.
	 *
	 * This method adds several actions and filters to hook into WordPress' event-driven model.
	 * It enables AJAX endpoints, adds menu items, and sets up configuration handling,
	 * all related to the usage statistics functionality.
	 *
	 * @return void
	 */
	public function run() {
		$w3tc_c = Dispatcher::config();

		add_action( 'wp_ajax_ustats_access_log_test', array( $this, 'w3tc_ajax_ustats_access_log_test' ) );
		add_filter( 'w3tc_admin_menu', array( $this, 'w3tc_admin_menu' ) );
		add_action( 'w3tc_ajax_ustats_get', array( $this, 'w3tc_ajax_ustats_get' ) );
		add_filter( 'w3tc_usage_statistics_summary_from_history', array( 'W3TC\UsageStatistics_Sources', 'w3tc_usage_statistics_summary_from_history' ), 5, 2 );

		add_action( 'admin_init_w3tc_general', array( '\W3TC\UsageStatistics_GeneralPage', 'admin_init_w3tc_general' ) );

		add_action( 'w3tc_config_ui_save', array( $this, 'w3tc_config_ui_save' ), 10, 2 );

		add_filter( 'w3tc_notes', array( $this, 'w3tc_notes' ) );
	}

	/**
	 * Handles saving of the configuration when UI settings are changed.
	 *
	 * If the 'slot_seconds' value has changed in the configuration, this method
	 * ensures that all existing statistics are flushed to maintain consistency.
	 *
	 * @param object $w3tc_config     The current configuration object.
	 * @param object $old_config The previous configuration object.
	 *
	 * @return void
	 */
	public function w3tc_config_ui_save( $w3tc_config, $old_config ) {
		if ( $w3tc_config->get( 'stats.slot_seconds' ) !== $old_config->get( 'stats.slot_seconds' ) ) {
			// flush all stats otherwise will be inconsistent.
			$storage = new UsageStatistics_StorageWriter();
			$storage->reset();
		}
	}

	/**
	 * Adds custom notes to the admin dashboard related to statistics collection.
	 *
	 * This method checks if statistics collection is enabled, and if so, it adds a
	 * note to the WordPress dashboard to inform the user about the resource usage
	 * and provide options to disable or hide the note.
	 *
	 * @param array $w3tc_notes The current array of notes.
	 *
	 * @return array The modified array of notes with the statistics-related note added.
	 */
	public function w3tc_notes( $w3tc_notes ) {
		$w3tc_c       = Dispatcher::config();
		$state_master = Dispatcher::config_state_master();

		if ( $w3tc_c->get_boolean( 'stats.enabled' ) && ! $state_master->get_boolean( 'common.hide_note_stats_enabled' ) ) {
			$w3tc_notes['stats_enabled'] = sprintf(
				// Translators: 1 disable statistics button, 2 hide notes stats button.
				__(
					'W3 Total Cache: Statistics collection is currently enabled. This consumes additional resources, and is not recommended to be run continuously. %1$s %2$s',
					'w3-total-cache'
				),
				Util_Ui::button_link(
					__( 'Disable statistics', 'w3-total-cache' ),
					Util_Ui::url( array( 'w3tc_ustats_note_disable' => 'y' ) ),
					false,
					'button',
					'w3tc_note_stats_disable'
				),
				Util_Ui::button_hide_note2(
					array(
						'w3tc_default_config_state_master' => 'y',
						'key'                              => 'common.hide_note_stats_enabled',
						'value'                            => 'true',
					)
				)
			);
		}

		return $w3tc_notes;
	}

	/**
	 * Adds a custom menu item to the WordPress admin menu.
	 *
	 * This method adds a new 'Statistics' page to the admin menu. The page is
	 * visible only when specifically enabled, and it is positioned with the appropriate
	 * order in the menu hierarchy.
	 *
	 * @param array $menu The existing admin menu array.
	 *
	 * @return array The modified admin menu array with the new 'Statistics' item.
	 */
	public function w3tc_admin_menu( $menu ) {
		$menu['w3tc_stats'] = array(
			'page_title'     => __( 'Statistics', 'w3-total-cache' ),
			'menu_text'      => __( 'Statistics', 'w3-total-cache' ),
			'visible_always' => false,
			'order'          => 2250,
		);

		return $menu;
	}

	/**
	 * Handles the AJAX request to retrieve usage statistics summary.
	 *
	 * This method is called when an AJAX request for statistics summary is made.
	 * It fetches the summary data from storage and returns it as a JSON response.
	 * The method includes a debug mode where the JSON is pretty-printed.
	 *
	 * @return void
	 */
	public function w3tc_ajax_ustats_get() {
		$storage = new UsageStatistics_StorageReader();
		$summary = $storage->get_history_summary();

		if ( defined( 'W3TC_DEBUG' ) ) {
			echo wp_json_encode( $summary, JSON_PRETTY_PRINT );
			exit();
		}

		echo wp_json_encode( $summary );
		exit();
	}

	/**
	 * Handles the AJAX request to test access to the specified log file.
	 *
	 * This method verifies the nonce, checks if a log file path has been provided,
	 * attempts to open the file, and returns a success or failure message based on
	 * whether the file could be opened.
	 *
	 * @return void
	 */
	public function w3tc_ajax_ustats_access_log_test() {
		/**
		 * Layer 3 of the nonce-verification pass: read the nonce as a
		 * scalar string via Util_Nonce::read_nonce so `_wpnonce[]=foo`
		 * is rejected at the type boundary before reaching
		 * wp_verify_nonce. The previous
		 * `Util_Request::get_array('_wpnonce')[0]` accessor accepted
		 * the array-shape value verbatim.
		 *
		 * Layer 2: per-action nonce key `w3tc_admin_action_w3tc_ustats_access_log_test`
		 * (minted by Util_Nonce::create_admin and read by w3tcGetAdminNonce).
		 *
		 * @since 2.10.0
		 */
		if ( ! Util_Nonce::verify_admin( Util_Nonce::admin_action( 'w3tc_ustats_access_log_test' ) ) ) {
			wp_die( esc_html__( 'Invalid WordPress nonce.  Please reload the page and try again.', 'w3-total-cache' ) );
		}

		/**
		 * Subscriber-reachable AJAX route: the nonce alone does not
		 * establish authorization. Without this gate any logged-in user
		 * could probe arbitrary filesystem paths via fopen(), turning
		 * the handler into a file-existence oracle.
		 *
		 * @since 2.10.0
		 */
		if ( ! \current_user_can( 'manage_options' ) ) {
			wp_die(
				\esc_html__( 'You do not have sufficient permissions to perform this action.', 'w3-total-cache' ),
				'',
				array( 'response' => 403 )
			);
		}

		$handle       = false;
		$filename_val = Util_Request::get_string( 'filename' );
		$filepath     = ! empty( $filename_val ) ? $filename_val : null;

		/**
		 * Path-traversal allowlist for the access-log test handler
		 * (path half). The admin-supplied filename previously
		 * flowed straight into fopen() with only a `://` -> `/` collapse,
		 * turning the handler into a file-existence oracle on arbitrary
		 * host paths (e.g. `/etc/passwd`, `/proc/self/cmdline`).
		 *
		 * Defence-in-depth on top of the `manage_options` check above:
		 * even when an admin account is compromised or the attacker IS
		 * the admin, the allowlist refuses paths outside the documented
		 * log-bearing directories.
		 *
		 * Acceptable resolved paths must live under one of:
		 *   - /var/log (typical Apache/nginx access-log location)
		 *   - the WP uploads basedir
		 *   - W3TC_CACHE_DIR (W3TC's own cache tree)
		 *   - WP_CONTENT_DIR (covers debug.log + any user-configured
		 *     log location inside wp-content)
		 *
		 * On rejection we return the same generic 'Failed to open file'
		 * response as the not-found case -- the rejection reason is not
		 * leaked to the client, so the wire shape stays stable for
		 * legitimate users.
		 *
		 * @since 2.10.0
		 */
		$validated = self::validate_access_log_path( $filepath );

		if ( false !== $validated ) {
			$handle = @fopen( $validated, 'rb' ); // phpcs:ignore WordPress
		}

		if ( $handle ) {
			esc_html_e( 'Success', 'w3-total-cache' );
		} else {
			esc_html_e( 'Failed to open file', 'w3-total-cache' );
		}

		wp_die();
	}

	/**
	 * Validates a user-supplied filesystem path against the access-log
	 * test handler's allowlist.
	 *
	 * Returns the canonicalized absolute path on success, or false if
	 * the input is empty, doesn't resolve via realpath(), or escapes the
	 * permitted root set.
	 *
	 * @since 2.10.0
	 *
	 * @param string|null $filepath Raw filepath from the request.
	 *
	 * @return string|false Canonical absolute path, or false on rejection.
	 */
	private static function validate_access_log_path( $filepath ) {
		if ( ! \is_string( $filepath ) || '' === $filepath ) {
			return false;
		}

		$real = \realpath( $filepath );

		if ( false === $real || ! \is_file( $real ) ) {
			return false;
		}

		$roots = array();

		$var_log_real = \realpath( '/var/log' );
		if ( false !== $var_log_real ) {
			$roots[] = $var_log_real;
		}

		if ( \function_exists( 'wp_upload_dir' ) ) {
			$uploads = \wp_upload_dir( null, false );
			if ( \is_array( $uploads ) && ! empty( $uploads['basedir'] ) ) {
				$uploads_real = \realpath( $uploads['basedir'] );
				if ( false !== $uploads_real ) {
					$roots[] = $uploads_real;
				}
			}
		}

		if ( \defined( 'W3TC_CACHE_DIR' ) ) {
			$cache_real = \realpath( W3TC_CACHE_DIR );
			if ( false !== $cache_real ) {
				$roots[] = $cache_real;
			}
		}

		if ( \defined( 'WP_CONTENT_DIR' ) ) {
			$content_real = \realpath( WP_CONTENT_DIR );
			if ( false !== $content_real ) {
				$roots[] = $content_real;
			}
		}

		foreach ( $roots as $root ) {
			if ( '' === $root ) {
				continue;
			}
			if ( 0 === \strpos( $real, $root . DIRECTORY_SEPARATOR ) ) {
				return $real;
			}
		}

		return false;
	}
}
