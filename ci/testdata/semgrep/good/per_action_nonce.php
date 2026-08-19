<?php
// Compliant: per-action SetupGuide nonce.
if ( ! wp_verify_nonce( $n, 'w3tc_wizard_config_pgcache' ) ) {
	return;
}
