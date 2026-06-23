<?php
/**
 * File: memcached.php
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'ABSPATH' ) || exit;
if ( ! defined( 'W3TC' ) ) {
	die();
}

/*
 * Requires $w3tc_module variable
 */
?>
<tr>
	<th><label for="memcached_servers"><?php echo wp_kses( Util_ConfigLabel::get( 'memcached.servers' ), array( 'acronym' => array( 'title' => array() ) ) ); ?></label></th>
	<td>
		<textarea id="memcached_servers" name="<?php echo esc_attr( $w3tc_module ); ?>__memcached__servers" <?php Util_Ui::sealing_disabled( $w3tc_module ); ?> rows="10" cols="50"><?php echo esc_html( implode( "\n", $this->_config->get_array( $w3tc_module . '.memcached.servers' ) ) ); ?></textarea>
		<input id="memcached_test" class="button {nonce: '<?php echo esc_attr( Util_Nonce::create_admin( 'w3tc_test_memcached' ) ); ?>'}"
			<?php Util_Ui::sealing_disabled( $w3tc_module ); ?>
			type="button" value="<?php esc_attr_e( 'Test', 'w3-total-cache' ); ?>" />
		<span id="memcached_test_status" class="w3tc-status w3tc-process"></span>
		<p class="description"><?php esc_html_e( 'Enter one server definition per line: e.g. 127.0.0.1:11211 or domain.com:11211.', 'w3-total-cache' ); ?></p>
	</td>
</tr>
<tr>
	<th><label><?php esc_html_e( 'Use persistent connection:', 'w3-total-cache' ); ?></label></th>
	<td>
		<?php $this->checkbox( $w3tc_module . '.memcached.persistent' ); ?> <?php echo wp_kses( Util_ConfigLabel::get( 'memcached.persistent' ), array( 'acronym' => array( 'title' => array() ) ) ); ?></label>
		<p class="description"><?php esc_html_e( 'Using persistent connection doesn\'t reinitialize memcached driver on each request', 'w3-total-cache' ); ?></p>
	</td>
</tr>
<tr>
	<th><label><?php esc_html_e( 'Node Auto Discovery:', 'w3-total-cache' ); ?></label></th>
	<td>
		<label>
			<?php $this->checkbox( $w3tc_module . '.memcached.aws_autodiscovery', ! Util_Installed::memcached_aws() ); ?>
			Amazon Node Auto Discovery
		</label>
		<p class="description">
			<?php
			if ( ! Util_Installed::memcached_aws() ) {
				echo wp_kses(
					sprintf(
						// translators: 1 opening HTML acronym tag, 2 closing HTML acronym tag.
						__(
							'ElastiCache %1$sPHP%2$s module not found',
							'w3-total-cache'
						),
						'<acronym title="' . __( 'Hypertext Preprocessor', 'w3-total-cache' ) . '">',
						'</acronym>'
					),
					array(
						'acronym' => array(
							'title' => array(),
						),
					)
				);
			} else {
				esc_html_e( 'When Amazon ElastiCache used, specify configuration endpoint as Memcached host', 'w3-total-cache' );
			}
			?>
		</p>
	</td>
</tr>
<tr>
	<th><label><?php esc_html_e( 'Use binary protocol:', 'w3-total-cache' ); ?></label></th>
	<td>
		<?php $this->checkbox( $w3tc_module . '.memcached.binary_protocol' ); ?> <?php echo wp_kses( Util_ConfigLabel::get( 'memcached.binary_protocol' ), array( 'acronym' => array( 'title' => array() ) ) ); ?></label>
		<p class="description"><?php esc_html_e( 'Using binary protocol can increase throughput.', 'w3-total-cache' ); ?></p>
	</td>
</tr>

<tr>
	<th><label for="memcached_username"><?php echo wp_kses( Util_ConfigLabel::get( 'memcached.username' ), array( 'acronym' => array( 'title' => array() ) ) ); ?></label></th>
	<td>
		<input id="memcached_username" name="<?php echo esc_attr( $w3tc_module ); ?>__memcached__username" type="text"
			<?php Util_Ui::sealing_disabled( $w3tc_module ); ?>
			<?php $this->value_with_disabled( $w3tc_module . '.memcached.username', ! Util_Installed::memcached_auth(), '' ); ?> />
		<p class="description">
			<?php
			echo wp_kses(
				sprintf(
					// translators: 1 opening HTML acronym tag, 2 closing HTML acronym tag.
					__(
						'Specify memcached username, when %1$sSASL%2$s authentication used',
						'w3-total-cache'
					),
					'<acronym title="' . __( 'Simple Authentication and Security Layer', 'w3-total-cache' ) . '">',
					'</acronym>'
				),
				array(
					'acronym' => array(
						'title' => array(),
					),
				)
			);
			if ( ! Util_Installed::memcached_auth() ) {
				echo wp_kses(
					sprintf(
						// translators: 1 HTML line break, 2 opening HTML acronym tag, 3 closing HTML acronym tag.
						__(
							'%1$sAvailable when memcached extension installed, built with %2$sSASL%3$s',
							'w3-total-cache'
						),
						'</ br>',
						'<acronym title="' . __( 'Simple Authentication and Security Layer', 'w3-total-cache' ) . '">',
						'</acronym>'
					),
					array(
						'br'      => array(),
						'acronym' => array(
							'title' => array(),
						),
					)
				);
			}
			?>
		</p>
	</td>
</tr>
<tr>
	<th><label for="memcached_password"><?php echo wp_kses( Util_ConfigLabel::get( 'memcached.password' ), array( 'acronym' => array( 'title' => array() ) ) ); ?></label></th>
	<td>
		<?php
		/**
		 * RT9-19: Use the masked secret renderer so the stored
		 * Memcached SASL password never round-trips through the HTML
		 * `value=` attribute. `Util_Installed::memcached_auth()` may
		 * disable the field when libmemcached lacks SASL support —
		 * pass that through unchanged via the `disabled` arg.
		 */
		Util_Ui::secret_input(
			array(
				'id'          => 'memcached_password',
				'name'        => $w3tc_module . '__memcached__password',
				'has_value'   => '' !== $this->_config->get_string( $w3tc_module . '.memcached.password' ),
				'type'        => 'password',
				'sealing_key' => $w3tc_module . '.',
				'disabled'    => ! Util_Installed::memcached_auth(),
			)
		);
		?>
		<p class="description">
			<?php
			echo wp_kses(
				sprintf(
					// translators: 1 opening HTML acronym tag, 2 closing HTML acronym tag.
					__(
						'Specify memcached password, when %1$sSASL%2$s authentication used',
						'w3-total-cache'
					),
					'<acronym title="' . __( 'Simple Authentication and Security Layer', 'w3-total-cache' ) . '">',
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
