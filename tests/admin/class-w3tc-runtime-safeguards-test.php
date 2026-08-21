<?php
/**
 * File: class-w3tc-runtime-safeguards-test.php
 *
 * Regressions for ENG7-4295 Phase 4 runtime safeguards.
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      X.X.X
 */

declare( strict_types = 1 );

use W3TC\Cache_File_Generic;
use W3TC\Cdn_BunnyCdn_Page;
use W3TC\ConfigKeysSchema;
use W3TC\Dispatcher;
use W3TC\Generic_AdminActions_Default;
use W3TC\Generic_Plugin_AdminNotices;
use W3TC\Minify_MinifiedFileRequestHandler;
use W3TC\Util_Nonce;
use W3TC\Util_RateLimit;

/**
 * Class: W3tc_Runtime_Safeguards_Test
 *
 * @since X.X.X
 */
class W3tc_Runtime_Safeguards_Test extends WP_UnitTestCase {

	/**
	 * Backup of $_REQUEST around tests.
	 *
	 * @var array
	 */
	private $saved_request;

	/**
	 * Backup of $_GET around tests.
	 *
	 * @var array
	 */
	private $saved_get;

	/**
	 * Backup of $_POST around tests.
	 *
	 * @var array
	 */
	private $saved_post;

	/**
	 * Backup of REMOTE_ADDR around tests.
	 *
	 * @var string|null
	 */
	private $saved_remote_addr;

	/**
	 * Backup of dismissed notices around tests.
	 *
	 * @var mixed
	 */
	private $saved_dismissed_notices;

	/**
	 * Custom AJAX die handler that throws WPDieException instead of exiting.
	 *
	 * @since X.X.X
	 *
	 * @return callable
	 */
	public static function ajax_die_handler() {
		return static function ( $message = '' ) {
			throw new \WPDieException( (string) $message );
		};
	}

	/**
	 * Set up.
	 *
	 * @since X.X.X
	 */
	public function set_up() {
		parent::set_up();
		$this->saved_request           = $_REQUEST;
		$this->saved_get               = $_GET;
		$this->saved_post              = $_POST;
		$this->saved_remote_addr       = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : null;
		$this->saved_dismissed_notices = \get_option( 'w3tc_dismissed_notices', null );
		\add_filter( 'wp_doing_ajax', '__return_true' );
		\add_filter( 'wp_die_ajax_handler', array( __CLASS__, 'ajax_die_handler' ) );
	}

