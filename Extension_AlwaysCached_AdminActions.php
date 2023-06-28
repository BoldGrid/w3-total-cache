<?php
namespace W3TC;



class Extension_AlwaysCached_AdminActions {
	public function w3tc_alwayscached_empty() {
		Extension_AlwaysCached_Queue::empty();

		Util_Admin::redirect_with_custom_messages2( [
				'notes' => [
					'alwayscached_empty' => __( 'Queue successfully emptied.', 'w3-total-cache' )
				]
			] );
	}
}
