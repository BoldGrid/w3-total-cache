<?php
/**
 * File: UserExperience_OEmbed_Extension.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class UserExperience_OEmbed_Extension
 */
class UserExperience_OEmbed_Extension {
	/**
	 * Runs the initialization process for the extension.
	 *
	 * Adds the necessary WordPress hooks to modify the oEmbed behavior.
	 *
	 * @return void
	 */
	public function run() {
		add_action( 'wp_footer', array( $this, 'wp_footer' ) );
	}

	/**
	 * Modifies the footer of the WordPress page.
	 *
	 * Deregisters the 'wp-embed' script to prevent WordPress from enqueueing
	 * its default embed script, reducing unnecessary resources being loaded.
	 *
	 * @return void
	 */
	public function wp_footer() {
		wp_deregister_script( 'wp-embed' );
	}
}

$o = new UserExperience_OEmbed_Extension();
$o->run();
