<?php
/**
 * File: class-w3tc-userexperience-lazyload-mutator.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @author     BoldGrid <development@boldgrid.com>
 * @since      2.10.0
 * @link       https://www.boldgrid.com/w3-total-cache/
 */

declare( strict_types = 1 );

use W3TC\UserExperience_LazyLoad_Mutator;
use W3TC\UserExperience_LazyLoad_Mutator_Picture;

/**
 * Class: W3tc_UserExperience_LazyLoad_Test
 *
 * @since 2.8.12
 */
class W3tc_UserExperience_LazyLoad_Mutator_Test extends WP_UnitTestCase {
		/**
		 * Create a mutator instance with a minimal config stub.
		 *
		 * @since 2.8.12
		 *
		 * @return UserExperience_LazyLoad_Mutator
		 */
		private function get_mutator() {
				return new UserExperience_LazyLoad_Mutator( w3tc_config(), array() );
		}

		/**
		 * Ensure background styles are offloaded with raw URL only.
		 *
		 * @since 2.8.12
		 */
		public function test_style_offload_background_strips_url_wrapper() {
				$mutator = $this->get_mutator();
				$matches = array(
						' style="background-image:url(\'image.jpg\');color:red;"',
						' ',
						'style="',
						'background-image:url(\'image.jpg\');color:red;',
						'"'
				);

				$result = $mutator->style_offload_background( $matches );

				$this->assertSame(
						' style="color:red;" data-bg="image.jpg"',
						$result
				);
		}

		/**
		 * Ensure background styles are offloaded with raw URL only containing parenthesis.
		 *
		 * @since 2.8.12
		 */
		public function test_style_offload_background_strips_url_parenthesis_wrapper() {
			$mutator = $this->get_mutator();
			$matches = array(
					' style="background-image:url(\'image(1).jpg\');color:red;"',
					' ',
					'style="',
					'background-image:url(\'image(1).jpg\');color:red;',
					'"'
			);

			$result = $mutator->style_offload_background( $matches );

			$this->assertSame(
					' style="color:red;" data-bg="image(1).jpg"',
					$result
			);
	}

		/**
		 * Image tags are converted to lazy load placeholders and attributes.
		 *
		 * @since 2.8.12
		 */
		public function test_tag_img_content_replace_adds_lazy_attributes() {
				$mutator = $this->get_mutator();
				$img     = '<img src="image.jpg" srcset="image-2x.jpg 2x" sizes="100vw" loading="lazy" width="10" height="10">';

				$result = $mutator->tag_img_content_replace( $img, array( 'w' => 10, 'h' => 10 ) );

				$this->assertStringContainsString( 'data-src="image.jpg"', $result );
				$this->assertStringContainsString( 'data-srcset="image-2x.jpg 2x"', $result );
				$this->assertStringContainsString( 'data-sizes="100vw"', $result );
				$this->assertStringNotContainsString( 'loading="lazy"', $result );
				$this->assertMatchesRegularExpression( '/src="data:image\\/svg\\+xml/', $result );
				$this->assertMatchesRegularExpression( '/class="[^"]*lazy[^"]*"/', $result );
		}

		/**
		 * Elements with background images move URL to data attribute and gain lazy class.
		 *
		 * @since 2.8.12
		 */
		public function test_tag_with_background_moves_url_to_data_attribute() {
				$mutator = $this->get_mutator();
				// Initialize excludes array.
				$mutator->run( '' );

				$element = '<div class="cover" style="background-image:url(\'bg.jpg\');color:red;"></div>';
				$result  = $mutator->tag_with_background( array( $element ) );

				$this->assertStringContainsString( 'class="cover lazy"', $result );
				$this->assertStringContainsString( 'data-bg="bg.jpg"', $result );
				$this->assertStringNotContainsString( 'background-image', $result );
				$this->assertStringContainsString( 'style="color:red;"', $result );
		}

		/**
		 * Substring "src=" inside a single-quoted alt value is left alone.
		 *
		 * @since 2.10.4
		 */
		public function test_tag_img_content_replace_ignores_src_substring_in_alt() {
				$mutator = $this->get_mutator();
				$img     = "<img alt='avatar src=x data-marker=value name' src='avatar.jpg' width='96' height='96'>";

				$result = $mutator->tag_img_content_replace( $img, array( 'w' => 96, 'h' => 96 ) );

				$this->assertStringContainsString( "alt='avatar src=x data-marker=value name'", $result );
				$this->assertStringContainsString( "data-src='avatar.jpg'", $result );
				$this->assertStringNotContainsString( 'data-src=x', $result );
				$this->assertMatchesRegularExpression( '/\ssrc="data:image\\/svg\\+xml,/', $result );
		}

