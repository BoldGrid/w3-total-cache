<?php
namespace W3TC;

/**
 * Rules generation for Nginx
 */
class BrowserCache_Environment_Nginx {
	/**
	 * Returns cache rules
	 *
	 * @param Config  $config
	 * @param bool    $cdnftp
	 * @return string
	 */
	public function generate( $config, $mime_types, $cdnftp = false ) {
		$cssjs_types = $mime_types['cssjs'];
		$cssjs_types = array_unique( $cssjs_types );
		$html_types = $mime_types['html'];
		$other_types = $mime_types['other'];
		$other_compression_types = $mime_types['other_compression'];

		$rules = '';
		$rules .= W3TC_MARKER_BEGIN_BROWSERCACHE_CACHE . "\n";

		if ( $config->get_boolean( 'browsercache.rewrite' ) ) {
			$core = Dispatcher::component( 'BrowserCache_Core' );
			$extensions = $core->get_replace_extensions( $config );

			$exts = implode( '|', $extensions );

			$rules .= "set \$w3tcbc_rewrite_filename '';\n";
			$rules .= "if (\$request_uri ~ '^(?<w3tcbc_base>.+)\.(x[0-9]{5})" .
				"(?<w3tcbc_ext>\.($exts))(\\?|$)') {\n";
			$rules .= "    set \$w3tcbc_rewrite_filename \$document_root\$w3tcbc_base\$w3tcbc_ext;\n";
			$rules .= "}\n";
			$rules .= "if (-f \$w3tcbc_rewrite_filename) {\n";
			$rules .= "    rewrite .* \$w3tcbc_base\$w3tcbc_ext;\n";
			$rules .= "}\n";
		}

		$cssjs_brotli = $config->get_boolean( 'browsercache.cssjs.brotli' );
		$html_brotli = $config->get_boolean( 'browsercache.html.brotli' );
		$other_brotli = $config->get_boolean( 'browsercache.other.brotli' );

		if ( $cssjs_brotli || $html_brotli || $other_brotli ) {
			$brotli_types = array();

			if ( $cssjs_brotli ) {
				$brotli_types = array_merge( $brotli_types, $cssjs_types );
			}

			if ( $html_brotli ) {
				$brotli_types = array_merge( $brotli_types, $html_types );
			}

			if ( $other_brotli ) {
				$brotli_types = array_merge( $brotli_types,
					$other_compression_types );
			}

			unset( $brotli_types['html|htm'] );

			// some nginx cant handle values longer than 47 chars
			unset( $brotli_types['odp'] );

			$rules .= "brotli on;\n";
			$rules .= 'brotli_types ' .
				implode( ' ', array_unique( $brotli_types ) ) . ";\n";
		}

		$cssjs_compression = $config->get_boolean( 'browsercache.cssjs.compression' );
		$html_compression = $config->get_boolean( 'browsercache.html.compression' );
		$other_compression = $config->get_boolean( 'browsercache.other.compression' );

		if ( $cssjs_compression || $html_compression || $other_compression ) {
			$compression_types = array();

			if ( $cssjs_compression ) {
				$compression_types = array_merge( $compression_types, $cssjs_types );
			}

			if ( $html_compression ) {
				$compression_types = array_merge( $compression_types, $html_types );
			}

			if ( $other_compression ) {
				$compression_types = array_merge( $compression_types,
					$other_compression_types );
			}

			unset( $compression_types['html|htm'] );

			// some nginx cant handle values longer than 47 chars
			unset( $compression_types['odp'] );

			$rules .= "gzip on;\n";
			$rules .= "gzip_types " .
				implode( ' ', array_unique( $compression_types ) ) . ";\n";
		}

		if ( $config->get_boolean( 'browsercache.no404wp' ) ) {
			$exceptions = $config->get_array( 'browsercache.no404wp.exceptions' );

			$impoloded = implode( '|', $exceptions );
			if ( !empty( $impoloded ) ) {
				$wp_uri = network_home_url( '', 'relative' );
				$wp_uri = rtrim( $wp_uri, '/' );

				$rules .= "location ~ (" . $impoloded . ") {\n";
				$rules .= '    try_files $uri $uri/ ' . $wp_uri .
					'/index.php?$args;' . "\n";
				$rules .= "}\n";
			}
		}

		foreach ( $mime_types as $type => $extensions ) {
			if ( $type != 'other_compression' ) {
				$this->generate_section( $config, $rules, $extensions, $type );
			}
		}

		$rules .= implode( "\n", $this->security_rules( $config ) );
		$rules .= W3TC_MARKER_END_BROWSERCACHE_CACHE . "\n";

		return $rules;
	}

