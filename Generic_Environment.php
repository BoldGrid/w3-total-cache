<?php
namespace W3TC;



class Generic_Environment {

	/**
	 * Fixes environment
	 *
	 * @param Config  $config
	 * @param bool    $force_all_checks
	 * @throws Util_Environment_Exceptions
	 */
	function fix_on_wpadmin_request( $config, $force_all_checks ) {
		$exs = new Util_Environment_Exceptions();
		// create add-ins
		$this->create_required_files( $config, $exs );
		$this->robots_add_directive();

		// create folders
		$this->create_required_folders( $exs );
		$this->add_index_to_folders();

		if ( count( $exs->exceptions() ) <= 0 ) {
			// save actual version of config is it's built on legacy configs
			$f = ConfigUtil::is_item_exists( 0, false );
			$f2 = file_exists( Config::util_config_filename_legacy_v2( 0, false ) );

			$c = Dispatcher::config_master();
			if ( ( $f || $f2 ) && $c->is_compiled() ) {
				$c->save();
				$f = ConfigUtil::is_item_exists( 0, false );
			}

			if ( $f && $f2 )
				@unlink( Config::util_config_filename_legacy_v2( 0, false ) );
		}

		if ( count( $exs->exceptions() ) > 0 )
			throw $exs;
	}

	/**
	 * Fixes environment once event occurs
	 *
	 * @throws Util_Environment_Exceptions
	 */
	public function fix_on_event( $config, $event, $old_config = null ) {
	}

