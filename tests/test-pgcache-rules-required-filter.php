<?php
/**
 * Standalone test for the "w3tc_pgcache_rules_required" filter.
 *
 * Run with: php tests/test-pgcache-rules-required-filter.php
 *
 * @package W3TC\Tests
 * @since   X.X.X
 */

if ( \realpath( __FILE__ ) !== \realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) ) {
	return;
}

$prr_cases = array();
$prr_pass  = 0;
$prr_fail  = 0;

$GLOBALS['prr_filters'] = array();

if ( ! \function_exists( 'add_filter' ) ) {
	/**
	 * Minimal add_filter() stub.
	 *
	 * @param string   $tag      Filter name.
	 * @param callable $callback Callback.
	 *
	 * @return void
	 */
	function add_filter( $tag, $callback ) {
		$GLOBALS['prr_filters'][ $tag ][] = $callback;
	}
}

if ( ! \function_exists( 'apply_filters' ) ) {
	/**
	 * Minimal apply_filters() stub.
	 *
	 * @param string $tag   Filter name.
	 * @param mixed  $value Value being filtered.
	 *
	 * @return mixed
	 */
	function apply_filters( $tag, $value ) {
		$args = \array_slice( \func_get_args(), 2 );

		foreach ( $GLOBALS['prr_filters'][ $tag ] ?? array() as $callback ) {
			$value = \call_user_func_array( $callback, \array_merge( array( $value ), $args ) );
		}

		return $value;
	}
}

require_once \dirname( __DIR__ ) . '/PgCache_Environment.php';

/**
 * Records the outcome of one assertion.
 *
 * @param string $label       Case description.
 * @param bool   $expectation Assertion result.
 * @param string $detail      Optional detail printed on failure.
 *
 * @return void
 */
function prr_assert( string $label, bool $expectation, string $detail = '' ): void {
	global $prr_cases, $prr_pass, $prr_fail;

	if ( $expectation ) {
		++$prr_pass;
		$prr_cases[] = array( 'PASS', $label );

		return;
	}

	++$prr_fail;
	$prr_cases[] = array( 'FAIL', $label . ( $detail ? ' | ' . $detail : '' ) );
}

/**
 * Config stub exposing only what is_rules_required() reads.
 */
class PRR_Config_Stub {
	/**
	 * Values keyed by config key.
	 *
	 * @var array
	 */
	private $values;

	/**
	 * Constructor.
	 *
	 * @param array $values Values keyed by config key.
	 */
	public function __construct( array $values ) {
		$this->values = $values;
	}

	/**
	 * Returns a boolean config value.
	 *
	 * @param string $key Config key.
	 *
	 * @return bool
	 */
	public function get_boolean( $key ) {
		return (bool) ( $this->values[ $key ] ?? false );
	}

	/**
	 * Returns a string config value.
	 *
	 * @param string $key Config key.
	 *
	 * @return string
	 */
	public function get_string( $key ) {
		return (string) ( $this->values[ $key ] ?? '' );
	}
}

/**
 * Calls is_rules_required() with a stub config.
 *
 * @param bool   $enabled Whether page cache is enabled.
 * @param string $engine  Page cache engine.
 *
 * @return bool
 */
function prr_required( bool $enabled, string $engine ): bool {
	$environment = new \W3TC\PgCache_Environment();
	$method      = new \ReflectionMethod( '\W3TC\PgCache_Environment', 'is_rules_required' );
	$method->setAccessible( true );

	return $method->invoke(
		$environment,
		new PRR_Config_Stub(
			array(
				'pgcache.enabled' => $enabled,
				'pgcache.engine'  => $engine,
			)
		)
	);
}

// Defaults, with nothing hooked.
prr_assert( '[1] required for Disk: Enhanced', true === prr_required( true, 'file_generic' ) );
prr_assert( '[2] required for nginx + memcached', true === prr_required( true, 'nginx_memcached' ) );
prr_assert( '[3] not required for Disk: Basic', false === prr_required( true, 'file' ) );
prr_assert( '[4] not required when page cache is disabled', false === prr_required( false, 'file_generic' ) );

// A callback returning false suppresses the rules.
add_filter( 'w3tc_pgcache_rules_required', 'prr_return_false' );

/**
 * Returns false regardless of input.
 *
 * @return bool
 */
function prr_return_false(): bool {
	return false;
}

prr_assert( '[5] a callback returning false suppresses the rules', false === prr_required( true, 'file_generic' ) );

// The config object is passed to the callback.
$GLOBALS['prr_filters'] = array();
add_filter(
	'w3tc_pgcache_rules_required',
	function ( $required, $config ) {
		return $required && 'file_generic' === $config->get_string( 'pgcache.engine' );
	}
);

prr_assert( '[6] config reaches the callback, Disk: Enhanced kept', true === prr_required( true, 'file_generic' ) );
prr_assert( '[7] config reaches the callback, other engine suppressed', false === prr_required( true, 'nginx_memcached' ) );

// A non-boolean return is cast.
$GLOBALS['prr_filters'] = array();
add_filter(
	'w3tc_pgcache_rules_required',
	function () {
		return 'yes';
	}
);

prr_assert( '[8] a non-boolean return is cast to bool', true === prr_required( true, 'file_generic' ) );

// A callback cannot turn the rules on where they are not used.
$GLOBALS['prr_filters'] = array();
add_filter(
	'w3tc_pgcache_rules_required',
	function () {
		return true;
	}
);

prr_assert( '[9] a callback cannot force rules on for Disk: Basic', false === prr_required( true, 'file' ) );
prr_assert( '[10] a callback cannot force rules on when page cache is disabled', false === prr_required( false, 'file_generic' ) );

echo "\n  Page cache rules-required filter\n";
echo '  ' . \str_repeat( '-', 70 ) . "\n";

foreach ( $prr_cases as $case ) {
	$mark = 'PASS' === $case[0] ? 'PASS' : 'FAIL';
	\printf( "  %s  %s\n", $mark, $case[1] );
}

echo "\n  Total: " . ( $prr_pass + $prr_fail )
	. "  Passed: {$prr_pass}  Failed: {$prr_fail}\n\n";

exit( $prr_fail > 0 ? 1 : 0 );
