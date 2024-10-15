<?php
/**
 * File: Util_AttachToActions.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Attaches to wp actions related to content change, which should fire
 * flushes of html content
 */
class Util_AttachToActions {
	/**
	 * Flush actions.
	 *
	 * @return void
	 */
	public static function flush_posts_on_actions() {
		static $attached = false;
		if ( $attached ) {
			return;
		}

		$attached = true;

		$o = new Util_AttachToActions();

		// posts.
		add_action( 'pre_post_update', array( $o, 'on_pre_post_update' ), 0, 2 );
		add_action( 'save_post', array( $o, 'on_post_change' ), 0, 2 );
		add_filter( 'pre_trash_post', array( $o, 'on_post_change' ), 0, 2 );
		add_action( 'before_delete_post', array( $o, 'on_post_change' ), 0, 2 );
		add_action( 'attachment_updated', array( $o, 'on_post_change' ), 0, 2 );

		// comments.
		add_action( 'comment_post', array( $o, 'on_comment_change' ), 0 );
		add_action( 'edit_comment', array( $o, 'on_comment_change' ), 0 );
		add_action( 'delete_comment', array( $o, 'on_comment_change' ), 0 );
		add_action( 'wp_set_comment_status', array( $o, 'on_comment_status' ), 0, 2 );
		add_action( 'trackback_post', array( $o, 'on_comment_change' ), 0 );
		add_action( 'pingback_post', array( $o, 'on_comment_change' ), 0 );

		/**
		 * Fires after the theme is switched.
		 *
		 * See 'after_switch_theme'.
		 *
		 * @since 1.5.0
		 * @since 4.5.0 Introduced the `$old_theme` parameter.
		 *
		 * @see w3tc_flush_posts()
		 *
		 * @link https://developer.wordpress.org/reference/hooks/switch_theme/
		 *
		 * @param string   $new_name  Name of the new theme.
		 * @param WP_Theme $new_theme WP_Theme instance of the new theme.
		 * @param WP_Theme $old_theme WP_Theme instance of the old theme.
		 */
		add_action( 'switch_theme', 'w3tc_flush_posts', 0, 0 );

		/**
		 * Fires after a navigation menu has been successfully updated.
		 *
		 * @since 3.0.0
		 *
		 * @see w3tc_flush_posts()
		 *
		 * @link https://developer.wordpress.org/reference/hooks/wp_update_nav_menu/
		 *
		 * @param int   $menu_id   ID of the updated menu.
		 * @param array $menu_data An array of menu data.
		 */
		add_action( 'wp_update_nav_menu', 'w3tc_flush_posts', 0, 0 );

		/**
		 * Terms.
		 */

		/**
		 * Fires immediately before the given terms are edited (inserted or updated).
		 *
		 * @since 2.9.0
		 * @since 6.1.0 The `$args` parameter was added.
		 *
		 * @see w3tc_flush_posts()
		 *
		 * @link https://developer.wordpress.org/reference/hooks/edited_terms/
		 *
		 * @param int    $term_id  Term ID.
		 * @param string $taxonomy Taxonomy slug.
		 * @param array  $args     Arguments passed to wp_update_term().
		 */
		add_action( 'edited_term', 'w3tc_flush_posts', 0, 0 );

		/**
		 * Fires after a term is deleted from the database and the cache is cleaned.
		 *
		 * The 'delete_$taxonomy' hook is also available for targeting a specific
		 * taxonomy.
		 *
		 * @since 2.5.0
		 * @since 4.5.0 Introduced the `$object_ids` argument.
		 *
		 * @see w3tc_flush_posts()
		 *
		 * @link https://developer.wordpress.org/reference/hooks/delete_term/
		 *
		 * @param int     $term         Term ID.
		 * @param int     $tt_id        Term taxonomy ID.
		 * @param string  $taxonomy     Taxonomy slug.
		 * @param WP_Term $deleted_term Copy of the already-deleted term.
		 * @param array   $object_ids   List of term object IDs.
		 */
		add_action( 'delete_term', 'w3tc_flush_posts', 0, 0 );

		/**
		 * Multisite.
		 */
		if ( Util_Environment::is_wpmu() ) {
			/**
			 * Fires once a site has been deleted from the database.
			 *
			 * @since 5.1.0
			 *
			 * @see w3tc_flush_posts()
			 *
			 * @link https://developer.wordpress.org/reference/hooks/wp_delete_site/
			 *
			 * @param WP_Site $old_site Deleted site object.
			 */
			add_action( 'wp_delete_site', 'w3tc_flush_posts', 0, 0 );

			/**
			 * Fires once a site has been updated in the database.
			 *
			 * @since 5.1.0
			 *
			 * @link https://developer.wordpress.org/reference/hooks/wp_update_site/
			 *
			 * @param WP_Site $new_site New site object.
			 * @param WP_Site $old_site Old site object.
			 */
			add_action( 'wp_update_site', array( $o, 'on_update_site' ), 0, 2 );
		}
	}

