/**
 * File: Cdn_BunnyCdn_Page_View.js
 *
 * @since   2.6.0
 * @package W3TC
 *
 * @global W3TC_Bunnycdn Localization array for info and language.
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
		.on('click', '.w3tc_cdn_bunnycdn_authorize', function() {
			W3tc_Lightbox.open({
				id:'w3tc-overlay',
				close: '',
				width: 800,
				height: 300,
				url: ajaxurl +
					'?action=w3tc_ajax&_wpnonce=' +
					w3tc_nonce +
					'&w3tc_action=cdn_bunnycdn_intro',
				callback: w3tc_bunnycdn_resize
			});
		})

		// Sanitize the account API key input value.
		.on('change', '#w3tc-account-api-key', function() {
			var $this = $(this);

			$this.val($.trim($this.val().replace(/[^a-z0-9-]/g, '')));
		})

		// Load the pull zone selection form.
		.on('click', '.w3tc_cdn_bunnycdn_list_pull_zones', function() {
			var url = ajaxurl + '?action=w3tc_ajax&_wpnonce=' + w3tc_nonce +
				'&w3tc_action=cdn_bunnycdn_list_pull_zones';

			W3tc_Lightbox.load_form(url, '.w3tc_cdn_bunnycdn_form', w3tc_bunnycdn_resize);
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

			$this.val($.trim($this.val().toLowerCase().replace(/[^a-z0-9\.:\/-]/g, '')));
		})

		// Sanitize the pull zone name input value.
		.on('change', '#w3tc-pull-zone-name', function() {
			var $this = $(this);

			$this.val($.trim($this.val().toLowerCase().replace(/[^a-z0-9-]/g, '')));
		})

		// Sanitize the CDN hostname input value.
		.on('change', '#w3tc_bunnycdn_hostname', function() {
			var $this = $(this);

			$this.val($.trim($this.val().toLowerCase().replace(/(^https?:|:.+$|[^a-z0-9\.-])/g, '')));
		})

		// Configure pull zone.
		.on('click', '.w3tc_cdn_bunnycdn_configure_pull_zone', function() {
			var url = ajaxurl + '?action=w3tc_ajax&_wpnonce=' + w3tc_nonce +
				'&w3tc_action=cdn_bunnycdn_configure_pull_zone';

			W3tc_Lightbox.load_form(url, '.w3tc_cdn_bunnycdn_form', w3tc_bunnycdn_resize);
		})

		// Close the popup success modal.
		.on('click', '.w3tc_cdn_bunnycdn_done', function() {
			window.location = window.location + '&';
		})

		// Load the deauthorize form.
		.on('click', '.w3tc_cdn_bunnycdn_deauthorization', function() {
			W3tc_Lightbox.open({
				id:'w3tc-overlay',
				close: '',
				width: 800,
				height: 300,
				url: ajaxurl +
					'?action=w3tc_ajax&_wpnonce=' +
					w3tc_nonce +
					'&w3tc_action=cdn_bunnycdn_deauthorization',
				callback: w3tc_bunnycdn_resize
			});
		})

		// Deauthorize and optionally delete the pull zone.
		.on('click', '.w3tc_cdn_bunnycdn_deauthorize', function() {
			var url = ajaxurl + '?action=w3tc_ajax&_wpnonce=' + w3tc_nonce +
				'&w3tc_action=cdn_bunnycdn_deauthorize';

			W3tc_Lightbox.load_form(url, '.w3tc_cdn_bunnycdn_form', w3tc_bunnycdn_resize);
		})

		// Sanitize the purge URL list.
		.on('focusout', '#w3tc-purge-urls', function () {
			// Abort if Bunny CDN is not authorized.
			if (! W3TC_Bunnycdn.is_authorized) {
				return;
			}

			// Declare vars.
			var $this = $(this),
				$button = $('.w3tc_cdn_bunnycdn_purge_urls');

			// Strip whitespace, newlines, and invalid characters.
			$this.val( $this.val().replace(/^(\s)*(\r\n|\n|\r)/gm, '') );
			$this.val($.trim($this.val().replace(/[^a-z0-9\.:\/\r\n*-]/g, '')));

			// Enable the purge button.
			$button.prop('disabled', false);
		})

		// Purge URLs.
		.on('click', '.w3tc_cdn_bunnycdn_purge_urls', function() {
			// Abort if Bunny CDN is not authorized.
			if (! W3TC_Bunnycdn.is_authorized) {
				return;
			}

			// Declare vars.
			var urls_processed = 0,
				list = $('#w3tc-purge-urls').val().split("\n").filter((v) => v != ''),
				$messages = $('#w3tc-purge-messages'),
				$this = $(this);

			// Disable the button clicked and show a spinner.
			$this
				.prop('disabled', true)
				.closest('p').addClass('lightbox-loader');

			// Clear the messages div.
			$messages.empty();

			// Abort if nothing was submitted.
			if (list.length < 1) {
				$('<div/>', {
					class: 'error',
					text: W3TC_Bunnycdn.lang.empty_url + '.'
				}).appendTo($messages);

				$this.closest('p').removeClass('lightbox-loader');

				return;
			}

			list.forEach(function(url, index, array) {
				$.ajax({
					method: 'POST',
					url: ajaxurl,
					data: {
						_wpnonce: w3tc_nonce[0],
						action: 'w3tc_ajax',
						w3tc_action: 'cdn_bunnycdn_purge_url',
						url: url
					}
				})
					.done(function(response) {
						// Possible success.
						if (typeof response.success !== 'undefined') {
							if (response.success) {
								// Successful.
								$('<div/>', {
									class: 'updated',
									text: W3TC_Bunnycdn.lang.success_purging + ' "' + url + '".'
								}).appendTo($messages);
							} else {
								// Unsucessful.
								$('<div/>', {
									class: 'error',
									text: W3TC_Bunnycdn.lang.error_purging + ' "' + url + '"; ' + response.data.error_message + '.'
								}).appendTo($messages);
							}
						} else {
							// Unknown error.
							$('<div/>', {
								class: 'error',
								text: W3TC_Bunnycdn.lang.error_ajax + '.'
							}).appendTo($messages);
						}
					})
					.fail(function(response) {
						// Failure; received a non-2xx/3xx HTTP status code.
						if (typeof response.responseJSON !== 'undefined' && 'data' in response.responseJSON && 'error_message' in response.responseJSON.data) {
							// An error message was passed in the response data.
							$('<div/>', {
								class: 'error',
								text: W3TC_Bunnycdn.lang.error_purging + ' "' + url + '"; ' + response.responseJSON.data.error_message + '.'
							}).appendTo($messages);
						} else {
							// Unknown error.
							$('<div/>', {
								class: 'error',
								text: W3TC_Bunnycdn.lang.error_ajax + '.'
							}).appendTo($messages);
						}
					})
					.complete(function() {
						urls_processed++;

						// When requests are all complete, then remove the spinner.
						if (urls_processed === array.length) {
							$this.closest('p').removeClass('lightbox-loader');
						}
					});
				});
		});
});
