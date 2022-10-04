<?php
/**
 * File: PageSpeed_Page_View.php
 *
 * Default PageSpeed page template.
 *
 * @since 2.3.0 Update to utilize OAuth2.0 and overhaul of feature.
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
	<div id="w3tcps_intro">
		<h1><?php esc_html_e( 'Google PageSpeed', 'w3-total-cache' ); ?></h1>
		<p><?php esc_html_e( 'This tool will analyze your website\'s homepage using the Google PageSpeed Insights API to gather desktop/mobile performance metrics. Additionally for each metric W3 Total Cache will include an explaination of the metric and our recommendation for achieving improvments via W3 Total Cache features/extensions if available.', 'w3-total-cache' ); ?></p>
	</div>
	<div id="w3tcps_home" class="w3tcps_content">
		<div class="page_post">
			<h3 class="page_post_url" page_post_id="<?php echo esc_attr( get_option( 'page_on_front' ) ); ?>" page_post_url="<?php echo esc_attr( network_home_url() ); ?>">
				<input class="button w3tcps_analyze w3tc_none" type="button" value="<?php esc_attr_e( 'Refresh Analysis', 'w3-total-cache' ); ?>" />
			</h3>
			<div class="w3tcps_feedback">
				<div class="w3tcps_loading w3tc_none"></div>
				<div class="notice notice-error inline w3tcps_error w3tc_none"><p><?php esc_html_e( 'An unknown error occurred', 'w3-total-cache' ); ?></p></div>
				<div class="notice notice-info inline w3tcps_missing_token w3tc_none"><p><?php esc_html_e( 'Google PageSpeed Insights authorization required', 'w3-total-cache' ); ?></p></div>
			</div>
			<div id="<?php echo esc_attr( get_option( 'page_on_front' ) ); ?>" class="page_post_psresults"></div>
		</div>
	</div>
</div>
