<?php
/**
 * File: basic.php
 *
 * Object cache: Basic.
 *
 * Template Name: Object cache: Basic
 * Template Post Type: post, page
 *
 * @package W3TC
 * @subpackage QA
 *
 * phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput, WordPress.WP.GlobalVariablesOverride.Prohibited
 */

require __DIR__ . '/wp-load.php';

$action = $_REQUEST['action'];

switch ( $action ) {
	case 'checkLoaded':
		$set = function_exists( 'wp_cache_set' );
		echo $set ? 'wp_cache loaded' : 'wp_cache missing';
		die;

	case 'setInCache':
		$set = wp_cache_set( $_REQUEST['id'], $_REQUEST['value'], $_REQUEST['group'] );
		echo $set ? 'setInCache ok' : 'setCache error ' . esc_html( $group ) . ':' . esc_html( $id );
		die;

	case 'setInCacheBooleanFalse':
		$set = wp_cache_set( $_REQUEST['id'], false, $_REQUEST['group'] );
		echo $set ? 'setInCache ok' : 'setCache error ' . esc_html( $group ) . ':' . esc_html( $id );
		die;

	case 'getFromCache':
		$found = null;
		$value = wp_cache_get( $_REQUEST['id'], $_REQUEST['group'], false, $found );
		echo wp_json_encode(
			array(
				'value' => $value,
				'found' => $found,
			)
		);
		die;

	case 'doubleGetFromCache':
		$found1 = null;
		$value1 = wp_cache_get( $_REQUEST['id'], $_REQUEST['group'], false, $found1 );
		$found = null;
		$value = wp_cache_get( $_REQUEST['id'], $_REQUEST['group'], false, $found );
		echo wp_json_encode(
			array(
				'value' => $value,
				'found' => $found,
			)
		);
		die;
}
