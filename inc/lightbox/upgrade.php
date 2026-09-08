<?php
/**
 * File: upgrade.php
 *
 * Upgrade overlay: marketing ad iframe plus a purchase button that
 * opens checkout in a new tab (checkout itself is not iframed).
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'ABSPATH' ) || exit;
if ( ! defined( 'W3TC' ) ) {
	die();
}

$data_src  = isset( $data_src ) ? $data_src : '';
$renew_key = isset( $renew_key ) ? $renew_key : '';
$client_id = isset( $client_id ) ? $client_id : '';

$w3tc_ad_url = add_query_arg(
	array(
		'data_src'  => $data_src,
		'client_id' => $client_id,
	),
	W3TC_PURCHASE_AD_URL
);

$w3tc_checkout_url = w3tc_purchase_url( $data_src, $renew_key, $client_id );
?>
<div id="w3tc-upgrade">
	<div id="w3tc_upgrade_header">
		<div>
			<div>
				<strong>W3 TOTAL CACHE</strong><br />
				<span style="font-size:16px;"><?php esc_html_e( 'Unlock more performance options', 'w3-total-cache' ); ?></span>
			</div>
		</div>
	</div>
	<div class="w3tc_overlay_upgrade_header">
		<iframe src="<?php echo esc_url( $w3tc_ad_url ); ?>" width="100%" height="410px" title="<?php esc_attr_e( 'W3 Total Cache Pro', 'w3-total-cache' ); ?>"></iframe>
	</div>
	<div class="w3tc_upgrade_footer">
		<a id="w3tc-purchase-link"
			href="<?php echo esc_url( $w3tc_checkout_url ); ?>"
			target="_blank"
			rel="noopener noreferrer"
			class="btn w3tc-size">
			<?php esc_html_e( 'Subscribe to Go Faster Now', 'w3-total-cache' ); ?>
		</a>
	</div>
</div>
