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
 * Centralised nonce minting and verification for W3 Total Cache admin
 * dispatcher handlers and AJAX hub sub-actions.
 *
 * Admin handlers mint and verify against `w3tc_admin_action_{handler}`
 * keys (see {@see self::admin_action()}). AJAX hub calls mint and verify
 * against `w3tc_ajax_{sub_action}` keys (see {@see self::ajax_action()}).
 *
 * The legacy shared `'w3tc'` action remains available only when callers pass
 * `$allow_legacy = true` to {@see self::verify_admin()} or
 * {@see self::verify_ajax()}. New minters should use
 * {@see self::create_admin()} / {@see self::create_ajax()} and admin JS
 * should read tokens from the localized `w3tc_admin_nonces` /
 * `w3tc_ajax_nonces` maps via `pub/js/w3tc-nonce.js`.
 *
 * @since 2.10.0
 */
class Util_Nonce {

	/**
	 * Legacy nonce action used as a back-compat fallback.
	 *
	 * Remaining `wp_nonce_url( ..., 'w3tc' )` minters not yet on per-handler keys
	 * still mint this action; pass `$allow_legacy = true` to verify those tokens.
	 *
	 * @since 2.10.0
	 *
	 * @var string
	 */
	const LEGACY_ACTION = 'w3tc';

	/**
	 * Build the admin-action nonce key for a dispatcher handler.
	 *
	 * @since 2.10.0
	 *
	 * @param string $action Handler request key (e.g. `w3tc_flush_all`).
	 *
	 * @return string
	 */
	public static function admin_action( $action ) {
		if ( 0 === \strpos( $action, 'w3tc_admin_action_' ) ) {
			return $action;
		}

		return 'w3tc_admin_action_' . $action;
	}

	/**
	 * Build the AJAX hub nonce key for a sub-action.
	 *
	 * @since 2.10.0
	 *
	 * @param string $action AJAX sub-action (e.g. `faq`).
	 *
	 * @return string
	 */
	public static function ajax_action( $action ) {
		if ( 0 === \strpos( $action, 'w3tc_ajax_' ) ) {
			return $action;
		}

		return 'w3tc_ajax_' . $action;
	}

	/**
	 * Mint a nonce for an admin dispatcher handler.
	 *
	 * @since 2.10.0
	 *
	 * @param string $action Handler request key.
	 *
	 * @return string
	 */
	public static function create_admin( $action ) {
		return \wp_create_nonce( self::admin_action( $action ) );
	}

	/**
	 * Mint a nonce for a `w3tc_ajax` hub sub-action.
	 *
	 * @since 2.10.0
	 *
	 * @param string $action AJAX sub-action.
	 *
	 * @return string
	 */
	public static function create_ajax( $action ) {
		return \wp_create_nonce( self::ajax_action( $action ) );
	}

	/**
	 * Append a per-handler admin nonce to a URL.
	 *
	 * @since 2.10.0
	 *
	 * @param string $url    Admin URL (relative or absolute).
	 * @param string $action Dispatcher handler request key.
	 *
	 * @return string
	 */
	public static function admin_nonce_url( $url, $action ) {
		return \wp_nonce_url( $url, self::admin_action( $action ) );
	}

	/**
	 * Mint admin nonces for a list of dispatcher handler keys.
	 *
	 * @since 2.10.0
	 *
	 * @param string[] $actions Handler request keys.
	 *
	 * @return array<string,string> Map of action => nonce.
	 */
	public static function create_admin_map( array $actions ) {
		$map = array();

		foreach ( $actions as $action ) {
			if ( ! \is_string( $action ) || '' === $action ) {
				continue;
			}
			$map[ $action ] = self::create_admin( $action );
		}

		return $map;
	}

	/**
	 * Mint AJAX hub nonces for a list of sub-actions.
	 *
	 * @since 2.10.0
	 *
	 * @param string[] $actions AJAX sub-actions.
	 *
	 * @return array<string,string> Map of action => nonce.
	 */
	public static function create_ajax_map( array $actions ) {
		$map = array();

		foreach ( $actions as $action ) {
			if ( ! \is_string( $action ) || '' === $action ) {
				continue;
			}
			$map[ $action ] = self::create_ajax( $action );
		}

		return $map;
	}

	/**
	 * Register the shared admin nonce script handle.
	 *
	 * @since 2.10.0
	 *
	 * @return void
	 */
	public static function register_script() {
		if ( \wp_script_is( 'w3tc-nonce', 'registered' ) ) {
			return;
		}

		// Dashboard widgets and admin notices may enqueue before load_scripts() runs.
		\wp_register_script(
			'w3tc-nonce',
			\plugins_url( 'pub/js/w3tc-nonce.js', W3TC_FILE ),
			array( 'jquery' ),
			W3TC_VERSION,
			true
		);
	}

