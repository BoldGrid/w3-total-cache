<?php

add_action( 'wp_head', function() {
	echo '<link rel="stylesheet" type="text/css" id="font_test" href="//fonts.googleapis.com/css?family=Ubuntu%3A400%2C700%26subset%3Dlatin%2Clatin-ex" media="all" />'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
} );
