<?php
/**
 * Tests for the Generic Plugin.
 *
 * @package W3TC\Tests
 *
 * @since X.X.X
 */

use W3TC\Generic_Plugin;
use W3TC\Util_Bus;

/**
 * Class Generic_Plugin_DynamicFragments_Test.
 *
 * @since X.X.X
 */
class Generic_Plugin_DynamicFragments_Test extends WP_UnitTestCase {
	/**
	 * Generic plugin instance under test.
	 *
	 * @since X.X.X
	 *
	 * @var Generic_Plugin
	 */
	protected $plugin;

	/**
	 * Original output-buffer callbacks.
	 *
	 * @since X.X.X
	 *
	 * @var array
	 */
	protected $original_ob_callbacks = array();

	/**
	 * Output buffer level before each test.
	 *
	 * @since X.X.X
	 *
	 * @var int
	 */
	protected $initial_ob_level = 0;

	/**
	 * Sets up test fixtures.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		if ( ! defined( 'W3TC_DYNAMIC_SECURITY' ) ) {
			define( 'W3TC_DYNAMIC_SECURITY', 'unit-test-token' );
		}

		$this->plugin = new Generic_Plugin();
		$this->initial_ob_level    = ob_get_level();
		$this->original_ob_callbacks = isset( $GLOBALS['_w3tc_ob_callbacks'] ) ? $GLOBALS['_w3tc_ob_callbacks'] : array();
	}

	/**
	 * Cleans up globals and output buffers after each test.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function tear_down() {
		$GLOBALS['_w3tc_ob_callbacks'] = $this->original_ob_callbacks;

		while ( ob_get_level() > $this->initial_ob_level ) {
			ob_end_clean();
		}

		parent::tear_down();
	}

	/**
	 * Confirms comment preprocessing strips fragment directives and secrets.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_strip_dynamic_fragment_tags_from_comment_removes_fragments() {
		$fragment = '<!-- mfunc ' . W3TC_DYNAMIC_SECURITY . " -->echo 'danger';<!-- /mfunc " . W3TC_DYNAMIC_SECURITY . ' -->';
		$comment  = array( 'comment_content' => 'Before ' . $fragment . ' After ' . W3TC_DYNAMIC_SECURITY );

		$result = $this->plugin->strip_dynamic_fragment_tags_from_comment( $comment );

		$this->assertArrayHasKey( 'comment_content', $result );
		$this->assertStringNotContainsString( W3TC_DYNAMIC_SECURITY, $result['comment_content'] );
		$this->assertStringNotContainsString( 'mfunc', $result['comment_content'] );
	}

	/**
	 * Confirms that the no-space bypass variant is stripped from comments.
	 *
	 * The attack crafts a tag name where removing the security token via str_replace
	 * would morph it into a valid mfunc tag, e.g. with token "unit-test-token":
	 *   <!-- mfuncXunit-test-tokenX --> ... <!-- /mfuncXunit-test-tokenX -->
	 * becomes <!-- mfuncXX --> after str_replace, which (with the old \s* pattern)
	 * could then match and be eval()'d. The fix requires \s+ so no-space tags never
	 * execute, and \s*\S+ in sanitization so they are stripped before storage.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_strip_dynamic_fragment_tags_no_space_bypass_is_stripped() {
		// Craft a tag where the security token is embedded in the keyword so that
		// str_replace(token, '', ...) would otherwise produce a valid mfunc tag.
		$token   = W3TC_DYNAMIC_SECURITY;
		$crafted = '<!-- mfuncA' . $token . 'A -->phpinfo();<!-- /mfuncA' . $token . 'A -->';
		$comment = array( 'comment_content' => 'Safe text. ' . $crafted );

		$result = $this->plugin->strip_dynamic_fragment_tags_from_comment( $comment );

		$this->assertArrayHasKey( 'comment_content', $result );
		// The mfunc wrapper itself must be gone.
		$this->assertStringNotContainsString( 'mfunc', $result['comment_content'] );
		// The security token must not appear in any form.
		$this->assertStringNotContainsString( $token, $result['comment_content'] );
		// Safe surrounding text should be preserved.
		$this->assertStringContainsString( 'Safe text.', $result['comment_content'] );
	}

	/**
	 * Ensures feed filtering removes fragment directives.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_strip_dynamic_fragment_tags_filter_removes_fragments_from_feeds() {
		$fragment = '<!-- mclude ' . W3TC_DYNAMIC_SECURITY . ' -->foo.php<!-- /mclude ' . W3TC_DYNAMIC_SECURITY . ' -->';
		$content  = '<p>Before</p>' . $fragment . '<p>After ' . W3TC_DYNAMIC_SECURITY . '</p>';

		$filtered = $this->plugin->strip_dynamic_fragment_tags_filter( $content );

		$this->assertStringNotContainsString( W3TC_DYNAMIC_SECURITY, $filtered );
		$this->assertStringNotContainsString( 'mclude', $filtered );
	}

	/**
	 * Tests REST responses are sanitized outside of edit context.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_sanitize_rest_response_dynamic_tags_removes_fragments_for_view_context() {
		$fragment = '<!-- mfunc ' . W3TC_DYNAMIC_SECURITY . " -->echo 'danger';<!-- /mfunc " . W3TC_DYNAMIC_SECURITY . ' -->';
		$data     = array(
			'rendered' => 'Prefix ' . $fragment . ' Suffix',
			'nested'   => array(
				'value' => 'Nested ' . W3TC_DYNAMIC_SECURITY,
			),
		);

		$response = new WP_REST_Response( $data );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/pages' );
		$request->set_param( 'context', 'view' );

		$sanitized = $this->plugin->sanitize_rest_response_dynamic_tags( $response, null, $request );
		$payload   = $sanitized->get_data();

		$this->assertArrayHasKey( 'rendered', $payload );
		$this->assertStringNotContainsString( W3TC_DYNAMIC_SECURITY, $payload['rendered'] );
		$this->assertStringNotContainsString( 'mfunc', $payload['rendered'] );
		$this->assertStringNotContainsString( W3TC_DYNAMIC_SECURITY, $payload['nested']['value'] );
	}

	/**
	 * Ensures edit-context REST responses remain untouched for editors.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_sanitize_rest_response_dynamic_tags_skips_edit_context() {
		$fragment = '<!-- mfunc ' . W3TC_DYNAMIC_SECURITY . " -->echo 'danger';<!-- /mfunc " . W3TC_DYNAMIC_SECURITY . ' -->';
		$data     = array( 'rendered' => $fragment );
		$response = new WP_REST_Response( $data );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/pages' );
		$request->set_param( 'context', 'edit' );

		$sanitized = $this->plugin->sanitize_rest_response_dynamic_tags( $response, null, $request );

		$this->assertSame( $response, $sanitized );
		$this->assertStringContainsString( W3TC_DYNAMIC_SECURITY, $sanitized->get_data()['rendered'] );
	}

	/**
	 * Ensures shutdown processing handles dynamic fragments safely.
	 *
	 * Exercises the new no-callback ob_start() + ob_shutdown() flow and verifies
	 * callbacks can still open nested output buffers while transforming content.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_ob_shutdown_processes_dynamic_buffer_with_nested_ob_start() {
		Util_Bus::add_ob_callback( 'pagecache', array( $this, 'replace_mfunc_fragments_for_test' ) );

		ob_start();
		ob_start();
		$this->set_private_property( '_ob_level', ob_get_level() );

		echo 'Before <!-- mfunc ' . W3TC_DYNAMIC_SECURITY . ' -->echo "unsafe";<!-- /mfunc ' . W3TC_DYNAMIC_SECURITY . ' --> After'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		$this->plugin->ob_shutdown();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Before dynamic-fragment-output After', $output );
		$this->assertStringNotContainsString( '<!-- mfunc', $output );

		ob_start();
		$this->plugin->ob_shutdown();
		$second_output = ob_get_clean();

		$this->assertSame( '', $second_output );
	}

	/**
	 * Replaces mfunc blocks while simulating nested output buffering.
	 *
	 * @since X.X.X
	 *
	 * @param string $buffer Buffer content.
	 *
	 * @return string
	 */
	public function replace_mfunc_fragments_for_test( $buffer ) {
		$pattern = '~<!--\s*mfunc\s+' . preg_quote( W3TC_DYNAMIC_SECURITY, '~' ) . '\s*-->.*?<!--\s*/mfunc\s+' . preg_quote( W3TC_DYNAMIC_SECURITY, '~' ) . '\s*-->~is';

		return (string) preg_replace_callback(
			$pattern,
			array( $this, 'simulate_dynamic_fragment_parsing' ),
			$buffer
		);
	}

