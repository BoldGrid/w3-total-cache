<?php
/**
 * File: sns.php
 *
 * Public AWS SNS webhook receiver for W3 Total Cache cluster cache
 * invalidation messages.
 *
 * Request-handling order:
 *
 *   - Earlier versions of this file read the request body, decoded the JSON
 *     message, and wrote `$_SERVER['HTTP_HOST']` and `$w3tc_w3_current_blog_id`
 *     directly from request data BEFORE loading WordPress. That let the
 *     request set the host the entire WordPress bootstrap saw (init-phase
 *     URL generation, password-reset links, cookie domains, cache keys) on
 *     any site running the plugin.
 *
 *   - The endpoint also bootstrapped the full WordPress stack on every
 *     unauthenticated POST (a ~34MB / ~510ms cost per request), giving any
 *     internet-reachable WordPress install an expensive entrypoint that
 *     anyone could submit to.
 *
 * The order below runs the cheap structural checks first, then loads WordPress,
 * then compares the topic and verifies the signature:
 *
 *   1. Reject any non-POST request and any request without the required AWS
 *      SNS message-type header. Both pre-checks happen before WordPress is
 *      loaded so scanners and DoS traffic are dropped cheaply.
 *   2. Reject a message without a string TopicArn, a known SNS type,
 *      signature version 1 or 2, or an AWS SNS signing-certificate URL
 *      before WordPress loads. The configured topic is not read from a
 *      config file here, because a leftover file may not be the active
 *      config.
 *   3. Load WordPress, so certificate retrieval can use transients and
 *      wp_safe_remote_get.
 *   4. Compare the TopicArn with the loaded config (hash_equals) before any
 *      certificate request, then verify the message signature against
 *      AWS's published signing certificate (cached via transients).
 *   5. Dispatch the validated message to
 *      `Enterprise_SnsServer::process_message()` for execution.
 *   6. `$_SERVER['HTTP_HOST']` and `$w3tc_w3_current_blog_id` are NEVER written
 *      from request input here. Multisite/blog-context handling, if needed,
 *      now happens inside `Enterprise_SnsServer` after validation, using
 *      `switch_to_blog()` against an allowlist derived from configured site
 *      hostnames.
 *
 * Defence in depth: `pub/.htaccess` (shipped alongside this file) denies
 * direct execution of every other PHP file under `pub/` on Apache and
 * LiteSpeed, so adding a new file to this directory does not silently
 * expose a new public entrypoint. Nginx ignores `.htaccess` entirely;
 * `Generic_Environment::get_required_rules()` emits an equivalent
 * `location ~* /w3-total-cache/pub/(?!sns\.php$)[^/]+\.php$ { deny all; }`
 * block into the W3TC-managed `nginx.conf`, so as long as the site's
 * `nginx.conf` includes the W3TC rules file (the standard W3TC install
 * step on nginx) the same deny applies. Operators who include W3TC's
 * rules manually need the include statement to land for this defense
 * in depth to apply.
 *
 * @package W3TC
 *
 * @since 2.10.0
 */

/**
 * SNS endpoint PHPCS suppressions.
 *
 * phpcs:disable WordPress.Security.NonceVerification.Missing -- Endpoint authenticated via AWS SNS signature, not a WP nonce.
 * phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Payload is raw JSON from AWS; validated cryptographically before use.
 * phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- See above; AWS SNS payload is signed JSON.
 */

if ( ! function_exists( 'w3tc_sns_reject' ) ) {
	/**
	 * Emit a small text response and terminate.
	 *
	 * @since 2.10.0
	 *
	 * @param int    $status HTTP status code.
	 * @param string $body   Short response body (single line).
	 *
	 * @return void
	 */
	function w3tc_sns_reject( $status, $body ) {
		if ( ! headers_sent() ) {
			http_response_code( $status );
			header( 'Content-Type: text/plain; charset=utf-8' );
			header( 'Cache-Control: no-store' );
			header( 'X-Robots-Tag: noindex, nofollow, noarchive' );
		}
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Caller-controlled constant string only.
		exit();
	}
}