	/**
	 * Returns security header directives
	 */
	private function security_rules( $config ) {
		$rules = [];

		if ( $config->get_boolean( 'browsercache.hsts' ) ||
			 $config->get_boolean( 'browsercache.security.xfo' )  ||
			 $config->get_boolean( 'browsercache.security.xss' )  ||
			 $config->get_boolean( 'browsercache.security.xcto' ) ||
			 $config->get_boolean( 'browsercache.security.pkp' )  ||
			 $config->get_boolean( 'browsercache.security.referrer.policy' )  ||
			 $config->get_boolean( 'browsercache.security.csp' )
		   ) {
			$lifetime = $config->get_integer( 'browsercache.other.lifetime' );

			if ( $config->get_boolean( 'browsercache.hsts' ) ) {
				$dir = $config->get_string( 'browsercache.security.hsts.directive' );
				$rules[] = "add_header Strict-Transport-Security \"max-age=$lifetime" . ( strpos( $dir,"inc" ) ? "; includeSubDomains" : "" ) . ( strpos( $dir, "pre" ) ? "; preload" : "" ) . "\";";
			}

			if ( $config->get_boolean( 'browsercache.security.xfo' ) ) {
				$dir = $config->get_string( 'browsercache.security.xfo.directive' );
				$url = trim( $config->get_string( 'browsercache.security.xfo.allow' ) );
				if ( empty( $url ) ) {
					$url = Util_Environment::home_url_maybe_https();
				}
				$rules[] = "add_header X-Frame-Options \"" . ( $dir == "same" ? "SAMEORIGIN" : ( $dir == "deny" ? "DENY" : "ALLOW-FROM $url" ) ) . "\";";
			}

			if ( $config->get_boolean( 'browsercache.security.xss' ) ) {
				$dir = $config->get_string( 'browsercache.security.xss.directive' );
				$rules[] = "add_header X-XSS-Protection \"" . ( $dir == "block" ? "1; mode=block" : $dir ) . "\";";
			}

			if ( $config->get_boolean( 'browsercache.security.xcto' ) ) {
				$rules[] = "add_header X-Content-Type-Options \"nosniff\";";
			}

			if ( $config->get_boolean( 'browsercache.security.pkp' ) ) {
				$pin = trim( $config->get_string( 'browsercache.security.pkp.pin' ) );
				$pinbak = trim( $config->get_string( 'browsercache.security.pkp.pin.backup' ) );
				$extra = $config->get_string( 'browsercache.security.pkp.extra' );
				$url = trim( $config->get_string( 'browsercache.security.pkp.report.url' ) );
				$rep_only = $config->get_string( 'browsercache.security.pkp.report.only' ) == '1' ? true : false;
				$rules[] = "add_header " . ( $rep_only ? "Public-Key-Pins-Report-Only" : "Public-Key-Pins" ) . " 'pin-sha256=\"$pin\"; pin-sha256=\"$pinbak\"; max-age=$lifetime" . ( strpos( $extra,"inc" ) ? "; includeSubDomains" : "" ) . ( !empty( $url ) ? "; report-uri=\"$url\"" : "" ) . "';";
			}

			if ( $config->get_boolean( 'browsercache.security.referrer.policy' ) ) {
				$dir = $config->get_string( 'browsercache.security.referrer.policy.directive' );
				$rules[] = "add_header Referrer-Policy \"" . ( $dir == "0" ? "" : $dir ) . "\";";
			}

			if ( $config->get_boolean( 'browsercache.security.csp' ) ) {
				$base = trim( $config->get_string( 'browsercache.security.csp.base' ) );
				$frame = trim( $config->get_string( 'browsercache.security.csp.frame' ) );
				$connect = trim( $config->get_string( 'browsercache.security.csp.connect' ) );
				$font = trim( $config->get_string( 'browsercache.security.csp.font' ) );
				$script = trim( $config->get_string( 'browsercache.security.csp.script' ) );
				$style = trim( $config->get_string( 'browsercache.security.csp.style' ) );
				$img = trim( $config->get_string( 'browsercache.security.csp.img' ) );
				$media = trim( $config->get_string( 'browsercache.security.csp.media' ) );
				$object = trim( $config->get_string( 'browsercache.security.csp.object' ) );
				$plugin = trim( $config->get_string( 'browsercache.security.csp.plugin' ) );
				$form = trim( $config->get_string( 'browsercache.security.csp.form' ) );
				$frame_ancestors = trim( $config->get_string( 'browsercache.security.csp.frame.ancestors' ) );
				$sandbox = $config->get_string( 'browsercache.security.csp.sandbox' );
				$default = trim( $config->get_string( 'browsercache.security.csp.default' ) );

				$dir = rtrim( ( !empty( $base ) ? "base-uri $base; " : "" ).
					   ( !empty( $frame ) ? "frame-src $frame; " : "" ).
					   ( !empty( $connect ) ? "connect-src $connect; " : "" ).
					   ( !empty( $font ) ? "font-src $font; " : "" ).
					   ( !empty( $script ) ? "script-src $script; " : "" ).
					   ( !empty( $style ) ? "style-src $style; " : "" ).
					   ( !empty( $img ) ? "img-src $img; " : "" ).
					   ( !empty( $media ) ? "media-src $media; " : "" ).
					   ( !empty( $object ) ? "object-src $object; " : "" ).
					   ( !empty( $plugin ) ? "plugin-types $plugin; " : "" ).
					   ( !empty( $form ) ? "form-action $form; " : "" ).
					   ( !empty( $frame_ancestors ) ? "frame-ancestors $frame_ancestors; " : "" ).
					   ( !empty( $sandbox ) ? "sandbox " . trim( $sandbox ) . "; " : "" ).
					   ( !empty( $default ) ? "default-src $default;" : "" ), "; " );

				if ( !empty( $dir ) ) {
					$rules[] = "add_header Content-Security-Policy \"$dir\";";
				}
			}
		}

		return $rules;
	}