	/**
	 * Fixes environment after plugin deactivation
	 *
	 * @throws Util_Environment_Exceptions
	 * @return array
	 */
	public function fix_after_deactivation() {
		$exs = new Util_Environment_Exceptions();

		$this->delete_required_files( $exs );
		$this->robots_remove_directive();

		if ( count( $exs->exceptions() ) > 0 )
			throw $exs;
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
	 * Checks if addins in wp-content is available and correct version.
	 *
	 * @param unknown $config
	 * @param Util_Environment_Exceptions $exs
	 */
	private function create_required_files( $config, $exs ) {
		$src = W3TC_INSTALL_FILE_ADVANCED_CACHE;
		$dst = W3TC_ADDIN_FILE_ADVANCED_CACHE;

		if ( $this->advanced_cache_installed() ) {
			if ( $this->is_advanced_cache_add_in() ) {
				$script_data = @file_get_contents( $dst );
				if ( $script_data == @file_get_contents( $src ) )
					return;
			} else if ( get_transient( 'w3tc_remove_add_in_pgcache' ) == 'yes' ) {
					// user already manually asked to remove another plugin's add in,
					// we should try to apply ours
					// (in case of missing permissions deletion could fail)
				} else if ( !$this->advanced_cache_check_old_add_in() ) {
					$remove_url = Util_Ui::admin_url( 'admin.php?page=w3tc_dashboard&amp;w3tc_default_remove_add_in=pgcache' );

					$exs->push( new Util_WpFile_FilesystemOperationException(
							sprintf( __( 'The Page Cache add-in file advanced-cache.php is not a W3 Total Cache drop-in.
                    It should be removed. %s', 'w3-total-cache' ),
								Util_Ui::button_link( __( 'Yes, remove it for me', 'w3-total-cache' ), wp_nonce_url( $remove_url, 'w3tc' ) ) ) ) );
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
	 * @param Util_Environment_Exceptions $exs
	 */
	private function delete_required_files( $exs ) {
		try {
			if ( $this->is_advanced_cache_add_in() )
				Util_WpFile::delete_file( W3TC_ADDIN_FILE_ADVANCED_CACHE );
		} catch ( Util_WpFile_FilesystemOperationException $ex ) {
			$exs->push( $ex );
		}
	}

	/**
	 * Checks if addins in wp-content is available and correct version.
	 *
	 * @param Util_Environment_Exceptions $exs
	 */
	private function create_required_folders( $exs ) {
		// folders that we create if not exists
		$directories = array(
			W3TC_CACHE_DIR
		);

		if ( !(defined( 'W3TC_CONFIG_DATABASE' ) && W3TC_CONFIG_DATABASE ) ) {
			$directories[] = W3TC_CONFIG_DIR;
		}

		foreach ( $directories as $directory ) {
			try{
				Util_WpFile::create_writeable_folder( $directory, WP_CONTENT_DIR );
			} catch ( Util_WpFile_FilesystemOperationException $ex ) {
				$exs->push( $ex );
			}
		}

		// folders that we delete if exists and not writeable
		$directories = array(
			W3TC_CACHE_TMP_DIR,
			W3TC_CACHE_BLOGMAP_FILENAME,
			W3TC_CACHE_DIR . '/object',
			W3TC_CACHE_DIR . '/db'
		);

		foreach ( $directories as $directory ) {
			try{
				if ( file_exists( $directory ) && !is_writeable( $directory ) )
					Util_WpFile::delete_folder( $directory );
			} catch ( Util_WpFile_FilesystemRmdirException $ex ) {
				$exs->push( $ex );
			}
		}
	}

	/**
	 * Adds index files
	 */
	private function add_index_to_folders() {
		$directories = array(
			W3TC_CACHE_DIR,
			W3TC_CONFIG_DIR );
		$add_files = array();
		foreach ( $directories as $dir ) {
			if ( is_dir( $dir ) && !file_exists( $dir . '/index.html' ) )
				@file_put_contents( $dir . '/index.html', '' );
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
		return ( ( $script_data = @file_get_contents( W3TC_ADDIN_FILE_ADVANCED_CACHE ) )
			&& strstr( $script_data, 'w3_instance' ) !== false );
	}

	/**
	 * Checks if advanced-cache.php exists
	 *
	 * @return boolean
	 */
	public function is_advanced_cache_add_in() {
		return ( ( $script_data = @file_get_contents( W3TC_ADDIN_FILE_ADVANCED_CACHE ) )
			&& strstr( $script_data, 'PgCache_ContentGrabber' ) !== false );
	}

	/**
	 * Enables crawling blocker for cache directory
	 *
	 * @throws Util_WpFile_FilesystemOperationException with S/FTP form if it can't get the required filesystem credentials
	 */
	private function robots_add_directive() {
		$robots_path = Util_Environment::site_path() . 'robots.txt';

		$robots_content = @file_get_contents( $robots_path );
		if ( $robots_content === false )
			return;

		$new_robots_content = $this->robots_remove_from_content( $robots_content );
		$new_robots_content = $new_robots_content . $this->robots_addon();

		if ( $new_robots_content != $robots_content ) {
			try {
				Util_WpFile::write_to_file( $robots_path, $new_robots_content );
			} catch ( Util_WpFile_FilesystemOperationException $ex ) {
				throw new Util_WpFile_FilesystemModifyException(
					$ex->getMessage(), $ex->credentials_form(),
					'Edit file <strong>' . $robots_path .
					'</strong> and add next lines:', $robots_path,
					$this->robots_addon() );
			}
		}
		// that file was in opcache for sure and it may take time to
		// start execution of new modified now version
		$o = Dispatcher::component( 'SystemOpCache_Core' );
		$o->flush();
	}
	
	/**
	 * Disables crawling blocker for cache directory
	 *
	 * @throws Util_WpFile_FilesystemOperationException with S/FTP form if it can't get the required filesystem credentials
	 */
	private function robots_remove_directive() {
		$robots_path = Util_Environment::site_path() . 'robots.txt';

		$robots_content = @file_get_contents( $robots_path );
		if ( $robots_content === false )
			return;

		$new_robots_content = $this->robots_remove_from_content( $robots_content );
		if ( $new_robots_content != $robots_content ) {
			try {
				Util_WpFile::write_to_file( $robots_path, $new_robots_content );
			} catch ( Util_WpFile_FilesystemOperationException $ex ) {
				throw new Util_WpFile_FilesystemModifyException(
					$ex->getMessage(), $ex->credentials_form(),
					'Edit file <strong>' . $robots_path .
					'</strong> and remove next lines:',
					$robots_path,  $this->robots_addon() );
			}
		}
	}
	
	/**
	 * Returns string to block crawling of cache directory via robot.txt
	 */
	private function robots_addon() {
		return "\r\n\r\n# W3TC prevent indexing of cache directory\r\nUser-agent: *\r\nDisallow: /wp-content/cache/\r\n";
	}
	
	/**
	 * Disables crawling blocker for cache directory
	 *
	 * @param string  $robots_content robots.txt content
	 * @return string
	 * @throws Util_WpFile_FilesystemOperationException with S/FTP form if it can't get the required filesystem credentials
	 */
	private function robots_remove_from_content( $robots_content ) {
		$robots_content = preg_replace(
			"~(\r\n|\r|\n)+\\# W3TC prevent indexing of cache directory(\r\n|\r|\n)+User-agent\\: \\*(\r\n|\r|\n)+Disallow\\: \\/wp\\-content\\/cache\\/(\r\n|\r|\n)+~s",
			'', $robots_content );
		/*$robots_content = preg_replace(
			"~(\r\n|\r|\n)+\# W3TC prevent indexing of cache directory(\r\n|\r|\n)+User-agent\: \*(\r\n|\r|\n)+Disallow\: \/wp\-content\/cache\/(\r\n|\r|\n)+~s",
			'', $robots_content );*/

		return $robots_content;
	}
}