if ( ! function_exists( 'w3tc_sns_signing_cert_url_allowed' ) ) {
	/**
	 * Whether a signing-certificate URL is an AWS SNS PEM.
	 *
	 * This runs before WordPress loads, so it cannot compare the configured
	 * topic. It matches the host pattern used by the AWS SNS message
	 * validator and requires a SimpleNotificationService certificate name.
	 *
	 * @since 2.11.1
	 *
	 * @param mixed $url Candidate SigningCertURL.
	 *
	 * @return bool
	 */
	function w3tc_sns_signing_cert_url_allowed( $url ) {
		if ( ! is_string( $url ) || '' === $url ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Runs before WordPress loads.
		$parts = parse_url( $url );
		if ( ! is_array( $parts ) ) {
			return false;
		}

		$scheme = isset( $parts['scheme'] ) ? $parts['scheme'] : '';
		$host   = isset( $parts['host'] ) ? $parts['host'] : '';
		$path   = isset( $parts['path'] ) ? $parts['path'] : '';
		if ( 'https' !== $scheme || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) ) {
			return false;
		}
		if ( 1 !== preg_match( '/^sns\.[a-zA-Z0-9\-]{3,}\.amazonaws\.com(\.cn)?$/', $host ) ) {
			return false;
		}

		return 1 === preg_match( '/^\/SimpleNotificationService-[A-Za-z0-9_-]+\.pem$/', $path );
	}
}

// 1. Method gate: this endpoint exists solely to receive AWS SNS POSTs.
if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) ) {
	w3tc_sns_reject( 405, 'Method Not Allowed' );
}

/**
 * 2. Header gate: AWS SNS always sends `x-amz-sns-message-type`. Drop scanners
 * and generic POST traffic before paying for any further work, including
 * reading the request body.
 */
if ( empty( $_SERVER['HTTP_X_AMZ_SNS_MESSAGE_TYPE'] ) ) {
	w3tc_sns_reject( 400, 'Missing SNS headers' );
}

/**
 * 3. Bound the request body. AWS SNS notifications are well below 256KB; we
 * hard-cap to defend against memory-exhaustion attempts.
 *
 * The Content-Length check is a fast pre-filter, but it is not sufficient on
 * its own: chunked transfer-encoded requests (and proxied requests where the
 * front-end strips Content-Length) report 0 here. We therefore also read the
 * body ourselves with a hard byte cap so a streaming client cannot bypass the
 * limit by omitting Content-Length.
 */
$w3tc_sns_max_body_bytes = 262144; // 256 KB.
$w3tc_sns_content_length = isset( $_SERVER['CONTENT_LENGTH'] ) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
if ( $w3tc_sns_content_length > $w3tc_sns_max_body_bytes ) {
	w3tc_sns_reject( 413, 'Payload too large' );
}

/**
 * Read at most max+1 bytes; if the stream still has data we know the payload
 * exceeds the cap and reject. We deliberately consume php://input here so the
 * SDK's `fromRawPostData()` (which would re-read the stream unbounded) is not
 * used downstream.
 */
