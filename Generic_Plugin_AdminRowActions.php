<?php
/**
 * File: Generic_Plugin_AdminRowActions.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class Generic_Plugin_AdminRowActions
 */
class Generic_Plugin_AdminRowActions {
	/**
	 * Runs plugin
	 *
	 * @return void
	 */
	public function run() {
		add_filter( 'post_row_actions', array( $this, 'post_row_actions' ), 0, 2 );

		add_filter( 'page_row_actions', array( $this, 'page_row_actions' ), 0, 2 );

		add_action( 'post_submitbox_start', array( $this, 'post_submitbox_start' ) );
	}


	/**
	 * Filter for post_row_actions
	 *
	 * @param array  $actions Actions.
	 * @param object $post    Post.
	 *
	 * @return array
	 */
	public function post_row_actions( $actions, $post ) {
		return $this->add_flush_post_row_action( $actions, $post );
	}

	/**
	 * Filter for page_row_actions
	 *
	 * @param array  $actions Actions.
	 * @param object $post    Post.
	 *
	 * @return array
	 */
	public function page_row_actions( $actions, $post ) {
		return $this->add_flush_post_row_action( $actions, $post );
	}

	/**
	 * Append Purge from cache when the user may flush this post.
	 *
	 * @since 2.10.4
	 *
	 * @param array  $actions Actions.
	 * @param object $post    Post.
	 *
	 * @return array
	 */
	private function add_flush_post_row_action( $actions, $post ) {
		if ( ! isset( $post->ID ) || ! Util_Capability::can_flush_post_id( (int) $post->ID ) ) {
			return $actions;
		}

		$actions = array_merge(
			$actions,
			array(
				'w3tc_flush_post' => sprintf(
					'<a href="%s">%s</a>',
					esc_url(
						Util_Capability::purge_action_url(
							'w3tc_flush_post',
							array(
								'post_id' => (int) $post->ID,
								'force'   => 'true',
							)
						)
					),
					esc_html__( 'Purge from cache', 'w3-total-cache' )
				),
			)
		);

		return $actions;
	}

	/**
	 * Display Purge from cache on Page/Post post.php.
	 *
	 * @return void
	 */
	public function post_submitbox_start() {
		global $post;

		if ( is_null( $post ) || ! Util_Capability::can_flush_post_id( (int) $post->ID ) ) {
			return;
		}

		printf(
			'<div><a href="%s">%s</a></div>',
			esc_url(
				Util_Capability::purge_action_url(
					'w3tc_flush_post',
					array(
						'post_id' => (int) $post->ID,
						'force'   => 'true',
					)
				)
			),
			esc_html__( 'Purge from cache', 'w3-total-cache' )
		);
	}
}
