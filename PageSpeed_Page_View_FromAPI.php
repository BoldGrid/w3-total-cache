<?php
/**
 * File: PageSpeed_Page_View_FromAPI.php
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
	if ( ! empty( $score ) && $score >= 90 ) {
		$color = '#0c6';
	} elseif ( ! empty( $score ) && $score >= 50 && $score < 90 ) {
		$color = '#fa3';
	} elseif ( ! empty( $score ) && $score >= 0 && $score < 50 ) {
		$color = '#f33';
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
 * Get PageSpeed metric notice BG
 *
 * @param int $score PageSpeed desktop/mobile score.
 *
 * @return string
 */
function w3tcps_breakdown_bg( $score ) {
	$notice = 'notice notice-info inline';
	if ( $score >= 90 ) {
		$notice = 'notice notice-success inline';
	} elseif ( $score >= 50 && $score < 90 ) {
		$noitce = 'notice notice-warning inline';
	} elseif ( $score > 0 && $score < 50 ) {
		$notice = 'notice notice-error inline';
	}
	return $notice;
}

/**
 * Get PageSpeed metric grade
 *
 * @param int $score PageSpeed desktop/mobile score.
 *
 * @return string
 */
function w3tcps_breakdown_grade( $score ) {
	$grade = 'w3tcps_blank';
	if ( $score >= 90 ) {
		$grade = 'w3tcps_pass';
	} elseif ( $score >= 50 && $score < 90 ) {
		$grade = 'w3tcps_average';
	} elseif ( $score > 0 && $score < 50 ) {
		$grade = 'w3tcps_fail';
	}
	return $grade;
}

/**
 * Render the final generated screenshot
 *
 * @param array $data PageSpeed data.
 *
 * @return void
 */
function w3tcps_final_screenshot( $data ) {
	echo '<img src="' . esc_attr( $data['screenshots']['final']['screenshot'] ) . '" alt="' . esc_attr( $data['screenshots']['final']['title'] ) . '"/>';
}

/**
 * Render all "building" screenshots
 *
 * @param mixed $data PageSpeed desktop/mobile score.
 *
 * @return void
 */
function w3tcps_screenshots( $data ) {
	foreach ( $data['screenshots']['other']['screenshots'] as $screenshot ) {
		echo '<img src="' . esc_attr( $screenshot['data'] ) . '" alt="' . esc_attr( $data['screenshots']['other']['title'] ) . '"/>';
	}
}

/**
 * Render all metric data into listable items
 *
 * @param array $data PageSpeed desktop/mobile score.
 *
 * @return void
 */
