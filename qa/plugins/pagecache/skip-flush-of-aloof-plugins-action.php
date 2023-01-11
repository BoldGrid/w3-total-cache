<?php
/**
 * File: skip-flush-of-aloof-plugins-action.php
 *
 * @package W3TC
 * @subpackage QA
 *
 * phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput, WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput.OutputNotEscaped
 */

if ( ! defined( 'DONOTCACHEPAGE' ) ) {
	define( 'DONOTCACHEPAGE', true );
}

require __DIR__ . '/wp-load.php';

if ( $_REQUEST['action'] == 'w3tc_flush_all' ) {
	w3tc_flush_all();
	echo 'ok';
	exit;
}

if ( $_REQUEST['action'] == 'w3tc_flush_posts' ) {
	w3tc_flush_posts();
	echo 'ok';
	exit;
}

echo 'unknown action';
