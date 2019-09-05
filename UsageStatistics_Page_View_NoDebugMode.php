<?php
namespace W3TC;

if ( !defined( 'W3TC' ) )
	die();

include W3TC_INC_DIR . '/options/common/header.php';
?>
<div class="metabox-holder">
	<?php Util_Ui::postbox_header( __( 'Usage Statistics', 'w3-total-cache' ) ); ?>

	<p>
		Usage Statistics is collected only when Debug Mode is enabled.
	</p>

	<a href="admin.php?page=w3tc_general#debug" class="button-primary">Enable it here</a>

	<?php Util_Ui::postbox_footer(); ?>
</div>
