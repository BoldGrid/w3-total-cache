/**
 * File: Cdnfsd_BunnyCdn_Page_View.js
 *
 * @since   2.6.0
 * @package W3TC
 */

jQuery(function($) {
	/**
	 * Resize the popup modal.
	 *
	 * @param object o W3tc_Lightbox object.
	 */
	function w3tc_bunnycdn_resize(o) {
		o.resize();
	}

	// Add event handlers.
	$('body')
		// Load the authorization form.
		.on('click', '.w3tc_cdn_bunnycdn_fsd_authorize', function() {
			W3tc_Lightbox.open({
				id:'w3tc-overlay',
				close: '',
				width: 800,
				height: 300,
				url: ajaxurl +
					'?action=w3tc_ajax&_wpnonce=' +
					w3tc_nonce +
					'&w3tc_action=cdn_bunnycdn_fsd_intro',
				callback: w3tc_bunnycdn_resize
			});
		})

		// Sanitize the account API key input value.
		.on('change', '#w3tc-account-api-key', function() {
			var $this = $(this);

			$this.val($.trim($this.val().replace(/[^a-z0-9-]/g, '')));
		})

		// Load the pull zone selection form.
		.on('click', '.w3tc_cdn_bunnycdn_fsd_list_pull_zones', function() {
			var url = ajaxurl + '?action=w3tc_ajax&_wpnonce=' + w3tc_nonce +
				'&w3tc_action=cdn_bunnycdn_fsd_list_pull_zones';

			W3tc_Lightbox.load_form(url, '.w3tc_cdn_bunnycdn_fsd_form', w3tc_bunnycdn_resize);
		})

		// Enable/disable (readonly) add pull zone form fields based on selection.
		.on('change', '#w3tc-pull-zone-id', function() {
			var $selected_option = $(this).find(':selected'),
				$origin = $('#w3tc-origin-url'),
				$name = $('#w3tc-pull-zone-name'),
				$hostnames = $('#w3tc-custom-hostnames');

			if ($(this).find(':selected').val() === '') {
				// Enable the add pull zone fields with suggested or entered values.
				$origin.val($origin.data('suggested')).prop('readonly', false);
				$name.val($name.data('suggested')).prop('readonly', false);
				$hostnames.val($hostnames.data('suggested')).prop('readonly', false);
			} else {
				// Disable the add pull zone fields and change values using the selected option.
				$origin.prop('readonly', true).val($selected_option.data('origin'));
				$name.prop('readonly', true).val($selected_option.data('name'));
				$hostnames.prop('readonly', true).val($selected_option.data('custom-hostnames'));
			}

			// Update the hidden input field for the selected pull zone id from the select option value.
			$('[name="pull_zone_id"]').val($selected_option.val());

			// Update the hidden input field for the selected pull zone CDN hostname from the select option value.
			$('[name="cdn_hostname"]').val($selected_option.data('cdn-hostname'));
		})

		// Sanitize the origin URL/IP input value.
		.on('change', '#w3tc-origin-url', function() {
			var $this = $(this);

			$this.val($.trim($this.val().replace(/[^a-z0-9\.:\/-]/g, '')));
		})

		// Sanitize the pull zone name input value.
		.on('change', '#w3tc-pull-zone-name', function() {
			var $this = $(this);

			$this.val($.trim($this.val().replace(/[^a-z0-9-]/g, '')));
		})

		// Configure pull zone.
		.on('click', '.w3tc_cdn_bunnycdn_fsd_configure_pull_zone', function() {
			var url = ajaxurl + '?action=w3tc_ajax&_wpnonce=' + w3tc_nonce +
				'&w3tc_action=cdn_bunnycdn_fsd_configure_pull_zone';

			W3tc_Lightbox.load_form(url, '.w3tc_cdn_bunnycdn_fsd_form', w3tc_bunnycdn_resize);
		})

		// Close the popup success modal.
		.on('click', '.w3tc_cdn_bunnycdn_fsd_done', function() {
			window.location = window.location + '&';
		})

		// Load the deauthorize form.
		.on('click', '.w3tc_cdn_bunnycdn_fsd_deauthorization', function() {
			W3tc_Lightbox.open({
				id:'w3tc-overlay',
				close: '',
				width: 800,
				height: 300,
				url: ajaxurl +
					'?action=w3tc_ajax&_wpnonce=' +
					w3tc_nonce +
					'&w3tc_action=cdn_bunnycdn_fsd_deauthorization',
				callback: w3tc_bunnycdn_resize
			});
		})

		// Deauthorize and optionally delete the pull zone.
		.on('click', '.w3tc_cdn_bunnycdn_fsd_deauthorize', function() {
			var url = ajaxurl + '?action=w3tc_ajax&_wpnonce=' + w3tc_nonce +
				'&w3tc_action=cdn_bunnycdn_fsd_deauthorize';

			W3tc_Lightbox.load_form(url, '.w3tc_cdn_bunnycdn_fsd_form', w3tc_bunnycdn_resize);
		});
});
