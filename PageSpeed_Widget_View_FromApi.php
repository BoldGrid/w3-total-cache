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

/**
 * HTTP GET URL query value.
 *
 * @var string
 */
$encoded_url = Util_Request::get( 'url' );
$url         = ( ! empty( $encoded_url ) ? urldecode( $encoded_url ) : get_home_url() );

/**
 * Google PageSpeed Insights API response and error. The response can be cached via GET query key/value "&cache=yes".
 *
 * @var string
 */
$api_response       = null;
$api_response_error = null;

/**
 * Get score guage color
 *
 * @param int $score PageSpeed desktop/mobile score.
 *
 * @return string
 */
function w3tcps_gauge_color( $score ) {
	$color = '#fff';
	if ( ! empty( $score ) && is_numeric( $score ) ) {
		if ( $score >= 90 ) {
			$color = '#0c6';
		} elseif ( $score >= 50 && $score < 90 ) {
			$color = '#fa3';
		} elseif ( $score >= 0 && $score < 50 ) {
			$color = '#f33';
		}
	}
	return $color;
}

/**
 * Get score guage angle
 *
 * @param int $score PageSpeed desktop/mobile score.
 *
 * @return int
 */
function w3tcps_gauge_angle( $score ) {
	return ( ! empty( $score ) ? ( $score / 100 ) * 180 : 0 );
}

/**
 * Render the PageSpeed desktop/mobile score guage
 *
 * @param array  $data PageSpeed data.
 * @param string $icon Desktop/Mobile icon.
 *
 * @return void
 */
function w3tcps_gauge( $data, $icon ) {
	if ( ! isset( $data ) || empty ( $data['score'] ) || empty ( $icon ) ) {
		return;
	}

	$color = w3tcps_gauge_color( $data['score'] );
	$angle = w3tcps_gauge_angle( $data['score'] );

	?>
	<div class="gauge" style="width: 120px; --rotation:<?php echo esc_attr( $angle ); ?>deg; --color:<?php echo esc_attr( $color ); ?>; --background:#888;">
		<div class="percentage"></div>
		<div class="mask"></div>
		<span class="value">
			<i class="material-icons" aria-hidden="true"><?php echo esc_html( $icon ); ?></i>
			<?php echo ( ! empty( $data['score'] ) ? esc_html( $data['score'] ) : '' ); ?>
		</span>
	</div>
	<?php
}

/**
 * Render metric barline
 *
 * @param array $metric PageSpeed desktop/mobile score.
 *
 * @return void
 */
function w3tcps_barline( $metric ) {
	if ( empty( $metric['score'] ) ) {
		return;
	}

	$metric['score'] *= 100;

	$bar = '';

	if ( $metric['score'] >= 90 ) {
		$bar = '<div style="flex-grow: ' . $metric['score'] . '"><span class="w3tcps_range w3tcps_pass">' . $metric['displayValue'] . '</span></div>';
	} elseif ( $metric['score'] >= 50 && $metric['score'] < 90 ) {
		$bar = '<div style="flex-grow: ' . $metric['score'] . '"><span class="w3tcps_range w3tcps_average">' . $metric['displayValue'] . '</span></div>';
	} elseif ( $metric['score'] < 50 ) {
		$bar = '<div style="flex-grow: ' . $metric['score'] . '"><span class="w3tcps_range w3tcps_fail">' . $metric['displayValue'] . '<span></div>';
	}

	echo wp_kses(
		'<div class="w3tcps_barline">' . $bar . '</div>',
		array(
			'div'  => array(
				'style' => array(),
				'class' => array(),
			),
			'span' => array(
				'class' => array(),
			),
		)
	);
}

/**
 * [Description for w3tcps_bar]
 *
 * @param array  $data PageSpeed desktop/mobile score.
 * @param string $metric Metric key.
 * @param string $name Metric name.
 * @param string $icon Desktop/Mobile icon.
 *
 * @return void
 */
function w3tcps_bar( $data, $metric, $name ) {
	if ( ! isset( $data ) || empty ( $metric ) || empty ( $name ) ) {
		return;
	}

	?>
	<div class="w3tcps_metric">
		<h3 class="w3tcps_metric_title"><?php echo esc_html( $name ); ?></h3>
		<div class="w3tcps_metric_stats">
			<i class="material-icons" aria-hidden="true"><?php echo esc_html( 'computer' ); ?></i> 
			<?php w3tcps_barline( $data['desktop'][ $metric ] ); ?>
			<i class="material-icons" aria-hidden="true"><?php echo esc_html( 'smartphone' ); ?></i> 
			<?php w3tcps_barline( $data['mobile'][ $metric ] ); ?>
		</div>
	</div>
	<?php
}

if ( Util_Request::get( 'cache' ) !== 'no' ) {
	$cached_api_response = get_transient( 'w3tc_pagespeed_data_' . $encoded_url );
	$cached_api_response = @json_decode( $cached_api_response, true );
	if ( is_array( $cached_api_response ) && isset( $cached_api_response['time'] ) && $cached_api_response['time'] >= time() - 3600 ) {
		$api_response = $cached_api_response;
	}
}

