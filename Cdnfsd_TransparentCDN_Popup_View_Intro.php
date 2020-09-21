<?php
namespace W3TC;

if ( !defined( 'W3TC' ) )
	die();
?>
<form class="w3tc_cdn_transparentcdn_fsd_form">
	<?php
if ( isset( $details['error_message'] ) )
	echo '<div class="error">' . $details['error_message'] . '</div>';
?>
	<div class="metabox-holder">
		<?php Util_Ui::postbox_header(
	__( 'Your TransparentCDN Account credentials', 'w3-total-cache' ) ); ?>
		<table class="form-table">
			<tr>
				<td>
					<?php	__('Company ID:', 'w3-total-cache') ?>
				</td>
				<td>
					<input name="company_id" type="text" class="w3tc-ignore-change"
						style="width: 550px"
						value="<?php echo esc_attr( $details['company_id'] ) ?>" />
				</td>
			</tr>
			<tr>
				<td>
					<?php __("API Client ID:") ?>
				</td>
				<td>
					<input name="client_id" type="text" class="w3tc-ignore-change"
						style="width: 550px"
						value="<?php echo esc_attr( $details['client_id'] ) ?>" />
				</td>
			</tr>
			<tr>
				<td>
					<?php __("API Client Secret:") ?>
				</td>
				<td>
					<input name="client_secret" type="text" class="w3tc-ignore-change"
						style="width: 550px"
						value="<?php echo esc_attr( $details['client_secret'] ) ?>" />
					<br />
					<span class="description">
						<?php __('You can get every detail about your account in the dashboard at' , 'w3-total-cache') ?> <a href="https://app.transparentcdn.com">https://app.transparentcdn.com</a>
					</span>
				</td>
			</tr>
		</table>

		<p class="submit">
			<input type="button"
				class="w3tc-button-save button-primary"
				value="<?php _e( 'Next', 'w3-total-cache' ); ?>" />
		</p>
		<?php Util_Ui::postbox_footer(); ?>
	</div>
</form>
