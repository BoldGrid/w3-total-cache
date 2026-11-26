<?php
/**
 * File: class-w3tc-public-endpoints-test.php
 *
 * Regressions for the missing-auth-public-endpoints remediation pass.
 *
 * pub/sns.php is a pre-WordPress entrypoint — we can't legitimately invoke its
 * body inside the WP test bootstrap (the file gates on REQUEST_METHOD and the
 * SNS signing-cert chain). What we *can* do is assert the static structure
 * that closes the historical vulnerabilities:
 *
 *   - The method gate runs before the WordPress load (HTTP_HOST cannot be
 *     overridden from request input before `wp-load.php`).
 *   - The body cap is enforced via `stream_get_contents( ..., $max + 1 )`,
 *     not just a `Content-Length` check (chunked / proxied requests can lie
 *     about Content-Length).
 *   - The topic is compared with the loaded config (hash_equals) before
 *     any certificate request, and WordPress loads first so certificate
 *     transients work in production.
 *   - `$_SERVER['HTTP_HOST']` and `$w3_current_blog_id` are NEVER written
 *     from request input in this file.
 *   - `pub/.htaccess` ships a default-deny rule for `*.php` with an explicit
 *     allowlist for `sns.php`, so any new file dropped into `pub/` is
 *     unreachable on Apache / LiteSpeed without an explicit allowlist entry.
 *
 * Static-structure assertions are the right shape for a public-entrypoint
 * regression: the kill chain is "what runs before the auth check" — line-
 * ordering, not runtime behaviour — and a structural test catches a re-ordered
 * future edit that re-introduces the bug.
 *
 * @package    W3TC
 * @subpackage W3TC/tests/admin
 * @since      2.10.0
 */

declare( strict_types = 1 );

/**
 * Class: W3tc_Public_Endpoints_Test
 *
 * @since 2.10.0
 */
class W3tc_Public_Endpoints_Test extends WP_UnitTestCase {

	/**
	 * Absolute path to pub/sns.php in the plugin checkout.
	 *
	 * @since 2.10.0
	 *
	 * @var string
	 */
	private static $sns_path;

	/**
	 * Absolute path to pub/.htaccess in the plugin checkout.
	 *
	 * @since 2.10.0
	 *
	 * @var string
	 */
	private static $htaccess_path;

	/**
	 * Verbatim contents of pub/sns.php, loaded once.
	 *
	 * @since 2.10.0
	 *
	 * @var string
	 */
	private static $sns_source;

	/**
	 * Verbatim contents of pub/.htaccess, loaded once.
	 *
	 * @since 2.10.0
	 *
	 * @var string
	 */
	private static $htaccess_source;

	/**
	 * Verbatim contents of Enterprise_SnsServer.php, loaded once.
	 *
	 * @since 2.11.1
	 *
	 * @var string
	 */
	private static $sns_server_source;

	/**
	 * Signing key and certificate for SNS signature tests.
	 *
	 * @since 2.11.1
	 *
	 * @var array|null
	 */
	private static $sns_signing_fixture;

	/**
	 * Cache the file contents once for the whole suite.
	 *
	 * @since 2.10.0
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$sns_path        = W3TC_DIR . '/pub/sns.php';
		self::$htaccess_path   = W3TC_DIR . '/pub/.htaccess';
		self::$sns_source      = \file_exists( self::$sns_path ) ? \file_get_contents( self::$sns_path ) : '';
		self::$htaccess_source = \file_exists( self::$htaccess_path ) ? \file_get_contents( self::$htaccess_path ) : '';

		$sns_server_path        = W3TC_DIR . '/Enterprise_SnsServer.php';
		self::$sns_server_source = \file_exists( $sns_server_path ) ? \file_get_contents( $sns_server_path ) : '';
	}

	/**
	 * Compute the offset of a literal substring in pub/sns.php so we can
	 * compare line-ordering between guards and the wp-load require.
	 *
	 * Fails the calling test on a non-match or on a needle that occurs more
	 * than once — the structural assertions below are meaningless if the anchor
	 * disappears in a refactor, or if an earlier unrelated match (such as a
	 * function definition) is returned instead of the intended one.
	 *
	 * @since 2.10.0
	 *
	 * @param string $needle Literal substring to locate.
	 *
	 * @return int Byte-offset of the only occurrence.
	 */
	private function offset_of( $needle ) {
		$this->assertLessThanOrEqual(
			1,
			\substr_count( self::$sns_source, $needle ),
			'Expected the marker "' . $needle . '" to occur once in pub/sns.php; pick an anchor that is unique.'
		);

		$offset = \strpos( self::$sns_source, $needle );
		$this->assertNotFalse(
			$offset,
			'Expected pub/sns.php to contain the marker "' . $needle . '" — refactor moved or removed the anchor.'
		);
		return (int) $offset;
	}

	/**
	 * pub/sns.php and pub/.htaccess both exist in the plugin checkout.
	 *
	 * @since 2.10.0
	 */
	public function test_pub_files_exist() {
		$this->assertFileExists( self::$sns_path, 'pub/sns.php must ship with the plugin.' );
		$this->assertFileExists( self::$htaccess_path, 'pub/.htaccess must ship with the plugin so dropped-in PHP under pub/ is unreachable on Apache.' );
	}

