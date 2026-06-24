<?php
/**
 * File: BrowserCache_Environment_Nginx.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class BrowserCache_Environment_Nginx
 *
 * Rules generation for Nginx
 *
 * phpcs:disable Squiz.Strings.DoubleQuoteUsage.NotRequired
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
 */
class BrowserCache_Environment_Nginx {
	/**
	 * Config
	 *
	 * @var Config
	 */
	private $w3tc_c;

	/**
	 * Constructor
	 *
	 * @param Config $w3tc_config Config.
	 *
	 * @return void
	 */
	public function __construct( $w3tc_config ) {
		$this->w3tc_c = $w3tc_config;
	}

	/**
	 * Returns required rules
	 *
	 * @param array $mime_types Mime types.
	 *
	 * @return array
	 */
	public function get_required_rules( $mime_types ) {
		return array(
			array(
				'filename' => Util_Rule::get_nginx_rules_path(),
				'content'  => $this->generate( $mime_types ),
			),
		);
	}

	/**
	 * Returns cache rules
	 *
	 * @param array $mime_types Mime types.
	 * @param bool  $cdnftp     CDN FTP flag.
	 *
	 * @return string
	 */
	public function generate( $mime_types, $cdnftp = false ) {
		$cssjs_types             = $mime_types['cssjs'];
		$cssjs_types             = array_unique( $cssjs_types );
		$html_types              = $mime_types['html'];
		$other_types             = $mime_types['other'];
		$other_compression_types = $mime_types['other_compression'];

		$rules  = '';
		$rules .= W3TC_MARKER_BEGIN_BROWSERCACHE_CACHE . "\n";

		if ( $this->w3tc_c->get_boolean( 'browsercache.rewrite' ) ) {
			$core       = Dispatcher::component( 'BrowserCache_Core' );
			$extensions = $core->get_replace_extensions( $this->w3tc_c );

			$exts = implode( '|', $extensions );

			$rules .= "set \$w3tcbc_rewrite_filename '';\n";
			$rules .= "set \$w3tcbc_rewrite_uri '';\n";
			$rules .= "if (\$uri ~ '^(?<w3tcbc_base>.+)\.(x[0-9]{5})(?<w3tcbc_ext>\.($exts))$') {\n";
			$rules .= "    set \$w3tcbc_rewrite_filename \$document_root\$w3tcbc_base\$w3tcbc_ext;\n";
			$rules .= "    set \$w3tcbc_rewrite_uri \$w3tcbc_base\$w3tcbc_ext;\n";
			$rules .= "}\n";

			if ( Util_Environment::is_wpmu() && ! Util_Environment::is_wpmu_subdomain() ) {
				// WPMU subdir extra rewrite.
				if ( defined( 'W3TC_HOME_URI' ) ) {
					$home_uri = W3TC_HOME_URI;
				} else {
					$primary_blog_id = get_network()->site_id;
					$home_uri        = Util_Environment::url_to_uri( get_home_url( $primary_blog_id ) );
				}

				$rules .= "if (\$uri ~ '^$home_uri/[_0-9a-zA-Z-]+(?<w3tcbc_base>/wp-.+)\.(x[0-9]{5})(?<w3tcbc_ext>\.($exts))$') {\n";
				$rules .= "    set \$w3tcbc_rewrite_filename \$document_root$home_uri\$w3tcbc_base\$w3tcbc_ext;\n";
				$rules .= "    set \$w3tcbc_rewrite_uri $home_uri\$w3tcbc_base\$w3tcbc_ext;\n";
				$rules .= "}\n";
			}

			$rules .= "if (-f \$w3tcbc_rewrite_filename) {\n";
			$rules .= "    rewrite .* \$w3tcbc_rewrite_uri;\n";
			$rules .= "}\n";
		}

		$cssjs_brotli = $this->w3tc_c->get_boolean( 'browsercache.cssjs.brotli' );
		$html_brotli  = $this->w3tc_c->get_boolean( 'browsercache.html.brotli' );
		$other_brotli = $this->w3tc_c->get_boolean( 'browsercache.other.brotli' );

		if ( $cssjs_brotli || $html_brotli || $other_brotli ) {
			$brotli_types = array();

			if ( $cssjs_brotli ) {
				$brotli_types = array_merge( $brotli_types, $cssjs_types );
			}

			if ( $html_brotli ) {
				$brotli_types = array_merge( $brotli_types, $html_types );
			}

			if ( $other_brotli ) {
				$brotli_types = array_merge( $brotli_types, $other_compression_types );
			}

			unset( $brotli_types['html|htm'] );

			// some nginx cant handle values longer than 47 chars.
			unset( $brotli_types['odp'] );

			$rules .= "brotli on;\n";
			$rules .= 'brotli_types ' . implode( ' ', array_unique( $brotli_types ) ) . ";\n";
		}

		$cssjs_compression = $this->w3tc_c->get_boolean( 'browsercache.cssjs.compression' );
		$html_compression  = $this->w3tc_c->get_boolean( 'browsercache.html.compression' );
		$other_compression = $this->w3tc_c->get_boolean( 'browsercache.other.compression' );

		if ( $cssjs_compression || $html_compression || $other_compression ) {
			$compression_types = array();

			if ( $cssjs_compression ) {
				$compression_types = array_merge( $compression_types, $cssjs_types );
			}

			if ( $html_compression ) {
				$compression_types = array_merge( $compression_types, $html_types );
			}

			if ( $other_compression ) {
				$compression_types = array_merge( $compression_types, $other_compression_types );
			}

			unset( $compression_types['html|htm'] );

			// some nginx cant handle values longer than 47 chars.
			unset( $compression_types['odp'] );

			$rules .= "gzip on;\n";
			$rules .= "gzip_types " . implode( ' ', array_unique( $compression_types ) ) . ";\n";
		}

		if ( $this->w3tc_c->get_boolean( 'browsercache.no404wp' ) ) {
			$exceptions = $this->w3tc_c->get_array( 'browsercache.no404wp.exceptions' );

			$impoloded = implode( '|', $exceptions );
			if ( ! empty( $impoloded ) ) {
				$wp_uri = network_home_url( '', 'relative' );
				$wp_uri = rtrim( $wp_uri, '/' );

				$rules .= "location ~ (" . $impoloded . ") {\n";
				$rules .= '    try_files $uri $uri/ ' . $wp_uri . '/index.php?$args;' . "\n";
				$rules .= "}\n";
			}
		}

		$this->generate_section( $rules, $mime_types['cssjs'], 'cssjs' );
		$this->generate_section( $rules, $mime_types['html'], 'html' );
		$this->generate_section( $rules, $mime_types['other'], 'other' );

		$rules .= implode( "\n", $this->security_rules() ) . "\n";
		$rules .= W3TC_MARKER_END_BROWSERCACHE_CACHE . "\n";

		return $rules;
	}

