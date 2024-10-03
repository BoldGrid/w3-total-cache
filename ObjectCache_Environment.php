<?php
namespace W3TC;

/**
 * W3 Object Cache plugin - administrative interface
 */

/**
 * class ObjectCache_Environment
 */
class ObjectCache_Environment {
	/**
	 * Fixes environment in each wp-admin request
	 *
	 * @param Config  $config
	 * @param bool    $force_all_checks
	 *
	 * @throws Util_Environment_Exceptions
	 */
	public function fix_on_wpadmin_request( $config, $force_all_checks ) {
		$exs                 = new Util_Environment_Exceptions();
		$objectcache_enabled = $config->get_boolean( 'objectcache.enabled' );

		try {
			$addin_required = apply_filters( 'w3tc_objectcache_addin_required', $objectcache_enabled );

			if ( $addin_required ) {
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

		// Schedule purge.
		if ( $objectcache_enabled && $config->get_boolean( 'objectcache.wp_cron' ) ) {
			$new_wp_cron_time     = $config->get_integer( 'objectcache.wp_cron_time' );
			$old_wp_cron_time     = $old_config ? $old_config->get_integer( 'objectcache.wp_cron_time' ) : -1;
			$new_wp_cron_interval = $config->get_integer( 'objectcache.wp_cron_interval' );
			$old_wp_cron_interval = $old_config ? $old_config->get_integer( 'objectcache.wp_cron_interval' ) : -1;

			if ( $new_wp_cron_time !== $old_wp_cron_time || $new_wp_cron_interval !== $old_wp_cron_interval ) {
				$this->unschedule_purge_wpcron();
			}

			// Calculate the start time based on the selected cron time.
			$current_time   = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
			$start_of_today = strtotime( 'today', $current_time ); // Get the start of today in WordPress timezone.
			$hour           = floor( $new_wp_cron_time / 60 ); // Convert the selected time into hours.
			$minute         = $new_wp_cron_time % 60; // Convert the selected time into minutes.
			$scheduled_time = strtotime( "$hour:$minute", $start_of_today ); // Create a timestamp for the selected time today.

			// If the selected time has already passed today, schedule it for tomorrow.
			if ( $scheduled_time <= $current_time ) {
				$scheduled_time = strtotime( '+1 day', $scheduled_time );
			}

			if ( ! wp_next_scheduled( 'w3tc_objectcache_purge_wpcron' ) ) {
				wp_schedule_event( $scheduled_time, 'w3tc_objectcache_purge_wpcron', 'w3tc_objectcache_purge_wpcron' );
			}
		} else {
			$this->unschedule_purge_wpcron();
		}
	}

	/**
	 * Fixes environment once event occurs.
	 *
	 * @param Config      $config     Config.
	 * @param string      $event      Event.
	 * @param null|Config $old_config Old Config.
	 *
	 * @throws Util_Environment_Exceptions Exception.
	 */
	public function fix_on_event( $config, $event, $old_config = null ) {
		$objectcache_enabled = $config->get_boolean( 'objectcache.enabled' );
		$engine              = $config->get_string( 'objectcache.engine' );

		if ( $objectcache_enabled && ( 'file' === $engine || 'file_generic' === $engine ) ) {
			$new_interval = $config->get_integer( 'objectcache.file.gc' );
			$old_interval = $old_config ? $old_config->get_integer( 'objectcache.file.gc' ) : -1;

			if ( null !== $old_config && $new_interval !== $old_interval ) {
				$this->unschedule_gc();
			}

			if ( ! wp_next_scheduled( 'w3_objectcache_cleanup' ) ) {
				wp_schedule_event( time(), 'w3_objectcache_cleanup', 'w3_objectcache_cleanup' );
			}
		} else {
			$this->unschedule_gc();
		}
	}

	/**
	 * Fixes environment after plugin deactivation.
	 *
	 * @throws Util_Environment_Exceptions Exception.
	 * @throws Util_WpFile_FilesystemOperationException Exception.
	 *
	 * @return void
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
	 * Returns required rules for module
	 *
	 * @var Config $config
	 * @return array
	 */
	function get_required_rules( $config ) {
		return null;
	}

	/**
	 * scheduling stuff
	 */
	private function unschedule_gc() {
		if ( wp_next_scheduled( 'w3_objectcache_cleanup' ) ) {
			wp_clear_scheduled_hook( 'w3_objectcache_cleanup' );
		}
	}

	/**
	 * Remove cron job for object cache purge.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	private function unschedule_purge_wpcron() {
		if ( wp_next_scheduled( 'w3tc_objectcache_purge_wpcron' ) ) {
			wp_clear_scheduled_hook( 'w3tc_objectcache_purge_wpcron' );
		}
	}

	/**
	 * Creates add-in
	 *
	 * @throws Util_WpFile_FilesystemOperationException
	 */
	private function create_addin() {
		$src = W3TC_INSTALL_FILE_OBJECT_CACHE;
		$dst = W3TC_ADDIN_FILE_OBJECT_CACHE;

		if ( $this->objectcache_installed() ) {
			if ( $this->is_objectcache_add_in() ) {
				$script_data = @file_get_contents( $dst );
				if ( $script_data == @file_get_contents( $src ) ) {
					return;
				}
			} elseif ( 'yes' === get_transient( 'w3tc_remove_add_in_objectcache' ) ) {
				// user already manually asked to remove another plugin's add in,
				// we should try to apply ours
				// (in case of missing permissions deletion could fail).
			} elseif ( ! $this->is_objectcache_old_add_in() ) {
				$page_val = Util_Request::get_string( 'page' );
				if ( ! empty( $page_val ) ) {
					$url = 'admin.php?page=' . $page_val . '&amp;';
				} else {
					$url = basename(
						Util_Environment::remove_query_all(
							isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : ''
						)
					) . '?page=w3tc_dashboard&amp;';
				}

				$remove_url = Util_Ui::admin_url( $url . 'w3tc_default_remove_add_in=objectcache' );

				throw new Util_WpFile_FilesystemOperationException(
					sprintf(
						// translators: 1 HTML button link to remove object-cache.php file.
						__(
							'The Object Cache add-in file object-cache.php is not a W3 Total Cache drop-in. Remove it or disable Object Caching. %1$s',
							'w3-total-cache'
						),
						Util_Ui::button_link(
							__(
								'Yes, remove it for me',
								'w3-total-cache'
							),
							wp_nonce_url( $remove_url, 'w3tc' )
						)
					)
				);
			}
		}

		Util_WpFile::copy_file( $src, $dst );
	}

	/**
	 * Deletes add-in
	 *
	 * @throws Util_WpFile_FilesystemOperationException
	 */
	private function delete_addin() {
		if ( $this->is_objectcache_add_in() )
			Util_WpFile::delete_file( W3TC_ADDIN_FILE_OBJECT_CACHE );
	}

	/**
	 * Returns true if object-cache.php is installed
	 *
	 * @return boolean
	 */
	public function objectcache_installed() {
		return file_exists( W3TC_ADDIN_FILE_OBJECT_CACHE );
	}

	/**
	 * Returns true if object-cache.php is old version.
	 *
	 * @return boolean
	 */
	public function is_objectcache_old_add_in() {
		if ( !$this->objectcache_installed() )
			return false;

		return ( ( $script_data = @file_get_contents( W3TC_ADDIN_FILE_OBJECT_CACHE ) )
			&& ( ( strstr( $script_data, 'W3 Total Cache Object Cache' ) !== false ) ||
				strstr( $script_data, 'w3_instance' ) !== false ) );
	}

	/**
	 * Checks if object-cache.php is latest version
	 *
	 * @return boolean
	 */
	public function is_objectcache_add_in() {
		if ( !$this->objectcache_installed() )
			return false;

		return ( ( $script_data = @file_get_contents( W3TC_ADDIN_FILE_OBJECT_CACHE ) )
			&& strstr( $script_data, '//ObjectCache Version: 1.4' ) !== false );
	}
}
