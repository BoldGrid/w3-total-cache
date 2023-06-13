<?php
namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}
?>
<div id="w3tc_extensions">
	<form action="admin.php?page=<?php echo esc_attr( $this->_page ); ?><?php echo $extension ? '&extension=' . esc_attr( $extension ) . '&action=view' : ''; ?>" method="post">
		<div class="metabox-holder <?php echo $extension ? 'extension-settings' : ''; ?>">
			<?php require W3TC_INC_OPTIONS_DIR . "/extensions/$sub_view.php"; ?>
		</div>
	</form>
</div>
