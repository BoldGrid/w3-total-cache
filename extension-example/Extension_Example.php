<?php
/**
 * Example extension runtime bootstrap.
 *
 * @package W3TCExample
 */

namespace W3TCExample;

/**
 * Example extension frontend handler.
 */
class Extension_Example {
	/**
	 * W3 Total Cache config.
	 *
	 * @var \W3TC\Config
	 */
	private $w3tc_config;

	/**
	 * Runs extension.
	 *
	 * @return void
	 */
	public function run() {
		$this->w3tc_config = w3tc_config();

		if ( $this->w3tc_config->get_boolean( array( 'example', 'is_title_postfix' ) ) ) {
			add_filter( 'the_title', array( $this, 'the_title' ), 10, 2 );
		}
	}

	/**
	 * The_title filter handler.
	 *
	 * Adds a configured postfix to each post title.
	 *
	 * @param string $title Post title.
	 * @param int    $id    Post ID.
	 *
	 * @return string
	 */
	public function the_title( $title, $id ) {
		return $title .
			$this->w3tc_config->get_string( array( 'example', 'title_postfix' ) );
	}
}

/*
 * Loaded by W3 Total Cache when the extension is active.
 */
$w3tc_p = new Extension_Example();
$w3tc_p->run();

if ( is_admin() ) {
	$w3tc_p = new Extension_Example_Admin();
	$w3tc_p->run();
}
