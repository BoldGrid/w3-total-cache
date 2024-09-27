<?php
namespace W3TC;

/**
 * Disk-enhanced file cache cleaner
 */
class Cache_File_Cleaner_Generic_HardDelete extends Cache_File_Cleaner_Generic {
	function __construct( $config = array() ) {
		parent::__construct( $config );
	}

	function _clean_file( $entry, $full_path ) {
		if ( substr( $entry, -4 ) === '_old' && ! $this->is_old_file_valid( $full_path ) ) {
			$this->processed_count++;
			@unlink( $full_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		} elseif ( !$this->is_valid( $full_path ) ) {
			@unlink( $full_path );
		}
	}
}
