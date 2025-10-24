/**
 * File: exit-survey.js
 *
 * JavaScript for the exit survey modal.
 *
 * @since 2.8.3
 *
 * @global w3tcData Localized array variable.
 */

/**
 * Display the exit servey modal on plugin deactivation.
 *
 * @since 2.8.3
 */
function w3tc_exit_survey_render() {
	W3tc_Lightbox.open({
		id: 'w3tc-overlay',
		height: 'auto',
		maxWidth: 600,
		url: `${ajaxurl}?action=w3tc_ajax&_wpnonce=${w3tcData.nonce}&w3tc_action=exit_survey_render`,
		callback: function(lightbox) {
			// Retrieve the original deactivation URL.
			var deactivateUrl = jQuery('#deactivate-w3-total-cache').attr('href');

			// Cancel button action
			jQuery('#w3tc-exit-survey-skip', lightbox.container).on( 'click', function() {
				// Show spinner and disable interactions.
				lightbox.show_spinner();

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

				var remove = jQuery('input[name="remove"]:checked', lightbox.container).val();

				if ( 'yes' === remove ) {
					// Build the params object.
					var params = {
						action: 'w3tc_ajax',
						_wpnonce: w3tcData.nonce,
						w3tc_action: 'exit_survey_skip',
						remove: remove
					};

					// Send the remove data flag via AJAX.
					jQuery.post( ajaxurl, params, function(response) {
						if(response.error && window.w3tc_ga) {
							w3tc_ga(
								'event',
								'w3tc_error',
								{
									eventCategory: 'exit_survey',
									eventLabel: 'skip_error'
								}
							);
						}
					});
				}

				lightbox.close();
				window.location.href = deactivateUrl;
			});

			// Handle form submission.
			jQuery('#w3tc-exit-survey-form', lightbox.container).on('submit', function(event) {
				event.preventDefault();

				// Show spinner and disable interactions.
				lightbox.show_spinner();

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

				// Collect form data.
				var reason = jQuery('input[name="reason"]:checked', lightbox.container).val();
				var other = jQuery('input[name="other"]', lightbox.container).val();
				var email = jQuery('input[name="email"]', lightbox.container).val();
				var remove = jQuery('input[name="remove"]:checked', lightbox.container).val();

				// Build the params object.
				var params = {
					action: 'w3tc_ajax',
					_wpnonce: w3tcData.nonce,
					w3tc_action: 'exit_survey_submit',
					reason: reason,
					other: other,
					email: email,
					remove: remove
				};

				// Send the survey data to the API server.
				jQuery.post( ajaxurl, params, function(response) {
					if(response.error && window.w3tc_ga) {
						w3tc_ga(
							'event',
							'w3tc_error',
							{
								eventCategory: 'exit_survey',
								eventLabel: 'api_error'
							}
						);
					}

					lightbox.close();
					window.location.href = deactivateUrl;
				});
			});

			lightbox.resize();
		}
	});
}

// On document ready.
jQuery(function() {
	/**
	 * Trigger display of exit survey on plugin deactivation link click.
	 *
	 * @since 2.8.3
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

	// Listen for changes on the radio buttons.
	jQuery(document).on('change', 'input[name="reason"]', function() {
		// Enable Submit & Deactivate button once an option is selected.
		if (jQuery('input[name="reason"]:checked').length > 0) {
			jQuery('.w3tc-exit-survey-email #email').prop('disabled', false);
			jQuery('#w3tc-exit-survey-submit').prop('disabled', false);
		}

		// Show more info input box.
		jQuery('#w3tc_exit_survey_uninstall_reason_more_info').show();
	});
});
