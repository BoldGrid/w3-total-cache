<?php
/**
 * File: UserExperience_LazyLoad_Mutator_Picture.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class UserExperience_LazyLoad_Mutator_Picture
 */
class UserExperience_LazyLoad_Mutator_Picture {
	/**
	 * Constructor for the UserExperience_LazyLoad_Mutator_Picture class.
	 *
	 * An instance of a common utility class used to process image and source tags.
	 *
	 * @var object
	 */
	private $common;

	/**
	 * Initializes the class with a common utility instance.
	 *
	 * @param object $common An instance of a utility class for handling image and source tag processing.
	 *
	 * @return void
	 */
	public function __construct( $common ) {
		$this->common = $common;
	}

	/**
	 * Processes the provided content to modify `<img>` and `<source>` tags.
	 *
	 * This method replaces the `srcset` and `sizes` attributes in `<source>` tags with `data-srcset` and `data-sizes`,
	 * and applies custom modifications to `<img>` tags using utility methods.
	 *
	 * @param string $content The content containing `<img>` and `<source>` tags to be processed.
	 *
	 * @return string The modified content with lazy-loading attributes applied.
	 */
	public function run( $content ) {
		$content = preg_replace_callback(
			'~(<img\s[^>]+>)~i',
			array( $this, 'tag_img' ),
			$content
		);

		$content = preg_replace_callback(
			'~(<source\s[^>]+>)~i',
			array( $this, 'tag_source' ),
			$content
		);

		return $content;
	}

	/**
	 * Processes a matched `<img>` tag.
	 *
	 * This method retrieves the dimensions of the image and replaces its attributes with lazy-loading compatible attributes.
	 *
	 * @param array $matches The matches from the regular expression containing the `<img>` tag.
	 *
	 * @return string The modified `<img>` tag with lazy-loading attributes applied.
	 */
	public function tag_img( $matches ) {
		$content = $matches[0];

		// get image dimensions.
		$dim = $this->common->tag_get_dimensions( $content );
		return $this->common->tag_img_content_replace( $content, $dim );
	}

	/**
	 * Processes a matched `<source>` tag.
	 *
	 * This method replaces the `srcset` and `sizes` attributes with `data-srcset` and `data-sizes`
	 * to delay loading the sources until needed.
	 *
	 * @param array $matches The matches from the regular expression containing the `<source>` tag.
	 *
	 * @return string The modified `<source>` tag with lazy-loading attributes applied.
	 */
	private function tag_source( $matches ) {
		$content = $matches[0];

		$content = preg_replace(
			'~(\s)(srcset|sizes)=~i',
			'$1data-$2=',
			$content
		);

		return $content;
	}
}
