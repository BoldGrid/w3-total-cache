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
	 * wp_ob_end_flush_all is NOT removed from the shutdown action on the main path.
	 *
	 * Allowing wp_ob_end_flush_all to run at shutdown priority 1 flushes the
	 * re-buffered output early, so the client receives the response before
	 * heavier shutdown handlers (e.g. WooCommerce order processing) execute.
	 * Removing it caused minute-long delays on WooCommerce order-completion pages.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_ob_shutdown_does_not_remove_wp_ob_end_flush_all_on_main_path() {
		// Capture the original shutdown hook priority so we can restore it after the test.
		$original_priority = has_action( 'shutdown', 'wp_ob_end_flush_all' );

		try {
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

			$this->assertNotFalse(
				has_action( 'shutdown', 'wp_ob_end_flush_all' ),
				'ob_shutdown() must NOT remove wp_ob_end_flush_all on the main path — removal causes WooCommerce response delays.'
			);
		} finally {
			// Consume re-buffered output if the buffer is still active.
			if ( ob_get_level() > 0 ) {
				ob_get_clean();
			}
			// Restore the shutdown hook registry to its original state.
			remove_action( 'shutdown', 'wp_ob_end_flush_all' );
			if ( false !== $original_priority ) {
				add_action( 'shutdown', 'wp_ob_end_flush_all', $original_priority );
			}
		}
	}

	/**
	 * When the W3TC buffer was already closed, ob_shutdown() must still remove
	 * wp_ob_end_flush_all so Core does not flush at shutdown priority 1 before
	 * late handlers (e.g. setcookie at priority 999).
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_ob_shutdown_early_return_still_removes_wp_ob_end_flush_all() {
		$original_priority = has_action( 'shutdown', 'wp_ob_end_flush_all' );

		try {
			add_action( 'shutdown', 'wp_ob_end_flush_all', 1 );
			$this->assertNotFalse(
				has_action( 'shutdown', 'wp_ob_end_flush_all' ),
				'wp_ob_end_flush_all must be registered on shutdown before ob_shutdown() runs.'
			);

			ob_start();
			$this->set_private_property( '_ob_level', ob_get_level() );
			ob_end_clean();

			$this->plugin->ob_shutdown();

			$this->assertFalse(
				has_action( 'shutdown', 'wp_ob_end_flush_all' ),
				'ob_shutdown() must remove wp_ob_end_flush_all even when its buffer was already closed.'
			);
		} finally {
			remove_action( 'shutdown', 'wp_ob_end_flush_all' );
			if ( false !== $original_priority ) {
				add_action( 'shutdown', 'wp_ob_end_flush_all', $original_priority );
			}
		}
	}

	/**
	 * When an external flush closes the W3TC buffer before ob_shutdown runs,
	 * output must reach the parent buffer and ob_shutdown must exit cleanly.
	 *
	 * If an external mechanism (e.g. an early ob_end_flush() call) closes the
	 * W3TC output buffer before ob_shutdown executes, ob_shutdown must detect
	 * the already-closed buffer via the early-return path, remove
	 * wp_ob_end_flush_all, and return without opening a new ob_start() or
	 * calling ob_callback(). The flushed content lands in the parent buffer
	 * (e.g. PHP's output_buffering layer), keeping response headers open for
	 * any remaining shutdown handlers (e.g. setcookie at a higher priority).
	 *
	 * @since 2.9.3
	 *
	 * @return void
	 */
	public function test_ob_shutdown_output_reaches_parent_buffer_when_wp_ob_end_flush_all_runs_first() {
		$this->plugin->html_response = true;

		/*
		 * Open a parent buffer to simulate PHP's output_buffering layer (or the
		 * web server's implicit buffer). This acts as the container into which
		 * wp_ob_end_flush_all flushes W3TC's buffer in production.
		 */
		ob_start();
		$parent_level = ob_get_level();

		// Open W3TC's buffer and record the level, exactly as run() does.
		ob_start();
		$w3tc_level = ob_get_level();
		$this->set_private_property( '_ob_level', $w3tc_level );
		echo '<html>page content</html>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		// Ensure wp_ob_end_flush_all is registered so ob_shutdown can remove it.
		$original_priority = has_action( 'shutdown', 'wp_ob_end_flush_all' );
		add_action( 'shutdown', 'wp_ob_end_flush_all', 1 );

		try {
			/*
			 * Simulate wp_ob_end_flush_all() running first at priority 1.
			 * wp_ob_end_flush_all() calls ob_end_flush() for every open level,
			 * flushing each buffer into its parent. Here we replicate that behaviour
			 * only down to $parent_level so we do not disturb PHPUnit's own buffers.
			 */
			for ( $i = ob_get_level(); $i > $parent_level; $i-- ) {
				ob_end_flush();
			}

			// W3TC's buffer is now closed; ob_get_level() dropped below _ob_level.
			$this->assertLessThan(
				$w3tc_level,
				ob_get_level(),
				'wp_ob_end_flush_all must have closed the W3TC buffer before ob_shutdown runs.'
			);

			/*
			 * ob_shutdown runs second at priority 1. It must detect the
			 * already-closed buffer, remove wp_ob_end_flush_all, and return
			 * early without opening a new ob_start() or calling ob_callback().
			 */
			$this->plugin->ob_shutdown();

			// ob_callback must NOT have run — the buffer was already closed.
			$this->assertFalse(
				$this->plugin->ob_callback_called,
				'ob_callback() must not run when the W3TC buffer was already closed by wp_ob_end_flush_all.'
			);

			// wp_ob_end_flush_all must have been removed from the shutdown action.
			$this->assertFalse(
				has_action( 'shutdown', 'wp_ob_end_flush_all' ),
				'ob_shutdown() must remove wp_ob_end_flush_all on the early-return path.'
			);

			// OB stack must remain at $parent_level: ob_shutdown must not open a
			// new buffer on the early-return path.
			$this->assertSame(
				$parent_level,
				ob_get_level(),
				'ob_shutdown() must not alter the OB level on the early-return path.'
			);

			// Page content must be present in the parent buffer, confirming that
			// output is still held in a buffer (not sent to the client), so late
			// shutdown handlers can still call setcookie() / header().
			$output = ob_get_clean();
			$this->assertStringContainsString(
				'page content',
				$output,
				'Page content must reach the parent buffer rather than being sent directly to the client.'
			);
		} finally {
			// Restore the shutdown hook registry to its pre-test state.
			remove_action( 'shutdown', 'wp_ob_end_flush_all', 1 );
			if ( false !== $original_priority ) {
				add_action( 'shutdown', 'wp_ob_end_flush_all', $original_priority );
			}
			// Clean up any leftover buffers.
			while ( ob_get_level() > $this->initial_ob_level ) {
				ob_end_clean();
			}
		}
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
		$level_after_first          = ob_get_level();
		$callback_state_after_first = $this->plugin->ob_callback_called;
		$buffer_after_first         = ob_get_contents();

		$this->plugin->ob_shutdown(); // Second call — must be a no-op.

		$this->assertSame(
			$level_after_first,
			ob_get_level(),
			'Second ob_shutdown() call must not alter the OB stack.'
		);
		$this->assertSame(
			$callback_state_after_first,
			$this->plugin->ob_callback_called,
			'Second ob_shutdown() call must not invoke ob_callback() again or change its tracking state.'
		);
		$this->assertSame(
			$buffer_after_first,
			ob_get_contents(),
			'Second ob_shutdown() call must not modify the output buffer contents.'
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
