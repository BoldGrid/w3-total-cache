<?php
/**
 * File: Cache_File_Cleaner.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cache_File_Cleaner
 *
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.WP.AlternativeFunctions
 */
class Cache_File_Cleaner {
	/**
	 * Cache directory
	 *
	 * @var string
	 */
	protected $_cache_dir = '';

	/**
	 * Clean operation time limit
	 *
	 * @var int
	 */
	protected $_clean_timelimit = 0;

	/**
	 * Exclude files
	 *
	 * @var array
	 */
	protected $_exclude = array();

	/**
	 * PHP5-style constructor
	 *
	 * @param array $w3tc_config Config.
	 *
	 * @return void
	 */
	public function __construct( $w3tc_config = array() ) {
		$this->_cache_dir       = ( isset( $w3tc_config['cache_dir'] ) ? trim( $w3tc_config['cache_dir'] ) : 'cache' );
		$this->_clean_timelimit = ( isset( $w3tc_config['clean_timelimit'] ) ? (int) $w3tc_config['clean_timelimit'] : 180 );
		$this->_exclude         = ( isset( $w3tc_config['exclude'] ) ? (array) $w3tc_config['exclude'] : array() );
	}

	/**
	 * Run clean operation
	 *
	 * @return void
	 */
	public function clean() {
		@set_time_limit( $this->_clean_timelimit ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged

		$this->_clean( $this->_cache_dir, false );
	}

	/**
	 * Run clean operation
	 *
	 * @return void
	 */
	public function clean_before() {
		@set_time_limit( $this->_clean_timelimit ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged

		$this->_clean( $this->_cache_dir, false );
	}

	/**
	 * Clean
	 *
	 * @param string $path   Path.
	 * @param bool   $remove Remove flag.
	 *
	 * @return void
	 */
	public function _clean( $path, $remove = true ) {
		$dir = @opendir( $path );

		if ( $dir ) {
			$w3tc_entry = @readdir( $dir );
			while ( false !== $w3tc_entry ) {
				if ( '.' === $w3tc_entry || '..' === $w3tc_entry ) {
					$w3tc_entry = @readdir( $dir );
					continue;
				}

				foreach ( $this->_exclude as $mask ) {
					if ( fnmatch( $mask, basename( $w3tc_entry ) ) ) {
						continue 2;
					}
				}

				$full_path = $path . DIRECTORY_SEPARATOR . $w3tc_entry;

				if ( @is_dir( $full_path ) ) {
					$this->_clean( $full_path );
				} elseif ( ! $this->is_valid( $full_path ) ) {
					@unlink( $full_path );
				}

				$w3tc_entry = @readdir( $dir );
			}

			@closedir( $dir );

			if ( $remove ) {
				@rmdir( $path );
			}
		}
	}

	/**
	 * Check if file is valid
	 *
	 * @param string $w3tc_file File.
	 *
	 * @return bool
	 */
	public function is_valid( $w3tc_file ) {
		$valid = false;

		if ( file_exists( $w3tc_file ) ) {
			$fp = @fopen( $w3tc_file, 'rb' );

			if ( $fp ) {
				$expires = @fread( $fp, 4 );

				if ( false !== $expires ) {
					list( , $expires_at ) = @unpack( 'L', $expires );
					$valid                = ( time() < $expires_at );
				}

				@fclose( $fp );
			}
		}

		return $valid;
	}
}
