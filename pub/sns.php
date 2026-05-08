<?php
/**
 * File: sns.php
 *
 * Public AWS SNS webhook receiver for W3 Total Cache cluster cache
 * invalidation messages.
 *
 * SECURITY (rt9-20 / rt9-148 / rt9-179 / rt9-155):
 *
 *   - Earlier versions of this file read the request body, decoded the JSON
 *     payload, and wrote `$_SERVER['HTTP_HOST']` and `$w3_current_blog_id`
 *     directly from attacker-controlled data BEFORE loading WordPress. That
 *     allowed an unauthenticated remote attacker to host-poison the entire
 *     WordPress bootstrap (init-phase URL generation, password-reset links,
 *     cookie domains, cache keys) on any site running the plugin.
 *
 *   - The endpoint also bootstrapped the full WordPress stack on every
 *     unauthenticated POST (a ~34MB / ~510ms cost per request), making it a
 *     DoS amplifier for any internet-reachable WordPress install.
 *
 * The hardened flow is:
 *
 *   1. Reject any non-POST request and any request without the required AWS
 *      SNS message-type header. Both pre-checks happen before WordPress is
 *      loaded so scanners and DoS traffic are dropped cheaply.
 *   2. Load only the bundled AWS SNS message validator (Composer autoloader)
 *      — *not* WordPress — and verify the message's signature against AWS's
 *      published SNS signing certificates.
 *   3. Only after the signature is verified, load WordPress and dispatch the
 *      message to `Enterprise_SnsServer::process_message()` for further
 *      authorisation (TopicArn match) and execution.
 *   4. `$_SERVER['HTTP_HOST']` and `$w3_current_blog_id` are NEVER written
 *      from request input here. Multisite/blog-context handling, if needed,
 *      now happens inside `Enterprise_SnsServer` after validation, using
 *      `switch_to_blog()` against an allowlist derived from configured site
 *      hostnames.
 *
 * Defence in depth: `pub/.htaccess` (shipped alongside this file) denies
 * direct execution of every other PHP file under `pub/`, so adding a new
 * file to this directory does not silently expose a new public entrypoint.
 *
 * @package W3TC
 *
 * @since 2.9.5
 */

// phpcs:disable WordPress.Security.NonceVerification.Missing -- Endpoint authenticated via AWS SNS signature, not a WP nonce.
// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Payload is raw JSON from AWS; validated cryptographically before use.
// phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- See above; AWS SNS payload is signed JSON.

/**
 * Emit a small text response and terminate.
 *
 * @since 2.9.5
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

// 1. Method gate: this endpoint exists solely to receive AWS SNS POSTs.
if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) ) {
	w3tc_sns_reject( 405, 'Method Not Allowed' );
}

// 2. Header gate: AWS SNS always sends `x-amz-sns-message-type`. Drop scanners
// and generic POST traffic before paying for any further work, including
// reading the request body.
if ( empty( $_SERVER['HTTP_X_AMZ_SNS_MESSAGE_TYPE'] ) ) {
	w3tc_sns_reject( 400, 'Missing SNS headers' );
}

// 3. Bound the request body. AWS SNS notifications are well below 256KB; we
// hard-cap to defend against memory-exhaustion attempts.
$w3tc_sns_max_body_bytes = 262144; // 256 KB.
$w3tc_sns_content_length = isset( $_SERVER['CONTENT_LENGTH'] ) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
if ( $w3tc_sns_content_length > $w3tc_sns_max_body_bytes ) {
	w3tc_sns_reject( 413, 'Payload too large' );
}

// 4. Locate the plugin's vendored Composer autoloader so we can use the AWS
// SNS message validator WITHOUT bootstrapping WordPress. This file lives in
// `<plugin>/pub/sns.php`, so the autoloader is one level up.
$w3tc_sns_autoload = __DIR__ . '/../vendor/autoload.php';
if ( ! file_exists( $w3tc_sns_autoload ) ) {
	w3tc_sns_reject( 500, 'Server misconfigured' );
}
require_once $w3tc_sns_autoload;

if ( ! class_exists( '\Aws\Sns\Message' ) || ! class_exists( '\Aws\Sns\MessageValidator' ) ) {
	w3tc_sns_reject( 500, 'Server misconfigured' );
}

// 5. Build the SNS Message from the raw POST body (this also reads
// php://input). On any structural problem, fail closed without leaking
// detail.
try {
	$w3tc_sns_message = \Aws\Sns\Message::fromRawPostData();
} catch ( \Exception $e ) {
	w3tc_sns_reject( 400, 'Invalid SNS message' );
}

// 6. Cryptographically validate the message signature against AWS's
// published SNS signing certificates before we trust ANY field on it. The
// validator throws on any failure (bad signature, untrusted cert host, etc).
try {
	$w3tc_sns_validator = new \Aws\Sns\MessageValidator();
	$w3tc_sns_validator->validate( $w3tc_sns_message );
} catch ( \Exception $e ) {
	w3tc_sns_reject( 403, 'Forbidden' );
}

// 7. Signature verified. NOW we can load WordPress and hand the message off
// to the existing handler for TopicArn matching and action dispatch.
//
// Note: we deliberately do NOT mutate `$_SERVER['HTTP_HOST']` or
// `$w3_current_blog_id` here. Any multisite/blog-context switching the SNS
// payload requests is performed inside `Enterprise_SnsServer` AFTER
// validation, against an allowlist of configured site hostnames.
if ( ! defined( 'W3TC_WP_LOADING' ) ) {
	define( 'W3TC_WP_LOADING', true );
}

if ( ! defined( 'ABSPATH' ) ) {
	$w3tc_sns_wp_load = __DIR__ . '/../../../../wp-load.php';
	if ( ! file_exists( $w3tc_sns_wp_load ) ) {
		w3tc_sns_reject( 500, 'WordPress not found' );
	}
	require_once $w3tc_sns_wp_load;
}

if ( ! defined( 'W3TC_DIR' ) ) {
	define( 'W3TC_DIR', realpath( __DIR__ . '/..' ) );
}

if ( ! @is_dir( W3TC_DIR ) || ! file_exists( W3TC_DIR . '/w3-total-cache-api.php' ) ) {
	w3tc_sns_reject( 500, 'Plugin files missing' );
}

require_once W3TC_DIR . '/w3-total-cache-api.php';

// Convert the validated SNS Message back to a plain array for the existing
// handler signature (it constructs its own `\Aws\Sns\Message` internally and
// re-validates as belt-and-braces).
$w3tc_sns_message_array = $w3tc_sns_message->toArray();

$w3tc_sns_server = \W3TC\Dispatcher::component( 'Enterprise_SnsServer' );
$w3tc_sns_server->process_message( $w3tc_sns_message_array );
