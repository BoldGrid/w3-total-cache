<?php
/**
 * File: Extension_ImageService_Environment.php
 *
 * @since 2.2.0
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Extension_ImageService_Environment
 *
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
 */
class Extension_ImageService_Environment {
	/**
	 * Fixes environment in each wp-admin request.
	 *
	 * @since 2.2.0
	 *
	 * @param Config $config           Configuration.
	 * @param bool   $force_all_checks Force all checks.
	 * @throws Util_Environment_Exceptions Exceptions.
	 */
	public function fix_on_wpadmin_request( $config, $force_all_checks ) {
		$exs = new Util_Environment_Exceptions();

		if ( $config->get_boolean( 'config.check' ) || $force_all_checks ) {
			$extensions_active = $config->get_array( 'extensions.active' );

			if ( array_key_exists( 'imageservice', $extensions_active ) ) {
				$this->rules_add( $config, $exs );
			} else {
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
	 * @since 2.2.0
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
	 * @since 2.2.0
	 *
	 * @throws Util_Environment_Exceptions Exceptions.
	 */
	public function fix_after_deactivation() {
		$exs = new Util_Environment_Exceptions();

		$this->rules_remove( $exs );

		if ( count( $exs->exceptions() ) > 0 ) {
			throw $exs;
		}
	}

	/**
	 * Returns required rules for module.
	 *
	 * @since 2.2.0
	 *
	 * @param Config $config Configuration object.
	 * @return array
	 */
	public function get_required_rules( $config ) {
		return array(
			array(
				'filename' => Util_Rule::get_browsercache_rules_cache_path(),
				'content'  => $this->rules_generate_avif() . $this->rules_generate_webp(),
			),
		);
	}

	/**
	 * Write rewrite rules.
	 *
	 * @since 2.2.0
	 *
	 * @param Config                      $config Configuration.
	 * @param Util_Environment_Exceptions $exs    Exceptions.
	 *
	 * @throws Util_WpFile_FilesystemOperationException S/FTP form if it can't get the required filesystem credentials.
	 */
	private function rules_add( $config, $exs ) {
		// Remove existing rules first to ensure correct positioning on re-add.
		// This is necessary because add_rules replaces in place when rules exist.
		Util_Rule::remove_rules(
			$exs,
			Util_Rule::get_browsercache_rules_cache_path(),
			W3TC_MARKER_BEGIN_AVIF,
			W3TC_MARKER_END_AVIF
		);
		Util_Rule::remove_rules(
			$exs,
			Util_Rule::get_browsercache_rules_cache_path(),
			W3TC_MARKER_BEGIN_WEBP,
			W3TC_MARKER_END_WEBP
		);

		// Add AVIF rules first (higher priority).
		// Position before Page Cache so image requests are handled before Page Cache location blocks.
		Util_Rule::add_rules(
			$exs,
			Util_Rule::get_browsercache_rules_cache_path(),
			$this->rules_generate_avif(),
			W3TC_MARKER_BEGIN_AVIF,
			W3TC_MARKER_END_AVIF,
			array(
				W3TC_MARKER_BEGIN_PGCACHE_CACHE      => 0,
				W3TC_MARKER_BEGIN_PGCACHE_CORE       => 0,
				W3TC_MARKER_BEGIN_BROWSERCACHE_CACHE => 0,
				W3TC_MARKER_BEGIN_WORDPRESS          => 0,
			)
		);

		// Add WebP rules (lower priority than AVIF).
		// Position before Page Cache so image requests are handled before Page Cache location blocks.
		Util_Rule::add_rules(
			$exs,
			Util_Rule::get_browsercache_rules_cache_path(),
			$this->rules_generate_webp(),
			W3TC_MARKER_BEGIN_WEBP,
			W3TC_MARKER_END_WEBP,
			array(
				W3TC_MARKER_BEGIN_PGCACHE_CACHE      => 0,
				W3TC_MARKER_BEGIN_PGCACHE_CORE       => 0,
				W3TC_MARKER_BEGIN_BROWSERCACHE_CACHE => 0,
				W3TC_MARKER_BEGIN_WORDPRESS          => 0,
			)
		);
	}

	/**
	 * Generate AVIF rewrite rules (higher priority).
	 *
	 * @since X.X.X
	 *
	 * @see Dispatcher::nginx_rules_for_browsercache_section()
	 *
	 * @return string
	 */
	private function rules_generate_avif() {
		switch ( true ) {
			case Util_Environment::is_apache():
			case Util_Environment::is_litespeed():
				return '
# BEGIN W3TC AVIF
<IfModule mod_mime.c>
    AddType image/avif .avif
</IfModule>
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{HTTP_ACCEPT} image/avif
    RewriteCond %{REQUEST_FILENAME} (.+)\.(jpe?g|png|gif)$
    RewriteCond %1\.avif -f
    RewriteCond %{QUERY_STRING} !type=original
    RewriteRule (.+)\.(jpe?g|png|gif)$ $1.avif [NC,T=image/avif,E=avif,L]
</IfModule>
<IfModule mod_headers.c>
    <FilesMatch "\.(jpe?g|png|gif|avif)$">
        Header append Vary Accept
    </FilesMatch>
</IfModule>
# END W3TC AVIF

';

			case Util_Environment::is_nginx():
				$config = Dispatcher::config();

				/*
				 * Add Nginx rules only if Browser Cache is disabled.
				 * Otherwise, the rules are added in "BrowserCache_Environment_Nginx.php".
				 * @see BrowserCache_Environment_Nginx::generate_section()
				 */
				if ( ! $config->get_boolean( 'browsercache.enabled' ) ) {
					if ( $config->get_boolean( 'browsercache.no404wp' ) ) {
						$fallback = '=404';
					} else {
						$fallback = '/index.php$is_args$args';
					}

					return '
# BEGIN W3TC AVIF
location ~* ^(?<path>.+)\.(jpe?g|png|gif)$ {
    ' . implode( "\n    ", Dispatcher::nginx_rules_for_browsercache_section( $config, 'other' ) ) . '

    add_header Vary Accept;

    # Initialize variables.
    set $avif_ok 0;
    set $webp_ok 0;
    set $want_avif 0;
    set $want_webp 0;
    set $serve_avif 0;
    set $serve_webp 0;

    # Check file existence.
    if ( -f $document_root${path}.avif ) {
        set $avif_ok 1;
    }
    if ( -f $document_root${path}.webp ) {
        set $webp_ok 1;
    }

    # Check Accept header.
    if ( $http_accept ~* "image/avif" ) {
        set $want_avif 1;
    }
    if ( $http_accept ~* "image/webp" ) {
        set $want_webp 1;
    }

    # Combine conditions: serve AVIF if both accepted and file exists.
    if ( $want_avif = 1 ) {
        set $serve_avif $avif_ok;
    }
    if ( $serve_avif = 1 ) {
        rewrite ^ ${path}.avif last;
    }

    # Serve WEBP if accepted and file exists (only if AVIF wasn\'t served).
    if ( $want_webp = 1 ) {
        set $serve_webp $webp_ok;
    }
    if ( $serve_webp = 1 ) {
        rewrite ^ ${path}.webp last;
    }

    # Default: serve original file.
    try_files $uri ' . $fallback . ';
}

location ~* \.(avif|avifs)$ {
    default_type image/avif;
}
# END W3TC AVIF

';
				} else {
					return '';
				}

			default:
				return '';
		}
	}

	/**
	 * Generate WebP rewrite rules (lower priority than AVIF).
	 *
	 * @since X.X.X
	 *
	 * @see Dispatcher::nginx_rules_for_browsercache_section()
	 *
	 * @return string
	 */
	private function rules_generate_webp() {
		switch ( true ) {
			case Util_Environment::is_apache():
			case Util_Environment::is_litespeed():
				return '
# BEGIN W3TC WEBP
<IfModule mod_mime.c>
    AddType image/webp .webp
</IfModule>
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{HTTP_ACCEPT} image/webp
    RewriteCond %{REQUEST_FILENAME} (.+)\.(jpe?g|png|gif)$
    RewriteCond %1\.webp -f
    RewriteCond %{QUERY_STRING} !type=original
    RewriteRule (.+)\.(jpe?g|png|gif)$ $1.webp [NC,T=image/webp,E=webp,L]
</IfModule>
<IfModule mod_headers.c>
    <FilesMatch "\.(jpe?g|png|gif|webp)$">
        Header append Vary Accept
    </FilesMatch>
</IfModule>
# END W3TC WEBP

';

			case Util_Environment::is_nginx():
				$config = Dispatcher::config();

				/*
				 * Add Nginx rules only if Browser Cache is disabled.
				 * Otherwise, the rules are added in "BrowserCache_Environment_Nginx.php".
				 * @see BrowserCache_Environment_Nginx::generate_section()
				 */
				if ( ! $config->get_boolean( 'browsercache.enabled' ) ) {
					if ( $config->get_boolean( 'browsercache.no404wp' ) ) {
						$fallback = '=404';
					} else {
						$fallback = '/index.php?$args';
					}

					return '
# BEGIN W3TC WEBP
location ~* \.webp$ {
    default_type image/webp;
}
# END W3TC WEBP

';
				} else {
					return '';
				}

			default:
				return '';
		}
	}

	/**
	 * Removes cache directives
	 *
	 * @since 2.2.0
	 *
	 * @param Util_Environment_Exceptions $exs Exceptions.
	 *
	 * @throws Util_WpFile_FilesystemOperationException S/FTP form if it can't get the required filesystem credentials.
	 */
	private function rules_remove( $exs ) {
		// Remove AVIF rules.
		Util_Rule::remove_rules(
			$exs,
			Util_Rule::get_pgcache_rules_core_path(),
			W3TC_MARKER_BEGIN_AVIF,
			W3TC_MARKER_END_AVIF
		);

		// Remove WebP rules.
		Util_Rule::remove_rules(
			$exs,
			Util_Rule::get_pgcache_rules_core_path(),
			W3TC_MARKER_BEGIN_WEBP,
			W3TC_MARKER_END_WEBP
		);
	}
}
