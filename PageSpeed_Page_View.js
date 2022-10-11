/**
 * File: PageSpeed_Page_View.js
 *
 * JavaScript for the PageSpeed page.
 *
 * @since 2.3.0 Update to utilize OAuth2.0 and overhaul of feature.
 *
 * @global w3tcData Localized data.
 */
jQuery(document).ready(function($) {
	/**
	 * Analyze GPS page_post URL via AJAX to Google PageSpeed Insights.
	 *
	 * @since 2.3.0
	 *
	 * @param object page_post GPS page page_post object.
	 * @param boolean nocache Flag to enable/disable results cache.
	 * 
	 * @return void
	 */
	function w3tcps_analyze(page_post, nocache) {
        let page_post_id = page_post.find('.w3tcps_buttons').attr('page_post_id');
        let page_post_url = page_post.find('.w3tcps_buttons').attr('page_post_url');

        page_post.find('.page_post_psresults').fadeOut('fast');
		page_post.find('.w3tcps_buttons').addClass('w3tc_none');
        page_post.find('.w3tcps_loading').removeClass('w3tc_none');
		page_post.find('.w3tcps_error').addClass('w3tc_none');

		$.ajax({
			type: 'GET',
			url: ajaxurl + '?action=w3tc_ajax&_wpnonce=' + w3tc_nonce + '&w3tc_action=pagespeed_data&url=' + encodeURIComponent( page_post_url ) + (nocache ? '&cache=no' : ''),
			dataType: 'json',
			success: function(data){
			    $('#' + page_post_id).prev().find('.w3tcps_loading').addClass('w3tc_none');
				if (data.error) {
					$('.w3tcps_buttons').removeClass( 'w3tc_none' );
					$('#' + page_post_id).prev().find('.w3tcps_error p').html( w3tcData.lang.pagespeed_data_error + data.error );
					$('#' + page_post_id).prev().find('.w3tcps_error').removeClass( 'w3tc_none' );
					return;
			    } else if (data.missing_token) {
					$('.w3tcps_buttons').addClass('w3tc_none');
					$('#' + page_post_id).prev().find('.w3tcps_missing_token p').html( data.notice );
					$('#' + page_post_id).prev().find('.w3tcps_missing_token').removeClass( 'w3tc_none' );
					return;
				}
				$('.w3tcps_timestamp').html(data['w3tcps_timestamp']);
				$('.w3tcps_buttons').removeClass( 'w3tc_none' );
				$('#' + page_post_id).html(data['w3tcps_content']).fadeIn('slow');
				$('.w3tcps_item_desciption a').attr('target', '_blank');
			},
			error: function(jqXHR, textStatus, errorThrown) {
			    $('.w3tcps_analyze').prop('disabled',false);
				$('#' + page_post_id).prev().find('.w3tcps_error p').html( w3tcData.lang.pagespeed_data_error + errorThrown );
			    $('#' + page_post_id).prev().find('.w3tcps_error').removeClass('w3tc_none');
			    $('#' + page_post_id).prev().find('.w3tcps_loading').addClass('w3tc_none');
			},
			async: true
        });
    }

	/**
	 * Toggle breakdown accordion.
	 * 
	 * @since 2.3.0
	 *
	 * @return void
	 */
    function w3tcps_breakdown_items_toggle() {
        $(this).find('.dashicons').toggleClass("dashicons-arrow-up-alt2 dashicons-arrow-down-alt2");
        $(this).next().slideToggle();
    }
   
	/**
	 * View mobile tab.
	 *
	 * @since 2.3.0
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
	 * View desktop tab.
	 * 
	 * @since 2.3.0
	 *
	 * @return void
	 */
    function w3tcps_desktop_toggle() {
		$('#w3tcps_control_mobile').removeClass('nav-tab-active')
		$('#w3tcps_mobile').hide();
		$('#w3tcps_control_desktop').addClass('nav-tab-active');
		$('#w3tcps_desktop').show();
    }

	/**
	 * View breakdown auidt type tab.
	 * 
	 * @since 2.3.0
	 *
	 * @return void
	 */
	function w3tcps_audit_filter( event ) {
		event.preventDefault();
		if ( 'ALL' === $(this).text() ) {
			$( '.w3tcps_breakdown .audits' ).show();
		} else if ( $(this).text().trim ) {
			$( '.w3tcps_breakdown .audits' ).hide();
			$( '.w3tcps_breakdown .' + $( this).text() ).delay(200).show();
		} else {
			$( '.w3tcps_breakdown .audits' ).show();
			alert( w3tcData.lang.pagespeed_filter_error );
		}
    }

	$(document).on('click', '.w3tcps_breakdown_items_toggle', w3tcps_breakdown_items_toggle);
	$(document).on('click', '#w3tcps_control_mobile', w3tcps_mobile_toggle);
    $(document).on('click', '#w3tcps_control_desktop', w3tcps_desktop_toggle);
	$(document).on('click', '.w3tcps_audit_filter', w3tcps_audit_filter);

    $('.w3tcps_content').on('click', '.w3tcps_analyze', function() {
		w3tcps_analyze($(this).closest('.page_post'),true);
	});

	w3tcps_analyze($('#w3tcps_home .page_post'),false);
});
