<?php
namespace W3TC;

if ( !defined( 'W3TC' ) )
	die();

include W3TC_INC_DIR . '/options/common/header.php';
?>
<div class="metabox-holder ustats_ad_metabox">
	<?php Util_Ui::postbox_header( __( 'Usage Statistics', 'w3-total-cache' ) ); ?>

	<div class="ustats_ad">
		<?php include __DIR__ . '/UsageStatistics_Page_View_Ad.php' ?>

		<input type="button" class="button-primary button-buy-plugin"
			data-src="page_stats_bottom"
			value="<?php _e( 'upgrade', 'w3-total-cache' ) ?>" />
	</div>

	<?php Util_Ui::postbox_footer(); ?>
</div>
