jQuery(function($) {
	function w3tc_popup_resize(o) {
		o.resize();
	}

	$('body')
		.on('click', '.w3tc_cdnfsd_limelight_authorize', function() {
		    W3tc_Lightbox.open({
		        id:'w3tc-overlay',
		        close: '',
		        width: 800,
		        height: 300,
		        url: ajaxurl + '?action=w3tc_ajax&_wpnonce=' + w3tc_nonce +
            		'&w3tc_action=cdnfsd_limelight_intro',
		        callback: w3tc_popup_resize
		    });
		})



		.on('click', '.w3tc_cdnfsd_limelight_save', function() {
			var url = ajaxurl + '?action=w3tc_ajax&_wpnonce=' + w3tc_nonce +
        		'&w3tc_action=cdnfsd_limelight_save';

			var v = $('.w3tc_popup_form').find('input').each(function(i) {
				var name = $(this).attr('name');
				if (name)
					url += '&' + encodeURIComponent(name) + '=' +
						encodeURIComponent($(this).val());
			});

	    	W3tc_Lightbox.load(url, w3tc_popup_resize);
	    })



	    .on('click', '.w3tc_cdnfsd_limelight_done', function() {
			// refresh page
	    	window.location = window.location + '&';
	    })
});
