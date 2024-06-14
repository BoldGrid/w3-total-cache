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

		$this->buffer = $r['buffer'];

		// Sets includes whose matches will be stripped site-wide.
		$this->includes = $this->config->get_array(
			array(
				'user-experience-remove-cssjs',
				'includes',
			)
		);

		// Sets singles includes data whose matches will be removed on mated pages.
		$this->singles_includes = $this->config->get_array( 'user-experience-remove-cssjs-singles' );

		// If old data structure convert to new.
		// Old data structure used url_pattern as the key for each block. New uses indicies and has url_pattern within.
		if ( ! is_numeric( key( $this->singles_includes ) ) ) {
			$new_array = array();
			foreach ( $this->singles_includes as $match => $data ) {
				$new_array[] = array(
					'url_pattern'      => $match,
					'action'           => isset( $data['action'] ) ? $data['action'] : 'exclude',
					'includes'         => $data['includes'],
					'includes_content' => $data['includes_content'],
				);
			}
			$this->singles_includes = $new_array;
		}

		$this->buffer = preg_replace_callback(
			'~(<link.+?href.+?>)|(<script.+?src.+?</script>)~is',
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

		// Build array of possible current page relative/absolute URLs.
		$current_pages = array(
			$wp->request,
			trailingslashit( $wp->request ),
			home_url( $wp->request ),
			trailingslashit( home_url( $wp->request ) ),
		);

		foreach ( $this->singles_includes as $id => $data ) {
			// Check if the defined single CSS/JS file is present in HTML content.
			if ( ! empty( $data ) && strpos( $content, $data['url_pattern'] ) !== false ) {
				// Check if current page URL(s) match any defined conditions.
				$page_match = array_intersect(
					$current_pages,
					array_map(
						function ($value) {
							return ltrim( $value, '/' );
						},
						$data['includes']
					)
				);

				// Check if current page content match any defined conditions.
				$content_match = false;
				foreach ( $data['includes_content'] as $include ) {
					if ( strpos( $this->buffer, $include ) !== false ) {
						$content_match = true;
						break;
					}
				}

				/**
				 * If set to exclude, remove the file if the page matches defined URLs.
				 * If set to include, Remove the file if the page doesn't match defined URLs.
				 */
				if ( 'exclude' === $data['action'] && ( $page_match || $content_match ) ) {
					return true;
				} elseif ( 'include' === $data['action'] && ! ( $page_match || $content_match ) ) {
					return true;
				}
			}
		}

		return false;
	}
}
