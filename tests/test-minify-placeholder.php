<?php
/**
 * Standalone regression test for minify placeholder ids.
 *
 * Run with: php tests/test-minify-placeholder.php
 *
 * Exit code 0 = all pass, non-zero = failures.
 *
 * @package W3TC\Tests
 * @since   X.X.X
 */

require_once __DIR__ . '/../lib/Minify/Minify/IgnoredCommentPreserver.php';
require_once __DIR__ . '/../lib/Minify/Minify/HTML.php';
require_once __DIR__ . '/../lib/Minify/Minify/CSS/Compressor.php';

if ( realpath( __FILE__ ) !== realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) ) {
	return;
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Stub for Minify_HTML script handling.
	 *
	 * @param string $tag   Filter name.
	 * @param mixed  $value Filter value.
	 * @return mixed
	 */
	function apply_filters( $tag, $value ) {
		unset( $tag );
		return $value;
	}
}

if ( ! class_exists( 'W3TC_Mph_Preserver', false ) ) {
	/**
	 * Exposes the placeholder id for assertions.
	 *
	 * @since X.X.X
	 */
	class W3TC_Mph_Preserver extends \W3TCL\Minify\Minify_IgnoredCommentPreserver {
		/**
		 * Placeholder id for this instance.
		 *
		 * @since X.X.X
		 *
		 * @return string
		 */
		public function hash() {
			return $this->_ensurePlaceholderHash();
		}

		/**
		 * Placeholder token at $index.
		 *
		 * @since X.X.X
		 *
		 * @param int $index Placeholder index.
		 * @return string
		 */
		public function token( $index ) {
			return '%%' . $this->hash() . '_' . (int) $index . '%%';
		}
	}
}

if ( ! class_exists( 'W3TC_Mph_HTML', false ) ) {
	/**
	 * Exposes the HTML placeholder id for assertions.
	 *
	 * @since X.X.X
	 */
	class W3TC_Mph_HTML extends \W3TCL\Minify\Minify_HTML {
		/**
		 * Builds an empty minifier so the id can be read before input is set.
		 *
		 * @since X.X.X
		 */
		public function __construct() {
			parent::__construct( '', array() );
		}

		/**
		 * Placeholder id for this instance.
		 *
		 * @since X.X.X
		 *
		 * @return string
		 */
		public function hash() {
			return $this->_ensurePlaceholderHash();
		}

		/**
		 * Replaces the HTML that process() will read.
		 *
		 * @since X.X.X
		 *
		 * @param string $html HTML input.
		 */
		public function set_html( $html ) {
			$this->_html = $html;
		}

		/**
		 * Runs minification.
		 *
		 * @since X.X.X
		 *
		 * @return string
		 */
		public function run() {
			return $this->process();
		}
	}
}

$mph_pass = 0;
$mph_fail = 0;

/**
 * Records one assertion.
 *
 * @since X.X.X
 *
 * @param string $label       Case name.
 * @param bool   $expectation Whether the case passed.
 * @param string $detail      Extra context on failure.
 */
function mph_assert_true( $label, $expectation, $detail = '' ) {
	global $mph_pass, $mph_fail;

	if ( $expectation ) {
		echo "PASS  $label\n";
		++$mph_pass;
		return;
	}

	echo 'FAIL  ' . $label . ( '' !== $detail ? " | $detail" : '' ) . "\n";
	++$mph_fail;
}

/**
 * True when $hash is a prefix plus 32 hex characters and is not a clock digest.
 *
 * @since X.X.X
 *
 * @param string $hash   Placeholder id.
 * @param string $prefix Expected prefix.
 * @return bool
 */
function mph_is_request_id( $hash, $prefix ) {
	if ( ! \is_string( $hash ) || ! \preg_match( '/^' . \preg_quote( $prefix, '/' ) . '[a-f0-9]{32}$/', $hash ) ) {
		return false;
	}

	$clock = isset( $_SERVER['REQUEST_TIME'] ) ? (string) $_SERVER['REQUEST_TIME'] : '';
	$now   = (string) \time();

	return $hash !== $prefix . \md5( $clock ) && $hash !== $prefix . \md5( $now );
}

/**
 * Builds a CSS compressor without invoking its private constructor.
 *
 * @since X.X.X
 *
 * @return array{0:\ReflectionClass,1:object}
 */
function mph_css_instance() {
	$ref = new \ReflectionClass( \W3TCL\Minify\Minify_CSS_Compressor::class );
	$obj = $ref->newInstanceWithoutConstructor();

	$options = $ref->getProperty( '_options' );
	$options->setAccessible( true );
	$options->setValue( $obj, array() );

	return array( $ref, $obj );
}

/**
 * Reads or creates the CSS placeholder id.
 *
 * @since X.X.X
 *
 * @param \ReflectionClass $ref Reflection of the compressor.
 * @param object           $obj Compressor instance.
 * @return string
 */
function mph_css_hash( $ref, $obj ) {
	$method = $ref->getMethod( '_ensurePlaceholderHash' );
	$method->setAccessible( true );
	return $method->invoke( $obj );
}

/**
 * Minifies CSS on an existing compressor instance.
 *
 * @since X.X.X
 *
 * @param \ReflectionClass $ref Reflection of the compressor.
 * @param object           $obj Compressor instance.
 * @param string           $css CSS input.
 * @return string
 */
