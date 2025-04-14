<?php
/**
 * File: DbCache_Environment.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: DbCache_Environment
 *
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.WP.AlternativeFunctions
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
 */
class DbCache_Environment {
	/**
	 * Fixes database cache on WordPress admin requests.
	 *
	 * @param \W3_Config $config         Configuration object for the application.
	 * @param bool       $force_all_checks Whether to enforce all environmental checks.
	 *
	 * @return void
	 *
	 * @throws \Util_Environment_Exceptions If multiple exceptions occur during operations.
	 */
	public function fix_on_wpadmin_request( $config, $force_all_checks ) {
		$exs             = new Util_Environment_Exceptions();
		$dbcache_enabled = $config->get_boolean( 'dbcache.enabled' );

		try {
			if ( $dbcache_enabled || Util_Environment::is_dbcluster( $config ) ) {
				$this->create_addin();
			} else {
				$this->delete_addin();
			}
		} catch ( Util_WpFile_FilesystemOperationException $ex ) {
			$exs->push( $ex );
		}

		if ( count( $exs->exceptions() ) > 0 ) {
			throw $exs;
		}
	}

	/**
	 * Fixes database cache during specific events.
	 *
	 * @param \W3_Config      $config     Configuration object for the application.
	 * @param string          $event      Name of the event triggering the fix.
	 * @param \W3_Config|null $old_config Optional previous configuration object.
	 *
	 * @return void
	 */
	public function fix_on_event( $config, $event, $old_config = null ) {
		$dbcache_enabled = $config->get_boolean( 'dbcache.enabled' );
		$engine          = $config->get_string( 'dbcache.engine' );

		if ( $dbcache_enabled && ( 'file' === $engine || 'file_generic' === $engine ) ) {
			$new_interval = $config->get_integer( 'dbcache.file.gc' );
			$old_interval = $old_config ? $old_config->get_integer( 'dbcache.file.gc' ) : -1;

			if ( null !== $old_config && $new_interval !== $old_interval ) {
				$this->unschedule_gc();
			}

			if ( ! wp_next_scheduled( 'w3_dbcache_cleanup' ) ) {
				wp_schedule_event( time(), 'w3_dbcache_cleanup', 'w3_dbcache_cleanup' );
			}
		} else {
			$this->unschedule_gc();
		}
	}

	/**
	 * Performs necessary actions after deactivating the plugin.
	 *
	 * @return void
	 *
	 * @throws \Util_Environment_Exceptions If multiple exceptions occur during cleanup operations.
	 */
	public function fix_after_deactivation() {
		$exs = new Util_Environment_Exceptions();

		try {
			$this->delete_addin();
		} catch ( Util_WpFile_FilesystemOperationException $ex ) {
			$exs->push( $ex );
		}

		$this->unschedule_gc();
		$this->unschedule_purge_wpcron();

		if ( count( $exs->exceptions() ) > 0 ) {
			throw $exs;
		}
	}

	/**
	 * Retrieves required database cache rules based on the configuration.
	 *
	 * @param \W3_Config $config Configuration object for the application.
	 *
	 * @return array|null An array of required rules, or null if none are required.
	 */
	public function get_required_rules( $config ) {
		return null;
	}

	/**
	 * Unschedules garbage collection events for database cache.
	 *
	 * @return void
	 */
	private function unschedule_gc() {
		if ( wp_next_scheduled( 'w3_dbcache_cleanup' ) ) {
			wp_clear_scheduled_hook( 'w3_dbcache_cleanup' );
		}
	}

	/**
	 * Unschedules database cache purge events via WP-Cron.
	 *
	 * @return void
	 */
	private function unschedule_purge_wpcron() {
		if ( wp_next_scheduled( 'w3tc_dbcache_purge_wpcron' ) ) {
			wp_clear_scheduled_hook( 'w3tc_dbcache_purge_wpcron' );
		}
	}

	/**
	 * Creates the database cache add-in file.
	 *
	 * @return void
	 *
	 * @throws \Util_WpFile_FilesystemOperationException If the add-in file cannot be created.
	 */
	private function create_addin() {
		$src = W3TC_INSTALL_FILE_DB;
		$dst = W3TC_ADDIN_FILE_DB;

		if ( $this->db_installed() ) {
			if ( $this->is_dbcache_add_in() ) {
				$script_data = @file_get_contents( $dst );
				if ( @file_get_contents( $src ) === $script_data ) {
					return;
				}
			} elseif ( 'yes' === get_transient( 'w3tc_remove_add_in_dbcache' ) ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedElseif
				// User already manually asked to remove another plugin's add in, we should try to apply ours
				// (in case of missing permissions deletion could fail).
			} elseif ( ! $this->db_check_old_add_in() ) {
				$page_val = Util_Request::get_string( 'page' );
				if ( isset( $page_val ) ) {
					$url = 'admin.php?page=' . $page_val . '&amp;';
				} else {
					$url = basename( Util_Environment::remove_query_all( isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '' ) ) . '?page=w3tc_dashboard&amp;';
				}

				$remove_url = Util_Ui::admin_url( $url . 'w3tc_default_remove_add_in=dbcache' );
				throw new Util_WpFile_FilesystemOperationException(
					sprintf(
						// Translators: 1 remove button with link.
						esc_html__(
							'The Database add-in file db.php is not a W3 Total Cache drop-in. Remove it or disable Database Caching. %1$s',
							'w3-total-cache'
						),
						wp_kses_post( Util_Ui::button_link( __( 'Remove it for me', 'w3-total-cache' ), wp_nonce_url( $remove_url, 'w3tc' ) ) )
					)
				);
			}
		}

		Util_WpFile::copy_file( $src, $dst );
	}

	/**
	 * Deletes the database cache add-in file if it exists.
	 *
	 * @return void
	 */
	private function delete_addin() {
		if ( $this->is_dbcache_add_in() ) {
			Util_WpFile::delete_file( W3TC_ADDIN_FILE_DB );
		}
	}

	/**
	 * Checks if the database cache add-in is installed.
	 *
	 * @return bool True if the database cache add-in file exists, false otherwise.
	 */
	public function db_installed() {
		return file_exists( W3TC_ADDIN_FILE_DB );
	}

	/**
	 * Checks for old database cache add-in file versions.
	 *
	 * @return bool True if an old database cache add-in file version exists, false otherwise.
	 */
	public function db_check_old_add_in() {
		if ( ! $this->db_installed() ) {
			return false;
		}

		$script_data = @file_get_contents( W3TC_ADDIN_FILE_DB );
		return ( $script_data && false !== strstr( $script_data, 'w3_instance' ) );
	}

	/**
	 * Checks if the current database cache add-in belongs to this plugin.
	 *
	 * @return bool True if the add-in file matches the plugin's expected content, false otherwise.
	 */
	public function is_dbcache_add_in() {
		if ( ! $this->db_installed() ) {
			return false;
		}

		$script_data = @file_get_contents( W3TC_ADDIN_FILE_DB );
		return ( $script_data && false !== strstr( $script_data, 'DbCache_Wpdb' ) );
	}
}
