<?php
namespace W3TC;

if ( !defined( 'W3TC' ) )
	die();

$r = $api_response;
$rm = $api_response['mobile'];
$rd = $api_response['desktop'];



function w3tcps_barline($metric) {
	if ( !is_array( $metric['distributions'] ) ||
			count( $metric['distributions'] ) < 3 ) {
		return;
	}

	$v1 = round((float)$metric['distributions'][0]['proportion'] * 100);
	$v2 = round((float)$metric['distributions'][1]['proportion'] * 100);
	$v3 = round((float)$metric['distributions'][2]['proportion'] * 100);

	?>
	<div class="w3tcps_barline">
		<div class="w3tcps_barfast" style="flex-grow: <?php echo esc_html($v1) ?>"><?php echo esc_html($v1) ?>%</div>
		<div class="w3tcps_baraverage" style="flex-grow: <?php echo esc_html($v2) ?>"><?php echo esc_html($v2) ?>%</div>
		<div class="w3tcps_barslow" style="flex-grow: <?php echo esc_html($v3) ?>;"><?php echo esc_html($v3) ?>%</div>
	</div>
	<?php
}



function w3tcps_bar($r, $metric, $name) {
	if ( !isset( $r['desktop']['metrics'][$metric] ) ) {
		return;
	}

	echo '<div class="w3tcps_metric">' . esc_html( $name ) . '</div>';
	w3tcps_barline( $r['mobile']['metrics'][$metric]);
	w3tcps_barline( $r['desktop']['metrics'][$metric]);
}



?>
<div class="w3tcps_scores">
	<section title="Mobile"><?php echo ( isset( $rm['score'] ) ? esc_html( $rm['score'] ) : '' ); ?></section>
	<p>|</p>
	<section title="Desktop"><?php echo ( isset( $rd['score'] ) ? esc_html( $rd['score'] ) : '' ); ?></section>
</div>

<div>
	<?php w3tcps_bar($r, 'LARGEST_CONTENTFUL_PAINT_MS', 'Largest Contentful Paint') ?>
	<?php w3tcps_bar($r, 'FIRST_CONTENTFUL_PAINT_MS', 'First Contentful Paint') ?>
	<?php w3tcps_bar($r, 'FIRST_INPUT_DELAY_MS', 'First Input Delay') ?>
	<?php w3tcps_bar($r, 'CUMULATIVE_LAYOUT_SHIFT_SCORE', 'Cumulative Layout Shift') ?>
</div>
<div class="w3tcps_buttons">
	<input class="button w3tcps_refresh" type="button" value="Refresh analysis" />
	<a href="<?php echo esc_html( $r['test_url'] ) ?>" target="_blank" class="button">View all results</a>
</div>
