<?php
/**
 * Tests for the Generic Plugin.
 *
 * @package W3TC\Tests
 *
 * @since X.X.X
 */

use W3TC\Generic_Plugin;

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
	 * Output buffering must not be disabled for a spoofed W3 Total Cache user-agent (mfunc secret leak).
	 *
	 * @since X.X.X
	 *
	 * @return void
	 */
	public function test_can_ob_allows_output_buffering_when_user_agent_matches_w3tc_powered_by() {
		global $w3_late_init;

		$prev_late    = $w3_late_init;
		$w3_late_init = false;
		$prev_ua      = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : null;

		$_SERVER['HTTP_USER_AGENT'] = W3TC_POWERED_BY;

		$plugin = new Generic_Plugin();
		$can_ob = $plugin->can_ob();

		if ( null === $prev_ua ) {
			unset( $_SERVER['HTTP_USER_AGENT'] );
		} else {
			$_SERVER['HTTP_USER_AGENT'] = $prev_ua;
		}

		$w3_late_init = $prev_late;

		$this->assertTrue(
			$can_ob,
			'can_ob() must remain true when HTTP_USER_AGENT contains W3TC_POWERED_BY (client-controlled).'
		);
	}
}
