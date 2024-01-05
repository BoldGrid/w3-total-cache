<?php
namespace W3TC;

if ( ! defined( 'W3TC' ) ) {
	die();
}
?>
<div id="w3tc-webp-widget-stats-container">
	<h3 class="w3tc-webp-widget-stats-title"><?php esc_html_e( 'Status', 'w3-total-cache' ); ?></h3>
	<div id="counts_chart"></div>
	<h3 class="w3tc-webp-widget-stats-title"><?php esc_html_e( 'API Use Limits', 'w3-total-cache' ); ?></h3>
	<div id="api_charts"></div>
</div>