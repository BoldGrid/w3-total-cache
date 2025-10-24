<?php
/**
 * FIle: Generic_Environment.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Generic_Environment
 *
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.WP.AlternativeFunctions
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
 */
class Generic_Environment {
	/**
	 * Fixes environment
	 *
	 * @param Config $config           Config.
	 * @param bool   $force_all_checks Force checks flag.
	 *
	 * @throws Util_Environment_Exceptions Exceptions.
	 */
	public function fix_on_wpadmin_request( $config, $force_all_checks ) {
		$exs = new Util_Environment_Exceptions();

		// create add-ins.
		$this->create_required_files( $config, $exs );

		// create folders.
		$this->create_required_folders( $exs );
		$this->add_index_to_folders();

		if ( count( $exs->exceptions() ) <= 0 ) {
			// save actual version of config is it's built on legacy configs.
			$f  = ConfigUtil::is_item_exists( 0, false );
			$f2 = file_exists( Config::util_config_filename_legacy_v2( 0, false ) );

			$c = Dispatcher::config_master();
			if ( ( $f || $f2 ) && $c->is_compiled() ) {
				$c->save();
				$f = ConfigUtil::is_item_exists( 0, false );
			}

			if ( $f && $f2 ) {
				@unlink( Config::util_config_filename_legacy_v2( 0, false ) );
			}
		}

		if ( count( $exs->exceptions() ) > 0 ) {
			throw $exs;
		}
	}

	/**
	 * Fixes environment once event occurs
	 *
	 * @param Config      $config     Config.
	 * @param string      $event      Event.
	 * @param null|Config $old_config Old Config.
	 *
	 * @throws Util_Environment_Exceptions Exceptions.
	 */
	public function fix_on_event( $config, $event, $old_config = null ) {
	}

	/**
	 * Fixes environment after plugin deactivation
	 *
	 * @throws Util_Environment_Exceptions Environment exception.
	 *
	 * @return void
	 */
	public function fix_after_deactivation() {
		$exs = new Util_Environment_Exceptions();

		$this->delete_required_files( $exs );

		$this->unschedule_purge_wpcron();

		if ( count( $exs->exceptions() ) > 0 ) {
			throw $exs;
		}
	}

	/**
	 * Returns required rules for module
	 *
	 * @param Config $config Config.
	 *
	 * @return null
	 */
	public function get_required_rules( $config ) {
		return null;
	}

	/**
	 * Checks if addins in wp-content is available and correct version.
	 *
	 * @param unknown                     $config Config.
	 * @param Util_Environment_Exceptions $exs    Enfironment exceptions.
	 *
	 * @return void
	 */
	private function create_required_files( $config, $exs ) {
		$src = W3TC_INSTALL_FILE_ADVANCED_CACHE;
		$dst = W3TC_ADDIN_FILE_ADVANCED_CACHE;

		if ( $this->advanced_cache_installed() ) {
			if ( $this->is_advanced_cache_add_in() ) {
				if ( @hash_file( 'sha256', $src ) === @hash_file( 'sha256', $dst ) ) {
					return;
				}
			} elseif ( 'yes' === get_transient( 'w3tc_remove_add_in_pgcache' ) ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedElseif
				/**
				 * User already manually asked to remove another plugin's add in, we should
				 * try to apply ours (in case of missing permissions deletion could fail).
				 */
			} elseif ( ! $this->advanced_cache_check_old_add_in() ) {
				$remove_url = Util_Ui::admin_url( 'admin.php?page=w3tc_dashboard&amp;w3tc_default_remove_add_in=pgcache' );

				$exs->push(
					new Util_WpFile_FilesystemOperationException(
						sprintf(
							// Translators: 1 button link.
							__(
								'The Page Cache add-in file advanced-cache.php is not a W3 Total Cache drop-in. It should be removed. %s',
								'w3-total-cache'
							),
							Util_Ui::button_link(
								__( 'Yes, remove it for me', 'w3-total-cache' ),
								wp_nonce_url( $remove_url, 'w3tc' )
							)
						)
					)
				);
				return;
			}
		}

		try {
			Util_WpFile::copy_file( $src, $dst );
		} catch ( Util_WpFile_FilesystemOperationException $ex ) {
			$exs->push( $ex );
		}
	}

