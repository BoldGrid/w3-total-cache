<?php
/**
 * File: Util_Nonce.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class: Util_Nonce
 *
 * Centralised nonce-verification helpers for W3 Total Cache.
 *
 * Historically, the plugin shared a single nonce action string `'w3tc'` across
 * roughly 60 minters (`wp_create_nonce( 'w3tc' )`) and ~10 verifiers
 * (`wp_verify_nonce( $n, 'w3tc' )` / `check_ajax_referer( 'w3tc', ... )`).
 *
 * That made the `'w3tc'` token a bearer credential: a nonce minted for one
 * surface (e.g. a CDN test popup) was valid for every other admin-action and
 * AJAX dispatcher entry point. A value visible in an HTML
 * `class="... nonce: 'X' ..."` attribute or a localized `w3tc_nonce` global
 * could be reused against unrelated handlers.
 *
 * The fix is to scope nonces per handler. To avoid breaking the (very large)
 * minter surface in a single drop, the verifier helpers below accept the new
 * per-action key as the primary, AND the legacy `'w3tc'` token as a
 * back-compat fallback. Capability checks added by the prior
 * `missing-auth-capability-checks` group already enforce `manage_options` on
 * every public surface, so the legacy fallback only widens the
 * cross-action-replay window for already-authorised admins -- not
 * unauthenticated users. The fallback SHOULD be removed in a follow-up
 * release once the minter surface is fully migrated to per-action nonces.
 *
 * TODO(follow-up release): flip the `$allow_legacy` default to `false` and
 * remove the LEGACY_ACTION branch in verify_admin() once every
 * `wp_create_nonce('w3tc')` minter (~60 call sites across admin views,
 * Util_Ui::nonce_field(), Util_AdminLinks, inc/options/extensions/list.php,
 * etc.) has been migrated to per-action keys. Until then per-action
 * enforcement is best-effort: existing minters still produce LEGACY_ACTION
 * tokens and pass via the fallback, so the practical security improvement is
 * only realised once the minters move too.
 *
 * @since X.X.X
 */
class Util_Nonce {

	/**
	 * Legacy nonce action used as a back-compat fallback.
	 *
	 * @since X.X.X
	 *
	 * @var string
	 */
	const LEGACY_ACTION = 'w3tc';

	/**
	 * Read the nonce value from $_REQUEST as a scalar string.
	 *
	 * Layer 3 of the nonce-verification pass: defends against array-shape
	 * (`_wpnonce[]=foo` causing `wp_verify_nonce` to receive an array and
	 * short-circuit through type juggling).
	 *
	 * @since X.X.X
	 *
	 * @param string $field Request field name. Default `_wpnonce`.
	 *
	 * @return string Empty string when the field is absent or non-scalar.
	 */
	public static function read_nonce( $field = '_wpnonce' ) {
		$raw = isset( $_REQUEST[ $field ] ) ? $_REQUEST[ $field ] : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wp_unslash + sanitize_text_field applied after the is_scalar() guard below.
		if ( ! is_scalar( $raw ) ) {
			return '';
		}
		$value = \sanitize_text_field( \wp_unslash( (string) $raw ) );
		return is_string( $value ) ? $value : '';
	}

	/**
	 * Verify an admin-action nonce.
	 *
	 * Accepts the per-action key as the primary nonce action. When
	 * `$allow_legacy` is true (default), the legacy shared `'w3tc'` action
	 * is also accepted for back-compat with admin tabs opened before the
	 * deploy. Caller MUST still enforce `current_user_can()` separately --
	 * a passing nonce never authorises by itself.
	 *
	 * @since X.X.X
	 *
	 * @param string $action       Per-action nonce key (e.g. `w3tc_extension_activate_<slug>`).
	 * @param string $field        Request field name. Default `_wpnonce`.
	 * @param bool   $allow_legacy Accept the legacy `'w3tc'` action as a fallback.
	 *
	 * @return bool True when verification passes against either the primary
	 *              or (if enabled) legacy action.
	 */
	public static function verify_admin( $action, $field = '_wpnonce', $allow_legacy = true ) {
		$nonce = self::read_nonce( $field );
		if ( '' === $nonce ) {
			return false;
		}
		if ( \wp_verify_nonce( $nonce, $action ) ) {
			return true;
		}
		if ( $allow_legacy && self::LEGACY_ACTION !== $action ) {
			return (bool) \wp_verify_nonce( $nonce, self::LEGACY_ACTION );
		}
		return false;
	}

	/**
	 * Verify an AJAX nonce, mirroring `check_ajax_referer()` semantics.
	 *
	 * On failure, terminates with HTTP 403 via `wp_die()` (same as
	 * `check_ajax_referer( $action, $field, true )`).
	 *
	 * @since X.X.X
	 *
	 * @param string $action       Per-action nonce key.
	 * @param string $field        Request field name. Default `_wpnonce`.
	 * @param bool   $allow_legacy Accept the legacy `'w3tc'` action as a fallback.
	 *
	 * @return bool True on success. Calls `wp_die()` on failure.
	 */
	public static function verify_ajax( $action, $field = '_wpnonce', $allow_legacy = true ) {
		if ( self::verify_admin( $action, $field, $allow_legacy ) ) {
			return true;
		}
		/**
		 * `wp_die`'s second arg is the page title, not the HTTP status. Pass
		 * the status via the `$args` array so the response is an actual 403
		 * (consistent with the other access-control failure paths in this
		 * pass).
		 *
		 * Body is the legacy WordPress AJAX-failure sentinel `-1` so existing
		 * jQuery `.fail()` handlers (and `check_ajax_referer`-shaped callers)
		 * keep working. Detailed reasons go to the server log only -- echoing
		 * them in the response would tell a caller which nonce key a handler
		 * expects, which is a fingerprinting signal worth keeping off the
		 * wire.
		 */
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				sprintf( '[W3TC] Util_Nonce::verify_ajax failed for action "%s".', $action )
			);
		}
		\wp_die( -1, '', array( 'response' => 403 ) );
	}
}
