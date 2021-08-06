<?php
/**
 * File: Extension_ImageOptimizer_Environment.php
 *
 * @since X.X.X
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Extension_ImageOptimizer_Environment
 */
class Extension_ImageOptimizer_Environment {
	/**
	 * Fixes environment in each wp-admin request.
	 *
	 * @since X.X.X
	 *
	 * @param Config $config           Configuration.
	 * @param bool   $force_all_checks Force all checks.
	 * @throws Util_Environment_Exceptions
	 */
	public function fix_on_wpadmin_request( $config, $force_all_checks ) {
		$exs = new Util_Environment_Exceptions();

		if ( $config->get_boolean( 'config.check' ) || $force_all_checks ) {
			$extensions_active = $config->get_array( 'extensions.active' );

			if ( array_key_exists( 'optimager', $extensions_active ) ) {
				$this->rules_add( $config, $exs );
			} else {
				$this->rules_remove( $exs );
			}
		}

		if ( count( $exs->exceptions() ) > 0 )
			throw $exs;
	}

	/**
	 * Fixes environment once event occurs.
	 *
	 * @since X.X.X
	 */
	public function fix_on_event( $config, $event, $old_config = null ) {
	}

	/**
	 * Fixes environment after plugin deactivation
	 *
	 * @since X.X.X
	 *
	 * @throws Util_Environment_Exceptions
	 */
	public function fix_after_deactivation() {
		$exs = new Util_Environment_Exceptions();

		$this->rules_remove( $exs );

		if ( count( $exs->exceptions() ) > 0 )
			throw $exs;
	}

	/**
	 * Write rewrite rules.
	 *
	 * @since X.X.X
	 *
	 * @param Config $config Configuration.
	 * @param Util_Environment_Exceptions $exs Exceptions.
	 *
	 * @throws Util_WpFile_FilesystemOperationException with S/FTP form if it can't get the required filesystem credentials.
	 */
	private function rules_add( $config, $exs ) {
		Util_Rule::add_rules(
			$exs,
			Util_Rule::get_browsercache_rules_cache_path(),
			$this->rules_generate(),
			W3TC_MARKER_BEGIN_WEBP,
			W3TC_MARKER_END_WEBP,
			array(
				W3TC_MARKER_BEGIN_WORDPRESS => 0,
			)
		);
	}

	/**
	 * Generate rewrite rules.
	 *
	 * @since X.X.X
	 *
	 * @return string
	 */
	private function rules_generate() {
		switch ( true ) {
			case Util_Environment::is_apache():
			case Util_Environment::is_litespeed():
				return '
# BEGIN W3TC WEBP
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{HTTP_ACCEPT} image/webp
    RewriteCond %{REQUEST_FILENAME} (.+)\.(jpe?g|png|gif)$
    RewriteCond %1\.webp -f
    RewriteCond %{QUERY_STRING} !type=original
    RewriteRule (.+)\.(jpe?g|png)$ $1.webp [NC,T=image/webp,E=webp,L]
</IfModule>
<IfModule mod_headers.c>
    <FilesMatch "\.(jpe?g|png|gif)$">
        Header append Vary Accept
    </FilesMatch>
</IfModule>
AddType image/webp .webp
# END W3TC WEBP

';

			case Util_Environment::is_nginx():
				return '
# BEGIN W3TC WEBP
map $http_accept $webp_ext {
    default "";
    "~*webp" ".webp";
}

location ~* ^(.+)\.(png|jpg)$ {
    set $img_path $1;
    add_header Vary Accept;
    try_files $img_path$webp_ext $uri =404;
}
# END W3TC WEBP

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
	 * @throws Util_WpFile_FilesystemOperationException with S/FTP form if it can't get the required filesystem credentials.
	 */
	private function rules_remove( $exs ) {
		Util_Rule::remove_rules(
			$exs,
			Util_Rule::get_pgcache_rules_core_path(),
			W3TC_MARKER_BEGIN_WEBP,
			W3TC_MARKER_END_WEBP
		);
	}
}
