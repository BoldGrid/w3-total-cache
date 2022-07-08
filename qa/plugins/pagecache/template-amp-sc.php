<?php

add_shortcode( 'w3tcqa', function( $atts ) {
	if ( isset( $_REQUEST['amp'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return '!amp-page!';
	} else {
		return '!regular-page!';
	}
} );
