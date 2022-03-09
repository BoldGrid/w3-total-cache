<?php 
if ( ! defined( 'W3TC' ) ) {
	die();
}
?>
<h3><?php esc_html_e( 'Create Pull Zone', 'w3-total-cache' ); ?></h3>
<style type="text/css">.form-table th {width:auto}</style>
<div class="netdna-maxcdn-form">
	<div class="create-error w3tc-error"></div>
	<table class="form-table">
	<tr>
		<th><label for="name"><?php esc_html_e( 'Name:', 'w3-total-cache' ); ?></label></th>
		<td><input type="text" id="name" name="name"/><div class="name_message w3tc-error inline"></div>
		<p class="description"><?php esc_html_e( 'Pull Zone Name. Length: 3-32 chars; only letters, digits, and dash (-) accepted', 'w3-total-cache' ); ?></p>
		</td>
	</tr>
	<tr>
		<th>
			<label for="url">
				<?php
				echo wp_kses(
					sprintf(
						// translators: 1 opening HTML acronym tag, 2 closing HTML acronym tag.
						__(
							'Origin %1$sURL%2$s:',
							'w3-total-cache'
						),
						'<acronym title="' . __( 'Uniform Resource Indicator', 'w3-total-cache' ) . '">',
						'</acronym>'
					),
					array(
						'acronym' => array(
							'title' => array(),
						),
					)
				);
				?>
			</label>
		</th>
		<td><?php echo esc_html( get_home_url() ); ?>
		<p class="description"><?php esc_html_e( 'Your server\'s hostname or domain', 'w3-total-cache' ); ?></p>
		</td>
	</tr>
	<tr>
		<th><label for="label"><?php esc_html_e( 'Description:', 'w3-total-cache' ); ?></label></th>
		<td><textarea id="label" name="label" cols="40"></textarea><div class="label_message w3tc-error inline"></div>
		<p class="description"><?php esc_html_e( 'Something that describes your zone. Length: 1-255 chars', 'w3-total-cache' ); ?></p>
		</td>
	</tr>
	<tr>
		<th></th>
		<td>
			<input type="hidden" name="type" id="type" value="<?php echo esc_attr( $type ); ?>" />
			<?php wp_nonce_field( 'w3tc' ); ?>
			<input id="create_pull_zone" id="create_pull_zone" type="button" value="<?php esc_attr_e( 'Create Pull Zone', 'w3-total-cache' ); ?>" class="button-primary" />
			<div id="pull-zone-loading" style="display:inline-block;"></div>
		</td>
	</tr>
</table>
</div>
