<?php
namespace W3TC;

if ( !defined( 'W3TC' ) )
	die();

?>
<div class="w3tcps_loading w3tc_none"></div>
<div class="w3tcps_error w3tc_none">
	<p>Unable to fetch Page Speed results.</p>
	<p>
		<input class="button w3tc-widget-ps-refresh" type="button" value="Refresh Analysis" />
	</p>
</div>
<div class="w3tcps_content w3tc_none"></div>
<div class="w3tcps_buttons">
	<input class="button w3tcps_refresh" type="button" value="Refresh analysis" />
	<a href="#" class="button">View all results</a>
</div>
