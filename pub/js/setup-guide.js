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
	var configSuccess = false,
		pgcacheSettings = {
			enaled: null,
			engine: null
		},
		dbcacheSettings = {
			enaled: null,
			engine: null
		},
		slideId = $slide.prop( 'id' ),
		$container = jQuery( '#w3tc-wizard-container' ),
		nonce = $container.find( '[name="_wpnonce"]' ).val(),
		$nextButton = $container.find( '#w3tc-wizard-next' ),
		$prevButton = $container.find( '#w3tc-wizard-previous' ),
		$skipButton = $container.find( '#w3tc-wizard-skip' );

	/**
	 * Configure Page Cache.
	 *
	 * @since X.X.X
	 *
	 * @param bool   enable Enable Page Cache.
	 * @param string engine Page Cache storage engine.
	 * @return jqXHR
	 */
	function configPgcache( enable, engine = '' ) {
		var $jqXHR = jQuery.ajax({
			method: 'POST',
			url: ajaxurl,
			data: {
				_wpnonce: nonce,
				action: 'w3tc_config_pgcache',
				enable: enable,
				engine: engine
			}
		});

		configSuccess = null;

		$jqXHR.done(function( response ) {
			configSuccess = response.data.success;
		});

		return $jqXHR;
	}

	/**
	 * Get Page Cache settings.
	 *
	 * @since X.X.X
	 *
	 * @return jqXHR
	 */
	function getPgcacheSettings() {
		return jQuery.ajax({
			method: 'POST',
			url: ajaxurl,
			data: {
				_wpnonce: nonce,
				action: 'w3tc_get_pgcache_settings'
			}
		})
		.done(function( response ) {
			pgcacheSettings = response.data;
		});
	}

	/**
	 * Configure Database Cache.
	 *
	 * @since X.X.X
	 *
	 * @param bool   enable Enable database cache.
	 * @param string engine Database cache storage engine.
	 * @return jqXHR
	 */
	function configDbcache( enable, engine = '' ) {
		var $jqXHR = jQuery.ajax({
			method: 'POST',
			url: ajaxurl,
			data: {
				_wpnonce: nonce,
				action: 'w3tc_config_dbcache',
				enable: enable,
				engine: engine
			}
		});

		configSuccess = null;

		$jqXHR.done(function( response ) {
			configSuccess = response.data.success;
		});

		return $jqXHR;
	}

	/**
	 * Get Database Cache settings.
	 *
	 * @since X.X.X
	 *
	 * @return jqXHR
	 */
	function getDbcacheSettings() {
		return jQuery.ajax({
			method: 'POST',
			url: ajaxurl,
			data: {
				_wpnonce: nonce,
				action: 'w3tc_get_dbcache_settings'
			}
		})
		.done(function( response ) {
			dbcacheSettings = response.data;
		});
	}

	/**
	 * Configuration failed.
	 *
	 * @since X.X.X
	 */
	function configFailed() {
		$slide.append(
			'<p class="notice notice-error"><strong>' +
			W3TC_SetupGuide.config_error_msg +
			'</strong></p>'
		);
		$nextButton.closest( 'span' ).hide();
		$prevButton.closest( 'span' ).hide();
		$skipButton.closest( 'span' ).show();
	}

	/**
	 * Test failed.
	 *
	 * @since X.X.X
	 */
	function testFailed() {
		$slide.append(
			'<p class="notice notice-error"><strong>' +
			W3TC_SetupGuide.test_error_msg +
			'</strong></p>'
		);
		$nextButton.closest( 'span' ).hide();
		$prevButton.closest( 'span' ).hide();
		$skipButton.closest( 'span' ).show();
	}

	switch ( slideId ) {
		case 'w3tc-wizard-slide-welcome':
			$container.find( '.w3tc-wizard-steps' ).removeClass( 'is-active' );

			break;

		case 'w3tc-wizard-slide-pc1':
			// Test Page Cache.
			$container.find( '.w3tc-wizard-steps' ).removeClass( 'is-active' );
			$container.find( '#w3tc-wizard-step-pgcache' ).addClass( 'is-active' );

			if ( ! $container.find( '#test-results' ).data( 'pgcache-none' ) ) {
				$nextButton.prop( 'disabled', 'disabled' );
			}

			$slide.find( '.w3tc-test-pgcache' ).unbind().on('click', function () {
				var $spinnerParent = $slide.find( '.spinner' ).addClass( 'is-active' ).parent(),
					$this = jQuery( this );

				$this.prop( 'disabled', 'disabled' );
				$slide.find( '.notice' ).remove();
				$container.find( '#w3tc-pgcache-table tbody' ).empty();

				$spinnerParent.show();

				/**
				 * Add a test result table row.
				 *
				 * @since X.X.X
				 *
				 * @param object testResponse Data.
				 * @param string engine       Cache storage engine.
				 * @param string label        Text label for the engine.
				 */
				function addResultRow( testResponse, engine, label ) {
					var baseline,
						diffPercent,
						results = '<tr';

					if ( ! configSuccess ) {
						results += ' class="w3tc-option-disabled"';
					}

					results += '><td><input type="radio" name="pgcache_engine" value="' +
						engine +
						'"';

					if ( ! configSuccess ) {
						results += ' disabled="disabled"';
					}

					if ( ( ! pgcacheSettings.enabled && 'none' === engine ) || pgcacheSettings.engine === engine ) {
						results += ' checked';
					}

					results += '></td><td>' +
						label +
						'</td><td>';

					if ( testResponse.success ) {
						results += ( testResponse.data.ttfb * 1000 ).toFixed( 2 );
						if ( 'none' !== engine ) {
							baseline = $container.find( '#test-results' ).data( 'pgcache-none' ).ttfb;
								results += ' ('+
								( ( testResponse.data.ttfb - baseline ) / baseline * 100 ).toFixed( 2 ) +
								'%)';
						}
					} else {
						results += W3TC_SetupGuide.unavailable_text;
					}

					results += '</td></tr>';

					$container.find( '#w3tc-pgcache-table tbody' ).append( results );
					$container.find( '#w3tc-pgcache-table' ).show();
				}

				/**
				 * Test Page Cache.
				 *
				 * @since X.X.X
				 *
				 * @param string engine Cache storage engine.
				 * @param string label  Text label for the engine.
				 * @return jqXHR
				 */
				function testPgcache( engine, label ) {
					if ( configSuccess ) {
						return jQuery.ajax({
							method: 'POST',
							url: ajaxurl,
							data: {
								_wpnonce: nonce,
								action: 'w3tc_test_pgcache'
							}
						})
						.done(function( testResponse ) {
							$container.find( '#test-results' ).data( 'pgcache-' + engine, testResponse.data );
							addResultRow( testResponse, engine, label );
						});
					} else {
						addResultRow( [ success => false ], engine, label );
					}
				}

				// Run config and tests.
				getPgcacheSettings()
					.then( function() {
						return configPgcache( 0 );
					}, configFailed )
					.then( function() {
						return testPgcache( 'none', W3TC_SetupGuide.none );
					}, configFailed )
					.then( function() {
						return configPgcache( 1, 'file' );
					} , testFailed )
					.then( function() {
						return testPgcache( 'file', W3TC_SetupGuide.disk_basic );
					}, configFailed )
					.then( function() {
						return configPgcache( 1, 'file_generic' );
					} , testFailed )
					.then( function() {
						return testPgcache( 'file_generic', W3TC_SetupGuide.disk_enhanced );
					}, configFailed )
					.then( function() {
						return configPgcache( 1, 'redis' );
					}, testFailed )
					.then( function() {
						return testPgcache( 'redis', 'Redis' );
					}, configFailed )
					.then( function() {
						return configPgcache( 1, 'memcached' );
					}, testFailed )
					.then( function() {
						return testPgcache( 'memcached', 'Memcached' );
					}, configFailed )
					.then( function() {
						return configPgcache( 1, 'apc' );
					}, testFailed )
					.then( function() {
						return testPgcache( 'apc', 'APC' );
					}, configFailed )
					.then( function() {
						return configPgcache( 1, 'eaccelerator' );
					}, testFailed )
					.then( function() {
						return testPgcache( 'eaccelerator', 'eAccelerator' );
					}, configFailed )
					.then( function() {
						return configPgcache( 1, 'xcache' );
					}, testFailed )
					.then( function() {
						return testPgcache( 'xcache', 'XCache' );
					}, configFailed )
					.then( function() {
						return configPgcache( 1, 'wincache' );
					}, testFailed )
					.then( function() {
						return testPgcache( 'wincache', 'WinCache' );
					}, configFailed )
					.then(function() {
						$spinnerParent.hide();
						$this.removeProp( 'disabled' );
						$prevButton.removeProp( 'disabled' );
						$nextButton.removeProp( 'disabled' );
						return true;
					}, testFailed )
					// Restore the original database cache settings.
					.then( function() {
						return configPgcache( ( pgcacheSettings.enabled ? 1 : 0 ), pgcacheSettings.engine );
					})
					.fail( configFailed );
			});

			break;

		case 'w3tc-wizard-slide-dbc1':
			// Test database cache.
			$container.find( '.w3tc-wizard-steps' ).removeClass( 'is-active' );
			$container.find( '#w3tc-wizard-step-dbcache' ).addClass( 'is-active' );

			if ( ! $container.find( '#test-results' ).data( 'dbc-none' ) ) {
				$nextButton.prop( 'disabled', 'disabled' );
			}

			$slide.find( '.w3tc-test-dbcache' ).unbind().on('click', function () {
				var $spinnerParent = $slide.find( '.spinner' ).addClass( 'is-active' ).parent(),
					$this = jQuery( this );

				$this.prop( 'disabled', 'disabled' );
				$slide.find( '.notice' ).remove();
				$container.find( '#w3tc-dbc-table tbody' ).empty();

				$spinnerParent.show();

				/**
				 * Add a test result table row.
				 *
				 * @since X.X.X
				 *
				 * @param object testResponse Data.
				 * @param string engine       Cache storage engine.
				 * @param string label        Text label for the engine.
				 */
				function addResultRow( testResponse, engine, label ) {
					var baseline,
						diffPercent,
						results = '<tr';

					if ( ! configSuccess ) {
						results += ' class="w3tc-option-disabled"';
					}

					results += '><td><input type="radio" name="dbc_engine" value="' +
						engine +
						'"';

					if ( ! configSuccess ) {
						results += ' disabled="disabled"';
					}

					if ( ( ! dbcacheSettings.enabled && 'none' === engine ) || dbcacheSettings.engine === engine ) {
						results += ' checked';
					}

					results += '></td><td>' +
						label +
						'</td><td>';

					if ( testResponse.success ) {
						results += ( testResponse.data.elapsed * 1000 ).toFixed( 2 );
						if ( 'none' !== engine ) {
							baseline = $container.find( '#test-results' ).data( 'dbc-none' ).elapsed;
								results += ' ('+
								( ( testResponse.data.elapsed - baseline ) / baseline * 100 ).toFixed( 2 ) +
								'%)';
						}
					} else {
						results += W3TC_SetupGuide.unavailable_text;
					}

					results += '</td></tr>';

					$container.find( '#w3tc-dbc-table tbody' ).append( results );
					$container.find( '#w3tc-dbc-table' ).show();
				}

				/**
				 * Test database cache.
				 *
				 * @since X.X.X
				 *
				 * @param string engine Cache storage engine.
				 * @param string label  Text label for the engine.
				 * @return jqXHR
				 */
				function testDbcache( engine, label ) {
					if ( configSuccess ) {
						return jQuery.ajax({
							method: 'POST',
							url: ajaxurl,
							data: {
								_wpnonce: nonce,
								action: 'w3tc_test_dbcache'
							}
						})
						.done(function( testResponse ) {
							$container.find( '#test-results' ).data( 'dbc-' + engine, testResponse.data );
							addResultRow( testResponse, engine, label );
						});
					} else {
						addResultRow( [ success => false ], engine, label );
					}
				}

				// Run config and tests.
				getDbcacheSettings()
					.then( function() {
						return configDbcache( 0 );
					}, configFailed )
					.then( function() {
						return testDbcache( 'none', W3TC_SetupGuide.none );
					}, configFailed )
					.then( function() {
						return configDbcache( 1, 'file' );
					} , testFailed )
					.then( function() {
						return testDbcache( 'file', W3TC_SetupGuide.disk );
					}, configFailed )
					.then( function() {
						return configDbcache( 1, 'redis' );
					}, testFailed )
					.then( function() {
						return testDbcache( 'redis', 'Redis' );
					}, configFailed )
					.then( function() {
						return configDbcache( 1, 'memcached' );
					}, testFailed )
					.then( function() {
						return testDbcache( 'memcached', 'Memcached' );
					}, configFailed )
					.then( function() {
						return configDbcache( 1, 'apc' );
					}, testFailed )
					.then( function() {
						return testDbcache( 'apc', 'APC' );
					}, configFailed )
					.then( function() {
						return configDbcache( 1, 'eaccelerator' );
					}, testFailed )
					.then( function() {
						return testDbcache( 'eaccelerator', 'eAccelerator' );
					}, configFailed )
					.then( function() {
						return configDbcache( 1, 'xcache' );
					}, testFailed )
					.then( function() {
						return testDbcache( 'xcache', 'XCache' );
					}, configFailed )
					.then( function() {
						return configDbcache( 1, 'wincache' );
					}, testFailed )
					.then( function() {
						return testDbcache( 'wincache', 'WinCache' );
					}, configFailed )
					.then(function() {
						$spinnerParent.hide();
						$this.removeProp( 'disabled' );
						$prevButton.removeProp( 'disabled' );
						$nextButton.removeProp( 'disabled' );
						return true;
					}, testFailed )
					// Restore the original database cache settings.
					.then( function() {
						return configDbcache( ( dbcacheSettings.enabled ? 1 : 0 ), dbcacheSettings.engine );
					})
					.fail( configFailed );
			});

			break;

		case 'w3tc-wizard-slide-oc1':
			// Save the database cache engine setting from the previous slide.
			var dbcEngine = $container.find( 'input:checked[name="dbc_engine"]' ).val();

			configDbcache( ( 'none' === dbcEngine ? 0 : 1 ), 'none' === dbcEngine ? '' : dbcEngine )
				.fail( function() {
					$slide.append(
						'<p class="notice notice-error"><strong>' +
						W3TC_SetupGuide.config_error_msg +
						'</strong></p>'
					);
				});

			// Present the Object Cache slide.
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
					_wpnonce: nonce,
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
						_wpnonce: nonce,
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
							_wpnonce: nonce,
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
						_wpnonce: nonce,
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
