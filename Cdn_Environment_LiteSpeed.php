<?php
namespace W3TC;

/**
 * CDN rules generation for LiteSpeed
 */
class Cdn_Environment_LiteSpeed {
	private $c;



	public function __construct( $config ) {
		$this->c = $config;
	}



	public function generate( $cdnftp ) {
		$rules = [];

		if ( $this->c->get_boolean( 'cdn.cors_header') ) {
			$section_rules = Dispatcher::litespeed_rules_for_browsercache_section(
				$this->c, 'other' );

			$section_rules['add_header'][] = 'set Access-Control-Allow-Origin "*"';

			$context_rules[] = "    extraHeaders <<<END_extraHeaders";
			foreach ( $section_rules['add_header'] as $line ) {
				$context_rules[] = '        ' . $line;
			}
			$context_rules[] = "    END_extraHeaders";

			$rules[] = 'context exp:^.*(ttf|ttc|otf|eot|woff|woff2|font.css)$ {';
			$rules[] = '    location $DOC_ROOT/$0';
			$rules[] = '    allowBrowse 1';
			$rules[] = implode( "\n", $context_rules );
			$rules[] = '}';
		}

		if ( empty( $rules ) ) {
			return '';
		}

		return
			W3TC_MARKER_BEGIN_CDN . "\n" .
			implode( "\n", $rules ) . "\n" .
			W3TC_MARKER_END_CDN . "\n";
	}



	public function w3tc_browsercache_rules_section_extensions(
			$extensions, $section ) {
		// CDN adds own rules for those extensions
		if ( $this->c->get_boolean( 'cdn.cors_header') ) {
			unset( $extensions['ttf|ttc'] );
			unset( $extensions['otf'] );
			unset( $extensions['eot'] );
			unset( $extensions['woff'] );
			unset( $extensions['woff2'] );
		}

		return $extensions;
	}
}
