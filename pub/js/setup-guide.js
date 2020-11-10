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
		objcacheSettings = {
			enaled: null,
			engine: null
		},
		browsercacheSettings = {
			enaled: null
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
	 * @param int    enable Enable Page Cache.
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
	 * @param int    enable Enable database cache.
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
	 * Configure Object Cache.
	 *
	 * @since X.X.X
	 *
	 * @param int    enable Enable cache.
	 * @param string engine Cache storage engine.
	 * @return jqXHR
	 */
	function configObjcache( enable, engine = '' ) {
		var $jqXHR = jQuery.ajax({
			method: 'POST',
			url: ajaxurl,
			data: {
				_wpnonce: nonce,
				action: 'w3tc_config_objcache',
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
	 * Get Object Cache settings.
	 *
	 * @since X.X.X
	 *
	 * @return jqXHR
	 */
	function getObjcacheSettings() {
		return jQuery.ajax({
			method: 'POST',
			url: ajaxurl,
			data: {
				_wpnonce: nonce,
				action: 'w3tc_get_objcache_settings'
			}
		})
		.done(function( response ) {
			objcacheSettings = response.data;
		});
	}

	/**
	 * Configure Browser Cache.
	 *
	 * @since X.X.X
	 *
	 * @param int enable Enable browser cache.
	 * @return jqXHR
	 */
	function configBrowsercache( enable ) {
		configSuccess = null;

		return jQuery.ajax({
			method: 'POST',
			url: ajaxurl,
			data: {
				_wpnonce: nonce,
				action: 'w3tc_config_browsercache',
				enable: enable
			}
		})
		.done(function( response ) {
			configSuccess = response.data.success;
		});
	}

	/**
	 * Get Browser Cache settings.
	 *
	 * @since X.X.X
	 *
	 * @return jqXHR
	 */
	function getBrowsercacheSettings() {
		return jQuery.ajax({
			method: 'POST',
			url: ajaxurl,
			data: {
				_wpnonce: nonce,
				action: 'w3tc_get_browsercache_settings'
			}
		})
		.done(function( response ) {
			browsercacheSettings = response.data;
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

			$slide.find( '#w3tc-test-pgcache' ).unbind().on('click', function () {
				var $spinnerParent = $slide.find( '.spinner' ).addClass( 'is-active' ).parent(),
					$this = jQuery( this );

				$this.prop( 'disabled', 'disabled' );
				$slide.find( '.notice' ).remove();
				$container.find( '#w3tc-pgcache-table tbody' ).empty();
				$prevButton.prop( 'disabled', 'disabled' );
				$nextButton.prop( 'disabled', 'disabled' );

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

					if ( ( ! pgcacheSettings.enabled && 'none' === engine ) ||
						( pgcacheSettings.enabled && pgcacheSettings.engine === engine ) ) {
							results += ' checked';
					}

					results += '></td><td>' +
						label +
						'</td><td>';

					if ( testResponse.success ) {
						results += ( testResponse.data.ttfb * 1000 ).toFixed( 2 );
						if ( 'none' !== engine ) {
							baseline = $container.find( '#test-results' ).data( 'pgcache-none' ).ttfb;
							diffPercent = ( ( testResponse.data.ttfb - baseline ) / baseline * 100 ).toFixed( 2 );
							$container.find( '#test-results' ).data( 'pgcacheDiffPercent-' + engine, diffPercent );
							results += ' ('+
								diffPercent +
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
					},
					function() {
						$spinnerParent.hide();
						return configFailed();
					});
			});

			break;

		case 'w3tc-wizard-slide-dbc1':
			// Save the page cache engine setting from the previous slide.
			var pgcacheEngine = $container.find( 'input:checked[name="pgcache_engine"]' ).val();

			configPgcache( ( 'none' === pgcacheEngine ? 0 : 1 ), 'none' === pgcacheEngine ? '' : pgcacheEngine )
				.fail( function() {
					$slide.append(
						'<p class="notice notice-error"><strong>' +
						W3TC_SetupGuide.config_error_msg +
						'</strong></p>'
					);
				});

			// Present the Database Cache slide.
			$container.find( '.w3tc-wizard-steps' ).removeClass( 'is-active' );
			$container.find( '#w3tc-wizard-step-dbcache' ).addClass( 'is-active' );

			if ( ! $container.find( '#test-results' ).data( 'dbc-none' ) ) {
				$nextButton.prop( 'disabled', 'disabled' );
			}

			$slide.find( '#w3tc-test-dbcache' ).unbind().on('click', function () {
				var $spinnerParent = $slide.find( '.spinner' ).addClass( 'is-active' ).parent(),
					$this = jQuery( this );

				$this.prop( 'disabled', 'disabled' );
				$slide.find( '.notice' ).remove();
				$container.find( '#w3tc-dbc-table tbody' ).empty();
				$prevButton.prop( 'disabled', 'disabled' );
				$nextButton.prop( 'disabled', 'disabled' );

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

					if ( ( ! dbcacheSettings.enabled && 'none' === engine ) ||
						( dbcacheSettings.enabled && dbcacheSettings.engine === engine ) ) {
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
					},
					function() {
						$spinnerParent.hide();
						return configFailed();
					});
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

			if ( ! $container.find( '#test-results' ).data( 'oc-none' ) ) {
				$nextButton.prop( 'disabled', 'disabled' );
			}

			$slide.find( '#w3tc-test-objcache' ).unbind().on('click', function () {
				var $spinnerParent = $slide.find( '.spinner' ).addClass( 'is-active' ).parent(),
					$this = jQuery( this );

				$this.prop( 'disabled', 'disabled' );
				$slide.find( '.notice' ).remove();
				$container.find( '#w3tc-objcache-table tbody' ).empty();
				$prevButton.prop( 'disabled', 'disabled' );
				$nextButton.prop( 'disabled', 'disabled' );

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
						results = '<tr';

					if ( ! configSuccess ) {
						results += ' class="w3tc-option-disabled"';
					}

					results += '><td><input type="radio" name="objcache_engine" value="' +
						engine +
						'"';

					if ( ! configSuccess ) {
						results += ' disabled="disabled"';
					}

					if ( ( ! objcacheSettings.enabled && 'none' === engine ) ||
						( objcacheSettings.enabled && objcacheSettings.engine === engine ) ) {
							results += ' checked';
					}

					results += '></td><td>' +
						label +
						'</td><td>';

					if ( testResponse.success ) {
						results += ( testResponse.data.elapsed * 1000 ).toFixed( 2 );
						if ( 'none' !== engine ) {
							baseline = $container.find( '#test-results' ).data( 'oc-none' ).elapsed;
								results += ' ('+
								( ( testResponse.data.elapsed - baseline ) / baseline * 100 ).toFixed( 2 ) +
								'%)';
						}
					} else {
						results += W3TC_SetupGuide.unavailable_text;
					}

					results += '</td></tr>';

					$container.find( '#w3tc-objcache-table tbody' ).append( results );
					$container.find( '#w3tc-objcache-table' ).show();
				}

				/**
				 * Test object cache cache.
				 *
				 * @since X.X.X
				 *
				 * @param string engine Cache storage engine.
				 * @param string label  Text label for the engine.
				 * @return jqXHR
				 */
				function testObjcache( engine, label ) {
					if ( configSuccess ) {
						return jQuery.ajax({
							method: 'POST',
							url: ajaxurl,
							data: {
								_wpnonce: nonce,
								action: 'w3tc_test_objcache'
							}
						})
						.done(function( testResponse ) {
							$container.find( '#test-results' ).data( 'oc-' + engine, testResponse.data );
							addResultRow( testResponse, engine, label );
						});
					} else {
						addResultRow( [ success => false ], engine, label );
					}
				}

				// Run config and tests.
				getObjcacheSettings()
					.then( function() {
						return configObjcache( 0 );
					}, configFailed )
					.then( function() {
						return testObjcache( 'none', W3TC_SetupGuide.none );
					}, configFailed )
					.then( function() {
						return configObjcache( 1, 'file' );
					} , testFailed )
					.then( function() {
						return testObjcache( 'file', W3TC_SetupGuide.disk );
					}, configFailed )
					.then( function() {
						return configObjcache( 1, 'redis' );
					}, testFailed )
					.then( function() {
						return testObjcache( 'redis', 'Redis' );
					}, configFailed )
					.then( function() {
						return configObjcache( 1, 'memcached' );
					}, testFailed )
					.then( function() {
						return testObjcache( 'memcached', 'Memcached' );
					}, configFailed )
					.then( function() {
						return configObjcache( 1, 'apc' );
					}, testFailed )
					.then( function() {
						return testObjcache( 'apc', 'APC' );
					}, configFailed )
					.then( function() {
						return configObjcache( 1, 'eaccelerator' );
					}, testFailed )
					.then( function() {
						return testObjcache( 'eaccelerator', 'eAccelerator' );
					}, configFailed )
					.then( function() {
						return configObjcache( 1, 'xcache' );
					}, testFailed )
					.then( function() {
						return testObjcache( 'xcache', 'XCache' );
					}, configFailed )
					.then( function() {
						return configObjcache( 1, 'wincache' );
					}, testFailed )
					.then( function() {
						return testObjcache( 'wincache', 'WinCache' );
					}, configFailed )
					.then(function() {
						$spinnerParent.hide();
						$this.removeProp( 'disabled' );
						$prevButton.removeProp( 'disabled' );
						$nextButton.removeProp( 'disabled' );
						return true;
					}, testFailed )
					// Restore the original object cache settings.
					.then( function() {
						return configObjcache( ( objcacheSettings.enabled ? 1 : 0 ), objcacheSettings.engine );
					},
					function() {
						$spinnerParent.hide();
						return configFailed();
					});
			});

			break;

		case 'w3tc-wizard-slide-bc1':
			// Save the object cache engine setting from the previous slide.
			var objcacheEngine = $container.find( 'input:checked[name="objcache_engine"]' ).val();

			configObjcache( ( 'none' === objcacheEngine ? 0 : 1 ), 'none' === objcacheEngine ? '' : objcacheEngine )
				.fail( function() {
					$slide.append(
						'<p class="notice notice-error"><strong>' +
						W3TC_SetupGuide.config_error_msg +
						'</strong></p>'
					);
				});

			// Present the Browser Cache slide.
			$container.find( '.w3tc-wizard-steps' ).removeClass( 'is-active' );
			$container.find( '#w3tc-wizard-step-browsercache' ).addClass( 'is-active' );

			if ( ! $container.find( '#test-results' ).data( 'bc-off' ) ) {
				$nextButton.prop( 'disabled', 'disabled' );
			}

			$slide.find( '#w3tc-test-browsercache' ).unbind().on('click', function () {
				var bcEnabled,
					$spinnerParent = $slide.find( '.spinner' ).addClass( 'is-active' ).parent(),
					$this = jQuery( this );

				$this.prop( 'disabled', 'disabled' );
				$slide.find( '.notice' ).remove();
				$container.find( '#w3tc-browsercache-table tbody' ).empty();
				$prevButton.prop( 'disabled', 'disabled' );
				$nextButton.prop( 'disabled', 'disabled' );

				$spinnerParent.show();

				/**
				 * Add a Browser Cache test result table row.
				 *
				 * @since X.X.X
				 *
				 * @param object testResponse An object (success, data) containing a data array of objects
				 * 	                          (url, filename, header, headers).
				 */
				function addResultRow( testResponse ) {
					var label = bcEnabled ? W3TC_SetupGuide.enabled : W3TC_SetupGuide.none,
						results = '<tr';

					if ( ! configSuccess ) {
						results += ' class="w3tc-option-disabled"';
					}

					results += '><td><input type="radio" name="browsercache_enable" value="' +
						bcEnabled +
						'"';

					if ( ! configSuccess ) {
						results += ' disabled="disabled"';
					}

					if ( ! bcEnabled ) {
						results += ' checked';
					}

					results += '></td><td>' +
						label +
						'</td>';

					if ( testResponse.success ) {
						results += '<td>';

						testResponse.data.forEach( function( item, index ) {
							results += '<a href="' +
							item.url +
							'">' +
							item.filename +
							'</a></td><td>' +
							item.header +
							'</td></tr>';

							// If not the last entry, then start the next row.
							if ( index !== ( testResponse.data.length - 1 ) ) {
								results += '<tr><td colspan="2"></td><td>';
							}
						} );
					} else {
						results = '<td colspan="2">' +
							W3TC_SetupGuide.test_error_msg +
							'</td></tr>';
					}

					$container.find( '#w3tc-browsercache-table > tbody' ).append( results );
					$container.find( '#w3tc-browsercache-table' ).show();
				}

				/**
				 * Test browser cache.
				 *
				 * @since X.X.X
				 *
				 * @return jqXHR
				 */
				function testBrowsercache() {
					if ( configSuccess ) {
						return jQuery.ajax({
							method: 'POST',
							url: ajaxurl,
							data: {
								_wpnonce: nonce,
								action: 'w3tc_test_browsercache'
							}
						})
						.done(function( testResponse ) {
							var enabled = bcEnabled ? 'on' : 'off';

							$container.find( '#test-results' ).data( 'bc-' + enabled, testResponse.data );
							addResultRow( testResponse );
						});
					} else {
						addResultRow( [ success => false ] );
					}
				}

				// Run config and tests.
				getBrowsercacheSettings()
					.then( function() {
						bcEnabled = 0;
						return configBrowsercache( bcEnabled );
					}, configFailed )
					.then( testBrowsercache, configFailed )
					.then( function() {
						bcEnabled = 1;
						return configBrowsercache( bcEnabled );
					} , testFailed )
					.then( testBrowsercache, configFailed )
					.then(function() {
						$spinnerParent.hide();
						$this.removeProp( 'disabled' );
						$prevButton.removeProp( 'disabled' );
						$nextButton.removeProp( 'disabled' );
						return true;
					}, testFailed )
					// Restore the original browser cache settings.
					.then( function() {
						return configBrowsercache( ( browsercacheSettings.enabled ? 1 : 0 ) );
					},
					function() {
						$spinnerParent.hide();
						return configFailed();
					});
			});

			break;

		case 'w3tc-wizard-slide-ll1':
			// Save the browser cache engine setting from the previous slide.
			var browsercacheEnabled = $container.find( 'input:checked[name="browsercache_enable"]' ).val();

			configBrowsercache( ( '1' === browsercacheEnabled ? 1 : 0 ) )
				.fail( function() {
					$slide.append(
						'<p class="notice notice-error"><strong>' +
						W3TC_SetupGuide.config_error_msg +
						'</strong></p>'
					);
				});

			// Present the Lazy Load slide.
			$container.find( '.w3tc-wizard-steps' ).removeClass( 'is-active' );
			$container.find( '#w3tc-wizard-step-lazyload' ).addClass( 'is-active' );

			break;

		case 'w3tc-wizard-slide-complete':
			var html,
				pgcacheEngine = $container.find( 'input:checked[name="pgcache_engine"]' ).val();
				pgcacheDiffPercent = $container.find( '#test-results' ).data( 'pgcacheDiffPercent-' + pgcacheEngine );

			$container.find( '.w3tc-wizard-steps' ).removeClass( 'is-active' );
			$container.find( '#w3tc-wizard-step-more' ).addClass( 'is-active' );

			html = ( pgcacheDiffPercent > 0 ? '+' : '' ) +
				pgcacheDiffPercent +
				'%';

			$container.find( '#w3tc-ttfb-diff' ).html( html );

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