function mph_css_process( $ref, $obj, $css ) {
	$method = $ref->getMethod( '_process' );
	$method->setAccessible( true );
	return $method->invoke( $obj, $css );
}

$one = new W3TC_Mph_Preserver();
$one->setIgnoredComments( array( 'mfunc' ) );
$two = new W3TC_Mph_Preserver();
$two->setIgnoredComments( array( 'mfunc' ) );

mph_assert_true(
	'ignored-comment ids differ across instances',
	$one->hash() !== $two->hash()
);
mph_assert_true(
	'ignored-comment id is a per-request token',
	mph_is_request_id( $one->hash(), 'IgnoredCommentPreserver_' )
);

$kept = $one->replace( $one->search( '<p>Hello</p><!-- mfunc KEEP-COMMENT -->' ) );
mph_assert_true(
	'ignored comment is restored when its token is absent from the input',
	false !== \strpos( $kept, '<!-- mfunc KEEP-COMMENT -->' ) && false === \strpos( $kept, '%%IgnoredCommentPreserver_' )
);

$seeded = new W3TC_Mph_Preserver();
$seeded->setIgnoredComments( array( 'mfunc' ) );
$token0 = $seeded->token( 0 );
$before = $seeded->hash();
$html   = '<abbr title="x=' . $token0 . '">marker</abbr><!-- mfunc FIRST-BODY --> <!-- mfunc SECOND-BODY -->';
$out    = $seeded->replace( $seeded->search( $html ) );
mph_assert_true(
	'ignored-comment id changes when the input already contains it',
	$seeded->hash() !== $before && false === \strpos( $html, $seeded->hash() )
);
mph_assert_true(
	'pre-seeded placeholder inside an attribute is left unchanged',
	false !== \strpos( $out, 'title="x=' . $token0 . '"' )
);
mph_assert_true(
	'ignored comments are restored when the id is extended',
	false !== \strpos( $out, '<!-- mfunc FIRST-BODY -->' ) && false !== \strpos( $out, '<!-- mfunc SECOND-BODY -->' )
);

$html_a = new W3TC_Mph_HTML();
$html_b = new W3TC_Mph_HTML();
mph_assert_true(
	'html placeholder ids differ across instances',
	$html_a->hash() !== $html_b->hash()
);
mph_assert_true(
	'html placeholder id is a per-request token',
	mph_is_request_id( $html_a->hash(), 'MINIFYHTML' )
);

$html_a->set_html( '<p>Hi</p><script>var kept=1;</script>' );
$html_kept = $html_a->run();
mph_assert_true(
	'script is restored when its token is absent from the input',
	false !== \strpos( $html_kept, 'var kept=1;' )
);

$html_area = new W3TC_Mph_HTML();
$html_area->set_html( '<textarea><script>var inside=1;</script></textarea>' );
$html_area_out = $html_area->run();
mph_assert_true(
	'script inside a textarea is restored on the second pass',
	false !== \strpos( $html_area_out, 'var inside=1;' ) && false !== \strpos( $html_area_out, '<textarea>' )
);

$html_seed   = new W3TC_Mph_HTML();
$html_before = $html_seed->hash();
$html_token  = '%' . $html_before . '0%';
$html_seed->set_html( '<abbr title="x=' . $html_token . '">marker</abbr><script>var kept=1;</script>' );
$html_out = $html_seed->run();
mph_assert_true(
	'html id changes when the input already contains it',
	$html_seed->hash() !== $html_before
);
mph_assert_true(
	'pre-seeded html token and script both survive',
	false !== \strpos( $html_out, $html_token ) && false !== \strpos( $html_out, 'var kept=1;' )
);

list( $css_ref_a, $css_a ) = mph_css_instance();
list( $css_ref_b, $css_b ) = mph_css_instance();
mph_assert_true(
	'css placeholder ids differ across instances',
	mph_css_hash( $css_ref_a, $css_a ) !== mph_css_hash( $css_ref_b, $css_b )
);
mph_assert_true(
	'css placeholder id is a per-request token',
	mph_is_request_id( mph_css_hash( $css_ref_a, $css_a ), 'MINIFYCSS' )
);

$css_kept = mph_css_process( $css_ref_a, $css_a, '.a{"kept-color"}' );
mph_assert_true(
	'css quote is restored when its token is absent from the input',
	false !== \strpos( $css_kept, '"kept-color"' )
);

list( $css_ref_seed, $css_seed ) = mph_css_instance();
$css_before = mph_css_hash( $css_ref_seed, $css_seed );
$css_token  = '"' . $css_before . '0"';
$css_out    = mph_css_process( $css_ref_seed, $css_seed, '.a{"kept-color"} .b{content:' . $css_token . '}' );
mph_assert_true(
	'css id changes when the input already contains it',
	mph_css_hash( $css_ref_seed, $css_seed ) !== $css_before
);
mph_assert_true(
	'pre-seeded css token and quoted value both survive',
	false !== \strpos( $css_out, $css_token ) && false !== \strpos( $css_out, '"kept-color"' )
);

echo "\n$mph_pass passed, $mph_fail failed\n";
exit( $mph_fail > 0 ? 1 : 0 );