function w3tcps_breakdown( $data ) {
	$opportunities = '';
	$diagnostics   = '';
	$passed_audits = '';

	foreach ( $data['opportunities'] as $opportunity ) {
		if ( empty( $opportunity['details'] ) ) {
			continue;
		}

		$opportunity['score'] *= 100;

		$notice = 'notice notice-info inline';
		$grade  = 'w3tcps_blank';
		if ( ! empty( $opportunity['score'] ) ) {
			$notice = w3tcps_breakdown_bg( $opportunity['score'] );
			$grade  = w3tcps_breakdown_grade( $opportunity['score'] );
		}

		$audit_classes = '';
		if ( ! empty( $opportunity['type'] ) ) {
			foreach ( $opportunity['type'] as $type ) {
				$audit_classes .= ' ' . $type;
			}
		}

		$opportunity['description'] = preg_replace( '/(.*)(\[Learn more\])\((.*?)\)(.*)/i', '$1<a href="$3">$2</a>$4', $opportunity['description'] );

		$headers = '';
		$items   = '';

		foreach ( $opportunity['details'] as $item ) {
			$headers = '';
			$items  .= '<tr class="w3tcps_passed_audit_item">';
			if ( ! empty( $item['url'] ) ) {
				$headers .= '<th>URL</th>';
				$items   .= '<td>...' . wp_parse_url( $item['url'] )['path'] . '</td>';
			}
			if ( ! empty( $item['totalBytes'] ) ) {
				$headers .= '<th>Total Bytes</th>';
				$items   .= '<td>' . $item['totalBytes'] . '</td>';
			}
			if ( ! empty( $item['wastedBytes'] ) ) {
				$headers .= '<th>Wasted Bytes</th>';
				$items   .= '<td>' . $item['wastedBytes'] . '</td>';
			}
			if ( ! empty( $item['wastedPercent'] ) ) {
				$headers .= '<th>Wasted Percentage</th>';
				$items   .= '<td>' . round( $item['wastedPercent'], 2 ) . '%</td>';
			}
			if ( ! empty( $item['wastedMs'] ) ) {
				$headers .= '<th>Wasted Miliseconds</th>';
				$items   .= '<td>' . round( $item['wastedMs'], 2 ) . '</td>';
			}
			if ( ! empty( $item['label'] ) ) {
				$headers .= '<th>Type</th>';
				$items   .= '<td>' . $item['label'] . '</td>';
			}
			if ( ! empty( $item['groupLabel'] ) ) {
				$headers .= '<th>Group</th>';
				$items   .= '<td>' . $item['groupLabel'] . '</td>';
			}
			if ( ! empty( $item['requestCount'] ) ) {
				$headers .= '<th>Requests</th>';
				$items   .= '<td>' . $item['requestCount'] . '</td>';
			}
			if ( ! empty( $item['transferSize'] ) ) {
				$headers .= '<th>Transfer Size</th>';
				$items   .= '<td>' . $item['transferSize'] . '</td>';
			}
			if ( ! empty( $item['startTime'] ) ) {
				$headers .= '<th>Start Time</th>';
				$items   .= '<td>' . $item['startTime'] . '</td>';
			}
			if ( ! empty( $item['duration'] ) ) {
				$headers .= '<th>Duration</th>';
				$items   .= '<td>' . $item['duration'] . '</td>';
			}
			if ( ! empty( $item['scriptParseCompile'] ) ) {
				$headers .= '<th>Parse/Compile Time</th>';
				$items   .= '<td>' . $item['scriptParseCompile'] . '</td>';
			}
			if ( ! empty( $item['scripting'] ) ) {
				$headers .= '<th>Execution Time</th>';
				$items   .= '<td>' . $item['scripting'] . '</td>';
			}
			if ( ! empty( $item['total'] ) ) {
				$headers .= '<th>Total</th>';
				$items   .= '<td>' . $item['total'] . '</td>';
			}
			if ( ! empty( $item['cacheLifetimeMs'] ) ) {
				$headers .= '<th>Cache Lifetime Miliseconds</th>';
				$items   .= '<td>' . $item['cacheLifetimeMs'] . '</td>';
			}
			if ( ! empty( $item['cacheHitProbability'] ) ) {
				$headers .= '<th>Cache Hit Probability</th>';
				$items   .= '<td>' . $item['cacheHitProbability'] . '</td>';
			}
			$items .= '</tr>';
		}

		if ( $opportunity['score'] >= 90 ) {
			$passed_audits .= '
				<div class="audits w3tcps_passed_audit' . $audit_classes . ' ' . $notice . '">
					<span class="w3tcps_breakdown_items_toggle w3tcps_range chevron_down ' . $grade . '">' . $opportunity['title'] . ' - ' . $opportunity['displayValue'] . '</span>
					<div class="w3tcps_breakdown_items w3tcps_pass_audit_items">
						<p>' . $opportunity['description'] . '</p>
						<table class="w3tcps_item_table">
							<tr class="w3tcps_passed_audit_item_header">' . $headers . '</tr>' . $items . '
						</table>
						<div class="w3tcps_instruction">
							<div class="w3tc_fancy_header">
								<img class="w3tc_fancy_icon" src="' . esc_url( plugins_url( '/w3-total-cache/pub/img/w3tc_cube-shadow.png' ) ) . '" />
								<div class="w3tc_fancy_title">
									<span>TOTAL</span> <span>CACHE</span><span>:</span> <span>Our Recommendation</span>
								</div>
							</div>
							<div class="w3tc_instruction_copy">' . $opportunity['instruction'] . '</div>
						</div>
					</div>
				</div>';
		} else {
			$opportunities .= '
				<div class="audits w3tcps_opportunities' . $audit_classes . ' ' . $notice . '">
					<span class="w3tcps_breakdown_items_toggle w3tcps_range chevron_down ' . $grade . '">' . $opportunity['title'] . ' - ' . $opportunity['displayValue'] . '</span>
					<div class="w3tcps_breakdown_items w3tcps_opportunity_items">
						<p>' . $opportunity['description'] . '</p>
						<table class="w3tcps_item_table">
							<tr class="w3tcps_passed_audit_item_header">' . $headers . '</tr>' . $items . '
						</table>
						<div class="w3tcps_instruction">
							<div class="w3tc_fancy_header">
								<img class="w3tc_fancy_icon" src="' . esc_url( plugins_url( '/w3-total-cache/pub/img/w3tc_cube-shadow.png' ) ) . '" />
								<div class="w3tc_fancy_title">
									<span>TOTAL</span> <span>CACHE</span><span>:</span> <span>Our Recommendation</span>
								</div>
							</div>
							<div class="w3tc_instruction_copy">' . $opportunity['instruction'] . '</div>
						</div>
					</div>
				</div>';
		}
	}

	foreach ( $data['diagnostics'] as $diagnostic ) {
		if ( empty( $diagnostic['details'] ) ) {
			continue;
		}

		$diagnostic['score'] *= 100;

		$notice = 'notice notice-info inline';
		$grade  = 'w3tcps_blank';
		if ( ! empty( $diagnostic['score'] ) ) {
			$notice = w3tcps_breakdown_bg( $diagnostic['score'] );
			$grade  = w3tcps_breakdown_grade( $diagnostic['score'] );
		}

		$audit_classes = '';
		foreach ( $opportunity['type'] as $type ) {
			$audit_classes .= ' ' . $type;
		}

		$diagnostic['description'] = preg_replace( '/(.*)(\[Learn more\])\((.*?)\)(.*)/i', '$1<a href="$3">$2</a>$4', $diagnostic['description'] );

		$headers = '';
		$items   = '';
		foreach ( $diagnostic['details'] as $item ) {
			$headers = '';
			$items  .= '<tr class="w3tcps_passed_audit_item">';
			if ( ! empty( $item['url'] ) ) {
				$headers .= '<th>URL</th>';
				$items   .= '<td>...' . wp_parse_url( $item['url'] )['path'] . '</td>';
			}
			if ( ! empty( $item['totalBytes'] ) ) {
				$headers .= '<th>Total Bytes</th>';
				$items   .= '<td>' . $item['totalBytes'] . '</td>';
			}
			if ( ! empty( $item['wastedBytes'] ) ) {
				$headers .= '<th>Wasted Bytes</th>';
				$items   .= '<td>' . $item['wastedBytes'] . '</td>';
			}
			if ( ! empty( $item['wastedPercent'] ) ) {
				$headers .= '<th>Wasted Percentage</th>';
				$items   .= '<td>' . round( $item['wastedPercent'], 2 ) . '%</td>';
			}
			if ( ! empty( $item['wastedMs'] ) ) {
				$headers .= '<th>Wasted Miliseconds</th>';
				$items   .= '<td>' . round( $item['wastedMs'], 2 ) . '</td>';
			}
			if ( ! empty( $item['label'] ) ) {
				$headers .= '<th>Type</th>';
				$items   .= '<td>' . $item['label'] . '</td>';
			}
			if ( ! empty( $item['groupLabel'] ) ) {
				$headers .= '<th>Group</th>';
				$items   .= '<td>' . $item['groupLabel'] . '</td>';
			}
			if ( ! empty( $item['requestCount'] ) ) {
				$headers .= '<th>Requests</th>';
				$items   .= '<td>' . $item['requestCount'] . '</td>';
			}
			if ( ! empty( $item['transferSize'] ) ) {
				$headers .= '<th>Transfer Size</th>';
				$items   .= '<td>' . $item['transferSize'] . '</td>';
			}
			if ( ! empty( $item['startTime'] ) ) {
				$headers .= '<th>Start Time</th>';
				$items   .= '<td>' . $item['startTime'] . '</td>';
			}
			if ( ! empty( $item['duration'] ) ) {
				$headers .= '<th>Duration</th>';
				$items   .= '<td>' . $item['duration'] . '</td>';
			}
			if ( ! empty( $item['scriptParseCompile'] ) ) {
				$headers .= '<th>Parse/Compile Time</th>';
				$items   .= '<td>' . $item['scriptParseCompile'] . '</td>';
			}
			if ( ! empty( $item['scripting'] ) ) {
				$headers .= '<th>Execution Time</th>';
				$items   .= '<td>' . $item['scripting'] . '</td>';
			}
			if ( ! empty( $item['total'] ) ) {
				$headers .= '<th>Total</th>';
				$items   .= '<td>' . $item['total'] . '</td>';
			}
			if ( ! empty( $item['cacheLifetimeMs'] ) ) {
				$headers .= '<th>Cache Lifetime Miliseconds</th>';
				$items   .= '<td>' . $item['cacheLifetimeMs'] . '</td>';
			}
			if ( ! empty( $item['cacheHitProbability'] ) ) {
				$headers .= '<th>Cache Hit Probability</th>';
				$items   .= '<td>' . ( $item['cacheHitProbability'] * 100 ) . '%</td>';
			}
			$items .= '</tr>';
		}

		if ( $diagnostic['score'] >= 90 ) {
			$passed_audits .= '
				<div class="audits w3tcps_passed_audit' . $audit_classes . ' ' . $notice . '">
					<span class="w3tcps_breakdown_items_toggle w3tcps_range chevron_down ' . $grade . '">' . $diagnostic['title'] . ' - ' . $diagnostic['displayValue'] . '</span>
					<div class="w3tcps_breakdown_items w3tcps_pass_audit_items">
						<p>' . $diagnostic['description'] . '</p>
						<table class="w3tcps_item_table">
							<tr class="w3tcps_passed_audit_item_header">' . $headers . '</tr>' . $items . '
						</table>
						<div class="w3tcps_instruction">
							<div class="w3tc_fancy_header">
								<img class="w3tc_fancy_icon" src="' . esc_url( plugins_url( '/w3-total-cache/pub/img/w3tc_cube-shadow.png' ) ) . '" />
								<div class="w3tc_fancy_title">
									<span>TOTAL</span> <span>CACHE</span><span>:</span> <span>Our Recommendation</span>
								</div>
							</div>
							<div class="w3tc_instruction_copy">' . $diagnostic['instruction'] . '</div>
						</div>
					</div>
				</div>';
		} else {
			$diagnostics .= '
				<div class="audits w3tcps_diagnostics' . $audit_classes . ' ' . $notice . '">
					<span class="w3tcps_breakdown_items_toggle w3tcps_range chevron_down ' . $grade . '">' . $diagnostic['title'] . ' - ' . $diagnostic['displayValue'] . '</span>
					<div class="w3tcps_breakdown_items w3tcps_diagnostic_items">
						<p>' . $diagnostic['description'] . '</p>
						<table class="w3tcps_item_table">
							<tr class="w3tcps_passed_audit_item_header">' . $headers . '</tr>' . $items . '
						</table>
						<div class="w3tcps_instruction">
							<div class="w3tc_fancy_header">
								<img class="w3tc_fancy_icon" src="' . esc_url( plugins_url( '/w3-total-cache/pub/img/w3tc_cube-shadow.png' ) ) . '" />
								<div class="w3tc_fancy_title">
									<span>TOTAL</span> <span>CACHE</span><span>:</span> <span>Our Recommendation</span>
								</div>
							</div>
							<div class="w3tc_instruction_copy">' . $diagnostic['instruction'] . '</div>
						</div>
					</div>
				</div>';
		}
	}

	$allowed_tags = w3tcps_allowed_tags();

	echo '<div class="w3tcps_audit_results">';
	echo wp_kses(
		'<div class="opportunities"><h3 class="w3tcps_metric_title">' . esc_html__( 'Opportunities', 'w3-total-cache' ) . '</h3>' . $opportunities . '</div>',
		$allowed_tags
	);
	echo wp_kses(
		'<div class="diagnostics"><h3 class="w3tcps_metric_title">' . esc_html__( 'Diagnostics', 'w3-total-cache' ) . '</h3>' . $diagnostics . '</div>',
		$allowed_tags
	);
	echo wp_kses(
		'<div class="passed_audits"><h3 class="w3tcps_metric_title">' . esc_html__( 'Passed Audits', 'w3-total-cache' ) . '</h3>' . $passed_audits . '</div>',
		$allowed_tags
	);
	echo '</div>';
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
function w3tcps_bar( $data, $metric, $name, $icon ) {
	if ( empty( $data[ $metric ] ) ) {
		return;
	}

	?>
	<div class="w3tcps_metric">
		<h3 class="w3tcps_metric_title"><?php echo esc_html( $name ); ?></h3>
		<div class="w3tcps_metric_stats">
			<i class="material-icons" aria-hidden="true"><?php echo esc_html( $icon ); ?></i> 
			<?php w3tcps_barline( $data[ $metric ] ); ?>
		</div>
	</div>
	<?php
}

/**
 * Return wp_kses allowed HTML tags/attributes.
 *
 * @return array
 */
function w3tcps_allowed_tags() {
	return array(
		'div'   => array(
			'id'    => array(),
			'class' => array(),
		),
		'span'  => array(
			'id'    => array(),
			'class' => array(),
		),
		'p'     => array(
			'id'    => array(),
			'class' => array(),
		),
		'table' => array(
			'id'    => array(),
			'class' => array(),
		),
		'tr'    => array(
			'id'    => array(),
			'class' => array(),
		),
		'td'    => array(
			'id'    => array(),
			'class' => array(),
		),
		'th'    => array(
			'id'    => array(),
			'class' => array(),
		),
		'b'     => array(
			'id'    => array(),
			'class' => array(),
		),
		'br'    => array(),
		'a'     => array(
			'id'     => array(),
			'class'  => array(),
			'href'   => array(),
			'target' => array(),
			'rel'    => array(),
		),
		'link'  => array(
			'id'    => array(),
			'class' => array(),
			'href'  => array(),
			'rel'   => array(),
			'as'    => array(),
			'type'  => array(),
		),
		'code'  => array(
			'id'    => array(),
			'class' => array(),
		),
		'img'   => array(
			'id'     => array(),
			'class'  => array(),
			'srcset' => array(),
			'src'    => array(),
			'alt'    => array(),
		),
		'ul'    => array(
			'id'    => array(),
			'class' => array(),
		),
		'ol'    => array(
			'id'    => array(),
			'class' => array(),
		),
		'li'    => array(
			'id'    => array(),
			'class' => array(),
		),
		'h3'    => array(
			'id'    => array(),
			'class' => array(),
		),
	);
}

if ( Util_Request::get( 'cache' ) !== 'no' ) {
	$r = get_transient( 'w3tc_pagespeed_data_' . $encoded_url );
	$r = @json_decode( $r, true );
	if ( is_array( $r ) && isset( $r['time'] ) && $r['time'] >= time() - 3600 ) {
		$api_response = $r;
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
	}

	$api_response['time'] = time();

	set_transient( 'w3tc_pagespeed_data_' . $encoded_url, wp_json_encode( $api_response ), 3600 );
}

/**
 * Google PageSpeed Insights API response data.
 *
 * @var array
 */
$r = $api_response;

/**
 * Get the active tab and icon from the $_GET param.
 *
 * @var string
 */
$current_tab  = ( ! empty( $_GET['tab'] ) ? Util_Request::get( 'tab' ) : 'mobile' );

?>
<div id="w3tcps_container">
	<div class="w3tcps_content">
		<div id="w3tcps_home">
			<div class="page_post">
				<p class="page_post_url" page_post_id="<?php echo esc_attr( get_option( 'page_on_front' ) ); ?>"></p>
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
				} elseif ( empty( $api_response ) ) {
					echo '<div class="w3tcps_feedback"><div class="notice notice-error inline w3tcps_error">' . esc_html__( 'An unknown error has occured!', 'w3-total-cache' ) . '</div></div>';
				} else {
					?>
					<div id="w3tc" class="wrap">
						<h1><?php esc_html_e( 'Google PageSpeed Insights Results', 'w3-total-cache' ); ?></h1>
						<nav class="nav-tab-wrapper">
							<a href="#" id="w3tcps_control_mobile" class="nav-tab <?php echo ( 'mobile' === $current_tab ? 'nav-tab-active' : '' ); ?>"><?php esc_html_e( 'Mobile', 'w3-total-cache' ); ?></a>
							<a href="#" id="w3tcps_control_desktop" class="nav-tab <?php echo ( 'desktop' === $current_tab ? 'nav-tab-active' : '' ); ?>"><?php esc_html_e( 'Desktop', 'w3-total-cache' ); ?></a>
						</nav>
						<div class="metabox-holder">
							<?php
							$analysis_types = array(
								'desktop' => 'computer',
								'mobile'  => 'smartphone',
							);
							foreach ( $analysis_types as $analysis_type => $icon ) {
								?>
								<div id="w3tcps_<?php echo esc_attr( $analysis_type ); ?>" class="tab-content w3tcps_content">
									<div id="w3tcps_legend_<?php echo esc_attr( $analysis_type ); ?>">
										<?php Util_Ui::postbox_header( __( 'Legend', 'w3-total-cache' ), '', 'gps-legend' ); ?>
										<div class="w3tcps_gauge_<?php echo esc_attr( $analysis_type ); ?>">
											<?php w3tcps_gauge( $r[ $analysis_type ], $icon ); ?>
										</div>
										<?php
										echo wp_kses(
											sprintf(
												// translators: 1 opening HTML span tag, 2 opening HTML a tag to web.dev/performance-soring, 3 closing HTML a tag,
												// translators: 4 closing HTML span tag, 5 opening HTML a tag to googlechrome.github.io Lighthouse Score Calculator,
												// translators: 6 closing HTML a tag.
												__(
													'%1$sValues are estimated and may vary. The %2$sperformance score is calculated%3$s directly from these metrics.%4%$s%5$sSee calculator.%6$s',
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
										<?php Util_Ui::postbox_footer(); ?>
									</div>
									<div class="w3tcps_metrics_<?php echo esc_attr( $analysis_type ); ?>">
										<?php Util_Ui::postbox_header( __( 'Core Metrics', 'w3-total-cache' ), '', 'gps-core-metrics' ); ?>
										<?php w3tcps_bar( $r[ $analysis_type ], 'first-contentful-paint', 'First Contentful Paint', $icon ); ?>
										<?php w3tcps_bar( $r[ $analysis_type ], 'speed-index', 'Speed Index', $icon ); ?>
										<?php w3tcps_bar( $r[ $analysis_type ], 'largest-contentful-paint', 'Largest Contentful Paint', $icon ); ?>
										<?php w3tcps_bar( $r[ $analysis_type ], 'interactive', 'Time to Interactive', $icon ); ?>
										<?php w3tcps_bar( $r[ $analysis_type ], 'total-blocking-time', 'Total Blocking Time', $icon ); ?>
										<?php w3tcps_bar( $r[ $analysis_type ], 'cumulative-layout-shift', 'Cumulative Layout Shift', $icon ); ?>
										<?php Util_Ui::postbox_footer(); ?>
									</div>
									<div class="w3tcps_screenshots_<?php echo esc_attr( $analysis_type ); ?>">
										<?php Util_Ui::postbox_header( __( 'Screenshots', 'w3-total-cache' ), '', 'gps-screenshots' ); ?>
										<div class="w3tcps_screenshots_other_<?php echo esc_attr( $analysis_type ); ?>">
											<h3 class="w3tcps_metric_title"><?php esc_html_e( 'Pageload Thumbnails', 'w3-total-cache' ); ?></h3>
											<div class="w3tcps_other_screenshot_container"><?php w3tcps_screenshots( $r[ $analysis_type ] ); ?></div>
										</div>    
										<div class="w3tcps_screenshots_final_<?php echo esc_attr( $analysis_type ); ?>">
											<h3 class="w3tcps_metric_title"><?php esc_html_e( 'Final Screenshot', 'w3-total-cache' ); ?></h3>
											<div class="w3tcps_final_screenshot_container"><?php w3tcps_final_screenshot( $r[ $analysis_type ] ); ?></div>
										</div>
										<?php Util_Ui::postbox_footer(); ?>
									</div>
									<div class="w3tcps_breakdown w3tcps_breakdown_<?php echo esc_attr( $analysis_type ); ?>">
										<?php Util_Ui::postbox_header( __( 'Audit Results', 'w3-total-cache' ), '', 'gps-audit-results' ); ?>
										<div id="w3tcps_audit_filters_<?php echo esc_attr( $analysis_type ); ?>" class="nav-tab-wrapper">
											<a href="#" class="w3tcps_audit_filter nav-tab nav-tab-active"><?php esc_html_e( 'ALL', 'w3-total-cache' ); ?></a>
											<a href="#" class="w3tcps_audit_filter nav-tab"><?php esc_html_e( 'FCP', 'w3-total-cache' ); ?></a>
											<a href="#" class="w3tcps_audit_filter nav-tab"><?php esc_html_e( 'TBT', 'w3-total-cache' ); ?></a>
											<a href="#" class="w3tcps_audit_filter nav-tab"><?php esc_html_e( 'LCP', 'w3-total-cache' ); ?></a>
											<a href="#" class="w3tcps_audit_filter nav-tab"><?php esc_html_e( 'CLS', 'w3-total-cache' ); ?></a>
										</div>
										<?php w3tcps_breakdown( $r[ $analysis_type ] ); ?>
										<?php Util_Ui::postbox_footer(); ?>
									</div>
								</div>
								<?php
							}
							?>
						</div>
					</div>
					<?php
				}
				?>
			</div>
		</div>
	</div>
</div>
