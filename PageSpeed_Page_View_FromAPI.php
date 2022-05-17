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

$r = $api_response;

/**
 * Get score guage color
 *
 * @param int $score PageSpeed desktop/mobile score.
 *
 * @return string
 */
function w3tcps_gauge_color( $score ) {
	$color = '#fff';
	if ( isset( $score ) && $score >= 90 ) {
		$color = '#0c6';
	} elseif ( isset( $score ) && $score >= 50 && $score < 90 ) {
		$color = '#fa3';
	} elseif ( isset( $score ) && $score >= 0 && $score < 50 ) {
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
	return ( isset( $score ) ? ( $score / 100 ) * 180 : 0 );
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
			<?php echo ( isset( $data['score'] ) ? esc_html( $data['score'] ) : '' ); ?>
		</span>
	</div>
	<?php
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

		$grade = 'w3tcps_blank';
		if ( isset( $opportunity['score'] ) ) {
			$grade = w3tcps_breakdown_grade( $opportunity['score'] );
		}

		$audit_classes = '';
		foreach ( $opportunity['type'] as $type ) {
			$audit_classes .= ' ' . $type;
		}

		$opportunity['description'] = preg_replace( '/(.*)(\[Learn more\])\((.*?)\)(.*)/i', '$1<a href="$3">$2</a>$4', $opportunity['description'] );

		$headers = '';
		$items   = '';

		foreach ( $opportunity['details'] as $item ) {
			$headers = '';
			$items  .= '<tr class="w3tcps_passed_audit_item">';
			if ( isset( $item['url'] ) ) {
				$headers .= '<th>URL</th>';
				$items   .= '<td>...' . wp_parse_url( $item['url'] )['path'] . '</td>';
			}
			if ( isset( $item['totalBytes'] ) ) {
				$headers .= '<th>Total Bytes</th>';
				$items   .= '<td>' . $item['totalBytes'] . '</td>';
			}
			if ( isset( $item['wastedBytes'] ) ) {
				$headers .= '<th>Wasted Bytes</th>';
				$items   .= '<td>' . $item['wastedBytes'] . '</td>';
			}
			if ( isset( $item['wastedPercent'] ) ) {
				$headers .= '<th>Wasted Percentage</th>';
				$items   .= '<td>' . round( $item['wastedPercent'], 2 ) . '%</td>';
			}
			if ( isset( $item['wastedMs'] ) ) {
				$headers .= '<th>Wasted Miliseconds</th>';
				$items   .= '<td>' . round( $item['wastedMs'], 2 ) . '</td>';
			}
			if ( isset( $item['label'] ) ) {
				$headers .= '<th>Type</th>';
				$items   .= '<td>' . $item['label'] . '</td>';
			}
			if ( isset( $item['groupLabel'] ) ) {
				$headers .= '<th>Group</th>';
				$items   .= '<td>' . $item['groupLabel'] . '</td>';
			}
			if ( isset( $item['requestCount'] ) ) {
				$headers .= '<th>Requests</th>';
				$items   .= '<td>' . $item['requestCount'] . '</td>';
			}
			if ( isset( $item['transferSize'] ) ) {
				$headers .= '<th>Transfer Size</th>';
				$items   .= '<td>' . $item['transferSize'] . '</td>';
			}
			if ( isset( $item['startTime'] ) ) {
				$headers .= '<th>Start Time</th>';
				$items   .= '<td>' . $item['startTime'] . '</td>';
			}
			if ( isset( $item['duration'] ) ) {
				$headers .= '<th>Duration</th>';
				$items   .= '<td>' . $item['duration'] . '</td>';
			}
			if ( isset( $item['scriptParseCompile'] ) ) {
				$headers .= '<th>Parse/Compile Time</th>';
				$items   .= '<td>' . $item['scriptParseCompile'] . '</td>';
			}
			if ( isset( $item['scripting'] ) ) {
				$headers .= '<th>Execution Time</th>';
				$items   .= '<td>' . $item['scripting'] . '</td>';
			}
			if ( isset( $item['total'] ) ) {
				$headers .= '<th>Total</th>';
				$items   .= '<td>' . $item['total'] . '</td>';
			}
			if ( isset( $item['cacheLifetimeMs'] ) ) {
				$headers .= '<th>Cache Lifetime Miliseconds</th>';
				$items   .= '<td>' . $item['cacheLifetimeMs'] . '</td>';
			}
			if ( isset( $item['cacheHitProbability'] ) ) {
				$headers .= '<th>Cache Hit Probability</th>';
				$items   .= '<td>' . $item['cacheHitProbability'] . '</td>';
			}
			$items .= '</tr>';
		}

		if ( $opportunity['score'] >= 90 ) {
			$passed_audits .= '<div class="audits w3tcps_passed_audit' . $audit_classes . '"><span class="w3tcps_breakdown_items_toggle w3tcps_range chevron_down ' . $grade . '">' . $opportunity['title'] . ' - ' . $opportunity['displayValue'] . '</span><div class="w3tcps_breakdown_items w3tcps_pass_audit_items"><p>' . $opportunity['description'] . '</p><table class="w3tcps_item_table"><tr class="w3tcps_passed_audit_item_header">' . $headers . '</tr>' . $items . '</table><div class="w3tcps_instruction">' . $opportunity['instruction'] . '</div></div></div>';
		} else {
			$opportunities .= '<div class="audits w3tcps_opportunities' . $audit_classes . '"><span class="w3tcps_breakdown_items_toggle w3tcps_range chevron_down ' . $grade . '">' . $opportunity['title'] . ' - ' . $opportunity['displayValue'] . '</span><div class="w3tcps_breakdown_items w3tcps_opportunity_items"><p>' . $opportunity['description'] . '</p><table class="w3tcps_item_table"><tr class="w3tcps_passed_audit_item_header">' . $headers . '</tr>' . $items . '</table><div class="w3tcps_instruction">' . $opportunity['instruction'] . '</div></div></div>';
		}
	}

	foreach ( $data['diagnostics'] as $diagnostic ) {
		if ( empty( $diagnostic['details'] ) ) {
			continue;
		}

		$diagnostic['score'] *= 100;

		$grade = 'w3tcps_blank';
		if ( isset( $diagnostic['score'] ) ) {
			$grade = w3tcps_breakdown_grade( $diagnostic['score'] );
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
			if ( isset( $item['url'] ) ) {
				$headers .= '<th>URL</th>';
				$items   .= '<td>...' . wp_parse_url( $item['url'] )['path'] . '</td>';
			}
			if ( isset( $item['totalBytes'] ) ) {
				$headers .= '<th>Total Bytes</th>';
				$items   .= '<td>' . $item['totalBytes'] . '</td>';
			}
			if ( isset( $item['wastedBytes'] ) ) {
				$headers .= '<th>Wasted Bytes</th>';
				$items   .= '<td>' . $item['wastedBytes'] . '</td>';
			}
			if ( isset( $item['wastedPercent'] ) ) {
				$headers .= '<th>Wasted Percentage</th>';
				$items   .= '<td>' . round( $item['wastedPercent'], 2 ) . '%</td>';
			}
			if ( isset( $item['wastedMs'] ) ) {
				$headers .= '<th>Wasted Miliseconds</th>';
				$items   .= '<td>' . round( $item['wastedMs'], 2 ) . '</td>';
			}
			if ( isset( $item['label'] ) ) {
				$headers .= '<th>Type</th>';
				$items   .= '<td>' . $item['label'] . '</td>';
			}
			if ( isset( $item['groupLabel'] ) ) {
				$headers .= '<th>Group</th>';
				$items   .= '<td>' . $item['groupLabel'] . '</td>';
			}
			if ( isset( $item['requestCount'] ) ) {
				$headers .= '<th>Requests</th>';
				$items   .= '<td>' . $item['requestCount'] . '</td>';
			}
			if ( isset( $item['transferSize'] ) ) {
				$headers .= '<th>Transfer Size</th>';
				$items   .= '<td>' . $item['transferSize'] . '</td>';
			}
			if ( isset( $item['startTime'] ) ) {
				$headers .= '<th>Start Time</th>';
				$items   .= '<td>' . $item['startTime'] . '</td>';
			}
			if ( isset( $item['duration'] ) ) {
				$headers .= '<th>Duration</th>';
				$items   .= '<td>' . $item['duration'] . '</td>';
			}
			if ( isset( $item['scriptParseCompile'] ) ) {
				$headers .= '<th>Parse/Compile Time</th>';
				$items   .= '<td>' . $item['scriptParseCompile'] . '</td>';
			}
			if ( isset( $item['scripting'] ) ) {
				$headers .= '<th>Execution Time</th>';
				$items   .= '<td>' . $item['scripting'] . '</td>';
			}
			if ( isset( $item['total'] ) ) {
				$headers .= '<th>Total</th>';
				$items   .= '<td>' . $item['total'] . '</td>';
			}
			if ( isset( $item['cacheLifetimeMs'] ) ) {
				$headers .= '<th>Cache Lifetime Miliseconds</th>';
				$items   .= '<td>' . $item['cacheLifetimeMs'] . '</td>';
			}
			if ( isset( $item['cacheHitProbability'] ) ) {
				$headers .= '<th>Cache Hit Probability</th>';
				$items   .= '<td>' . ( $item['cacheHitProbability'] * 100 ) . '%</td>';
			}
			$items .= '</tr>';
		}

		if ( $diagnostic['score'] >= 90 ) {
			$passed_audits .= '<div class="audits w3tcps_passed_audit' . $audit_classes . '"><span class="w3tcps_breakdown_items_toggle w3tcps_range chevron_down ' . $grade . '">' . $diagnostic['title'] . ' - ' . $diagnostic['displayValue'] . '</span><div class="w3tcps_breakdown_items w3tcps_pass_audit_items"><p>' . $diagnostic['description'] . '</p><table class="w3tcps_item_table"><tr class="w3tcps_passed_audit_item_header">' . $headers . '</tr>' . $items . '</table><div class="w3tcps_instruction">' . $diagnostic['instruction'] . '</div></div></div>';
		} else {
			$diagnostics .= '<div class="audits w3tcps_diagnostics' . $audit_classes . '"><span class="w3tcps_breakdown_items_toggle w3tcps_range chevron_down ' . $grade . '">' . $diagnostic['title'] . ' - ' . $diagnostic['displayValue'] . '</span><div class="w3tcps_breakdown_items w3tcps_diagnostic_items"><p>' . $diagnostic['description'] . '</p><table class="w3tcps_item_table"><tr class="w3tcps_passed_audit_item_header">' . $headers . '</tr>' . $items . '</table><div class="w3tcps_instruction">' . $diagnostic['instruction'] . '</div></div></div>';
		}
	}

	$allowed_tags = array(
		'div'   => array(
			'class' => array(),
		),
		'span'  => array(
			'class' => array(),
		),
		'p'     => array(
			'class' => array(),
		),
		'table' => array(
			'class' => array(),
		),
		'tr'    => array(
			'class' => array(),
		),
		'td'    => array(
			'class' => array(),
		),
		'th'    => array(
			'class' => array(),
		),
		'b'     => array(),
		'br'    => array(),
		'a'     => array(
			'href'   => array(),
			'target' => array(),
			'rel'    => array(),
		),
		'link'  => array(
			'href' => array(),
			'rel'  => array(),
			'as'   => array(),
			'type' => array(),
		),
		'code'  => array(),
		'img'   => array(
			'srcset' => array(),
			'src'    => array(),
			'alt'    => array(),
		),
		'ul'    => array(),
		'ol'    => array(),
		'li'    => array(),
	);

	echo wp_kses(
		'<div class="opportunities"><p class="title">Opportunities</p>' . $opportunities . '</div>',
		$allowed_tags
	);
	echo wp_kses(
		'<div class="diagnostics"><p class="title">Diagnostics</p>' . $diagnostics . '</div>',
		$allowed_tags
	);
	echo wp_kses(
		'<div class="passed_audits"><p class="title">Passed Audits</p>' . $passed_audits . '</div>',
		$allowed_tags
	);
}

