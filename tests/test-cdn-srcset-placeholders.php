<?php
/**
 * Standalone test for the CDN srcset rewriter in _Cdn_Plugin_ContentFilter.
 *
 * Covers srcset attributes on real tags being rewritten to the CDN host, srcset-like text inside
 * other attribute values being left alone, and placeholders being restored only where emitted.
 *
 * Run with: php tests/test-cdn-srcset-placeholders.php
 *
 * @package W3TC\Tests
 * @since   2.11.1
 */

namespace W3TC;

if ( \realpath( __FILE__ ) !== \realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) ) {
	return;
}

if ( ! \defined( 'W3TC' ) ) {
	\define( 'W3TC', true );
}

if ( true !== require __DIR__ . '/cdn-srcset-html-api-stubs.php' ) {
	if ( false !== \getenv( 'WP_CORE_DIR' ) ) {
		echo "FAIL: WP_HTML_Tag_Processor not found under WP_CORE_DIR.\n";
		exit( 1 );
	}
	echo "SKIP: WP_HTML_Tag_Processor not found; set WP_CORE_DIR to a WordPress core checkout.\n";
	exit( 0 );
}

require_once \dirname( __DIR__ ) . '/Cdn_Plugin.php';

/**
 * Content filter with a fixed uploads regexp and a host-swapping link callback.
 *
 * @since 2.11.1
 */
class W3TC_Test_Cdn_Srcset_Filter extends _Cdn_Plugin_ContentFilter { // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
	/**
	 * Skips Dispatcher::config().
	 *
	 * @since 2.11.1
	 */
	public function __construct() {
	}

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

	/**
	 * Runs the srcset placeholder, link rewrite, and restore steps of replace_all_links().
	 *
	 * @since 2.11.1
	 *
	 * @param string $buffer HTML.
	 *
	 * @return string
	 */
	public function run( $buffer ) {
		$regexps = array( '~(["\'])\s*((https?://example\.com)?(/wp-content/uploads/([^"\'>]+)))~i' );

		$parent = new \ReflectionClass( _Cdn_Plugin_ContentFilter::class );
		$prop   = $parent->getProperty( '_regexps' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$prop->setAccessible( true );
		}
		$prop->setValue( $this, $regexps );

		$call = function ( $method, $arg ) use ( $parent ) {
			$m = $parent->getMethod( $method );
			if ( \PHP_VERSION_ID < 80100 ) {
				$m->setAccessible( true );
			}
			return $m->invoke( $this, $arg );
		};

		$call( '_ensure_srcset_placeholder_hash', $buffer );
		$buffer = $call( 'replace_srcset_attributes', $buffer );

		foreach ( $regexps as $regexp ) {
			$buffer = \preg_replace_callback( $regexp, array( $this, '_link_replace_callback' ), $buffer );
		}

		return $call( 'replace_placeholders', $buffer );
	}
}

$cdnsp_pass = 0;
$cdnsp_fail = 0;

/**
 * Records one assertion.
 *
 * @since 2.11.1
 *
 * @param bool   $condition Result.
 * @param string $label     Case label.
 *
 * @return void
 */
function cdnsp_assert( $condition, $label ) {
	global $cdnsp_pass, $cdnsp_fail;

	if ( $condition ) {
		++$cdnsp_pass;
		echo 'PASS: ' . $label . "\n";
	} else {
		++$cdnsp_fail;
		echo 'FAIL: ' . $label . "\n";
	}
}

/**
 * Runs a buffer through a fresh filter.
 *
 * @since 2.11.1
 *
 * @param string $html HTML.
 *
 * @return string
 */
function cdnsp_run( $html ) {
	$filter = new W3TC_Test_Cdn_Srcset_Filter();
	return $filter->run( $html );
}

$up  = 'http://example.com/wp-content/uploads/';
$cdn = 'https://cdn.example.net/wp-content/uploads/';

$out = cdnsp_run( '<img src="' . $up . 'a.jpg" srcset="' . $up . 'a.jpg 1x, ' . $up . 'a-2x.jpg 2x" alt="">' );
cdnsp_assert(
	'<img src="' . $cdn . 'a.jpg" srcset="' . $cdn . 'a.jpg 1x, ' . $cdn . 'a-2x.jpg 2x" alt="">' === $out,
	'img srcset list is rewritten to the CDN host'
);

