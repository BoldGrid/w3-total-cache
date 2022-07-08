<?php
namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}
?>
<form class="w3tc_cdn_maxcdn_form" method="post">
	<?php
	Util_Ui::hidden( '', 'api_key', $details['api_key'] );
	Util_Ui::hidden( '', 'zone_id', $details['zone_id'] );
	Util_Ui::hidden( '', 'name', $details['name'] );
	?>

	<div class="metabox-holder">
		<?php Util_Ui::postbox_header( esc_html__( 'Configure zone', 'w3-total-cache' ) ); ?>
		<table class="form-table">
			<tr>
				<th>Name:</th>
				<td><?php echo esc_html( $details['name'] ); ?></td>
			</tr>
			<tr>
				<th>Origin URL:</th>
				<td><?php $this->render_zone_textbox_change( $details, 'url' ); ?></td>
			</tr>
			<tr>
				<th>Compress content:</th>
				<td><?php $this->render_zone_boolean_change( $details, 'compress' ); ?></td>
			</tr>
			<tr>
				<th>Add CORS header:</th>
				<td><?php $this->render_zone_boolean_change( $details, 'cors_headers' ); ?></td>
			</tr>
			<tr>
				<th>HTTPS support:</th>
				<td>
					<?php if ( ! is_null( $details['ssl']['current'] ) ) : ?>
						<strong>
							<?php
							$v = $details['ssl']['current'];
							if ( 'dedicated' === $v ) {
								esc_html_e( 'Dedicated', 'w3-total-cache' );
							} elseif ( 'sni' === $v ) {
								esc_html_e( 'SNI', 'w3-total-cache' );
							} elseif ( 'shared' === $v ) {
								esc_html_e( 'Shared', 'w3-total-cache' );
							} else {
								esc_html_e( 'Not active', 'w3-total-cache' );
							}
							?>
						</strong><br />
					<?php endif; ?>
					<?php if ( ! is_null( $details['ssl']['new'] ) ) : ?>
						<?php Util_Ui::hidden( '', 'ssl', $details['ssl']['new'] ); ?>
						<label>
							<input type="radio" name="ssl" value="" checked="" /> Leave as is
						</label>
						<br />
						<label>
							<input type="radio" name="ssl" value="shared" checked="checked" />
							Enable Shared HTTPS
						</label>
						<br />
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<p class="submit">
			<input type="button"
				class="w3tc_cdn_maxcdn_configure_zone w3tc-button-save button-primary"
				value="<?php esc_attr_e( 'Apply', 'w3-total-cache' ); ?>" />
		</p>
		<?php Util_Ui::postbox_footer(); ?>
	</div>
</form>
