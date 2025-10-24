<?php
/**
 * File: UserExperience_LazyLoad_Mutator_Unmutable.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class UserExperience_LazyLoad_Mutator_Unmutable
 */
class UserExperience_LazyLoad_Mutator_Unmutable {
	/**
	 * An array mapping unique keys to unmutable content (e.g., scripts and styles).
	 *
	 * @var array
	 */
	private $placeholders = array();

	/**
	 * A base string used to create unique keys for placeholders.
	 *
	 * @var string
	 */
	private $placeholder_base = '';

	/**
	 * Constructor for the UserExperience_LazyLoad_Mutator_Unmutable class.
	 *
	 * Initializes the placeholder base using a hash generated from the server's request time.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->placeholder_base = 'w3tc_lazyload_' . md5(
			isset( $_SERVER['REQUEST_TIME'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_TIME'] ) ) : ''
		) . '_';
	}

	/**
	 * Replaces unmutable content (e.g., scripts and styles) in the provided buffer with placeholders.
	 *
	 * This method identifies `<script>` and `<style>` tags in the buffer and replaces them
	 * with unique placeholders to allow lazy loading or other transformations.
	 *
	 * @param string $buffer The HTML content buffer to process.
	 *
	 * @return string The modified buffer with placeholders replacing the unmutable content.
	 */
	public function remove_unmutable( $buffer ) {
		// scripts.
		$buffer = preg_replace_callback(
			'~<script(\b[^>]*)>(.*?)</script>~is',
			array( $this, 'placeholder' ),
			$buffer
		);

		// styles.
		$buffer = preg_replace_callback(
			'~\s*<style(\b[^>]*)>(.*?)</style>~is',
			array( $this, 'placeholder' ),
			$buffer
		);

		return $buffer;
	}

	/**
	 * Restores unmutable content in the provided buffer by replacing placeholders with their original content.
	 *
	 * This method replaces previously added placeholders with their associated content stored in `$placeholders`.
	 *
	 * @param string $buffer The HTML content buffer containing placeholders.
	 *
	 * @return string The restored buffer with original unmutable content.
	 */
	public function restore_unmutable( $buffer ) {
		return str_replace(
			array_keys( $this->placeholders ),
			array_values( $this->placeholders ),
			$buffer
		);
	}

	/**
	 * Generates a unique placeholder for a matched piece of unmutable content and stores it.
	 *
	 * This method is called internally by `remove_unmutable` to replace matches (e.g., scripts and styles)
	 * with unique placeholders. The original content is stored in `$placeholders`.
	 *
	 * @param array $matches The matches found by the `preg_replace_callback` function,
	 *                       where the full match is at index 0.
	 *
	 * @return string The generated placeholder key.
	 */
	public function placeholder( $matches ) {
		$key                        = '{' . $this->placeholder_base . count( $this->placeholders ) . '}';
		$this->placeholders[ $key ] = $matches[0];
		return $key;
	}
}
