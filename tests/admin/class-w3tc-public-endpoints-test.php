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
 *   - The SNS signature validator runs before any `require wp-load.php`.
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
 * @since      X.X.X
 */

declare( strict_types = 1 );

/**
 * Class: W3tc_Public_Endpoints_Test
 *
 * @since X.X.X
 */
class W3tc_Public_Endpoints_Test extends WP_UnitTestCase {

	/**
	 * Absolute path to pub/sns.php in the plugin checkout.
	 *
	 * @since X.X.X
	 *
	 * @var string
	 */
	private static $sns_path;

	/**
	 * Absolute path to pub/.htaccess in the plugin checkout.
	 *
	 * @since X.X.X
	 *
	 * @var string
	 */
	private static $htaccess_path;

	/**
	 * Verbatim contents of pub/sns.php, loaded once.
	 *
	 * @since X.X.X
	 *
	 * @var string
	 */
	private static $sns_source;

	/**
	 * Verbatim contents of pub/.htaccess, loaded once.
	 *
	 * @since X.X.X
	 *
	 * @var string
	 */
	private static $htaccess_source;

	/**
	 * Cache the file contents once for the whole suite.
	 *
	 * @since X.X.X
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$sns_path        = W3TC_DIR . '/pub/sns.php';
		self::$htaccess_path   = W3TC_DIR . '/pub/.htaccess';
		self::$sns_source      = \file_exists( self::$sns_path ) ? \file_get_contents( self::$sns_path ) : '';
		self::$htaccess_source = \file_exists( self::$htaccess_path ) ? \file_get_contents( self::$htaccess_path ) : '';
	}

	/**
	 * Compute the offset of a literal substring in pub/sns.php so we can
	 * compare line-ordering between guards and the wp-load require.
	 *
	 * Fails the calling test on a non-match — the structural assertions below
	 * are meaningless if the anchor disappears in a refactor.
	 *
	 * @since X.X.X
	 *
	 * @param string $needle Literal substring to locate.
	 *
	 * @return int Byte-offset of the first occurrence.
	 */
	private function offset_of( $needle ) {
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
	 * @since X.X.X
	 */
	public function test_pub_files_exist() {
		$this->assertFileExists( self::$sns_path, 'pub/sns.php must ship with the plugin.' );
		$this->assertFileExists( self::$htaccess_path, 'pub/.htaccess must ship with the plugin so dropped-in PHP under pub/ is unreachable on Apache.' );
	}

	/**
	 * The SNS entrypoint rejects non-POST requests before any further work.
	 *
	 * @since X.X.X
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
	 * @since X.X.X
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
	 * @since X.X.X
	 */
	public function test_sns_enforces_body_byte_cap_via_stream_read() {
		$this->assertStringContainsString( 'stream_get_contents', self::$sns_source );
		$this->assertStringContainsString( 'max_body_bytes + 1', self::$sns_source );
		$this->assertStringContainsString( '262144', self::$sns_source );
		$this->assertStringContainsString( 'Payload too large', self::$sns_source );
	}

	/**
	 * The SNS signature validator runs BEFORE WordPress is loaded.
	 *
	 * @since X.X.X
	 */
	public function test_sns_signature_check_precedes_wp_load() {
		$validator_call = $this->offset_of( '$w3tc_sns_validator->validate' );
		$wp_load_call   = $this->offset_of( "require_once \$w3tc_sns_wp_load" );

		$this->assertLessThan(
			$wp_load_call,
			$validator_call,
			'SNS signature validation must run before wp-load.php is required. ' .
				'Loading WordPress first re-opens the HTTP_HOST/blog-id injection window from earlier versions.'
		);
	}

	/**
	 * The autoloader is required from the plugin's own vendor/ directory —
	 * not from a path joined with attacker input — and before the validator.
	 *
	 * @since X.X.X
	 */
	public function test_sns_loads_vendor_autoload_before_validator() {
		$autoload_require = $this->offset_of( "require_once \$w3tc_sns_autoload" );
		$validator_new    = $this->offset_of( '\\Aws\\Sns\\MessageValidator()' );

		$this->assertLessThan(
			$validator_new,
			$autoload_require,
			'Composer autoloader must be required before \\Aws\\Sns\\MessageValidator is instantiated.'
		);
		$this->assertStringContainsString(
			"__DIR__ . '/../vendor/autoload.php'",
			self::$sns_source,
			'Autoload path must be the plugin-relative vendor/autoload.php — never derived from request input.'
		);
	}

	/**
	 * `$_SERVER['HTTP_HOST']` is never written from request input in this
	 * file. We scan for any LHS assignment to that superglobal key and
	 * fail the test if one appears.
	 *
	 * @since X.X.X
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
	 * @since X.X.X
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
	 * @since X.X.X
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
	 * @since X.X.X
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
	 * @since X.X.X
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
	 * @since X.X.X
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
