<?php
/**
 * File: redis_extension.php
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
$config = Dispatcher::config();

?>
<tr>
	<th><label for="redis_servers"><?php echo wp_kses( Util_ConfigLabel::get( 'redis.servers' ), array( 'acronym' => array( 'title' => array() ) ) ); ?></label></th>
	<td>
		<input id="redis_servers" type="text"
			name="<?php echo esc_attr( $module ); ?>___redis__servers"
			<?php Util_Ui::sealing_disabled( $module ); ?>
			value="<?php echo esc_attr( implode( ',', $config->get_array( array( $module, 'redis.servers' ) ) ) ); ?>"
			size="100" />
		<input class="w3tc_common_redis_test button {nonce: '<?php echo esc_attr( wp_create_nonce( 'w3tc' ) ); ?>'}"
			<?php Util_Ui::sealing_disabled( $module ); ?>
			type="button" value="<?php esc_attr_e( 'Test', 'w3-total-cache' ); ?>" />
		<span class="w3tc_common_redis_test_result w3tc-status w3tc-process"></span>
		<p class="description"><?php esc_html_e( 'Multiple servers may be used and seperated by a comma; e.g. 127.0.0.1:6379, domain.com:6379. To use TLS, prefix server with tls://', 'w3-total-cache' ); ?></p>
	</td>
</tr>
<?php

Util_Ui::config_item(
	array(
		'key'            => array( $module, 'redis.verify_tls_certificates' ),
		'label'          => Util_ConfigLabel::get( 'redis.verify_tls_certificates' ),
		'control'        => 'checkbox',
		'checkbox_label' => Util_ConfigLabel::get( 'redis.verify_tls_certificates' ),
		'description'    => __( 'Verify the server\'s certificate when connecting via TLS.', 'w3-total-cache' ),
	)
);

Util_Ui::config_item(
	array(
		'key'            => array( $module, 'redis.persistent' ),
		'label'          => __( 'Use persistent connection:', 'w3-total-cache' ),
		'control'        => 'checkbox',
		'checkbox_label' => Util_ConfigLabel::get( 'redis.persistent' ),
		'description'    => __( 'Using persistent connection doesn\'t reinitialize memcached driver on each request', 'w3-total-cache' ),
	)
);

Util_Ui::config_item(
	array(
		'key'          => array( $module, 'redis.timeout' ),
		'label'        => Util_ConfigLabel::get( 'redis.timeout' ),
		'control'      => 'textbox',
		'textbox_type' => 'number',
		'description'  => __( 'In seconds', 'w3-total-cache' ),
	)
);

Util_Ui::config_item(
	array(
		'key'          => array( $module, 'redis.retry_interval' ),
		'label'        => Util_ConfigLabel::get( 'redis.retry_interval' ),
		'control'      => 'textbox',
		'textbox_type' => 'number',
		'description'  => __( 'In miliseconds', 'w3-total-cache' ),
	)
);

if ( version_compare( phpversion( 'redis' ), '5', '>=' ) ) {
	// PHP Redis 5 supports the read_timeout setting.
	Util_Ui::config_item(
		array(
			'key'          => array( $module, 'redis.read_timeout' ),
			'label'        => Util_ConfigLabel::get( 'redis.read_timeout' ),
			'control'      => 'textbox',
			'textbox_type' => 'number',
			'description'  => __( 'In seconds', 'w3-total-cache' ),
		)
	);
}

Util_Ui::config_item(
	array(
		'key'         => array( $module, 'redis.dbid' ),
		'label'       => Util_ConfigLabel::get( 'redis.dbid' ),
		'control'     => 'textbox',
		'description' => __( 'Database ID to use', 'w3-total-cache' ),
	)
);

Util_Ui::config_item(
	array(
		'key'         => array( $module, 'redis.password' ),
		'label'       => Util_ConfigLabel::get( 'redis.password' ),
		'control'     => 'textbox',
		'description' => __( 'Specify redis password', 'w3-total-cache' ),
	)
);