	/**
	 * Returns security header directives
	 *
	 * @return array
	 */
	private function security_rules() {
		$rules = array();

		/**
		 * Local shorthand: render-time strip set for every admin-set
		 * security-header string before it lands inside a quoted
		 * `add_header` value. Matches the Apache renderer in
		 * `BrowserCache_Environment::security_rules()` so an admin-set
		 * value carrying a CR/LF / NUL / `<` / `>` / `"` cannot start
		 * a fresh directive on the next line, regardless of the server
		 * flavour being emitted.
		 */
		$w3tc_c = $this->w3tc_c;
		$g      = function ( $w3tc_key ) use ( $w3tc_c ) {
			return Util_Rule::sanitize_directive_value( $w3tc_c->get_string( $w3tc_key ) );
		};

		if ( $this->w3tc_c->get_boolean( 'browsercache.hsts' ) ||
			$this->w3tc_c->get_boolean( 'browsercache.security.xfo' ) ||
			$this->w3tc_c->get_boolean( 'browsercache.security.xss' ) ||
			$this->w3tc_c->get_boolean( 'browsercache.security.xcto' ) ||
			$this->w3tc_c->get_boolean( 'browsercache.security.pkp' ) ||
			$this->w3tc_c->get_boolean( 'browsercache.security.referrer.policy' ) ||
			$this->w3tc_c->get_boolean( 'browsercache.security.csp' ) ||
			$this->w3tc_c->get_boolean( 'browsercache.security.cspro' ) ||
			$this->w3tc_c->get_boolean( 'browsercache.security.fp' )
		) {
			$lifetime = $this->w3tc_c->get_integer( 'browsercache.other.lifetime' );

			if ( $this->w3tc_c->get_boolean( 'browsercache.hsts' ) ) {
				$dir     = $g( 'browsercache.security.hsts.directive' );
				$rules[] = "add_header Strict-Transport-Security \"max-age=$lifetime" . ( strpos( $dir, "inc" ) ? "; includeSubDomains" : "" ) . ( strpos( $dir, "pre" ) ? "; preload" : "" ) . "\";";
			}

			if ( $this->w3tc_c->get_boolean( 'browsercache.security.xfo' ) ) {
				$dir      = $g( 'browsercache.security.xfo.directive' );
				$w3tc_url = trim( $g( 'browsercache.security.xfo.allow' ) );
				if ( empty( $w3tc_url ) ) {
					$w3tc_url = Util_Rule::sanitize_directive_value( Util_Environment::home_url_maybe_https() );
				}
				$rules[] = "add_header X-Frame-Options \"" . ( 'same' === $dir ? "SAMEORIGIN" : ( 'deny' === $dir ? "DENY" : "ALLOW-FROM $w3tc_url" ) ) . "\";";
			}

			if ( $this->w3tc_c->get_boolean( 'browsercache.security.xss' ) ) {
				$dir     = $g( 'browsercache.security.xss.directive' );
				$rules[] = "add_header X-XSS-Protection \"" . ( 'block' === $dir ? "1; mode=block" : $dir ) . "\";";
			}

			if ( $this->w3tc_c->get_boolean( 'browsercache.security.xcto' ) ) {
				$rules[] = "add_header X-Content-Type-Options \"nosniff\";";
			}

			if ( $this->w3tc_c->get_boolean( 'browsercache.security.pkp' ) ) {
				$pin      = trim( $g( 'browsercache.security.pkp.pin' ) );
				$pinbak   = trim( $g( 'browsercache.security.pkp.pin.backup' ) );
				$extra    = $g( 'browsercache.security.pkp.extra' );
				$w3tc_url = trim( $g( 'browsercache.security.pkp.report.url' ) );
				$rep_only = '1' === $g( 'browsercache.security.pkp.report.only' ) ? true : false;
				$rules[]  = "add_header " . ( $rep_only ? "Public-Key-Pins-Report-Only" : "Public-Key-Pins" ) . " 'pin-sha256=\"$pin\"; pin-sha256=\"$pinbak\"; max-age=$lifetime" . ( strpos( $extra, "inc" ) ? "; includeSubDomains" : "" ) . ( ! empty( $w3tc_url ) ? "; report-uri=\"$w3tc_url\"" : "" ) . "';";
			}

			if ( $this->w3tc_c->get_boolean( 'browsercache.security.referrer.policy' ) ) {
				$dir     = $g( 'browsercache.security.referrer.policy.directive' );
				$rules[] = "add_header Referrer-Policy \"" . ( '0' === $dir ? "" : $dir ) . "\";";
			}

			if ( $this->w3tc_c->get_boolean( 'browsercache.security.csp' ) ) {
				$base            = trim( $g( 'browsercache.security.csp.base' ) );
				$frame           = trim( $g( 'browsercache.security.csp.frame' ) );
				$connect         = trim( $g( 'browsercache.security.csp.connect' ) );
				$font            = trim( $g( 'browsercache.security.csp.font' ) );
				$script          = trim( $g( 'browsercache.security.csp.script' ) );
				$style           = trim( $g( 'browsercache.security.csp.style' ) );
				$img             = trim( $g( 'browsercache.security.csp.img' ) );
				$media           = trim( $g( 'browsercache.security.csp.media' ) );
				$object          = trim( $g( 'browsercache.security.csp.object' ) );
				$plugin          = trim( $g( 'browsercache.security.csp.plugin' ) );
				$form            = trim( $g( 'browsercache.security.csp.form' ) );
				$frame_ancestors = trim( $g( 'browsercache.security.csp.frame.ancestors' ) );
				$sandbox         = trim( $g( 'browsercache.security.csp.sandbox' ) );
				$child           = trim( $g( 'browsercache.security.csp.child' ) );
				$manifest        = trim( $g( 'browsercache.security.csp.manifest' ) );
				$scriptelem      = trim( $g( 'browsercache.security.csp.scriptelem' ) );
				$scriptattr      = trim( $g( 'browsercache.security.csp.scriptattr' ) );
				$styleelem       = trim( $g( 'browsercache.security.csp.styleelem' ) );
				$styleattr       = trim( $g( 'browsercache.security.csp.styleattr' ) );
				$worker          = trim( $g( 'browsercache.security.csp.worker' ) );
				$default         = trim( $g( 'browsercache.security.csp.default' ) );

				$dir = rtrim(
					( ! empty( $base ) ? "base-uri $base; " : '' ) .
						( ! empty( $frame ) ? "frame-src $frame; " : '' ) .
						( ! empty( $connect ) ? "connect-src $connect; " : '' ) .
						( ! empty( $font ) ? "font-src $font; " : '' ) .
						( ! empty( $script ) ? "script-src $script; " : '' ) .
						( ! empty( $style ) ? "style-src $style; " : '' ) .
						( ! empty( $img ) ? "img-src $img; " : '' ) .
						( ! empty( $media ) ? "media-src $media; " : '' ) .
						( ! empty( $object ) ? "object-src $object; " : '' ) .
						( ! empty( $plugin ) ? "plugin-types $plugin; " : '' ) .
						( ! empty( $form ) ? "form-action $form; " : '' ) .
						( ! empty( $frame_ancestors ) ? "frame-ancestors $frame_ancestors; " : '' ) .
						( ! empty( $sandbox ) ? "sandbox $sandbox; " : '' ) .
						( ! empty( $child ) ? "child-src $child; " : '' ) .
						( ! empty( $manifest ) ? "manifest-src $manifest; " : '' ) .
						( ! empty( $scriptelem ) ? "script-src-elem $scriptelem; " : '' ) .
						( ! empty( $scriptattr ) ? "script-src-attr $scriptattr; " : '' ) .
						( ! empty( $styleelem ) ? "style-src-elem $styleelem; " : '' ) .
						( ! empty( $styleattr ) ? "style-src-attr $styleattr; " : '' ) .
						( ! empty( $worker ) ? "worker-src $worker; " : '' ) .
						( ! empty( $default ) ? "default-src $default;" : '' ),
					'; '
				);

				if ( ! empty( $dir ) ) {
					$rules[] = "add_header Content-Security-Policy \"$dir\";";
				}
			}

			if ( $this->w3tc_c->get_boolean( 'browsercache.security.cspro' ) && ( ! empty( $g( 'browsercache.security.cspro.reporturi' ) ) || ! empty( $g( 'browsercache.security.cspro.reportto' ) ) ) ) {
				$base            = trim( $g( 'browsercache.security.cspro.base' ) );
				$reporturi       = trim( $g( 'browsercache.security.cspro.reporturi' ) );
				$reportto        = trim( $g( 'browsercache.security.cspro.reportto' ) );
				$frame           = trim( $g( 'browsercache.security.cspro.frame' ) );
				$connect         = trim( $g( 'browsercache.security.cspro.connect' ) );
				$font            = trim( $g( 'browsercache.security.cspro.font' ) );
				$script          = trim( $g( 'browsercache.security.cspro.script' ) );
				$style           = trim( $g( 'browsercache.security.cspro.style' ) );
				$img             = trim( $g( 'browsercache.security.cspro.img' ) );
				$media           = trim( $g( 'browsercache.security.cspro.media' ) );
				$object          = trim( $g( 'browsercache.security.cspro.object' ) );
				$plugin          = trim( $g( 'browsercache.security.cspro.plugin' ) );
				$form            = trim( $g( 'browsercache.security.cspro.form' ) );
				$frame_ancestors = trim( $g( 'browsercache.security.cspro.frame.ancestors' ) );
				$sandbox         = trim( $g( 'browsercache.security.cspro.sandbox' ) );
				$child           = trim( $g( 'browsercache.security.csp.child' ) );
				$manifest        = trim( $g( 'browsercache.security.csp.manifest' ) );
				$scriptelem      = trim( $g( 'browsercache.security.csp.scriptelem' ) );
				$scriptattr      = trim( $g( 'browsercache.security.csp.scriptattr' ) );
				$styleelem       = trim( $g( 'browsercache.security.csp.styleelem' ) );
				$styleattr       = trim( $g( 'browsercache.security.csp.styleattr' ) );
				$worker          = trim( $g( 'browsercache.security.csp.worker' ) );
				$default         = trim( $g( 'browsercache.security.cspro.default' ) );

				$dir = rtrim(
					( ! empty( $base ) ? "base-uri $base; " : '' ) .
						( ! empty( $reporturi ) ? "report-uri $reporturi; " : '' ) .
						( ! empty( $reportto ) ? "report-to $reportto; " : '' ) .
						( ! empty( $frame ) ? "frame-src $frame; " : '' ) .
						( ! empty( $connect ) ? "connect-src $connect; " : '' ) .
						( ! empty( $font ) ? "font-src $font; " : '' ) .
						( ! empty( $script ) ? "script-src $script; " : '' ) .
						( ! empty( $style ) ? "style-src $style; " : '' ) .
						( ! empty( $img ) ? "img-src $img; " : '' ) .
						( ! empty( $media ) ? "media-src $media; " : '' ) .
						( ! empty( $object ) ? "object-src $object; " : '' ) .
						( ! empty( $plugin ) ? "plugin-types $plugin; " : '' ) .
						( ! empty( $form ) ? "form-action $form; " : '' ) .
						( ! empty( $frame_ancestors ) ? "frame-ancestors $frame_ancestors; " : '' ) .
						( ! empty( $sandbox ) ? "sandbox $sandbox; " : '' ) .
						( ! empty( $child ) ? "child-src $child; " : '' ) .
						( ! empty( $manifest ) ? "manifest-src $manifest; " : '' ) .
						( ! empty( $scriptelem ) ? "script-src-elem $scriptelem; " : '' ) .
						( ! empty( $scriptattr ) ? "script-src-attr $scriptattr; " : '' ) .
						( ! empty( $styleelem ) ? "style-src-elem $styleelem; " : '' ) .
						( ! empty( $styleattr ) ? "style-src-attr $styleattr; " : '' ) .
						( ! empty( $worker ) ? "worker-src $worker; " : '' ) .
						( ! empty( $default ) ? "default-src $default;" : '' ),
					'; '
				);

				if ( ! empty( $dir ) ) {
					$rules[] = "add_header Content-Security-Policy-Report-Only \"$dir\";";
				}
			}

			if ( $this->w3tc_c->get_boolean( 'browsercache.security.fp' ) ) {
				$w3tc_fp_values = $this->w3tc_c->get_array( 'browsercache.security.fp.values' );

				$feature_v    = array();
				$permission_v = array();
				foreach ( $w3tc_fp_values as $w3tc_key => $w3tc_value ) {
					if ( ! empty( $w3tc_value ) ) {
						/**
						 * Strip the existing quote pair AND any directive-
						 * terminating bytes (CR/LF/NUL/<>/") so a config
						 * value carrying a `\n"` cannot close the
						 * `add_header X "..."` argument and start a fresh
						 * Nginx directive on the next line. Matches the
						 * Apache renderer at
						 * `BrowserCache_Environment::security_rules()`.
						 */
						$w3tc_key   = Util_Rule::sanitize_directive_value( (string) $w3tc_key );
						$w3tc_value = Util_Rule::sanitize_directive_value( str_replace( array( '"', "'" ), '', $w3tc_value ) );

						$feature_v[]    = "$w3tc_key '$w3tc_value'";
						$permission_v[] = "$w3tc_key=($w3tc_value)";
					}
				}

				if ( ! empty( $feature_v ) ) {
					$rules[] = 'add_header Feature-Policy "' . implode( ';', $feature_v ) . "\";\n";
				}

				if ( ! empty( $permission_v ) ) {
					$rules[] = 'add_header Permissions-Policy "' . implode( ',', $permission_v ) . "\";\n";
				}
			}
		}

		return $rules;
	}