$out = cdnsp_run( "<picture><source srcset='" . $up . "a.webp 1x, " . $up . "b.webp 2x' type=\"image/webp\"></picture>" );
cdnsp_assert(
	'<picture><source srcset="' . $cdn . 'a.webp 1x, ' . $cdn . 'b.webp 2x" type="image/webp"></picture>' === $out,
	'single-quoted source srcset is rewritten and written double-quoted'
);

$out = cdnsp_run( '<img srcset="' . $up . 'a.jpg?w=1&amp;h=2 1x, ' . $up . 'b.jpg?w=1&h=2 2x">' );
cdnsp_assert(
	'<img srcset="' . $cdn . 'a.jpg?w=1&amp;h=2 1x, ' . $cdn . 'b.jpg?w=1&amp;h=2 2x">' === $out,
	'srcset ampersands are written encoded'
);

$out = cdnsp_run( '<link rel="preload" as="image" imagesrcset="' . $up . 'a.jpg 1x">' );
cdnsp_assert( '<link rel="preload" as="image" imagesrcset="' . $cdn . 'a.jpg 1x">' === $out, 'imagesrcset is rewritten' );

$out = cdnsp_run( '<IMG SRCSET="' . $up . 'a.jpg 1x">' );
cdnsp_assert( '<IMG srcset="' . $cdn . 'a.jpg 1x">' === $out, 'uppercase SRCSET is rewritten' );

$out = cdnsp_run( '<img data-srcset="' . $up . 'a.jpg 300w" src="data:,">' );
cdnsp_assert( '<img data-srcset="' . $cdn . 'a.jpg 300w" src="data:,">' === $out, 'data-srcset is rewritten' );

$out = cdnsp_run( '<img data-lazy-srcset="' . $up . 'a.jpg 1x, ' . $up . 'b.jpg 2x" src="data:,">' );
cdnsp_assert(
	'<img data-lazy-srcset="' . $cdn . 'a.jpg 1x, ' . $cdn . 'b.jpg 2x" src="data:,">' === $out,
	'data-lazy-srcset list is fully rewritten'
);

$out = cdnsp_run( '<img title="a>b" srcset = "' . $up . 'a.jpg 1x">' );
cdnsp_assert( '<img title="a>b" srcset="' . $cdn . 'a.jpg 1x">' === $out, 'quoted > before srcset keeps tag context' );

$out = cdnsp_run( "<img\nsrcset=\"" . $up . "a.jpg 1x\"\n/>" );
cdnsp_assert( "<img\nsrcset=\"" . $cdn . "a.jpg 1x\"\n/>" === $out, 'multi-line self-closing tag is rewritten' );

$html = '<p>Hi <abbr title="q srcset=">a</abbr><abbr title="w">b</abbr>'
	. '<code>%srcset-0%z onmouseover=f() x=</code>'
	. '<code>%%CdnSrcset_00_0%%z onmouseover=f() x=</code></p>';
$out  = cdnsp_run( $html );
cdnsp_assert( $html === $out, 'srcset-like text inside a title attribute is not rewritten' );
cdnsp_assert( 0 === \preg_match( '~<[a-z][^>]*\sonmouseover=~i', $out ), 'no handler attribute is moved into a tag' );

$html = '<abbr title="q srcset=">a</abbr><img srcset="' . $up . 'a.jpg 1x, ' . $up . 'b.jpg 2x"><code>z onmouseover=f() x=</code>';
$out  = cdnsp_run( $html );
cdnsp_assert(
	'<abbr title="q srcset=">a</abbr><img srcset="' . $cdn . 'a.jpg 1x, ' . $cdn . 'b.jpg 2x"><code>z onmouseover=f() x=</code>' === $out,
	'real srcset after a srcset-like title is rewritten in place'
);

$out = cdnsp_run( '<img srcset="' . $up . 'a.jpg 1x"><img srcset="' . $up . 'b.jpg 1x"><img srcset="' . $up . 'c.jpg 1x">' );
cdnsp_assert( false === \strpos( $out, 'CdnSrcset_' ), 'all placeholders are restored' );
cdnsp_assert( 3 === \substr_count( $out, 'srcset="' . $cdn ), 'each srcset restored at its own position' );

$out = cdnsp_run( '<img srcset="' . $up . 'a.jpg 1x' );
cdnsp_assert( false === \strpos( $out, 'CdnSrcset_' ), 'unterminated tag gets no placeholder' );

echo "\n" . $cdnsp_pass . ' passed, ' . $cdnsp_fail . " failed\n";
exit( $cdnsp_fail > 0 ? 1 : 0 );
