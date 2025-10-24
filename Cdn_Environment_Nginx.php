<?php
/**
 * File: Cdn_Environment_Nginx.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdn_Environment_Nginx
 *
 * CDN rules generation for Nginx
 *
 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
 */
class Cdn_Environment_Nginx {
	/**
	 * Config.
	 *
	 * @var Config
	 */
	private $c;

	/**
	 * Constructor for initializing the CDN environment with the given configuration.
	 *
	 * This constructor initializes the object with the provided configuration. The configuration is typically
	 * an array or object that contains various settings required to generate the appropriate Nginx rules and
	 * configurations for the CDN environment.
	 *
	 * @param mixed $config The configuration object or array used to initialize the CDN environment.
	 */
	public function __construct( $config ) {
		$this->c = $config;
	}

	/**
	 * Generates Nginx configuration rules for the CDN environment.
	 *
	 * This method generates the necessary Nginx rules for the CDN environment based on the configuration and the
	 * presence of specific CDN settings such as CORS headers and canonical headers. The generated rules are returned
	 * as a string, ready to be used in the Nginx configuration file.
	 *
	 * @param mixed $cdnftp The CDN FTP configuration object used to generate canonical headers.
	 *
	 * @return string The generated Nginx configuration rules for the CDN environment.
	 */
	public function generate( $cdnftp ) {
		$rules = '';
		$rule  = $this->generate_canonical( $cdnftp );
		if ( ! empty( $rule ) ) {
			$rules = $rule . "\n";
		}

		if ( $this->c->get_boolean( 'cdn.cors_header' ) ) {
			$rules_a   = Dispatcher::nginx_rules_for_browsercache_section( $this->c, 'other', true );
			$rules_a[] = 'add_header Access-Control-Allow-Origin "*";';

			$rules .= "location ~ \\.(ttf|ttc|otf|eot|woff|woff2|font.css)\$ {\n    " . implode( "\n    ", $rules_a ) . "\n}\n";
		}

		if ( strlen( $rules ) > 0 ) {
			$rules = W3TC_MARKER_BEGIN_CDN . "\n" . $rules . W3TC_MARKER_END_CDN . "\n";
		}

		return $rules;
	}

	/**
	 * Generates the canonical header rule for Nginx.
	 *
	 * This method generates the canonical header rule to be added to the Nginx configuration. The canonical header
	 * is used to indicate the preferred version of a resource in case there are multiple versions available.
	 * The rule is only generated if the 'cdn.canonical_header' setting is enabled in the configuration.
	 *
	 * @param bool $cdnftp Whether to use the FTP configuration to determine the home URL.
	 *
	 * @return string|null The canonical header rule, or null if the rule is not enabled.
	 */
	public function generate_canonical( $cdnftp = false ) {
		if ( ! $this->c->get_boolean( 'cdn.canonical_header' ) ) {
			return null;
		}

		$home = ( $cdnftp ? Util_Environment::home_url_host() : '$host' );

		return 'add_header Link "<$scheme://' . $home . '$request_uri>; rel=\"canonical\"";';
	}

	/**
	 * Modifies the list of extensions for the browser cache rules section based on the CDN configuration.
	 *
	 * This method modifies the list of file extensions that are included in the browser cache rules section of the
	 * Nginx configuration. If the CDN settings specify that CORS headers should be applied, certain font file
	 * extensions (such as ttf, otf, woff) are excluded from the list of extensions.
	 *
	 * @param array  $extensions The list of file extensions to be included in the browser cache rules.
	 * @param string $section The section of the configuration where the extensions are being applied.
	 *
	 * @return array The modified list of extensions for the browser cache rules section.
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
}