	/**
	 * Tear down.
	 *
	 * @since X.X.X
	 */
	public function tear_down() {
		\remove_filter( 'wp_die_ajax_handler', array( __CLASS__, 'ajax_die_handler' ) );
		\remove_filter( 'wp_doing_ajax', '__return_true' );
		$_REQUEST = $this->saved_request;
		$_GET     = $this->saved_get;
		$_POST    = $this->saved_post;
		if ( null === $this->saved_remote_addr ) {
			unset( $_SERVER['REMOTE_ADDR'] );
		} else {
			$_SERVER['REMOTE_ADDR'] = $this->saved_remote_addr;
		}
		if ( null === $this->saved_dismissed_notices ) {
			\delete_option( 'w3tc_dismissed_notices' );
		} else {
			\update_option( 'w3tc_dismissed_notices', $this->saved_dismissed_notices, false );
		}
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Known note IDs are admitted; arbitrary IDs are refused.
	 *
	 * @since X.X.X
	 */
	public function test_is_known_note_id_allowlist() {
		$this->assertTrue( ConfigKeysSchema::is_known_note_id( 'cloudflare_plugin' ) );
		$this->assertTrue( ConfigKeysSchema::is_known_note_id( 'need_empty_fragmentcache' ) );
		$this->assertFalse( ConfigKeysSchema::is_known_note_id( 'redteam_proof_123' ) );
		$this->assertFalse( ConfigKeysSchema::is_known_note_id( '../etc/passwd' ) );
		$this->assertFalse( ConfigKeysSchema::is_known_note_id( '' ) );
		$this->assertFalse( ConfigKeysSchema::is_known_note_id( 'BadCase' ) );
	}

	/**
	 * Extensions can append note IDs via filter.
	 *
	 * @since X.X.X
	 */
	public function test_is_known_note_id_filter_extension() {
		$cb = static function ( $allowed ) {
			$allowed[] = 'extension_custom_note';
			return $allowed;
		};
		add_filter( 'w3tc_known_note_ids', $cb );
		$this->assertTrue( ConfigKeysSchema::is_known_note_id( 'extension_custom_note' ) );
		remove_filter( 'w3tc_known_note_ids', $cb );
		$this->assertFalse( ConfigKeysSchema::is_known_note_id( 'extension_custom_note' ) );
	}

	/**
	 * Rate limiter admits until the budget is exhausted.
	 *
	 * @since X.X.X
	 */
	public function test_rate_limit_budget() {
		$bucket = 'unit_test_' . wp_generate_password( 8, false );
		$this->assertTrue( Util_RateLimit::allow( $bucket, 2, 60, 'u1' ) );
		$this->assertTrue( Util_RateLimit::allow( $bucket, 2, 60, 'u1' ) );
		$this->assertFalse( Util_RateLimit::allow( $bucket, 2, 60, 'u1' ) );
		// Distinct discriminator keeps an independent budget.
		$this->assertTrue( Util_RateLimit::allow( $bucket, 2, 60, 'u2' ) );
	}

	/**
	 * Cache_File_Generic::escape_header_value strips CR/LF/NUL, keeps quotes
	 * and Link angle brackets required for HTTP/2 preload Header lines.
	 *
	 * @since X.X.X
	 */
	public function test_escape_header_value_strips_controls_keeps_link_brackets() {
		$ref    = new \ReflectionClass( Cache_File_Generic::class );
		$cache  = $ref->newInstanceWithoutConstructor();
		$method = $ref->getMethod( 'escape_header_value' );
		$method->setAccessible( true );

		$this->assertSame(
			'ok',
			$method->invoke( $cache, "ok\x00\r\n" ),
			'CR/LF/NUL must be stripped from Header values.'
		);
		$this->assertSame(
			'<https://example.test/a.js>; rel=preload; as="script"',
			$method->invoke(
				$cache,
				'<https://example.test/a.js>; rel=preload; as="script"'
			),
			'Link angle brackets and double quotes must survive escape_header_value().'
		);
		$this->assertSame(
			"it\\'s",
			$method->invoke( $cache, "it's" ),
			'Single quotes must be escaped for the Apache Header add context.'
		);
		$this->assertSame(
			'',
			$method->invoke( $cache, array( 'not-a-string' ) ),
			'Non-string input must return empty.'
		);
	}

	/**
	 * Unauthenticated minify probe path returns a 429-shaped quiet payload
	 * once the per-IP bad-request budget is exhausted.
	 *
	 * @since X.X.X
	 */
	public function test_minify_finish_with_error_rate_limits_probe_path() {
		$ip                         = '203.0.113.50';
		$_SERVER['REMOTE_ADDR']     = $ip;
		$transient                  = 'w3tc_rl_minify_bad_request_' . \substr( \md5( $ip ), 0, 12 );
		\set_transient( $transient, 30, 60 );

		$handler = new Minify_MinifiedFileRequestHandler();
		$result  = $handler->finish_with_error( 'Bad file param format', true, false );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'content', $result );
		$this->assertStringContainsString( 'Too many minify requests', $result['content'] );

		\delete_transient( $transient );
	}

	/**
	 * Minify requests without a client address share a fallback budget.
	 *
	 * @since X.X.X
	 */
	public function test_minify_finish_with_error_rate_limits_missing_remote_addr() {
		unset( $_SERVER['REMOTE_ADDR'] );
		$transient = 'w3tc_rl_minify_bad_request_' . \substr( \md5( 'unknown' ), 0, 12 );
		\set_transient( $transient, 30, 60 );

		$handler = new Minify_MinifiedFileRequestHandler();
		$result  = $handler->finish_with_error( 'Bad file param format', true, false );

		$this->assertIsArray( $result );
		$this->assertStringContainsString( 'Too many minify requests', $result['content'] );

		\delete_transient( $transient );
	}

