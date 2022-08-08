<?php
namespace W3TC;

/**
 * Rules generation for OpenLiteSpeed
 */
class BrowserCache_Environment_LiteSpeed {
	private $c;



	public function __construct( $config ) {
		$this->c = $config;
	}



	/**
	 * Returns cache rules
	 */
	public function generate( $mime_types, $cdnftp = false ) {
		$cssjs_types = $mime_types['cssjs'];
		$cssjs_types = array_unique( $cssjs_types );
		$html_types = $mime_types['html'];
		$other_types = $mime_types['other'];
		$other_compression_types = $mime_types['other_compression'];

		$rules = '';
		$rules .= W3TC_MARKER_BEGIN_BROWSERCACHE_CACHE . "\n";

		if ( $this->c->get_boolean( 'browsercache.rewrite' ) ) {
		}

		$this->generate_section( $rules, $mime_types['cssjs'], 'cssjs' );
		$this->generate_section( $rules, $mime_types['html'], 'html' );
		$this->generate_section( $rules, $mime_types['other'], 'other' );

		$rules .= W3TC_MARKER_END_BROWSERCACHE_CACHE . "\n";

		return $rules;
	}



	/**
	 * Adds cache rules for type to &$rules.
	 *
	 * @param string $rules      Rules.
	 * @param array  $mime_types MIME types.
	 * @param string $section    Section.
	 * @return void
	 */
	private function generate_section( &$rules, $mime_types, $section ) {
		$expires       = $this->c->get_boolean( 'browsercache.' . $section . '.expires' );
		$etag          = $this->c->get_boolean( 'browsercache.' . $section . '.etag' );
		$cache_control = $this->c->get_boolean( 'browsercache.' . $section . '.cache.control' );
		$w3tc          = $this->c->get_boolean( 'browsercache.' . $section . '.w3tc' );
		$last_modified = $this->c->get_boolean( 'browsercache.' . $section . '.last_modified' );

		if ( $etag || $expires || $cache_control || $w3tc || ! $last_modified ) {
			$mime_types2 = apply_filters(
				'w3tc_browsercache_rules_section_extensions',
				$mime_types,
				$this->c,
				$section
			);
			$extensions  = array_keys( $mime_types2 );

			// Remove ext from filesmatch if its the same as permalink extension.
			$pext = strtolower( pathinfo( get_option( 'permalink_structure' ), PATHINFO_EXTENSION ) );

			if ( $pext ) {
				$extensions = Util_Rule::remove_extension_from_list( $extensions, $pext );
			}

			$extensions_string = implode( '|', $extensions );


			$section_rules = self::section_rules( $section );
			$context_rules = $section_rules['other'];

			if ( !empty( $section_rules['add_header'] ) ) {
				$context_rules[] = "    extraHeaders <<<END_extraHeaders";
				foreach ( $section_rules['add_header'] as $line ) {
					$context_rules[] = '        ' . $line;
				}
				$context_rules[] = "    END_extraHeaders";
			}

			$rules .= "context exp:^.*($extensions_string)\$ {\n";
			$rules .= "    location \$DOC_ROOT/\$0\n";
			$rules .= "    allowBrowse 1\n";
			$rules .= implode( "\n", $context_rules ) . "\n";
			$rules .= "}\n";
		}
	}

	/**
	 * Returns directives plugin applies to files of specific section
	 * Without location
	 */
	public function section_rules( $section ) {
		$rules = [];

		$expires = $this->c->get_boolean( "browsercache.$section.expires" );
		$lifetime = $this->c->get_integer( "browsercache.$section.lifetime" );

		if ( $expires ) {
			$rules[] = '    enableExpires 1';
			$rules[] = "    expiresDefault A$lifetime";
			$rules[] = "    ExpiresByType */*=A$lifetime";
		} else {
			$rules[] = '    enableExpires 0';
		}

		/*
		if ( $this->c->get_boolean( "browsercache.$section.last_modified" ) )
		lastmod support not implemented
		*/

		$add_header_rules = array();
		if ( $this->c->get_boolean( "browsercache.$section.cache.control" ) ) {
			$cache_policy = $this->c->get_string( "browsercache.$section.cache.policy" );

			switch ( $cache_policy ) {
			case 'cache':
				$add_header_rules[] = 'unset Pragma';
				$add_header_rules[] = 'set Pragma public';
				$add_header_rules[] = 'set Cache-Control public';
				break;

			case 'cache_public_maxage':
				$add_header_rules[] = 'unset Pragma';
				$add_header_rules[] = 'set Pragma public';
				break;

			case 'cache_validation':
				$add_header_rules[] = 'unset Pragma';
				$add_header_rules[] = 'set Pragma public';
				$add_header_rules[] = 'unset Cache-Control';
				$add_header_rules[] = 'set Cache-Control "public, must-revalidate, proxy-revalidate"';
				break;

			case 'cache_noproxy':
				$add_header_rules[] = 'unset Pragma';
				$add_header_rules[] = 'set Pragma public';
				$add_header_rules[] = 'unset Cache-Control';
				$add_header_rules[] = 'set Cache-Control "private, must-revalidate"';
				break;

			case 'cache_maxage':
				$add_header_rules[] = 'unset Pragma';
				$add_header_rules[] = 'set Pragma "public"';

				$add_header_rules[] = 'unset Cache-Control';
				if ( $expires ) {
					$add_header_rules[] = 'set Cache-Control "public, must-revalidate, proxy-revalidate"';
				} else {
					$add_header_rules[] = "set Cache-Control \"max-age=$lifetime, public, must-revalidate, proxy-revalidate\"";
				}
				break;

			case 'no_cache':
				$add_header_rules[] = 'unset Pragma';
				$add_header_rules[] = 'add_header Pragma "no-cache";';
				$add_header_rules[] = 'unset Cache-Control';
				$add_header_rules[] = 'add_header Cache-Control "max-age=0, private, no-store, no-cache, must-revalidate"';
				break;
			}
		}

		return array( 'add_header' => $add_header_rules, 'other' => $rules );
	}
}
