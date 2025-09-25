/**
 * File: Cdn_TotalCdn_FsdPopup.js
 *
 * @since X.X.X
 *
 * @package W3TC
 */

var w3tcTotalcdnFsdModalOpen = false;

function w3tc_open_totalcdn_fsd_notice( config ) {
	if ( ! config || ! config.action ) {
		return;
	}

	var closeHandler = null;

	W3tc_Lightbox.open(
		{
			id: 'w3tc-overlay',
			close: '',
			width: 800,
			height: 360,
			url: ajaxurl + '?action=w3tc_ajax&_wpnonce=' + w3tc_nonce + '&w3tc_action=' + config.action,
			callback: function ( lightbox ) {
				function cleanup() {
					jQuery( document ).off( 'keyup.w3tc_totalcdn_fsd_notice' );
					closeHandler = null;
					w3tcTotalcdnFsdModalOpen = false;
					lightbox.close();
				}

				function handleConfirm() {
					if ( typeof config.onConfirm === 'function' ) {
						config.onConfirm();
					}
					cleanup();
				}

				function handleCancel() {
					if ( typeof config.onCancel === 'function' ) {
						config.onCancel();
					}
					cleanup();
				}

				jQuery(
					'.btn-primary',
					lightbox.container
				).on(
					'click',
					handleConfirm
				);

				closeHandler = handleCancel;

				jQuery(
					'.btn-secondary, .lightbox-close',
					lightbox.container
				).on(
					'click',
					closeHandler,
				);

				jQuery( document ).on(
					'keyup.w3tc_totalcdn_fsd_notice',
					function ( event ) {
						if ( 'Escape' === event.key && closeHandler ) {
							closeHandler();
						}
					}
				);

				lightbox.resize();
			},
		}
	);
}

function w3tc_show_totalcdn_fsd_enable_notice( config ) {
	if ( w3tcTotalcdnFsdModalOpen ) {
		return;
	}

	w3tcTotalcdnFsdModalOpen = true;

	w3tc_open_totalcdn_fsd_notice(
		jQuery.extend(
			{
				action: 'cdn_totalcdn_fsd_enable_notice',
			},
			config || {},
		),
	);
}

function w3tc_show_totalcdn_fsd_disable_notice( config ) {
	if ( w3tcTotalcdnFsdModalOpen ) {
		return;
	}

	w3tcTotalcdnFsdModalOpen = true;

	w3tc_open_totalcdn_fsd_notice(
		jQuery.extend(
			{
				action: 'cdn_totalcdn_fsd_disable_notice',
			},
			config || {},
		),
	);
}

jQuery(
	function ( $ ) {
		var $fsdCheckbox = $( '#cdnfsd__enabled' );
		var $engineSelect = $( '#cdnfsd__engine' );
		var committedCheckboxState = $fsdCheckbox.is( ':checked' );
		var committedEngine = $engineSelect.val();	

		function setCheckboxState( state ) {
			$fsdCheckbox.prop( 'checked', state );
			committedCheckboxState = state;
		}

		function setEngineValue( value ) {
			$engineSelect.val( value );
			committedEngine = value;
		}

		$fsdCheckbox.on(
			'mousedown keydown',
			function () {
				committedCheckboxState = $fsdCheckbox.is( ':checked' );
			}
		);

		$fsdCheckbox.on(
			'click',
			function () {
				var isChecked = $fsdCheckbox.is( ':checked' );
				var currentEngine = $engineSelect.val();

				if ( isChecked && ! committedCheckboxState && 'totalcdn' === currentEngine ) {
					w3tc_show_totalcdn_fsd_enable_notice(
						{
							onCancel: function () {
								setCheckboxState(false);
							},
							onConfirm: function () {
								setCheckboxState(true);
							},
						}
					);
				} else if (
					! isChecked &&
					committedCheckboxState &&
					'totalcdn' === committedEngine
				) {
					w3tc_show_totalcdn_fsd_disable_notice(
						{
							onCancel: function () {
								setCheckboxState( true );
							},
							onConfirm: function () {
								setCheckboxState( false );
							},
						}
					);
				}

				committedCheckboxState = $fsdCheckbox.is( ':checked' );
			}
		);

		$engineSelect.on(
			'change',
			function () {
				var newEngine = $engineSelect.val();
				var previousEngine = committedEngine;
				var fsdChecked = $fsdCheckbox.is( ':checked' );

				if ( ! fsdChecked ) {
					setEngineValue( newEngine );
					return;
				}

				if ( 'totalcdn' === newEngine && 'totalcdn' !== previousEngine ) {
					w3tc_show_totalcdn_fsd_enable_notice(
						{
							onCancel: function () {
								setEngineValue( previousEngine );
							},
							onConfirm: function () {
								setEngineValue( newEngine );
							},
						}
					);
				} else if ( 'totalcdn' !== newEngine && 'totalcdn' === previousEngine ) {
					w3tc_show_totalcdn_fsd_disable_notice(
						{
							onCancel: function () {
								setEngineValue( previousEngine );
							},
							onConfirm: function () {
								setEngineValue( newEngine );
							},
						}
					);
				} else {
					setEngineValue( newEngine );
				}
			}
		);

		// Ensure the flag resets if the lightbox script triggers a custom close event.
		$( document ).on(
			'w3tc_lightbox_closed',
			function () {
				w3tcTotalcdnFsdModalOpen = false;
			}
		);
	}
);
