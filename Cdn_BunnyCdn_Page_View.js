/**
 * File: Cdn_BunnyCdn_Page_View.js
 *
 * @since   X.X.X
 * @package W3TC
 */

jQuery(function($) {
	function w3tc_bunnycdn_resize(o) {
		o.options.height = jQuery('.w3tc_cdn_bunnycdn_form').height();
		o.resize();
	}

	$('body')
		.on('click', '.w3tc_cdn_bunnycdn_authorize', function() {
			W3tc_Lightbox.open({
				id:'w3tc-overlay',
				close: '',
				width: 800,
				height: 300,
				url: ajaxurl + '?action=w3tc_ajax&_wpnonce=' + w3tc_nonce +
					'&w3tc_action=cdn_bunnycdn_intro',
				callback: w3tc_bunnycdn_resize
			});
		})

		.on('click', '.w3tc_cdn_bunnycdn_list_stacks', function() {
			var url = ajaxurl + '?action=w3tc_ajax&_wpnonce=' + w3tc_nonce +
				'&w3tc_action=cdn_bunnycdn_list_stacks';

			W3tc_Lightbox.load_form(url, '.w3tc_cdn_bunnycdn_form', w3tc_bunnycdn_resize);
		})

		.on('click', '.w3tc_cdn_bunnycdn_list_sites', function() {
			var url = ajaxurl + '?action=w3tc_ajax&_wpnonce=' + w3tc_nonce +
				'&w3tc_action=cdn_bunnycdn_list_sites';

			W3tc_Lightbox.load_form(url, '.w3tc_cdn_bunnycdn_form', w3tc_bunnycdn_resize);
		})

		.on('click', '.w3tc_cdn_bunnycdn_view_site', function() {
			var url = ajaxurl + '?action=w3tc_ajax&_wpnonce=' + w3tc_nonce +
				'&w3tc_action=cdn_bunnycdn_view_site';

			W3tc_Lightbox.load_form(url, '.w3tc_cdn_bunnycdn_form', w3tc_bunnycdn_resize);
		})

		.on('click', '.w3tc_cdn_bunnycdn_configure_site', function() {
			var url = ajaxurl + '?action=w3tc_ajax&_wpnonce=' + w3tc_nonce +
				'&w3tc_action=cdn_bunnycdn_configure_site';

			W3tc_Lightbox.load_form(url, '.w3tc_cdn_bunnycdn_form', w3tc_bunnycdn_resize);
		})

		.on('click', '.w3tc_cdn_bunnycdn_configure_site_skip', function() {
			var url = ajaxurl + '?action=w3tc_ajax&_wpnonce=' + w3tc_nonce +
				'&w3tc_action=cdn_bunnycdn_configure_site_skip';

			W3tc_Lightbox.load_form(url, '.w3tc_cdn_bunnycdn_form', w3tc_bunnycdn_resize);
		})

		.on('click', '.w3tc_cdn_bunnycdn_done', function() {
			// Refresh page.
			window.location = window.location + '&';
		});
});
