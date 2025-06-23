<?php
/**
 * File: Cdn_TotalCdn_Popup_View_Add_Custom_Hostname.php
 *
 * Shows a form a to add a custom hostname to a pull zone
 *
 * @since   2.6.0
 * @package W3TC
 */

namespace W3TC;

defined( 'W3TC' ) || die();

?>
<form class="w3tc_cdn_<?php echo esc_attr( W3TC_CDN_SLUG ); ?>_form">
	<div class="metabox-holder">
		<?php Util_Ui::postbox_header( esc_html__( 'Load free SSL Certificate', 'w3-total-cache' ) ); ?>
		<div style="text-align: center">
			<p>
				<?php echo wp_kses_post( $message ); ?>
			</p>
			<p class="submit">
			<input type="button"
				class="<?php echo esc_attr( $button_class ); ?> w3tc-button-save button-primary"
				value="<?php esc_attr_e( 'Ok', 'w3-total-cache' ); ?>" />
			</p>
		<?php Util_Ui::postbox_footer(); ?>
	</div>
</form>
