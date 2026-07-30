<?php
/**
 * Standalone test for Apache cache .htaccess rules.
 *
 * Run with: php tests/test-apache-cache-rules.php
 *
 * @package W3TC\Tests
 * @since   2.10.2
 */

if ( \realpath( __FILE__ ) !== \realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) ) {
	return;
}

if ( ! \defined( 'W3TC_MARKER_BEGIN_PGCACHE_CACHE' ) ) {
	\define( 'W3TC_MARKER_BEGIN_PGCACHE_CACHE', '# BEGIN W3TC Page Cache cache' );
}

if ( ! \defined( 'W3TC_MARKER_END_PGCACHE_CACHE' ) ) {
	\define( 'W3TC_MARKER_END_PGCACHE_CACHE', '# END W3TC Page Cache cache' );
}

if ( ! \defined( 'W3TC_MARKER_BEGIN_MINIFY_CACHE' ) ) {
	\define( 'W3TC_MARKER_BEGIN_MINIFY_CACHE', '# BEGIN W3TC Minify cache' );
}

if ( ! \defined( 'W3TC_MARKER_END_MINIFY_CACHE' ) ) {
	\define( 'W3TC_MARKER_END_MINIFY_CACHE', '# END W3TC Minify cache' );
}

if ( ! \function_exists( 'get_option' ) ) {
	function get_option( $name ) {
		return 'blog_charset' === $name ? 'UTF-8' : false;
	}
}

if ( ! \function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $show ) {
		return 'pingback_url' === $show ? 'https://example.test/xmlrpc.php' : '';
	}
}

require_once __DIR__ . '/../Util_Environment.php';
require_once __DIR__ . '/../PgCache_Environment.php';
require_once __DIR__ . '/../Minify_Environment.php';

$_SERVER['SERVER_SOFTWARE'] = 'Apache/2.4.58';

$acr_pass  = 0;
$acr_fail  = 0;
$acr_cases = array();

class W3TC_Test_Apache_Cache_Rules_Config {
	private $values;

	public function __construct( array $values ) {
		$this->values = $values;
	}

	public function get_boolean( $key, $default_value = false ) {
		return (bool) $this->get( $key, $default_value );
	}

	public function get_integer( $key, $default_value = 0 ) {
		return (int) $this->get( $key, $default_value );
	}

	public function get_string( $key, $default_value = '', $trim = true ) {
		$value = (string) $this->get( $key, $default_value );

		return $trim ? \trim( $value ) : $value;
	}

	private function get( $key, $default_value ) {
		return \array_key_exists( $key, $this->values ) ? $this->values[ $key ] : $default_value;
	}
}

function acr_rules_from_private_generator( $class_name, $method_name, array $values ) {
	$class  = new \ReflectionClass( $class_name );
	$object = $class->newInstanceWithoutConstructor();
	$method = new \ReflectionMethod( $class_name, $method_name );

	$method->setAccessible( true );

	return $method->invoke( $object, new W3TC_Test_Apache_Cache_Rules_Config( $values ) );
}

function acr_assert( $label, $expectation, $detail = '' ) {
	global $acr_pass, $acr_fail, $acr_cases;

	if ( $expectation ) {
		$acr_cases[] = array( 'PASS', $label );
		++$acr_pass;
		return;
	}

	$acr_cases[] = array( 'FAIL', $label . ( $detail ? ' | ' . $detail : '' ) );
	++$acr_fail;
}

$pgcache_rules = acr_rules_from_private_generator(
	'\W3TC\PgCache_Environment',
	'rules_cache_generate_apache',
	array(
		'pgcache.compatibility' => true,
		'pgcache.enabled'       => true,
		'pgcache.engine'        => 'file_generic',
	)
);

acr_assert(
	'[1] page cache compatibility rules include Options -MultiViews',
	false !== \strpos( $pgcache_rules, "Options -MultiViews\n" ),
	$pgcache_rules
);
acr_assert(
	'[2] page cache compatibility block still writes file access rules',
	false !== \strpos( $pgcache_rules, '<Files ~' ),
	$pgcache_rules
);

$pgcache_disk_enhanced_rules = acr_rules_from_private_generator(
	'\W3TC\PgCache_Environment',
	'rules_cache_generate_apache',
	array(
		'pgcache.compatibility' => false,
		'pgcache.enabled'       => true,
		'pgcache.engine'        => 'file_generic',
	)
);

acr_assert(
	'[3] page cache disk enhanced rules skip compatibility block',
	false === \strpos( $pgcache_disk_enhanced_rules, '<Files ~' ),
	$pgcache_disk_enhanced_rules
);
acr_assert(
	'[4] page cache disk enhanced rules omit Options -MultiViews',
	false === \strpos( $pgcache_disk_enhanced_rules, "Options -MultiViews\n" ),
	$pgcache_disk_enhanced_rules
);

$minify_compatibility_rules = acr_rules_from_private_generator(
	'\W3TC\Minify_Environment',
	'rules_cache_generate_apache',
	array(
		'pgcache.compatibility' => true,
	)
);

acr_assert(
	'[5] minify compatibility rules include Options -MultiViews',
	false !== \strpos( $minify_compatibility_rules, "Options -MultiViews\n" ),
	$minify_compatibility_rules
);

$minify_rules = acr_rules_from_private_generator(
	'\W3TC\Minify_Environment',
	'rules_cache_generate_apache',
	array()
);

acr_assert(
	'[6] minify cache rules omit Options -MultiViews by default',
	false === \strpos( $minify_rules, "Options -MultiViews\n" ),
	$minify_rules
);
acr_assert(
	'[7] minify cache rules still include cache markers',
	false !== \strpos( $minify_rules, W3TC_MARKER_BEGIN_MINIFY_CACHE ) &&
		false !== \strpos( $minify_rules, W3TC_MARKER_END_MINIFY_CACHE ),
	$minify_rules
);

echo "\n  Apache cache .htaccess rules\n";
echo '  ' . \str_repeat( '-', 70 ) . "\n";

foreach ( $acr_cases as $case ) {
	$mark = 'PASS' === $case[0] ? 'PASS' : 'FAIL';
	\printf( "  %s  %s\n", $mark, $case[1] );
}

echo "\n  Total: " . ( $acr_pass + $acr_fail )
	. "  Passed: {$acr_pass}  Failed: {$acr_fail}\n\n";

exit( $acr_fail > 0 ? 1 : 0 );
