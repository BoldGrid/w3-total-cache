<?php
/**
 * File: Cache_File_Cleaner_Generic_HardDelete.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cache_File_Cleaner_Generic_HardDelete
 *
 * phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod.Found
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 * phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
 * phpcs:disable WordPress.WP.AlternativeFunctions.unlink_unlink
 */
class Cache_File_Cleaner_Generic_HardDelete extends Cache_File_Cleaner_Generic {
	/**
	 * Constructor
	 *
	 * @param Config $config Config.
	 *
	 * @return void
	 */
	public function __construct( $config = array() ) {
		parent::__construct( $config );
	}

	/**
	 * Clean file
	 *
	 * @param string $entry     Entry.
	 * @param string $full_path Full path.
	 *
	 * @return void
	 */
	public function _clean_file( $entry, $full_path ) {
		if ( substr( $entry, -4 ) === '_old' && ! $this->is_old_file_valid( $full_path ) ) {
			++$this->processed_count;
			@unlink( $full_path );
		} elseif ( ! $this->is_valid( $full_path ) ) {
			@unlink( $full_path );
		}
	}
}
