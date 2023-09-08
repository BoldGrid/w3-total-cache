/**
 * File: Cdn_BunnyCdn_Wiget_View.js
 *
 * @since   X.X.X
 * @package W3TC
 */

var w3tc_bunnycdn_graph_data;

function w3tc_bunnycdn_load() {
	jQuery('.w3tc_bunnycdn_loading').removeClass('w3tc_hidden');
	jQuery('.w3tc_bunnycdn_content').addClass('w3tc_hidden');
	jQuery('.w3tc_bunnycdn_error').addClass('w3tc_none');

	jQuery.getJSON(
		ajaxurl + '?action=w3tc_ajax&_wpnonce=' + w3tc_nonce + '&w3tc_action=cdn_bunnycdn_widgetdata',
		function(data) {
			if ( data && data.error ) {
				jQuery('.w3tc_bunnycdn_error').removeClass('w3tc_none');
				jQuery('.w3tc_bunnycdn_error_details').html(data.error);
				jQuery('.w3tc_bunnycdn_loading').addClass('w3tc_hidden');
				return;
			}

			for ( p in data ) {
				var v = data[p];

				if ( p.substr(0, 4) === 'url_' ) {
					jQuery('.w3tc_bunnycdn_href_' + p.substr(4)).attr('href', v);
				} else {
					jQuery('.w3tc_bunnycdn_' + p).html(v);
				}
			}

			var chart_data = google.visualization.arrayToDataTable(data.chart_mb);

			var chart = new google.visualization.ColumnChart(
				document.getElementById('chart_div'));
			var options = {};//colors: 'blue,red'};
			chart.draw(chart_data, options);

			jQuery('.w3tc_bunnycdn_content').removeClass('w3tc_hidden');
			jQuery('.w3tc_bunnycdn_loading').addClass('w3tc_hidden');
		}
	)
	.fail(function() {
		jQuery('.w3tc_bunnycdn_error').removeClass('w3tc_none');
		jQuery('.w3tc_bunnycdn_content').addClass('w3tc_hidden');
		jQuery('.w3tc_bunnycdn_loading').addClass('w3tc_hidden');
	});
}



google.load('visualization', '1', {packages:['corechart']});
google.setOnLoadCallback(w3tc_bunnycdn_load);
