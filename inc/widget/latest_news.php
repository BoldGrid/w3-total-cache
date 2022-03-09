<?php
namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}
?>
<p class="widget-loading hide-if-no-js {nonce: '<?php echo esc_attr( wp_create_nonce( 'w3tc' ) ); ?>'}">
	<?php echo esc_html( __( 'Loading&#8230;' ) ); ?>
</p>
<p class="hide-if-js">
	<?php echo esc_html( __( 'This widget requires JavaScript.' ) ); ?>
</p>
