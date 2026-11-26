<?php
/**
 * File: Cdn_GoogleDrive_OAuthTokens.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdn_GoogleDrive_OAuthTokens
 *
 * Short-lived, user-bound store for Google Drive OAuth return values.
 *
 * The authorization proxy can place oa_* values on the first admin
 * return URL. This class copies those values into a per-user transient.
 * Later admin URLs, the folder-picker popup, and the auth-set form
 * carry only an opaque handle.
 *
 * @since 2.11.1
 */
class Cdn_GoogleDrive_OAuthTokens {
	/**
	 * Transient key prefix. Concatenated with the current user ID.
	 *
	 * @since 2.11.1
	 *
	 * @var string
	 */
	const TRANSIENT_PREFIX = 'w3tc_gdrive_oauth_tokens_';

	/**
	 * Transient TTL in seconds. Matches the OAuth state window so the
	 * folder picker can finish the same round trip.
	 *
	 * @since 2.11.1
	 *
	 * @var int
	 */
	const TRANSIENT_TTL = 900;

	/**
	 * Query and form parameter that carries the opaque handle.
	 *
	 * @since 2.11.1
	 *
	 * @var string
	 */
	const HANDLE_PARAM = 'w3tc_gdrive_oauth';

	/**
	 * Request keys copied into the store on the OAuth return.
	 *
	 * @since 2.11.1
	 *
	 * @var string[]
	 */
	const TOKEN_KEYS = array(
		'oa_client_id',
		'oa_access_token',
		'oa_refresh_token',
		'oa_token_type',
		'oa_expires_in',
		'oa_created',
	);

	/**
	 * Read one scalar request value.
	 *
	 * Arrays and other non-strings are ignored so a crafted query
	 * cannot reach string sanitizers.
	 *
	 * @since 2.11.1
	 *
	 * @param string $key Request key.
	 *
	 * @return string
	 */
	public static function request_string( $key ) {
		if ( ! \is_string( $key ) || '' === $key ) {
			return '';
		}

		$value = Util_Request::get( $key, '' );
		if ( ! \is_string( $value ) ) {
			return '';
		}

		return \trim( $value );
	}

	/**
	 * Whether the current request includes any OAuth return parameter.
	 *
	 * @since 2.11.1
	 *
	 * @return bool
	 */
	public static function request_has_oauth_parameters() {
		foreach ( self::TOKEN_KEYS as $key ) {
			if ( '' !== self::request_string( $key ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Collect OAuth return parameters from the current request.
	 *
	 * @since 2.11.1
	 *
	 * @return array<string,string>
	 */
	public static function from_request() {
		$tokens = array();

		foreach ( self::TOKEN_KEYS as $key ) {
			$tokens[ $key ] = self::request_string( $key );
		}

		return $tokens;
	}

	/**
	 * Whether the token map has the fields required to call Google Drive.
	 *
	 * @since 2.11.1
	 *
	 * @param array<string,string> $tokens OAuth return values.
	 *
	 * @return bool
	 */
	public static function are_complete( array $tokens ) {
		$tokens = self::normalize( $tokens );

		foreach ( array( 'oa_client_id', 'oa_access_token', 'oa_refresh_token' ) as $key ) {
			if ( '' === $tokens[ $key ] ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Store OAuth return values for the current user and return the handle.
	 *
	 * @since 2.11.1
	 *
	 * @param array<string,string> $tokens OAuth return values.
	 *
	 * @return string Handle, or '' when there is nothing to store.
	 */
	public static function store( array $tokens ) {
		$user_id = \get_current_user_id();
		if ( ! $user_id ) {
			return '';
		}

		$normalized = self::normalize( $tokens );
		if ( ! self::are_complete( $normalized ) ) {
			return '';
		}

		$handle = \wp_generate_password( 32, false, false );
		\set_transient(
			self::TRANSIENT_PREFIX . $user_id,
			array(
				'handle' => $handle,
				'tokens' => $normalized,
			),
			self::TRANSIENT_TTL
		);

		return $handle;
	}

	/**
	 * Read OAuth values when the handle matches the current user's store.
	 *
	 * @since 2.11.1
	 *
	 * @param mixed $handle Submitted handle.
	 *
	 * @return array<string,string> Token map, or an empty array.
	 */
	public static function read( $handle ) {
		$user_id = \get_current_user_id();
		if ( ! $user_id || ! \is_string( $handle ) || '' === $handle ) {
			return array();
		}

		$stored = \get_transient( self::TRANSIENT_PREFIX . $user_id );
		if (
			! \is_array( $stored ) ||
			! isset( $stored['handle'], $stored['tokens'] ) ||
			! \is_string( $stored['handle'] ) ||
			! \is_array( $stored['tokens'] ) ||
			! \hash_equals( $stored['handle'], $handle )
		) {
			return array();
		}

		return self::normalize( $stored['tokens'] );
	}

	/**
	 * Handle submitted with the auth-set form.
	 *
	 * The value is taken from $_POST only. A query-string handle or a
	 * raw token field is not accepted here.
	 *
	 * @since 2.11.1
	 *
	 * @return string
	 */
	public static function posted_handle() {
		$key = self::HANDLE_PARAM;

		// The admin-action dispatcher verifies the auth-set nonce before this runs.
		if ( ! isset( $_POST[ $key ] ) || ! \is_scalar( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return '';
		}

		return \sanitize_text_field( \wp_unslash( (string) $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	/**
	 * OAuth values for the auth-set POST, looked up by the posted handle.
	 *
	 * @since 2.11.1
	 *
	 * @return array<string,string>
	 */
	public static function posted_tokens() {
		return self::read( self::posted_handle() );
	}

	/**
	 * JSON access-token document expected by the Google client.
	 *
	 * @since 2.11.1
	 *
	 * @param array<string,string> $tokens Stored OAuth values.
	 *
	 * @return string
	 */
	public static function access_token_json( array $tokens ) {
		$tokens = self::normalize( $tokens );

		return \wp_json_encode(
			array(
				'access_token' => $tokens['oa_access_token'],
				'token_type'   => $tokens['oa_token_type'],
				'expires_in'   => $tokens['oa_expires_in'],
				'created'      => $tokens['oa_created'],
			)
		);
	}

	/**
	 * Delete the current user's stored OAuth values.
	 *
	 * @since 2.11.1
	 *
	 * @return void
	 */
	public static function consume() {
		$user_id = \get_current_user_id();
		if ( $user_id ) {
			\delete_transient( self::TRANSIENT_PREFIX . $user_id );
		}
	}

	/**
	 * Return the known keys, each as a string.
	 *
	 * @since 2.11.1
	 *
	 * @param array<string,mixed> $tokens Raw token map.
	 *
	 * @return array<string,string>
	 */
	private static function normalize( array $tokens ) {
		$normalized = array();

		foreach ( self::TOKEN_KEYS as $key ) {
			$value              = ( isset( $tokens[ $key ] ) && \is_string( $tokens[ $key ] ) ) ? $tokens[ $key ] : '';
			$normalized[ $key ] = $value;
		}

		return $normalized;
	}
}
