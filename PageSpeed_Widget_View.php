<?php
namespace W3TC;

if ( !defined( 'W3TC' ) )
	die();

?>
<div class="w3tcps_loading w3tc_loading w3tc_hidden">Loading...</div>
<div class="w3tcps_error w3tc_none">
	<p>Unable to fetch Page Speed results.</p>
	<p>
		<input class="button w3tc-widget-ps-refresh" type="button" value="Refresh Analysis" />
	</p>
</div>

<div class="w3tcps_content w3tc_hidden">
	<div class="w3tcps_scores">
		<section title="Mobile">0</section>
		<p>|</p>
		<section title="Desktop">4</section>
	</div>

	<div>
		<div class="w3tcps_metric">V</div>
		<div class="w3tcps_barline">
			<div class="w3tcps_barfast">0%</div>
		</div>
		<div class="w3tcps_barline">
			<div class="w3tcps_barfast">0%</div>
		</div>
		<div class="w3tcps_metric">V</div>
		<div class="w3tcps_barline">
			<div class="w3tcps_barfast">0%</div>
		</div>
		<div class="w3tcps_barline">
			<div class="w3tcps_barfast">0%</div>
		</div>
		<div class="w3tcps_metric">V</div>
		<div class="w3tcps_barline">
			<div class="w3tcps_barfast">0%</div>
		</div>
		<div class="w3tcps_barline">
			<div class="w3tcps_barfast">0%</div>
		</div>
		<div class="w3tcps_metric">V</div>
		<div class="w3tcps_barline">
			<div class="w3tcps_barfast">0%</div>
		</div>
		<div class="w3tcps_barline">
			<div class="w3tcps_barfast">0%</div>
		</div>
	</div>

	<div class="w3tcps_buttons">
		<input class="button w3tcps_refresh" type="button" value="Refresh analysis" />
		<a href="#" class="button">View all results</a>
	</div>
</div>
