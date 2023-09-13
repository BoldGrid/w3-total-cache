<?php
namespace W3TC;



class Extension_AlwaysCached_AdminActions {
	public function w3tc_alwayscached_regenerate() {
		$post_id = Util_Request::get_integer( 'post_id' );

		$url = get_permalink( $post_id );

		if ( empty( $url ) ) {
			$note = __( 'Failed to detect current page.', 'w3-total-cache' );
		} else {
			$result = wp_remote_request( $url, [
				'headers' => [
					'w3tcalwayscached' => '-10'
				]
			] );
			if ( empty( $result['response'] ) ||
				empty( $result['response']['code'] ) ||
				$result['response']['code'] == 500 ) {
				$note = 'Failed to handle url ' . $url;
			} elseif ( empty( $result['headers'] ) || empty( $result['headers']['w3tcalwayscached'] ) ) {
				$note = 'No evidence of cache refresh';
			} else {
				$note = __( 'Page was successfully regenerated.', 'w3-total-cache' );
			}
		}

		Util_Admin::redirect_with_custom_messages2( [
				'notes' => [
					'alwayscached_regenerated' => $note
				]
			] );
	}

	public function w3tc_alwayscached_empty() {
		Extension_AlwaysCached_Queue::empty();

		Util_Admin::redirect_with_custom_messages2( [
				'notes' => [
					'alwayscached_empty' => __( 'Queue successfully emptied.', 'w3-total-cache' )
				]
			] );
	}
}
