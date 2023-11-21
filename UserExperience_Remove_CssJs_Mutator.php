<?php
/**
 * File: UserExperience_Remove_CssJs_Mutator.php
 *
 * CSS/JS feature mutator for buffer processing.
 *
 * @since 2.7.0
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * UserExperience Remove CSS/JS Mutator.
 *
 * @since 2.7.0
 */
class UserExperience_Remove_CssJs_Mutator {
	/**
	 * Config.
	 *
	 * @var object
	 */
	private $config;

	/**
	 * Array of includes.
	 *
	 * @var array
	 */
	private $includes = array();

	/**
	 * Array of singles includes.
	 *
	 * @var array
	 */
	private $singles_includes = array();

	/**
	 * User Experience Remove CSS/JS Mutator constructor.
	 *
	 * @since 2.7.0
	 *
	 * @param object $config Config object.
	 *
	 * @return void
	 */
	public function __construct( $config ) {
		$this->config = $config;
	}

	/**
	 * Runs User Experience Remove CSS/JS Mutator.
	 *
	 * @since 2.7.0
	 *
	 * @param string $buffer Buffer string containing browser output.
	 *
	 * @return string
	 */
	public function run( $buffer ) {
		$r = apply_filters(
			'w3tc_remove_cssjs_mutator_before',
			array(
				'buffer' => $buffer,
			)
		);

		$buffer = $r['buffer'];

		// Sets includes whose matches will be stripped site-wide.
		$this->includes = $this->config->get_array(
			array(
				'user-experience-remove-cssjs',
				'includes',
			)
		);

		// Sets singles includes data whose matches will be removed on mated pages.
		$this->singles_includes = $this->config->get_array( 'user-experience-remove-cssjs-singles' );

		$buffer = preg_replace_callback(
			'~<link.*?href.*?/>~is',
			array(
				$this,
				'remove_styles',
			),
			$buffer
		);

		$buffer = preg_replace_callback(
			'~<script.*?src.*?<\/script>~is',
			array(
				$this,
				'remove_scripts',
			),
			$buffer
		);

		return $buffer;
	}

	/**
	 * Removes style tag for style matched to be removed.
	 *
	 * @since 2.7.0
	 *
	 * @param array $matches array of matched CSS entries.
	 *
	 * @return string
	 */
	public function remove_styles( $matches ) {
		$content = $matches[0];

		if ( is_main_query() && $this->is_content_included( $content ) ) {
			$count   = 0;
			$content = preg_replace(
				'~<link.*?href.*?/>~is',
				'',
				$content,
				-1,
				$count
			);

			if ( $count > 0 ) {
				$this->modified = true;
			}
		}

		return $content;
	}

	/**
	 * Removes script tag for script matched to be removed.
	 *
	 * @since 2.7.0
	 *
	 * @param array $matches array of matched JS entries.
	 *
	 * @return string
	 */
	public function remove_scripts( $matches ) {
		$content = $matches[0];

		if ( is_main_query() && $this->is_content_included( $content ) ) {
			$count   = 0;
			$content = preg_replace(
				'~<script.*?src.*?<\/script>~is',
				'',
				$content,
				-1,
				$count
			);

			if ( $count > 0 ) {
				$this->modified = true;
			}
		}

		return $content;
	}

	/**
	 * Checks if content has already been removed.
	 *
	 * @since 2.7.0
	 *
	 * @param string $content script tag string.
	 *
	 * @return boolean
	 */
	private function is_content_included( $content ) {
		global $wp;

		// Always removes matched CSS/JS for all pages.
		foreach ( $this->includes as $include ) {
			if ( ! empty( $include ) ) {
				if ( strpos( $content, $include ) !== false ) {
					return true;
				}
			}
		}

		// Only removes matched CSS/JS on matching pages.
		foreach ( $this->singles_includes as $include => $pages ) {
			if ( ! empty( $pages ) && in_array( ) ) {
				foreach ( $pages as $page ) {
					if ( home_url( $wp->request ) === $page && strpos( $content, $include ) !== false ) {
						return true;
					}
				}
			}
		}

		return false;
	}
}
