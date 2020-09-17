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
		$container = jQuery( '#w3tc-wizard-container' ),
		$nextButton = $container.find( '#w3tc-wizard-next' ),
		$prevButton = $container.find( '#w3tc-wizard-previous' ),
		$skipButton = $container.find( '#w3tc-wizard-skip' );

	switch ( slideId ) {
		case 'w3tc-wizard-slide-welcome':
			$container.find( '.w3tc-wizard-steps' ).removeClass( 'is-active' );

			break;

		case 'w3tc-wizard-slide-pc1':
			// Test TTFB.
			$container.find( '.w3tc-wizard-steps' ).removeClass( 'is-active' );
			$container.find( '#w3tc-wizard-step-pagecache' ).addClass( 'is-active' );

			if ( ! $container.find( '#test-results' ).data( 'ttfb' ) ) {
				$nextButton.prop( 'disabled', 'disabled' );
			}

			$slide.find( '.notice' ).remove();

			$slide.find( '.w3tc-test-pagecache' ).unbind().on('click', function () {
				var $spinnerParent = $slide.find( '.spinner' ).addClass( 'is-active' ).parent();

				$prevButton.prop( 'disabled', 'disabled' );
				$spinnerParent.show();

				jQuery.ajax({
					method: 'POST',
					url: ajaxurl,
					data: {
						_wpnonce: $container.find( '[name="_wpnonce"]' ).val(),
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
					$container.find( '#test-results' ).data( 'ttfb', response.data );
					$slide.find( '#w3tc-ttfb-table tbody' ).html( results );
					$slide.find( '#w3tc-ttfb-table' ).show();
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
					$spinnerParent.hide();
				});
			});

			break;

		case 'w3tc-wizard-slide-pc2':
			// Display TTFB result and wait for user to click to enable Page Cache; then test and advance to next slide.
			$container.find( '.w3tc-wizard-steps' ).removeClass( 'is-active' );
			$container.find( '#w3tc-wizard-step-pagecache' ).addClass( 'is-active' );

			if ( ! $container.find( '#test-results' ).data( 'ttfb2' ) ) {
				$nextButton.prop( 'disabled', 'disabled' );
			}

			$slide.find( '.notice' ).remove();

			$slide.find( '.w3tc-test-pagecache' ).unbind().on('click', function () {
				var $enabled = $container.find( 'input:checkbox[name=enable_pagecache]' ),
					$spinnerParent = $slide.find( '.spinner' ).addClass( 'is-active' ).parent();

				$prevButton.prop( 'disabled', 'disabled' );
				$spinnerParent.show();

				jQuery.ajax({
					method: 'POST',
					url: ajaxurl,
					data: {
						_wpnonce: $container.find( '[name="_wpnonce"]' ).val(),
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
							_wpnonce: $container.find( '[name="_wpnonce"]' ).val(),
							action: 'w3tc_test_ttfb',
						}
					})
					.done(function( response ) {
						var results = '',
							diffCount = 0,
							diffTotal = 0,
							diffPercentTotal = 0,
							$testResults = $container.find( '#test-results' );

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
						$slide.find( '#w3tc-ttfb-table2 tbody' ).html( results );
						$slide.find( '#w3tc-ttfb-table2' ).show();
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
				})
				.complete(function() {
					$spinnerParent.hide();
				});
			});

			break;

		case 'w3tc-wizard-slide-dbc1':
			// Test database cache.
			$container.find( '.w3tc-wizard-steps' ).removeClass( 'is-active' );
			$container.find( '#w3tc-wizard-step-dbcache' ).addClass( 'is-active' );

			if ( ! $container.find( '#test-results' ).data( 'dbc' ) ) {
				$nextButton.prop( 'disabled', 'disabled' );
			}

			$slide.find( '.notice' ).remove();

			$slide.find( '.w3tc-test-dbcache' ).unbind().on('click', function () {
				var $spinnerParent = $slide.find( '.spinner' ).addClass( 'is-active' ).parent();

				$spinnerParent.show();

				// Test database cache for all available engines.
				jQuery.ajax({
					method: 'POST',
					url: ajaxurl,
					data: {
						_wpnonce: $container.find( '[name="_wpnonce"]' ).val(),
						action: 'w3tc_test_dbcache'
					}
				})
				.done(function( response ) {
					var results;

					$container.find( '#test-results' ).data( 'dbc', response.data );

					response.data.forEach(function( item, index ) {
						results += '<tr';

						if ( ! item.config.success ) {
							results += ' class="w3tc-option-disabled"';
						}

						results += '><td><input type="radio" name="dbc_engine" id="dbc-engine" value="' +
							item.key +
							'"';

						if ( ! item.config.success ) {
							results += ' disabled="disabled"';
						}

						if ( 0 === index ) {
							results += ' checked';
						}

						results += '></td><td>' +
							item.label +
							'</td><td>';

						if ( item.config.success ) {
							results += ( item.elapsed * 1000 ).toFixed( 2 );
						} else {
							results += W3TC_SetupGuide.unavailable_text;
						}

						results += '</td></tr>';
					});

					$container.find( '#w3tc-dbc-table tbody' ).html( results );
					$container.find( '#w3tc-dbc-table' ).show();

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
					$spinnerParent.hide();
				});
			});

			break;

		case 'w3tc-wizard-slide-oc1':
			$container.find( '.w3tc-wizard-steps' ).removeClass( 'is-active' );
			$container.find( '#w3tc-wizard-step-objectcache' ).addClass( 'is-active' );

			break;

		case 'w3tc-wizard-slide-bc1':
			// Test Browser Cache header.
			var $spinnerParent = $slide.find( '.spinner' ).addClass( 'is-active' ).parent();

			$nextButton.prop( 'disabled', 'disabled' );

			$container.find( '.w3tc-wizard-steps' ).removeClass( 'is-active' );
			$container.find( '#w3tc-wizard-step-browsercache' ).addClass( 'is-active' );

			$slide.find( '.notice' ).remove();
			$spinnerParent.show();

			jQuery.ajax({
				method: 'POST',
				url: ajaxurl,
				data: {
					_wpnonce: $container.find( '[name="_wpnonce"]' ).val(),
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

				$container.find( '#w3tc-browsercache-table tbody' ).html( results );
				$container.find( '#test-results' ).data( 'bc', response.data );
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
				$spinnerParent.hide();
			});

			break;

		case 'w3tc-wizard-slide-bc3':
			// Display Browser Cache result and wait for user to click to enable Page Cache; then test and advance to next slide.
			var $spinnerParent = $slide.find( '.spinner' ).addClass( 'is-active' ).parent();

			if ( ! $container.find( '#test-results' ).data( 'bc2' ) ) {
				$nextButton.prop( 'disabled', 'disabled' );
			}

			$slide.find( '.notice' ).remove();
			$spinnerParent.hide();

			$slide.find( '.w3tc-test-browsercache' ).unbind().on('click', function () {
				var $enabled = $container.find( 'input:checkbox[name=enable_browsercache]' );

				$prevButton.prop( 'disabled', 'disabled' );
				$spinnerParent.show();

				jQuery.ajax({
					method: 'POST',
					url: ajaxurl,
					data: {
						_wpnonce: $container.find( '[name="_wpnonce"]' ).val(),
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
							_wpnonce: $container.find( '[name="_wpnonce"]' ).val(),
							action: 'w3tc_test_browsercache',
						}
					})
					.done(function( response ) {
						var results = '',
							$testResults = $container.find( '#test-results' );
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

						$container.find( '#w3tc-browsercache-table2 tbody' ).html( results );
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
					$spinnerParent.hide();
				});
			});

			break;

		case 'w3tc-wizard-slide-bc4':
			$container.find( '.w3tc-wizard-steps' ).removeClass( 'is-active' );
			$container.find( '#w3tc-wizard-step-browsercache' ).addClass( 'is-active' );

			break;

		case 'w3tc-wizard-slide-ll1':
			$container.find( '.w3tc-wizard-steps' ).removeClass( 'is-active' );
			$container.find( '#w3tc-wizard-step-lazyload' ).addClass( 'is-active' );

			break;

		case 'w3tc-wizard-slide-complete':
			var html,
				diffAvg = $container.find( '#test-results' ).data( 'ttfb2' ).diffAvg,
				diffPercentAvg = $container.find( '#test-results' ).data( 'ttfb2' ).diffPercentAvg;

				$container.find( '.w3tc-wizard-steps' ).removeClass( 'is-active' );
				$container.find( '#w3tc-wizard-step-more' ).addClass( 'is-active' );

			html = ( diffAvg > 0 ? '+' : '' ) +
				diffAvg.toFixed( 2 ) +
				' ms (' +
				diffPercentAvg.toFixed( 2 ) +
				'%)';

				$container.find( '#w3tc-ttfb-diff-avg' ).html( html );

			if ( ! jQuery( '#test-results' ).data( 'completed' ) ) {
				jQuery.ajax({
					method: 'POST',
					url: ajaxurl,
					data: {
						_wpnonce: $container.find( '[name="_wpnonce"]' ).val(),
						action: "w3tc_wizard_skip"
					}
				})
				.done(function () {
					$container.find( '#test-results' ).data( 'completed', true );
				});
			}

			break;

		default:
			break;
	}
};