	/**
	 * The SNS entrypoint rejects non-POST requests before any further work.
	 *
	 * @since 2.10.0
	 */
	public function test_sns_rejects_non_post_method() {
		$this->assertStringContainsString(
			"'POST' !== strtoupper",
			self::$sns_source,
			'pub/sns.php must compare REQUEST_METHOD to POST.'
		);
		$this->assertStringContainsString(
			'Method Not Allowed',
			self::$sns_source,
			'pub/sns.php must emit a 405 on non-POST.'
		);
	}

	/**
	 * The SNS entrypoint requires the `x-amz-sns-message-type` header so generic
	 * POST scanners are dropped without paying for body reads or autoloading.
	 *
	 * @since 2.10.0
	 */
	public function test_sns_requires_amz_message_type_header() {
		$this->assertStringContainsString(
			'HTTP_X_AMZ_SNS_MESSAGE_TYPE',
			self::$sns_source,
			'pub/sns.php must require the AWS SNS message-type header before doing further work.'
		);
	}

	/**
	 * Body size is enforced by a hard byte cap on `stream_get_contents`,
	 * not just a `Content-Length` check (chunked / proxied requests can lie).
	 *
	 * @since 2.10.0
	 */
	public function test_sns_enforces_body_byte_cap_via_stream_read() {
		$this->assertStringContainsString( 'stream_get_contents', self::$sns_source );
		$this->assertStringContainsString( 'max_body_bytes + 1', self::$sns_source );
		$this->assertStringContainsString( '262144', self::$sns_source );
		$this->assertStringContainsString( 'Payload too large', self::$sns_source );
	}

	/**
	 * TopicArn, Type, SignatureVersion and the signing-certificate URL are checked before WordPress loads;
	 * the configured topic and signature are checked after bootstrap so transients can cache certificates.
	 *
	 * @since 2.11.1
	 */
	public function test_sns_compares_topic_before_certificate_validation() {
		$topic_arn_check   = $this->offset_of( "! isset( \$w3tc_sns_decoded['TopicArn'] )" );
		$type_check        = $this->offset_of( 'in_array( $w3tc_sns_type,' );
		$version_check     = $this->offset_of( 'in_array( $w3tc_sns_signature_version,' );
		$cert_url_check    = $this->offset_of( "! w3tc_sns_signing_cert_url_allowed( isset( \$w3tc_sns_decoded['SigningCertURL'] )" );
		$wp_load_call      = $this->offset_of( "require_once \$w3tc_sns_wp_load" );
		$config_topic_read = $this->offset_of( "Dispatcher::config()->get_string( 'cluster.messagebus.sns.topic_arn' )" );
		$validator_call    = $this->offset_of( '->validate_message( $w3tc_sns_message->toArray(), $w3tc_sns_topic_arn )' );

		$this->assertLessThan(
			$wp_load_call,
			$topic_arn_check,
			'A message without a TopicArn must be rejected before WordPress loads.'
		);
		$this->assertLessThan(
			$wp_load_call,
			$type_check,
			'The message Type check must run before WordPress loads.'
		);
		$this->assertLessThan(
			$wp_load_call,
			$version_check,
			'The SignatureVersion check must run before WordPress loads.'
		);
		$this->assertLessThan(
			$wp_load_call,
			$cert_url_check,
			'The signing-certificate URL check call must run before WordPress loads.'
		);
		$this->assertLessThan(
			$config_topic_read,
			$wp_load_call,
			'The loaded config must supply the topic so file and database storage are both honored.'
		);
		$this->assertLessThan(
			$validator_call,
			$config_topic_read,
			'Sns_MessageValidator::validate_message() must receive the topic from the loaded config.'
		);
		$this->assertStringNotContainsString(
			'w3tc-config',
			self::$sns_source,
			'pub/sns.php must not read a config file directly; a retained file may not be the active config.'
		);
	}

	/**
	 * The file-level and step comments in pub/sns.php describe the current
	 * order of checks, not the earlier one.
	 *
	 * @since 2.11.1
	 */
	public function test_sns_comments_do_not_describe_old_check_order() {
		$this->assertStringNotContainsString(
			'validates first, loads WordPress second',
			self::$sns_source,
			'The order-of-checks comment must not describe the earlier order.'
		);
		$this->assertStringNotContainsString(
			'WITHOUT bootstrapping WordPress',
			self::$sns_source,
			'Only the SNS Message is built before bootstrap; the validator runs after it.'
		);
	}

