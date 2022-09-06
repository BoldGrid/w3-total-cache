<?php
/**
 * File: PageSpeed_Widget_View_FromApi.php
 *
 * @package W3TC
 */

namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}

?>
<div class="metabox-holder">
	<?php
	if ( ! empty( $api_response_error ) ) {
		echo wp_kses(
			'<div class="w3tcps_feedback"><div class="notice notice-error inline w3tcps_error">' . $api_response_error . '</div></div>',
			array(
				'div' => array(
					'class' => array(),
				),
				'br'  => array(),
			)
		);
	} elseif ( empty( $api_response['desktop'] ) || empty( $api_response['mobile'] ) ) {
		echo '<div class="w3tcps_feedback"><div class="notice notice-error inline w3tcps_error">' . esc_html__( 'An unknown error has occured!', 'w3-total-cache' ) . '</div></div>';
	} else {
		?>
		<div id="w3tcps_legend">
			<div class="w3tcps_gages">
				<div class="w3tcps_gauge_desktop">
					<?php Util_PageSpeed::print_gauge( $api_response['desktop'], 'desktop' ); ?>
				</div>
				<div class="w3tcps_gauge_mobile">
					<?php Util_PageSpeed::print_gauge( $api_response['mobile'], 'smartphone' ); ?>
				</div>
			</div>
			<?php
			echo wp_kses(
				sprintf(
					// translators: 1 opening HTML span tag, 2 opening HTML a tag to web.dev/performance-soring, 3 closing HTML a tag,
					// translators: 4 closing HTML span tag, 5 opening HTML a tag to googlechrome.github.io Lighthouse Score Calculator,
					// translators: 6 closing HTML a tag.
					__(
						'%1$sValues are estimated and may vary. The %2$sperformance score is calculated%3$s directly from these metrics.%4$s%5$sSee calculator.%6$s',
						'w3-total-cache'
					),
					'<span>',
					'<a rel="noopener" target="_blank" href="' . esc_url( 'https://web.dev/performance-scoring/?utm_source=lighthouse&amp;utm_medium=lr' ) . '">',
					'</a>',
					'</span>',
					'<a target="_blank" href="' . esc_url( 'https://googlechrome.github.io/lighthouse/scorecalc/#FCP=1028&amp;TTI=1119&amp;SI=1028&amp;TBT=18&amp;LCP=1057&amp;CLS=0&amp;FMP=1028&amp;device=desktop&amp;version=9.0.0' ) . '">',
					'</a>'
				),
				array(
					'span' => array(),
					'a'    => array(
						'rel'    => array(),
						'target' => array(),
						'href'   => array(),
					),
				)
			);
			?>
			<div class="w3tcps_ranges">
				<span class="w3tcps_range w3tcps_fail"><?php esc_html_e( '0–49', 'w3-total-cache' ); ?></span> 
				<span class="w3tcps_range w3tcps_average"><?php esc_html_e( '50–89', 'w3-total-cache' ); ?></span> 
				<span class="w3tcps_range w3tcps_pass"><?php esc_html_e( '90–100', 'w3-total-cache' ); ?></span> 
			</div>
		</div>
		<div id="w3tcps_widget_metrics_container" class="tab-content w3tcps_content">
			<div class="w3tcps_widget_metrics">
				<?php Util_PageSpeed::print_bar( $api_response, 'first-contentful-paint', 'First Contentful Paint' ); ?>
				<?php Util_PageSpeed::print_bar( $api_response, 'speed-index', 'Speed Index' ); ?>
				<?php Util_PageSpeed::print_bar( $api_response, 'largest-contentful-paint', 'Largest Contentful Paint' ); ?>
				<?php Util_PageSpeed::print_bar( $api_response, 'interactive', 'Time to Interactive' ); ?>
				<?php Util_PageSpeed::print_bar( $api_response, 'total-blocking-time', 'Total Blocking Time' ); ?>
				<?php Util_PageSpeed::print_bar( $api_response, 'cumulative-layout-shift', 'Cumulative Layout Shift' ); ?>
			</div>
		</div>
		<?php
	}
	?>
</div>
