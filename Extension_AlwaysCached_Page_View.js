/**
 * File: Extension_AlwaysCached_Page_View.js
 *
 * JavaScript for the Always Cached settings page.
 *
 * @since X.X.X
 *
 * @global w3tcData Localized data.
 */

jQuery(function() {
	jQuery(document).on(
		'click',
		'.w3tc_alwayscached_queue',
		function(e) {
			e.preventDefault();

			var mode = jQuery(this).data('mode');
			var elContainer = jQuery(this).parent().find('section');

			jQuery.ajax(
				{
					url: ajaxurl,
					method: 'GET',
					data: {
						action: 'w3tc_ajax',
						_wpnonce: w3tc_nonce[0],
						w3tc_action: 'extension_alwayscached_queue',
						mode: mode
					},
					success: function(data) {
						elContainer.html(data);
					}
				}
			);
		}
	);

	jQuery(document).on(
		'change',
		'#alwayscached___flush_all',
		function() {
			let $enabled = jQuery(this).prop('checked');
        
        	jQuery('#alwayscached___flush_all_home').prop('disabled', ! $enabled);
       		jQuery('#alwayscached___flush_all_posts_count').prop('disabled', ! $enabled);
        	jQuery('#alwayscached___flush_all_pages_count').prop('disabled', ! $enabled);
		}
	);
});