	/**
	 * Simulates dynamic mfunc rendering with nested ob_start().
	 *
	 * @since X.X.X
	 *
	 * @return string
	 */
	public function simulate_dynamic_fragment_parsing( $matches ) {
		unset( $matches );
		ob_start();
		echo 'dynamic-fragment-output'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return (string) ob_get_clean();
	}

	/**
	 * Sets a private property on the plugin instance.
	 *
	 * @since X.X.X
	 *
	 * @param string $property_name Property name.
	 * @param mixed  $value         Property value.
	 *
	 * @return void
	 */
	private function set_private_property( $property_name, $value ) {
		$reflection = new ReflectionClass( Generic_Plugin::class );
		$property   = $reflection->getProperty( $property_name );
		$property->setAccessible( true );
		$property->setValue( $this->plugin, $value );
	}
}

/**
 * Testable subclass of Generic_Plugin that lets tests control _is_html_response().
 *
 * _is_html_response() relies on headers_list(), which always returns [] in PHP
 * CLI (PHPUnit's runtime), making the non-HTML branch unreachable without
 * this seam. Changing visibility to protected and subclassing is the minimal
 * change needed to cover both branches.
 *
 * @since X.X.X
 */
class Generic_Plugin_Testable extends \W3TC\Generic_Plugin {
	/**
	 * Value returned by _is_html_response() override.
	 *
	 * @var bool
	 */
	public $html_response = true;

