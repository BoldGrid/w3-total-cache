<?php
/**
 * File: Extension_NewRelic_Widget_View_NotConfigured.php
 *
 * @package W3TC
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

esc_html_e( 'You have not configured API key and Account Id.', 'w3-total-cache' );
