<?php
namespace W3TC;

/**
 * CDN rules generation for Nginx
 */
class Cdn_Environment_Nginx {
	static public function generate( $config, $cdnftp ) {
		$rules = '';
		$rule = Cdn_Environment_Nginx::generate_canonical( $config, $cdnftp );
		if ( !empty( $rule ) ) {
			$rules = $rule . "\n";
		}

		if ( $config->get_boolean( 'cdn.cors_header') ) {
			$rules_a = Dispatcher::nginx_rules_for_browsercache_section(
				$config, 'other', true );
			$rules_a[] = 'add_header Access-Control-Allow-Origin "*";';

			$rules .=
			'location ~ ^\\.(ttf|ttc|otf|eot|woff|woff2|font.css)$ {' .
			'    ' . implode( "\n    ", $rules_a ) . "\n" .
			"}\n";
		}

		if ( strlen( $rules ) > 0 ) {
			$rules =
				W3TC_MARKER_BEGIN_CDN . "\n" .
				$rules .
				W3TC_MARKER_END_CDN . "\n";
		}

		return $rules;
	}



	static public function generate_canonical( $config, $cdnftp = false ) {
		if ( !$config->get_boolean( 'cdn.canonical_header' ) ) {
			return null;
		}

		$home = ( $cdnftp ) ? Util_Environment::home_url_host() : '$host';

		return 'add_header Link "<$scheme://' .	$home .
			'$request_uri>; rel=\"canonical\"";';
	}
}
