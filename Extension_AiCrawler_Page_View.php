<?php
/**
 * File: Extension_AiCrawler_Page_View.php
 *
 * @package W3TC
 * @since   X.X.X
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

$config = Dispatcher::config();


?>
<p>
	<?php esc_html_e( 'AI Cralwer service is currently ', 'w3-total-cache' ); ?>
	<?php
	if ( Extension_AiCrawler_Plugin::is_enabled() ) {
		echo '<span class="w3tc-enabled">' . esc_html__( 'enabled.', 'w3-total-cache' ) . '</span>';
	} else {
		echo '<span class="w3tc-disabled">' . esc_html__( 'disabled.', 'w3-total-cache' ) . '</span>';
	}
	?>
<p>
<h2><?php esc_html_e( 'AI Crawler Extension', 'w3-total-cache' ); ?></h2>
<form id="w3tc-aicrawler-settings" action="admin.php?page=w3tc_aicrawler&amp;action=view" method="post">
	<?php
	Util_Ui::print_control_bar( 'extension_aicrawler_form_control' );

	echo wp_kses(
		Util_Ui::nonce_field( 'w3tc' ),
		array(
			'input' => array(
				'type'  => array(),
				'name'  => array(),
				'value' => array(),
			),
		)
	);

	require __DIR__ . '/Extension_AiCrawler_Page_View_Settings.php';
	require __DIR__ . '/Extension_AiCrawler_Page_View_Tools.php';
	?>
</form>
<?php require W3TC_INC_DIR . '/options/common/footer.php'; ?>
