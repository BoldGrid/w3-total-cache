<?php
/**
 * File: class-w3tc-userexperience-lazyload-mutator.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @author     BoldGrid <development@boldgrid.com>
 * @since      X.X.X
 * @link       https://www.boldgrid.com/w3-total-cache/
 */

declare( strict_types = 1 );

use W3TC\UserExperience_LazyLoad_Mutator;

/**
 * Class: W3tc_UserExperience_LazyLoad_Test
 *
 * @since X.X.X
 */
class W3tc_UserExperience_LazyLoad_Mutator_Test extends WP_UnitTestCase {
		/**
		 * Create a mutator instance with a minimal config stub.
		 *
		 * @since X.X.X
		 *
		 * @return UserExperience_LazyLoad_Mutator
		 */
		private function get_mutator() {
				return new UserExperience_LazyLoad_Mutator( w3tc_config(), array() );
		}

		/**
		 * Ensure background styles are offloaded with raw URL only.
		 *
		 * @since X.X.X
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
		 * @since X.X.X
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
		 * @since X.X.X
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
		 * @since X.X.X
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
}
