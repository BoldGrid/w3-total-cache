<?php
/**
 * File: class-w3tc-cdn-srcset-rewriter-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.11.0
 */

declare( strict_types = 1 );

use W3TC\_Cdn_Plugin_ContentFilter;

/**
 * CDN srcset placeholder rewriter behavior.
 *
 * @since 2.11.0
 */
class W3tc_Cdn_Srcset_Rewriter_Test extends WP_UnitTestCase {
	/**
	 * Loads the CDN content filter class (same file as Cdn_Plugin).
	 *
	 * @since 2.11.0
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		require_once dirname( __DIR__, 2 ) . '/Cdn_Plugin.php';
	}

	/**
	 * Runs the production srcset matcher without fill_regexps().
	 *
	 * @since 2.11.0
	 *
	 * @param _Cdn_Plugin_ContentFilter $filter Filter instance.
	 * @param string                    $html   Input HTML.
	 *
	 * @return string
	 */
	private function rewrite_srcset( _Cdn_Plugin_ContentFilter $filter, $html ) {
		$ref = new ReflectionObject( $filter );

		$ensure = $ref->getMethod( '_ensure_srcset_placeholder_hash' );
		\W3TC\Util_Reflection::set_accessible( $ensure );
		$ensure->invoke( $filter, $html );

		$replace_srcset = $ref->getMethod( 'replace_srcset_attributes' );
		\W3TC\Util_Reflection::set_accessible( $replace_srcset );
		$buffer = $replace_srcset->invoke( $filter, $html );

		$replace = $ref->getMethod( 'replace_placeholders' );
		\W3TC\Util_Reflection::set_accessible( $replace );

		return $replace->invoke( $filter, $buffer );
	}

	/**
	 * Builds a filter whose only regexp swaps the uploads host for a CDN host.
	 *
	 * @since 2.11.1
	 *
	 * @return _Cdn_Plugin_ContentFilter
	 */
	private function make_host_swap_filter() {
		$filter = new class() extends _Cdn_Plugin_ContentFilter {
			/**
			 * Swaps the origin host for the CDN host.
			 *
			 * @since 2.11.1
			 *
			 * @param array $matches Regexp matches.
			 *
			 * @return string
			 */
			public function _link_replace_callback( $matches ) { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
				return $matches[1] . 'https://cdn.example.net' . $matches[4];
			}
		};

		$prop = ( new ReflectionClass( _Cdn_Plugin_ContentFilter::class ) )->getProperty( '_regexps' );
		\W3TC\Util_Reflection::set_accessible( $prop );
		$prop->setValue(
			$filter,
			array( '~(["\'])\s*((https?://example\.com)?(/wp-content/uploads/([^"\'>]+)))~i' )
		);

		return $filter;
	}

	/**
	 * Srcset-style attributes on real tags.
	 *
	 * @since 2.11.1
	 *
	 * @return array
	 */
	public function data_srcset_attributes() {
		$up  = 'http://example.com/wp-content/uploads/';
		$cdn = 'https://cdn.example.net/wp-content/uploads/';

		return array(
			'img srcset'                 => array(
				'<img srcset="' . $up . 'a.jpg 1x, ' . $up . 'b.jpg 2x" alt="">',
				'<img srcset="' . $cdn . 'a.jpg 1x, ' . $cdn . 'b.jpg 2x" alt="">',
			),
			'single-quoted source'       => array(
				"<source srcset='" . $up . "a.webp 1x' type=\"image/webp\">",
				'<source srcset="' . $cdn . 'a.webp 1x" type="image/webp">',
			),
			'data-srcset'                => array(
				'<img data-srcset="' . $up . 'a.jpg 300w, ' . $up . 'b.jpg 600w">',
				'<img data-srcset="' . $cdn . 'a.jpg 300w, ' . $cdn . 'b.jpg 600w">',
			),
			'imagesrcset'                => array(
				'<link rel="preload" as="image" imagesrcset="' . $up . 'a.jpg 1x">',
				'<link rel="preload" as="image" imagesrcset="' . $cdn . 'a.jpg 1x">',
			),
			'data-lazy-srcset'           => array(
				'<img data-lazy-srcset="' . $up . 'a.jpg 1x, ' . $up . 'b.jpg 2x">',
				'<img data-lazy-srcset="' . $cdn . 'a.jpg 1x, ' . $cdn . 'b.jpg 2x">',
			),
			'srcset= text inside title'  => array(
				'<abbr title="q srcset=">a</abbr><img srcset="' . $up . 'a.jpg 1x, ' . $up . 'b.jpg 2x">',
				'<abbr title="q srcset=">a</abbr><img srcset="' . $cdn . 'a.jpg 1x, ' . $cdn . 'b.jpg 2x">',
			),
			'srcset= text only in title' => array(
				'<abbr title="q srcset=">a</abbr><abbr title="w">b</abbr><code>z onmouseover=f() x=</code>',
				'<abbr title="q srcset=">a</abbr><abbr title="w">b</abbr><code>z onmouseover=f() x=</code>',
			),
		);
	}

	/**
	 * Every attribute whose name ends in srcset is rewritten; srcset-like text elsewhere is not.
	 *
	 * @since 2.11.1
	 *
	 * @dataProvider data_srcset_attributes
	 *
	 * @param string $html     Input HTML.
	 * @param string $expected Expected HTML.
	 *
	 * @return void
	 */
	public function test_srcset_attributes_are_rewritten_in_place( $html, $expected ) {
		$this->assertSame( $expected, $this->rewrite_srcset( $this->make_host_swap_filter(), $html ) );
	}

