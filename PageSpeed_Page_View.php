<?php
/**
 * File: PageSpeed_Page_View.php
 *
 * @package W3TC
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

require W3TC_INC_DIR . '/options/common/header.php';

?>
<div id="w3tcps_container">
	<div class="w3tcps_content">
		<div id="w3tcps_home">
			<h3><?php esc_html_e( 'Homepage', 'w3-total-cache' ); ?></h3>
			<div class="page_post">
				<p class="page_post_url" page_post_id="<?php echo esc_attr( get_option( 'page_on_front' ) ); ?>"></p>
				<div class="w3tcps_feedback">
					<div class="w3tcps_loading w3tc_none"></div>
					<div class="notice notice-error inline w3tcps_error w3tc_none"><p><?php esc_html_e( 'An unknown error occurred', 'w3-total-cache' ); ?></p></div>
				</div>
				<div id="<?php echo esc_attr( get_option( 'page_on_front' ) ); ?>" class="page_post_psresults"></div>
				<input class="button w3tcps_analyze" type="button" value="<?php esc_attr_e( 'Analyze', 'w3-total-cache' ); ?>" />
			</div>
		</div>
	</div>
</div>