	/**
	 * Enterprise_SnsServer::process_message() rejects another topic without a
	 * certificate request and reuses the cached certificate for a repeat.
	 *
	 * @since 2.11.1
	 */
	public function test_enterprise_sns_server_checks_topic_before_certificate_request() {
		$configured_topic = 'arn:aws:sns:us-east-1:123456789012:configured';
		$certificate_url  = 'https://sns.us-east-1.amazonaws.com/SimpleNotificationService-server-test.pem';
		$transient_key    = 'w3tc_sns_cert_' . \hash( 'sha256', $certificate_url );
		$config           = \W3TC\Dispatcher::config();
		$original_topic   = $config->get_string( 'cluster.messagebus.sns.topic_arn' );
		$requests         = 0;
		$received         = \did_action( 'w3tc_messagebus_message_received' );

		$certificate      = $this->sns_signing_fixture()['certificate'];

		$intercept = static function ( $pre, $args, $url ) use ( &$requests, $certificate_url, $certificate ) {
			if ( $certificate_url !== $url ) {
				return $pre;
			}

			++$requests;

			return array(
				'headers'  => array(),
				'body'     => $certificate,
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		};

		\delete_transient( $transient_key );
		\add_filter( 'pre_http_request', $intercept, 10, 3 );
		$config->set( 'cluster.messagebus.sns.topic_arn', $configured_topic );

		try {
			$server = new \W3TC\Enterprise_SnsServer();

			$server->process_message( $this->sns_message( $certificate_url, 'arn:aws:sns:us-east-1:123456789012:other' ) );
			$this->assertSame( 0, $requests, 'A message from another topic must not request a certificate.' );

			$server->process_message( $this->sns_message( $certificate_url, $configured_topic ) );
			$server->process_message( $this->sns_message( $certificate_url, $configured_topic ) );
			$this->assertSame( 1, $requests, 'A repeated certificate URL must use the cached transient.' );

			$this->assertSame( $received, \did_action( 'w3tc_messagebus_message_received' ), 'A message that fails signature validation must not be processed.' );
		} finally {
			$config->set( 'cluster.messagebus.sns.topic_arn', $original_topic );
			\remove_filter( 'pre_http_request', $intercept, 10 );
			\delete_transient( $transient_key );
		}
	}

	/**
	 * Unvalidated SNS processing reuses Sns_MessageValidator and hash_equals.
	 *
	 * @since 2.11.1
	 */
	public function test_enterprise_sns_server_unvalidated_path_uses_w3tc_validator() {
		$this->assertNotSame( '', self::$sns_server_source, 'Enterprise_SnsServer.php must exist in the plugin checkout.' );

		$this->assertStringContainsString(
			'new Sns_MessageValidator()',
			self::$sns_server_source,
			'process_message() must validate via W3TC\\Sns_MessageValidator when the public endpoint did not pre-validate.'
		);
		$this->assertStringContainsString(
			'->validate_message(',
			self::$sns_server_source,
			'process_message() must call validate_message() for the unvalidated path.'
		);
		$this->assertStringNotContainsString(
			'new \\Aws\\Sns\\MessageValidator()',
			self::$sns_server_source,
			'process_message() must not instantiate the raw AWS MessageValidator directly.'
		);
		$this->assertStringContainsString(
			'hash_equals( $topic_arn, $w3tc_message',
			self::$sns_server_source,
			'process_message() must compare topic ARNs with hash_equals.'
		);
	}

	/**
	 * The autoloader is required from the plugin's own vendor/ directory —
	 * not from a path joined with request input — before the SNS message is
	 * constructed.
	 *
	 * @since 2.10.0
	 */
	public function test_sns_loads_vendor_autoload_before_validator() {
		$autoload_require = $this->offset_of( "require_once \$w3tc_sns_autoload" );
		$message_new      = $this->offset_of( 'new \\Aws\\Sns\\Message' );

		$this->assertLessThan(
			$message_new,
			$autoload_require,
			'Composer autoloader must be required before \\Aws\\Sns\\Message is instantiated.'
		);
		$this->assertStringContainsString(
			"__DIR__ . '/../vendor/autoload.php'",
			self::$sns_source,
			'Autoload path must be the plugin-relative vendor/autoload.php — never derived from request input.'
		);
	}

	/**
	 * Certificate retrieval is skipped for another topic, and a signed message
	 * validates on both the initial fetch and the cached call.
	 *
	 * @since 2.11.1
	 */
	public function test_sns_certificate_fetch_requires_matching_topic_and_uses_transient_cache() {
		$configured_topic = 'arn:aws:sns:us-east-1:123456789012:configured';
		$certificate_url  = 'https://sns.us-east-1.amazonaws.com/SimpleNotificationService-test.pem';
		$transient_key    = 'w3tc_sns_cert_' . \hash( 'sha256', $certificate_url );
		$fixture          = $this->sns_signing_fixture();
		$fetches          = 0;

		\delete_transient( $transient_key );
		$validator = new \W3TC\Sns_MessageValidator(
			static function ( $url ) use ( &$fetches, $certificate_url, $fixture ) {
				++$fetches;
				return $certificate_url === $url ? $fixture['certificate'] : false;
			}
		);

		try {
			$this->assertFalse(
				$validator->validate_message( $this->signed_sns_message( $certificate_url, 'arn:aws:sns:us-east-1:123456789012:other' ), $configured_topic )
			);
			$this->assertSame( 0, $fetches, 'A message from another topic must not retrieve a certificate.' );

			$message = $this->signed_sns_message( $certificate_url, $configured_topic );
			$this->assertTrue( $validator->validate_message( $message, $configured_topic ), 'A signed message must validate on the initial fetch.' );
			$this->assertTrue( $validator->validate_message( $message, $configured_topic ), 'A signed message must validate with the cached certificate.' );
			$this->assertSame( 1, $fetches, 'A repeated certificate URL must use the cached transient.' );

			$message['Message'] = 'changed';
			$this->assertFalse( $validator->validate_message( $message, $configured_topic ), 'A changed message must fail signature validation.' );
		} finally {
			\delete_transient( $transient_key );
		}
	}

	/**
	 * A response that is not a certificate is not cached.
	 *
	 * @since 2.11.1
	 */
	public function test_sns_does_not_cache_non_certificate_response() {
		$configured_topic = 'arn:aws:sns:us-east-1:123456789012:configured';
		$certificate_url  = 'https://sns.us-east-1.amazonaws.com/SimpleNotificationService-error.pem';
		$transient_key    = 'w3tc_sns_cert_' . \hash( 'sha256', $certificate_url );
		$fetches          = 0;

		\delete_transient( $transient_key );
		$validator = new \W3TC\Sns_MessageValidator(
			static function () use ( &$fetches ) {
				++$fetches;
				return '<html>Service Unavailable</html>';
			}
		);

		try {
			$message = $this->signed_sns_message( $certificate_url, $configured_topic );
			$negative_key = 'w3tc_sns_cert_miss_' . \hash( 'sha256', $certificate_url );
			$this->assertFalse( $validator->validate_message( $message, $configured_topic ) );
			$this->assertFalse( $validator->validate_message( $message, $configured_topic ) );
			$this->assertSame( 1, $fetches, 'A failed certificate fetch must not be repeated inside the negative-cache window.' );
			$this->assertFalse( \get_transient( $transient_key ) );
			$this->assertNotFalse( \get_transient( $negative_key ) );
		} finally {
			\delete_transient( $transient_key );
			\delete_transient( 'w3tc_sns_cert_miss_' . \hash( 'sha256', $certificate_url ) );
		}
	}

	/**
	 * Certificate URLs that the AWS library accepts but the allowlist refuses
	 * are never fetched.
	 *
	 * @since 2.11.1
	 *
	 * @dataProvider provide_certificate_urls_refused_by_allowlist
	 *
	 * @param string $certificate_url Certificate URL.
	 */
	public function test_sns_rejects_disallowed_certificate_url_without_fetch( $certificate_url ) {
		$topic   = 'arn:aws:sns:us-east-1:123456789012:configured';
		$fetches = 0;

		$validator = new \W3TC\Sns_MessageValidator(
			static function () use ( &$fetches ) {
				++$fetches;
				return false;
			}
		);

		$this->assertFalse( $validator->validate_message( $this->sns_message( $certificate_url, $topic ), $topic ) );
		$this->assertSame( 0, $fetches, 'A certificate URL outside the allowlist must not be fetched.' );
	}

	/**
	 * Certificate URLs that pass the AWS library check but must be refused.
	 *
	 * @since 2.11.1
	 *
	 * @return array
	 */
	public function provide_certificate_urls_refused_by_allowlist() {
		return array(
			'AWS host, not a SimpleNotificationService name' => array( 'https://sns.us-east-1.amazonaws.com/other.pem' ),
			'AWS host, nested path'                          => array( 'https://sns.us-east-1.amazonaws.com/a/SimpleNotificationService-x.pem' ),
			'non-AWS host'                                   => array( 'https://example.com/SimpleNotificationService-evil.pem' ),
		);
	}

	/**
	 * Varying the certificate name cannot make more uncached fetches than the
	 * global budget allows within one window.
	 *
	 * @since 2.11.1
	 */
	public function test_sns_certificate_fetches_are_limited_across_urls() {
		$topic   = 'arn:aws:sns:us-east-1:123456789012:configured';
		$fetches = 0;
		$count   = \W3TC\Sns_MessageValidator::FETCH_BUDGET * 3;

		\delete_transient( 'w3tc_rl_sns_cert_fetch' );
		$validator = new \W3TC\Sns_MessageValidator(
			static function () use ( &$fetches ) {
				++$fetches;
				return null;
			}
		);

		try {
			for ( $i = 0; $i < $count; $i++ ) {
				$url = 'https://sns.us-east-1.amazonaws.com/SimpleNotificationService-name' . $i . '.pem';
				$this->assertFalse( $validator->validate_message( $this->sns_message( $url, $topic ), $topic ) );
			}

			$this->assertSame(
				\W3TC\Sns_MessageValidator::FETCH_BUDGET,
				$fetches,
				'Distinct allowed certificate names must not exceed the fetch budget in one window.'
			);
		} finally {
			\delete_transient( 'w3tc_rl_sns_cert_fetch' );
			for ( $i = 0; $i < $count; $i++ ) {
				\delete_transient( 'w3tc_sns_cert_miss_' . \hash( 'sha256', 'https://sns.us-east-1.amazonaws.com/SimpleNotificationService-name' . $i . '.pem' ) );
			}
		}
	}

	/**
	 * Only a validated signature marks a certificate URL as known. A message for
	 * the configured topic with a bad signature leaves no marker.
	 *
	 * @since 2.11.1
	 */
	public function test_sns_known_url_marker_is_written_only_after_signature_validates() {
		$topic       = 'arn:aws:sns:us-east-1:123456789012:configured';
		$url         = 'https://sns.us-east-1.amazonaws.com/SimpleNotificationService-marker.pem';
		$certificate = $this->sns_signing_fixture()['certificate'];
		$hash        = \hash( 'sha256', $url );
		$known_key   = 'w3tc_sns_cert_known_' . $hash;
		$keys        = array( 'w3tc_rl_sns_cert_fetch', 'w3tc_rl_sns_cert_fetch_known', 'w3tc_sns_cert_' . $hash, $known_key );

		foreach ( $keys as $key ) {
			\delete_transient( $key );
		}

		$validator = new \W3TC\Sns_MessageValidator(
			static function ( $requested ) use ( $url, $certificate ) {
				return $url === $requested ? $certificate : false;
			}
		);

		try {
			$unsigned = $this->sns_message( $url, $topic );
			$this->assertFalse( $validator->validate_message( $unsigned, $topic ) );
			$this->assertNotFalse( \get_transient( 'w3tc_sns_cert_' . $hash ), 'The certificate itself is still cached.' );
			$this->assertFalse( \get_transient( $known_key ), 'A bad signature must not leave a known-URL marker.' );

			$this->assertTrue( $validator->validate_message( $this->signed_sns_message( $url, $topic ), $topic ) );
			$this->assertNotFalse( \get_transient( $known_key ), 'A validated signature must leave a known-URL marker.' );
		} finally {
			foreach ( $keys as $key ) {
				\delete_transient( $key );
			}
		}
	}

	/**
	 * A URL whose certificate validated a signature before can refresh it after
	 * the shared budget is used up, within its own limit; an unknown name is
	 * still refused.
	 *
	 * @since 2.11.1
	 */
	public function test_sns_known_certificate_url_refreshes_when_shared_budget_is_used_up() {
		$topic       = 'arn:aws:sns:us-east-1:123456789012:configured';
		$known_url   = 'https://sns.us-east-1.amazonaws.com/SimpleNotificationService-known.pem';
		$certificate = $this->sns_signing_fixture()['certificate'];
		$hash        = \hash( 'sha256', $known_url );
		$keys        = array( 'w3tc_rl_sns_cert_fetch', 'w3tc_rl_sns_cert_fetch_known', 'w3tc_sns_cert_' . $hash, 'w3tc_sns_cert_known_' . $hash );
		$count       = \W3TC\Sns_MessageValidator::FETCH_BUDGET;
		$budget      = \W3TC\Sns_MessageValidator::KNOWN_FETCH_BUDGET;
		$fetches     = array();
		$respond     = true;

		foreach ( $keys as $key ) {
			\delete_transient( $key );
		}

		$validator = new \W3TC\Sns_MessageValidator(
			static function ( $url ) use ( &$fetches, &$respond, $known_url, $certificate ) {
				$fetches[] = $url;
				if ( ! $respond ) {
					return false;
				}
				return $known_url === $url ? $certificate : null;
			}
		);

		try {
			$this->assertTrue( $validator->validate_message( $this->signed_sns_message( $known_url, $topic ), $topic ) );
			$this->assertNotFalse( \get_transient( 'w3tc_sns_cert_known_' . $hash ), 'A validated message must leave a known-URL marker.' );

			\delete_transient( 'w3tc_rl_sns_cert_fetch' );
			\delete_transient( 'w3tc_rl_sns_cert_fetch_known' );
			for ( $i = 0; $i < $count; $i++ ) {
				$url = 'https://sns.us-east-1.amazonaws.com/SimpleNotificationService-refresh' . $i . '.pem';
				$this->assertFalse( $validator->get_certificate( $url ) );
			}
			$fetches = array();

			$unknown_url = 'https://sns.us-east-1.amazonaws.com/SimpleNotificationService-refresh-new.pem';
			$this->assertFalse( $validator->get_certificate( $unknown_url ), 'An unknown name must still be refused once the budget is used up.' );
			$this->assertSame( array(), $fetches );

			\delete_transient( 'w3tc_sns_cert_' . $hash );
			$this->assertSame( $certificate, $validator->get_certificate( $known_url ) );
			$this->assertSame( array( $known_url ), $fetches, 'A known URL must be fetched once from its own budget.' );

			// Known URLs have their own limit: nothing is cached, so every call would fetch if allowed.
			$respond = false;
			for ( $i = 0; $i < $budget + 1; $i++ ) {
				\delete_transient( 'w3tc_sns_cert_' . $hash );
				$this->assertFalse( $validator->get_certificate( $known_url ) );
			}
			$this->assertCount(
				$budget,
				$fetches,
				'Known-URL fetches, including the refresh above, must equal the known-URL budget in one window.'
			);
		} finally {
			foreach ( $keys as $key ) {
				\delete_transient( $key );
			}
			for ( $i = 0; $i < $count; $i++ ) {
				\delete_transient( 'w3tc_sns_cert_miss_' . \hash( 'sha256', 'https://sns.us-east-1.amazonaws.com/SimpleNotificationService-refresh' . $i . '.pem' ) );
			}
		}
	}

	/**
	 * A transient fetch failure is not remembered, so the next delivery can
	 * retry; a definitive failure is still remembered.
	 *
	 * @since 2.11.1
	 */
	public function test_sns_transient_fetch_failure_is_not_remembered() {
		$transient_url = 'https://sns.us-east-1.amazonaws.com/SimpleNotificationService-transient.pem';
		$missing_url   = 'https://sns.us-east-1.amazonaws.com/SimpleNotificationService-missing.pem';
		$certificate   = $this->sns_signing_fixture()['certificate'];
		$keys          = array(
			'w3tc_rl_sns_cert_fetch',
			'w3tc_sns_cert_' . \hash( 'sha256', $transient_url ),
			'w3tc_sns_cert_miss_' . \hash( 'sha256', $transient_url ),
			'w3tc_sns_cert_' . \hash( 'sha256', $missing_url ),
			'w3tc_sns_cert_miss_' . \hash( 'sha256', $missing_url ),
		);
		$calls         = array();

		foreach ( $keys as $key ) {
			\delete_transient( $key );
		}

		$validator = new \W3TC\Sns_MessageValidator(
			static function ( $url ) use ( &$calls, $transient_url, $certificate ) {
				$calls[ $url ] = isset( $calls[ $url ] ) ? $calls[ $url ] + 1 : 1;

				if ( $transient_url === $url ) {
					return 1 === $calls[ $url ] ? false : $certificate;
				}

				return null;
			}
		);

		try {
			$this->assertFalse( $validator->get_certificate( $transient_url ), 'A transient failure returns no certificate.' );
			$this->assertSame( $certificate, $validator->get_certificate( $transient_url ), 'The next request must retry after a transient failure.' );
			$this->assertSame( 2, $calls[ $transient_url ] );

			$this->assertFalse( $validator->get_certificate( $missing_url ) );
			$this->assertFalse( $validator->get_certificate( $missing_url ) );
			$this->assertSame( 1, $calls[ $missing_url ], 'A definitive failure must be remembered.' );
		} finally {
			foreach ( $keys as $key ) {
				\delete_transient( $key );
			}
		}
	}

	/**
	 * The pre-bootstrap helper in pub/sns.php and
	 * Sns_MessageValidator::signing_cert_url_allowed() agree on every input.
	 *
	 * pub/sns.php cannot be included (it gates on the request and exits), so
	 * the helper definition is read from the source and defined here.
	 *
	 * @since 2.11.1
	 *
	 * @dataProvider provide_signing_cert_urls
	 *
	 * @param mixed $url      Candidate SigningCertURL.
	 * @param bool  $expected Whether the URL is allowed.
	 */
	public function test_sns_signing_cert_url_allowlists_agree( $url, $expected ) {
		$this->define_pub_signing_cert_url_helper();

		$this->assertSame( $expected, \W3TC\Sns_MessageValidator::signing_cert_url_allowed( $url ), 'Sns_MessageValidator allowlist result.' );
		$this->assertSame( $expected, \w3tc_sns_signing_cert_url_allowed( $url ), 'pub/sns.php allowlist result.' );
	}

	/**
	 * Signing certificate URLs and whether the allowlist accepts each.
	 *
	 * @since 2.11.1
	 *
	 * @return array
	 */
	public function provide_signing_cert_urls() {
		return array(
			'valid'                       => array( 'https://sns.us-east-1.amazonaws.com/SimpleNotificationService-abc_123-XYZ.pem', true ),
			'valid China region'          => array( 'https://sns.cn-north-1.amazonaws.com.cn/SimpleNotificationService-abc.pem', true ),
			'other pem name'              => array( 'https://sns.us-east-1.amazonaws.com/other.pem', false ),
			'nested path'                 => array( 'https://sns.us-east-1.amazonaws.com/a/SimpleNotificationService-x.pem', false ),
			'not pem'                     => array( 'https://sns.us-east-1.amazonaws.com/SimpleNotificationService-x.txt', false ),
			'empty name'                  => array( 'https://sns.us-east-1.amazonaws.com/SimpleNotificationService-.pem', false ),
			'http scheme'                 => array( 'http://sns.us-east-1.amazonaws.com/SimpleNotificationService-x.pem', false ),
			'non-AWS host'                => array( 'https://example.com/SimpleNotificationService-x.pem', false ),
			'AWS name as subdomain'       => array( 'https://sns.us-east-1.amazonaws.com.example.com/SimpleNotificationService-x.pem', false ),
			'short region'                => array( 'https://sns.us.amazonaws.com/SimpleNotificationService-x.pem', false ),
			'user info'                   => array( 'https://user@sns.us-east-1.amazonaws.com/SimpleNotificationService-x.pem', false ),
			'password'                    => array( 'https://user:pass@sns.us-east-1.amazonaws.com/SimpleNotificationService-x.pem', false ),
			'query'                       => array( 'https://sns.us-east-1.amazonaws.com/SimpleNotificationService-x.pem?a=1', false ),
			'fragment'                    => array( 'https://sns.us-east-1.amazonaws.com/SimpleNotificationService-x.pem#a', false ),
			'empty string'                => array( '', false ),
			'integer'                     => array( 123, false ),
			'null'                        => array( null, false ),
			'array'                       => array( array( 'https://sns.us-east-1.amazonaws.com/SimpleNotificationService-x.pem' ), false ),
			'not a URL'                   => array( 'not a url', false ),
		);
	}

	/**
	 * Defines the helper from pub/sns.php for comparison.
	 *
	 * @since 2.11.1
	 *
	 * @return void
	 */
	private function define_pub_signing_cert_url_helper() {
		if ( \function_exists( 'w3tc_sns_signing_cert_url_allowed' ) ) {
			return;
		}

		$this->assertSame(
			1,
			\preg_match( '/^\tfunction w3tc_sns_signing_cert_url_allowed\(.*?^\t\}$/ms', self::$sns_source, $matches ),
			'pub/sns.php must define w3tc_sns_signing_cert_url_allowed() at one level of indentation.'
		);

		eval( $matches[0] ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- Test-only: pub/sns.php exits when included, so its helper is defined from source.
	}

	/**
	 * A signed message is processed when the caller has not pre-validated it
	 * and when the public endpoint already has, and a signed message for
	 * another topic is not dispatched in either case.
	 *
	 * @since 2.11.1
	 */
	public function test_signed_message_is_processed_with_and_without_prevalidation() {
		$configured_topic = 'arn:aws:sns:us-east-1:123456789012:configured';
		$certificate_url  = 'https://sns.us-east-1.amazonaws.com/SimpleNotificationService-processed.pem';
		$transient_key    = 'w3tc_sns_cert_' . \hash( 'sha256', $certificate_url );
		$negative_key     = 'w3tc_sns_cert_miss_' . \hash( 'sha256', $certificate_url );
		$config           = \W3TC\Dispatcher::config();
		$original_topic   = $config->get_string( 'cluster.messagebus.sns.topic_arn' );
		$fixture          = $this->sns_signing_fixture();
		$body             = '{"actions":[]}';
		$received         = \did_action( 'w3tc_messagebus_message_received' );

		\delete_transient( $transient_key );
		\delete_transient( $negative_key );
		$config->set( 'cluster.messagebus.sns.topic_arn', $configured_topic );
		$intercept = static function ( $pre, $args, $url ) use ( $certificate_url, $fixture ) {
			if ( $certificate_url !== $url ) {
				return $pre;
			}

			return array(
				'headers'  => array(),
				'body'     => $fixture['certificate'],
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		};
		\add_filter( 'pre_http_request', $intercept, 10, 3 );

		try {
			$server = new \W3TC\Enterprise_SnsServer();
			$signed = $this->signed_sns_message( $certificate_url, $configured_topic, $body );

			$server->process_message( $signed, false );
			$this->assertSame( $received + 1, \did_action( 'w3tc_messagebus_message_received' ) );

			$server->process_message( $signed, true );
			$this->assertSame( $received + 2, \did_action( 'w3tc_messagebus_message_received' ) );

			$server->process_message( $this->signed_sns_message( $certificate_url, 'arn:aws:sns:us-east-1:123456789012:other', $body ), true );
			$this->assertSame( $received + 2, \did_action( 'w3tc_messagebus_message_received' ), 'A signed message for another topic must not be dispatched, even when pre-validated.' );
		} finally {
			$config->set( 'cluster.messagebus.sns.topic_arn', $original_topic );
			\remove_filter( 'pre_http_request', $intercept, 10 );
			\delete_transient( $transient_key );
			\delete_transient( $negative_key );
		}
	}

	/**
	 * Builds a signing key and matching self-signed certificate.
	 *
	 * @since 2.11.1
	 *
	 * @return array {
	 *     @type mixed  $key         Private key.
	 *     @type string $certificate PEM certificate.
	 * }
	 */
	private function sns_signing_fixture() {
		if ( null === self::$sns_signing_fixture ) {
			$key = \openssl_pkey_new(
				array(
					'private_key_bits' => 2048,
					'private_key_type' => \OPENSSL_KEYTYPE_RSA,
				)
			);
			$csr = \openssl_csr_new( array( 'commonName' => 'sns.amazonaws.com' ), $key, array( 'digest_alg' => 'sha256' ) );
			$x509 = \openssl_csr_sign( $csr, null, $key, 1, array( 'digest_alg' => 'sha256' ) );
			\openssl_x509_export( $x509, $certificate );

			self::$sns_signing_fixture = array(
				'key'         => $key,
				'certificate' => $certificate,
			);
		}

		return self::$sns_signing_fixture;
	}

	/**
	 * Builds an SNS message signed with the fixture key.
	 *
	 * @since 2.11.1
	 *
	 * @param string $certificate_url SNS certificate URL.
	 * @param string $topic_arn       SNS topic ARN.
	 *
	 * @return array
	 */
	private function signed_sns_message( $certificate_url, $topic_arn, $body = 'test' ) {
		$message                     = $this->sns_message( $certificate_url, $topic_arn );
		$message['Message']          = $body;
		$message['SignatureVersion'] = '2';

		$string_to_sign = ( new \Aws\Sns\MessageValidator() )->getStringToSign( new \Aws\Sns\Message( $message ) );
		\openssl_sign( $string_to_sign, $signature, $this->sns_signing_fixture()['key'], \OPENSSL_ALGO_SHA256 );
		$message['Signature'] = \base64_encode( $signature ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		return $message;
	}

	/**
	 * Builds the minimum SNS message required by the AWS validator.
	 *
	 * @since 2.11.1
	 *
	 * @param string $certificate_url SNS certificate URL.
	 * @param string $topic_arn       SNS topic ARN.
	 *
	 * @return array
	 */
	private function sns_message( $certificate_url, $topic_arn ) {
		return array(
			'Message'          => 'test',
			'MessageId'        => '00000000-0000-0000-0000-000000000000',
			'Signature'        => 'invalid',
			'SignatureVersion' => '1',
			'SigningCertURL'   => $certificate_url,
			'Timestamp'        => '2026-01-01T00:00:00.000Z',
			'TopicArn'         => $topic_arn,
			'Type'             => 'Notification',
		);
	}

	/**
	 * `$_SERVER['HTTP_HOST']` is never written from request input in this
	 * file. We scan for any LHS assignment to that superglobal key and
	 * fail the test if one appears.
	 *
	 * @since 2.10.0
	 */
	public function test_sns_never_writes_http_host_from_request() {
		/**
		 * Match `$_SERVER['HTTP_HOST'] = ...` or `$_SERVER[ 'HTTP_HOST' ] = ...`
		 * regardless of internal whitespace.
		 */
		$this->assertSame(
			0,
			\preg_match( '/\$_SERVER\s*\[\s*[\'"]HTTP_HOST[\'"]\s*\]\s*=/', self::$sns_source ),
			'pub/sns.php must NOT assign to $_SERVER[\'HTTP_HOST\'] anywhere. The pre-bootstrap host override was the original RCE pivot.'
		);
	}

	/**
	 * `$w3_current_blog_id` is never set from request input in this file.
	 *
	 * @since 2.10.0
	 */
	public function test_sns_never_writes_current_blog_id_from_request() {
		$this->assertSame(
			0,
			\preg_match( '/\$w3_current_blog_id\s*=/', self::$sns_source ),
			'pub/sns.php must NOT assign $w3_current_blog_id from request input. Multisite switching now happens inside Enterprise_SnsServer after signature validation.'
		);
	}

	/**
	 * `$_GET`, `$_POST`, `$_REQUEST`, and `$_COOKIE` are never read in this
	 * file. The only input source is the validated SNS message body.
	 *
	 * @since 2.10.0
	 */
	public function test_sns_never_reads_request_superglobals_for_data() {
		/**
		 * `$_SERVER` IS read (for REQUEST_METHOD, HTTP_X_AMZ_*, CONTENT_LENGTH)
		 * — that's correct and expected. The forbidden ones are user-data ones.
		 */
		foreach ( array( '$_GET', '$_POST', '$_REQUEST', '$_COOKIE', '$_FILES' ) as $forbidden ) {
			$this->assertStringNotContainsString(
				$forbidden,
				self::$sns_source,
				'pub/sns.php must not read ' . $forbidden . ' — input is php://input only, validated cryptographically.'
			);
		}
	}

	/**
	 * `pub/.htaccess` defaults to deny for all `*.php` files and grants
	 * explicit access only to `sns.php`. A future PHP file dropped into
	 * `pub/` is therefore unreachable on Apache / LiteSpeed without a
	 * matching `<Files>` allowlist entry.
	 *
	 * @since 2.10.0
	 */
	public function test_pub_htaccess_default_denies_php() {
		$this->assertStringContainsString(
			'<FilesMatch "\.php$">',
			self::$htaccess_source,
			'pub/.htaccess must default-deny *.php to prevent silent exposure of new entrypoints.'
		);
		$this->assertStringContainsString(
			'Require all denied',
			self::$htaccess_source,
			'pub/.htaccess must use Apache 2.4+ Require directive to deny.'
		);
		$this->assertStringContainsString(
			'<Files "sns.php">',
			self::$htaccess_source,
			'pub/.htaccess must explicitly allowlist sns.php.'
		);
		$this->assertStringContainsString(
			'Require all granted',
			self::$htaccess_source,
			'pub/.htaccess must use Apache 2.4+ Require directive to grant.'
		);
	}

	/**
	 * Belt-and-braces: `pub/.htaccess` also blocks direct fetches of dotfiles
	 * (e.g. itself, an accidentally committed `.git`), and ships the Apache
	 * 2.2-era Order/Allow,Deny fallback so the rule actually takes effect on
	 * older Apache builds that lack mod_authz_core.
	 *
	 * @since 2.10.0
	 */
	public function test_pub_htaccess_blocks_dotfiles_and_supports_apache_22() {
		$this->assertStringContainsString( '<FilesMatch "^\\.">', self::$htaccess_source );
		$this->assertStringContainsString( 'Order Allow,Deny', self::$htaccess_source );
		$this->assertStringContainsString( 'Deny from all', self::$htaccess_source );
	}

	/**
	 * Parse-check: pub/sns.php must be syntactically valid PHP. Otherwise
	 * the structural assertions above (which only `file_get_contents`) would
	 * silently keep passing for a broken file that 500s in production.
	 *
	 * @since 2.10.0
	 */
	public function test_sns_php_parses_cleanly() {
		$cmd    = \escapeshellcmd( PHP_BINARY ) . ' -l ' . \escapeshellarg( self::$sns_path ) . ' 2>&1';
		$output = array();
		$status = 0;
		\exec( $cmd, $output, $status );

		$this->assertSame(
			0,
			$status,
			'pub/sns.php failed php -l: ' . implode( "\n", $output )
		);
	}
}
