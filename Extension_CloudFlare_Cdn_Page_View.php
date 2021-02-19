<?php
namespace W3TC;

if ( !defined( 'W3TC' ) )
	die();

?>
<form id="cdn_form" action="admin.php?page=w3tc_cdn" method="post">
	<div class="metabox-holder">
		<?php Util_Ui::postbox_header( __( 'Configuration: Full-Site Delivery', 'w3-total-cache' ),
	'', 'configuration' ); ?>
		<table class="form-table">
			<tr>
				<th style="width: 300px;">
					<label>
						<?php _e( 'Configuration:', 'w3-total-cache' ); ?>
					</label>
				</th>
				<td>
					<a href="admin.php?page=w3tc_extensions&extension=cloudflare&action=view">Open Configuration Page</a>
				</td>
			</tr>
		</table>

		<?php Util_Ui::postbox_footer(); ?>
	</div>
</form>
