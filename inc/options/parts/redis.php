<?php
/**
 * File: redis.php
 *
 * @package W3TC
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

/*
 * Requires $module variable
 */
?>
<tr>
	<th><label for="redis_servers"><?php echo wp_kses( Util_ConfigLabel::get( 'redis.servers' ), array( 'acronym' => array( 'title' => array() ) ) ); ?></label></th>
	<td>
		<input id="redis_servers" type="text"
			name="<?php echo esc_attr( $module ); ?>__redis__servers"
			<?php Util_Ui::sealing_disabled( $module ); ?>
			value="<?php echo esc_attr( implode( ',', $this->_config->get_array( $module . '.redis.servers' ) ) ); ?>"
			size="100" />
		<input class="w3tc_common_redis_test button {nonce: '<?php echo esc_attr( wp_create_nonce( 'w3tc' ) ); ?>'}"
			<?php Util_Ui::sealing_disabled( $module ); ?>
			type="button" value="<?php esc_attr_e( 'Test', 'w3-total-cache' ); ?>" />
		<span class="w3tc_common_redis_test_result w3tc-status w3tc-process"></span>
		<p class="description"><?php esc_html_e( 'Multiple servers may be used and seperated by a comma; e.g. 192.168.1.100:11211, domain.com:22122. To use TLS, prefix server with tls://', 'w3-total-cache' ); ?></p>
	</td>
</tr>
<tr class="hidden">
	<th><label><?php esc_html_e( 'Verify TLS Certificates:', 'w3-total-cache' ); ?></label></th>
	<td>
		<?php $this->checkbox( $module . '.redis.verify_tls_certificates' ); ?> <?php echo wp_kses( Util_ConfigLabel::get( 'redis.verify_tls_certificates' ), array( 'acronym' => array( 'title' => array() ) ) ); ?></label>
		<p class="description"><?php esc_html_e( 'Verify the server\'s certificate when connecting via TLS.', 'w3-total-cache' ); ?></p>
	</td>
</tr>
<tr>
	<th><label><?php esc_html_e( 'Use persistent connection:', 'w3-total-cache' ); ?></label></th>
	<td>
		<?php $this->checkbox( $module . '.redis.persistent' ); ?> <?php echo wp_kses( Util_ConfigLabel::get( 'redis.persistent' ), array( 'acronym' => array( 'title' => array() ) ) ); ?></label>
		<p class="description"><?php esc_html_e( 'Using persistent connection doesn\'t reinitialize redis driver on each request', 'w3-total-cache' ); ?></p>
	</td>
</tr>
<tr>
	<th style="width: 250px;"><label for="redis_timeout"><?php echo wp_kses( Util_ConfigLabel::get( 'redis.timeout' ), array( 'acronym' => array( 'title' => array() ) ) ); ?></label></th>
	<td>
		<input id="redis_timeout" type="number" name="<?php echo esc_attr( $module ); ?>__redis__timeout"
			<?php Util_Ui::sealing_disabled( $module ); ?>
			value="<?php echo esc_attr( $this->_config->get_integer( $module . '.redis.timeout' ) ); ?>"
			size="8" step="1" min="0" />
		<p class="description"><?php esc_html_e( 'In seconds', 'w3-total-cache' ); ?></p>
	</td>
</tr>
<tr>
	<th style="width: 250px;"><label for="redis_retry_interval"><?php echo wp_kses( Util_ConfigLabel::get( 'redis.retry_interval' ), array( 'acronym' => array( 'title' => array() ) ) ); ?></label></th>
	<td>
		<input id="redis_retry_interval" type="number" name="<?php echo esc_attr( $module ); ?>__redis__retry_interval"
			<?php Util_Ui::sealing_disabled( $module ); ?>
			value="<?php echo esc_attr( $this->_config->get_integer( $module . '.redis.retry_interval' ) ); ?>"
			size="8" step="100" min="0" />
		<p class="description"><?php esc_html_e( 'In miliseconds', 'w3-total-cache' ); ?></p>
	</td>
</tr>
<?php
if ( version_compare( phpversion( 'redis' ), '5', '>=' ) ) {
	// PHP Redis 5 supports the read_timeout setting.
	?>
<tr>
	<th style="width: 250px;"><label for="redis_read_timeout"><?php echo wp_kses( Util_ConfigLabel::get( 'redis.read_timeout' ), array( 'acronym' => array( 'title' => array() ) ) ); ?></label></th>
	<td>
		<input id="redis_read_timeout" type="number" name="<?php echo esc_attr( $module ); ?>__redis__read_timeout"
			<?php Util_Ui::sealing_disabled( $module ); ?>
			value="<?php echo esc_attr( $this->_config->get_integer( $module . '.redis.read_timeout' ) ); ?>"
			size="8" step="1" min="0" />
		<p class="description"><?php esc_html_e( 'In seconds', 'w3-total-cache' ); ?></p>
	</td>
</tr>
	<?php
}
?>
<tr>
	<th style="width: 250px;"><label for="redis_dbid"><?php echo wp_kses( Util_ConfigLabel::get( 'redis.dbid' ), array( 'acronym' => array( 'title' => array() ) ) ); ?></label></th>
	<td>
		<input id="redis_dbid" type="text" name="<?php echo esc_attr( $module ); ?>__redis__dbid"
			<?php Util_Ui::sealing_disabled( $module ); ?>
			value="<?php echo esc_attr( $this->_config->get_integer( $module . '.redis.dbid' ) ); ?>"
			size="8" />
		<p class="description"><?php esc_html_e( 'Database ID to use', 'w3-total-cache' ); ?></p>
	</td>
</tr>
<tr>
	<th><label for="redis_password"><?php echo wp_kses( Util_ConfigLabel::get( 'redis.password' ), array( 'acronym' => array( 'title' => array() ) ) ); ?></label></th>
	<td>
		<input id="redis_password" name="<?php echo esc_attr( $module ); ?>__redis__password" type="text"
			<?php Util_Ui::sealing_disabled( $module ); ?>
			<?php $this->value_with_disabled( $module . '.redis.password', false, '' ); ?> />
		<p class="description">
			<?php
			echo wp_kses(
				sprintf(
					// translators: 1 opening HTML acronym tag, 2 closing HTML acronym tag.
					__(
						'Specify redis password, when %1$sSASL%2$s authentication used',
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
