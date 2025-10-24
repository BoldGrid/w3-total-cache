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
}
