<?php
/**
 * File: sns.php
 *
 * @package W3TC
 */

// Get the request body.
$message = file_get_contents( 'php://input' );

// Validate the messagee format.
try {
	$message_object = json_decode( $message, true );
} catch ( \Exception $e ) {
	echo 'Failed to parse message';
	exit();
}

if ( ! isset( $message_object['Type'] ) || ! isset( $message_object['Message'] ) ) {
	echo 'Unknown message';
	exit();
}

if ( 'Notification' === $message_object['Type'] ) {
	$w3tc_message        = $message_object['Message'];
	$w3tc_message_object = json_decode( $w3tc_message );

	// Switch blog before any action.
	if ( isset( $w3tc_message_object->blog_id ) ) {
		global $w3_current_blog_id;
		$w3_current_blog_id = $w3tc_message_object->blog_id;
	}
	if ( isset( $w3tc_message_object->host ) && ! is_null( $w3tc_message_object->host ) ) {
		$_SERVER['HTTP_HOST'] = $w3tc_message_object->host;
	}
} elseif ( 'SubscriptionConfirmation' === $message_object['Type'] ) {
	echo 'Unsupported message type';
	exit();
}

/**
 * W3 Total Cache SNS module.
 */
define( 'W3TC_WP_LOADING', true );

if ( ! defined( 'ABSPATH' ) ) {
	if ( file_exists( __DIR__ . '/../../../../wp-load.php' ) ) {
		require_once __DIR__ . '/../../../../wp-load.php';
	} else {
		require_once __DIR__ . '/../../w3tc-wp-loader.php';
	}
}

if ( ! defined( 'W3TC_DIR' ) ) {
	define( 'W3TC_DIR', realpath( __DIR__ . '/..' ) );
}

if ( ! @is_dir( W3TC_DIR ) || ! file_exists( W3TC_DIR . '/w3-total-cache-api.php' ) ) {
	@header( 'X-Robots-Tag: noarchive, noodp, nosnippet' );
	printf( '<strong>W3 Total Cache Error:</strong> some files appear to be missing or out of place. Please re-install plugin or remove <strong>%s</strong>. <br />', __DIR__ );
}

require_once W3TC_DIR . '/w3-total-cache-api.php';

// Process message.
$server = \W3TC\Dispatcher::component( 'Enterprise_SnsServer' );
$server->process_message( $message_object );
