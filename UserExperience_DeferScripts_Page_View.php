<?php
namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

$c = Dispatcher::config();
if ( '' === $c->get( array( 'user-experience-defer-scripts', 'timeout' ) ) ) {
	$c->set( array( 'user-experience-defer-scripts', 'timeout' ), 5000 );
}

?>
<?php Util_Ui::postbox_header( esc_html__( 'Defer Scripts', 'w3-total-cache' ), '', 'application' ); ?>
<table class="form-table">
	<?php
	Util_Ui::config_item(
		array(
			'key'         => array( 'user-experience-defer-scripts', 'timeout' ),
			'label'       => esc_html__( 'Timeout (ms):', 'w3-total-cache' ),
			'control'     => 'textbox',
			'description' => esc_html__( 'Timeout (ms)', 'w3-total-cache' ),
		)
	);

	Util_Ui::config_item(
		array(
			'key'         => array( 'user-experience-defer-scripts', 'includes' ),
			'label'       => esc_html__( 'Include words:', 'w3-total-cache' ),
			'control'     => 'textarea',
			'description' => esc_html__( 'Include tags containing words', 'w3-total-cache' ),
		)
	);

	?>
</table>
<?php Util_Ui::postbox_footer(); ?>
