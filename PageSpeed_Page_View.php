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
		<p>
			<?php
			echo esc_html(
				sprintf(
					// translators: 1 cache lifetime.
					__(
						'This tool will analyze your website\'s homepage using the Google PageSpeed Insights API to gather desktop/mobile performance metrics. Additionally for each metric W3 Total Cache will include an explaination of the metric and our recommendation for achieving improvments via W3 Total Cache features/extensions if available. Results will be cached for a maximum of %1$s but will forcibly refresh via the "Refresh Analysis" button.',
						'w3-total-cache'
					),
					Util_PageSpeed::seconds_to_str( Util_PageSpeed::get_cache_life() )
				)
			);
			?>
		</p>
	</div>
	<div id="w3tcps_home" class="w3tcps_content">
		<div class="page_post">
			<div class="w3tcps_buttons w3tc_none" page_post_id="<?php echo esc_attr( get_option( 'page_on_front' ) ); ?>" page_post_url="<?php echo esc_attr( network_home_url() ); ?>">
				<span class="w3tcps_timestamp_label"><?php esc_html_e( 'Analysis last run ', 'w3-total-cache' ); ?></span>
				<span class="w3tcps_timestamp"></span>
				<input class="button w3tcps_analyze" type="button" value="<?php esc_attr_e( 'Refresh Analysis', 'w3-total-cache' ); ?>" />
			</div>
			<div class="w3tcps_feedback">
				<div class="w3tcps_loading w3tc_none">
					<img src="/wp-includes/js/tinymce/skins/lightgray/img/loader.gif" alt="loader.gif">
					<p><?php esc_html_e( 'Running Analysis. This may take up to 2 minutes.', 'w3-total-cache' ); ?></p>
				</div>
				<div class="notice notice-error inline w3tcps_error w3tc_none"><p><?php esc_html_e( 'An unknown error occurred', 'w3-total-cache' ); ?></p></div>
				<div class="notice notice-info inline w3tcps_missing_token w3tc_none">
					<p>
						<?php
						echo wp_kses(
							sprintf(
								// translators: 1 HTML a tag to W3TC settings page Google PageSpeed meta box.
								__(
									'It appears that your Google Access token is either missing, expired, or invalid. Please click %1$s to obtain a new Google access token or to refresh an expired one.',
									'w3-total-cache'
								),
								'<a href="' . esc_url( Util_Ui::admin_url( 'admin.php?page=w3tc_general#google_page_speed' ) ) . '" target="_blank">' . esc_html__( 'here', 'w3-total-cache' ) . '</a>'
							),
							Util_PageSpeed::get_allowed_tags()
						);
						?>
					</p>
				</div>
			</div>
			<div id="<?php echo esc_attr( get_option( 'page_on_front' ) ); ?>" class="page_post_psresults"></div>
		</div>
	</div>
</div>
