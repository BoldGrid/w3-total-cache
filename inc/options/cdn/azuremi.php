<?php
/**
 * File: azuremi.php
 *
 * @since   2.7.7
 * @package W3TC
 */

namespace W3TC;

defined( 'W3TC' ) || die();

// Use default values if config is not set.
$cdn_azuremi_config = array_map(
	/**
	 * Anonymous function to populate unset config keys using defaults.
	 *
	 * @since 2.7.7
	 *
	 * @param string|array $default Default configuration .
	 * @param string|array $config  Stored configuration values.
	 * @return string|array
	 */
	function ( $default, $config ) {
		return empty( $config ) ? $default : $config;
	},
	// Default configuration values.
	array(
		'user'      => (string) getenv( 'STORAGE_ACCOUNT_NAME' ),
		'client_id' => (string) getenv( 'ENTRA_CLIENT_ID' ),
		'container' => (string) getenv( 'BLOB_CONTAINER_NAME' ),
		'cname'     => empty( getenv( 'BLOB_STORAGE_URL' ) ) ? array() : array( (string) getenv( 'BLOB_STORAGE_URL' ) ),
	),
	// Stored configuration values.
	array(
		'user'      => $this->_config->get_string( 'cdn.azuremi.user' ),
		'client_id' => $this->_config->get_string( 'cdn.azuremi.clientid' ),
		'container' => $this->_config->get_string( 'cdn.azuremi.container' ),
		'cname'     => $this->_config->get_array( 'cdn.azuremi.cname' ),
	)
);

$cdn_azuremi_config['user'] = $cdn_azuremi_config[0];
unset( $cdn_azuremi_config[0] );
$cdn_azuremi_config['client_id'] = $cdn_azuremi_config[1];
unset( $cdn_azuremi_config[1] );
$cdn_azuremi_config['container'] = $cdn_azuremi_config[2];
unset( $cdn_azuremi_config[2] );
$cdn_azuremi_config['cname'] = $cdn_azuremi_config[3];
unset( $cdn_azuremi_config[3] );

?>
<tr>
	<th style="width: 300px;"><label for="cdn_azuremi_user"><?php esc_html_e( 'Account name:', 'w3-total-cache' ); ?></label></th>
	<td>
		<input id="cdn_azuremi_user" class="w3tc-ignore-change" type="text"
			<?php Util_Ui::sealing_disabled( 'cdn.' ); ?> name="cdn__azuremi__user" value="<?php echo esc_attr( $cdn_azuremi_config['user'] ); ?>" size="30" />
	</td>
</tr>
<tr>
	<th><label for="cdn_azuremi_clientid"><?php esc_html_e( 'Entra client ID:', 'w3-total-cache' ); ?></label></th>
	<td>
		<input id="cdn_azuremi_clientid" class="w3tc-ignore-change"
			<?php Util_Ui::sealing_disabled( 'cdn.' ); ?> type="text" name="cdn__azuremi__clientid" value="<?php echo esc_attr( $cdn_azuremi_config['client_id'] ); ?>" size="60" />
	</td>
</tr>
<tr>
	<th><label for="cdn_azuremi_container"><?php esc_html_e( 'Container:', 'w3-total-cache' ); ?></label></th>
	<td>
		<input id="cdn_azuremi_container" type="text"
			<?php Util_Ui::sealing_disabled( 'cdn.' ); ?> name="cdn__azuremi__container" value="<?php echo esc_attr( $cdn_azuremi_config['container'] ); ?>" size="30" />
		<input id="cdn_create_container" <?php Util_Ui::sealing_disabled( 'cdn.' ); ?> class="button {type: 'azuremi', nonce: '<?php echo esc_attr( wp_create_nonce( 'w3tc' ) ); ?>'}" type="button" value="<?php esc_attr_e( 'Create container', 'w3-total-cache' ); ?>" />
		<span id="cdn_create_container_status" class="w3tc-status w3tc-process"></span>
	</td>
</tr>
<tr>
	<th>
		<label for="cdn_azuremi_ssl">
			<?php
			echo wp_kses(
				sprintf(
					// translators: 1 opening HTML acronym tag, 2 closing HTML acronym tag.
					__(
						'%1$sSSL%2$s support:',
						'w3-total-cache'
					),
					'<acronym title="' . __( 'Secure Sockets Layer', 'w3-total-cache' ) . '">',
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
	<td>
		<select id="cdn_azuremi_ssl" name="cdn__azuremi__ssl" <?php Util_Ui::sealing_disabled( 'cdn.' ); ?>>
			<option value="auto"<?php selected( $this->_config->get_string( 'cdn.azuremi.ssl' ), 'auto' ); ?>><?php esc_html_e( 'Auto (determine connection type automatically)', 'w3-total-cache' ); ?></option>
			<option value="enabled"<?php selected( $this->_config->get_string( 'cdn.azuremi.ssl' ), 'enabled' ); ?>><?php esc_html_e( 'Enabled (always use SSL)', 'w3-total-cache' ); ?></option>
			<option value="disabled"<?php selected( $this->_config->get_string( 'cdn.azuremi.ssl' ), 'disabled' ); ?>><?php esc_html_e( 'Disabled (always use HTTP)', 'w3-total-cache' ); ?></option>
		</select>
		<p class="description">
			<?php
			echo wp_kses(
				sprintf(
					// translators: 1 opening HTML acronym tag, 2 closing HTML acronym tag,
					// translators: 3 opening HTML acronym tag, 4 closing HTML acronym tag.
					__(
						'Some %1$sCDN%2$s providers may or may not support %3$sSSL%4$s, contact your vendor for more information.',
						'w3-total-cache'
					),
					'<acronym title="' . __( 'Content Delivery Network', 'w3-total-cache' ) . '">',
					'</acronym>',
					'<acronym title="' . __( 'Secure Sockets Layer', 'w3-total-cache' ) . '">',
					'</acronym>'
				),
				array(
					'acronym' => array(
						'title' => array(),
					),
				)
			);
			?>
		</p>
	</td>
</tr>
<tr>
	<th><?php esc_html_e( 'Replace site\'s hostname with:', 'w3-total-cache' ); ?></th>
	<td>
		<?php
		$cdn_azure_user = $this->_config->get_string( 'cdn.azuremi.user' );

		if ( empty( $cdn_azure_user ) ) {
			echo '&lt;account name&gt;.blob.core.windows.net';
		} else {
			echo esc_attr( $cdn_azure_user ) . '.blob.core.windows.net';
		}

		echo wp_kses(
			sprintf(
				// translators: 1 opening HTML acronym tag, 2 closing HTML acronym tag.
				__(
					' or %1$sCNAME%2$s:',
					'w3-total-cache'
				),
				'<acronym title="' . __( 'Canonical Name', 'w3-total-cache' ) . '">',
				'</acronym>'
			),
			array(
				'acronym' => array(
					'title' => array(),
				),
			)
		);

		$cnames = $cdn_azuremi_config['cname'];
		require W3TC_INC_DIR . '/options/cdn/common/cnames.php';
		?>
	</td>
</tr>
<tr>
	<th colspan="2">
		<input id="cdn_test" class="button {type: 'azuremi', nonce: '<?php echo esc_attr( wp_create_nonce( 'w3tc' ) ); ?>'}" type="button" value="<?php esc_attr_e( 'Test Microsoft Azure Storage upload', 'w3-total-cache' ); ?>" /> <span id="cdn_test_status" class="w3tc-status w3tc-process"></span>
	</th>
</tr>
