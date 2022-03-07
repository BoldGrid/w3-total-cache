<?php

add_action( 'template_redirect', function() {
	if ( ! defined( 'W3TCQA_NO_REDIRECT' ) ) {
		$scheme = 'http';

		if ( isset( $_SERVER['REQUEST_SCHEME'] ) ) {
			$scheme = $_SERVER['REQUEST_SCHEME'];
		} elseif ( '443' === $_SERVER['SERVER_PORT'] ) {
			$scheme = 'https';   // Nginx.
		}

		$url = $scheme . '://for-tests.sandbox';

		if ( '80' !== $_SERVER['SERVER_PORT'] && '443' !== $_SERVER['SERVER_PORT'] ) {
			$url .= ':' . $_SERVER['SERVER_PORT'];
		}

		$url .= '/';

		wp_redirect( $url );
		exit;
	} else {
		echo 'no redirect\n';
	}
} );
