<?php
/**
 * File: extensions.php
 *
 * @package W3TC
 */

namespace W3TC;

defined( 'ABSPATH' ) || exit;
if ( ! defined( 'W3TC' ) ) {
	die();
}
?>
<div id="w3tc_extensions">
	<form action="admin.php?page=<?php echo esc_attr( $this->_page ); ?><?php echo $w3tc_extension ? '&extension=' . esc_attr( $w3tc_extension ) . '&action=view' : ''; ?>" method="post">
		<div class="metabox-holder <?php echo $w3tc_extension ? 'extension-settings' : ''; ?>">
			<?php require W3TC_INC_OPTIONS_DIR . "/extensions/$sub_view.php"; ?>
		</div>
	</form>
</div>
