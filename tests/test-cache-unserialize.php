<?php
/**
 * Standalone regression test for Cache_Base::_unserialize().
 *
 * Verifies that the cache-backend deserialization helper:
 *   1. Round-trips primitives, arrays, and the WordPress core data classes.
 *   2. Treats payloads containing classes outside the allowlist as a cache
 *      miss (returns false) rather than handing back __PHP_Incomplete_Class
 *      stubs that would fatal the caller in PHP 8.
 *   3. Honors the `w3tc_cache_allowed_classes` filter so plugins can opt
 *      their own classes into the allowlist.
 *   4. Defends against the FileCookieJar __destruct gadget the original
 *      hardening targeted (PR #1319 / ENG7-3003).
 *
 * Run with: php tests/test-cache-unserialize.php
 *
 * Exit code 0 = all pass, non-zero = failures.
 *
 * @package W3TC\Tests
 * @since   X.X.X
 */

if ( realpath( __FILE__ ) !== realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) ) {
	return;
}

// ---------------------------------------------------------------------------
// Minimal WordPress filter stub. Cache_Base::_get_allowed_classes() calls
// apply_filters() when it exists; we provide a tiny implementation so the
// filter integration is exercised without booting WordPress.
// ---------------------------------------------------------------------------

$GLOBALS['__w3tc_filters'] = array();

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) {
		if ( ! isset( $GLOBALS['__w3tc_filters'][ $tag ] ) ) {
			return $value;
		}
		foreach ( $GLOBALS['__w3tc_filters'][ $tag ] as $cb ) {
			$value = call_user_func( $cb, $value );
		}
		return $value;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $tag, $cb ) {
		$GLOBALS['__w3tc_filters'][ $tag ][] = $cb;
	}
}

if ( ! function_exists( 'remove_all_filters' ) ) {
	function remove_all_filters( $tag ) {
		unset( $GLOBALS['__w3tc_filters'][ $tag ] );
	}
}

require_once __DIR__ . '/../Cache_Base.php';

// Stand-in classes for the WordPress data classes the helper allows by
// default. We declare them globally so the unserialize() allowlist matches
// the real class names that core would use in production.
if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post {
		public $ID;
		public $post_title;
		public function __construct( $id = 0, $title = '' ) {
			$this->ID         = $id;
			$this->post_title = $title;
		}
	}
}

// Fixture class outside the default allowlist. Stands in for a plugin's
// internal cache value (e.g. ACF_Field, WC_Session, etc.).
class W3TC_Test_PluginCacheValue {
	public $payload;
	public function __construct( $payload = '' ) {
		$this->payload = $payload;
	}
}

// Fixture wrapper class with a non-public property — exercises the
// (array)-cast property traversal in _contains_incomplete_class().
// get_object_vars() called from a base class would NOT see $hidden, so
// a disallowed object stored there would have leaked through the guard
// before this fix.
class W3TC_Test_AllowedWrapper {
	public $visible;
	protected $hidden;
	public function __construct( $visible = null, $hidden = null ) {
		$this->visible = $visible;
		$this->hidden  = $hidden;
	}
}

// Fixture gadget class with a __destruct side effect — proves that gadget
// __destruct never fires when the class is not on the allowlist.
class W3TC_Test_Gadget {
	public $sink_path;
	public $payload;
	public function __destruct() {
		// If this ever runs in the test, it writes a sentinel file we can
		// detect. With the helper, it must never run.
		if ( ! empty( $this->sink_path ) ) {
			@file_put_contents( $this->sink_path, (string) $this->payload );
		}
	}
}

// ---------------------------------------------------------------------------
// Test harness
// ---------------------------------------------------------------------------

$pass  = 0;
$fail  = 0;
$cases = array();

function cu_assert_true( $label, $expectation, $detail = '' ) {
	global $pass, $fail, $cases;
	if ( $expectation ) {
		$cases[] = array( 'PASS', $label );
		++$pass;
	} else {
		$cases[] = array( 'FAIL', $label . ( $detail ? " | $detail" : '' ) );
		++$fail;
	}
}

// Expose the protected helper for direct testing.
$probe = new class() extends \W3TC\Cache_Base {
	public function __construct() {
		// Skip parent constructor — we don't need the config bag for the
		// unserialize helper.
	}
	public function probe( $data ) {
		return $this->_unserialize( $data );
	}
};

