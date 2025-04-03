<?php
/**
 * File: Cdn_Environment_LiteSpeed.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdn_Environment_LiteSpeed
 *
 * CDN rules generation for LiteSpeed
 *
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
 */
class Cdn_Environment_LiteSpeed {
	/**
	 * Config.
	 *
	 * @var Config
	 */
	private $c;

	/**
	 * Constructor for the Cdn_Environment_LiteSpeed class.
	 *
	 * This constructor initializes the object with the provided configuration.
	 * It is typically called when an instance of the class is created to set up the configuration.
	 *
	 * @param object $config The configuration object that contains necessary settings.
	 *
	 * @return void
	 */
	public function __construct( $config ) {
		$this->c = $config;
	}

	/**
	 * Generates CDN configuration rules for the LiteSpeed server.
	 *
	 * This method generates and returns the LiteSpeed configuration rules for handling fonts and headers, including
	 * canonical and CORS headers. It processes the provided CDN FTP configuration and applies filters to modify the rules.
	 *
	 * @param object $cdnftp The CDN FTP object used for generating the canonical header.
	 *
	 * @return string The generated CDN configuration rules.
	 */
	public function generate( $cdnftp ) {
		$section_rules = array(
			'other'      => array(),
			'add_header' => array(),
		);

		if ( $this->c->get_boolean( 'cdn.cors_header' ) ) {
			$section_rules['add_header'][] = 'set Access-Control-Allow-Origin "*"';
		}

		$canonical_header = $this->generate_canonical( $cdnftp );
		if ( ! empty( $canonical_header ) ) {
			$section_rules['add_header'][] = $canonical_header;
		}

		if ( empty( $section_rules['add_header'] ) ) {
			return '';
		}

		$section_rules = apply_filters( 'w3tc_cdn_rules_section', $section_rules, $this->c );

		$context_rules[] = '    extraHeaders <<<END_extraHeaders';
		foreach ( $section_rules['add_header'] as $line ) {
			$context_rules[] = '        ' . $line;
		}
		$context_rules[] = '    END_extraHeaders';

		$rules   = array();
		$rules[] = 'context exp:^.*(ttf|ttc|otf|eot|woff|woff2|font.css)$ {';
		$rules[] = '    location $DOC_ROOT/$0';
		$rules[] = '    allowBrowse 1';
		$rules[] = implode( "\n", $context_rules );
		$rules[] = '}';

		return W3TC_MARKER_BEGIN_CDN . "\n" . implode( "\n", $rules ) . "\n" . W3TC_MARKER_END_CDN . "\n";
	}

	/**
	 * Generates the canonical header for the CDN configuration.
	 *
	 * This method generates a canonical header to be used in the CDN configuration if the 'cdn.canonical_header' setting
	 * is enabled in the configuration. It constructs a 'Link' header with the canonical URL based on the home URL.
	 *
	 * @param bool $cdnftp Optional. A flag to include CDN FTP in the canonical header generation. Defaults to false.
	 *
	 * @return string|null The canonical header string or null if not generated.
	 */
	public function generate_canonical( $cdnftp = false ) {
		if ( ! $this->c->get_boolean( 'cdn.canonical_header' ) ) {
			return null;
		}

		$home_url  = get_home_url();
		$parse_url = @parse_url( $home_url ); // phpcs:ignore
		if ( ! isset( $parse_url['host'] ) ) {
			return null;
		}

		return "set Link '<" . $parse_url['scheme'] . '://' . $parse_url['host'] . '%{REQUEST_URI}e>; rel="canonical"' . "'";

		/* phpcs:ignore Squiz.PHP.CommentedOutCode.Found
		$rules .= "      RewriteRule .* - [E=CANONICAL:https://$host%{REQUEST_URI},NE]\n";
		$rules .= "   </IfModule>\n";
		$rules .= "   <IfModule mod_headers.c>\n";
		$rules .= '      Header set Link "<%{CANONICAL}e>; rel=\"canonical\""' . "\n";

		return 'set Link "<%{CANONICAL}e>; rel=\"canonical\""' . "\n";
		*/
	}

	/**
	 * Modifies the extensions for the browser cache rules section.
	 *
	 * This method adjusts the list of file extensions for which browser cache rules should be applied. If CORS headers
	 * are enabled, certain font file types (e.g., ttf, otf, eot, woff, woff2) are removed from the extensions list.
	 *
	 * @param array  $extensions The array of extensions for which rules are applied.
	 * @param string $section    The section to which these rules apply.
	 *
	 * @return array The modified array of extensions.
	 */
	public function w3tc_browsercache_rules_section_extensions( $extensions, $section ) {
		// CDN adds own rules for those extensions.
		if ( $this->c->get_boolean( 'cdn.cors_header' ) ) {
			unset( $extensions['ttf|ttc'] );
			unset( $extensions['otf'] );
			unset( $extensions['eot'] );
			unset( $extensions['woff'] );
			unset( $extensions['woff2'] );
		}

		return $extensions;
	}

	/**
	 * Modifies the browser cache rules section with the canonical header.
	 *
	 * This method adds the canonical header to the section rules if the 'cdn.canonical_header' setting is enabled.
	 * It modifies the provided section rules to include the generated canonical header.
	 *
	 * @param array  $section_rules The current set of section rules.
	 * @param string $section       The section to which the rules apply.
	 *
	 * @return array The modified set of section rules with the canonical header included.
	 */
	public function w3tc_browsercache_rules_section( $section_rules, $section ) {
		$canonical_header = $this->generate_canonical();
		if ( ! empty( $canonical_header ) ) {
			$section_rules['add_header'][] = $canonical_header;
		}

		return $section_rules;
	}
}
