<?php
/**
 * File: purchase.php
 *
 * Checkout is no longer iframed (Guideline 8).
 *
 * @package W3TC
 */

defined( 'ABSPATH' ) || exit;
if ( ! defined( 'W3TC' ) ) {
	die;
}

$w3tc_purchase_url = function_exists( 'w3tc_purchase_url' )
	? w3tc_purchase_url()
	: ( defined( 'W3TC_PURCHASE_URL' ) ? W3TC_PURCHASE_URL : 'https://www.w3-edge.com/checkout/' );
?>
<div style="min-height:120px;padding:10px;box-sizing:border-box;">
	<p><?php esc_html_e( 'W3 Total Cache Pro is sold separately. Open the purchase page in a new window to continue.', 'w3-total-cache' ); ?></p>
	<p>
		<a href="<?php echo esc_url( $w3tc_purchase_url ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary">
			<?php esc_html_e( 'Get W3 Total Cache Pro', 'w3-total-cache' ); ?>
		</a>
	</p>
</div>