// ---------------------------------------------------------------------------
// 1. Primitives and arrays round-trip unchanged.
// ---------------------------------------------------------------------------

cu_assert_true(
	'string round-trip',
	'hello' === $probe->probe( serialize( 'hello' ) )
);

cu_assert_true(
	'int round-trip',
	42 === $probe->probe( serialize( 42 ) )
);

cu_assert_true(
	'array round-trip',
	array( 'a' => 1, 'b' => array( 2, 3 ) ) === $probe->probe(
		serialize( array( 'a' => 1, 'b' => array( 2, 3 ) ) )
	)
);

cu_assert_true(
	'envelope-shaped array round-trip',
	array( 'key_version' => 1, 'value' => 'x' ) === $probe->probe(
		serialize( array( 'key_version' => 1, 'value' => 'x' ) )
	)
);

// ---------------------------------------------------------------------------
// 2. Allowed core classes survive the round-trip as real instances.
// ---------------------------------------------------------------------------

$post     = new WP_Post( 7, 'Hello' );
$envelope = array( 'content' => $post, 'key_version_all' => 5 );
$decoded  = $probe->probe( serialize( $envelope ) );

cu_assert_true(
	'WP_Post nested in envelope: outer array preserved',
	is_array( $decoded ) && isset( $decoded['content'], $decoded['key_version_all'] )
);
cu_assert_true(
	'WP_Post nested in envelope: inner value is WP_Post (not __PHP_Incomplete_Class)',
	is_array( $decoded ) && $decoded['content'] instanceof WP_Post
);
cu_assert_true(
	'WP_Post nested in envelope: properties intact',
	is_array( $decoded ) && 7 === $decoded['content']->ID && 'Hello' === $decoded['content']->post_title
);

// stdClass is on the default allowlist.
$obj    = (object) array( 'k' => 'v' );
$result = $probe->probe( serialize( $obj ) );
cu_assert_true(
	'stdClass round-trip',
	$result instanceof stdClass && 'v' === $result->k
);

// ---------------------------------------------------------------------------
// 3. Disallowed classes -> cache miss (not __PHP_Incomplete_Class leak).
// ---------------------------------------------------------------------------

$plugin_value = new W3TC_Test_PluginCacheValue( 'secret-payload' );
$result       = $probe->probe( serialize( $plugin_value ) );
cu_assert_true(
	'disallowed top-level object returns false (cache miss)',
	false === $result
);

$nested = array( 'content' => $plugin_value, 'key_version_all' => 1 );
$result = $probe->probe( serialize( $nested ) );
cu_assert_true(
	'disallowed object nested in envelope returns false (cache miss)',
	false === $result
);

$deep = array( 'a' => array( 'b' => array( 'c' => $plugin_value ) ) );
$result = $probe->probe( serialize( $deep ) );
cu_assert_true(
	'deeply nested disallowed object returns false (cache miss)',
	false === $result
);

// Allowed class with a disallowed object hiding inside one of its properties.
$post_with_payload             = new WP_Post( 1, 'x' );
$post_with_payload->post_title = $plugin_value;
$result                        = $probe->probe( serialize( $post_with_payload ) );
cu_assert_true(
	'allowed-class wrapper hiding disallowed object in PUBLIC property returns false',
	false === $result
);

// Same test against a non-public property of an allowed-by-filter wrapper.
// Regression guard for Copilot review: get_object_vars() called from
// Cache_Base only sees public props, so this case would have leaked
// before switching to (array) iteration.
add_filter( 'w3tc_cache_allowed_classes', function ( $allowed ) {
	$allowed[] = 'W3TC_Test_AllowedWrapper';
	return $allowed;
} );

$wrapper_with_hidden_payload = new W3TC_Test_AllowedWrapper( 'visible-ok', new W3TC_Test_PluginCacheValue( 'leaked' ) );
$result                      = $probe->probe( serialize( $wrapper_with_hidden_payload ) );
cu_assert_true(
	'allowed-class wrapper hiding disallowed object in PROTECTED property returns false',
	false === $result
);

remove_all_filters( 'w3tc_cache_allowed_classes' );

// ---------------------------------------------------------------------------
// 4. Filter extension: plugin opts its class into the allowlist.
// ---------------------------------------------------------------------------