	/**
	 * Minify probe path still returns the normal quiet error body while the
	 * budget has remaining capacity.
	 *
	 * @since X.X.X
	 */
	public function test_minify_finish_with_error_allows_within_budget() {
		$ip                     = '203.0.113.51';
		$_SERVER['REMOTE_ADDR'] = $ip;
		$transient              = 'w3tc_rl_minify_bad_request_' . \substr( \md5( $ip ), 0, 12 );
		\delete_transient( $transient );

		$handler = new Minify_MinifiedFileRequestHandler();
		$result  = $handler->finish_with_error( 'Bad file param format', true, false );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'content', $result );
		$this->assertStringNotContainsString( 'Too many minify requests', $result['content'] );
		$this->assertStringContainsString( 'W3TC Minify Error', $result['content'] );

		\delete_transient( $transient );
	}

	/**
	 * BunnyCDN purge AJAX returns 429 JSON when the per-user budget is spent.
	 *
	 * @since X.X.X
	 */
	public function test_bunnycdn_purge_url_rate_limited() {
		$admin = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$user_id   = (string) $admin;
		$transient = 'w3tc_rl_bunnycdn_purge_url_' . \substr( \md5( $user_id ), 0, 12 );
		\set_transient( $transient, 60, 60 );

		$_GET['url'] = 'https://example.com/asset.js';

		$page = new Cdn_BunnyCdn_Page();

		\ob_start();
		try {
			$page->w3tc_ajax_cdn_bunnycdn_purge_url();
			\ob_end_clean();
			$this->fail( 'Expected WPDieException from rate-limited BunnyCDN purge.' );
		} catch ( \WPDieException $e ) {
			$out = \ob_get_clean();
			$this->assertStringContainsString( 'Rate limit exceeded', $out );
		}

		\delete_transient( $transient );
	}

	/**
	 * Invalid BunnyCDN purge URLs are refused without consuming the budget.
	 *
	 * @since X.X.X
	 */
	public function test_bunnycdn_purge_url_invalid_does_not_consume_budget() {
		$admin = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$user_id   = (string) $admin;
		$transient = 'w3tc_rl_bunnycdn_purge_url_' . \substr( \md5( $user_id ), 0, 12 );
		\delete_transient( $transient );

		$_GET['url'] = 'not-a-url';

		$page = new Cdn_BunnyCdn_Page();

		\ob_start();
		try {
			$page->w3tc_ajax_cdn_bunnycdn_purge_url();
			\ob_end_clean();
			$this->fail( 'Expected WPDieException from invalid BunnyCDN purge URL.' );
		} catch ( \WPDieException $e ) {
			$out = \ob_get_clean();
			$this->assertStringContainsString( 'Invalid URL', $out );
		}

		$this->assertFalse(
			(bool) \get_transient( $transient ),
			'Invalid URL must not increment the BunnyCDN purge rate-limit bucket.'
		);
	}

	/**
	 * Notice AJAX rejects a missing / wrong nonce before the capability check.
	 *
	 * @since X.X.X
	 */
	public function test_get_notices_rejects_wrong_nonce() {
		$admin = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$_REQUEST['_wpnonce'] = \wp_create_nonce( 'w3tc_ajax_unrelated_action' );

		$plugin = new Generic_Plugin_AdminNotices();

		\ob_start();
		try {
			$plugin->w3tc_ajax_get_notices();
			\ob_end_clean();
			$this->fail( 'Expected WPDieException from nonce failure.' );
		} catch ( \WPDieException $e ) {
			$out = \ob_get_clean();
			$this->assertSame( '-1', $e->getMessage() );
			$this->assertStringNotContainsString( 'noticeData', $out );
		}
	}

	/**
	 * Notice retrieval accepts the nonce shape used by admin JavaScript.
	 *
	 * @since X.X.X
	 */
	public function test_get_notices_accepts_admin_with_valid_nonce() {
		$admin = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$_REQUEST['_wpnonce'] = Util_Nonce::create_ajax( 'get_notices' );

		$plugin = new Generic_Plugin_AdminNotices();

		\ob_start();
		try {
			$plugin->w3tc_ajax_get_notices();
			\ob_end_clean();
			$this->fail( 'Expected WPDieException after the JSON response.' );
		} catch ( \WPDieException $e ) {
			$response = \json_decode( \ob_get_clean(), true );
			$this->assertTrue( $response['success'] );
			$this->assertArrayHasKey( 'noticeData', $response['data'] );
		}
	}

	/**
	 * Notice AJAX rejects a subscriber even with a valid per-action nonce.
	 *
	 * @since X.X.X
	 */
	public function test_dismiss_notice_rejects_subscriber_with_valid_nonce() {
		$subscriber = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );

		$_REQUEST['_wpnonce']  = Util_Nonce::create_ajax( 'dismiss_notice' );
		$_REQUEST['notice_id'] = '1';

		$plugin = new Generic_Plugin_AdminNotices();

		\ob_start();
		try {
			$plugin->w3tc_ajax_dismiss_notice();
			\ob_end_clean();
			$this->fail( 'Expected WPDieException from capability failure.' );
		} catch ( \WPDieException $e ) {
			$out = \ob_get_clean();
			$this->assertStringContainsString( 'Insufficient permissions', $out );
		}
	}

	/**
	 * Notice dismissal accepts the nonce shape used by admin JavaScript.
	 *
	 * @since X.X.X
	 */
	public function test_dismiss_notice_accepts_admin_with_valid_nonce() {
		$admin = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$_REQUEST['_wpnonce']  = Util_Nonce::create_ajax( 'dismiss_notice' );
		$_POST['notice_id']    = '987654';

		$plugin = new Generic_Plugin_AdminNotices();

		\ob_start();
		try {
			$plugin->w3tc_ajax_dismiss_notice();
			\ob_end_clean();
			$this->fail( 'Expected WPDieException after the JSON response.' );
		} catch ( \WPDieException $e ) {
			$response = \json_decode( \ob_get_clean(), true );
			$this->assertTrue( $response['success'] );
			$this->assertContains( 987654, \get_option( 'w3tc_dismissed_notices', array() ) );
		}
	}

	/**
	 * Interrupt Util_Environment::redirect() so handler tests can continue.
	 *
	 * @since X.X.X
	 *
	 * @return void
	 * @throws \RuntimeException Always.
	 */
	public static function throw_on_redirect() {
		throw new \RuntimeException( 'w3tc_redirect' );
	}

	/**
	 * Unknown note IDs are refused by w3tc_default_hide_note without writing.
	 *
	 * @since X.X.X
	 */
	public function test_hide_note_rejects_unknown_note_id() {
		$admin = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$_GET['page'] = 'w3tc_dashboard';
		$_GET['note'] = 'redteam_invented_note';

		\add_action( 'w3tc_redirect', array( __CLASS__, 'throw_on_redirect' ) );

		$admin_actions = new Generic_AdminActions_Default();
		try {
			$admin_actions->w3tc_default_hide_note();
			$this->fail( 'Expected redirect after refusing an unknown note id.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'w3tc_redirect', $e->getMessage() );
		} finally {
			\remove_action( 'w3tc_redirect', array( __CLASS__, 'throw_on_redirect' ) );
		}

		$config = Dispatcher::config();
		$this->assertNull(
			$config->get( 'notes.redteam_invented_note' ),
			'Unknown note ids must not create notes.* config keys.'
		);
	}

	/**
	 * Known note IDs are dismissed to false by w3tc_default_hide_note.
	 *
	 * @since X.X.X
	 */
	public function test_hide_note_accepts_known_note_id() {
		$admin = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$config = Dispatcher::config();
		$config->set( 'notes.cloudflare_plugin', true );
		$config->save();

		$_GET['page'] = 'w3tc_dashboard';
		$_GET['note'] = 'cloudflare_plugin';

		\add_action( 'w3tc_redirect', array( __CLASS__, 'throw_on_redirect' ) );

		$admin_actions = new Generic_AdminActions_Default();
		try {
			$admin_actions->w3tc_default_hide_note();
			$this->fail( 'Expected redirect after dismissing a known note id.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'w3tc_redirect', $e->getMessage() );
		} finally {
			\remove_action( 'w3tc_redirect', array( __CLASS__, 'throw_on_redirect' ) );
		}

		$config = Dispatcher::config();
		$this->assertFalse( $config->get_boolean( 'notes.cloudflare_plugin' ) );
	}
}