	/**
	 * Adds cache rules for type to &$rules
	 *
	 * @param Config  $config
	 * @param string  $rules
	 * @param array   $mime_types
	 * @param string  $section
	 * @return void
	 */
	private function generate_section( $config, &$rules, $mime_types, $section ) {
		$expires = $config->get_boolean( 'browsercache.' . $section . '.expires' );
		$etag = $config->get_boolean( 'browsercache.' . $section . '.etag' );
		$cache_control = $config->get_boolean( 'browsercache.' . $section . '.cache.control' );
		$w3tc = $config->get_boolean( 'browsercache.' . $section . '.w3tc' );
		$last_modified = $config->get_boolean( 'browsercache.' . $section . '.last_modified' );

		if ( $etag || $expires || $cache_control || $w3tc || !$last_modified ) {
			$lifetime = $config->get_integer( 'browsercache.' . $section . '.lifetime' );

			$extensions = array_keys( $mime_types );

			// Remove ext from filesmatch if its the same as permalink extension
			$pext = strtolower( pathinfo( get_option( 'permalink_structure' ), PATHINFO_EXTENSION ) );
			if ( $pext ) {
				$extensions = $this->_remove_extension_from_list( $extensions, $pext );
			}

			$rules .= "location ~ \\.(" . implode( '|', $extensions ) . ")$ {\n";

			$subrules = Dispatcher::nginx_rules_for_browsercache_section(
				$config, $section );
			$rules .= '    ' . implode( "\n    ", $subrules ) . "\n";

			if ( !$config->get_boolean( 'browsercache.no404wp' ) ) {
				$wp_uri = network_home_url( '', 'relative' );
				$wp_uri = rtrim( $wp_uri, '/' );

				$rules .= '    try_files $uri $uri/ ' . $wp_uri .
					'/index.php?$args;' . "\n";
			}
			$rules .= "}\n";
		}
	}

