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

		<a class="button-primary"
			href="admin.php?page=w3tc_general#stats">Enable here</a>
	</div>

	<?php Util_Ui::postbox_footer(); ?>
</div>
