<?php
/**
 * File: file-change-timestamp.php
 *
 * @package W3TC
 * @subpackage QA
 *
 * phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
 */

/**
 * Change file timestamp.
 */
function change_fdate() {
	if ( touch( $_GET['filename'], time() - 3600 ) ) {
		echo 'success change time';
	}
}

change_fdate();