	/**
	 * Returns directives plugin applies to files of specific section
	 * Without location
	 *
	 * $extra_add_headers_set specifies if other add_header directives will
	 *   be added to location block generated
	 */
	public function section_rules( $config, $section,
			$extra_add_headers_set = false ) {
		$rules = array();

		$expires = $config->get_boolean( "browsercache.$section.expires" );
		$lifetime = $config->get_integer( "browsercache.$section.lifetime" );

		if ( $expires ) {
			$rules[] = "expires ${lifetime}s;";
		}
		if ( version_compare( Util_Environment::get_server_version(), '1.3.3', '>=' ) ) {
			if ( $config->get_boolean( "browsercache.$section.etag" ) ) {
				$rules[] = 'etag on;';
			} else {
				$rules[] = 'etag off;';
			}
		}
		if ( $config->get_boolean( "browsercache.$section.last_modified" ) ) {
			$rules[] = 'if_modified_since exact;';
		} else {
			$rules[] = 'if_modified_since off;';
		}

		$add_header_rules = array();
		if ( $config->get_boolean( "browsercache.$section.cache.control" ) ) {
			$cache_policy = $config->get_string( "browsercache.$section.cache.policy" );

			switch ( $cache_policy ) {
			case 'cache':
				$add_header_rules[] = 'add_header Pragma "public";';
				$add_header_rules[] = 'add_header Cache-Control "public";';
				break;

			case 'cache_public_maxage':
				$add_header_rules[] = 'add_header Pragma "public";';

				if ( $expires ) {
					$add_header_rules[] = 'add_header Cache-Control "public";';
				} else {
					$add_header_rules[] = "add_header Cache-Control \"max-age=$lifetime, public\";";
				}
				break;

			case 'cache_validation':
				$add_header_rules[] = 'add_header Pragma "public";';
				$add_header_rules[] = 'add_header Cache-Control "public, must-revalidate, proxy-revalidate";';
				break;

			case 'cache_noproxy':
				$add_header_rules[] = 'add_header Pragma "public";';
				$add_header_rules[] = 'add_header Cache-Control "private, must-revalidate";';
				break;

			case 'cache_maxage':
				$add_header_rules[] = 'add_header Pragma "public";';

				if ( $expires ) {
					$add_header_rules[] = 'add_header Cache-Control "public, must-revalidate, proxy-revalidate";';
				} else {
					$add_header_rules[] = "add_header Cache-Control \"max-age=$lifetime, public, must-revalidate, proxy-revalidate\";";
				}
				break;

			case 'no_cache':
				$add_header_rules[] = 'add_header Pragma "no-cache";';
				$add_header_rules[] = 'add_header Cache-Control "max-age=0, private, no-store, no-cache, must-revalidate";';
				break;
			}
		}

		if ( $config->get_boolean( "browsercache.$section.w3tc" ) ) {
			$add_header_rules[] = 'add_header X-Powered-y "' .
				Util_Environment::w3tc_header() . '";';
		}

		if ( !empty( $add_header_rules ) || $extra_add_headers_set ) {
			$add_header_rules = array_merge( $add_header_rules,
				$this->security_rules( $config ) );
		}

		return array( 'add_header' => $add_header_rules, 'other' => $rules );
	}
}
