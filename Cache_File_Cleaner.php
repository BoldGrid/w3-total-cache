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
	 * @param array $config Config.
	 *
	 * @return void
	 */
	public function __construct( $config = array() ) {
		$this->_cache_dir       = ( isset( $config['cache_dir'] ) ? trim( $config['cache_dir'] ) : 'cache' );
		$this->_clean_timelimit = ( isset( $config['clean_timelimit'] ) ? (int) $config['clean_timelimit'] : 180 );
		$this->_exclude         = ( isset( $config['exclude'] ) ? (array) $config['exclude'] : array() );
	}

	/**
	 * Run clean operation
	 *
	 * @return void
	 */
	public function clean() {
		@set_time_limit( $this->_clean_timelimit );

		$this->_clean( $this->_cache_dir, false );
	}

	/**
	 * Run clean operation
	 *
	 * @return void
	 */
	public function clean_before() {
		@set_time_limit( $this->_clean_timelimit );

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
			$entry = @readdir( $dir );
			while ( false !== $entry ) {
				if ( '.' === $entry || '..' === $entry ) {
					$entry = @readdir( $dir );
					continue;
				}

				foreach ( $this->_exclude as $mask ) {
					if ( fnmatch( $mask, basename( $entry ) ) ) {
						continue 2;
					}
				}

				$full_path = $path . DIRECTORY_SEPARATOR . $entry;

				if ( @is_dir( $full_path ) ) {
					$this->_clean( $full_path );
				} elseif ( ! $this->is_valid( $full_path ) ) {
					@unlink( $full_path );
				}

				$entry = @readdir( $dir );
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
	 * @param string $file File.
	 *
	 * @return bool
	 */
	public function is_valid( $file ) {
		$valid = false;

		if ( file_exists( $file ) ) {
			$fp = @fopen( $file, 'rb' );

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
