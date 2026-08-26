<?php
/**
 * File: class-w3tc-forums-api-test.php
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.10.6
 */

declare( strict_types = 1 );

use W3TC\Generic_Forums_Api;
use W3TC\Generic_Plugin_Admin;

/**
 * Class: W3tc_Forums_Api_Test
 *
 * Forums AJAX returns a parsed topic list. Raw HTTP envelopes, cookies,
 * headers, and transport messages stay off the wire.
 *
 * @since 2.10.6
 */
class W3tc_Forums_Api_Test extends WP_UnitTestCase {

	/**
	 * Captured outbound URLs from HTTP mocks.
	 *
	 * @var array
	 */
	private $http_urls = array();

	/**
	 * AJAX die handler that throws WPDieException instead of exiting.
	 *
	 * @since 2.10.6
	 *
	 * @return callable
	 */
	public static function ajax_die_handler() {
		return static function ( $message = '' ) {
			throw new \WPDieException( (string) $message );
		};
	}

	/**
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$this->http_urls = array();
		\add_filter( 'wp_doing_ajax', '__return_true' );
		\add_filter( 'wp_die_ajax_handler', array( __CLASS__, 'ajax_die_handler' ) );
	}

	/**
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function tear_down() {
		\remove_all_filters( 'pre_http_request' );
		\remove_filter( 'wp_die_ajax_handler', array( __CLASS__, 'ajax_die_handler' ) );
		\remove_filter( 'wp_doing_ajax', '__return_true' );
		$this->http_urls = array();
		wp_set_current_user( 0 );
		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();
		parent::tear_down();
	}

	/**
	 * Valid API list becomes `{ topics: [ { title, link } ] }`.
	 *
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function test_normalize_valid_body_shape() {
		$body = wp_json_encode(
			array(
				array(
					'title'   => 'Page Cache &amp; CDN',
					'link'    => 'https://www.boldgrid.com/support/page-cache/',
					'excerpt' => '<script>alert(1)</script>',
					'id'      => 99,
				),
			)
		);

		$result  = Generic_Forums_Api::normalize( $this->http_envelope( $body ) );
		$payload = Generic_Forums_Api::client_payload( $result );

		$this->assertTrue( $result['ok'] );
		$this->assertSame( array( 'topics' ), array_keys( $payload ) );
		$this->assertCount( 1, $payload['topics'] );
		$this->assertSame(
			array(
				'title' => 'Page Cache & CDN',
				'link'  => 'https://www.boldgrid.com/support/page-cache/',
			),
			$payload['topics'][0]
		);
		$this->assertArrayNotHasKey( 'excerpt', $payload['topics'][0] );
		$this->assertArrayNotHasKey( 'id', $payload['topics'][0] );
		$this->assertPayloadHasNoTransportKeys( $payload );
	}

	/**
	 * Empty bodies are a successful empty topic list.
	 *
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function test_normalize_empty_body() {
		foreach ( array( '', '   ', '[]', 'null' ) as $body ) {
			$result  = Generic_Forums_Api::normalize( $this->http_envelope( $body ) );
			$payload = Generic_Forums_Api::client_payload( $result );

			$this->assertTrue( $result['ok'], 'Failed for body: ' . $body );
			$this->assertSame( array(), $payload['topics'] );
			$this->assertArrayNotHasKey( 'error', $payload );
		}
	}

	/**
	 * Malformed JSON fails without notices and without echoing the body.
	 *
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function test_normalize_malformed_body() {
		$result  = Generic_Forums_Api::normalize( $this->http_envelope( '{not json' ) );
		$payload = Generic_Forums_Api::client_payload( $result );

		$this->assertFalse( $result['ok'] );
		$this->assertSame( Generic_Forums_Api::ERROR_MALFORMED, $payload['error'] );
		$this->assertSame( array(), $payload['topics'] );
		$this->assertSame( array( 'topics', 'error' ), array_keys( $payload ) );
		$this->assertPayloadHasNoTransportKeys( $payload );
		$this->assertStringNotContainsString( '{not json', wp_json_encode( $payload ) );
	}

	/**
	 * HTTP errors and WP_Error are deterministic codes, not transport text.
	 *
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function test_normalize_error_shapes() {
		$http = Generic_Forums_Api::client_payload(
			Generic_Forums_Api::normalize(
				$this->http_envelope( 'upstream timeout at https://secret.example/path', 502 )
			)
		);
		$this->assertSame( Generic_Forums_Api::ERROR_HTTP, $http['error'] );
		$this->assertStringNotContainsString( 'secret.example', wp_json_encode( $http ) );

		$transport = Generic_Forums_Api::client_payload(
			Generic_Forums_Api::normalize(
				new \WP_Error( 'http_request_failed', 'cURL error 28: https://secret.example timed out' )
			)
		);
		$this->assertSame( Generic_Forums_Api::ERROR_TRANSPORT, $transport['error'] );
		$this->assertStringNotContainsString( 'secret.example', wp_json_encode( $transport ) );
		$this->assertStringNotContainsString( 'cURL', wp_json_encode( $transport ) );
	}

	/**
	 * Compatibility wrappers (topics, data, single object) parse the same way.
	 *
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function test_normalize_compatibility_shapes() {
		$topic = array(
			'title' => 'Minify',
			'link'  => 'https://www.boldgrid.com/support/minify/',
		);

		$cases = array(
			wp_json_encode( array( 'topics' => array( $topic ) ) ),
			wp_json_encode( array( 'data' => array( $topic ) ) ),
			wp_json_encode( $topic ),
			wp_json_encode( array( $topic ) ),
		);

		foreach ( $cases as $body ) {
			$payload = Generic_Forums_Api::client_payload(
				Generic_Forums_Api::normalize( $this->http_envelope( $body ) )
			);
			$this->assertCount( 1, $payload['topics'], 'Failed for ' . $body );
			$this->assertSame( 'Minify', $payload['topics'][0]['title'] );
			$this->assertSame( $topic['link'], $payload['topics'][0]['link'] );
		}

		$already = Generic_Forums_Api::client_payload(
			Generic_Forums_Api::normalize( array( 'topics' => array( $topic ) ) )
		);
		$this->assertCount( 1, $already['topics'] );
	}

	/**
	 * Non-http(s) links and markup in titles are dropped / stripped.
	 *
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function test_sanitize_drops_unsafe_links() {
		$payload = Generic_Forums_Api::client_payload(
			Generic_Forums_Api::normalize(
				$this->http_envelope(
					wp_json_encode(
						array(
							array(
								'title' => '<b>Keep</b>',
								'link'  => 'https://www.boldgrid.com/support/ok/',
							),
							array(
								'title' => 'Nope',
								'link'  => 'javascript:alert(1)',
							),
							array(
								'title' => 'Also no',
								'link'  => 'data:text/html,hi',
							),
							array(
								'title' => 'Protocol relative',
								'link'  => '//evil.example/x',
							),
							array(
								'title' => 'Root relative',
								'link'  => '/support/injected',
							),
						)
					)
				)
			)
		);

		$json = wp_json_encode( $payload );
		$this->assertCount( 1, $payload['topics'] );
		$this->assertSame( 'Keep', $payload['topics'][0]['title'] );
		$this->assertSame( 'https://www.boldgrid.com/support/ok/', $payload['topics'][0]['link'] );
		$this->assertStringNotContainsString( 'javascript:', $json );
		$this->assertStringNotContainsString( 'evil.example', $json );
		$this->assertStringNotContainsString( '/support/injected', $json );

		$api_src = file_get_contents( W3TC_DIR . '/Generic_Forums_Api.php' );
		$js_src  = file_get_contents( W3TC_DIR . '/pub/js/forums-api.js' );
		$this->assertIsString( $api_src );
		$this->assertIsString( $js_src );
		$this->assertStringContainsString( '#^https?://#i', $api_src );
		$this->assertStringContainsString( '/^https?:\\/\\//i', $js_src );
	}

	/**
	 * Title caps use UTF-8 character length so multibyte titles are not split.
	 *
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function test_sanitize_truncates_multibyte_titles() {
		$char  = '字';
		$title = str_repeat( $char, Generic_Forums_Api::TITLE_MAX + 8 );
		$payload = Generic_Forums_Api::client_payload(
			Generic_Forums_Api::normalize(
				$this->http_envelope(
					wp_json_encode(
						array(
							array(
								'title' => $title,
								'link'  => 'https://www.boldgrid.com/support/ok/',
							),
						)
					)
				)
			)
		);

		$this->assertCount( 1, $payload['topics'] );
		$this->assertSame(
			Generic_Forums_Api::TITLE_MAX,
			mb_strlen( $payload['topics'][0]['title'], 'UTF-8' )
		);
		$this->assertSame(
			str_repeat( $char, Generic_Forums_Api::TITLE_MAX ),
			$payload['topics'][0]['title']
		);
		$encoded = wp_json_encode( $payload );
		$this->assertIsString( $encoded );
		$this->assertNotFalse( $encoded );

		$api_src = file_get_contents( W3TC_DIR . '/Generic_Forums_Api.php' );
		$this->assertIsString( $api_src );
		$this->assertStringContainsString( "mb_strlen( \$title, 'UTF-8' )", $api_src );
		$this->assertStringContainsString( "mb_substr( \$title, 0, self::TITLE_MAX, 'UTF-8' )", $api_src );
		$this->assertDoesNotMatchRegularExpression(
			'/if\s*\(\s*strlen\s*\(\s*\$title\s*\)/',
			$api_src
		);
	}

	/**
	 * request() uses the allowlisted host and returns only the client shape.
	 *
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function test_request_strips_http_envelope() {
		$this->mock_http(
			$this->http_envelope(
				wp_json_encode(
					array(
						array(
							'title' => 'CDN',
							'link'  => 'https://www.boldgrid.com/support/cdn/',
						),
					)
				),
				200,
				array(
					'set-cookie' => 'session=leaked',
					'server'     => 'secret-upstream',
				)
			)
		);

		$payload = Generic_Forums_Api::client_payload(
			Generic_Forums_Api::request( 'tab_cdn' )
		);

		$this->assertSame(
			array( W3TC_BOLDGRID_FORUM_API . 'tab_cdn' ),
			$this->http_urls
		);
		$this->assertSame( array( 'topics' ), array_keys( $payload ) );
		$this->assertSame( 'CDN', $payload['topics'][0]['title'] );
		$this->assertPayloadHasNoTransportKeys( $payload );
		$json = wp_json_encode( $payload );
		$this->assertStringNotContainsString( 'session=leaked', $json );
		$this->assertStringNotContainsString( 'secret-upstream', $json );
		$this->assertStringNotContainsString( 'cookies', $json );
		$this->assertStringNotContainsString( 'http_response', $json );
	}

	/**
	 * AJAX handler emits the parsed payload, not the remote envelope.
	 *
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function test_ajax_handler_emits_parsed_body() {
		$admin = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$_POST['tabId']       = 'tab_page_cache';
		$_POST['_wpnonce']    = wp_create_nonce( 'w3tc_forums_api' );
		$_REQUEST['tabId']    = $_POST['tabId'];
		$_REQUEST['_wpnonce'] = $_POST['_wpnonce'];

		$this->mock_http(
			$this->http_envelope(
				wp_json_encode(
					array(
						array(
							'title' => 'Page Cache',
							'link'  => 'https://www.boldgrid.com/support/page-cache/',
						),
					)
				),
				200,
				array( 'x-powered-by' => 'secret-stack' )
			)
		);

		$out = $this->capture_ajax(
			static function () {
				( new Generic_Plugin_Admin() )->wp_ajax_w3tc_forums_api();
			}
		);

		$decoded = json_decode( $out, true );
		$this->assertIsArray( $decoded );
		$this->assertSame( array( 'topics' ), array_keys( $decoded ) );
		$this->assertSame( 'Page Cache', $decoded['topics'][0]['title'] );
		$this->assertStringNotContainsString( 'x-powered-by', $out );
		$this->assertStringNotContainsString( 'secret-stack', $out );
		$this->assertStringNotContainsString( '"headers"', $out );
		$this->assertStringNotContainsString( '"body"', $out );
		$this->assertStringNotContainsString( '"cookies"', $out );
	}

	/**
	 * Invalid tabId is rejected with 400 and does not hit the network.
	 *
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function test_ajax_handler_rejects_invalid_tab_id() {
		$admin = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$_POST['tabId']       = 'tab_page_cache&injected=1';
		$_POST['_wpnonce']    = wp_create_nonce( 'w3tc_forums_api' );
		$_REQUEST['tabId']    = $_POST['tabId'];
		$_REQUEST['_wpnonce'] = $_POST['_wpnonce'];

		$this->mock_http( $this->http_envelope( '[]' ) );

		$out = $this->capture_ajax(
			static function () {
				( new Generic_Plugin_Admin() )->wp_ajax_w3tc_forums_api();
			}
		);

		$this->assertSame( array(), $this->http_urls );
		$decoded = json_decode( $out, true );
		$this->assertIsArray( $decoded );
		$this->assertFalse( $decoded['success'] );
		$this->assertSame( 'invalid tabId', $decoded['data'] );
	}

	/**
	 * Callers without manage_options receive 403 and no outbound fetch.
	 *
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function test_ajax_handler_rejects_subscriber() {
		$subscriber = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );

		$_POST['tabId']       = 'tab_page_cache';
		$_POST['_wpnonce']    = wp_create_nonce( 'w3tc_forums_api' );
		$_REQUEST['tabId']    = $_POST['tabId'];
		$_REQUEST['_wpnonce'] = $_POST['_wpnonce'];

		$this->mock_http( $this->http_envelope( '[]' ) );

		$out = $this->capture_ajax(
			static function () {
				( new Generic_Plugin_Admin() )->wp_ajax_w3tc_forums_api();
			}
		);

		$this->assertSame( array(), $this->http_urls );
		$decoded = json_decode( $out, true );
		$this->assertIsArray( $decoded );
		$this->assertFalse( $decoded['success'] );
		$this->assertSame( 'no permissions', $decoded['data'] );
	}

	/**
	 * Handler and options.js no longer echo / parse the raw envelope.
	 *
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function test_call_sites_do_not_echo_raw_http() {
		$admin_src   = file_get_contents( W3TC_DIR . '/Generic_Plugin_Admin.php' );
		$options_src = file_get_contents( W3TC_DIR . '/pub/js/options.js' );

		$this->assertIsString( $admin_src );
		$this->assertIsString( $options_src );
		$this->assertStringContainsString( 'Generic_Forums_Api::send', $admin_src );
		$this->assertStringNotContainsString( 'wp_send_json( $posts )', $admin_src );
		$this->assertStringNotContainsString( 'JSON.parse(data.body)', $options_src );
		$this->assertStringContainsString( 'W3tcForumsApi.topicsFromResponse', $options_src );
	}

	/**
	 * Node harness covers client compatibility and leak guards.
	 *
	 * @since 2.10.6
	 *
	 * @return void
	 */
	public function test_js_gates_harness() {
		$node = trim( (string) shell_exec( 'command -v node' ) );
		if ( '' === $node ) {
			$this->markTestSkipped( 'node is not available' );
		}

		$script = W3TC_DIR . '/tests/admin/forums-api-gates.js';
		$cmd    = escapeshellarg( $node ) . ' ' . escapeshellarg( $script ) . ' 2>&1';
		$output = array();
		$code   = 0;
		exec( $cmd, $output, $code );

		$this->assertSame( 0, $code, implode( "\n", $output ) );
		$this->assertStringContainsString( 'forums-api-gates: ok', implode( "\n", $output ) );
	}

