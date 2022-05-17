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

$r         = $api_response;
$rm        = $api_response['mobile'];
$rm_anagle = ( isset( $rm['score'] ) ? ( $rm['score'] / 100 ) * 180 : 0 );
$rm_color  = '#fff';

if ( isset( $rm['score'] ) && $rm['score'] >= 90 ) {
	$rm_color = '#0c6';
} elseif ( isset( $rm['score'] ) && $rm['score'] >= 50 && $rm['score'] < 90 ) {
	$rm_color = '#fa3';
} elseif ( isset( $rm['score'] ) && $rm['score'] >= 0 && $rm['score'] < 50 ) {
	$rm_color = '#f33';
}

$rd        = $api_response['desktop'];
$rd_color  = '#fff';
$rd_anagle = ( isset( $rd['score'] ) ? ( $rd['score'] / 100 ) * 180 : 0 );

if ( isset( $rd['score'] ) && $rd['score'] >= 90 ) {
	$rd_color = '#0c6';
} elseif ( isset( $rd['score'] ) && $rd['score'] >= 50 && $rd['score'] < 90 ) {
	$rd_color = '#fa3';
} elseif ( isset( $rd['score'] ) && $rd['score'] >= 0 && $rd['score'] < 50 ) {
	$rd_color = '#f33';
}

/**
 * PageSpeed widget barline renderer
 *
 * @param array $metric Metrics data.
 *
 * @return void
 */
function w3tcps_barline( $metric ) {
	if ( ! isset( $r['mobile'][ $metric ] ) && ! isset( $r['desktop'][ $metric ] ) ) {
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
				'class' => array(),
				'style' => array(),
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
 * @param array  $r Metrics data.
 * @param string $metric Metric key name.
 * @param string $name Metric title.
 *
 * @return void
 */
function w3tcps_bar( $r, $metric, $name ) {
	if ( ! isset( $r['mobile'][ $metric ] ) && ! isset( $r['desktop'][ $metric ] ) ) {
		return;
	}

	?>
	<div class="w3tcps_metric">
		<p class="w3tcps_metric_title"><?php echo esc_html( $name ); ?></p>
		<div class="w3tcps_metric_stats">
			<i class="material-icons" aria-hidden="true"><?php esc_html_e( 'smartphone', 'w3-total-cache' ); ?></i>
			<?php w3tcps_barline( $r['mobile'][ $metric ] ); ?>
			<i class="material-icons" aria-hidden="true"><?php esc_html_e( 'computer', 'w3-total-cache' ); ?></i>
			<?php w3tcps_barline( $r['desktop'][ $metric ] ); ?>
		</div>
	</div>
	<?php
}

?>
<h3><?php esc_html_e( 'Homepage', 'w3-total-cache' ); ?></h3>
<div class="gauge" style="width: 120px; --rotation:<?php echo esc_attr( $rm_anagle ); ?>deg; --color:<?php echo esc_attr( $rm_color ); ?>; --background:#888;">
	<div class="percentage"></div>
	<div class="mask"></div>
	<span class="value">
		<i class="material-icons" aria-hidden="true"><?php esc_html_e( 'smartphone', 'w3-total-cache' ); ?></i>
		<?php echo ( isset( $rm['score'] ) ? esc_html( $rm['score'] ) : '' ); ?>
	</span>
</div>
<div class="gauge" style="width: 120px; --rotation:<?php echo esc_attr( $rd_anagle ); ?>deg; --color:<?php echo esc_attr( $rd_color ); ?>; --background:#888;">
	<div class="percentage"></div>
	<div class="mask"></div>
	<span class="value">
		<i class="material-icons" aria-hidden="true"><?php esc_html_e( 'computer', 'w3-total-cache' ); ?></i>
		<?php echo ( isset( $rd['score'] ) ? esc_html( $rd['score'] ) : '' ); ?>
	</span>
</div>
<div class="w3tcps_metrics_container">
	<?php w3tcps_bar( $r, 'first-contentful-paint', 'First Contentful Paint' ); ?>
	<?php w3tcps_bar( $r, 'speed-index', 'Speed Index' ); ?>
	<?php w3tcps_bar( $r, 'largest-contentful-paint', 'Largest Contentful Paint' ); ?>
	<?php w3tcps_bar( $r, 'interactive', 'Time to Interactive' ); ?>
	<?php w3tcps_bar( $r, 'total-blocking-time', 'Total Blocking Time' ); ?>
	<?php w3tcps_bar( $r, 'cumulative-layout-shift', 'Cumulative Layout Shift' ); ?>
</div>