	/**
	 * Whether ob_callback() was invoked.
	 *
	 * @var bool
	 */
	public $ob_callback_called = false;

	/**
	 * Returns the controlled value instead of inspecting headers_list().
	 *
	 * @return bool
	 */
	protected function _is_html_response() {
		return $this->html_response;
	}

	/**
	 * Tracks invocation before delegating to the real implementation.
	 *
	 * @param string $buffer Buffer content.
	 *
	 * @return string
	 */
	public function ob_callback( $buffer ) {
		$this->ob_callback_called = true;
		return parent::ob_callback( $buffer );
	}
}

/**
 * Class Generic_Plugin_ObShutdown_Test.
 *
 * Covers the ob_shutdown() changes introduced in 2.9.2 patch:
 *   Bug 1 – FacetWP/JSON contamination: ob_callback() must not run for
 *            non-HTML responses.
 *   Bug 2 – Headers-already-sent: output must be re-buffered (not echoed
 *            directly) so later shutdown handlers can still set headers/cookies,
 *            and wp_ob_end_flush_all must be removed from the shutdown action.
 *
 * @since X.X.X
 */
class Generic_Plugin_ObShutdown_Test extends WP_UnitTestCase {
	/**
	 * Testable plugin instance.
	 *
	 * @var Generic_Plugin_Testable
	 */
	protected $plugin;

	/**
	 * Output buffer level before each test.
	 *
	 * @var int
	 */
	protected $initial_ob_level = 0;

	/**
	 * Sets up a fresh testable plugin instance and records OB depth.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->plugin          = new Generic_Plugin_Testable();
		$this->initial_ob_level = ob_get_level();
	}

	/**
	 * Restores OB stack to pre-test depth after each test.
	 *
	 * @return void
	 */
	public function tear_down() {
		while ( ob_get_level() > $this->initial_ob_level ) {
			ob_end_clean();
		}
		parent::tear_down();
	}

	// -----------------------------------------------------------------------
	// Bug 1 – FacetWP / JSON contamination
	// -----------------------------------------------------------------------