	/**
	 * Run an AJAX handler and return buffered JSON.
	 *
	 * @since 2.10.6
	 *
	 * @param callable $callback Handler invocation.
	 *
	 * @return string
	 */
	private function capture_ajax( callable $callback ) {
		\ob_start();
		try {
			$callback();
			\ob_end_clean();
			$this->fail( 'Expected WPDieException from wp_send_json.' );
		} catch ( \WPDieException $e ) {
			return (string) \ob_get_clean();
		}

		return '';
	}

	/**
	 * Build a wp_remote_get-shaped envelope with extra transport fields.
	 *
	 * @since 2.10.6
	 *
	 * @param string $body    Response body.
	 * @param int    $code    HTTP status.
	 * @param array  $headers Optional headers.
	 *
	 * @return array
	 */
	private function http_envelope( $body, $code = 200, array $headers = array() ) {
		return array(
			'headers'      => $headers,
			'body'         => $body,
			'response'     => array(
				'code'    => $code,
				'message' => 200 === $code ? 'OK' : 'Error',
			),
			'cookies'      => array(
				array(
					'name'  => 'sid',
					'value' => 'cookie-secret',
				),
			),
			'filename'     => null,
			'http_response' => (object) array( 'data' => 'internal' ),
		);
	}

	/**
	 * Intercept outbound HTTP.
	 *
	 * @since 2.10.6
	 *
	 * @param array $response Mock response.
	 *
	 * @return void
	 */
	private function mock_http( array $response ) {
		$urls = &$this->http_urls;
		add_filter(
			'pre_http_request',
			static function ( $preempt, $args, $url ) use ( &$urls, $response ) {
				unset( $preempt, $args );
				$urls[] = $url;
				return $response;
			},
			10,
			3
		);
	}

	/**
	 * Client payload must not carry remote transport fields.
	 *
	 * @since 2.10.6
	 *
	 * @param array $payload Client payload.
	 *
	 * @return void
	 */
	private function assertPayloadHasNoTransportKeys( array $payload ) {
		foreach ( array( 'headers', 'body', 'cookies', 'filename', 'http_response', 'response', 'errors', 'error_data' ) as $key ) {
			$this->assertArrayNotHasKey( $key, $payload );
		}

		$allowed = Generic_Forums_Api::allowed_client_keys();
		foreach ( array_keys( $payload ) as $key ) {
			$this->assertContains( $key, $allowed );
		}
	}
}