$w3tc_sns_input_handle = fopen( 'php://input', 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- pre-WP-bootstrap; WP_Filesystem unavailable.
if ( false === $w3tc_sns_input_handle ) {
	w3tc_sns_reject( 400, 'Cannot read body' );
}
$w3tc_sns_body = stream_get_contents( $w3tc_sns_input_handle, $w3tc_sns_max_body_bytes + 1 );
fclose( $w3tc_sns_input_handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- pre-WP-bootstrap; WP_Filesystem unavailable.
if ( false === $w3tc_sns_body || strlen( $w3tc_sns_body ) > $w3tc_sns_max_body_bytes ) {
	w3tc_sns_reject( 413, 'Payload too large' );
}

/**
 * 4. Locate the plugin's vendored Composer autoloader so we can build the AWS
 * SNS `Message` object before WordPress is bootstrapped. Only `Message` is
 * built here; the message validator runs after bootstrap (see step 8). This
 * file lives in `<plugin>/pub/sns.php`, so the autoloader is one level up.
 */
$w3tc_sns_autoload = __DIR__ . '/../vendor/autoload.php';
if ( ! file_exists( $w3tc_sns_autoload ) ) {
	w3tc_sns_reject( 500, 'Server misconfigured' );
}
require_once $w3tc_sns_autoload;

if ( ! class_exists( '\Aws\Sns\Message' ) || ! class_exists( '\Aws\Sns\MessageValidator' ) ) {
	w3tc_sns_reject( 500, 'Server misconfigured' );
}

/**
 * 5. Build the SNS Message directly from the bounded body we read in step 3.
 * The SDK's `fromRawPostData()` re-reads php://input without a size cap and
 * `fromJsonString()` would re-decode the body, so we decode once and pass the
 * array to the constructor. Catch \Throwable rather than \Exception so future
 * SDK upgrades that throw Error subtypes (e.g. TypeError on a missing key) do
 * not bypass the 400 response and surface a 500 stack trace.
 */
$w3tc_sns_decoded = json_decode( $w3tc_sns_body, true );
if ( ! is_array( $w3tc_sns_decoded ) ) {
	w3tc_sns_reject( 400, 'Invalid SNS message' );
}
try {
	$w3tc_sns_message = new \Aws\Sns\Message( $w3tc_sns_decoded );
} catch ( \Throwable $e ) {
	w3tc_sns_reject( 400, 'Invalid SNS message' );
}

/**
 * 6. The configured topic can live in a file or the database, and only the
 * loaded config says which is active, so the topic itself is compared after
 * bootstrap.
 */
if ( ! isset( $w3tc_sns_decoded['TopicArn'] ) || ! \is_string( $w3tc_sns_decoded['TopicArn'] ) ) {
	w3tc_sns_reject( 403, 'Forbidden' );
}

$w3tc_sns_type              = isset( $w3tc_sns_decoded['Type'] ) ? $w3tc_sns_decoded['Type'] : '';
$w3tc_sns_signature_version = isset( $w3tc_sns_decoded['SignatureVersion'] ) ? $w3tc_sns_decoded['SignatureVersion'] : '';
if (
	! is_string( $w3tc_sns_type ) ||
	! in_array( $w3tc_sns_type, array( 'Notification', 'SubscriptionConfirmation', 'UnsubscribeConfirmation' ), true ) ||
	! is_string( $w3tc_sns_signature_version ) ||
	! in_array( $w3tc_sns_signature_version, array( '1', '2' ), true ) ||
	! w3tc_sns_signing_cert_url_allowed( isset( $w3tc_sns_decoded['SigningCertURL'] ) ? $w3tc_sns_decoded['SigningCertURL'] : '' )
) {
	w3tc_sns_reject( 403, 'Forbidden' );
}

/**
 * 7. Load WordPress so SNS certificate validation can use transients and
 * wp_safe_remote_get.
 */
if ( ! defined( 'W3TC_WP_LOADING' ) ) {
	define( 'W3TC_WP_LOADING', true );
}

if ( ! defined( 'ABSPATH' ) ) {
	/**
	 * Standard layout: this file lives at
	 * `wp-content/plugins/w3-total-cache/pub/sns.php`, so wp-load.php is four
	 * directories up. Sites that vendor W3TC outside `wp-content/plugins/`
	 * (must-use plugin dirs, custom plugin directories, some managed-host
	 * bundles) ship a shim at `<plugin>/../w3tc-wp-loader.php`; fall back to
	 * that before erroring so non-standard installs don't 500 on every SNS
	 * POST after the upgrade.
	 */
	$w3tc_sns_wp_load = __DIR__ . '/../../../../wp-load.php';
	if ( ! file_exists( $w3tc_sns_wp_load ) ) {
		$w3tc_sns_wp_load = __DIR__ . '/../../w3tc-wp-loader.php';
	}
	if ( ! file_exists( $w3tc_sns_wp_load ) ) {
		w3tc_sns_reject( 500, 'WordPress not found' );
	}
	require_once $w3tc_sns_wp_load;
}

if ( ! defined( 'W3TC_DIR' ) ) {
	define( 'W3TC_DIR', realpath( __DIR__ . '/..' ) );
}

if ( ! is_dir( W3TC_DIR ) || ! file_exists( W3TC_DIR . '/w3-total-cache-api.php' ) ) {
	w3tc_sns_reject( 500, 'Plugin files missing' );
}

require_once W3TC_DIR . '/w3-total-cache-api.php';

/**
 * 8. The loaded config is authoritative for the topic. validate_message()
 * rejects a mismatch before any certificate request.
 */
$w3tc_sns_topic_arn = \W3TC\Dispatcher::config()->get_string( 'cluster.messagebus.sns.topic_arn' );
$w3tc_sns_validator = new \W3TC\Sns_MessageValidator();
if ( ! $w3tc_sns_validator->validate_message( $w3tc_sns_message->toArray(), $w3tc_sns_topic_arn ) ) {
	w3tc_sns_reject( 403, 'Forbidden' );
}

$w3tc_sns_server = \W3TC\Dispatcher::component( 'Enterprise_SnsServer' );
$w3tc_sns_server->process_message( $w3tc_sns_message->toArray(), true );
