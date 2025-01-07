<?php
/**
 * File: Support_Page_View_DoneContent.php
 *
 * @package W3TC
 */

namespace W3TC;

require W3TC_INC_DIR . '/options/common/header.php';
?>
<div style="text-align: center; font-weight: bold; margin-top: 50px">
	Thank you for filling out the form
</div>
<iframe src="<?php echo esc_attr( $postprocess_url ); ?>" width="0" height="0"></iframe>
