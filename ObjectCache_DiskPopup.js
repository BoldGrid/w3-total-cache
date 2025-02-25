/**
 * File: ObjectCache_DiskPopup.js
 *
 * @since 2.8.6
 *
 * @package W3TC
 */

/**
 * Modal for object cache disk usage risk acceptance.
 *
 * @since 2.8.6
 *
 * @return void
 */
function w3tc_show_objectcache_diskpopup(previous, triggeredByEngineChange) {
	W3tc_Lightbox.open({
		id: 'w3tc-overlay',
		close: '',
		width: 800,
		height: 300,
		url: ajaxurl + '?action=w3tc_ajax&_wpnonce=' + w3tc_nonce + '&w3tc_action=objectcache_diskpopup',
		callback: function(lightbox) {
			jQuery('.btn-primary', lightbox.container).click(function() {
				jQuery(document).off('keyup.w3tc_lightbox'); // Cleanup event listener.
				lightbox.close();
			});

			jQuery('.btn-secondary, .lightbox-close').click(function() {
				if (triggeredByEngineChange) {
					// Case 2: Revert engine selection to previous.
					if (previous) {
						jQuery('#objectcache__engine').val(previous);
					}
				} else {
					// Case 1: Uncheck enable checkbox.
					jQuery('#objectcache__enabled').prop('checked', false);
				}

				jQuery('.objectcache_disk_notice').hide();
				jQuery(document).off('keyup.w3tc_lightbox'); // Cleanup event listener.
				lightbox.close();
			});

			jQuery(document).on('keyup.w3tc_lightbox', function(e) {
				if ('Escape' === e.key) {
					if (triggeredByEngineChange) {
						// Case 2: Revert engine selection to previous.
						if (previous) {
							jQuery('#objectcache__engine').val(previous);
						}
					} else {
						// Case 1: Uncheck enable checkbox.
						jQuery('#objectcache__enabled').prop('checked', false);
					}

					jQuery('.objectcache_disk_notice').hide();
					jQuery(document).off('keyup.w3tc_lightbox'); // Cleanup event listener.
					lightbox.close();
				}
			});

			lightbox.resize();
		}
	});
}

jQuery(function($) {
	$('#objectcache__enabled').click(function() {
		const checked = $(this).is(':checked'),
      		  engine = $('#objectcache__engine').val();

		if (!checked) {
			return;
		}

		if ('file' === engine) {
			w3tc_show_objectcache_diskpopup(null, false);
		}
	});

	let previous = null;

	$('#objectcache__engine').on('focus', function() {
		previous = this.value;
	}).change(function() {
		const checked = $('#objectcache__enabled').is(':checked'),
			  engine = $(this).val();

		if (!checked) {
			return;
		}

		if ('file' === engine) {
			w3tc_show_objectcache_diskpopup(previous, true);
		} else {
			// Only update `previous` if the new selection is NOT 'file'.
			previous = engine;
		}
	});
});
