<?php
/**
 * Standalone test for memcache-family item keys.
 *
 * Verifies that Cache_Memcache, Cache_Memcached and Cache_Nginx_Memcached
 * build item keys and key version keys through the fixed-length Cache_Base
 * helpers, that the keys stay within the 250-byte memcache key limit for
 * oversized inputs, and that distinct inputs never share a key.
 *
 * Cache_Nginx_Memcached page entries are stored under the raw key the nginx
 * rules compute ($memcached_key), not get_item_key(); only its counter, CAS
 * and stats paths use the keys tested here.
 *
 * When the pecl memcache extension and a memcached server on 127.0.0.1:11211
 * are available, also round-trips two items through Cache_Memcache.
 *
 * Run with: php tests/test-memcache-item-key.php
 *
 * Exit code 0 = all pass, non-zero = failures.
 *
 * @package W3TC\Tests
 * @since   2.11.1
 */

if ( realpath( __FILE__ ) !== realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) ) {
	return;
}

require_once __DIR__ . '/../Cache_Base.php';
require_once __DIR__ . '/../Cache_Memcache.php';
require_once __DIR__ . '/../Cache_Memcached.php';
require_once __DIR__ . '/../Cache_Nginx_Memcached.php';

$pass  = 0;
$fail  = 0;
$cases = array();

/**
 * Records a test result.
 *
 * @param string $label Case label.
 * @param bool   $cond  Condition.
 */
function mik_assert_true( $label, $cond ) {
	global $pass, $fail, $cases;
	if ( $cond ) {
		++$pass;
		$cases[] = array( 'PASS', $label );
	} else {
		++$fail;
		$cases[] = array( 'FAIL', $label );
	}
}

/**
 * Returns a writable reflection handle for a non-public property.
 *
 * @param string $class_name Declaring class.
 * @param string $name       Property name.
 *
 * @return ReflectionProperty
 */
function mik_property( $class_name, $name ) {
	$prop = new ReflectionProperty( $class_name, $name );
	if ( PHP_VERSION_ID < 80100 ) {
		$prop->setAccessible( true );
	}
	return $prop;
}

/**
 * Builds a cache engine instance without running its constructor (no server
 * connection) and sets the key components.
 *
 * @param string $class_name  Fully qualified class name.
 * @param int    $instance_id Instance id.
 * @param string $host        Host.
 * @param int    $blog_id     Blog id.
 * @param string $module      Module.
 *
 * @return object
 */
function mik_engine( $class_name, $instance_id, $host, $blog_id, $module ) {
	$ref    = new ReflectionClass( $class_name );
	$engine = $ref->newInstanceWithoutConstructor();
	$props  = array(
		'_instance_id' => $instance_id,
		'_host'        => $host,
		'_blog_id'     => $blog_id,
		'_module'      => $module,
	);
	foreach ( $props as $name => $value ) {
		mik_property( 'W3TC\Cache_Base', $name )->setValue( $engine, $value );
	}
	return $engine;
}

/**
 * Calls the protected _get_key_version_key() on an engine.
 *
 * @param object $engine     Engine.
 * @param string $w3tc_group Group.
 *
 * @return string
 */
function mik_version_key( $engine, $w3tc_group ) {
	$method = new ReflectionMethod( $engine, '_get_key_version_key' );
	if ( PHP_VERSION_ID < 80100 ) {
		$method->setAccessible( true );
	}
	return $method->invoke( $engine, $w3tc_group );
}

/**
 * The key format used before keys were hashed, for prefix comparisons.
 *
 * @param int    $instance_id Instance id.
 * @param string $host        Host.
 * @param int    $blog_id     Blog id.
 * @param string $module      Module.
 * @param string $name        Name.
 *
 * @return string
 */
function mik_legacy_key( $instance_id, $host, $blog_id, $module, $name ) {
	return sprintf( 'w3tc_%d_%s_%d_%s_%s', $instance_id, $host, $blog_id, $module, md5( $name ) );
}

$classes = array(
	'W3TC\Cache_Memcache',
	'W3TC\Cache_Memcached',
	'W3TC\Cache_Nginx_Memcached',
);

$long_host     = str_repeat( 'a', 320 ) . '.example.com';
$long_host_b   = str_repeat( 'a', 320 ) . '.example.org';
$long_name     = str_repeat( 'n', 5000 );
$long_instance = PHP_INT_MAX;