add_filter( 'w3tc_cache_allowed_classes', function ( $allowed ) {
	$allowed[] = 'W3TC_Test_PluginCacheValue';
	return $allowed;
} );

$plugin_value = new W3TC_Test_PluginCacheValue( 'opted-in' );
$result       = $probe->probe( serialize( $plugin_value ) );
cu_assert_true(
	'filter-extended class round-trips as real instance',
	$result instanceof W3TC_Test_PluginCacheValue && 'opted-in' === $result->payload
);

remove_all_filters( 'w3tc_cache_allowed_classes' );

// ---------------------------------------------------------------------------
// 5. Gadget __destruct must never fire.
// ---------------------------------------------------------------------------

$sentinel = sys_get_temp_dir() . '/w3tc-gadget-sentinel-' . uniqid() . '.txt';
if ( file_exists( $sentinel ) ) {
	@unlink( $sentinel );
}

// Build the serialized blob by hand so we never instantiate a real
// W3TC_Test_Gadget (whose destructor would write the sentinel itself).
// Class-name byte count must match exactly — `O:N:"<name>":...` where N
// is strlen($name). "W3TC_Test_Gadget" is 16 bytes.
$payload_str  = 'rce';
$class_name   = 'W3TC_Test_Gadget';
$blob_inner   = 'O:' . strlen( $class_name ) . ':"' . $class_name . '":2:{'
	. 's:9:"sink_path";s:' . strlen( $sentinel ) . ':"' . $sentinel . '";'
	. 's:7:"payload";s:' . strlen( $payload_str ) . ':"' . $payload_str . '";'
	. '}';

// Sanity-check the blob actually parses (otherwise the assertion below
// would pass for the wrong reason — unserialize rejecting a malformed
// payload, not the helper guarding the gadget).
$sanity = @unserialize( $blob_inner );
cu_assert_true(
	'gadget fixture decodes as a real W3TC_Test_Gadget (sanity)',
	$sanity instanceof W3TC_Test_Gadget,
	'blob did not decode — fix length prefix'
);
// Force the sanity instance to be released without firing its destructor's
// file write. Clear sink_path first; this also means the sanity check
// itself does not leave a sentinel.
if ( $sanity instanceof W3TC_Test_Gadget ) {
	$sanity->sink_path = '';
}
unset( $sanity );
gc_collect_cycles();
if ( file_exists( $sentinel ) ) {
	@unlink( $sentinel );
}

$result = $probe->probe( $blob_inner );
// Force any incomplete-class instances to fall out of scope.
unset( $result );
gc_collect_cycles();

cu_assert_true(
	'gadget __destruct never fires when class is not on allowlist',
	! file_exists( $sentinel )
);

if ( file_exists( $sentinel ) ) {
	@unlink( $sentinel );
}

// ---------------------------------------------------------------------------
// 6. Depth fail-closed.
// Regression guard for Copilot review: when the decoded tree is nested
// deeper than the recursion limit, the helper must return false (treat as
// miss), not pass the un-inspected subtree through.
// ---------------------------------------------------------------------------

$deep = $plugin_value; // disallowed object at the bottom
for ( $i = 0; $i < 200; ++$i ) {
	$deep = array( 'next' => $deep );
}
$result = $probe->probe( serialize( $deep ) );
cu_assert_true(
	'over-deep nesting fails closed (cache miss, no leaked stub)',
	false === $result
);

// ---------------------------------------------------------------------------
// 7. Edge cases.
// ---------------------------------------------------------------------------

cu_assert_true(
	'empty string returns false',
	false === $probe->probe( '' )
);
cu_assert_true(
	'non-string returns false',
	false === $probe->probe( null )
);
cu_assert_true(
	'malformed payload returns false',
	false === $probe->probe( 'not a serialize stream' )
);
cu_assert_true(
	'literal false (b:0;) returns false (acceptable — no caller stores false)',
	false === $probe->probe( serialize( false ) )
);

// ---------------------------------------------------------------------------
// Report
// ---------------------------------------------------------------------------

foreach ( $cases as $row ) {
	echo str_pad( $row[0], 6 ) . $row[1] . "\n";
}
echo "\n";
echo "Passed: $pass\n";
echo "Failed: $fail\n";
exit( $fail > 0 ? 1 : 0 );
