<?php
/**
 * Standalone test for ConfigState::save() skip-if-unchanged behavior.
 *
 * Run with: php tests/test-config-state-save.php
 *
 * @package W3TC\Tests
 * @since   X.X.X
 */

if ( \realpath( __FILE__ ) !== \realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) ) {
	return;
}

$cssave_cases = array();
$cssave_pass  = 0;
$cssave_fail  = 0;

$GLOBALS['cssave_blog_option']  = false;
$GLOBALS['cssave_site_option']  = false;
$GLOBALS['cssave_blog_writes']  = 0;
$GLOBALS['cssave_site_writes']  = 0;

if ( ! \defined( 'W3TC_VERSION' ) ) {
	\define( 'W3TC_VERSION', '0.0.0-test' );
}

if ( ! \function_exists( 'wp_json_encode' ) ) {
	/**
	 * Minimal wp_json_encode() stub.
	 *
	 * @param mixed $data Data to encode.
	 *
	 * @return string|false
	 */
	function wp_json_encode( $data ) {
		return \json_encode( $data );
	}
}

if ( ! \function_exists( 'get_option' ) ) {
	/**
	 * Stub get_option() for w3tc_state.
	 *
	 * @param string $option Option name.
	 *
	 * @return mixed
	 */
	function get_option( $option ) {
		if ( 'w3tc_state' !== $option ) {
			return false;
		}

		return $GLOBALS['cssave_blog_option'];
	}
}

if ( ! \function_exists( 'update_option' ) ) {
	/**
	 * Stub update_option() for w3tc_state.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Option value.
	 *
	 * @return bool
	 */
	function update_option( $option, $value ) {
		if ( 'w3tc_state' !== $option ) {
			return false;
		}

		++$GLOBALS['cssave_blog_writes'];
		$GLOBALS['cssave_blog_option'] = $value;

		return true;
	}
}

if ( ! \function_exists( 'get_site_option' ) ) {
	/**
	 * Stub get_site_option() for w3tc_state.
	 *
	 * @param string $option Option name.
	 *
	 * @return mixed
	 */
	function get_site_option( $option ) {
		if ( 'w3tc_state' !== $option ) {
			return false;
		}

		return $GLOBALS['cssave_site_option'];
	}
}

if ( ! \function_exists( 'update_site_option' ) ) {
	/**
	 * Stub update_site_option() for w3tc_state.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Option value.
	 *
	 * @return bool
	 */
	function update_site_option( $option, $value ) {
		if ( 'w3tc_state' !== $option ) {
			return false;
		}

		++$GLOBALS['cssave_site_writes'];
		$GLOBALS['cssave_site_option'] = $value;

		return true;
	}
}

require_once \dirname( __DIR__ ) . '/ConfigState.php';

/**
 * Records the outcome of one assertion.
 *
 * @param string $label       Case description.
 * @param bool   $expectation Assertion result.
 * @param string $detail      Optional detail printed on failure.
 *
 * @return void
 */
function cssave_assert( string $label, bool $expectation, string $detail = '' ): void {
	global $cssave_cases, $cssave_pass, $cssave_fail;

	if ( $expectation ) {
		++$cssave_pass;
		$cssave_cases[] = array( 'PASS', $label );

		return;
	}

	++$cssave_fail;
	$cssave_cases[] = array( 'FAIL', $label . ( $detail ? ' | ' . $detail : '' ) );
}

/**
 * Resets option stubs between cases.
 *
 * @return void
 */
function cssave_reset_store(): void {
	$GLOBALS['cssave_blog_option'] = false;
	$GLOBALS['cssave_site_option'] = false;
	$GLOBALS['cssave_blog_writes'] = 0;
	$GLOBALS['cssave_site_writes'] = 0;
}

cssave_reset_store();
$state = new \W3TC\ConfigState( false );
cssave_assert(
	'empty blog option seeds defaults with one write',
	1 === $GLOBALS['cssave_blog_writes'],
	'writes=' . $GLOBALS['cssave_blog_writes']
);
$writes_after_seed = $GLOBALS['cssave_blog_writes'];
$state->save();
cssave_assert(
	'repeat save after seed does not write',
	$writes_after_seed === $GLOBALS['cssave_blog_writes'],
	'writes=' . $GLOBALS['cssave_blog_writes']
);
$state->set( 'license.status', 'no_key' );
$state->save();
cssave_assert(
	'changed key writes once',
	$writes_after_seed + 1 === $GLOBALS['cssave_blog_writes'],
	'writes=' . $GLOBALS['cssave_blog_writes']
);
$state->save();
cssave_assert(
	'repeat save after change does not write',
	$writes_after_seed + 1 === $GLOBALS['cssave_blog_writes'],
	'writes=' . $GLOBALS['cssave_blog_writes']
);
$state->set( 'license.status', 'no_key' );
$state->save();
cssave_assert(
	'setting the same value does not write',
	$writes_after_seed + 1 === $GLOBALS['cssave_blog_writes'],
	'writes=' . $GLOBALS['cssave_blog_writes']
);

cssave_reset_store();
$GLOBALS['cssave_blog_option'] = \wp_json_encode(
	array(
		'common.install' => 1,
		'license.status' => 'no_key',
	)
);
$state = new \W3TC\ConfigState( false );
cssave_assert(
	'existing blog JSON does not write on construct',
	0 === $GLOBALS['cssave_blog_writes'],
	'writes=' . $GLOBALS['cssave_blog_writes']
);
$state->save();
cssave_assert(
	'existing blog JSON does not write on unchanged save',
	0 === $GLOBALS['cssave_blog_writes'],
	'writes=' . $GLOBALS['cssave_blog_writes']
);

cssave_reset_store();
$GLOBALS['cssave_site_option'] = \wp_json_encode(
	array(
		'common.install' => 1,
	)
);
$state = new \W3TC\ConfigState( true );
$state->save();
cssave_assert(
	'existing site JSON does not write on unchanged save',
	0 === $GLOBALS['cssave_site_writes'],
	'writes=' . $GLOBALS['cssave_site_writes']
);
$state->set( 'common.hide_note_nginx_restart_required', true );
$state->save();
cssave_assert(
	'master change writes site option once',
	1 === $GLOBALS['cssave_site_writes'] && 0 === $GLOBALS['cssave_blog_writes'],
	'site=' . $GLOBALS['cssave_site_writes'] . ' blog=' . $GLOBALS['cssave_blog_writes']
);
$state->set( 'common.hide_note_nginx_restart_required', true );
$state->save();
cssave_assert(
	'rewriting the same master flag does not write',
	1 === $GLOBALS['cssave_site_writes'],
	'writes=' . $GLOBALS['cssave_site_writes']
);

cssave_reset_store();
$GLOBALS['cssave_blog_option'] = \wp_json_encode( array( 'common.install' => 1 ) );
$state                         = new \W3TC\ConfigState( false );
$state->reset();
$state->save();
cssave_assert(
	'reset followed by save writes',
	$GLOBALS['cssave_blog_writes'] >= 1,
	'writes=' . $GLOBALS['cssave_blog_writes']
);

echo "ConfigState save skip tests\n";
foreach ( $cssave_cases as $row ) {
	echo $row[0] . '  ' . $row[1] . "\n";
}
echo "\n{$cssave_pass} passed, {$cssave_fail} failed\n";

exit( $cssave_fail > 0 ? 1 : 0 );
