<?php
/**
 * File: Cdnfsd_BunnyCdn_Popup_View_Deauthorized.php
 *
 * @since   2.6.0
 * @package W3TC
 *
 * @param Config $config               W3TC configuration.
 * @param string $delete_pull_zone     Delete pull zon choice ("yes").
 * @param string $delete_error_message An error message if there was an error trying to delete the pull zone.  String already escaped.
 */

namespace W3TC;

defined( 'W3TC' ) || die();

?>
<form class="w3tc_cdn_bunnycdn_form">
<?php if ( isset( $delete_error_message ) ) : ?>
	<div class="error">
		<?php
		esc_html_e( 'An error occurred trying to delete the pull zone; ', 'w3-total-cache' );
		echo $delete_error_message . '.'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
	</div>
<?php endif; ?>
	<div class="metabox-holder">
		<?php
		Util_Ui::postbox_header(
			esc_html__( 'Success', 'w3-total-cache' ) .
			( isset( $delete_error_message ) ? esc_html__( ' (with an error)', 'w3-total-cache' ) : '' )
		);
		?>

		<div style="text-align: center">
			<p><?php esc_html_e( 'The objects CDN has been deauthorized', 'w3-total-cache' ); ?>.</p>
		</div>
		<?php if ( 'yes' === $delete_pull_zone && empty( $delete_error_message ) ) : ?>
			<div style="text-align: center">
				<p><?php esc_html_e( 'The pull zone has been deleted', 'w3-total-cache' ); ?>.</p>
			</div>
		<?php endif; ?>
		<p class="submit">
			<input type="button" class="w3tc_cdn_bunnycdn_done w3tc-button-save button-primary"
				value="<?php esc_html_e( 'Done', 'w3-total-cache' ); ?>" />
		</p>
		<?php Util_Ui::postbox_footer(); ?>
	</div>
</form>
