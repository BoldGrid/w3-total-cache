<?php
/**
 * File: Extension_Sample_Page_View.php
 *
 * @package W3TC
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}
?>
<?php require W3TC_INC_DIR . '/options/common/header.php'; ?>

<h2><?php esc_html_e( 'Sample Extension', 'w3-total-cache' ); ?></h2>
<p><?php esc_html_e( 'Coming Soon', 'w3-total-cache' ); ?></p>

<?php require W3TC_INC_DIR . '/options/common/footer.php'; ?>