/**
 * Render metric barline
 *
 * @param array $metric PageSpeed desktop/mobile score.
 *
 * @return void
 */
function w3tcps_barline( $metric ) {
	if ( ! isset( $metric['score'] ) ) {
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
	if ( ! isset( $data[ $metric ] ) ) {
		return;
	}

	?>
	<div class="w3tcps_metric">
		<p class="w3tcps_metric_title"><?php echo esc_html( $name ); ?></p>
		<div class="w3tcps_metric_stats">
			<i class="material-icons" aria-hidden="true"><?php echo esc_html( $icon ); ?></i> 
			<?php w3tcps_barline( $data[ $metric ] ); ?>
		</div>
	</div>
	<?php
}

?>
<script>
	jQuery(document).ready(function($) {
		function filter_audits(event) {
			event.preventDefault();
			var breakdown = $(this).closest('.w3tcps_breakdown');
			breakdown.find('.opportunities').slideUp('fast');
			breakdown.find('.diagnostics').slideUp('fast');
			breakdown.find('.passed_audits').slideUp('fast');
			if($(this).text() == 'ALL'){
				breakdown.find('.audits').show();
			} else {
				breakdown.find('.audits').hide();
				breakdown.find('.' + $(this).text()).show();
			}
			if(breakdown.find('.opportunities').find('.audits:not([style*="display: none"])').length != 0){
				breakdown.find('.opportunities').slideDown('slow');
			}
			if(breakdown.find('.diagnostics').find('.audits:not([style*="display: none"])').length != 0){
				breakdown.find('.diagnostics').slideDown('slow');
			}
			if(breakdown.find('.passed_audits').find('.audits:not([style*="display: none"])').length != 0){
				breakdown.find('.passed_audits').slideDown('slow');
			}
		}

		$(document).on('click', '.w3tcps_audit_filter', filter_audits);
	});
</script>
<div id="w3tcps_control">
	<div id="w3tcps_control_mobile"><i class="material-icons" aria-hidden="true">smartphone</i><span><?php esc_html_e( 'Mobile', 'w3-total-cache' ); ?></span></div>
	<div id="w3tcps_control_desktop"><i class="material-icons" aria-hidden="true">computer</i><span><?php esc_html_e( 'Desktop', 'w3-total-cache' ); ?></span></div>
</div>
<div id="w3tcps_mobile">
	<div id="w3tcps_legend_mobile">
		<div class="w3tcps_gauge_mobile">
			<?php w3tcps_gauge( $r['mobile'], 'smartphone' ); ?>
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
	</div>
	<div class="w3tcps_metrics_mobile">
		<?php w3tcps_bar( $r['mobile'], 'first-contentful-paint', 'First Contentful Paint', 'smartphone' ); ?>
		<?php w3tcps_bar( $r['mobile'], 'speed-index', 'Speed Index', 'smartphone' ); ?>
		<?php w3tcps_bar( $r['mobile'], 'largest-contentful-paint', 'Largest Contentful Paint', 'smartphone' ); ?>
		<?php w3tcps_bar( $r['mobile'], 'interactive', 'Time to Interactive', 'smartphone' ); ?>
		<?php w3tcps_bar( $r['mobile'], 'total-blocking-time', 'Total Blocking Time', 'smartphone' ); ?>
		<?php w3tcps_bar( $r['mobile'], 'cumulative-layout-shift', 'Cumulative Layout Shift', 'smartphone' ); ?>
	</div>
	<div class="w3tcps_screenshots_other_mobile">
		<p><?php esc_html_e( 'Pageload Thumbnails', 'w3-total-cache' ); ?></p>
		<?php w3tcps_screenshots( $r['mobile'] ); ?>
	</div>    
	<div class="w3tcps_screenshots_final_mobile">
		<p><?php esc_html_e( 'Final Screenshot', 'w3-total-cache' ); ?></p>
		<div class="w3tcps_final_screenshot_container"><?php w3tcps_final_screenshot( $r['mobile'] ); ?></div>
	</div>
	<div class="w3tcps_breakdown w3tcps_breakdown_mobile">
		<div id="w3tcps_audit_filters_mobile">
			<a href="#" class="w3tcps_audit_filter"><?php esc_html_e( 'ALL', 'w3-total-cache' ); ?></a>
			<a href="#" class="w3tcps_audit_filter"><?php esc_html_e( 'FCP', 'w3-total-cache' ); ?></a>
			<a href="#" class="w3tcps_audit_filter"><?php esc_html_e( 'TBT', 'w3-total-cache' ); ?></a>
			<a href="#" class="w3tcps_audit_filter"><?php esc_html_e( 'LCP', 'w3-total-cache' ); ?></a>
			<a href="#" class="w3tcps_audit_filter"><?php esc_html_e( 'CLS', 'w3-total-cache' ); ?></a>
		</div>
		<?php w3tcps_breakdown( $r['mobile'] ); ?>
	</div>
</div>
<div id="w3tcps_desktop">
	<div id="w3tcps_legend_desktop">
		<div class="w3tcps_gauge_desktop">
			<?php w3tcps_gauge( $r['desktop'], 'computer' ); ?>
		</div>
		<?php
		echo wp_kses(
			sprintf(
				// translators: 1 opening HTML span tag, 2 opening HTML a tag to web.dev/performance-soring, 3 closing HTML a tag,
				// translators: 4 closing HTML span tag, 5 opening HTML a tag to googlechrome.github.io Lighthouse Score Calculator,
				// translators: 6 closing HTML a tag.
				__(
					'%1$sValues are estimated and may vary. The %2$sperformance score is calculated%3$s directly from these metrics. %4$s%5$sSee calculator.%6$s',
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
	<div class="w3tcps_metrics_desktop">
		<?php w3tcps_bar( $r['desktop'], 'first-contentful-paint', 'First Contentful Paint', 'computer' ); ?>
		<?php w3tcps_bar( $r['desktop'], 'speed-index', 'Speed Index', 'computer' ); ?>
		<?php w3tcps_bar( $r['desktop'], 'largest-contentful-paint', 'Largest Contentful Paint', 'computer' ); ?>
		<?php w3tcps_bar( $r['desktop'], 'interactive', 'Time to Interactive', 'computer' ); ?>
		<?php w3tcps_bar( $r['desktop'], 'total-blocking-time', 'Total Blocking Time', 'computer' ); ?>
		<?php w3tcps_bar( $r['desktop'], 'cumulative-layout-shift', 'Cumulative Layout Shift', 'computer' ); ?>
	</div>
	<div class="w3tcps_screenshots_other_desktop">
		<p><?php esc_html_e( 'Pageload Thumbnails', 'w3-total-cache' ); ?></p>
		<?php w3tcps_screenshots( $r['desktop'] ); ?>
	</div> 
	<div class="w3tcps_screenshots_final_desktop">
		<p><?php esc_html_e( 'Final Screenshot', 'w3-total-cache' ); ?></p>
		<div class="w3tcps_final_screenshot_container"><?php w3tcps_final_screenshot( $r['desktop'] ); ?></div>
	</div>
	<div class="w3tcps_breakdown w3tcps_breakdown_desktop">
		<div id="w3tcps_audit_filters_desktop">
			<a href="#" class="w3tcps_audit_filter"><?php esc_html_e( 'ALL', 'w3-total-cache' ); ?></a>
			<a href="#" class="w3tcps_audit_filter"><?php esc_html_e( 'FCP', 'w3-total-cache' ); ?></a>
			<a href="#" class="w3tcps_audit_filter"><?php esc_html_e( 'TBT', 'w3-total-cache' ); ?></a>
			<a href="#" class="w3tcps_audit_filter"><?php esc_html_e( 'LCP', 'w3-total-cache' ); ?></a>
			<a href="#" class="w3tcps_audit_filter"><?php esc_html_e( 'CLS', 'w3-total-cache' ); ?></a>
		</div>
		<?php w3tcps_breakdown( $r['desktop'] ); ?>
	</div>
</div>
