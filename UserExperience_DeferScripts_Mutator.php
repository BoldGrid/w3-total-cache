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
 * @since 2.4.2
 */
class UserExperience_DeferScripts_Mutator {
	/**
	 * Config.
	 *
	 * @var object
	 */
	private $config;
	
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
	 * @since 2.4.2
	 */
	public function __construct( $config ) {
		$this->config = $config;
	}

	/**
	 * Runs User Experience DeferScripts Mutator.
	 *
	 * @since 2.4.2
	 *
	 * @return void
	 */
	public function run( $buffer ) {
		$r = apply_filters(
			'w3tc_deferscripts_mutator_before',
			array(
				'buffer'   => $buffer,
				'modified' => $this->modified
			)
		);
		
		$buffer         = $r['buffer'];
		$this->modified = $r['modified'];

		/* 
		Because an array is used as the key the includes are saved as a string instead of array, so
		we need to parse it before use. This will remove empty lines, trim values, and sort before use.
		See Generic_AdminActions_Default.php read_request method.
		*/
		$this->includes = array_filter(
			array_map(
				'trim',
				explode(
					"\n",
					str_replace(
						"\r\n",
						"\n",
						$this->config->get_array(
							array(
								'user-experience-defer-scripts',
								'includes',
							)
						)[0]
					),
				)
			),
			'strlen'
		);
		sort( $this->includes );
		
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
	 * @since 2.4.2
	 *
	 * @return boolean
	 */
	public function content_modified() {
		return $this->modified;
	}

	/**
	 * Modifies script tag for script matched to be deferred.
	 *
	 * @since 2.4.2
	 *
	 * @param array $matches array of matched JS entries.
	 * 
	 * @return string
	 */
	public function tag_script( $matches ) {
		$content = $matches[0];

		if ( $this->is_content_included( $content ) ) {
			$count   = 0;
			$content = preg_replace(
				'~(\s)src=~is',
				'$1data-lazy="w3tc" data-src=',
				$content,
				-1,
				$count
			);

			if ($count > 0) {
				$this->modified = true;
			}
		}

		return $content;
	}

	/**
	 * Checks if content has already been deferred.
	 *
	 * @since 2.4.2
	 * 
	 * @param string $content script tag string.
	 *
	 * @return boolean
	 */
	private function is_content_included( $content ) {
		foreach ( $this->includes as $w ) {
			if ( ! empty( $w ) ) {
				if ( strpos( $content, $w ) !== FALSE ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Filters out scripts so minify doesn't touch deferred scripts.
	 *
	 * @since 2.4.2
	 *
	 * @param array $script_tags array of script tags.
	 *
	 * @return array
	 */
	public function w3tc_minify_js_script_tags( $script_tags ) {
		return array_values(
			array_filter(
				$script_tags,
				function( $i ) {
					return ! preg_match( '~\sdata-lazy="w3tc"\s~', $i );
				}
			)
		);
	}
}
