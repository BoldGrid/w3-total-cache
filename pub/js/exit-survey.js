/**
 * Display the exit servey modal on plugin deactivation.
 *
 * @since X.X.X
 */
function w3tc_exit_survey_render() {
	W3tc_Lightbox.open({
		id: 'w3tc-overlay',
		maxWidth: 600,
		url: ajaxurl +
			'?action=w3tc_ajax&_wpnonce=' + w3tc_nonce + '&w3tc_action=exit_survey_render' +
			(w3tc_ga_cid ? '&client_id=' + encodeURIComponent(w3tc_ga_cid) : ''),
		callback: function(lightbox) {
			// Retrieve the original deactivation URL
			var deactivateUrl = jQuery('#deactivate-w3-total-cache').attr('href');

			// Cancel button action
			jQuery('#w3tc-exit-survey-skip', lightbox.container).on( 'click', function() {
				// Show spinner and disable interactions
				showSpinner();

				if (window.w3tc_ga) {
					w3tc_ga(
						'event',
						'button',
						{
							eventCategory: 'click',
							eventLabel: 'exit_survey_skip'
						}
					);
				}

				// Close the lightbox
				lightbox.close();
				// Proceed with plugin deactivation
				window.location.href = deactivateUrl;
			});

			// Handle form submission
			jQuery('#w3tc-exit-survey-form', lightbox.container).on('submit', function(event) {
				event.preventDefault();

				// Show spinner and disable interactions
				showSpinner();

				if (window.w3tc_ga) {
					w3tc_ga(
						'event',
						'button',
						{
							eventCategory: 'click',
							eventLabel: 'exit_survey_submit'
						}
					);
				}

				// Collect form data
				var reason = jQuery('input[name="reason"]:checked', lightbox.container).val();
				var other = jQuery('input[name="other"]', lightbox.container).val();
				var remove = jQuery('input[name="remove"]:checked', lightbox.container).val();
				
				// Build the params object
				var params = {
					action: 'w3tc_ajax',
					_wpnonce: w3tc_nonce,
					w3tc_action: 'exit_survey_submit',
					reason: reason,
					other: other,
					remove: remove
				};

				// Send the survey data to your API server
				jQuery.post( ajaxurl, params, function(response) {
					if(response.success) {
						alert(response.data.message);
						lightbox.close();
						window.location.href = deactivateUrl;
					} else {
						alert(response.data.message);
						// Hide spinner and re-enable buttons/links
						hideSpinner();
					}
				});
			});

			lightbox.resize();
		}
	});
}

// Show the loading spinner and gray out the modal
function showSpinner() {
    jQuery('#w3tc-exit-surey-spinner').show();  // Show the spinner
    jQuery('#w3tc-exit-survey-modal').css('opacity', '0.5');  // Gray out the modal
    jQuery('#w3tc-exit-survey-form').find('input, button, a').prop('disabled', true);  // Disable other interactions
}

// Hide the loading spinner and restore interaction
function hideSpinner() {
    jQuery('#w3tc-exit-surey-spinner').hide();  // Hide the spinner
    jQuery('#w3tc-exit-survey-modal').css('opacity', '1');  // Restore the modal opacity
    jQuery('#w3tc-exit-survey-form').find('input, button, a').prop('disabled', false);  // Enable interactions
}

// On document ready.
jQuery(function() {
	/**
	 * Trigger display of exit survey on plugin deactivation link click.
	 *
	 * @since X.X.X
	 */
	jQuery('#deactivate-w3-total-cache').on( 'click', function(e) {
		e.preventDefault();
	
		if (window.w3tc_ga) {
			w3tc_ga(
				'event',
				'button',
				{
					eventCategory: 'click',
					eventLabel: 'exit_survey_open'
				}
			);
		}
	
		w3tc_exit_survey_render();
		return false;
	});

	// Listen for changes on the radio buttons
	jQuery(document).on('change', 'input[name="reason"]', function() {
		// Enable Submit & Deactivate button once an option is selected
		if (jQuery('input[name="reason"]:checked').length > 0) {
			jQuery('#w3tc-exit-survey-submit').prop('disabled', false);
		}

		// If the "Other" option is selected, show the text box
		if (jQuery(this).val() === 'other') {
			jQuery('#w3tc_exit_survey_uninstall_reason_other').show();
		} else {
			jQuery('#w3tc_exit_survey_uninstall_reason_other').hide().val(''); // Clear the "Other" input when not selected
		}
	});
});