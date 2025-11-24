/**
 * File: Cdn_TotalCdn_Page_View.js
 *
 * @since   2.6.0
 * @package W3TC
 *
 * @global W3TC_TotalCdn Localization array for info and language.
 */

jQuery(function($) {
	/**
	 * Resize the popup modal.
	 *
	 * @param object o W3tc_Lightbox object.
	 */
	function w3tc_totalcdn_resize(o) {
		o.resize();
	}

	/**
	 * Extracts a user-friendly error message from a potentially complex error response.
	 *
	 * @since X.X.X
	 *
	 * @param string|object error_message The raw error message that may contain JSON or be an object.
	 *
	 * @return string A simplified, user-friendly error message.
	 */
	function extractUserFriendlyMessage( error_message ) {
		if ( ! error_message ) {
			return W3TC_TotalCdn.lang.error_updating;
		}

		var error_obj = null;

		// If error_message is already an object, use it directly.
		if ( typeof error_message === 'object' && error_message !== null ) {
			error_obj = error_message;
		} else if ( typeof error_message === 'string' ) {
			// Remove "Response code XXX: " prefix if present.
			var cleaned = error_message.replace( /^Response code \d+: /, '' ).trim();

			// Try to find and parse JSON in the string.
			var json_match = cleaned.match( /\{[\s\S]*\}/ );
			if ( json_match ) {
				try {
					error_obj = JSON.parse( json_match[0] );
				} catch ( e ) {
					// If JSON parsing fails, try parsing the entire cleaned string.
					if ( cleaned.indexOf( '{' ) === 0 ) {
						try {
							error_obj = JSON.parse( cleaned );
						} catch ( e2 ) {
							// If still fails, return the cleaned message.
							return cleaned;
						}
					} else {
						return cleaned;
					}
				}
			} else if ( cleaned.indexOf( '{' ) === 0 ) {
				// String starts with { but regex didn't match, try parsing directly.
				try {
					error_obj = JSON.parse( cleaned );
				} catch ( e ) {
					return cleaned;
				}
			} else {
				// No JSON found, return the cleaned message.
				return cleaned;
			}
		} else {
			return W3TC_TotalCdn.lang.error_updating;
		}

		// Extract user-friendly message from the error object.
		if ( error_obj && typeof error_obj === 'object' ) {
			var parts = [];

			// Extract the main error message.
			if ( error_obj.Error && typeof error_obj.Error === 'string' ) {
				parts.push( error_obj.Error );
			}

			// Extract the CDN provider response message if available.
			if ( error_obj['CDN Provider Response'] && typeof error_obj['CDN Provider Response'] === 'object' ) {
				if ( error_obj['CDN Provider Response'].Message && typeof error_obj['CDN Provider Response'].Message === 'string' ) {
					parts.push( error_obj['CDN Provider Response'].Message );
				}
			} else if ( error_obj.Message && typeof error_obj.Message === 'string' ) {
				parts.push( error_obj.Message );
			}

			if ( parts.length > 0 ) {
				return parts.join( '. ' );
			}
		}

		// Fallback: return a generic message.
		return W3TC_TotalCdn.lang.error_updating;
	}

	// Add event handlers.
	$('body')
		// Load the authorization or subscription form.
		.on('click', '.w3tc_cdn_totalcdn_authorize', function() {
			if ( W3TC_TotalCdn.has_api_key ) {
				W3tc_Lightbox.open({
					id:'w3tc-overlay',
					close: '',
					width: 800,
					height: 300,
					url: ajaxurl +
							'?action=w3tc_ajax&_wpnonce=' +
							w3tc_nonce +
							'&w3tc_action=cdn_totalcdn_list_pull_zones',
					callback: w3tc_totalcdn_resize
				});
			} else {
				w3tc_lightbox_buy_tcdn( w3tc_nonce, 'cdn_authorize', W3TC_TotalCdn.license_key );
			}
		})

		// Sanitize the account API key input value.
		.on('change', '#w3tc-account-api-key', function() {
			var $this = $(this);

			$this.val($.trim($this.val().replace(/[^a-z0-9-]/g, '')));
		})

		// Load the pull zone selection form.
		.on('click', '.w3tc_cdn_totalcdn_list_pull_zones', function() {
			var url = ajaxurl + '?action=w3tc_ajax&_wpnonce=' + w3tc_nonce +
				'&w3tc_action=cdn_totalcdn_list_pull_zones';

			W3tc_Lightbox.load_form(url, '.w3tc_cdn_totalcdn_form', w3tc_totalcdn_resize);
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
		.on('change', '#w3tc_totalcdn_hostname', function() {
			var $this = $(this);

			$this.val($.trim($this.val().toLowerCase().replace(/(^https?:|:.+$|[^a-z0-9\.-])/g, '')));
		})

		// Configure pull zone.
		.on('click', '.w3tc_cdn_totalcdn_configure_pull_zone', function() {
			var url = ajaxurl + '?action=w3tc_ajax&_wpnonce=' + w3tc_nonce +
				'&w3tc_action=cdn_totalcdn_configure_pull_zone';

			W3tc_Lightbox.load_form(url, '.w3tc_cdn_totalcdn_form', w3tc_totalcdn_resize);
		})

		// Close the popup success modal.
		.on('click', '.w3tc_cdn_totalcdn_done', function() {
			window.location = window.location + '&';
		})

		// Load the deauthorize form.
		.on('click', '.w3tc_cdn_totalcdn_deauthorization', function() {
			W3tc_Lightbox.open({
				id:'w3tc-overlay',
				close: '',
				width: 800,
				height: 300,
				url: ajaxurl +
					'?action=w3tc_ajax&_wpnonce=' +
					w3tc_nonce +
					'&w3tc_action=cdn_totalcdn_deauthorization',
				callback: w3tc_totalcdn_resize
			});
		})

		.on( 'click', '.w3tc_cdn_totalcdn_add_custom_hostname', function() {
			console.log('Loading custom hostname form...');
			W3tc_Lightbox.open({
				id:'w3tc-overlay',
				close: '',
				width: 800,
				height: 300,
				url: ajaxurl +
					'?action=w3tc_ajax&_wpnonce=' +
					w3tc_nonce +
					'&w3tc_action=cdn_totalcdn_add_custom_hostname',
				callback: w3tc_totalcdn_resize
			});
		} )

		.on( 'click', '.w3tc_cdn_totalcdn_load_free_ssl', function() {
			console.log('Loading free SSL form...');
			W3tc_Lightbox.open({
				id:'w3tc-overlay',
				close: '',
				width: 800,
				height: 300,
				url: ajaxurl +
					'?action=w3tc_ajax&_wpnonce=' +
					w3tc_nonce +
					'&w3tc_action=cdn_totalcdn_validate_dns',
				callback: w3tc_totalcdn_resize
			});
		} )

		.on ( 'click', '.w3tc_cdn_totalcdn_generate_and_save_ssl', function() {
			var url = ajaxurl + '?action=w3tc_ajax&_wpnonce=' + w3tc_nonce +
				'&w3tc_action=cdn_totalcdn_generate_and_save_ssl';

			W3tc_Lightbox.load_form(url, '.w3tc_cdn_totalcdn_form', w3tc_totalcdn_resize);
		} )

		.on( 'click', '.w3tc_cdn_totalcdn_remove_custom_hostname', function() {
			if ( ! confirm( W3TC_TotalCdn.lang.remove_custom_hostname_confirmation ) ) {
				return;
			}

			var $this = $(this);

			// Disable the button and show a spinner.
			$this
				.prop('disabled', true)
				.closest('td').addClass('lightbox-loader');

			$.ajax({
				method: 'POST',
				url: ajaxurl,
				data: {
					_wpnonce: w3tc_nonce[0],
					action: 'w3tc_ajax',
					w3tc_action: 'cdn_totalcdn_remove_custom_hostname'
				}
			} ).then( function( response ) {
				// Log the full JSON response for debugging.
				console.log( 'Custom hostname remove response:', JSON.stringify( response, null, 2 ) );

				if ( typeof response.success !== 'undefined' && response.success ) {
					// Reload the page to show the updated configuration.
					window.location = window.location + '&';
				} else {
					// Unsuccessful.
					var error_message = '';
					if ( typeof response.data !== 'undefined' && typeof response.data.error_message !== 'undefined' ) {
						console.log( 'Raw error message:', response.data.error_message );
						error_message = extractUserFriendlyMessage( response.data.error_message );
						console.log( 'Extracted error message:', error_message );
					} else {
						error_message = 'Unable to remove the custom hostname. Please try again.';
					}
					alert( error_message );

					$this
						.prop('disabled', false)
						.closest('td').removeClass('lightbox-loader');
				}
			} ).fail(function( response ) {
				// Log the full JSON response for debugging.
				var response_json = '';
				if ( typeof response.responseJSON !== 'undefined' ) {
					response_json = JSON.stringify( response.responseJSON, null, 2 );
				} else {
					response_json = JSON.stringify( response, null, 2 );
				}
				console.log( 'Custom hostname remove error response:', response_json );

				// Failure; received a non-2xx/3xx HTTP status code.
				var error_message = '';
				if ( typeof response.responseJSON !== 'undefined' && 'data' in response.responseJSON && 'error_message' in response.responseJSON.data ) {
					// An error message was passed in the response data.
					console.log( 'Raw error message (fail):', response.responseJSON.data.error_message );
					error_message = extractUserFriendlyMessage( response.responseJSON.data.error_message );
					console.log( 'Extracted error message (fail):', error_message );
				} else {
					// Unknown error - use simple, user-friendly message.
					error_message = W3TC_TotalCdn.lang.error_removing_custom_hostname;
				}
				alert( error_message );

				$this
					.prop('disabled', false)
					.closest('td').removeClass('lightbox-loader');
			});
		} )

		.on( 'click', '.w3tc_cdn_totalcdn_save_custom_hostname', function() {
			console.log('Saving custom hostname...');
			var custom_hostname = $('#w3tc-custom-hostname' ).val(),
				$messages = $('#w3tc-custom-hostname-messages'),
				$this = $(this);

			// Clear any existing error messages.
			$messages.empty();

			// Disable the button and show a spinner.
			$this
				.prop('disabled', true)
				.closest('p').addClass('lightbox-loader');

			$.ajax({
				method: 'POST',
				url: ajaxurl,
				data: {
					_wpnonce: w3tc_nonce[0],
					action: 'w3tc_ajax',
					w3tc_action: 'cdn_totalcdn_save_custom_hostname',
					custom_hostname: custom_hostname
				}
			} ).then( function( response ) {
				// Log the full JSON response for debugging.
				console.log( 'Custom hostname save response:', JSON.stringify( response, null, 2 ) );

				if ( typeof response.success !== 'undefined' ) {
					if ( response.success ) {
						// reload the page to show the new custom hostname.
						window.location = window.location + '&';
					} else {
						// Unsuccessful.
						var error_message = '';
						if ( typeof response.data !== 'undefined' && typeof response.data.error_message !== 'undefined' ) {
							console.log( 'Raw error message:', response.data.error_message );
							error_message = extractUserFriendlyMessage( response.data.error_message );
							console.log( 'Extracted error message:', error_message );
						} else {
							error_message = W3TC_TotalCdn.lang.error_saving_custom_hostname;
						}
						$('<div/>', {
							class: 'error',
							html: '<p>' + error_message + '</p>'
						}).appendTo($messages);

						$this
							.prop('disabled', false)
							.closest('p').removeClass('lightbox-loader');
					}
				} else {
					// Unknown error.
					$('<div/>', {
						class: 'error',
						html: '<p>' + W3TC_TotalCdn.lang.error_unexpected + '</p>'
					}).appendTo($messages);

					$this
						.prop('disabled', false)
						.closest('p').removeClass('lightbox-loader');
				}
			} ).fail(function( response ) {
				// Log the full JSON response for debugging.
				var response_json = '';
				if ( typeof response.responseJSON !== 'undefined' ) {
					response_json = JSON.stringify( response.responseJSON, null, 2 );
				} else {
					response_json = JSON.stringify( response, null, 2 );
				}
				console.log( 'Custom hostname save error response:', response_json );

				// Failure; received a non-2xx/3xx HTTP status code.
				var error_message = '';
				if ( typeof response.responseJSON !== 'undefined' && 'data' in response.responseJSON && 'error_message' in response.responseJSON.data ) {
					// An error message was passed in the response data.
					console.log( 'Raw error message (fail):', response.responseJSON.data.error_message );
					error_message = extractUserFriendlyMessage( response.responseJSON.data.error_message );
					console.log( 'Extracted error message (fail):', error_message );
				} else {
					// Unknown error - use simple, user-friendly message.
					error_message = 'Unable to save the custom hostname. Please check your connection and try again.';
				}
				$('<div/>', {
					class: 'error',
					html: '<p>' + error_message + '</p>'
				}).appendTo($messages);

				$this
					.prop('disabled', false)
					.closest('p').removeClass('lightbox-loader');
			});
		} )

		// Deauthorize and optionally delete the pull zone.
		.on('click', '.w3tc_cdn_totalcdn_deauthorize', function() {
			var url = ajaxurl + '?action=w3tc_ajax&_wpnonce=' + w3tc_nonce +
				'&w3tc_action=cdn_totalcdn_deauthorize';

			W3tc_Lightbox.load_form(url, '.w3tc_cdn_totalcdn_form', w3tc_totalcdn_resize);
		})

		// Sanitize the purge URL list.
		.on('focusout', '#w3tc-cdn-totalcdn-purge-urls', function () {
			// Abort if not authorized.
			if (! W3TC_TotalCdn.is_authorized) {
				return;
			}

			// Declare vars.
			var $this = $(this),
				$button = $('#w3tc-cdn-totalcdn-purge-urls-button');

			// Strip whitespace, newlines, and invalid characters.
			$this.val( $this.val().replace(/^(\s)*(\r\n|\n|\r)/gm, '') );
			$this.val($.trim($this.val().replace(/[^a-z0-9\.:\/\r\n*-]/g, '')));
		})

		// Purge URLs.
		.on('click', '#w3tc-cdn-totalcdn-purge-urls-button', function() {
			// Abort if not authorized.
			if (! W3TC_TotalCdn.is_authorized) {
				return;
			}

			// Declare vars.
			var urls_processed = 0,
				list = $('#w3tc-cdn-totalcdn-purge-urls').val().split("\n").filter((v) => v != ''),
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
					html: W3TC_TotalCdn.lang.empty_url + '.'
				}).appendTo($messages);

				$this
					.prop('disabled', false)
					.closest('p').removeClass('lightbox-loader');

				return;
			}

			list.forEach(function(url, index, array) {
				$.ajax({
					method: 'POST',
					url: ajaxurl,
					data: {
						_wpnonce: w3tc_nonce[0],
						action: 'w3tc_ajax',
						w3tc_action: 'cdn_totalcdn_purge_url',
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
									html: W3TC_TotalCdn.lang.success_purging + ' "' + url + '".'
								}).appendTo($messages);
							} else {
								// Unsucessful.
								$('<div/>', {
									class: 'error',
									html: W3TC_TotalCdn.lang.error_purging + ' "' + url + '"; ' + response.data.error_message + '.'
								}).appendTo($messages);
							}
						} else {
							// Unknown error.
							$('<div/>', {
								class: 'error',
								html: W3TC_TotalCdn.lang.error_ajax + '.'
							}).appendTo($messages);
						}
					})
					.fail(function(response) {
						// Failure; received a non-2xx/3xx HTTP status code.
						if (typeof response.responseJSON !== 'undefined' && 'data' in response.responseJSON && 'error_message' in response.responseJSON.data) {
							// An error message was passed in the response data.
							$('<div/>', {
								class: 'error',
								html: W3TC_TotalCdn.lang.error_purging + ' "' + url + '"; ' + response.responseJSON.data.error_message + '.'
							}).appendTo($messages);
						} else {
							// Unknown error.
							$('<div/>', {
								class: 'error',
								html: W3TC_TotalCdn.lang.error_ajax + '.'
							}).appendTo($messages);
						}
					})
					.complete(function() {
						urls_processed++;

						// When requests are all complete, then remove the spinner.
						if (urls_processed === array.length) {
							$this
								.prop('disabled', false)
								.closest('p').removeClass('lightbox-loader');
						}
					});
				});
		});
});
