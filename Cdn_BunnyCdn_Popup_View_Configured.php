<?php
/**
 * File: Cdn_BunnyCdn_Popup_View_Configured.php
 *
 * @since   2.6.0
 * @package W3TC
 */

namespace W3TC;

defined( 'ABSPATH' ) || exit;
defined( 'W3TC' ) || die();

?>
<?php if ( ! empty( $error_messages ) ) : ?>
	<div class="error">
		<?php
		/**
		 * Escape at the sink. `$error_messages` is the join of one or
		 * more `Cdn_BunnyCdn_Api`-side `\Exception::getMessage()`
		 * values (see `Cdn_BunnyCdn_Popup::view_configured()` — the
		 * `add_edge_rule` catch concatenates `$ex->getMessage()` to
		 * the translated prefix without escaping). A BunnyCDN edge or
		 * SDK error containing `<script>` would render here. Pin the
		 * escape at this echo so a future supplier-side regression
		 * (or a new try/catch added upstream) can't introduce a sink.
		 */
		echo nl2br( esc_html( (string) $error_messages ) );
		?>
	</div>
<?php endif; ?>
<form class="w3tc_cdn_bunnycdn_form">
	<div class="metabox-holder">
		<?php Util_Ui::postbox_header( esc_html__( 'Success', 'w3-total-cache' ) ); ?>

		<div style="text-align: center">
			<?php esc_html_e( 'A pull zone has been configured successfully', 'w3-total-cache' ); ?>.<br />
		</div>

		<p class="submit">
			<input type="button" class="w3tc_cdn_bunnycdn_done w3tc-button-save button-primary"
				value="<?php esc_html_e( 'Done', 'w3-total-cache' ); ?>" />
		</p>
		<?php Util_Ui::postbox_footer(); ?>
	</div>
</form>
