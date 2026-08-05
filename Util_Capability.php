<?php
/**
 * File: Util_Capability.php
 *
 * Filterable cache-purge capability helpers. Default remains manage_options;
 * settings, config save, extensions, and AJAX stay floored elsewhere.
 *
 * @package W3TC
 * @since   2.10.4
 */

namespace W3TC;

/**
 * Class Util_Capability
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
	 * Non-string or empty values fall back to manage_options.
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
	 * Applies `w3tc_capability_admin_bar`, then `w3tc_capability_flush_all`,
	 * then `w3tc_capability_favorite_action_flush_all`.
	 *
	 * @since 2.10.4
	 *
	 * @return string
	 */
	public static function flush_all_capability() {
		/**
		 * Legacy admin-bar base capability.
		 *
		 * @param string $capability Capability slug.
		 */
		$capability = \apply_filters( 'w3tc_capability_admin_bar', 'manage_options' );

		/**
		 * Filters the capability required to purge all caches.
		 *
		 * @since 2.10.4
		 *
		 * @param string $capability Capability slug.
		 */
		$capability = \apply_filters( 'w3tc_capability_flush_all', $capability );

		/**
		 * Legacy favorite-action capability filter.
		 *
		 * @param string $capability Capability slug.
		 */
		$capability = \apply_filters( 'w3tc_capability_favorite_action_flush_all', $capability );

		return self::sanitize_capability( $capability );
	}

	/**
	 * Capability required to purge a post/page (or current page).
	 *
	 * Applies `w3tc_capability_admin_bar`, then `w3tc_capability_flush_post`,
	 * then `w3tc_capability_row_action_w3tc_flush_post`.
	 *
	 * @since 2.10.4
	 *
	 * @return string
	 */
	public static function flush_post_capability() {
		/**
		 * Legacy admin-bar base capability.
		 *
		 * @param string $capability Capability slug.
		 */
		$capability = \apply_filters( 'w3tc_capability_admin_bar', 'manage_options' );

		/**
		 * Filters the capability required to purge a specific post/page.
		 *
		 * @since 2.10.4
		 *
		 * @param string $capability Capability slug.
		 */
		$capability = \apply_filters( 'w3tc_capability_flush_post', $capability );

		/**
		 * Legacy row-action capability filter.
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
	 * Requires the filtered flush-post capability and edit_post for the id.
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
	 * Capability for the Performance admin-bar parent for purge-only users.
	 *
	 * Uses a purge cap the current user already satisfies so flush-all-only
	 * grants still show the parent item.
	 *
	 * @since 2.10.4
	 *
	 * @return string
	 */
	public static function admin_bar_parent_capability() {
		if ( self::can_flush_all() ) {
			return self::flush_all_capability();
		}

		if ( self::can_flush_post() ) {
			return self::flush_post_capability();
		}

		return 'manage_options';
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
	 * Whether a filtered admin-bar item is safe for purge-only non-admins.
	 *
	 * Requires an allowlisted id and (except the parent) an href that targets
	 * a filterable purge action — extensions may reuse an allowlisted id with
	 * a dashboard-only link.
	 *
	 * @since 2.10.4
	 *
	 * @param array $item Admin-bar menu item.
	 *
	 * @return bool
	 */
	public static function is_allowed_purge_admin_bar_item( array $item ) {
		if ( ! isset( $item['id'] ) || ! \in_array( $item['id'], self::PURGE_ADMIN_BAR_IDS, true ) ) {
			return false;
		}

		if ( 'w3tc' === $item['id'] ) {
			return true;
		}

		if ( empty( $item['href'] ) || ! \is_string( $item['href'] ) ) {
			return false;
		}

		foreach ( self::PURGE_ACTIONS as $action ) {
			if ( false !== \strpos( $item['href'], $action ) ) {
				return true;
			}
		}

		return false;
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

		if ( 'w3tc_flush_post' === $action ) {
			$post_id = Util_Request::get_integer( 'post_id' );
			if ( $post_id <= 0 ) {
				$post_id = (int) Util_Environment::detect_post_id();
			}

			return self::can_flush_post_id( $post_id );
		}

		return self::can_flush_current_page_request();
	}

	/**
	 * Authorize w3tc_flush_current_page for a same-host URL target.
	 *
	 * Flush-all may clear any same-host URL. Flush-post requires edit_post on
	 * the post resolved from the URL.
	 *
	 * @since 2.10.4
	 *
	 * @return bool
	 */
	public static function can_flush_current_page_request() {
		$url = Util_Request::get_string( 'url' );
		if ( '' === $url && isset( $_SERVER['HTTP_REFERER'] ) ) {
			$url = \sanitize_text_field( \wp_unslash( $_SERVER['HTTP_REFERER'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized immediately; referer fallback for URL flush.
		}

		if ( '' === $url ) {
			return false;
		}

		$validated = \wp_validate_redirect( $url, false );
		if ( ! $validated ) {
			return false;
		}

		if ( self::can_flush_all() ) {
			return true;
		}

		$post_id = (int) \url_to_postid( $validated );
		if ( $post_id <= 0 ) {
			return false;
		}

		return self::can_flush_post_id( $post_id );
	}

	/**
	 * Build a nonce-protected admin URL for a purge action (no W3TC page).
	 *
	 * @since 2.10.4
	 *
	 * @param string              $action Dispatcher handler key.
	 * @param array<string,mixed> $args   Extra query args (e.g. post_id).
	 *
	 * @return string
	 */
	public static function purge_action_url( $action, array $args = array() ) {
		$query        = \array_merge( array( $action => '1' ), $args );
		$query_string = \http_build_query( $query, '', '&' );
		$url          = Util_Ui::admin_url( 'admin.php?' . $query_string );

		return Util_Nonce::admin_nonce_url( $url, $action );
	}
}