$key_pattern = '/^w3tc_[0-9a-f]{32}_[0-9a-f]{32}$/';

foreach ( $classes as $class_name ) {
	$short = substr( $class_name, 5 );

	$e = mik_engine( $class_name, $long_instance, $long_host, 2147483647, 'object' );

	$k = $e->get_item_key( $long_name );
	mik_assert_true( "$short: key <= 250 bytes for 300+ byte host, long name, long instance id", strlen( $k ) <= 250 );
	mik_assert_true( "$short: key is fixed length (70 bytes)", 70 === strlen( $k ) );
	mik_assert_true( "$short: key has only hex/underscore characters", 1 === preg_match( $key_pattern, $k ) );
	mik_assert_true( "$short: key contains no raw host", false === strpos( $k, 'example' ) );

	mik_assert_true( "$short: same inputs produce the same key", $k === $e->get_item_key( $long_name ) );
	mik_assert_true(
		"$short: fresh instance with same components produces the same key",
		$k === mik_engine( $class_name, $long_instance, $long_host, 2147483647, 'object' )->get_item_key( $long_name )
	);

	$e9 = mik_engine( $class_name, 1234567890, $long_host, 1, 'object' );

	$name_a = '0user_meta1';
	$name_b = '0user_meta2';
	mik_assert_true(
		"$short: legacy composite for two names shares a 250-byte prefix (fixture sanity)",
		substr( mik_legacy_key( 1234567890, $long_host, 1, 'object', $name_a ), 0, 250 )
			=== substr( mik_legacy_key( 1234567890, $long_host, 1, 'object', $name_b ), 0, 250 )
	);
	mik_assert_true(
		"$short: different names produce keys that differ within the first 250 bytes",
		substr( $e9->get_item_key( $name_a ), 0, 250 ) !== substr( $e9->get_item_key( $name_b ), 0, 250 )
	);

	$e9b = mik_engine( $class_name, 1234567890, $long_host_b, 1, 'object' );
	mik_assert_true(
		"$short: legacy composite for two hosts shares a 250-byte prefix (fixture sanity)",
		substr( mik_legacy_key( 1234567890, $long_host, 1, 'object', $name_a ), 0, 250 )
			=== substr( mik_legacy_key( 1234567890, $long_host_b, 1, 'object', $name_a ), 0, 250 )
	);
	mik_assert_true(
		"$short: different hosts produce keys that differ within the first 250 bytes",
		substr( $e9->get_item_key( $name_a ), 0, 250 ) !== substr( $e9b->get_item_key( $name_a ), 0, 250 )
	);

	mik_assert_true(
		"$short: different blog ids produce different keys",
		$e9->get_item_key( $name_a ) !== mik_engine( $class_name, 1234567890, $long_host, 2, 'object' )->get_item_key( $name_a )
	);
	mik_assert_true(
		"$short: different modules produce different keys",
		$e9->get_item_key( $name_a ) !== mik_engine( $class_name, 1234567890, $long_host, 1, 'dbcache' )->get_item_key( $name_a )
	);
	mik_assert_true(
		"$short: different instance ids produce different keys",
		$e9->get_item_key( $name_a ) !== mik_engine( $class_name, 1234567891, $long_host, 1, 'object' )->get_item_key( $name_a )
	);
	mik_assert_true(
		"$short: host/blog boundary shift does not collide",
		mik_engine( $class_name, 1, 'example.com|1', 2, 'object' )->get_item_key( 'x' )
			!== mik_engine( $class_name, 1, 'example.com', 1, 'object' )->get_item_key( 'x' )
	);
	mik_assert_true(
		"$short: same scope shares the key prefix",
		substr( $e9->get_item_key( $name_a ), 0, 38 ) === substr( $e9->get_item_key( $name_b ), 0, 38 )
	);

	$vk_long_group  = str_repeat( 'g', 400 );
	$vk             = mik_version_key( $e, $vk_long_group );
	$vk_pattern     = '/^w3tc_[0-9a-f]{32}_[0-9a-f]{32}_key_version$/';
	$vk_other_group = mik_version_key( $e, $vk_long_group . 'x' );
	mik_assert_true( "$short: version key <= 250 bytes for 400-byte group", strlen( $vk ) <= 250 );
	mik_assert_true( "$short: version key is fixed length (82 bytes)", 82 === strlen( $vk ) );
	mik_assert_true( "$short: version key has expected format", 1 === preg_match( $vk_pattern, $vk ) );
	mik_assert_true( "$short: version key is stable", $vk === mik_version_key( $e, $vk_long_group ) );
	mik_assert_true(
		"$short: different groups produce version keys that differ within the first 250 bytes",
		substr( $vk, 0, 250 ) !== substr( $vk_other_group, 0, 250 )
	);
	mik_assert_true(
		"$short: version key does not depend on host",
		mik_version_key( $e9, 'posts' ) === mik_version_key( $e9b, 'posts' )
	);
	mik_assert_true(
		"$short: different blog ids produce different version keys",
		mik_version_key( $e9, 'posts' ) !== mik_version_key( mik_engine( $class_name, 1234567890, $long_host, 2, 'object' ), 'posts' )
	);
	mik_assert_true(
		"$short: version key never equals an item key",
		mik_version_key( $e9, $name_a ) !== $e9->get_item_key( $name_a )
	);
}

