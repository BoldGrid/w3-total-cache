<?php
/**
 * File: non-persistent-groups.php
 *
 * Object cache: Non-persistent groups.
 *
 * @package W3TC
 * @subpackage QA
 *
 * phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput, WordPress.WP.GlobalVariablesOverride.Prohibited
 */

require __DIR__ . '/wp-load.php';

$content = '';
$fc_key  = 'transient_test';

if ( ! isset( $_REQUEST['action'] ) || ! isset( $_REQUEST['group'] ) ) {
	return;
}

$action = $_REQUEST['action'];
$group  = $_REQUEST['group'];
$id     = 'test';
$data   = 'object cache test';

switch ( $action ) {
	case 'setCache':
		echo wp_cache_set( $id, $data, $group ) ? 'setCache ok' : 'error';
		die;

	case 'getCache':
		echo wp_cache_get( $id, $group ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		die;
}
