jQuery(document).ready(function($) {
	function w3tcps_load(nocache) {
		$('.w3tcps_loading').removeClass('w3tc_none');
		$('.w3tcps_content').addClass('w3tc_none');
		$('.w3tcps_error').addClass('w3tc_none');

		$.getJSON(ajaxurl + '?action=w3tc_ajax&_wpnonce=' + w3tc_nonce +
			'&w3tc_action=pagespeed_widgetdata' + (nocache ? '&cache=no' : ''),
			function(data) {
				$('.w3tcps_loading').addClass('w3tc_none');

				if (data.error) {
					$('.w3tcps_error').removeClass('w3tc_none');
					return;
				}

				jQuery('.w3tcps_content').html(data['.w3tcps_content']);
				$('.w3tcps_content').removeClass('w3tc_none').fadeIn('slow');
				console.log(7);
				$('#normal-sortables').masonry();
			}
		).fail(function() {
			$('.w3tcps_error').removeClass('w3tc_none');
			$('.w3tcps_content').addClass('w3tc_none');
			$('.w3tcps_loading').addClass('w3tc_none');
		});
	}

	jQuery('.w3tcps_buttons').on('click', '.w3tcps_refresh', function() {
		w3tcps_load(true);
	});



	w3tcps_load();
});