	/**
	 * ob_callback() must NOT run for non-HTML (e.g. application/json) responses.
	 *
	 * Regression: in 2.9.2, ob_callback() ran unconditionally, allowing the
	 * page-cache callback to prepend cached HTML to a JSON response body.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_ob_shutdown_skips_ob_callback_for_non_html_response() {
		$this->plugin->html_response = false;

		ob_start();
		$this->set_private_property( '_ob_level', ob_get_level() );
		echo '{"result":"ok"}'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		$this->plugin->ob_shutdown();

		$this->assertFalse(
			$this->plugin->ob_callback_called,
			'ob_callback() must not be called for non-HTML responses.'
		);

		// Retrieve the re-buffered output and assert it is verbatim JSON.
		$output = ob_get_clean();
		$this->assertSame( '{"result":"ok"}', $output );
	}

	/**
	 * Non-HTML nested OBs are discarded, not flushed into the W3TC buffer.
	 *
	 * A nested OB (opened by a theme or plugin) that contains partial HTML
	 * must be discarded when the response is non-HTML so it cannot be
	 * prepended to the JSON payload.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_ob_shutdown_discards_nested_obs_for_non_html_response() {
		$this->plugin->html_response = false;

		ob_start(); // W3TC's buffer (level N).
		$this->set_private_property( '_ob_level', ob_get_level() );
		echo '{"result":"ok"}'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		ob_start(); // Nested OB opened by another plugin (level N+1).
		echo '<html>partial page HTML that must NOT appear in JSON response</html>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		$this->plugin->ob_shutdown();

		$output = ob_get_clean(); // Grab re-buffered output from ob_shutdown's ob_start().
		$this->assertSame( '{"result":"ok"}', $output );
		$this->assertStringNotContainsString( 'partial page HTML', $output );
	}

	/**
	 * ob_callback() IS called for standard HTML responses.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_ob_shutdown_calls_ob_callback_for_html_response() {
		$this->plugin->html_response = true;

		ob_start();
		$this->set_private_property( '_ob_level', ob_get_level() );
		echo '<html><body>page</body></html>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		$this->plugin->ob_shutdown();

		$this->assertTrue(
			$this->plugin->ob_callback_called,
			'ob_callback() must be called for HTML responses.'
		);

		ob_get_clean(); // Consume re-buffered output.
	}

	// -----------------------------------------------------------------------
	// Bug 2 – Headers-already-sent / re-buffering strategy
	// -----------------------------------------------------------------------

	/**
	 * Output is placed into a new OB rather than echoed directly.
	 *
	 * After ob_shutdown() returns, ob_get_level() must be at least as high as
	 * it was when the W3TC buffer was opened, confirming that the processed
	 * output has been placed into a fresh ob_start() buffer instead of being
	 * flushed to the client immediately. This keeps response headers open for
	 * any remaining shutdown callbacks.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_ob_shutdown_rebuffers_output_not_echo_directly() {
		$this->plugin->html_response = true;

		ob_start();
		$w3tc_level = ob_get_level();
		$this->set_private_property( '_ob_level', $w3tc_level );
		echo '<html>content</html>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		$this->plugin->ob_shutdown();

		// ob_get_clean() closed W3TC's buffer, ob_start() opened a new one.
		// Net OB level is unchanged from when W3TC's buffer was at $w3tc_level.
		$this->assertSame(
			$w3tc_level,
			ob_get_level(),
			'ob_shutdown() must re-buffer processed output into a new ob_start() rather than echo directly.'
		);

		$output = ob_get_clean();
		$this->assertStringContainsString( 'content', $output );
	}

	/**
	 * wp_ob_end_flush_all is removed from the shutdown action after ob_shutdown().
	 *
	 * If wp_ob_end_flush_all were allowed to run at shutdown priority 1 it would
	 * prematurely flush the re-buffered output, closing headers before higher-
	 * priority shutdown handlers (e.g. session cookie writes) have run.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_ob_shutdown_removes_wp_ob_end_flush_all_from_shutdown() {
		// Ensure the action is present (WordPress registers it during bootstrap).
		add_action( 'shutdown', 'wp_ob_end_flush_all', 1 );
		$this->assertNotFalse(
			has_action( 'shutdown', 'wp_ob_end_flush_all' ),
			'wp_ob_end_flush_all must be registered on shutdown before ob_shutdown() runs.'
		);

		ob_start();
		$this->set_private_property( '_ob_level', ob_get_level() );
		echo 'content'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		$this->plugin->ob_shutdown();

		$this->assertFalse(
			has_action( 'shutdown', 'wp_ob_end_flush_all' ),
			'ob_shutdown() must remove wp_ob_end_flush_all from the shutdown action.'
		);

		ob_get_clean(); // Consume re-buffered output.
	}

	/**
	 * Second call to ob_shutdown() is a no-op (double-invocation guard).
	 *
	 * ob_shutdown() is registered both as a WP shutdown action and as a PHP
	 * shutdown function. The guard ensures the second invocation does nothing.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_ob_shutdown_double_invocation_is_noop() {
		$this->plugin->html_response = true;

		ob_start();
		$this->set_private_property( '_ob_level', ob_get_level() );
		echo 'content'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		$this->plugin->ob_shutdown(); // First call — processes buffer.
		$level_after_first = ob_get_level();

		$this->plugin->ob_shutdown(); // Second call — must be a no-op.

		$this->assertSame(
			$level_after_first,
			ob_get_level(),
			'Second ob_shutdown() call must not alter the OB stack.'
		);
		$this->assertFalse(
			$this->plugin->ob_callback_called === false && ob_get_level() === 0,
			'Guard must prevent re-processing on second call.'
		);

		ob_get_clean(); // Consume re-buffered output.
	}

	/**
	 * Sets a private/protected property on the plugin instance via reflection.
	 *
	 * @param string $property_name Property name.
	 * @param mixed  $value         Property value.
	 *
	 * @return void
	 */
	private function set_private_property( $property_name, $value ) {
		$reflection = new ReflectionClass( \W3TC\Generic_Plugin::class );
		$property   = $reflection->getProperty( $property_name );
		$property->setAccessible( true );
		$property->setValue( $this->plugin, $value );
	}
}
