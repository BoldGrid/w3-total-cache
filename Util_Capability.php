<?php
/**
 * File: Util_Capability.php
 *
 * Filterable cache-purge capability helpers. Default remains manage_options;
 * settings, config save, extensions, and AJAX stay floored elsewhere.
 *
 * @package W3TC
 * @since   X.X.X
 */

namespace W3TC;

/**
 * Class Util_Capability
 *
 * @since X.X.X
 */
class Util_Capability {

	/**
	 * Admin-action keys that may use filterable purge caps.
	 *
	 * @since X.X.X
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
	 * @since X.X.X
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
	 * @since X.X.X
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
	 * @since X.X.X
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
		 * @since X.X.X
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
	 * @since X.X.X
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
		 * @since X.X.X
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
	 * @since X.X.X
	 *
	 * @return bool
	 */
	public static function can_flush_all() {
		return \current_user_can( self::flush_all_capability() );
	}

	/**
	 * Whether the current user may purge posts/pages (role-level gate).
	 *
	 * @since X.X.X
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
	 * @since X.X.X
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
	 * @since X.X.X
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
	 * @since X.X.X
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
	 * @since X.X.X
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
	 * @since X.X.X
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
			if ( $post_id > 0 ) {
				return self::can_flush_post_id( $post_id );
			}

			return self::can_flush_post();
		}

		return self::can_flush_post();
	}

	/**
	 * Build a nonce-protected admin URL for a purge action (no W3TC page).
	 *
	 * @since X.X.X
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
