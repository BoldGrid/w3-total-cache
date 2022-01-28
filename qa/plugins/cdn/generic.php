<?php
/**
 * File: generic.php
 *
 * CDN: Generaic.
 *
 * @package W3TC
 * @subpackage QA
 *
 * phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput, WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
 */

require __DIR__ . '/wp-load.php';

$action = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : '';

if ( 'cron_queue_process' === $action ) {
	do_action( 'w3_cdn_cron_queue_process' );
	echo 'cron_queue_process';
} else {
	$path = $_REQUEST['path'];
	exec( 'sudo chown www-data:www-data ' . $path );
	$user = posix_getpwuid( fileowner( $path ) );
	echo $user['name']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
