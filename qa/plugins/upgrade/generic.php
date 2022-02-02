<?php
/**
 * File: generic.php
 *
 * Upgrade: Generaic.
 *
 * Template Name: Upgrade: Generic
 * Template Post Type: post, page
 *
 * @package W3TC
 * @subpackage QA
 *
 * phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput, WordPress.WP.AlternativeFunctions, WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
 */

if ( ! defined( 'DONOTCACHEPAGE' ) ) {
	define( 'DONOTCACHEPAGE', true );
}

$wp_plugins_path = $_REQUEST['wp_plugins_path'];
$w3tc_path       = $wp_plugins_path . 'w3-total-cache';

switch ( $_REQUEST['change_w3tc'] ) {
	case 'reset':
		exec( 'sudo /share/scripts/restore-w3tc-inactive.sh' );
		break;

	case 'old':
		$repo   = 'https://downloads.wordpress.org/plugin/w3-total-cache.0.9.5.zip';
		$output = '/share/w3tc-9-5.zip';

		exec( "sudo curl --silent $repo --output $output" );
		exec( 'sudo /share/scripts/w3tc-umount.sh' );
		exec( "sudo unzip -q $output -d $wp_plugins_path" );

		echo strpos( file_get_contents( $w3tc_path . '/w3-total-cache-api.php' ), "'0.9.5'" ) > 0 ? 'ok' : 'error';
		break;

	case 'new':
		exec( 'sudo /share/scripts/w3tc-mount.sh' );
		echo file_exists( $w3tc_path . '/Base_Page_Settings.php' ) ? 'ok' : 'error';
		break;
}
