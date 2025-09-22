/**
 * File: Cdn_TotalCdn_FsdEnablePopup.js
 *
 * @package W3TC
 */

var w3tcTotalcdnFsdModalOpen = false;

/**
 * Modal for Total CDN Full Site Delivery enablement reminder.
 *
 * @return void
 */
function w3tc_show_totalcdn_fsd_enable_notice(
	previousEngine,
	triggeredByEngineChange,
) {
	var closeHandler = null;

	function revertSelection() {
		if ( triggeredByEngineChange ) {
			if ( previousEngine !== null && typeof previousEngine !== "undefined" ) {
				jQuery( "#cdnfsd__engine" ).val( previousEngine );
			}
		} else {
			jQuery( "#cdnfsd__enabled" ).prop( "checked", false );
		}
	}

	W3tc_Lightbox.open(
		{
	    	id: "w3tc-overlay",
    		close: "",
    		width: 800,
    		height: 360,
    		url: ajaxurl + "?action=w3tc_ajax&_wpnonce=" + w3tc_nonce + "&w3tc_action=cdn_totalcdn_fsd_enable_notice",
			callback: function ( lightbox ) {
				function cleanup() {
					jQuery( document ).off( "keyup.w3tc_totalcdn_fsd_notice" );
					closeHandler = null;
					w3tcTotalcdnFsdModalOpen = false;
					lightbox.close();
				}

				jQuery( ".btn-primary", lightbox.container ).on(
					"click",
					function () {
						cleanup();
					}
				);

				closeHandler = function () {
					revertSelection();
					cleanup();
				};

				jQuery(
					".btn-secondary, .lightbox-close",
					lightbox.container
				).on(
					"click",
					closeHandler,
				);

				jQuery( document ).on(
					"keyup.w3tc_totalcdn_fsd_notice",
					function ( event ) {
						if ( "Escape" === event.key && closeHandler ) {
							closeHandler();
						}
					}
				);

				lightbox.resize();
			},
		}
	);
}

jQuery(
	function ( $ ) {
		var previousEngine = null;

		function maybeShowNotice( triggeredByEngineChange ) {
			if ( w3tcTotalcdnFsdModalOpen ) {
				return;
			}

			var enabled = $( "#cdnfsd__enabled" ).is( ":checked" );
    		var engine = $( "#cdnfsd__engine" ).val();

    		if ( ! enabled || "totalcdn" !== engine ) {
				return;
			}

			w3tcTotalcdnFsdModalOpen = true;

			var originalPrevious = triggeredByEngineChange ? previousEngine : null;

			w3tc_show_totalcdn_fsd_enable_notice(
				originalPrevious,
				triggeredByEngineChange,
			);
		}

		$( "#cdnfsd__enabled" ).on(
			"click",
			function () {
				var isChecked = $( this ).is( ":checked" );

				if ( ! isChecked ) {
					return;
				}

				maybeShowNotice( false );
			}
		);

		$( "#cdnfsd__engine" ).on(
			"focus",
			function () {
				previousEngine = this.value;
			}
		).on(
			"change",
			function () {
				var engine = $( this ).val();
				var enabled = $( "#cdnfsd__enabled" ).is( ":checked" );

				if ( ! enabled ) {
					previousEngine = engine;
					return;
				}

				if ( "totalcdn" === engine ) {
					maybeShowNotice( true );
				} else {
					previousEngine = engine;
				}
			}
		);

		// Ensure the flag resets if the lightbox script triggers a custom close event.
		$( document ).on(
			"w3tc_lightbox_closed",
			function () {
				w3tcTotalcdnFsdModalOpen = false;
			}
		);
	}
);