<?php
namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}
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
		<iframe src="https://www.w3-edge.com/checkout-ad/?data_src=<?php echo esc_attr( $data_src ); ?>&client_id=<?php echo esc_attr( $client_id ); ?>" width="100%" height="410px"></iframe>
	</div>
	<div class="w3tc_upgrade_footer">
		<?php if ( \W3TC\Util_Environment::is_https() ) : ?>
			<input id="w3tc-purchase" type="button"
				class="btn w3tc-size"
				value="<?php esc_attr_e( 'Subscribe to Go Faster Now', 'w3-total-cache' ); ?> " />
		<?php else : ?>
			<a id="w3tc-purchase-link"
				href="<?php echo esc_url( \W3TC\Licensing_Core::purchase_url( $data_src, $renew_key, $client_id ) ); ?>"
				target="_blank"
				class="btn w3tc-size">
				<?php esc_html_e( 'Subscribe to Go Faster Now', 'w3-total-cache' ); ?>
			</a>
		<?php endif ?>
	</div>
</div>
