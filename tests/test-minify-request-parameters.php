<?php
/**
 * Standalone test for request parameters used during inline minify generation.
 *
 * Run with: php tests/test-minify-request-parameters.php
 *
 * @package W3TC\Tests
 * @since   2.11.0
 */

if ( \realpath( __FILE__ ) !== \realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' ) ) {
	return;
}

if ( ! \defined( 'ABSPATH' ) ) {
	\define( 'ABSPATH', \dirname( __DIR__ ) . '/' );
}

require_once __DIR__ . '/minify-request-parameters-stubs.php';
require_once \dirname( __DIR__ ) . '/Minify_MinifiedFileRequestHandler.php';

/**
 * Config values used by the request-parameter test.
 *
 * @since 2.11.0
 */
class W3TC_Mrp_Config {
	/**
	 * Returns the file minify engine.
	 *
	 * @since 2.11.0
	 *
	 * @param string $key Config key.
	 * @return string
	 */
	public function get_string( $key ) {
		unset( $key );
		return 'file';
	}

	/**
	 * Enables minify rewriting.
	 *
	 * @since 2.11.0
	 *
	 * @param string $key Config key.
	 * @return bool
	 */
	public function get_boolean( $key ) {
		unset( $key );
		return true;
	}
}

/**
 * Handler that simulates the request mutations made by process().
 *
 * @since 2.11.0
 */
class W3TC_Mrp_Handler extends \W3TC\Minify_MinifiedFileRequestHandler {
	/**
	 * Whether process() should throw after changing request parameters.
	 *
	 * @var bool
	 */
	public $throw_during_process = false;

	/**
	 * Request parameters visible when process() starts.
	 *
	 * @var array
	 */
	public $request_before_process = array();

	/**
	 * Changes the same request parameters as the production process method.
	 *
	 * @since 2.11.0
	 *
	 * @param string|null $w3tc_file Requested minify filename.
	 * @param bool        $quiet     Whether output is suppressed.
	 * @return array
	 *
	 * @throws \RuntimeException When exception behavior is under test.
	 */
	public function process( $w3tc_file = null, $quiet = false ) {
		unset( $w3tc_file, $quiet );

		foreach ( array( 'f_array', 'ext', 'g' ) as $request_key ) {
			if ( \array_key_exists( $request_key, $_GET ) ) {
				$this->request_before_process[ $request_key ] = $_GET[ $request_key ];
			}
		}

		$_GET['f_array'] = array( 'generated.css' );
		$_GET['ext']     = 'css';
		$_GET['g']       = 'include';

		if ( $this->throw_during_process ) {
			throw new \RuntimeException( 'Test exception' );
		}

		return array();
	}
}

$mrp_pass = 0;
$mrp_fail = 0;

/**
 * Records one assertion.
 *
 * @since 2.11.0
 *
 * @param string $label       Case description.
 * @param bool   $expectation Assertion result.
 * @return void
 */
function mrp_assert_true( $label, $expectation ) {
	global $mrp_pass, $mrp_fail;

	if ( $expectation ) {
		echo "PASS  $label\n";
		++$mrp_pass;
		return;
	}

	echo "FAIL  $label\n";
	++$mrp_fail;
}

/**
 * Builds a handler with the required config stub.
 *
 * @since 2.11.0
 *
 * @return W3TC_Mrp_Handler
 */
function mrp_handler() {
	$handler  = new \ReflectionClass( W3TC_Mrp_Handler::class );
	$instance = $handler->newInstanceWithoutConstructor();
	$config   = new \ReflectionProperty( \W3TC\Minify_MinifiedFileRequestHandler::class, '_config' );
	$config->setAccessible( true );
	$config->setValue( $instance, new W3TC_Mrp_Config() );

	return $instance;
}

$original = array(
	'f_array' => array( 'visitor.js' ),
	'ext'     => null,
	'g'       => 'visitor-group',
	'other'   => 'unchanged',
);
$_GET      = $original;
$handler   = mrp_handler();

$handler->ensure_minify_file_exists( 'generated.css' );
mrp_assert_true( 'page request parameters are hidden during processing', array() === $handler->request_before_process );
mrp_assert_true( 'existing request parameters are restored', $original == $_GET );

$_GET = array( 'other' => 'unchanged' );
mrp_handler()->ensure_minify_file_exists( 'generated.css' );
mrp_assert_true(
	'initially absent request parameters stay absent',
	array( 'other' => 'unchanged' ) === $_GET
);

$original = array(
	'f_array' => array( 'visitor.css' ),
	'ext'     => 'visitor-ext',
);
$_GET      = $original;
$handler   = mrp_handler();
$handler->throw_during_process = true;

try {
	$handler->ensure_minify_file_exists( 'generated.css' );
} catch ( \RuntimeException $exception ) {
	unset( $exception );
}

mrp_assert_true( 'request parameters are restored after an exception', $original === $_GET );

echo "\n$mrp_pass passed, $mrp_fail failed\n";
exit( $mrp_fail > 0 ? 1 : 0 );
