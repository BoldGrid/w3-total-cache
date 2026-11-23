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
	private $w3tc_config;

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
	 * Page buffer.
	 *
	 * @var string
	 */
	private $buffer = '';

	/**
	 * User Experience Remove CSS/JS Mutator constructor.
	 *
	 * @since 2.7.0
	 *
	 * @param object $w3tc_config Config object.
	 *
	 * @return void
	 */
	public function __construct( $w3tc_config ) {
		$this->w3tc_config = $w3tc_config;
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
		$w3tc_r = apply_filters(
			'w3tc_remove_cssjs_mutator_before',
			array(
				'buffer' => $buffer,
			)
		);

		$this->buffer = $w3tc_r['buffer'];

		// Sets includes whose matches will be stripped site-wide.
		$this->includes = $this->w3tc_config->get_array(
			array(
				'user-experience-remove-cssjs',
				'includes',
			)
		);

		// Sets singles includes data whose matches will be removed on mated pages.
		$this->singles_includes = $this->w3tc_config->get_array( 'user-experience-remove-cssjs-singles' );

		// If old data structure convert to new.
		// Old data structure used url_pattern as the key for each block. New uses indicies and has url_pattern within.
		if ( ! is_numeric( key( $this->singles_includes ) ) ) {
			$w3tc_new_array = array();
			foreach ( $this->singles_includes as $w3tc_match => $w3tc_data ) {
				$w3tc_new_array[] = array(
					'url_pattern'      => $w3tc_match,
					'action'           => isset( $w3tc_data['action'] ) ? $w3tc_data['action'] : 'exclude',
					'includes'         => $w3tc_data['includes'],
					'includes_content' => $w3tc_data['includes_content'],
				);
			}
			$this->singles_includes = $w3tc_new_array;
		}

		$this->buffer = preg_replace_callback(
			'~(<link[^>]+href[^>]+>)|(<script[^>]+src[^>]+></script>)~is',
			array( $this, 'remove_content' ),
			$this->buffer
		);

		return $this->buffer;
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
	 * Checks if content matches defined rules for exlusion/inclusion.
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

		// Build array of possible current page URLs.
		$current_pages = array(
			esc_url( trailingslashit( home_url( $wp->request ) ) ),
		);

		if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
			$current_pages[] = esc_url( trailingslashit( home_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) ) );
		}

		foreach ( $this->singles_includes as $id => $w3tc_data ) {
			// Check if the defined single CSS/JS file is present in HTML content.
			if ( ! empty( $w3tc_data ) && strpos( $content, $w3tc_data['url_pattern'] ) !== false ) {
				// Check if current page URL(s) match any defined conditions.
				$page_match = Util_Environment::array_intersect_partial(
					$current_pages,
					$w3tc_data['includes']
				);

				// Check if current page content match any defined conditions.
				$content_match = false;
				foreach ( $w3tc_data['includes_content'] as $include ) {
					if ( strpos( $this->buffer, $include ) !== false ) {
						$content_match = true;
						break;
					}
				}

				/**
				 * If set to exclude, remove the file if the page matches defined URLs.
				 * If set to include, Remove the file if the page doesn't match defined URLs.
				 */
				if ( 'exclude' === $w3tc_data['action'] && ( $page_match || $content_match ) ) {
					return true;
				} elseif ( 'include' === $w3tc_data['action'] && ! ( $page_match || $content_match ) ) {
					return true;
				}
			}
		}

		return false;
	}
}
