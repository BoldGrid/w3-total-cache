<?php
/**
 * File: Cdn_GoogleDrive_OAuthState.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Cdn_GoogleDrive_OAuthState
 *
 * Server-side OAuth state binding for the Google Drive CDN integration.
 *
 * The Google Drive OAuth flow routes the browser through an external
 * `api.w3-edge.com` proxy which, on completion, redirects back to the
 * site's `return_url` with `oa_*` query parameters (`oa_client_id`,
 * `oa_access_token`, `oa_refresh_token`, …). Those values are then
 * presented to an authenticated admin via the auth-return popup and,
 * on submit, written into `cdn.google_drive.*` config.
 *
 * Without a session-bound state token, an attacker who already holds
 * a set of valid Google OAuth tokens for an attacker-owned Drive
 * account can craft a URL such as
 *
 *   /wp-admin/admin.php?page=w3tc_cdn
 *     &oa_client_id=ATTACKER
 *     &oa_access_token=ATTACKER
 *     &oa_refresh_token=ATTACKER
 *
 * and lure an authenticated admin to it. The admin's session has
 * access to the page's `_wpnonce`, the popup auto-opens, the admin
 * sees the attacker's Google Drive folder listing, picks a folder,
 * and the POST writes the attacker's credentials into the site's
 * `cdn.google_drive.*` config — the site now uploads CDN content to
 * the attacker's Drive.
 *
 * The fix binds the OAuth round-trip to the current admin's session:
 *
 *  1. {@see self::issue()} mints a 32-char alphanumeric token, stores
 *     it in a transient keyed by `user_id`, and returns the token to
 *     the caller. The caller embeds the token in the `return_url`
 *     query string (the external proxy preserves the return_url's
 *     query string when redirecting back).
 *
 *  2. On callback, the `oa_*` parameters arrive together with the
 *     `w3tc_gdrive_state` parameter. {@see self::verify()} compares
 *     the submitted token against the stored value for the current
 *     user using `hash_equals()` (constant-time).
 *
 *  3. {@see self::consume()} deletes the transient after a successful
 *     `auth_set` so the token is single-use.
 *
 * The TTL is short (15 minutes) because a legitimate OAuth round-trip
 * completes in seconds; anything longer is a stale tab or an attack.
 *
 * @since 2.10.0
 */
class Cdn_GoogleDrive_OAuthState {
	/**
	 * Transient key prefix. Concatenated with the current `user_id`
	 * to scope the state token to a single admin session.
	 *
	 * @since 2.10.0
	 *
	 * @var string
	 */
	const TRANSIENT_PREFIX = 'w3tc_gdrive_oauth_state_';

	/**
	 * Transient TTL in seconds. 15 minutes is generous for a single
	 * OAuth round-trip (Google's consent flow plus the proxy hop
	 * completes in < 30s) and bounds replay window for stolen state.
	 *
	 * @since 2.10.0
	 *
	 * @var int
	 */
	const TRANSIENT_TTL = 900;

	/**
	 * Query-string parameter that carries the state token end-to-end
	 * through the return_url → proxy → callback redirect chain, and
	 * into the auth-set form POST as a hidden input.
	 *
	 * @since 2.10.0
	 *
	 * @var string
	 */
	const STATE_PARAM = 'w3tc_gdrive_state';

	/**
	 * Mint a new state token bound to the current user and store it
	 * in a per-user transient. Returns the token, or an empty string
	 * if no user is logged in (in which case the caller should not
	 * embed the parameter — there is no session to bind to and the
	 * OAuth flow itself requires `manage_options`).
	 *
	 * @since 2.10.0
	 *
	 * @return string Token (32 alphanumeric chars) or '' when no user.
	 */
	public static function issue() {
		$user_id = \get_current_user_id();
		if ( ! $user_id ) {
			return '';
		}
		$token = \wp_generate_password( 32, false, false );
		\set_transient( self::TRANSIENT_PREFIX . $user_id, $token, self::TRANSIENT_TTL );
		return $token;
	}

	/**
	 * Constant-time check that `$submitted` matches the token stored
	 * for the current user. Returns false on any of: no user, no
	 * stored token (expired or never issued), submitted is empty /
	 * non-string, mismatch.
	 *
	 * Does NOT delete the transient — the popup-render path calls
	 * verify() then renders the folder picker, and the auth-set POST
	 * also calls verify() (then consume()). Calling delete here would
	 * break the multi-step flow.
	 *
	 * @since 2.10.0
	 *
	 * @param mixed $submitted The submitted state token (typically
	 *                          from `Util_Request::get_string()`).
	 *
	 * @return bool
	 */
	public static function verify( $submitted ) {
		$user_id = \get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}
		if ( ! \is_string( $submitted ) || '' === $submitted ) {
			return false;
		}
		$stored = \get_transient( self::TRANSIENT_PREFIX . $user_id );
		if ( ! \is_string( $stored ) || '' === $stored ) {
			return false;
		}
		return \hash_equals( $stored, $submitted );
	}

	/**
	 * Single-use consume — delete the stored token so a replay of the
	 * same state value cannot succeed. Called from the auth_set
	 * handler after a successful config write.
	 *
	 * @since 2.10.0
	 *
	 * @return void
	 */
	public static function consume() {
		$user_id = \get_current_user_id();
		if ( $user_id ) {
			\delete_transient( self::TRANSIENT_PREFIX . $user_id );
		}
	}
}
