<?php
/**
 * File: UserExperience_PartyTown_Environment.php
 *
 * @since 2.2.0
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: UserExperience_PartyTown_Environment
 */
class UserExperience_PartyTown_Environment {
	/**
	 * Fixes environment in each wp-admin request.
	 *
	 * @since X.X.X
	 *
	 * @param Config $config           Configuration.
	 * @param bool   $force_all_checks Force all checks.
	 *
	 * @throws Util_Environment_Exceptions Exceptions.
	 */
	public function fix_on_wpadmin_request( $config, $force_all_checks ) {
		$exs = new Util_Environment_Exceptions();

		if ( $config->get_boolean( 'config.check' ) || $force_all_checks ) {
			$dst = get_home_path() . '/partytown-sw.js';

			if ( UserExperience_PartyTown_Extension::is_enabled() && ! file_exists( $dst ) ) {
				try {
					$this->create_required_files( $config, $exs );
				} catch ( Util_WpFile_FilesystemOperationException $ex ) {
					$exs->push( $ex );
				}
			} else {
				try {
					$this->remove_required_files( $exs );
				} catch ( Util_WpFile_FilesystemOperationException $ex ) {
					$exs->push( $ex );
				}
			}
		}

		if ( count( $exs->exceptions() ) > 0 ) {
			throw $exs;
		}
	}

	/**
	 * Fixes environment after plugin deactivation
	 *
	 * @since X.X.X
	 *
	 * @throws Util_Environment_Exceptions Exceptions.
	 */
	public function fix_after_deactivation() {
		$exs = new Util_Environment_Exceptions();

		try {
			$this->remove_required_files( $exs );
		} catch ( Util_WpFile_FilesystemOperationException $ex ) {
			$exs->push( $ex );
		}

		if ( count( $exs->exceptions() ) > 0 ) {
			throw $exs;
		}
	}

	/**
	 * Checks if addins in wp-content is available and correct version.
	 *
	 * @since X.X.X
	 *
	 * @param unknown                     $config Config.
	 * @param Util_Environment_Exceptions $exs    Exemptions.
	 */
	private function create_required_files( $config, $exs ) {
		$src = W3TC_INSTALL_FILE_PARTYTOWN_SRC . 'partytown-sw.js';
		$dst = get_home_path() . '/partytown-sw.js';

		try {
			Util_WpFile::copy_file( $src, $dst );
		} catch ( Util_WpFile_FilesystemOperationException $ex ) {
			$exs->push( $ex );
		}
	}

	/**
	 * Removes required files such as partytown-sw.js.
	 *
	 * @since X.X.X
	 *
	 * @param Util_Environment_Exceptions $exs Exceptions.
	 */
	private function remove_required_files( $exs ) {
		$dst = get_home_path() . '/partytown-sw.js';

		// Check if the file exists before attempting to delete it.
		if ( file_exists( $dst ) ) {
			try {
				Util_WpFile::delete_file( $dst );
			} catch ( Util_WpFile_FilesystemOperationException $ex ) {
				$exs->push( $ex );
			}
		}
	}
}