	/**
	 * Adds cache rules for type to &$rules.
	 *
	 * @param string $rules      Rules.
	 * @param array  $mime_types MIME types.
	 * @param string $section    Section.
	 *
	 * @return void
	 */
	private function generate_section( &$rules, $mime_types, $section ) {
		$expires       = $this->w3tc_c->get_boolean( 'browsercache.' . $section . '.expires' );
		$etag          = $this->w3tc_c->get_boolean( 'browsercache.' . $section . '.etag' );
		$cache_control = $this->w3tc_c->get_boolean( 'browsercache.' . $section . '.cache.control' );
		$w3tc          = $this->w3tc_c->get_boolean( 'browsercache.' . $section . '.w3tc' );
		$last_modified = $this->w3tc_c->get_boolean( 'browsercache.' . $section . '.last_modified' );

		if ( $etag || $expires || $cache_control || $w3tc || ! $last_modified ) {
			$mime_types2 = apply_filters(
				'w3tc_browsercache_rules_section_extensions',
				$mime_types,
				$this->w3tc_c,
				$section
			);
			$extensions  = array_keys( $mime_types2 );

			// Remove ext from filesmatch if its the same as permalink extension.
			$pext = strtolower( pathinfo( get_option( 'permalink_structure' ), PATHINFO_EXTENSION ) );

			if ( $pext ) {
				$extensions = Util_Rule::remove_extension_from_list( $extensions, $pext );
			}

			/**
			 * Add rules for the Image Service extension, if active.
			 * These must be at the same level as the parent location block, not nested.
			 */
			if ( 'other' === $section && array_key_exists( 'imageservice', $this->w3tc_c->get_array( 'extensions.active' ) ) ) {
				// Exclude image extensions from the parent location block.
				$image_extensions = array( 'avif', 'avifs', 'webp', 'jpg', 'jpeg', 'jpe', 'png', 'gif' );
				$extensions       = array_filter(
					$extensions,
					function ( $w3tc_ext ) use ( $image_extensions ) {
						// Check if extension is a single value or pipe-delimited.
						$ext_parts = explode( '|', $w3tc_ext );
						// Return false if any part matches an image extension (exclude it).
						foreach ( $ext_parts as $part ) {
							if ( in_array( $part, $image_extensions, true ) ) {
								return false;
							}
						}
						return true;
					}
				);
				// Reindex array to ensure sequential numeric keys.
				$extensions = array_values( $extensions );

				// Direct AVIF / AVIFS location block (same level, not nested).
				$rules .= '# Direct AVIF / AVIFS.' . "\n" .
					'location ~* \.(avif|avifs)$ {' . "\n" .
					'    default_type image/avif;' . "\n" .
					'    ' . implode( "\n    ", Dispatcher::nginx_rules_for_browsercache_section( $this->w3tc_c, $section, true ) ) . "\n\n" .
					'    add_header Vary Accept;' . "\n\n";

				if ( $this->w3tc_c->get_boolean( 'browsercache.no404wp' ) ) {
					$rules .= '    try_files $uri =404;' . "\n";
				} else {
					$rules .= '    try_files $uri /index.php$is_args$args;' . "\n";
				}

				$rules .= '}' . "\n\n" .
					'# Direct WEBP.' . "\n" .
					'location ~* \.webp$ {' . "\n" .
					'    default_type image/webp;' . "\n" .
					'    ' . implode( "\n    ", Dispatcher::nginx_rules_for_browsercache_section( $this->w3tc_c, $section, true ) ) . "\n\n" .
					'    add_header Vary Accept;' . "\n\n";

				if ( $this->w3tc_c->get_boolean( 'browsercache.no404wp' ) ) {
					$rules .= '    try_files $uri =404;' . "\n";
				} else {
					$rules .= '    try_files $uri /index.php$is_args$args;' . "\n";
				}

				$rules .= '}' . "\n\n" .
					'# Negotiation for original JPEG/PNG/GIF.' . "\n" .
					'location ~* ^(?<path>.+)\.(jpe?g|png|gif)$ {' . "\n" .
					'    add_header Vary Accept;' . "\n\n";

				// Add location-level directives (expires, etag, if_modified_since, etc.) and headers.
				$subrules = Dispatcher::nginx_rules_for_browsercache_section( $this->w3tc_c, $section, true );
				$rules   .= '    ' . implode( "\n    ", $subrules ) . "\n\n";

				// Initialize all variables.
				$rules .= '    set $avif_ok 0;' . "\n";
				$rules .= '    set $webp_ok 0;' . "\n";
				$rules .= '    set $want_avif 0;' . "\n";
				$rules .= '    set $want_webp 0;' . "\n";
				$rules .= '    set $serve_avif 0;' . "\n";
				$rules .= '    set $serve_webp 0;' . "\n\n";

				// Check file existence.
				$rules .= '    if ( -f $document_root${path}.avif ) {' . "\n";
				$rules .= '        set $avif_ok 1;' . "\n";
				$rules .= '    }' . "\n";
				$rules .= '    if ( -f $document_root${path}.webp ) {' . "\n";
				$rules .= '        set $webp_ok 1;' . "\n";
				$rules .= '    }' . "\n\n";

				// Check Accept header.
				$rules .= '    if ( $http_accept ~* "avif" ) {' . "\n";
				$rules .= '        set $want_avif 1;' . "\n";
				$rules .= '    }' . "\n";
				$rules .= '    if ( $http_accept ~* "webp" ) {' . "\n";
				$rules .= '        set $want_webp 1;' . "\n";
				$rules .= '    }' . "\n\n";

				// Combine conditions: serve AVIF if both accepted and file exists.
				$rules .= '    if ( $want_avif = 1 ) {' . "\n";
				$rules .= '        set $serve_avif $avif_ok;' . "\n";
				$rules .= '    }' . "\n";
				$rules .= '    if ( $serve_avif = 1 ) {' . "\n";
				$rules .= '        rewrite ^ ${path}.avif last;' . "\n";
				$rules .= '    }' . "\n\n";

				// Serve WEBP if accepted and file exists (only if AVIF wasn't served).
				$rules .= '    if ( $want_webp = 1 ) {' . "\n";
				$rules .= '        set $serve_webp $webp_ok;' . "\n";
				$rules .= '    }' . "\n";
				$rules .= '    if ( $serve_webp = 1 ) {' . "\n";
				$rules .= '        rewrite ^ ${path}.webp last;' . "\n";
				$rules .= '    }' . "\n\n";

				// Default: serve original file.
				if ( $this->w3tc_c->get_boolean( 'browsercache.no404wp' ) ) {
					$rules .= '    try_files $uri =404;' . "\n";
				} else {
					$wp_uri = network_home_url( '', 'relative' );
					$wp_uri = rtrim( $wp_uri, '/' );
					$rules .= '    try_files $uri $uri/ ' . $wp_uri . '/index.php?$args;' . "\n";
				}

				$rules .= '}' . "\n\n";
			}

			// Parent location block for all other extensions (excluding image extensions if Image Service is active).
			if ( ! empty( $extensions ) ) {
				$rules .= 'location ~ \\.(' . implode( '|', $extensions ) . ')$ {' . "\n";

				$subrules = Dispatcher::nginx_rules_for_browsercache_section( $this->w3tc_c, $section );
				$rules   .= '    ' . implode( "\n    ", $subrules ) . "\n";

				if ( ! $this->w3tc_c->get_boolean( 'browsercache.no404wp' ) ) {
					$wp_uri = network_home_url( '', 'relative' );
					$wp_uri = rtrim( $wp_uri, '/' );
					$rules .= '    try_files $uri $uri/ ' . $wp_uri . '/index.php?$args;' . "\n";
				}

				$rules .= '}' . "\n\n";
			}
		}
	}

