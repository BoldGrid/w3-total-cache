<?php
namespace W3TC;

/**
 * Minify rules generation for LiteSpeed
 */
class Minify_Environment_LiteSpeed {
	private $c;



	public function __construct( $config ) {
		$this->c = $config;
	}



	// force rewrites to work in order to get minify a chance to generate content
	public function w3tc_browsercache_rules_section( $section_rules, $section ) {
		if ( $section == 'cssjs' ) {
			$indent = "\t";
			$section_rules['other'][] = $indent . 'rewrite {';
			$section_rules['other'][] = $indent . '  RewriteFile .htaccess';
			$section_rules['other'][] = $indent . '}';
		}

		return $section_rules;
	}
}
