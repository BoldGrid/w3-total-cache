<?php
/**
 * File: latest.php
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'ABSPATH' ) || exit;
if ( ! defined( 'W3TC' ) ) {
	die();
}
?>
<p class="widget-loading hide-if-no-js {nonce: '<?php echo esc_attr( Util_Nonce::create_admin( 'w3tc_widget_latest_ajax' ) ); ?>'}">
	<?php esc_html_e( 'Loading&#8230;', 'w3-total-cache' ); ?>
</p>
<p class="hide-if-js">
	<?php esc_html_e( 'This widget requires JavaScript.', 'w3-total-cache' ); ?>
</p>
