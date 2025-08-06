<?php
/**
 * File: UserExperience_LazyLoad_Mutator.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class UserExperience_LazyLoad_Mutator
 */
class UserExperience_LazyLoad_Mutator {
	/**
	 * Configuration settings for lazy loading.
	 *
	 * @var Config
	 */
	private $config;

	/**
	 * Tracks whether the content was modified during processing.
	 *
	 * @var bool
	 */
	private $modified = false;

	/**
	 * List of content patterns to exclude from lazy loading.
	 *
	 * @var array
	 */
	private $excludes;

	/**
	 * Map of post URLs to their corresponding post IDs.
	 *
	 * @var array
	 */
	private $posts_by_url;

	/**
	 * Constructor for UserExperience_LazyLoad_Mutator.
	 *
	 * @param array $config       Configuration settings for lazy loading.
	 * @param array $posts_by_url Map of post URLs to their corresponding post IDs.
	 *
	 * @return void
	 */
	public function __construct( $config, $posts_by_url ) {
		$this->config       = $config;
		$this->posts_by_url = $posts_by_url;
	}

	/**
	 * Runs the lazy loading mutator process on the provided buffer.
	 *
	 * @param string $buffer The HTML content to process for lazy loading.
	 *
	 * @return string The modified buffer with lazy loading applied.
	 */
	public function run( $buffer ) {
		$this->excludes = apply_filters( 'w3tc_lazyload_excludes', $this->config->get_array( 'lazyload.exclude' ) );

		$r              = apply_filters(
			'w3tc_lazyload_mutator_before',
			array(
				'buffer'   => $buffer,
				'modified' => $this->modified,
			)
		);
		$buffer         = $r['buffer'];
		$this->modified = $r['modified'];

		$unmutable = new UserExperience_LazyLoad_Mutator_Unmutable();
		$buffer    = $unmutable->remove_unmutable( $buffer );

		if ( $this->config->get_boolean( 'lazyload.process_img' ) ) {
			$buffer = preg_replace_callback(
				'~<picture(\s[^>]+)*>(.*?)</picture>~is',
				array( $this, 'tag_picture' ),
				$buffer
			);
			$buffer = preg_replace_callback(
				'~<img\s[^>]+>~is',
				array( $this, 'tag_img' ),
				$buffer
			);
		}

		if ( $this->config->get_boolean( 'lazyload.process_background' ) ) {
			$buffer = preg_replace_callback(
				'~<[^>]+background(-image)?:\s*url[^>]+>~is',
				array( $this, 'tag_with_background' ),
				$buffer
			);
		}

		$buffer = $unmutable->restore_unmutable( $buffer );

		return $buffer;
	}

	/**
	 * Checks if the content has been modified during processing.
	 *
	 * @return bool True if the content was modified; otherwise, false.
	 */
	public function content_modified() {
		return $this->modified;
	}

	/**
	 * Processes <picture> tags for lazy loading.
	 *
	 * @param array $matches Regex matches for the <picture> tag.
	 *
	 * @return string The modified <picture> tag with lazy loading applied.
	 */
	public function tag_picture( $matches ) {
		$content = $matches[0];

		if ( $this->is_content_excluded( $content ) ) {
			return $content;
		}

		$m = new UserExperience_LazyLoad_Mutator_Picture( $this );

		return $m->run( $content );
	}

	/**
	 * Processes <img> tags for lazy loading.
	 *
	 * @param array $matches Regex matches for the <img> tag.
	 *
	 * @return string The modified <img> tag with lazy loading applied.
	 */
	public function tag_img( $matches ) {
		$content = $matches[0];

		if ( $this->is_content_excluded( $content ) ) {
			return $content;
		}

		// get image dimensions.
		$dim = $this->tag_get_dimensions( $content );
		return $this->tag_img_content_replace( $content, $dim );
	}

	/**
	 * Replaces <img> content with placeholders and adds lazy loading attributes.
	 *
	 * @param string $content The <img> tag content.
	 * @param array  $dim     The dimensions of the image (width and height).
	 *
	 * @return string The modified <img> tag.
	 */
	public function tag_img_content_replace( $content, $dim ) {
		// do replace.
		$count   = 0;
		$content = preg_replace(
			'~(\s)src=~is',
			'$1src="' . $this->placeholder( $dim['w'], $dim['h'] ) . '" data-src=',
			$content,
			-1,
			$count
		);

		if ( $count > 0 ) {
			$content = preg_replace(
				'~(\s)(srcset|sizes)=~is',
				'$1data-$2=',
				$content
			);

			$content        = $this->add_class_lazy( $content );
			$content        = $this->remove_native_lazy( $content );
			$this->modified = true;
		}

		return $content;
	}