	/**
	 * Pre-Post changed action for published post changed to draft which invalidates the published URL.
	 *
	 * @link https://developer.wordpress.org/reference/hooks/pre_post_update/
	 *
	 * @param integer $post_id Post ID.
	 * @param array   $data    Array of unslashed post data.
	 *
	 * @return void
	 */
	public function on_pre_post_update( $post_id, $data = null ) {
		if ( is_null( $data ) ) {
			$data = get_post( $post_id, ARRAY_A );
		}

		// if attachment changed - parent post has to be flushed
		// since there are usually attachments content like title
		// on the page (gallery).
		if ( isset( $data['post_type'] ) && 'attachment' === $data['post_type'] ) {
			$post_id = $data['post_parent'];
			$data    = get_post( $post_id, ARRAY_A );
		}

		if ( ! isset( $data['post_status'] ) || 'draft' !== $data['post_status'] ) {
			return;
		}

		$cacheflush = Dispatcher::component( 'CacheFlush' );
		$cacheflush->flush_post( $post_id );
	}

	/**
	 * Post changed action
	 *
	 * @link https://developer.wordpress.org/reference/hooks/save_post/
	 * @link https://developer.wordpress.org/reference/hooks/pre_trash_post/
	 * @link https://developer.wordpress.org/reference/hooks/before_delete_post/
	 *
	 * @param int|bool $post_id Post ID or a boolean if running on the "pre_trash_post" hook filter.
	 * @param WP_Post  $post    Post.
	 *
	 * @return int|bool|null
	 */
	public function on_post_change( $post_id, $post = null ) {
		if ( is_null( $post ) ) {
			$post = get_post( $post_id );
		}

		$config     = Dispatcher::config();
		$cacheflush = Dispatcher::component( 'CacheFlush' );

		// If the post is an attachment, we need to get and flush its parent post.
		if ( isset( $post->post_type ) && 'attachment' === $post->post_type &&
			Util_Environment::is_flushable_post( get_post( $post->post_parent ), 'posts', $config ) ) {
				$cacheflush->flush_post( $post->post_parent );
		}

		// Check if the original post is flushable.
		if ( Util_Environment::is_flushable_post( $post, 'posts', $config ) ) {
			$cacheflush->flush_post( $post_id );
		}

		return $post_id;
	}

	/**
	 * Comment change action.
	 *
	 * @param integer $comment_id Comment ID.
	 */
	public function on_comment_change( $comment_id ) {
		$post_id = 0;

		if ( $comment_id ) {
			$comment = get_comment( $comment_id, ARRAY_A );
			$post_id = ( ! empty( $comment['comment_post_ID'] ) ? (int) $comment['comment_post_ID'] : 0 );
		}

		$this->on_post_change( $post_id );
	}

	/**
	 * Comment status action fired immediately after transitioning a comment’s status from one to another
	 * in the database and removing the comment, but prior to all status transition hooks.
	 *
	 * @link https://developer.wordpress.org/reference/functions/wp_set_comment_status/
	 *
	 * @param integer $comment_id Comment ID.
	 * @param string  $status Status.
	 */
	public function on_comment_status( $comment_id, $status ) {
		$this->on_comment_change( $comment_id );
	}

	/**
	 * Callback: Flush posts after a multisite blog is updated.
	 *
	 * @since X.X.X
	 *
	 * @link https://developer.wordpress.org/reference/hooks/wp_update_site/
	 * @param \WP_Site $new_site New site object.
	 * @param \WP_Site $old_site Old site object.
	 */
	public function on_update_site( \WP_Site $new_site, \WP_Site $old_site ) {
		if (
			$new_site->domain !== $old_site->domain ||
			$new_site->path !== $old_site->path ||
			$new_site->public !== $old_site->public ||
			$new_site->archived !== $old_site->archived ||
			$new_site->lang_id !== $old_site->lang_id
		) {
			w3tc_flush_posts();
		}
	}
}
