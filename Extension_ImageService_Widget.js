/**
 * W3TC image service widgets Google Charts driver.
 *
 * @file    Google Charts driver for W3TC dashboard image service widget.
 * @author  W3TC.
 * @version 1.0
 * @since   2.7.0
 */

jQuery( document ).ready(
	function() {
		google.charts.load( 'current', { packages: ['corechart', 'gauge'] } );
		google.charts.setOnLoadCallback( load );

		setInterval(load, 60000); // Refresh charts every 60 seconds

    	jQuery(window).resize(load); // Redraw charts after resize

		// Load method for image service charts. Fires on document ready, window resize, and on 60 second interval.
		function load() {
			processed_data = preprocess_data( w3tc_webp_data );
    	    draw_charts( processed_data );
    	}

		// Preprocesses statistics data for chart use.
		/**
		 * @param {array} data Image Service data.
		 * @returns {array} Image Service data in format required for Google charts.
		 */
		function preprocess_data( data ) {
			var processed_data = {
				'counts': {
					'data': [
						[ 'Converted', Number(data.counts.data['converted']),data.counts.data['convertedbytes'] ],
						[ 'Not Converted', Number(data.counts.data['notconverted']),data.counts.data['notconvertedbytes'] ],
						[ 'Processing', Number(data.counts.data['processing']),data.counts.data['processingbytes'] ],
						[ 'Sending', Number(data.counts.data['sending']),data.counts.data['sendingbytes'] ],
						[ 'Unconverted', Number(data.counts.data['unconverted']),data.counts.data['unconvertedbytes'] ]
					],
					'type': data.counts.type
				},
				'apihourly': {
					'data': [
						[ 'Hourly', Number( data.api.data['usage_hourly'] ) ]
					],
					'limit': Number( data.api.data['limit_hourly'] ),
					'type': data.api.type
				}
			};

			// Monthly data is only present for free users as pro is unlimited monthly usage.
			if( 'limit_monthly' in data.api.data ) {
				processed_data['apimonthly'] = {
					'data': [
						[ 'Monthly', Number( data.api.data['usage_monthly'] ) ]
					],
					'limit': Number( data.api.data['limit_monthly'] ),
					'type': data.api.type
				};
			}

			return processed_data;
		}

		// Draws the stats charts.
		/**
		 * @param {array} data - Preprocessed Image Service data.
		 */
		function draw_charts( data ) {
			for ( var key in data ) {
				if ( data[key]['type'] === 'pie' && document.getElementById( key + '_chart' ) ) {
					var chart_data = new google.visualization.DataTable();

    	        	// Add columns for the chart data
    	        	chart_data.addColumn( 'string', 'Status' );
    	        	chart_data.addColumn( 'number', 'Count' );
    	        	chart_data.addColumn( { type: 'string', role: 'tooltip', 'p': { 'html': true } } );
    	        	chart_data.addColumn( 'number', 'Bytes' );

    	        	// Add rows for the chart data
    	        	data[key]['data'].forEach( function ( row ) {
    	        	    chart_data.addRow( [ row[0], row[1], generateTooltip( row[0], row[1], row[2]), row[2] ] );
    	        	});

    	        	var chart_options = {
						chartArea: { width: '100%', height: '100%', top: 8, bottom: 40 },
    	        	    legend: { position: 'bottom' },
    	        	    tooltip: { isHtml: true }
    	        	};

					var chart = new google.visualization.PieChart( document.getElementById( key + '_chart' ) );
				} else if( data[key]['type'] === 'gauge' ) {
					if( document.getElementById( key + '_chart' ) === null ) {
						jQuery( '#api_charts' ).append( '<div id="' + key + '_chart"></div>' );
					}

					var chart_data = google.visualization.arrayToDataTable( data[key]['data'], true );
					var yellow_from, yellow_to, red_from;

					if ( data[key]['limit'] > 10 && data[key]['limit'] <= 100 ) {
						yellow_from = data[key]['limit'] - 40;
						yellow_to = data[key]['limit'] - 10;
						red_from = data[key]['limit'] - 10;
					} else if ( data[key]['limit'] > 100 ) {
						yellow_from = data[key]['limit'] - 4000;
						yellow_to = data[key]['limit'] - 1000;
						red_from = data[key]['limit'] - 1000;
					} else {
						yellow_from = data[key]['limit'] - 4;
						yellow_to = data[key]['limit'] - 1;
						red_from = data[key]['limit'] - 1;
					}

					var chart_options = {
    	        	    legend: { position: 'bottom' },
						max: data[key]['limit'],
						yellowFrom: yellow_from,
						yellowTo: yellow_to,
						redFrom: red_from,
						redTo: data[key]['limit'],
						minorTicks: 5
    	        	};

					var chart = new google.visualization.Gauge( document.getElementById( key + '_chart' ) );
				}
				chart.draw( chart_data, chart_options );
			};
		}

		// Function to generate custom tooltip with count and bytes
		function generateTooltip( dataType, count, bytes ) {
			return `<div style="padding:10px;"><span><strong>Type:</strong> ${dataType}</span><br/><span><strong>Count:</strong> ${count}</span><br/><span><strong>Bytes:</strong> ${formatBytes(bytes)}</span></div>`;
		}

		// Formats a timestamp into a human readable string.
		/**
		 * @param {Object} d Timestamp.
		 * @returns {string} Human readable date/time string.
		 */
		function dateFormat( d ){
			return ( "0" + d.getUTCHours() ).slice( -2 ) + ":" + ( "0" + d.getUTCMinutes() ).slice( -2 );
		}

		// Formats bytes into a human readable string.
		/**
		 * @param {Number} x Bytes.
		 * @returns {string} Human readable string.
		 */
		function formatBytes( x ){
			const units = [ 'bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB' ];
			let l = 0, n = parseInt(x, 10) || 0;

			while( n >= 1000 && ++l ){
				n = n/1000;
			}

  			return( n.toFixed( n < 10 && l > 0 ? 1 : 0 ) + ' ' + units[l] );
		}

		// Time since last refresh.
		var seconds_timer_id;

		// Interval for the image service data refresh.
		/**
		 * @param {Number} new_seconds_till_refresh Interval to trigger refresh.
		 */
    	function setRefresh( new_seconds_till_refresh ) {
			clearTimeout( seconds_timer_id );
			var seconds_till_refresh = new_seconds_till_refresh;
			seconds_timer_id = setInterval(
				function () {
					seconds_till_refresh--;
					if ( seconds_till_refresh <= 0 ) {
						clearInterval( seconds_timer_id ); // Change clearTimeout to clearInterval here
						seconds_timer_id = null;
						load();
						setRefresh( new_seconds_till_refresh ); // Restart the timer after calling load()
						return;
					}
				},
				1000
			);
		}
	}
);
