<?php
/**
 * File: Sns_MessageValidator.php
 *
 * @package W3TC
 *
 * @since 2.11.1
 */

namespace W3TC;

/**
 * Validates SNS messages using cached signing certificates.
 *
 * @since 2.11.1
 */
class Sns_MessageValidator {
	/**
	 * Lifetime for cached signing certificates.
	 *
	 * @since 2.11.1
	 *
	 * @var int
	 */
	const CERTIFICATE_TTL = 3600;

	/**
	 * Lifetime for a remembered failed certificate fetch.
	 *
	 * @since 2.11.1
	 *
	 * @var int
	 */
	const FAILED_FETCH_TTL = 60;

	/**
	 * Uncached certificate fetches allowed per window, across all URLs.
	 *
	 * @since 2.11.1
	 *
	 * @var int
	 */
	const FETCH_BUDGET = 10;

	/**
	 * Length in seconds of the certificate fetch budget window.
	 *
	 * @since 2.11.1
	 *
	 * @var int
	 */
	const FETCH_BUDGET_WINDOW = 60;

	/**
	 * Uncached fetches allowed per window for URLs whose certificate validated a signature before.
	 *
	 * @since 2.11.1
	 *
	 * @var int
	 */
	const KNOWN_FETCH_BUDGET = 5;

	/**
	 * Lifetime for the marker that a URL's certificate once validated a message signature.
	 *
	 * @since 2.11.1
	 *
	 * @var int
	 */
	const KNOWN_URL_TTL = 604800;

	/**
	 * Retrieves certificate contents.
	 *
	 * The callable returns the certificate body as a string. A non-certificate
	 * string or null is a definitive failure that is remembered briefly. False
	 * is a transient failure (timeout, 5xx, WP_Error) that is not remembered.
	 *
	 * @since 2.11.1
	 *
	 * @var callable
	 */
	private $certificate_fetcher;

	/**
	 * Certificate URL that get_certificate() last returned a certificate for.
	 *
	 * Used to mark the URL as known only after a signature validates.
	 *
	 * @since 2.11.1
	 *
	 * @var string|null
	 */
	private $retrieved_certificate_url = null;

	/**
	 * Constructor.
	 *
	 * @since 2.11.1
	 *
	 * @param callable|null $certificate_fetcher Retrieves a certificate by URL. Returns the body, null for a
	 *                                           definitive failure, or false for a transient failure.
	 */
	public function __construct( $certificate_fetcher = null ) {
		$this->certificate_fetcher = $certificate_fetcher ? $certificate_fetcher : array( $this, 'fetch_certificate' );
	}

	/**
	 * Validates a message after confirming it belongs to the configured topic.
	 *
	 * @since 2.11.1
	 *
	 * @param array  $message   SNS message data.
	 * @param string $topic_arn Configured SNS topic ARN.
	 *
	 * @return bool Whether the message is valid.
	 */
	public function validate_message( $message, $topic_arn ) {
		if (
			! \is_string( $topic_arn ) ||
			'' === $topic_arn ||
			! isset( $message['TopicArn'] ) ||
			! \is_string( $message['TopicArn'] ) ||
			! \hash_equals( $topic_arn, $message['TopicArn'] )
		) {
			return false;
		}

		if ( ! \class_exists( '\Aws\Sns\MessageValidator' ) ) {
			require_once W3TC_DIR . '/vendor/autoload.php';
		}

		$this->retrieved_certificate_url = null;

		try {
			$sns_message = new \Aws\Sns\Message( $message );
			$validator   = new \Aws\Sns\MessageValidator( array( $this, 'get_certificate' ) );
			$validator->validate( $sns_message );
		} catch ( \Throwable $e ) {
			return false;
		}

		// Only a validated signature marks the URL as known, so unsigned posts cannot create markers.
		if ( \is_string( $this->retrieved_certificate_url ) && \function_exists( 'set_transient' ) ) {
			\set_transient( 'w3tc_sns_cert_known_' . \hash( 'sha256', $this->retrieved_certificate_url ), 1, self::KNOWN_URL_TTL );
		}

		return true;
	}