	/**
	 * Enqueue the nonce script with AJAX sub-action nonces.
	 *
	 * @since 2.10.0
	 *
	 * @param string[] $actions AJAX sub-actions.
	 *
	 * @return void
	 */
	public static function enqueue_ajax_nonces( array $actions ) {
		self::register_script();
		\wp_enqueue_script( 'w3tc-nonce' );
		\wp_localize_script(
			'w3tc-nonce',
			'w3tc_ajax_nonces',
			self::create_ajax_map( $actions )
		);
	}

	/**
	 * Enqueue the nonce script with admin dispatcher nonces.
	 *
	 * @since 2.10.0
	 *
	 * @param string[] $actions Admin handler request keys.
	 *
	 * @return void
	 */
	public static function enqueue_admin_nonces( array $actions ) {
		self::register_script();
		\wp_enqueue_script( 'w3tc-nonce' );
		\wp_localize_script(
			'w3tc-nonce',
			'w3tc_admin_nonces',
			self::create_admin_map( $actions )
		);
	}

	/**
	 * AJAX hub sub-actions that mint dedicated nonces in admin JS.
	 *
	 * @since 2.10.0
	 *
	 * @return string[]
	 */
	public static function known_ajax_actions() {
		return array(
			'browsercache_quick_reference',
			'faq',
			'get_notices',
			'dismiss_notice',
			'ustats_get',
			'pagespeed_data',
			'pagespeed_widgetdata',
			'objectcache_diskpopup',
			'minify_help',
			'newrelic_widgetdata_basic',
			'newrelic_widgetdata_pageloads',
			'newrelic_widgetdata_webtransactions',
			'newrelic_widgetdata_dbtimes',
			'newrelic_popup',
			'newrelic_list_applications',
			'newrelic_apply_configuration',
			'cdn_bunnycdn_intro',
			'cdn_bunnycdn_list_pull_zones',
			'cdn_bunnycdn_configure_pull_zone',
			'cdn_bunnycdn_deauthorization',
			'cdn_bunnycdn_deauthorize',
			'cdn_bunnycdn_purge_url',
			'cdn_bunnycdn_widgetdata',
			'cdn_bunnycdn_fsd_intro',
			'cdn_bunnycdn_fsd_list_pull_zones',
			'cdn_bunnycdn_fsd_configure_pull_zone',
			'cdn_bunnycdn_fsd_deauthorization',
			'cdn_bunnycdn_fsd_deauthorize',
			'cdn_cloudfront_fsd_intro',
			'cdn_cloudfront_fsd_list_distributions',
			'cdn_cloudfront_fsd_view_distribution',
			'cdn_cloudfront_fsd_configure_distribution',
			'cdn_cloudfront_fsd_configure_distribution_skip',
			'cdn_rackspace_intro',
			'cdn_rackspace_intro_done',
			'cdn_rackspace_regions_done',
			'cdn_rackspace_services_done',
			'cdn_rackspace_service_create_done',
			'cdn_rackspace_service_get_state',
			'cdn_rackspace_service_created_done',
			'cdn_rackspace_service_actualize_done',
			'cdn_rackspace_configure_domains',
			'cdn_rackspace_configure_domains_done',
			'cdn_rackspace_authenticate',
			'cdn_rackspace_containers_done',
			'extension_cloudflare_intro',
			'extension_cloudflare_intro_done',
			'extension_cloudflare_zones_done',
			'extension_alwayscached_process_queue_item',
			'extension_alwayscached_queue',
			'extension_alwayscached_queue_filter',
		);
	}

	/**
	 * Read the nonce value from $_REQUEST as a scalar string.
	 *
	 * Layer 3 of the nonce-verification pass: defends against array-shape
	 * (`_wpnonce[]=foo` causing `wp_verify_nonce` to receive an array and
	 * short-circuit through type juggling).
	 *
	 * @since 2.10.0
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
		$w3tc_value = \sanitize_text_field( \wp_unslash( (string) $raw ) );
		return is_string( $w3tc_value ) ? $w3tc_value : '';
	}

	/**
	 * Verify an admin-action nonce.
	 *
	 * Accepts the per-action key as the primary nonce action. When
	 * `$allow_legacy` is true, the legacy shared `'w3tc'` action is also
	 * accepted. Caller MUST still enforce `current_user_can()` separately --
	 * a passing nonce never authorises by itself.
	 *
	 * @since 2.10.0
	 *
	 * @param string $action       Per-action nonce key (e.g. `w3tc_extension_activate_<slug>`).
	 * @param string $field        Request field name. Default `_wpnonce`.
	 * @param bool   $allow_legacy Accept the legacy `'w3tc'` action as a fallback.
	 *
	 * @return bool True when verification passes against either the primary
	 *              or (if enabled) legacy action.
	 */
	public static function verify_admin( $action, $field = '_wpnonce', $allow_legacy = false ) {
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
	 * @since 2.10.0
	 *
	 * @param string $action       Per-action nonce key.
	 * @param string $field        Request field name. Default `_wpnonce`.
	 * @param bool   $allow_legacy Accept the legacy `'w3tc'` action as a fallback.
	 *
	 * @return bool True on success. Calls `wp_die()` on failure.
	 */
	public static function verify_ajax( $action, $field = '_wpnonce', $allow_legacy = false ) {
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
