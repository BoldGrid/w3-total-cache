<?php
/**
 * File: cookie-groups.php
 *
 * Page cache: Cookie Groups test.
 *
 * @package W3TC
 * @subpackage QA
 *
 * phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
 */

if ( isset( $_REQUEST['action'] ) ) {
	add_action(
		'template_redirect',
		function () {
			$action = $_REQUEST['action'];
		}
	);

	if ( 'setcookie' === $action ) {
		setcookie( $_REQUEST['name'], $_REQUEST['value'] );
		echo 'ok';
		exit;
	}
}

add_action(
	'wp_footer',
	function () {
		?>
		<div id="cookie_groupcookie">
			<?php echo isset( $_COOKIE['groupcookie'] ) ? $_COOKIE['groupcookie'] : ''; ?>
		</div>

		<div id="incremental_key">
			<?php
			$v = (int) get_option( 'w3tcqa_incremental_key' );
			update_option( 'w3tcqa_incremental_key', $v + 1 );
			echo $v; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</div>
		<?php
	}
);
