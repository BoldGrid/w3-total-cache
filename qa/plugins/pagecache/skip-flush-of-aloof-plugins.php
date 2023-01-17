<?php
add_filter( 'w3tc_preflush_posts',
	function( $do_flush, $extras ) {
		if ( isset( $extras['ui_action'] ) && $extras['ui_action'] == 'flush_button' ) {
			return $do_flush;
		}

		return false;
	},
	99999, 2 );

add_filter( 'w3tc_preflush_all',
	function( $do_flush, $extras ) {
		if ( isset( $extras['ui_action'] ) && $extras['ui_action'] == 'flush_button' ) {
			return $do_flush;
		}

		return false;
	},
	99999, 2 );
