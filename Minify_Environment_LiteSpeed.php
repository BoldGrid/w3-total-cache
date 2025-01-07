<?php
/**
 * File: Minify_Environment_LiteSpeed.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Minify_Environment_LiteSpeed
 *
 * Minify rules generation for LiteSpeed
 */
class Minify_Environment_LiteSpeed {
	/**
	 * Config
	 *
	 * @var Config
	 */
	private $c;

	/**
	 * Constructor for the Minify_Environment_LiteSpeed class.
	 *
	 * @param array $config Configuration array for initializing the class.
	 *
	 * @return void
	 */
	public function __construct( $config ) {
		$this->c = $config;
	}

	/**
	 * Modifies the browser cache rules based on the section.
	 *
	 * @param array  $section_rules Array of the current cache rules for the section.
	 * @param string $section       The section type (e.g., 'cssjs').
	 *
	 * @return array Modified array of the section rules.
	 */
	public function w3tc_browsercache_rules_section( $section_rules, $section ) {
		if ( 'cssjs' === $section ) {
			$section_rules['rewrite'] = true;
		}

		return $section_rules;
	}
}
