/**
 * File: setup-guide.js
 *
 * JavaScript for the Setup Guide page.
 *
 * @since X.X.X
 *
 * @global W3TC-setup-guide Localized array variable.
 */

 /**
  * Wizard actions.
  *
  * @since X.X.X
  *
  * @param object $slide The div of the slide displayed.
  */
function w3tc_wizard_actions( $slide ) {
	var slideId = $slide.attr( 'id' ),
		$navButtonsEnabled = jQuery( '.w3tc-wizard-buttons:enabled' );

	switch ( slideId ) {
		case 'w3tc-wizard-slide-2':
			// Test TTFB and then advance to the next slide.
			$navButtonsEnabled.attr( 'disabled', 'disabled' );
			jQuery( '#w3tc-wizard-step-1' ).addClass( 'is-active' );
			$slide.find( '.notice' ).remove();
			jQuery( '#w3tc-ttfb-table tbody' ).empty();
			$slide.find( '.spinner' ).addClass( 'is-active' ).closest( 'p' ).show();
			jQuery.ajax({
				method: 'POST',
				url: ajaxurl,
				data: {
					_wpnonce: jQuery( '#w3tc-wizard-container [name="_wpnonce"]' ).val(),
					action: 'w3tc_test_ttfb',
					nocache: true
				}
			})
			.done(function( response ) {
				var results = '';
				response.data.forEach(function( item ) {
					results += '<tr><td>' + item.url + '</td><td>' +
						( item.ttfb * 1000 ).toFixed( 2 ) +
						'ms | ?? | ??</td></tr>';
				});
				jQuery( '#test-results' ).data( 'ttfb', response.data );
				$slide.append( '<p class="notice notice-info">' + W3TC_SetupGuide.test_complete_msg + ' <span class="spinner inline is-active"></span></p>' );
				$navButtonsEnabled.removeAttr( 'disabled' );
				jQuery( '#w3tc-ttfb-table tbody' ).append( results );
				jQuery( '#w3tc-wizard-next' ).click();
			})
			.fail(function() {
				$slide.append( '<p class="notice notice-error"><strong>' + W3TC_SetupGuide.test_error_msg + '</strong></p>' );
				jQuery( '#w3tc-wizard-next' ).closest( 'span' ).hide();
				jQuery( '#w3tc-wizard-previous' ).closest( 'span' ).hide();
				jQuery( '#w3tc-wizard-skip' ).closest( 'span' ).show();
			})
			.complete(function() {
				$slide.find( '.spinner' ).removeClass( 'is-active' ).closest( 'p' ).hide();
			});
			break;
		case 'w3tc-wizard-slide-4':
			// Display TTFB result and wait for user to click to enable Page Cache; then test and advance to next slide.
			jQuery( '#w3tc-wizard-next' ).attr( 'disabled', 'disabled' );
			$slide.find( '.notice' ).remove();
			jQuery( '#w3tc-ttfb-table2 tbody' ).empty();
			$slide.find( '.spinner' ).removeClass( 'is-active' ).closest( 'p' ).hide();

			$slide.find( '#w3tc-test-pagecache' ).unbind().on('click', function () {
				var $enabled = jQuery( 'input:checkbox[name=enable_pagecache]' );

				jQuery( '#w3tc-wizard-previous' ).attr( 'disabled', 'disabled' );
				$slide.find( '.spinner' ).addClass( 'is-active' ).closest( 'p' ).show();

				jQuery.ajax({
					method: 'POST',
					url: ajaxurl,
					data: {
						_wpnonce: jQuery( '#w3tc-wizard-container [name="_wpnonce"]' ).val(),
						action: 'w3tc_config_pagecache',
						pagecache: $enabled.prop( 'checked' )
					}
				})
				.fail(function() {
					$slide.append( '<p class="notice notice-error"><strong>' + W3TC_SetupGuide.config_error_msg + '</strong></p>' );
					jQuery( '#w3tc-wizard-next' ).closest( 'span' ).hide();
					jQuery( '#w3tc-wizard-previous' ).closest( 'span' ).hide();
					jQuery( '#w3tc-wizard-skip' ).closest( 'span' ).show();
					return false;
				});


				jQuery.ajax({
					method: 'POST',
					url: ajaxurl,
					data: {
						_wpnonce: jQuery( '#w3tc-wizard-container [name="_wpnonce"]' ).val(),
						action: 'w3tc_test_ttfb',
					}
				})
				.done(function( response ) {
					var results = '',
						$testResults = jQuery( '#test-results' );
					response.data.forEach(function( item, index ) {
						var before = $testResults.data( 'ttfb' )[ index ].ttfb * 1000,
							after = item.ttfb * 1000,
							diff = after - before;
						results += '<tr><td>' + item.url + '</td><td>' +
							before.toFixed( 2 ) +
							'ms | ' +
							after.toFixed( 2 ) +
							'ms | ' +
							( diff > 0 ? '+' : '' ) +
							diff.toFixed( 2 ) +
							' ms (' +
							( diff / before * 100 ).toFixed( 2 ) +
							'%)</td></tr>';
					});
					$testResults.data( 'ttfb2', response.data );
					$slide.append( '<p class="notice notice-info">' + W3TC_SetupGuide.test_complete_msg + ' <span class="spinner inline is-active"></span></p>' );
					$navButtonsEnabled.removeAttr( 'disabled' );
					jQuery( '#w3tc-ttfb-table2 tbody' ).html( results );
					jQuery( '#w3tc-wizard-next' ).click();
				})
				.fail(function() {
					$slide.append( '<p class="notice notice-error"><strong>' + W3TC_SetupGuide.test_error_msg + '</strong></p>' );
					jQuery( '#w3tc-wizard-next' ).closest( 'span' ).hide();
					jQuery( '#w3tc-wizard-previous' ).closest( 'span' ).hide();
					jQuery( '#w3tc-wizard-skip' ).closest( 'span' ).show();
				})
				.complete(function() {
					$slide.find( '.spinner' ).removeClass( 'is-active' ).closest( 'p' ).hide();
				});
			});
			break;
		case 'w3tc-wizard-slide-5':
			// TTFB results slide.
			jQuery( '#w3tc-wizard-step-2' ).removeClass( 'is-active' );
			jQuery( '#w3tc-wizard-step-1' ).addClass( 'is-active' );
			break;
		case 'w3tc-wizard-slide-6':
			// Test Browser Cache header.
			$navButtonsEnabled.attr( 'disabled', 'disabled' );
			jQuery( '#w3tc-wizard-step-1' ).removeClass( 'is-active' );
			jQuery( '#w3tc-wizard-step-2' ).addClass( 'is-active' );
			jQuery( '#w3tc-browsercache-table tbody' ).empty();
			$slide.find( '.spinner' ).addClass( 'is-active' ).closest( 'p' ).show();
			jQuery.ajax({
				method: 'POST',
				url: ajaxurl,
				data: {
					_wpnonce: jQuery( '#w3tc-wizard-container [name="_wpnonce"]' ).val(),
					action: 'w3tc_test_browsercache'
				}
			})
			.done(function( response ) {
				var results = '';
				response.data.forEach(function( item ) {
					results += '<tr><td>' + item.url + '</td><td>' +
						item.header +
						' | ??</td></tr>';
				});
				jQuery( '#test-results' ).data( 'bc', response.data );
				$slide.append( '<p class="notice notice-info">' + W3TC_SetupGuide.test_complete_msg + ' <span class="spinner inline is-active"></span></p>' );
				$navButtonsEnabled.removeAttr( 'disabled' );
				jQuery( '#w3tc-browsercache-table tbody' ).append( results );
				jQuery( '#w3tc-wizard-next' ).click();
			})
			.fail(function() {
				$slide.append( '<p class="notice notice-error"><strong>' + W3TC_SetupGuide.test_error_msg + '</strong></p>' );
				jQuery( '#w3tc-wizard-next' ).closest( 'span' ).hide();
				jQuery( '#w3tc-wizard-previous' ).closest( 'span' ).hide();
				jQuery( '#w3tc-wizard-skip' ).closest( 'span' ).show();
			})
			.complete(function() {
				$slide.find( '.spinner' ).removeClass( 'is-active' ).closest( 'p' ).hide();
			});
			break;
		case 'w3tc-wizard-slide-8':
			$navButtonsEnabled.attr( 'disabled', 'disabled' );
			$slide.find( '.spinner' ).addClass( 'is-active' );
			/*
			jQuery.ajax({
				method: 'POST',
				url: ajaxurl,
				data: {
					_wpnonce: jQuery( '[name="_wpnonce"]' ).val()
				}
			});
			*/
			$navButtonsEnabled.removeAttr( 'disabled' );
			break;
		case 'w3tc-wizard-slide-9':
			jQuery( '#w3tc-wizard-step-3' ).removeClass( 'is-active' );
			jQuery( '#w3tc-wizard-step-2' ).addClass( 'is-active' );
			break;
		case 'w3tc-wizard-slide-10':
			jQuery( '#w3tc-wizard-step-2' ).removeClass( 'is-active' );
			jQuery( '#w3tc-wizard-step-3' ).addClass( 'is-active' );
			break;
		default:
			break;
	}
};
