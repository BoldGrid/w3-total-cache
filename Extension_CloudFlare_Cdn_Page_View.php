<?php
namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}
?>
		<?php Util_Ui::postbox_header( esc_html__( 'Configuration: Full-Site Delivery', 'w3-total-cache' ), '', 'configuration' ); ?>
		<table class="form-table">
			<tr>
				<th style="width: 300px;">
					<label>
						<?php esc_html_e( 'Configuration:', 'w3-total-cache' ); ?>
					</label>
				</th>
				<td>
					<a href="admin.php?page=w3tc_extensions&extension=cloudflare&action=view">Open Configuration Page</a>
				</td>
			</tr>
		</table>

		<?php Util_Ui::postbox_footer(); ?>
