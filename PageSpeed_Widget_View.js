/**
 * File: PageSpeed_Widget_View.js
 *
 * JavaScript for the PageSpeed widget.
 *
 * @since 3.0.0
 *
 * @global w3tcData Localized data.
 */
jQuery(document).ready( function($) {
	/**
	 * Analyze homepage via AJAX to Google PageSpeed Insights.
	 *
	 * @param boolean nocache Flag to enable/disable results cache.
	 * 
	 * @return void
	 */
	function w3tcps_load(nocache) {
		$('.w3tcps_loading').removeClass('w3tc_none');
		$('.w3tc-gps-widget').addClass('w3tc_none');
		$('.w3tcps_error').addClass('w3tc_none');

		$.getJSON(ajaxurl + '?action=w3tc_ajax&_wpnonce=' + w3tc_nonce +
			'&w3tc_action=pagespeed_widgetdata' + (nocache ? '&cache=no' : ''),
			function(data) {
				$('.w3tcps_loading').addClass('w3tc_none');
				if (data.error) {
					$('.w3tcps_error .notice-error').html(w3tcData.lang.pagespeed_widget_data_error + data.error);
					$('.w3tcps_error').removeClass('w3tc_none');
					return;
				}
				$('.w3tc-gps-widget').html(data['.w3tc-gps-widget']);
				$('.w3tc-gps-widget').removeClass('w3tc_none').fadeIn('slow');
				$('#normal-sortables').masonry();
			}
		).fail(function(jqXHR, textStatus, errorThrown) {
			$('.w3tcps_error .notice-error').html(w3tcData.lang.pagespeed_widget_data_error + jqXHR.responseText);
			$('.w3tcps_error').removeClass('w3tc_none');
			$('.w3tc-gps-widget').addClass('w3tc_none');
			$('.w3tcps_loading').addClass('w3tc_none');
		});
	}
	
	/**
     * Toggle mobile view.
     *
     * @return void
     */
	function w3tcps_mobile_toggle() {
		$('#w3tcps_control_desktop').removeClass('nav-tab-active');
        $('#w3tcps_desktop').hide();
		$('#w3tcps_control_mobile').addClass('nav-tab-active');
		$('#w3tcps_mobile').show();
    }

    /**
     * Toggle desktop view.
     *
     * @return void
     */
    function w3tcps_desktop_toggle() {
		$('#w3tcps_control_mobile').removeClass('nav-tab-active')
		$('#w3tcps_mobile').hide();
		$('#w3tcps_control_desktop').addClass('nav-tab-active');
		$('#w3tcps_desktop').show();
    }

	$(document).on('click', '#w3tcps_control_mobile', w3tcps_mobile_toggle);
    $(document).on('click', '#w3tcps_control_desktop', w3tcps_desktop_toggle);

	$('.w3tcps_buttons').on('click', '.w3tcps_refresh', function() {
		w3tcps_load(true);
	});

	w3tcps_load(false);
});
