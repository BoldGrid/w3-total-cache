jQuery(document).ready(function($) {
	google.charts.load('current', {packages: ['corechart', 'line']});
	google.charts.setOnLoadCallback(load);

	setInterval(function () {
		load();
	}, 60000);

	jQuery(window).resize(function(){
		load();
	});

	function load() {
        $.getJSON(
			ajaxurl + '?action=w3tc_ajax&_wpnonce=' + w3tc_nonce + '&w3tc_action=ustats_get',
            function(data) {
                if(! data.period.seconds){
                	$('#w3tc_page_cache').addClass('w3tc_hidden');
					$('#w3tc_object_cache').addClass('w3tc_hidden');
					$('#w3tc_database_cache').addClass('w3tc_hidden');
                   	return;
				}
				processed_data = preprocess_data(data);
				draw_charts(processed_data);
            }
        );
    }

	function preprocess_data(data){
		var processed_data = {
			'page_cache':{'data':[],'color':'#6f9654'},
        	'object_cache':{'data':[],'color':'#e2431e'},
			'database_cache':{'data':[],'color':'#43459d'}
		};
		for( var i=40; i<data.history.length; i++){
			var entry = data.history[i];
			var timestamp = dateFormat(new Date(parseInt(entry.timestamp_start) * 1000));
			processed_data['page_cache']['data'].push([timestamp,Number(entry.php_requests_pagecache_hit)]);
			processed_data['object_cache']['data'].push([timestamp,Number(entry.objectcache_get_hits)]);
			processed_data['database_cache']['data'].push([timestamp,Number(entry.dbcache_calls_hits)]);
		}
		return processed_data;
	}

	function draw_charts(data){
		for ( var key in data ) {
			var chart_data = google.visualization.arrayToDataTable(data[key]['data'],true);
			var chart_options = {
				series:{0:{color:data[key]['color']}},
				legend:{position:'none'},
			};
			if(document.getElementById(key + '_chart')){
				var chart = new google.charts.Line(document.getElementById(key + '_chart'));
				chart.draw(chart_data, chart_options);
			}
		};
	}

	function dateFormat(d){
		return ("0" + d.getUTCHours()).slice(-2) + ":" + ("0" + d.getUTCMinutes()).slice(-2);
	}

	var seconds_timer_id;
    function setRefresh(new_seconds_till_refresh) {
		clearTimeout(seconds_timer_id);
		var seconds_till_refresh = new_seconds_till_refresh;
		seconds_timer_id = setInterval(function () {
			seconds_till_refresh--;
			if (seconds_till_refresh <= 0) {
				clearInterval(seconds_timer_id); // Change clearTimeout to clearInterval here
				seconds_timer_id = null;
				load();
				setRefresh(new_seconds_till_refresh); // Restart the timer after calling load()
				return;
			}
		}, 1000);
	}
});
