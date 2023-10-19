<?php
/**
 * File: Extension_AlwaysCached_AdminActions.php
 *
 * Controller for AlwaysCached extension admin actions.
 *
 * @since 2.5.1
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * AlwaysCached Admin Actions.
 *
 * @since 2.5.1
 */
class Extension_AlwaysCached_AdminActions {

	/**
	 * Handles regenerate page/post request.
	 *
	 * @since 2.5.1
	 *
	 * @return void
	 */
	public function w3tc_alwayscached_regenerate() {
		$post_id = Util_Request::get_integer( 'post_id' );
		$url     = get_permalink( $post_id );

		if ( empty( $url ) ) {
			$note = __( 'Failed to detect current page.', 'w3-total-cache' );
		} else {
			$result = wp_remote_request(
				$url,
				array(
					'headers' => array(
						'w3tcalwayscached' => '-10',
					),
				)
			);

			if (
				empty( $result['response'] )
				|| empty( $result['response']['code'] )
				|| 500 === $result['response']['code']
			) {
				$note = __( 'Failed to handle url ', 'w3-total-cache' ) . $url;
			} elseif ( empty( $result['headers'] ) || empty( $result['headers']['w3tcalwayscached'] ) ) {
				$note = __( 'No evidence of cache refresh.', 'w3-total-cache' );
			} else {
				$note = __( 'Page was successfully regenerated.', 'w3-total-cache' );
			}
		}

		Util_Admin::redirect_with_custom_messages2(
			array(
				'notes' => array(
					'alwayscached_regenerated' => $note,
				),
			)
		);
	}

	/**
	 * Handles empty queue request.
	 *
	 * @since 2.5.1
	 *
	 * @return void
	 */
	public function w3tc_alwayscached_empty() {
		Extension_AlwaysCached_Queue::empty();

		Util_Admin::redirect_with_custom_messages2(
			array(
				'notes' => array(
					'alwayscached_empty' => __( 'Queue successfully emptied.', 'w3-total-cache' ),
				),
			)
		);
	}
}
