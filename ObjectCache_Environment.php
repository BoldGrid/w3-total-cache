<?php
/**
 * File: ObjectCache_Environment.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class ObjectCache_Environment
 *
 * W3 Object Cache plugin - administrative interface
 *
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.WP.AlternativeFunctions
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
 */
class ObjectCache_Environment {
	/**
	 * Fixes the object cache configuration on a WP admin request.
	 *
	 * @param Config $config           W3TC Config containing relevant settings.
	 * @param bool   $force_all_checks Whether to force all checks.
	 *
	 * @return void
	 *
	 * @throws \Util_Environment_Exceptions If there are filesystem operation errors.
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
	}

	/**
	 * Fixes the object cache configuration based on a specific event.
	 *
	 * @param Config      $config     W3TC Config containing relevant settings.
	 * @param string      $event      Event name.
	 * @param Config|null $old_config Optional old W3TC Config containing relevant settings.
	 *
	 * @return void
	 */
	public function fix_on_event( $config, $event, $old_config = null ) {
		$objectcache_enabled = $config->get_boolean( 'objectcache.enabled' );
		$engine              = $config->get_string( 'objectcache.engine' );

		if ( $objectcache_enabled && 'file' === $engine ) {
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
	 * Fixes the object cache configuration after deactivation.
	 *
	 * @return void
	 *
	 * @throws \Util_Environment_Exceptions If there are filesystem operation errors.
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
	 * Retrieves the required rules for the object cache.
	 *
	 * @param Config $config W3TC Config containing relevant settings.
	 *
	 * @return null Always returns null.
	 */
	public function get_required_rules( $config ) {
		return null;
	}

	/**
	 * Unschedules the object cache garbage collection.
	 *
	 * @return void
	 */
	private function unschedule_gc() {
		if ( wp_next_scheduled( 'w3_objectcache_cleanup' ) ) {
			wp_clear_scheduled_hook( 'w3_objectcache_cleanup' );
		}
	}

	/**
	 * Unschedules the object cache purge cron job.
	 *
	 * @since 2.8.0
	 *
	 * @return void
	 */
	private function unschedule_purge_wpcron() {
		if ( wp_next_scheduled( 'w3tc_objectcache_purge_wpcron' ) ) {
			wp_clear_scheduled_hook( 'w3tc_objectcache_purge_wpcron' );
		}
	}

	/**
	 * Creates the object cache add-in file.
	 *
	 * @return void
	 *
	 * @throws \Util_WpFile_FilesystemOperationException If there is a filesystem operation error.
	 */
	private function create_addin() {
		$src = W3TC_INSTALL_FILE_OBJECT_CACHE;
		$dst = W3TC_ADDIN_FILE_OBJECT_CACHE;

		if ( $this->objectcache_installed() ) {
			if ( $this->is_objectcache_add_in() ) {
				$script_data = @file_get_contents( $dst );
				if ( @file_get_contents( $src ) === $script_data ) {
					return;
				}
			} elseif ( 'yes' === get_transient( 'w3tc_remove_add_in_objectcache' ) ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedElseif
				// user already manually asked to remove another plugin's add in, we should try to apply ours
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
					esc_html(
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
					)
				);
			}
		}

		Util_WpFile::copy_file( $src, $dst );
	}

	/**
	 * Deletes the object cache add-in file.
	 *
	 * @return void
	 */
	private function delete_addin() {
		if ( $this->is_objectcache_add_in() ) {
			Util_WpFile::delete_file( W3TC_ADDIN_FILE_OBJECT_CACHE );
		}
	}

	/**
	 * Checks if the object cache add-in is installed.
	 *
	 * @return bool True if the object cache add-in is installed, false otherwise.
	 */
	public function objectcache_installed() {
		return file_exists( W3TC_ADDIN_FILE_OBJECT_CACHE );
	}

	/**
	 * Checks if the object cache add-in is an old version.
	 *
	 * @return bool True if the object cache add-in is an old version, false otherwise.
	 */
	public function is_objectcache_old_add_in() {
		if ( ! $this->objectcache_installed() ) {
			return false;
		}

		$script_data = @file_get_contents( W3TC_ADDIN_FILE_OBJECT_CACHE );
		return $script_data && (
			strstr( $script_data, 'W3 Total Cache Object Cache' ) !== false ||
			strstr( $script_data, 'w3_instance' ) !== false
		);
	}

	/**
	 * Checks if the object cache add-in is installed and is the correct version.
	 *
	 * @return bool True if the object cache add-in is installed and is the correct version, false otherwise.
	 */
	public function is_objectcache_add_in() {
		if ( ! $this->objectcache_installed() ) {
			return false;
		}

		$script_data = @file_get_contents( W3TC_ADDIN_FILE_OBJECT_CACHE );

		return $script_data && strstr( $script_data, 'ObjectCache Version: 1.5' ) !== false;
	}
}
