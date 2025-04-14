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
		$capability = apply_filters( 'w3tc_capability_row_action_w3tc_flush_post', 'manage_options' );

		if ( current_user_can( $capability ) ) {
			$actions = array_merge(
				$actions,
				array(
					'w3tc_flush_post' => sprintf(
						'<a href="%s">' . __( 'Purge from cache', 'w3-total-cache' ) . '</a>',
						wp_nonce_url(
							sprintf(
								'admin.php?page=w3tc_dashboard&w3tc_flush_post&post_id=%d&force=true',
								$post->ID
							),
							'w3tc'
						)
					),
				)
			);
		}

		return $actions;
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
		$capability = apply_filters( 'w3tc_capability_row_action_w3tc_flush_post', 'manage_options' );

		if ( current_user_can( $capability ) ) {
			$actions = array_merge(
				$actions,
				array(
					'w3tc_flush_post' => sprintf(
						'<a href="%s">' . __( 'Purge from cache', 'w3-total-cache' ) . '</a>',
						wp_nonce_url(
							sprintf(
								'admin.php?page=w3tc_dashboard&w3tc_flush_post&post_id=%d&force=true',
								$post->ID
							),
							'w3tc'
						)
					),
				)
			);
		}

		return $actions;
	}

	/**
	 * Display Purge from cache on Page/Post post.php.
	 *
	 * @return void
	 */
	public function post_submitbox_start() {
		if ( current_user_can( 'manage_options' ) ) {
			global $post;
			if ( ! is_null( $post ) ) {
				$url = Util_Ui::url(
					array(
						'page'            => 'w3tc_dashboard',
						'w3tc_flush_post' => 'y',
						'post_id'         => $post->ID,
						'force'           => true,
					)
				);

				printf(
					'<div><a href="%s">%s</a></div>',
					esc_url( $url ),
					esc_html__( 'Purge from cache', 'w3-total-cache' )
				);
			}
		}
	}
}
