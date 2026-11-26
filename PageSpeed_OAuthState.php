<?php
/**
 * File: PageSpeed_OAuthState.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class PageSpeed_OAuthState
 *
 * Server-side OAuth state binding for the Google PageSpeed Insights
 * authorization flow.
 *
 * The Authorize button sends the browser through the W3 API proxy, which
 * redirects back to the General Settings `return_url` with
 * `w3tc_access_token` and `w3tc_pagespeed_key` query parameters. Those values
 * are written into `widget.pagespeed.*` config, so the return must be tied to
 * an authorization that the current admin started.
 *
 * {@see self::issue()} mints a per-user token that is embedded in the
 * `return_url`, {@see self::verify()} checks it on return, and
 * {@see self::consume()} makes it single-use.
 *
 * @since 2.11.1
 */
class PageSpeed_OAuthState {
	/**
	 * Transient key prefix. Concatenated with the current `user_id`.
	 *
	 * @since 2.11.1
	 *
	 * @var string
	 */
	const TRANSIENT_PREFIX = 'w3tc_pagespeed_oauth_state_';

	/**
	 * Transient TTL in seconds.
	 *
	 * @since 2.11.1
	 *
	 * @var int
	 */
	const TRANSIENT_TTL = 900;

	/**
	 * Query-string parameter that carries the state token through the
	 * return_url, proxy, and callback redirect chain.
	 *
	 * @since 2.11.1
	 *
	 * @var string
	 */
	const STATE_PARAM = 'w3tc_pagespeed_state';

	/**
	 * Nonce action for the Deauthorize link.
	 *
	 * @since 2.11.1
	 *
	 * @var string
	 */
	const DEAUTHORIZE_NONCE_ACTION = 'w3tc_pagespeed_deauthorize';

	/**
	 * Return the pending state token for the current user, minting one when
	 * none is stored.
	 *
	 * The Authorize link is rendered on every General Settings load, so an
	 * existing token is reused and its TTL refreshed. Otherwise a second tab
	 * or an unrelated settings save would invalidate an authorization that is
	 * still in progress.
	 *
	 * @since 2.11.1
	 *
	 * @return string Token (32 alphanumeric chars) or '' when no user.
	 */
	public static function issue() {
		$user_id = \get_current_user_id();
		if ( ! $user_id ) {
			return '';
		}
		$key   = self::TRANSIENT_PREFIX . $user_id;
		$token = \get_transient( $key );
		if ( ! \is_string( $token ) || '' === $token ) {
			$token = \wp_generate_password( 32, false, false );
		}
		\set_transient( $key, $token, self::TRANSIENT_TTL );
		return $token;
	}

	/**
	 * Constant-time check that `$submitted` matches the token stored for the
	 * current user.
	 *
	 * @since 2.11.1
	 *
	 * @param mixed $submitted The submitted state token.
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
	 * Delete the stored token so the same state cannot be replayed.
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
}
