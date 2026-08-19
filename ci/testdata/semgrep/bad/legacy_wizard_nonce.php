<?php
// Expected: w3tc-legacy-wizard-nonce
if ( ! wp_verify_nonce( $n, 'w3tc_wizard' ) ) {
	return;
}
