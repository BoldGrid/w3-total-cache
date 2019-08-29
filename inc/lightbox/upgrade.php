<?php
namespace W3TC;

if ( !defined( 'W3TC' ) )
	die();


?>
<div id="w3tc-upgrade">
	<div class="w3tc-overlay-logo"></div>
	<div class="w3tc_overlay_upgrade_header">
		<iframe src="https://www.w3-edge.com/checkout-ad/?data_src=<?php echo esc_attr($data_src) ?>" width="100%" height="420px"></iframe>
	</div>
	<div class="w3tc_overlay_content"></div>
	<div class="w3tc_overlay_footer">
		 <?php if ( \W3TC\Util_Environment::is_https() ): ?>
			 <input id="w3tc-purchase" type="button"
				 class="btn w3tc-size image btn-default palette-turquoise secure"
				 value="<?php _e( 'Subscribe to Go Faster Now', 'w3-total-cache' ) ?> " />
		 <?php else: ?>
			 <a id="w3tc-purchase-link" href="<?php echo \W3TC\Licensing_Core::purchase_url() ?>"
				 target="_blank"
				 class="btn w3tc-size image btn-default palette-turquoise secure">
				 <?php _e( 'Subscribe to Go Faster Now', 'w3-total-cache' ) ?>
			 </a>
		 <?php endif ?>
	</div>
	<div style="clear: both"></div>
</div>
