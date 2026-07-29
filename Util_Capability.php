<?php
/**
 * File: Util_Capability.php
 *
 * Filterable purge capabilities (ENG7-4564). Secure by default
 * (`manage_options`); developers may lower the required cap via filters.
 *
 * @package W3TC
 * @since   2.10.4
 */

namespace W3TC;

/**
 * Class Util_Capability
 *
 * Central helpers for cache-purge authorization. Settings, config save,
 * extensions, and AJAX remain hard-floored at `manage_options` elsewhere.
 *
 * @since 2.10.4
 */
class Util_Capability {

	/**
	 * Admin-action keys that may use filterable purge caps.
	 *
	 * @since 2.10.4
	 *
	 * @var string[]
	 */
	const PURGE_ACTIONS = array(
		'w3tc_flush_all',
		'w3tc_flush_post',
		'w3tc_flush_current_page',
	);

	/**
	 * Admin-bar menu item ids allowed for non-manage_options purge users.
	 *
	 * @since 2.10.4
	 *
	 * @var string[]
	 */
	const PURGE_ADMIN_BAR_IDS = array(
		'w3tc',
		'w3tc_flush_all',
		'w3tc_flush_current_page',
	);

	/**
	 * Sanitize a filtered capability value.
	 *
	 * Invalid returns (non-string, empty) fall back to manage_options.
	 * Any non-empty string is honored (including low caps such as `read`).
	 *
	 * @since 2.10.4
	 *
	 * @param mixed $capability Filtered capability.
	 *
	 * @return string
	 */
	public static function sanitize_capability( $capability ) {
		if ( ! \is_string( $capability ) || '' === $capability ) {
			return 'manage_options';
		}

		return $capability;
	}

	/**
	 * Capability required to purge all caches.
	 *
	 * Filter order: `w3tc_capability_admin_bar` (legacy base), then
	 * `w3tc_capability_flush_all`, then
	 * `w3tc_capability_favorite_action_flush_all`. Default `manage_options`.
	 *
	 * @since 2.10.4
	 *
	 * @return string
	 */
	public static function flush_all_capability() {
		/**
		 * Legacy admin-bar base capability (pre-2.10.4).
		 *
		 * @param string $capability Capability slug.
		 */
		$capability = \apply_filters( 'w3tc_capability_admin_bar', 'manage_options' );

		/**
		 * Filter the capability required to purge all caches.
		 *
		 * Default is manage_options (or the admin-bar base if filtered).
		 * Returning a lower capability (e.g. edit_posts) is supported for
		 * agency workflows but is not recommended for untrusted roles.
		 *
		 * @since 2.10.4
		 *
		 * @param string $capability Capability slug.
		 */
		$capability = \apply_filters( 'w3tc_capability_flush_all', $capability );

		/**
		 * Legacy favorite-action filter (pre-2.10.4).
		 *
		 * @param string $capability Capability slug.
		 */
		$capability = \apply_filters( 'w3tc_capability_favorite_action_flush_all', $capability );

		return self::sanitize_capability( $capability );
	}

	/**
	 * Capability required to purge a post/page (or current page).
	 *
	 * Filter order: `w3tc_capability_admin_bar` (legacy base), then
	 * `w3tc_capability_flush_post`, then
	 * `w3tc_capability_row_action_w3tc_flush_post`. Default `manage_options`.
	 *
	 * @since 2.10.4
	 *
	 * @return string
	 */
	public static function flush_post_capability() {
		/**
		 * Legacy admin-bar base capability (pre-2.10.4).
		 *
		 * @param string $capability Capability slug.
		 */
		$capability = \apply_filters( 'w3tc_capability_admin_bar', 'manage_options' );

		/**
		 * Filter the capability required to purge a specific post/page.
		 *
		 * @since 2.10.4
		 *
		 * @param string $capability Capability slug.
		 */
		$capability = \apply_filters( 'w3tc_capability_flush_post', $capability );

		/**
		 * Legacy row-action filter (pre-2.10.4).
		 *
		 * @param string $capability Capability slug.
		 */
		$capability = \apply_filters( 'w3tc_capability_row_action_w3tc_flush_post', $capability );

		return self::sanitize_capability( $capability );
	}

	/**
	 * Whether the current user may purge all caches.
	 *
	 * @since 2.10.4
	 *
	 * @return bool
	 */
	public static function can_flush_all() {
		return \current_user_can( self::flush_all_capability() );
	}

	/**
	 * Whether the current user may purge posts/pages (role-level gate).
	 *
	 * @since 2.10.4
	 *
	 * @return bool
	 */
	public static function can_flush_post() {
		return \current_user_can( self::flush_post_capability() );
	}

	/**
	 * Whether the current user may purge a specific post.
	 *
	 * Requires the filtered flush-post capability and `edit_post` for the id.
	 *
	 * @since 2.10.4
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return bool
	 */
	public static function can_flush_post_id( $post_id ) {
		$post_id = (int) $post_id;

		if ( ! self::can_flush_post() ) {
			return false;
		}

		if ( $post_id <= 0 ) {
			return false;
		}

		return \current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Whether the current user has any purge privilege.
	 *
	 * @since 2.10.4
	 *
	 * @return bool
	 */
	public static function user_can_purge_anything() {
		return self::can_flush_all() || self::can_flush_post();
	}

	/**
	 * Whether an admin-action key is a filterable purge action.
	 *
	 * @since 2.10.4
	 *
	 * @param string $action Dispatcher handler key.
	 *
	 * @return bool
	 */
	public static function is_purge_action( $action ) {
		return \in_array( $action, self::PURGE_ACTIONS, true );
	}

	/**
	 * Whether the current user may execute a purge admin-action.
	 *
	 * @since 2.10.4
	 *
	 * @param string $action Dispatcher handler key.
	 *
	 * @return bool
	 */
	public static function can_execute_purge( $action ) {
		if ( ! self::is_purge_action( $action ) ) {
			return false;
		}

		if ( 'w3tc_flush_all' === $action ) {
			return self::can_flush_all();
		}

		// w3tc_flush_post and w3tc_flush_current_page.
		if ( 'w3tc_flush_post' === $action ) {
			$post_id = Util_Request::get_integer( 'post_id' );
			if ( $post_id > 0 ) {
				return self::can_flush_post_id( $post_id );
			}

			return self::can_flush_post();
		}

		// Current page by URL — role-level flush-post cap only.
		return self::can_flush_post();
	}

	/**
	 * Build a nonce-protected admin URL for a purge action (no W3TC page).
	 *
	 * Works for purge-capable non-admins who cannot access w3tc_dashboard.
	 *
	 * @since 2.10.4
	 *
	 * @param string               $action Dispatcher handler key.
	 * @param array<string,mixed>  $args   Extra query args (e.g. post_id).
	 *
	 * @return string
	 */
	public static function purge_action_url( $action, array $args = array() ) {
		$query         = \array_merge( array( $action => '1' ), $args );
		$query_string  = \http_build_query( $query, '', '&' );
		$url           = Util_Ui::admin_url( 'admin.php?' . $query_string );

		return Util_Nonce::admin_nonce_url( $url, $action );
	}

}
