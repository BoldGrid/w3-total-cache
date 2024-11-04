<?php
/**
 * File: UserExperience_PartyTown_Environment.php
 *
 * @since X.X.X
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
			if ( UserExperience_PartyTown_Extension::is_enabled() ) {
				try {
					$sw_path = ABSPATH . '/partytown-sw.js';
					if ( ! file_exists( $sw_path ) ) {
						$this->setup_sw( $config, $exs );
					}

					$this->rules_add( $config, $exs );
				} catch ( Util_WpFile_FilesystemOperationException $ex ) {
					$exs->push( $ex );
				}
			} else {
				$this->remove_sw( $exs );
				$this->rules_remove( $exs );
			}
		}

		if ( count( $exs->exceptions() ) > 0 ) {
			throw $exs;
		}
	}

	/**
	 * Fixes environment once event occurs.
	 *
	 * @since X.X.X
	 *
	 * @param Config $config     Config object.
	 * @param mixed  $event      Event.
	 * @param Config $old_config Old config object.
	 */
	public function fix_on_event( $config, $event, $old_config = null ) {
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

		$this->remove_sw( $exs );
		$this->rules_remove( $exs );

		if ( count( $exs->exceptions() ) > 0 ) {
			throw $exs;
		}
	}

	/**
	 * Copies the required file partytown-sw.js to the web root.
	 *
	 * @since X.X.X
	 *
	 * @param unknown                     $config Config.
	 * @param Util_Environment_Exceptions $exs    Exemptions.
	 */
	private function setup_sw( $config, $exs ) {
		try {
			Util_WpFile::copy_file( W3TC_INSTALL_FILE_PARTYTOWN_SRC . 'lib/partytown-sw.js', ABSPATH . 'partytown-sw.js' );
		} catch ( Util_WpFile_FilesystemOperationException $ex ) {
			$exs->push( $ex );
		}
	}

	/**
	 * Removes required file partytown-sw.js.
	 *
	 * @since X.X.X
	 *
	 * @param Util_Environment_Exceptions $exs Exceptions.
	 */
	private function remove_sw( $exs ) {
		$sw_path = ABSPATH . '/partytown-sw.js';
	
		if ( file_exists( $sw_path ) ) {
			try {
				Util_WpFile::delete_file( $sw_path );
			} catch ( Util_WpFile_FilesystemOperationException $ex ) {
				$exs->push( $ex );
			}
		}
	}

	/**
	 * Returns required rules for module.
	 *
	 * @since X.X.X
	 *
	 * @param Config $config Configuration object.
	 * @return array
	 */
	public function get_required_rules( $config ) {
		return array(
			array(
				'filename' => Util_Rule::get_browsercache_rules_cache_path(),
				'content'  => $this->rules_generate(),
			),
		);
	}

	/**
	 * Write rewrite rules.
	 *
	 * @since X.X.X
	 *
	 * @param Config                      $config Configuration.
	 * @param Util_Environment_Exceptions $exs    Exceptions.
	 *
	 * @throws Util_WpFile_FilesystemOperationException S/FTP form if it can't get the required filesystem credentials.
	 */
	private function rules_add( $config, $exs ) {
		Util_Rule::add_rules(
			$exs,
			Util_Rule::get_browsercache_rules_cache_path(),
			$this->rules_generate(),
			W3TC_MARKER_BEGIN_PARTYTOWN,
			W3TC_MARKER_END_PARTYTOWN,
			array(
				W3TC_MARKER_BEGIN_BROWSERCACHE_CACHE => 0,
				W3TC_MARKER_BEGIN_WORDPRESS          => 0,
			)
		);
	}

	/**
	 * Generate rewrite rules.
	 *
	 * @since X.X.X
	 *
	 * @see Dispatcher::nginx_rules_for_browsercache_section()
	 *
	 * @return string
	 */
	private function rules_generate() {
		$config = Dispatcher::config();
	
		// Dynamically generate the path to the PartyTown directory.
		$partytown_relative_path = wp_make_link_relative( plugins_url( 'lib/PartyTown/lib/', __FILE__ ) );
		$partytown_absolute_path = plugin_dir_path( __FILE__ ) . '/lib/PartyTown/lib/';
	
		switch ( true ) {
			case Util_Environment::is_apache():
			case Util_Environment::is_litespeed():
				return '
# BEGIN W3TC PartyTown
RewriteEngine On

# Redirect debug/partytown-sw.js to the absolute path
RewriteRule ^debug/partytown-sw\.js$ ' . $partytown_relative_path . 'debug/partytown-sw.js [L,R=301]

# Exclude partytown-sw.js at the root level
RewriteCond %{REQUEST_URI} !^/partytown-sw\.js$

# Redirect all other PartyTown files
RewriteCond %{REQUEST_URI} ^/(partytown)-.*\.js$
RewriteRule ^(partytown)-(.*)\.js$ ' . $partytown_relative_path . '$1-$2.js [L,R=301]
	
# Serve all Partytown JavaScript files with the correct MIME type
<FilesMatch "partytown-.*\.js$">
	ForceType application/javascript
</FilesMatch>
	
' . ( $config->get_boolean( array( 'user-experience-partytown', 'atomics' ) ) ? '
Header set Cross-Origin-Opener-Policy "same-origin"
Header set Cross-Origin-Embedder-Policy "require-corp"				
' : '' ) . '
# END W3TC PartyTown
';
	
			case Util_Environment::is_nginx():
				return '
# BEGIN W3TC PartyTown
# Redirect debug/partytown-sw.js to the absolute path
location = /debug/partytown-sw.js {
    return 301 ' . $partytown_absolute_path . 'debug/partytown-sw.js;
}

# Redirect PartyTown files except partytown-sw.js
location ~* ^/partytown-(?!sw)(.*)\.js$ {
    rewrite ^/partytown-(.*)\.js$ ' . $partytown_absolute_path . 'partytown-$1.js permanent;
}

# Serve the actual PartyTown JavaScript files with the correct MIME type
location ~ ^' . preg_quote($partytown_absolute_path, '/') . 'partytown-.*\.js$ {
    default_type application/javascript;
}
}
	
' . ( $config->get_boolean( array( 'user-experience-partytown', 'atomics' ) ) ? '
add_header Cross-Origin-Opener-Policy same-origin;
add_header Cross-Origin-Embedder-Policy require-corp;		
' : '' ) . '
# END W3TC PartyTown
';
	
			default:
				return '';
		}
	}

	/**
	 * Removes cache directives
	 *
	 * @since X.X.X
	 *
	 * @param Util_Environment_Exceptions $exs Exceptions.
	 *
	 * @throws Util_WpFile_FilesystemOperationException S/FTP form if it can't get the required filesystem credentials.
	 */
	private function rules_remove( $exs ) {
		Util_Rule::remove_rules(
			$exs,
			Util_Rule::get_browsercache_rules_cache_path(),
			W3TC_MARKER_BEGIN_PARTYTOWN,
			W3TC_MARKER_END_PARTYTOWN
		);
	}
}
