<?php
/**
 * File: UserExperience_DeferScripts_Mutator.php
 *
 * JS feature mutator for buffer processing.
 *
 * @since 2.4.2
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * UserExperience DeferScripts Mutator.
 *
 * @since 2.5.0
 */
class UserExperience_DeferScripts_Mutator {
	/**
	 * Config.
	 *
	 * @var object
	 */
	private $w3tc_config;

	/**
	 * Modified flag.
	 *
	 * @var boolean
	 */
	private $modified = false;

	/**
	 * Array of includes.
	 *
	 * @var array
	 */
	private $includes = array();

	/**
	 * User Experience DeferScripts Mutator constructor.
	 *
	 * @since 2.5.0
	 *
	 * @param object $w3tc_config Config object.
	 *
	 * @return void
	 */
	public function __construct( $w3tc_config ) {
		$this->w3tc_config = $w3tc_config;
	}

	/**
	 * Runs User Experience DeferScripts Mutator.
	 *
	 * @since 2.5.0
	 *
	 * @param string $buffer Buffer string containing browser output.
	 *
	 * @return string
	 */
	public function run( $buffer ) {
		$w3tc_r = apply_filters(
			'w3tc_deferscripts_mutator_before',
			array(
				'buffer'   => $buffer,
				'modified' => $this->modified,
			)
		);

		$buffer         = $w3tc_r['buffer'];
		$this->modified = $w3tc_r['modified'];

		$this->includes = $this->w3tc_config->get_array(
			array(
				'user-experience-defer-scripts',
				'includes',
			)
		);

		$buffer = preg_replace_callback(
			'~<script\s[^>]+>~is',
			array(
				$this,
				'tag_script',
			),
			$buffer
		);

		return $buffer;
	}

	/**
	 * Get modified status flag.
	 *
	 * @since 2.5.0
	 *
	 * @return boolean
	 */
	public function content_modified() {
		return $this->modified;
	}

	/**
	 * Modifies script tag for script matched to be deferred.
	 *
	 * @since 2.5.0
	 *
	 * @param array $matches array of matched JS entries.
	 *
	 * @return string
	 */
	public function tag_script( $matches ) {
		$content = $matches[0];

		if ( $this->is_content_included( $content ) ) {
			$w3tc_count = 0;
			$content    = preg_replace(
				'~(\s)src=~is',
				'$1data-lazy="w3tc" data-src=',
				$content,
				-1,
				$w3tc_count
			);

			if ( $w3tc_count > 0 ) {
				$this->modified = true;
			}
		}

		return $content;
	}

	/**
	 * Checks if content has already been deferred.
	 *
	 * @since 2.5.0
	 *
	 * @param string $content script tag string.
	 *
	 * @return boolean
	 */
	private function is_content_included( $content ) {
		foreach ( $this->includes as $w ) {
			if ( ! empty( $w ) ) {
				if ( strpos( $content, $w ) !== false ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Filters out scripts so Minify doesn't touch deferred scripts.
	 *
	 * @since 2.5.0
	 *
	 * @param array $script_tags array of script tags.
	 *
	 * @return array
	 */
	public function w3tc_minify_js_script_tags( $script_tags ) {
		return array_values(
			array_filter(
				$script_tags,
				function ( $w3tc_i ) {
					return ! preg_match( '~\sdata-lazy="w3tc"\s~', $w3tc_i );
				}
			)
		);
	}
}