	/**
	 * Returns directives plugin applies to files of specific section without location
	 *
	 * $extra_add_headers_set specifies if other add_header directives will be added to location block generated
	 *
	 * @param string $section               Section.
	 * @param bool   $extra_add_headers_set Extra add headers flag.
	 *
	 * @return array
	 */
	public function section_rules( $section, $extra_add_headers_set = false ) {
		$rules = array();

		$expires  = $this->w3tc_c->get_boolean( "browsercache.$section.expires" );
		$lifetime = $this->w3tc_c->get_integer( "browsercache.$section.lifetime" );

		if ( $expires ) {
			$rules[] = 'expires ' . $lifetime . 's;';
		}
		if ( version_compare( Util_Environment::get_server_version(), '1.3.3', '>=' ) ) {
			if ( $this->w3tc_c->get_boolean( "browsercache.$section.etag" ) ) {
				$rules[] = 'etag on;';
			} else {
				$rules[] = 'etag off;';
			}
		}
		if ( $this->w3tc_c->get_boolean( "browsercache.$section.last_modified" ) ) {
			$rules[] = 'if_modified_since exact;';
		} else {
			$rules[] = 'if_modified_since off;';
		}

		$add_header_rules = array();
		if ( $this->w3tc_c->get_boolean( "browsercache.$section.cache.control" ) ) {
			$cache_policy = $this->w3tc_c->get_string( "browsercache.$section.cache.policy" );

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
					$add_header_rules[] = 'add_header Cache-Control "private, no-cache";';
					break;

				case 'no_store':
					$add_header_rules[] = 'add_header Pragma "no-store";';
					$add_header_rules[] = 'add_header Cache-Control "no-store";';
					break;

				case 'cache_immutable':
					$add_header_rules[] = 'add_header Pragma "public";';
					$add_header_rules[] = "add_header Cache-Control \"public, max-age=$lifetime, immutable\";";
					break;

				case 'cache_immutable_nomaxage':
					$add_header_rules[] = 'add_header Pragma "public";';
					$add_header_rules[] = 'add_header Cache-Control "public, immutable";';
					break;
			}
		}

		if ( $this->w3tc_c->get_boolean( "browsercache.$section.w3tc" ) ) {
			$add_header_rules[] = 'add_header X-Powered-By "' . Util_Environment::w3tc_header() . '";';
		}

		if ( ! empty( $add_header_rules ) || $extra_add_headers_set ) {
			$add_header_rules = array_merge( $add_header_rules, $this->security_rules() );
		}

		return array(
			'add_header' => $add_header_rules,
			'other'      => $rules,
		);
	}
}