	/**
	 * Extracts image dimensions from <img> content or associated metadata.
	 *
	 * @param string $content The <img> tag content.
	 *
	 * @return array The dimensions of the image (width and height).
	 */
	public function tag_get_dimensions( $content ) {
		$dim = array(
			'w' => 1,
			'h' => 1,
		);
		$m   = null;
		if ( preg_match( '~\swidth=[\s\'"]*([0-9]+)~is', $content, $m ) ) {
			$dim['w'] = (int) $m[1];
			$dim['h'] = $dim['w'];

			if ( preg_match( '~\sheight=[\s\'"]*([0-9]+)~is', $content, $m ) ) {
				$dim['h'] = (int) $m[1];
				return $dim;
			}
		}

		// if not in attributes - try to find via url.
		if (
			! preg_match(
				'~\ssrc=(\'([^\']*)\'|"([^"]*)"|([^\'"][^\\s]*))~is',
				$content,
				$m
			)
		) {
			return $dim;
		}

		$url = ( ! empty( $m[4] ) ? $m[4] : ( ( ! empty( $m[3] ) ? $m[3] : $m[2] ) ) );

		// full url found.
		if ( isset( $this->posts_by_url[ $url ] ) ) {
			$post_id = $this->posts_by_url[ $url ];

			$image = wp_get_attachment_image_src( $post_id, 'full' );
			if ( $image ) {
				$dim['w'] = $image[1];
				$dim['h'] = $image[2];
			}

			return $dim;
		}

		// try resized url by format.
		static $base_url = null;
		if ( is_null( $base_url ) ) {
			$base_url = wp_get_upload_dir()['baseurl'];
		}

		if (
			substr( $url, 0, strlen( $base_url ) ) === $base_url &&
			preg_match( '~(.+)-(\\d+)x(\\d+)(\\.[a-z0-9]+)$~is', $url, $m )
		) {
			$dim['w'] = (int) $m[2];
			$dim['h'] = (int) $m[3];
		}

		return $dim;
	}

	/**
	 * Processes elements with background styles for lazy loading.
	 *
	 * @param array $matches Regex matches for elements with background styles.
	 *
	 * @return string The modified element with lazy loading applied.
	 */
	public function tag_with_background( $matches ) {
		$content = $matches[0];

		if ( $this->is_content_excluded( $content ) ) {
			return $content;
		}

		$quote_match = null;
		if ( ! preg_match( '~\s+style\s*=\s*([\"\'])~is', $content, $quote_match ) ) {
			return $content;
		}

		$quote = $quote_match[1];

		$count   = 0;
		$content = preg_replace_callback(
			'~(\s+)(style\s*=\s*[' . $quote . '])(.*?)([' . $quote . '])~is',
			array( $this, 'style_offload_background' ),
			$content,
			-1,
			$count
		);

		if ( $count > 0 ) {
			$content        = $this->add_class_lazy( $content );
			$this->modified = true;
		}

		return $content;
	}

	/**
	 * Offloads background styles for lazy loading.
	 *
	 * @param array $matches Regex matches for background styles.
	 *
	 * @return string The modified style attribute with lazy loading applied.
	 */
	public function style_offload_background( $matches ) {
		list( $match, $v1, $v2, $v, $quote ) = $matches;
		$url_match                           = null;
		preg_match( '~background(?:-image)?:\s*url\(([\"\']?)(.+?)\1\)~is', $v, $url_match );
		$v = preg_replace( '~background(?:-image)?:\s*url\(([\"\']?).+?\1\)[^;]*;?\s*~is', '', $v );

		return $v1 . $v2 . $v . $quote . ' data-bg=' . $quote . ( isset( $url_match[2] ) ? $url_match[2] : '' ) . $quote;
	}

	/**
	 * Adds the "lazy" class to the provided content if not already present.
	 *
	 * @param string $content The content to modify.
	 *
	 * @return string The modified content with the "lazy" class applied.
	 */
	private function add_class_lazy( $content ) {
		$count   = 0;
		$content = preg_replace_callback(
			'~(\s+)(class=)([\"\'])(.*?)([\"\'])~is',
			array( $this, 'class_process' ),
			$content,
			-1,
			$count
		);

		if ( $count <= 0 ) {
			$content = preg_replace(
				'~<(\S+)(\s+)~is',
				'<$1$2class="lazy" ',
				$content
			);
		}

		return $content;
	}

	/**
	 * Removes native lazy loading attributes from the content.
	 *
	 * @param string $content The content to modify.
	 *
	 * @return string The modified content with native lazy loading removed.
	 */
	public function remove_native_lazy( $content ) {
		return preg_replace(
			'~(\s+)loading=[\'"]lazy[\'"]~is',
			'',
			$content
		);
	}

	/**
	 * Processes class attributes to include the "lazy" class.
	 *
	 * @param array $matches Regex matches for class attributes.
	 *
	 * @return string The modified class attribute.
	 */
	public function class_process( $matches ) {
		list( $match, $v1, $v2, $quote, $v ) = $matches;
		if ( preg_match( '~(^|\\s)lazy(\\s|$)~is', $v ) ) {
			return $match;
		}

		$v .= ' lazy';

		return $v1 . $v2 . $quote . $v . $quote;
	}

	/**
	 * Determines if the content should be excluded from lazy loading.
	 *
	 * @param string $content The content to check.
	 *
	 * @return bool True if the content is excluded; otherwise, false.
	 */
	private function is_content_excluded( $content ) {
		foreach ( $this->excludes as $w ) {
			if ( ! empty( $w ) ) {
				if ( strpos( $content, $w ) !== false ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Generates a placeholder SVG for an image with the given dimensions.
	 *
	 * @param int $w The width of the image.
	 * @param int $h The height of the image.
	 *
	 * @return string The SVG placeholder.
	 */
	public function placeholder( $w, $h ) {
		return 'data:image/svg+xml,%3Csvg%20xmlns=\'http://www.w3.org/2000/svg\'%20viewBox=\'0%200%20' .
			$w . '%20' . $h . '\'%3E%3C/svg%3E';
	}
}