		/**
		 * Double-quoted alt containing a quoted src= fragment is left alone.
		 *
		 * @since 2.10.4
		 */
		public function test_tag_img_content_replace_ignores_quoted_src_fragment_in_alt() {
				$mutator = $this->get_mutator();
				$img     = '<img alt="photo src=&quot;x&quot; title" src="photo.jpg" width="10" height="10">';

				$result = $mutator->tag_img_content_replace( $img, array( 'w' => 10, 'h' => 10 ) );

				$this->assertStringContainsString( 'alt="photo src=&quot;x&quot; title"', $result );
				$this->assertStringContainsString( 'data-src="photo.jpg"', $result );
		}

		/**
		 * Opposite-quote src= text inside a double-quoted alt is left alone.
		 *
		 * @since 2.10.4
		 */
		public function test_tag_img_content_replace_ignores_opposite_quote_src_in_double_alt() {
				$mutator = $this->get_mutator();
				$img     = '<img alt="photo src=\'x\' data-marker=1" src="photo.jpg" width="10" height="10">';

				$result = $mutator->tag_img_content_replace( $img, array( 'w' => 10, 'h' => 10 ) );

				$this->assertStringContainsString( 'alt="photo src=\'x\' data-marker=1"', $result );
				$this->assertStringContainsString( 'data-src="photo.jpg"', $result );
				$this->assertStringNotContainsString( "data-src='x'", $result );
		}

		/**
		 * Opposite-quote src= text inside a single-quoted alt is left alone.
		 *
		 * @since 2.10.4
		 */
		public function test_tag_img_content_replace_ignores_opposite_quote_src_in_single_alt() {
				$mutator = $this->get_mutator();
				$img     = "<img alt='photo src=\"x\" data-marker=1' src='photo.jpg' width='10' height='10'>";

				$result = $mutator->tag_img_content_replace( $img, array( 'w' => 10, 'h' => 10 ) );

				$this->assertStringContainsString( "alt='photo src=\"x\" data-marker=1'", $result );
				$this->assertStringContainsString( "data-src='photo.jpg'", $result );
				$this->assertStringNotContainsString( 'data-src="x"', $result );
		}

		/**
		 * Double-quoted src values may contain an apostrophe.
		 *
		 * @since 2.10.4
		 */
		public function test_tag_img_content_replace_allows_apostrophe_in_double_quoted_src() {
				$mutator = $this->get_mutator();
				$img     = '<img src="image\'s.jpg" width="10" height="10">';

				$result = $mutator->tag_img_content_replace( $img, array( 'w' => 10, 'h' => 10 ) );

				$this->assertStringContainsString( 'data-src="image\'s.jpg"', $result );
				$this->assertMatchesRegularExpression( '/\ssrc="data:image\\/svg\\+xml,/', $result );
		}

		/**
		 * Placeholder data-URI contains no literal apostrophe or quote characters.
		 *
		 * @since 2.10.4
		 */
		public function test_placeholder_has_no_literal_quotes() {
				$mutator    = $this->get_mutator();
				$placeholder = $mutator->placeholder( 10, 20 );

				$this->assertStringStartsWith( 'data:image/svg+xml,', $placeholder );
				$this->assertStringNotContainsString( "'", $placeholder );
				$this->assertStringNotContainsString( '"', $placeholder );
				$this->assertStringContainsString( 'viewBox', $placeholder );
		}

		/**
		 * Single-quoted src attributes still convert to lazy-load form.
		 *
		 * @since 2.10.4
		 */
		public function test_tag_img_content_replace_handles_single_quoted_src() {
				$mutator = $this->get_mutator();
				$img     = "<img src='image.jpg' width='10' height='10'>";

				$result = $mutator->tag_img_content_replace( $img, array( 'w' => 10, 'h' => 10 ) );

				$this->assertStringContainsString( "data-src='image.jpg'", $result );
				$this->assertMatchesRegularExpression( '/\ssrc="data:image\\/svg\\+xml,/', $result );
				$this->assertMatchesRegularExpression( '/class="[^"]*lazy[^"]*"/', $result );
		}

		/**
		 * Picture source and image attributes are converted to lazy-load form.
		 *
		 * @since 2.10.4
		 */
		public function test_picture_source_and_image_attributes_are_replaced() {
				$picture = new UserExperience_LazyLoad_Mutator_Picture( $this->get_mutator() );
				$content = '<picture><source srcset="image.webp 1x, image-2x.webp 2x" sizes="100vw">' .
					'<img alt="image src=opaque" src="image.jpg" width="10" height="10"></picture>';

				$result = $picture->run( $content );

				$this->assertStringContainsString( 'data-srcset="image.webp 1x, image-2x.webp 2x"', $result );
				$this->assertStringContainsString( 'data-sizes="100vw"', $result );
				$this->assertStringContainsString( 'alt="image src=opaque"', $result );
				$this->assertStringContainsString( 'data-src="image.jpg"', $result );
				$this->assertMatchesRegularExpression( '/\ssrc="data:image\\/svg\\+xml,/', $result );
		}
}