	/**
	 * Checks if addins in wp-content are available and deletes them.
	 *
	 * @param Util_Environment_Exceptions $exs Environment exceptions.
	 *
	 * @return void
	 */
	private function delete_required_files( $exs ) {
		try {
			if ( $this->is_advanced_cache_add_in() ) {
				Util_WpFile::delete_file( W3TC_ADDIN_FILE_ADVANCED_CACHE );
			}
		} catch ( Util_WpFile_FilesystemOperationException $ex ) {
			$exs->push( $ex );
		}
	}

	/**
	 * Checks if addins in wp-content is available and correct version.
	 *
	 * @param Util_Environment_Exceptions $exs Environment exceptions.
	 *
	 * @return void
	 */
	private function create_required_folders( $exs ) {
		// Folders that we create if not exists.
		$directories = array(
			W3TC_CACHE_DIR,
		);

		if ( ! ( defined( 'W3TC_CONFIG_DATABASE' ) && W3TC_CONFIG_DATABASE ) ) {
			$directories[] = W3TC_CONFIG_DIR;
		}

		foreach ( $directories as $directory ) {
			try {
				Util_WpFile::create_writeable_folder( $directory, WP_CONTENT_DIR );
			} catch ( Util_WpFile_FilesystemOperationException $ex ) {
				$exs->push( $ex );
			}
		}

		// folders that we delete if exists and not writeable.
		$directories = array(
			W3TC_CACHE_TMP_DIR,
			W3TC_CACHE_BLOGMAP_FILENAME,
			W3TC_CACHE_DIR . '/object',
			W3TC_CACHE_DIR . '/db',
		);

		foreach ( $directories as $directory ) {
			try {
				if ( file_exists( $directory ) && ! is_writeable( $directory ) ) {
					Util_WpFile::delete_folder( $directory );
				}
			} catch ( Util_WpFile_FilesystemRmdirException $ex ) {
				$exs->push( $ex );
			}
		}
	}

	/**
	 * Adds index files
	 *
	 * @return void
	 */
	private function add_index_to_folders() {
		$directories = array(
			W3TC_CACHE_DIR,
			W3TC_CONFIG_DIR,
		);

		$add_files = array();
		foreach ( $directories as $dir ) {
			if ( is_dir( $dir ) && ! file_exists( $dir . '/index.html' ) ) {
				@file_put_contents( $dir . '/index.html', '' );
			}
		}
	}

	/**
	 * Returns true if advanced-cache.php is installed
	 *
	 * @return boolean
	 */
	public function advanced_cache_installed() {
		return file_exists( W3TC_ADDIN_FILE_ADVANCED_CACHE );
	}

	/**
	 * Returns true if advanced-cache.php is old version.
	 *
	 * @return boolean
	 */
	public function advanced_cache_check_old_add_in() {
		$script_data = @file_get_contents( W3TC_ADDIN_FILE_ADVANCED_CACHE );
		return ( $script_data && strstr( $script_data, 'w3_instance' ) !== false );
	}

	/**
	 * Checks if advanced-cache.php exists
	 *
	 * @return boolean
	 */
	public function is_advanced_cache_add_in() {
		$script_data = @file_get_contents( W3TC_ADDIN_FILE_ADVANCED_CACHE );
		return ( $script_data && strstr( $script_data, 'PgCache_ContentGrabber' ) !== false );
	}

	/**
	 * Remove cron job for purge all caches.
	 *
	 * @since 2.8.0
	 *
	 * @return void
	 */
	private function unschedule_purge_wpcron() {
		if ( wp_next_scheduled( 'w3tc_purgeall_wpcron' ) ) {
			wp_clear_scheduled_hook( 'w3tc_purgeall_wpcron' );
		}
	}
}
