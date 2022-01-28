<?php
/**
 * File: default-lifetime-objects.php
 *
 * Object cache: Default lifetime objects.
 *
 * @package W3TC
 * @subpackage QA
 *
 * phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput, WordPress.WP.GlobalVariablesOverride.Prohibited
 */

require __DIR__ . '/wp-load.php';

$action = $_REQUEST['action'];
$group  = $_REQUEST['group'];
$id     = 'test';
$data   = 'object cache test';

switch ( $action ) {
	case 'setCache':
		echo wp_cache_set( $id, $data, $group ) ?
			'setCache ok' : 'setCache error ' . esc_html( $group ) . ':' . esc_html( $id );
		die;

	case 'getCache':
		echo wp_cache_get( $id, $group ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		die;
}