$m  = mik_engine( 'W3TC\Cache_Memcache', 42, 'example.com', 3, 'object' );
$md = mik_engine( 'W3TC\Cache_Memcached', 42, 'example.com', 3, 'object' );
$n  = mik_engine( 'W3TC\Cache_Nginx_Memcached', 42, 'example.com', 3, 'object' );
mik_assert_true(
	'all three classes produce identical keys for identical inputs',
	$m->get_item_key( 'k' ) === $md->get_item_key( 'k' ) && $md->get_item_key( 'k' ) === $n->get_item_key( 'k' )
);
mik_assert_true(
	'key matches documented format w3tc_md5(instance|blog|module|host)_md5(name)',
	'w3tc_' . md5( '42|3|object|example.com' ) . '_' . md5( 'k' ) === $m->get_item_key( 'k' )
);

$base = mik_engine( 'W3TC\Cache_Base', 42, 'example.com', 3, 'object' );
mik_assert_true(
	'Cache_Base::get_item_key() format is unchanged',
	'w3tc_42_example.com_3_object_k' === $base->get_item_key( 'k' )
);
mik_assert_true(
	'Cache_Base::_get_key_version_key() format is unchanged',
	'w3tc_42_3_object_posts_key_version' === mik_version_key( $base, 'posts' )
);
mik_assert_true(
	'all three classes produce identical version keys for identical inputs',
	mik_version_key( $m, 'posts' ) === mik_version_key( $md, 'posts' ) && mik_version_key( $md, 'posts' ) === mik_version_key( $n, 'posts' )
);

/**
 * Optional round-trip against a real pecl memcache connection.
 */
$mik_live = null;
if ( class_exists( '\Memcache' ) ) {
	$mik_conn = new \Memcache();
	if ( @$mik_conn->connect( '127.0.0.1', 11211 ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$mik_live = $mik_conn;
	}
}

if ( null === $mik_live ) {
	$cases[] = array( 'SKIP', 'pecl memcache round-trip (no \Memcache or no server on 127.0.0.1:11211)' );
} else {
	$live_host = str_repeat( 'h', 300 ) . '.test';
	$live      = mik_engine( 'W3TC\Cache_Memcache', 1234567890, $live_host, 1, 'mik_test_' . uniqid() );
	mik_property( 'W3TC\Cache_Memcache', '_memcache' )->setValue( $live, $mik_live );
	mik_property( 'W3TC\Cache_Memcache', '_key_version' )->setValue( $live, array( '' => 1 ) );

	$live->set( $name_a, array( 'content' => 'one' ), 60 );
	$live->set( $name_b, array( 'content' => 'two' ), 60 );
	$got_a = $live->get( $name_a );
	$got_b = $live->get( $name_b );

	mik_assert_true( 'pecl memcache: first item reads back its own value', is_array( $got_a ) && 'one' === $got_a['content'] );
	mik_assert_true( 'pecl memcache: second item reads back its own value', is_array( $got_b ) && 'two' === $got_b['content'] );

	$live->hard_delete( $name_a );
	$live->hard_delete( $name_b );
}

foreach ( $cases as $row ) {
	echo str_pad( $row[0], 6 ) . $row[1] . "\n";
}
echo "\n";
echo "Passed: $pass\n";
echo "Failed: $fail\n";
exit( $fail > 0 ? 1 : 0 );
