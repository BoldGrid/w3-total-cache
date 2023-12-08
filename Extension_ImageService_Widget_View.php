<?php
namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}
Util_Debug::debug('usage',$usage);
?>
<div id="w3tc-webp-widget-stats-container">
	<table>
		<tr>
			<th class="w3tc-webp-widget-stats-label">
				<b><?php esc_html_e( 'Counts and filesizes by status:', 'w3-total-cache' ); ?></b>
			</th>
		</tr>
		<tr>
			<td class="w3tc-webp-widget-stats-stat">
				<?php esc_html_e( 'Total:', 'w3-total-cache' ); ?>
			</td>
			<td class="w3tc-webp-widget-stats-stat-total">
				<?php echo esc_html( $counts['total'] ); ?>
			</td>
			<td class="w3tc-webp-widget-stats-stat-totalbytes">
				<?php echo esc_html( size_format( $counts['totalbytes'], 2 ) ); ?>
			</td>
		</tr>
		<tr>
			<td class="w3tc-webp-widget-stats-stat">
				<?php esc_html_e( 'Converted:', 'w3-total-cache' ); ?>
			</td>
			<td class="w3tc-webp-widget-stats-stat-total">
				<?php echo esc_html( $counts['converted'] ); ?>
			</td>
			<td class="w3tc-webp-widget-stats-stat-totalbytes">
				<?php echo esc_html( size_format( $counts['convertedbytes'], 2 ) ); ?>
			</td>
		</tr>
		<tr>
			<td class="w3tc-webp-widget-stats-stat">
				<?php esc_html_e( 'Sending:', 'w3-total-cache' ); ?>
			</td>
			<td class="w3tc-webp-widget-stats-stat-total">
				<?php echo esc_html( $counts['sending'] ); ?>
			</td>
			<td class="w3tc-webp-widget-stats-stat-totalbytes">
				<?php echo esc_html( size_format( $counts['sendingbytes'], 2 ) ); ?>
			</td>
		</tr>
		<tr>
			<td class="w3tc-webp-widget-stats-stat">
				<?php esc_html_e( 'Processing:', 'w3-total-cache' ); ?>
			</td>
			<td class="w3tc-webp-widget-stats-stat-total">
				<?php echo esc_html( $counts['processing'] ); ?>
			</td>
			<td class="w3tc-webp-widget-stats-stat-totalbytes">
				<?php echo esc_html( size_format( $counts['processingbytes'], 2 ) ); ?>
			</td>
		</tr>
		<tr>
			<td class="w3tc-webp-widget-stats-stat">
				<?php esc_html_e( 'Not Converted:', 'w3-total-cache' ); ?>
			</td>
			<td class="w3tc-webp-widget-stats-stat-total">
				<?php echo esc_html( $counts['notconverted'] ); ?>
			</td>
			<td class="w3tc-webp-widget-stats-stat-totalbytes">
				<?php echo esc_html( size_format( $counts['notconvertedbytes'], 2 ) ); ?>
			</td>
		</tr>
		<tr>
			<td class="w3tc-webp-widget-stats-stat">
				<?php esc_html_e( 'Unconverted:', 'w3-total-cache' ); ?>
			</td>
			<td class="w3tc-webp-widget-stats-stat-total">
				<?php echo esc_html( $counts['unconverted'] ); ?>
			</td>
			<td class="w3tc-webp-widget-stats-stat-totalbytes">
				<?php echo esc_html( size_format( $counts['unconvertedbytes'], 2 ) ); ?>
			</td>
		</tr>
	</table>
	<br/>
	<table>
		<tr>
			<th class="w3tc-webp-widget-stats-label">
				<b><?php esc_html_e( 'WebP Converter API usage:', 'w3-total-cache' ); ?></b>
			</th>
		</tr>
		<tr>
			<td class="w3tc-webp-widget-stats-stat">
				<?php esc_html_e( 'Hourly Requests:', 'w3-total-cache' ); ?>
			</td>
			<td class="w3tc-webp-widget-stats-stat-total">
				<?php echo esc_html( $usage['usage_hourly'] ); ?>
			</td>
		</tr>
		<tr>
			<td class="w3tc-webp-widget-stats-stat">
				<?php esc_html_e( 'Hourly Limit:', 'w3-total-cache' ); ?>
			</td>
			<td class="w3tc-webp-widget-stats-stat-total">
				<?php echo esc_html( $usage['limit_hourly'] ); ?>
			</td>
		</tr>
		<tr>
			<td class="w3tc-webp-widget-stats-stat">
				<?php esc_html_e( 'Monthly Requests:', 'w3-total-cache' ); ?>
			</td>
			<td class="w3tc-webp-widget-stats-stat-total">
				<?php echo esc_html( $usage['usage_monthly'] ); ?>
			</td>
		</tr>
		<tr>
			<td class="w3tc-webp-widget-stats-stat">
				<?php esc_html_e( 'Monthly Limit:', 'w3-total-cache' ); ?>
			</td>
			<td class="w3tc-webp-widget-stats-stat-total">
				<?php echo esc_html( $usage['limit_monthly'] ); ?>
			</td>
		</tr>
	</table>
</div>