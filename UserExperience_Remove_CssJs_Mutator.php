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
			'~(<link.*?href.*?/>)|(<script.*?src.*?<\/script>)~is',
			array( $this, 'remove_content' ),
			$buffer
		);

		return $buffer;
	}

	/**
	 * Removes matched link/script tag from HTML content.
	 *
	 * @since 2.7.0
	 *
	 * @param array $matches array of matched CSS/JS entries.
	 *
	 * @return string
	 */
	public function remove_content( $matches ) {
		$content = $matches[0];

		if ( is_main_query() && $this->is_content_included( $content ) ) {
			return '';
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

		// Always removes matched CSS/JS for home page.
		if ( is_front_page() ) {
			foreach ( $this->includes as $include ) {
				if ( ! empty( $include ) ) {
					if ( strpos( $content, $include ) !== false ) {
						return true;
					}
				}
			}
		}

		// Build array of possible current page relative/absolute URLs.
		$current_pages = array(
			$wp->request,
			trailingslashit( $wp->request ),
			home_url( $wp->request ),
			trailingslashit( home_url( $wp->request ) ),
		);

		// Only removes matched CSS/JS on matching pages.
		foreach ( $this->singles_includes as $include => $pages ) {
			if (
				! empty( $pages )
				// Check if the given single CSS/JS remove rule URL is present in HTML content.
				&& strpos( $content, $include ) !== false
				// Check if current page matches defined pages for given single CSS/JS remove rule.
				&& array_intersect(
					$current_pages,
					// Remove leading / value from included page value(s) as the $wp->request excludes them.
					array_map(
						function ( $value ) {
							return ltrim( $value, '/' );
						},
						$pages['includes']
					)
				)
			) {
				return true;
			}
		}

		return false;
	}
}
