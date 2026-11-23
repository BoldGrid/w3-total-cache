<?php
/**
 * File: cnames-readonly.php
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'ABSPATH' ) || exit;
if ( ! defined( 'W3TC' ) ) {
	die();
}

if ( ! empty( $w3tc_cnames ) ) {
	if ( 1 === count( $w3tc_cnames ) ) {
		echo '<div class="w3tc_cdn_cnames_readonly">' . esc_html( $w3tc_cnames[0] ) . '</div>';
	} else {
		echo '<ol class="w3tc_cdn_cnames_readonly">';

		foreach ( $w3tc_cnames as $w3tc_index => $w3tc_cname ) {
			$w3tc_label = '';

			if ( 0 === $w3tc_index ) {
				$w3tc_label = __( '(reserved for CSS)', 'w3-total-cache' );
			} elseif ( 1 === $w3tc_index ) {
				$w3tc_label = __( '(reserved for JS in <head>)', 'w3-total-cache' );
			} elseif ( 2 === $w3tc_index ) {
				$w3tc_label = __( '(reserved for JS after <body>)', 'w3-total-cache' );
			} elseif ( 3 === $w3tc_index ) {
				$w3tc_label = __( '(reserved for JS before </body>)', 'w3-total-cache' );
			} else {
				$w3tc_label = '';
			}

			echo '<li>' . esc_html( $w3tc_cname ) . '<span class="w3tc_cdn_cname_comment">';
			echo esc_html( $w3tc_label );
			echo '</span></li>';
		}

		echo '</ol>';
	}
}
