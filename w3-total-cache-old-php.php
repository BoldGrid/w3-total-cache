<?php
/**
 * File: w3-total-cache-old-php.php
 *
 * @package W3TC
 */

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/**
 * Generates a message indicating that the PHP version is outdated.
 *
 * This message notifies users that the PHP version must be updated to at least 7.2.5 to use W3 Total Cache.
 *
 * @return string The warning message about outdated PHP.
 */
function w3tc_old_php_message() {
	$m = __( 'Please update your PHP. <strong>W3 Total Cache</strong> requires PHP version 7.2.5 or above', 'w3-total-cache' );
	return $m;
}

/**
 * Displays an activation error message for outdated PHP versions.
 *
 * This method outputs an error message and terminates execution if the PHP version does not meet the minimum requirements.
 *
 * @return void
 */
function w3tc_old_php_activate() {
	echo esc_html( w3tc_old_php_message() );
	exit();
}

/**
 * Adds an admin notice for outdated PHP versions.
 *
 * This method displays an error notice in the WordPress admin area, alerting users to update their PHP version to at least 7.2.5.
 *
 * @return void
 */
function w3tc_old_php_admin_notices() {
	?>
	<div class="notice error notice-error">
		<p><?php echo esc_html( w3tc_old_php_message() ); ?></p>
	</div>
	<?php
}

add_action( 'admin_notices', 'w3tc_old_php_admin_notices' );