	/**
	 * Each placeholder is restored once, at the tag that emitted it.
	 *
	 * @since 2.11.1
	 *
	 * @return void
	 */
	public function test_each_srcset_placeholder_is_restored_once() {
		$up   = 'http://example.com/wp-content/uploads/';
		$cdn  = 'https://cdn.example.net/wp-content/uploads/';
		$html = '<img srcset="' . $up . 'a.jpg 1x"><img srcset="' . $up . 'b.jpg 1x"><img srcset="' . $up . 'c.jpg 1x">';

		$output = $this->rewrite_srcset( $this->make_host_swap_filter(), $html );

		$this->assertSame(
			'<img srcset="' . $cdn . 'a.jpg 1x"><img srcset="' . $cdn . 'b.jpg 1x"><img srcset="' . $cdn . 'c.jpg 1x">',
			$output
		);
		$this->assertStringNotContainsString( 'CdnSrcset_', $output );
	}

	/**
	 * replace_all_links must not expand attacker-supplied legacy placeholder literals.
	 *
	 * @since 2.11.0
	 *
	 * @return void
	 */
	public function test_replace_all_links_does_not_expand_legacy_srcset_placeholder_literals() {
		$filter = new _Cdn_Plugin_ContentFilter();
		$ref    = new ReflectionObject( $filter );
		$prop   = $ref->getProperty( '_regexps' );
		\W3TC\Util_Reflection::set_accessible( $prop );
		$prop->setValue(
			$filter,
			array(
				'~does-not-match~',
			)
		);

		$html = '<html><body><img srcset=\'/wp-content/uploads/a.jpg 1x\' alt="x">'
			. '<span>%srcset-0%</span></body></html>';

		$output = $this->rewrite_srcset( $filter, $html );

		$this->assertStringContainsString( '%srcset-0%', $output );
		$this->assertStringNotContainsString( 'onpointerover', $output );
	}

	/**
	 * Srcset matching in replace_all_links() must not run past the opening quote.
	 *
	 * CDN file-type rewrites are turned off so a non-matching custom mask is the
	 * only regexp. That keeps the srcset callback on the placeholder path
	 * without rewriting the image URL.
	 *
	 * @since 2.11.0
	 *
	 * @return void
	 */
	public function test_replace_all_links_srcset_closes_on_opening_quote() {
		$config = \W3TC\Dispatcher::config();
		$keys   = array(
			'cdn.uploads.enable'  => false,
			'cdn.includes.enable' => false,
			'cdn.theme.enable'    => false,
			'cdn.minify.enable'   => false,
			'cdn.custom.enable'   => true,
			'cdn.custom.files'     => array( 'https://cdn.example.invalid/never-match/*' ),
		);
		$saved  = array();
		foreach ( $keys as $key => $value ) {
			$saved[ $key ] = $config->get( $key );
			$config->set( $key, $value );
		}

		try {
			$filter = new _Cdn_Plugin_ContentFilter();
			$html   = '<html><body><cite>srcset=\'x</cite>'
				. '<img srcset="/wp-includes/js/a.js 1x" alt="y"></body></html>';

			$this->assertSame( $html, $filter->replace_all_links( $html ) );
		} finally {
			foreach ( $saved as $key => $value ) {
				$config->set( $key, $value );
			}
		}
	}

	/**
	 * With pcre.backtrack_limit at 20, an unterminated srcset leaves the buffer unchanged and emits no placeholder.
	 *
	 * @since 2.11.0
	 *
	 * @return void
	 */
	public function test_replace_all_links_keeps_buffer_when_srcset_match_fails() {
		$limit = ini_get( 'pcre.backtrack_limit' );
		ini_set( 'pcre.backtrack_limit', '20' );

		try {
			$filter = new _Cdn_Plugin_ContentFilter();
			$html   = '<html><body><img srcset="' . str_repeat( 'a', 200 ) . '></body></html>';
			$output = $filter->replace_all_links( $html );

			$this->assertSame( '20', ini_get( 'pcre.backtrack_limit' ), 'Low backtrack limit is in effect during the rewrite.' );
			$this->assertSame( $html, $output, 'Unterminated srcset under a low backtrack limit returns the input buffer.' );
			$this->assertStringNotContainsString( 'CdnSrcset_', $output, 'No srcset placeholder is left in the buffer.' );
		} finally {
			ini_set( 'pcre.backtrack_limit', (string) $limit );
		}
	}

	/**
	 * Placeholder restore must keep srcset URLs that contain capture syntax.
	 *
	 * @since 2.11.0
	 *
	 * @return void
	 */
	public function test_replace_placeholders_preserves_capture_syntax_in_srcset_urls() {
		$filter = new _Cdn_Plugin_ContentFilter();
		$ref    = new ReflectionObject( $filter );
		$prop   = $ref->getProperty( '_regexps' );
		\W3TC\Util_Reflection::set_accessible( $prop );
		$prop->setValue(
			$filter,
			array(
				'~does-not-match~',
			)
		);

		$html = '<html><body><img srcset="/images/photo$1.jpg 1x, /images/pic${1}.png 2x" alt="x"></body></html>';

		$output = $this->rewrite_srcset( $filter, $html );

		$this->assertSame( $html, $output );
	}
}
