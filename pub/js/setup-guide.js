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
	var slideId = $slide.prop( 'id' ),
		$nextButton = jQuery( '#w3tc-wizard-next' ),
		$prevButton = jQuery( '#w3tc-wizard-previous' ),
		$skipButton = jQuery( '#w3tc-wizard-skip' );

	switch ( slideId ) {
		case 'w3tc-wizard-slide-welcome':
			jQuery( '.w3tc-wizard-steps' ).removeClass( 'is-active' );

			break;

		case 'w3tc-wizard-slide-pc1':
			// Test TTFB.
			jQuery( '.w3tc-wizard-steps' ).removeClass( 'is-active' );
			jQuery( '#w3tc-wizard-step-pagecache' ).addClass( 'is-active' );
			$nextButton.prop( 'disabled', 'disabled' );
			$prevButton.prop( 'disabled', 'disabled' );
			$slide.find( '.notice' ).remove();
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
					results += '<tr><td><a target="_blank" href="' +
						item.url +
						'">' +
						item.urlshort +
						'</a></td><td>' +
						( item.ttfb * 1000 ).toFixed( 2 ) +
						'ms</td><td>??</td><td>??</td></tr>';
				});
				jQuery( '#test-results' ).data( 'ttfb', response.data );
				$slide.append(
					'<div class="notice notice-success"><p>' +
					W3TC_SetupGuide.test_complete_msg +
					'</p></div>'
				);
				jQuery( '#w3tc-ttfb-table tbody' ).html( results );
				$prevButton.removeProp( 'disabled' );
				$nextButton.removeProp( 'disabled' );
			})
			.fail(function() {
				$slide.append(
					'<p class="notice notice-error"><strong>' +
					W3TC_SetupGuide.test_error_msg +
					'</strong></p>'
				);
				$nextButton.closest( 'span' ).hide();
				$prevButton.closest( 'span' ).hide();
				$skipButton.closest( 'span' ).show();
			})
			.complete(function() {
				$slide.find( '.spinner' ).removeClass( 'is-active' ).closest( 'p' ).hide();
			});

			break;

		case 'w3tc-wizard-slide-pc3':
			// Display TTFB result and wait for user to click to enable Page Cache; then test and advance to next slide.
			if ( ! jQuery( '#test-results' ).data( 'ttfb2' ) ) {
				$nextButton.prop( 'disabled', 'disabled' );
			}

			$slide.find( '.notice' ).remove();
			$slide.find( '.spinner' ).removeClass( 'is-active' ).closest( 'p' ).hide();

			$slide.find( '.w3tc-test-pagecache' ).unbind().on('click', function () {
				var $enabled = jQuery( 'input:checkbox[name=enable_pagecache]' );

				$prevButton.prop( 'disabled', 'disabled' );
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
					$slide.append(
						'<p class="notice notice-error"><strong>' +
						W3TC_SetupGuide.config_error_msg +
						'</strong></p>'
					);

					$nextButton.closest( 'span' ).hide();
					$prevButton.closest( 'span' ).hide();
					$skipButton.closest( 'span' ).show();

					return false;
				})
				.done(function() {
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
							diffCount = 0,
							diffTotal = 0,
							diffPercentTotal = 0,
							$testResults = jQuery( '#test-results' );

						response.data.forEach(function( item, index ) {
							var before = $testResults.data( 'ttfb' )[ index ].ttfb * 1000,
								after = item.ttfb * 1000,
								diff = after - before,
								diffPercent = diff / before * 100;

							diffCount++;
							diffTotal += diff;
							diffPercentTotal += diffPercent;
							results += '<tr><td><a target="_blank" href="' +
								item.url +
								'">' +
								item.urlshort +
								'</a></td><td>' +
								before.toFixed( 2 ) +
								'ms</td><td>' +
								after.toFixed( 2 ) +
								'ms</td><td>' +
								( diff > 0 ? '+' : '' ) +
								parseInt( diff ).toFixed( 2 ) +
								' ms (' +
								diffPercent.toFixed( 2 ) +
								'%)</td></tr>';
						});
						response.data.diffAvg = diffTotal / diffCount;
						response.data.diffPercentAvg = diffPercentTotal / diffCount;
						$testResults.data( 'ttfb2', response.data );
						$slide.append(
							'<p class="notice notice-info">' +
							W3TC_SetupGuide.test_complete_msg +
							' <span class="spinner inline is-active"></span></p>'
						);
						jQuery( '#w3tc-ttfb-table2 tbody' ).html( results );
						$prevButton.removeProp( 'disabled' );
						$nextButton.removeProp( 'disabled' ).click();
					})
					.fail(function() {
						$slide.append(
							'<p class="notice notice-error"><strong>' +
							W3TC_SetupGuide.test_error_msg +
							'</strong></p>'
						);
						$nextButton.closest( 'span' ).hide();
						$prevButton.closest( 'span' ).hide();
						$skipButton.closest( 'span' ).show();
					})
				})
				.complete(function() {
					$slide.find( '.spinner' ).removeClass( 'is-active' ).closest( 'p' ).hide();
				});
			});

			break;

		case 'w3tc-wizard-slide-pc4':
			// TTFB results slide.
			jQuery( '.w3tc-wizard-steps' ).removeClass( 'is-active' );
			jQuery( '#w3tc-wizard-step-pagecache' ).addClass( 'is-active' );

			break;

		case 'w3tc-wizard-slide-dbc1':
			// Test database cache.
			$nextButton.prop( 'disabled', 'disabled' );

			jQuery( '.w3tc-wizard-steps' ).removeClass( 'is-active' );
			jQuery( '#w3tc-wizard-step-dbcache' ).addClass( 'is-active' );

			$slide.find( '.notice' ).remove();
			$slide.find( '.spinner' ).addClass( 'is-active' ).closest( 'p' ).show();

			// Test database cache with nocache option.
			jQuery.ajax({
				method: 'POST',
				url: ajaxurl,
				data: {
					_wpnonce: jQuery( '#w3tc-wizard-container [name="_wpnonce"]' ).val(),
					action: 'w3tc_test_dbcache',
					nocache: true
				}
			})
			.done(function( response ) {
				var results = '';
				response.data.forEach(function( item ) {
					results += '<tr><td><a target="_blank" href="' +
						item.url +
						'">' +
						item.urlshort +
						'</a></td><td>' +
						( item.ttfb * 1000 ).toFixed( 2 ) +
						'ms</td><td>??</td><td>??</td></tr>';
				});
				jQuery( '#test-results' ).data( 'dbc1', response.data );

				// Test database cache with cache primed.
				jQuery.ajax({
					method: 'POST',
					url: ajaxurl,
					data: {
						_wpnonce: jQuery( '#w3tc-wizard-container [name="_wpnonce"]' ).val(),
						action: 'w3tc_test_dbcache'
					}
				})
				.done(function( response ) {
					jQuery( '#test-results' ).data( 'dbc2', response.data );
					console.log(
						jQuery( '#test-results' ).data( 'dbc1' ).elapsed * 1000,
						jQuery( '#test-results' ).data( 'dbc2' ).elapsed * 1000,
						( jQuery( '#test-results' ).data( 'dbc2' ).elapsed - jQuery( '#test-results' ).data( 'dbc1' ).elapsed ) * 1000
					);
				});

				$slide.append(
					'<div class="notice notice-success"><p>' +
					W3TC_SetupGuide.test_complete_msg +
					'</p></div>'
				);
				jQuery( '#w3tc-ttfb-table tbody' ).html( results );

				$prevButton.removeProp( 'disabled' );
				$nextButton.removeProp( 'disabled' );
			})
			.fail(function() {
				$slide.append(
					'<p class="notice notice-error"><strong>' +
					W3TC_SetupGuide.test_error_msg +
					'</strong></p>'
				);
				$nextButton.closest( 'span' ).hide();
				$prevButton.closest( 'span' ).hide();
				$skipButton.closest( 'span' ).show();
			})
			.complete(function() {
				$slide.find( '.spinner' ).removeClass( 'is-active' ).closest( 'p' ).hide();
			});

			break;

		case 'w3tc-wizard-slide-oc1':
			jQuery( '.w3tc-wizard-steps' ).removeClass( 'is-active' );
			jQuery( '#w3tc-wizard-step-objectcache' ).addClass( 'is-active' );

			break;

		case 'w3tc-wizard-slide-bc1':
			// Test Browser Cache header.
			$nextButton.prop( 'disabled', 'disabled' );

			jQuery( '.w3tc-wizard-steps' ).removeClass( 'is-active' );
			jQuery( '#w3tc-wizard-step-browsercache' ).addClass( 'is-active' );

			$slide.find( '.notice' ).remove();
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
					results += '<tr><td><a target="_blank" href="' +
						item.url +
						'">' +
						item.filename +
						'</a></td><td>' +
						item.header +
						'</td><td>??</td></tr>';
				});

				$slide.append(
					'<div class="notice notice-success"><p>' +
					W3TC_SetupGuide.test_complete_msg +
					'</p></div>'
				);

				jQuery( '#w3tc-browsercache-table tbody' ).html( results );
				jQuery( '#test-results' ).data( 'bc', response.data );
				$prevButton.removeProp( 'disabled' );
				$nextButton.removeProp( 'disabled' );
			})
			.fail(function() {
				$slide.append(
					'<p class="notice notice-error"><strong>' +
					W3TC_SetupGuide.test_error_msg +
					'</strong></p>'
				);
				$nextButton.closest( 'span' ).hide();
				$prevButton.closest( 'span' ).hide();
				$skipButton.closest( 'span' ).show();
			})
			.complete(function() {
				$slide.find( '.spinner' ).removeClass( 'is-active' ).closest( 'p' ).hide();
			});

			break;

		case 'w3tc-wizard-slide-bc3':
			// Display Browser Cache result and wait for user to click to enable Page Cache; then test and advance to next slide.
			if ( ! jQuery( '#test-results' ).data( 'bc2' ) ) {
				$nextButton.prop( 'disabled', 'disabled' );
			}

			$slide.find( '.notice' ).remove();
			$slide.find( '.spinner' ).removeClass( 'is-active' ).closest( 'p' ).hide();

			$slide.find( '.w3tc-test-browsercache' ).unbind().on('click', function () {
				var $enabled = jQuery( 'input:checkbox[name=enable_browsercache]' );

				$prevButton.prop( 'disabled', 'disabled' );
				$slide.find( '.spinner' ).addClass( 'is-active' ).closest( 'p' ).show();

				jQuery.ajax({
					method: 'POST',
					url: ajaxurl,
					data: {
						_wpnonce: jQuery( '#w3tc-wizard-container [name="_wpnonce"]' ).val(),
						action: 'w3tc_config_browsercache',
						browsercache: $enabled.prop( 'checked' )
					}
				})
				.fail(function() {
					$slide.append(
						'<p class="notice notice-error"><strong>' +
						W3TC_SetupGuide.config_error_msg +
						'</strong></p>'
					);

					$nextButton.closest( 'span' ).hide();
					$prevButton.closest( 'span' ).hide();
					$skipButton.closest( 'span' ).show();

					return false;
				})
				.done(function() {
					jQuery.ajax({
						method: 'POST',
						url: ajaxurl,
						data: {
							_wpnonce: jQuery( '#w3tc-wizard-container [name="_wpnonce"]' ).val(),
							action: 'w3tc_test_browsercache',
						}
					})
					.done(function( response ) {
						var results = '',
							$testResults = jQuery( '#test-results' );
						response.data.forEach(function( item, index ) {
							var before = $testResults.data( 'bc' )[ index ].header,
								after = item.header;
							results += '<tr><td><a target="_blank" href="' +
								item.url +
								'">' +
								item.filename +
								'</a></td><td>' +
								before +
								'</td><td>' +
								after +
								'</td></tr>';
						});

						$testResults.data( 'bc2', response.data );
						$slide.append(
							'<p class="notice notice-info">' +
							W3TC_SetupGuide.test_complete_msg +
							' <span class="spinner inline is-active"></span></p>'
						);

						jQuery( '#w3tc-browsercache-table2 tbody' ).html( results );
						$prevButton.removeProp( 'disabled' );
						$nextButton.removeProp( 'disabled' ).click();
					})
					.fail(function() {
						$slide.append(
							'<p class="notice notice-error"><strong>' +
							W3TC_SetupGuide.test_error_msg +
							'</strong></p>'
						);

						$nextButton.closest( 'span' ).hide();
						$prevButton.closest( 'span' ).hide();
						$skipButton.closest( 'span' ).show();
					});
				})
				.complete(function() {
					$slide.find( '.spinner' ).removeClass( 'is-active' ).closest( 'p' ).hide();
				});
			});

			break;

		case 'w3tc-wizard-slide-bc4':
			jQuery( '.w3tc-wizard-steps' ).removeClass( 'is-active' );
			jQuery( '#w3tc-wizard-step-browsercache' ).addClass( 'is-active' );

			break;

		case 'w3tc-wizard-slide-ll1':
			jQuery( '.w3tc-wizard-steps' ).removeClass( 'is-active' );
			jQuery( '#w3tc-wizard-step-lazyload' ).addClass( 'is-active' );

			break;

		case 'w3tc-wizard-slide-complete':
			var html,
				diffAvg = jQuery( '#test-results' ).data( 'ttfb2' ).diffAvg,
				diffPercentAvg = jQuery( '#test-results' ).data( 'ttfb2' ).diffPercentAvg;

			jQuery( '.w3tc-wizard-steps' ).removeClass( 'is-active' );
			jQuery( '#w3tc-wizard-step-more' ).addClass( 'is-active' );

			html = ( diffAvg > 0 ? '+' : '' ) +
				diffAvg.toFixed( 2 ) +
				' ms (' +
				diffPercentAvg.toFixed( 2 ) +
				'%)';

			jQuery( '#w3tc-ttfb-diff-avg' ).html( html );

			if ( ! jQuery( '#test-results' ).data( 'completed' ) ) {
				jQuery.ajax({
					method: 'POST',
					url: ajaxurl,
					data: {
						_wpnonce: jQuery( '#w3tc-wizard-container [name="_wpnonce"]' ).val(),
						action: "w3tc_wizard_skip"
					}
				})
				.done(function () {
					jQuery( '#test-results' ).data( 'completed', true );
				});
			}

			break;

		default:
			break;
	}
};
