<?php
namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}
do_action( 'w3tc-dashboard-head' );
?>
<div class="wrap" id="w3tc">
	<?php Util_Ui::print_breadcrumb(); ?>