	/**
	 * Retrieves a certificate from the transient cache or its source.
	 *
	 * @since 2.11.1
	 *
	 * @param string $certificate_url SNS certificate URL.
	 *
	 * @return string|false Certificate contents, or false on failure.
	 */
	public function get_certificate( $certificate_url ) {
		if ( ! self::signing_cert_url_allowed( $certificate_url ) ) {
			return false;
		}

		$transient_key = 'w3tc_sns_cert_' . \hash( 'sha256', $certificate_url );
		$negative_key  = 'w3tc_sns_cert_miss_' . \hash( 'sha256', $certificate_url );
		$known_key     = 'w3tc_sns_cert_known_' . \hash( 'sha256', $certificate_url );
		$certificate   = \function_exists( 'get_transient' ) ? \get_transient( $transient_key ) : false;

		if ( \is_string( $certificate ) && '' !== $certificate ) {
			$this->retrieved_certificate_url = $certificate_url;
			return $certificate;
		}
		if ( \function_exists( 'get_transient' ) && \get_transient( $negative_key ) ) {
			return false;
		}

		/*
		 * Bound uncached fetches so varying the certificate name cannot trigger a request each time.
		 * A URL whose certificate validated a signature before is refreshed from its own small budget,
		 * so unknown names cannot starve a real certificate refresh.
		 */
		if ( \function_exists( 'set_transient' ) ) {
			$known   = \get_transient( $known_key );
			$allowed = $known
				? Util_RateLimit::allow( 'sns_cert_fetch_known', self::KNOWN_FETCH_BUDGET, self::FETCH_BUDGET_WINDOW )
				: Util_RateLimit::allow( 'sns_cert_fetch', self::FETCH_BUDGET, self::FETCH_BUDGET_WINDOW );
			if ( ! $allowed ) {
				return false;
			}
		}

		$certificate = \call_user_func( $this->certificate_fetcher, $certificate_url );
		if ( false === $certificate ) {
			// Transient failure: do not remember it, so the next delivery can retry.
			return false;
		}

		if ( ! \is_string( $certificate ) || '' === $certificate || false === \openssl_pkey_get_public( $certificate ) ) {
			// Definitive failure (4xx or a body that is not a certificate): remember it briefly.
			if ( \function_exists( 'set_transient' ) ) {
				\set_transient( $negative_key, 1, self::FAILED_FETCH_TTL );
			}
			return false;
		}

		if ( \function_exists( 'set_transient' ) ) {
			\set_transient( $transient_key, $certificate, self::CERTIFICATE_TTL );
		}

		$this->retrieved_certificate_url = $certificate_url;

		return $certificate;
	}

	/**
	 * Whether a signing-certificate URL is an AWS SNS PEM.
	 *
	 * The same host pattern is used by the AWS SNS message validator.
	 * The path must be a SimpleNotificationService certificate so a caller
	 * cannot vary an arbitrary `.pem` name on the AWS host.
	 *
	 * @since 2.11.1
	 *
	 * @param mixed $url Candidate SigningCertURL.
	 *
	 * @return bool
	 */
	public static function signing_cert_url_allowed( $url ) {
		if ( ! \is_string( $url ) || '' === $url ) {
			return false;
		}

		$parts = \wp_parse_url( $url );
		if ( ! \is_array( $parts ) ) {
			return false;
		}

		$scheme = isset( $parts['scheme'] ) ? $parts['scheme'] : '';
		$host   = isset( $parts['host'] ) ? $parts['host'] : '';
		$path   = isset( $parts['path'] ) ? $parts['path'] : '';
		if ( 'https' !== $scheme || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) ) {
			return false;
		}
		if ( 1 !== \preg_match( '/^sns\.[a-zA-Z0-9\-]{3,}\.amazonaws\.com(\.cn)?$/', $host ) ) {
			return false;
		}

		return 1 === \preg_match( '/^\/SimpleNotificationService-[A-Za-z0-9_-]+\.pem$/', $path );
	}

	/**
	 * Fetches a certificate using the WordPress HTTP client.
	 *
	 * @since 2.11.1
	 *
	 * @param string $certificate_url SNS certificate URL.
	 *
	 * @return string|false|null Certificate contents, null for a definitive failure (4xx other than
	 *                            408/429, or an empty body), or false for a transient failure
	 *                            (WP_Error, timeout, 408, 429, 5xx).
	 */
	private function fetch_certificate( $certificate_url ) {
		if ( ! \function_exists( 'wp_safe_remote_get' ) ) {
			return false;
		}

		$response = \wp_safe_remote_get(
			$certificate_url,
			array(
				'limit_response_size' => 131072,
				'redirection'         => 0,
				'sslverify'           => true,
				'timeout'             => 10,
			)
		);

		if ( \is_wp_error( $response ) ) {
			return false;
		}

		$code = (int) \wp_remote_retrieve_response_code( $response );
		if ( $code >= 400 && $code < 500 && ! \in_array( $code, array( 408, 429 ), true ) ) {
			return null;
		}
		if ( 200 !== $code ) {
			return false;
		}

		$certificate = \wp_remote_retrieve_body( $response );

		return \is_string( $certificate ) && '' !== $certificate ? $certificate : null;
	}
}