if ( is_null( $api_response ) ) {
	$config       = Dispatcher::config();
	$access_token = ( ! empty( $config->get_string( 'widget.pagespeed.access_token' ) ) ? $config->get_string( 'widget.pagespeed.access_token' ) : null );

	if ( empty( $access_token ) ) {
		$api_response_error = sprintf(
			// translators: 1 HTML a tag to W3TC settings page Google PageSpeed meta box.
			__(
				'It appears that your Google Access token is either missing, expired, or invalid. Please click %1$s to obtain a new Google access token or to refresh an expired one.',
				'w3-total-cache'
			),
			'<a href="' . filter_var( '/wp-admin/admin.php?page=w3tc_general#google_page_speed', FILTER_SANITIZE_URL ) . '">' . esc_html__( 'here', 'w3-total-cache' ) . '</a>'
		);
	}

	$w3_pagespeed = new PageSpeed_Api( $access_token );

	$w3_pagespeed->refresh_token_check();

	$api_response = $w3_pagespeed->analyze( $url );
	$api_response['time'] = time();
}

if ( ! $api_response ) {
	$api_response_error = sprintf(
		// translators: 1 Request URL value.
		__(
			'API request failed<br/><br/>
				Analyze URL: %1$s',
			'w3-total-cache'
		),
		$url
	);
	delete_transient( 'w3tc_pagespeed_data_' . $encoded_url );
} elseif ( ! empty( $api_response['error'] ) ) {
	$api_response_error = sprintf(
		// translators: 1 Request URL value, 2 Request response code, 3 Error message.
		__(
			'API request error!<br/><br/>
				Analyze URL: %1$s<br/><br/>
				Response Code: %2$s<br/>
				Response Message: %3$s<br/>',
			'w3-total-cache'
		),
		$url,
		! empty( $api_response['error']['code'] ) ? $api_response['error']['code'] : 'N/A',
		! empty( $api_response['error']['message'] ) ? $api_response['error']['message'] : 'N/A'
	);
	delete_transient( 'w3tc_pagespeed_data_' . $encoded_url );
} elseif ( ! empty( $api_response['mobile']['error'] ) || ! empty( $api_response['desktop']['error'] ) ) {
	$api_response_error = sprintf(
		// translators: 1 Request URL value, 2 Request response code, 3 Error message.
		__(
			'API request error!<br/><br/>
				Analyze URL: %1$s<br/><br/>
				Mobile response Code: %2$s<br/>Mobile response Message: %3$s<br/><br/>
				Desktop response Code: %4$s<br/>Desktop response Message: %5$s',
			'w3-total-cache'
		),
		$url,
		! empty( $api_response['mobile']['error']['code'] ) ? $api_response['mobile']['error']['code'] : 'N/A',
		! empty( $api_response['mobile']['error']['message'] ) ? $api_response['mobile']['error']['message'] : 'N/A',
		! empty( $api_response['desktop']['error']['code'] ) ? $api_response['desktop']['error']['code'] : 'N/A',
		! empty( $api_response['desktop']['error']['message'] ) ? $api_response['desktop']['error']['message'] : 'N/A'
	);
	delete_transient( 'w3tc_pagespeed_data_' . $encoded_url );
} else {
	set_transient( 'w3tc_pagespeed_data_' . $encoded_url, wp_json_encode( $api_response ), 3600 );
}

if ( ! empty( $api_response_error ) ) {
	echo wp_kses(
		'<p class="notice notice-error">' . $api_response_error . '</p>',
		array(
			'p' => array(
				'class' => array(),
			),
			'br'  => array(),
		)
	);
} elseif ( empty( $api_response ) ) {
	echo '<p class="notice notice-error">' . esc_html__( 'An unknown error has occured!', 'w3-total-cache' ) . '</p>';
} else {
	?>
	<div class="metabox-holder">
		<div id="w3tcps_legend">
			<div class="w3tcps_gages">
				<div class="w3tcps_gauge_desktop">
					<?php w3tcps_gauge( $api_response[ 'desktop' ], 'computer' ); ?>
				</div>
				<div class="w3tcps_gauge_mobile">
					<?php w3tcps_gauge( $api_response[ 'mobile' ], 'smartphone' ); ?>
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
				<?php w3tcps_bar( $api_response, 'first-contentful-paint', 'First Contentful Paint' ); ?>
				<?php w3tcps_bar( $api_response, 'speed-index', 'Speed Index' ); ?>
				<?php w3tcps_bar( $api_response, 'largest-contentful-paint', 'Largest Contentful Paint' ); ?>
				<?php w3tcps_bar( $api_response, 'interactive', 'Time to Interactive' ); ?>
				<?php w3tcps_bar( $api_response, 'total-blocking-time', 'Total Blocking Time' ); ?>
				<?php w3tcps_bar( $api_response, 'cumulative-layout-shift', 'Cumulative Layout Shift' ); ?>
			</div>
		</div>
	</div>
	<?php
}
?>