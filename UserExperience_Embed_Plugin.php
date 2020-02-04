<?php
namespace W3TC;

class UserExperience_Embed_Plugin {
	public function run() {
		add_action( 'wp_footer', array( $this, 'wp_footer' ) );
	}



	public function wp_footer() {
		wp_deregister_script( 'wp-embed' );
	}
}
