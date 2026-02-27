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
