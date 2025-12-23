/**
 * File: setup-guide.js
 *
 * JavaScript for the Setup Guide page.
 *
 * @since 2.0.0
 *
 * @global W3TC-setup-guide Localized array variable.
 */

var w3tc_enable_ga;
var w3tcTestHistory = {
	pgcache: {},
	dbcache: {},
	objcache: {}
};
var w3tcWizardSlideStepMap = {
	'w3tc-wizard-slide-pc1': 'pgcache',
	'w3tc-wizard-slide-dbc1': 'dbcache',
	'w3tc-wizard-slide-oc1': 'objectcache',
	'w3tc-wizard-slide-io1': 'imageservice',
	'w3tc-wizard-slide-ll1': 'lazyload',
	'w3tc-wizard-slide-complete': 'more'
};
var w3tcWizardSlidesWithTests = [
	'w3tc-wizard-slide-pc1',
	'w3tc-wizard-slide-dbc1',
	'w3tc-wizard-slide-oc1'
];

jQuery(function() {
	var $container = jQuery( '#w3tc-wizard-container'),
		$nextButton = $container.find( '#w3tc-wizard-next '),
		$tosNotice = $container.find( '#w3tc-licensing-terms' );

	w3tc_enable_ga = !!( 'accept' === W3TC_SetupGuide.tos_choice && W3TC_SetupGuide.track_usage && window.w3tc_ga );

	// GA.
	if ( w3tc_enable_ga ) {
		w3tc_ga(
			'event',
			'button',
			{
				eventCategory: 'w3tc_setup_guide',
				eventLabel: 'w3tc-wizard-step-welcome'
			}
		);
	}

	// Handle the terms of service notice.
	if ( $tosNotice.length ) {
		$nextButton.prop( 'disabled', true );
		$container.find( '.dashicons-yes' ).hide();

		$tosNotice.find( '.button' ).on( 'click', function() {
			var $this = jQuery( this ),
				choice = $this.data( 'choice' );

			jQuery.ajax({
				method: 'POST',
				url: ajaxurl,
				data: {
					_wpnonce: $container.find( '[name="_wpnonce"]' ).val(),
					action: "w3tc_tos_choice",
					choice: choice
				}
			})
				.done(function( response ) {
					$tosNotice.hide();
					$nextButton.prop( 'disabled', false );
					$container.find( '#w3tc-welcome' ).show();
					$container.find( '.dashicons-yes' ).show();
				})
				.fail(function() {
					$this.text( 'Error with Ajax; reloading page...' );

					location.reload();
				});

			if ( 'accept' === choice ) {
				W3TC_SetupGuide.tos_choice = choice;

				var gaScript = document.createElement( 'script' );
				gaScript.type = 'text/javascript';
				gaScript.setAttribute( 'async', 'true' );
				gaScript.setAttribute( 'src', 'https://www.googletagmanager.com/gtag/js?id=' + W3TC_SetupGuide.ga_profile );
				document.documentElement.firstChild.appendChild( gaScript );

				window.dataLayer = window.dataLayer || [];

				window.w3tc_ga = function() { dataLayer.push( arguments ); }

				w3tc_enable_ga = true;

				w3tc_ga( 'js', new Date() );

				w3tc_ga( 'config', W3TC_SetupGuide.ga_profile, {
					'user_properties': {
						'plugin': 'w3-total-cache',
						'w3tc_version': W3TC_SetupGuide.w3tc_version,
						'wp_version': W3TC_SetupGuide.wp_version,
						'php_version': W3TC_SetupGuide.php_version,
						'server_software': W3TC_SetupGuide.server_software,
						'wpdb_version': W3TC_SetupGuide.db_version,
						'home_url': W3TC_SetupGuide.home_url_host,
						'w3tc_install_version': W3TC_SetupGuide.install_version,
						'w3tc_edition': W3TC_SetupGuide.w3tc_edition,
						'w3tc_widgets': W3TC_SetupGuide.list_widgets,
						'page': W3TC_SetupGuide.page,
						'w3tc_install_date': W3TC_SetupGuide.w3tc_install_date,
						'w3tc_pro': W3TC_SetupGuide.w3tc_pro,
						'w3tc_has_key': W3TC_SetupGuide.w3tc_has_key,
						'w3tc_pro_c': W3TC_SetupGuide.w3tc_pro_c,
						'w3tc_enterprise_c': W3TC_SetupGuide.w3tc_enterprise_c,
						'w3tc_plugin_type': W3TC_SetupGuide.plugin_type,
					}
				});

				w3tc_ga(
					'event',
					'button',
					{
						eventCategory: 'w3tc_setup_guide',
						eventLabel: 'w3tc-wizard-step-welcome'
					}
				);
			}
		});
	}
});

jQuery( '#w3tc-wizard-step-welcome' )
	.addClass( 'is-active' );

/**
 * Record a test result for later averaging.
 *
 * @since 2.8.8
 *
 * @param string type   Cache type key.
 * @param string engine Engine slug.
 * @param number value  Result value in seconds.
 */
function w3tcRecordTestResult( type, engine, value ) {
	if ( ! w3tcTestHistory[ type ] ) {
		w3tcTestHistory[ type ] = {};
	}

	if ( ! w3tcTestHistory[ type ][ engine ] ) {
		w3tcTestHistory[ type ][ engine ] = [];
	}

	w3tcTestHistory[ type ][ engine ].push( value );
}

/**
 * Get an average result (seconds) for a cache engine.
 *
 * @since 2.8.8
 *
 * @param string type   Cache type key.
 * @param string engine Engine slug.
 * @return float|null
 */
function w3tcGetAverageResult( type, engine ) {
	var history = w3tcTestHistory[ type ] && w3tcTestHistory[ type ][ engine ] ? w3tcTestHistory[ type ][ engine ] : null;

	if ( ! history || ! history.length ) {
		return null;
	}

	return history.reduce( function( total, value ) {
		return total + value;
	}, 0 ) / history.length;
}

/**
 * Calculate the percent change using averaged results.
 *
 * @since 2.8.8
 *
 * @param string type   Cache type key.
 * @param string engine Cache engine slug.
 * @return string|null
 */
function w3tcGetAveragePercentChange( type, engine ) {
	var average = w3tcGetAverageResult( type, engine ),
		baseline = w3tcGetAverageResult( type, 'none' );

	if ( null === average || null === baseline || 0 === baseline || 'none' === engine ) {
		return null;
	}

	return ( ( average - baseline ) / baseline * 100 ).toFixed( 2 );
}

/**
 * Format a seconds value as milliseconds.
 *
 * @since 2.8.8
 *
 * @param float|null seconds Value in seconds.
 * @return string|null
 */
function w3tcFormatMs( seconds ) {
	if ( null === seconds ) {
		return null;
	}

	return ( seconds * 1000 ).toFixed( 2 );
}

/**
 * Toggle the completion checkmark for a step.
 *
 * @since 2.0.0
 *
 * @param string stepId   The step slug (matches the ID suffix).
 * @param bool   complete Whether the step should show as complete.
 */
function toggleStepComplete( stepId, complete ) {
	var excludedSteps = [ 'welcome', 'more' ],
		$step = jQuery( '#w3tc-wizard-step-' + stepId );

	if ( -1 !== excludedSteps.indexOf( stepId ) ) {
		return;
	}

	if ( complete && ! $step.find( '.dashicons-yes' ).length ) {
		$step.append( '<span class="dashicons dashicons-yes"></span>' );
	}
}

/**
 * Flag a slide as having completed its tests.
 *
 * @since 2.10.0
 *
 * @param string slideId The current slide ID.
 */
function w3tc_mark_slide_tests_complete( slideId ) {
	jQuery( '#' + slideId ).data( 'testsComplete', true );
}

/**
 * Mark a step complete when navigating away from a slide.
 *
 * @since 2.10.0
 *
 * @param string slideId The slide being left.
 */
function w3tc_mark_step_complete_on_leave( slideId ) {
	var stepId = w3tcWizardSlideStepMap[ slideId ],
		requiresTests = -1 !== w3tcWizardSlidesWithTests.indexOf( slideId );

	if ( ! stepId ) {
		return;
	}

	if ( requiresTests && ! jQuery( '#' + slideId ).data( 'testsComplete' ) ) {
		return;
	}

	toggleStepComplete( stepId, true );
}

/**
 * Display a disk warning.
 *
 * Used for dbcache and objectcache.
 *
 * @since 2.8.1
 *
 * @param bool show Print the warning or not.
 */
function w3tc_disk_warning( show = true ) {
	if (show) {
		jQuery( '#w3tc-wizard-container .w3tc-wizard-slides:visible' ).append(
			'<div class="notice notice-warning w3tc-disk-warning"><p><strong>' +
			W3TC_SetupGuide.warning_disk +
			'</strong></p></div>'
		);
	} else {
		jQuery( '#w3tc-wizard-container .w3tc-wizard-slides:visible .w3tc-disk-warning').remove();
	}

}

 /**
  * Wizard actions.
  *
  * @since 2.0.0
  *
  * @param object $slide          The div of the slide displayed.
  * @param object $previousSlide  The slide being left before showing the new one.
  */
function w3tc_wizard_actions( $slide, $previousSlide ) {
	var configSuccess = false,
		pgcacheSettings = {
			enabled: null,
			engine: null
		},
		dbcacheSettings = {
			enabled: null,
			engine: null
		},
		objcacheSettings = {
			enabled: null,
			engine: null
		},
		imageserviceSettings = {
			enabled: null,
			settings: {
				webp: true,
				avif: true,
				compression: 'lossy',
				auto: 'enabled',
				visibility: 'never'
			}
		},
		lazyloadSettings = {
			enabled: null
		},
		slideId = $slide.prop( 'id' ),
		previousSlideId = $previousSlide && $previousSlide.length ? $previousSlide.prop( 'id' ) : null,
		$container = jQuery( '#w3tc-wizard-container' ),
		nonce = $container.find( '[name="_wpnonce"]' ).val(),
		$nextButton = $container.find( '#w3tc-wizard-next' ),
		$prevButton = $container.find( '#w3tc-wizard-previous' ),
		$skipButton = $container.find( '#w3tc-wizard-skip' ),
		$dashboardButton = $container.find( '#w3tc-wizard-dashboard' ),
		$objcacheEngine = $container.find( 'input[name="objcache_engine"]' );

	/**
	 * Configure Page Cache.
	 *
	 * @since 2.0.0
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
	 * @since 2.0.0
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
	 * @since 2.0.0
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
	 * @since 2.0.0
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
	 * @since 2.0.0
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
	 * @since 2.0.0
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
	 * Configure Image Service.
	 *
	 * @since 2.3.4
	 *
	 * @param int enable Enable image converter.
	 * @param object settings Settings payload.
	 * @return jqXHR
	 */
	function configImageservice( enable, settings = {} ) {
		configSuccess = null;

		return jQuery.ajax({
			method: 'POST',
			url: ajaxurl,
			data: {
				_wpnonce: nonce,
				action: 'w3tc_config_imageservice',
				enable: enable,
				settings: settings
			}
		})
		.done(function( response ) {
			configSuccess = response.data.success;
		});
	}

	/**
	 * Gather Image Service form settings with defaults.
	 *
	 * @since 2.10.0
	 *
	 * @return object
	 */
	function getImageserviceFormSettings() {
		return {
			compression: 'lossy',
			auto: 'enabled',
			visibility: 'never',
			webp: $container.find( '#imageservice-webp' ).is( ':checked' ) ? 1 : 0,
			avif: $container.find( '#imageservice-avif' ).is( ':checked' ) ? 1 : 0
		};
	}

	/**
	 * Get Image Service settings.
	 *
	 * @since 2.3.4
	 *
	 * @return jqXHR
	 */
	function getImageserviceSettings() {
		return jQuery.ajax({
			method: 'POST',
			url: ajaxurl,
			data: {
				_wpnonce: nonce,
				action: 'w3tc_get_imageservice_settings'
			}
		})
		.done(function( response ) {
			imageserviceSettings = response.data;
			imageserviceSettings.settings = jQuery.extend(
				{},
				{
					webp: true,
					avif: true,
					compression: 'lossy',
					auto: 'enabled',
					visibility: 'never'
				},
				imageserviceSettings.settings || {}
			);
		});
	}

	/**
	 * Configure Lazy Load.
	 *
	 * @since 2.0.0
	 *
	 * @param int enable Enable lazyload.
	 * @return jqXHR
	 */
	function configLazyload( enable ) {
		configSuccess = null;

		return jQuery.ajax({
			method: 'POST',
			url: ajaxurl,
			data: {
				_wpnonce: nonce,
				action: 'w3tc_config_lazyload',
				enable: enable
			}
		})
		.done(function( response ) {
			configSuccess = response.data.success;
		});
	}

	/**
	 * Get Lazt Load settings.
	 *
	 * @since 2.0.0
	 *
	 * @return jqXHR
	 */
	function getLazyloadSettings() {
		return jQuery.ajax({
			method: 'POST',
			url: ajaxurl,
			data: {
				_wpnonce: nonce,
				action: 'w3tc_get_lazyload_settings'
			}
		})
		.done(function( response ) {
			lazyloadSettings = response.data;
		});
	}

	/**
	 * Persist settings when leaving a slide.
	 *
	 * @since 2.10.0
	 */
	function savePreviousSlideSettings() {
		if ( ! previousSlideId ) {
			return;
		}

		switch ( previousSlideId ) {
			case 'w3tc-wizard-slide-pc1':
				var $pgcacheEngine = $container.find( 'input:checked[name="pgcache_engine"]' ),
					pgcacheEngine;

				if ( $pgcacheEngine.length ) {
					pgcacheEngine = $pgcacheEngine.val();
					configPgcache( ( 'none' === pgcacheEngine ? 0 : 1 ), 'none' === pgcacheEngine ? '' : pgcacheEngine )
						.fail( function() {
							$slide.append(
								'<div class="notice notice-error"><p><strong>' +
								W3TC_SetupGuide.config_error_msg +
								'</strong></p></div>'
							);
						});
				}

				break;

			case 'w3tc-wizard-slide-dbc1':
				var $dbcEngine = $container.find( 'input:checked[name="dbcache_engine"]' ),
					dbcEngine;

				if ( $dbcEngine.length ) {
					dbcEngine = $dbcEngine.val();
					configDbcache( ( 'none' === dbcEngine ? 0 : 1 ), 'none' === dbcEngine ? '' : dbcEngine )
						.fail( function() {
							$slide.append(
								'<div class="notice notice-error"><p><strong>' +
								W3TC_SetupGuide.config_error_msg +
								'</strong></p></div>'
							);
						});
				}

				break;

			case 'w3tc-wizard-slide-oc1':
				var $objcacheEngine = $container.find( 'input:checked[name="objcache_engine"]' ),
					objcacheEngine;

				if ( $objcacheEngine.length ) {
					objcacheEngine = $objcacheEngine.val();
					configObjcache( ( 'none' === objcacheEngine ? 0 : 1 ), 'none' === objcacheEngine ? '' : objcacheEngine )
						.fail( function() {
							$slide.append(
								'<div class="notice notice-error"><p><strong>' +
								W3TC_SetupGuide.config_error_msg +
								'</strong></p></div>'
							);
						});
				}

				break;

			case 'w3tc-wizard-slide-bc1':
				var $browsercacheSelection = $container.find( 'input:checked[name="browsercache_enable"]' ),
					browsercacheEnabled;

				if ( $browsercacheSelection.length ) {
					browsercacheEnabled = $browsercacheSelection.val();
					configBrowsercache( ( '1' === browsercacheEnabled ? 1 : 0 ) )
						.fail( function() {
							$slide.append(
								'<div class="notice notice-error"><p><strong>' +
								W3TC_SetupGuide.config_error_msg +
								'</strong></p></div>'
							);
						});
				}

				break;

			case 'w3tc-wizard-slide-io1':
				var imageserviceEnabled = $container.find( 'input:checked#imageservice-enable' ).val(),
					imageserviceSettings = getImageserviceFormSettings();
				configImageservice( ( '1' === imageserviceEnabled ? 1 : 0 ), imageserviceSettings )
					.fail( function() {
						$slide.append(
							'<div class="notice notice-error"><p><strong>' +
							W3TC_SetupGuide.config_error_msg +
							'</strong></p></div>'
						);
					});

				break;

			case 'w3tc-wizard-slide-ll1':
				var lazyloadEnabled = $container.find( 'input#lazyload-enable' ).is( ':checked' );
				configLazyload( lazyloadEnabled ? 1 : 0 )
					.fail( function() {
						$slide.append(
							'<div class="notice notice-error"><p><strong>' +
							W3TC_SetupGuide.config_error_msg +
							'</strong></p></div>'
						);
					});

				break;

			default:
				break;
		}
	}

	/**
	 * Configuration failed.
	 *
	 * @since 2.0.0
	 */
	function configFailed() {
		$slide.append(
			'<div class="notice notice-error"><p><strong>' +
			W3TC_SetupGuide.config_error_msg +
			'</strong></p></div>'
		);
		$nextButton.closest( 'span' ).hide();
		$prevButton.closest( 'span' ).hide();
		$skipButton.closest( 'span' ).show();
	}

	/**
	 * Test failed.
	 *
	 * @since 2.0.0
	 */
	function testFailed() {
		$slide.append(
			'<div class="notice notice-error"><p><strong>' +
			W3TC_SetupGuide.config_error_msg +
			'</strong></p></div>'
		);
		$nextButton.closest( 'span' ).hide();
		$prevButton.closest( 'span' ).hide();
		$skipButton.closest( 'span' ).show();
	}

	// Save selections when navigating away from the previous slide.
	savePreviousSlideSettings();

	// GA.
	if ( w3tc_enable_ga ) {
		w3tc_ga(
			'event',
			'button',
			{
				eventCategory: 'w3tc_setup_guide',
				eventLabel: slideId
			}
		);
	}

	switch ( slideId ) {
		case 'w3tc-wizard-slide-welcome':
			$container.find( '#w3tc-options-menu li' ).removeClass( 'is-active' );
			$container.find( '#w3tc-wizard-step-welcome' ).addClass( 'is-active' );
			break;

		case 'w3tc-wizard-slide-pc1':
			// Test Page Cache.
			$container.find( '#w3tc-options-menu li' ).removeClass( 'is-active' );
			$container.find( '#w3tc-wizard-step-pgcache' ).addClass( 'is-active' );

			$slide.find( '#w3tc-test-pgcache' ).off('click').on('click', function () {
				var $spinnerParent = $slide.find( '.spinner' ).addClass( 'is-active' ).parent(),
					$this = jQuery( this );

				$this.prop( 'disabled', 'disabled' );
				$slide.find( '.notice-error' ).remove();
				$container.find( '#w3tc-pgcache-table tbody' ).empty();
				$prevButton.prop( 'disabled', 'disabled' );
				$nextButton.prop( 'disabled', 'disabled' );

				$spinnerParent.show();

				/**
				 * Add a test result table row.
				 *
				 * @since 2.0.0
				 *
				 * @param object testResponse Data.
				 * @param string engine       Cache storage engine.
				 * @param string label        Text label for the engine.
				 */
				function addResultRow( testResponse, engine, label ) {
					var results = '<tr',
						percentChange,
						changeLabelType,
						changeLabel,
						averageSeconds,
						averageMs,
						isCurrentSetting = ( ! pgcacheSettings.enabled && 'none' === engine ) ||
							( pgcacheSettings.enabled && pgcacheSettings.engine === engine );

					if ( ! configSuccess ) {
						results += ' class="w3tc-option-disabled"';
					}

					results += '><td><input type="radio" id="pgcache-engine-' +
						engine +
						'" name="pgcache_engine" value="' +
						engine +
						'"';

					if ( ! configSuccess ) {
						results += ' disabled="disabled"';
					}

					if ( isCurrentSetting ) {
						results += ' checked';
					}

					if ( configSuccess && 'file_generic' === engine ) {
						label += '<br /><span class="w3tc-option-recommended">(Recommended)</span>';
					}

					results += '>';

					if ( isCurrentSetting ) {
						results += '<span class="dashicons dashicons-admin-settings" title="Original Setting"></span>';
					}

					results += '</td><td><label for="pgcache-engine-' +
						engine +
						'">' +
						label +
						'</label></td><td>';

					if ( testResponse.success ) {
						w3tcRecordTestResult( 'pgcache', engine, testResponse.data.ttfb );

						averageSeconds = w3tcGetAverageResult( 'pgcache', engine );
						averageMs = w3tcFormatMs( averageSeconds );

						results += ( testResponse.data.ttfb * 1000 ).toFixed( 2 );
						results += '</td><td>';

						if ( null !== averageMs ) {
							results += averageMs;

							if ( 'none' !== engine ) {
								percentChange = w3tcGetAveragePercentChange( 'pgcache', engine );

								if ( null !== percentChange ) {
									changeLabelType = percentChange < 0 ? 'w3tc-label-success' : 'w3tc-label-danger';
									changeLabel = '<span class="w3tc-label ' + changeLabelType + '">' + percentChange + '%</span>';

									$container.find( '#test-results' ).data( 'pgcacheDiffPercent-' + engine, percentChange );
									results += ' ' + changeLabel;
								}
							}
						} else {
							results += W3TC_SetupGuide.unavailable_text;
						}
					} else {
						results += W3TC_SetupGuide.unavailable_text + '</td><td>' + W3TC_SetupGuide.unavailable_text;
					}

					results += '</td></tr>';

					$container.find( '#w3tc-pgcache-table tbody' ).append( results );
					$container.find( '#w3tc-pgcache-table' ).show();
				}

				/**
				 * Test Page Cache.
				 *
				 * @since 2.0.0
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
						addResultRow( { success: false }, engine, label );
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
						$this.prop( 'disabled', false );
						$prevButton.prop( 'disabled', false );
						$nextButton.prop( 'disabled', false );
						return true;
					}, testFailed )
					.then( function() {
						w3tc_mark_slide_tests_complete( slideId );
						return true;
					} )
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
			// Present the Database Cache slide.
			$container.find( '#w3tc-options-menu li' ).removeClass( 'is-active' );
			$container.find( '#w3tc-wizard-step-dbcache' ).addClass( 'is-active' );

			$slide.find( '#w3tc-test-dbcache' ).off('click').on('click', function () {
				var $spinnerParent = $slide.find( '.spinner' ).addClass( 'is-active' ).parent(),
					$this = jQuery( this );

				$this.prop( 'disabled', 'disabled' );
				$slide.find( '.notice-error' ).remove();
				$container.find( '#w3tc-dbc-table tbody' ).empty();
				$container.find( '#w3tc-dbcache-recommended' ).hide();
				$prevButton.prop( 'disabled', 'disabled' );
				$nextButton.prop( 'disabled', 'disabled' );

				// Hide disk warning if using file.
				w3tc_disk_warning(false);

				// Show spinner.
				$spinnerParent.show();

				/**
				 * Add a test result table row.
				 *
				 * @since 2.0.0
				 *
				 * @param object testResponse Data.
				 * @param string engine       Cache storage engine.
				 * @param string label        Text label for the engine.
				 */
				function addResultRow( testResponse, engine, label ) {
					var results = '<tr',
						percentChange,
						changeLabelType,
						changeLabel,
						averageSeconds,
						averageMs,
						isCurrentSetting = ( ! dbcacheSettings.enabled && 'none' === engine ) ||
							( dbcacheSettings.enabled && dbcacheSettings.engine === engine );

					if ( ! configSuccess ) {
						results += ' class="w3tc-option-disabled"';
					}

					results += '><td><input type="radio" id="dbcache-engine-' +
						engine +
						'" name="dbcache_engine" value="' +
						engine +
						'"';

					if ( ! configSuccess ) {
						results += ' disabled="disabled"';
					}

					if ( isCurrentSetting ) {
							results += ' checked';
					}

					results += '>';

					if ( isCurrentSetting ) {
						results += '<span class="dashicons dashicons-admin-settings" title="Original Setting"></span>';
					}

					results += '</td><td><label for="dbcache-engine-' +
						engine +
						'">' +
						label +
						'</label></td><td>';

					if ( testResponse.success ) {
						w3tcRecordTestResult( 'dbcache', engine, testResponse.data.elapsed );

						averageSeconds = w3tcGetAverageResult( 'dbcache', engine );
						averageMs = w3tcFormatMs( averageSeconds );
						results += w3tcFormatMs( testResponse.data.elapsed );

						results += '</td><td>';

						if ( null !== averageMs ) {
							results += averageMs;

							if ( 'none' !== engine ) {
								percentChange = w3tcGetAveragePercentChange( 'dbcache', engine );
								if ( null !== percentChange ) {
									changeLabelType = percentChange < 0 ? 'w3tc-label-success' : 'w3tc-label-danger';
									changeLabel = '<span class="w3tc-label ' + changeLabelType + '">'+ percentChange + '%</span>';

									results += ' ' + changeLabel;
								}
							}
						} else {
							results += W3TC_SetupGuide.unavailable_text;
						}
					} else {
						results += W3TC_SetupGuide.unavailable_text + '</td><td>' + W3TC_SetupGuide.unavailable_text;
					}

					results += '</td></tr>';

					$container.find( '#w3tc-dbc-table tbody' ).append( results );
					$container.find( '#w3tc-dbc-table' ).show();
				}

				/**
				 * Test database cache.
				 *
				 * @since 2.0.0
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
						addResultRow( { success: false }, engine, label );
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
						$this.prop( 'disabled', false );
						$prevButton.prop( 'disabled', false );
						$nextButton.prop( 'disabled', false );
						return true;
					}, testFailed )
					// Show disk warning if using file.
					.then( function() {
						$container.find( '#w3tc-dbcache-recommended' ).show();

						var $dbcacheEngine = $container.find( 'input[name="dbcache_engine"]' );

						if ( $dbcacheEngine.parent().find(':checked').val() === 'file' ) {
							$container.find('#w3tc-dbcache-recommended').hide();
							w3tc_disk_warning();
						}

						$dbcacheEngine.on('change', function () {
							const $this = jQuery(this),
								isFile = $this.is(':checked') && $this.val() === 'file';

							$container.find('#w3tc-dbcache-recommended').toggle(! isFile);
							w3tc_disk_warning(isFile);
						});

						return true;
					})
					.then( function() {
						w3tc_mark_slide_tests_complete( slideId );
						return true;
					} )
					.then( function() {
						// Restore the original database cache settings.
						return configDbcache( ( dbcacheSettings.enabled ? 1 : 0 ), dbcacheSettings.engine );
					},
					function() {
						$spinnerParent.hide();
						return configFailed();
					});
			});

			break;

		case 'w3tc-wizard-slide-oc1':
			// Present the Object Cache slide.
			$container.find( '#w3tc-options-menu li' ).removeClass( 'is-active' );
			$container.find( '#w3tc-wizard-step-objectcache' ).addClass( 'is-active' );

			$slide.find( '#w3tc-test-objcache' ).off('click').on('click', function () {
				var $spinnerParent = $slide.find( '.spinner' ).addClass( 'is-active' ).parent(),
					$this = jQuery( this );

				$this.prop( 'disabled', 'disabled' );
				$slide.find( '.notice-error' ).remove();
				$container.find( '#w3tc-objcache-table tbody' ).empty();
				$prevButton.prop( 'disabled', 'disabled' );
				$nextButton.prop( 'disabled', 'disabled' );

				// Hide disk warning if using file.
				w3tc_disk_warning(false);

				// Show spinner.
				$spinnerParent.show();

				/**
				 * Add a test result table row.
				 *
				 * @since 2.0.0
				 *
				 * @param object testResponse Data.
				 * @param string engine       Cache storage engine.
				 * @param string label        Text label for the engine.
				 */
				function addResultRow( testResponse, engine, label ) {
					var results = '<tr',
						percentChange,
						changeLabelType,
						changeLabel,
						averageSeconds,
						averageMs,
						isCurrentSetting = ( ! objcacheSettings.enabled && 'none' === engine ) ||
							( objcacheSettings.enabled && objcacheSettings.engine === engine );

					if ( ! configSuccess ) {
						results += ' class="w3tc-option-disabled"';
					}

					results += '><td><input type="radio" id="objcache-engine-' +
						engine +
						'" name="objcache_engine" value="' +
						engine +
						'"';

					if ( ! configSuccess ) {
						results += ' disabled="disabled"';
					}

					if ( isCurrentSetting ) {
							results += ' checked';
					}

					results += '>';

					if ( isCurrentSetting ) {
						results += '<span class="dashicons dashicons-admin-settings" title="Original Setting"></span>';
					}

					results += '</td><td><label for="objcache-engine-' +
						engine +
						'">' +
						label +
						'</label></td><td>';

					if ( testResponse.success ) {
						w3tcRecordTestResult( 'objcache', engine, testResponse.data.elapsed );

						averageSeconds = w3tcGetAverageResult( 'objcache', engine );
						averageMs = w3tcFormatMs( averageSeconds );
						results += ( testResponse.data.elapsed * 1000 ).toFixed( 2 );

						results += '</td><td>';

						if ( null !== averageMs ) {
							results += averageMs;
							if ( 'none' !== engine ) {
								percentChange = w3tcGetAveragePercentChange( 'objcache', engine );
								if ( null !== percentChange ) {
									changeLabelType = percentChange < 0 ? 'w3tc-label-success' : 'w3tc-label-danger';
									changeLabel = '<span class="w3tc-label ' + changeLabelType + '">' + percentChange + '%</span>';

									results += ' ' + changeLabel;
								}
							}
						} else {
							results += W3TC_SetupGuide.unavailable_text;
						}
					} else {
						results += W3TC_SetupGuide.unavailable_text + '</td><td>' + W3TC_SetupGuide.unavailable_text;
					}

					results += '</td></tr>';

					$container.find( '#w3tc-objcache-table tbody' ).append( results );
					$container.find( '#w3tc-objcache-table' ).show();
				}

				/**
				 * Test object cache cache.
				 *
				 * @since 2.0.0
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
						addResultRow( { success: false }, engine, label );
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
						$this.prop( 'disabled', false );
						$prevButton.prop( 'disabled', false );
						$nextButton.prop( 'disabled', false );
						return true;
					}, testFailed )
					// Show disk warning if using file.
					.then( function() {
						var $objcacheEngine = $container.find( 'input[name="objcache_engine"]' );

						if ( $objcacheEngine.parent().find(':checked').val() === 'file' ) {
							w3tc_disk_warning();
						}

						$objcacheEngine.on('change', function () {
							const $this = jQuery(this);

							w3tc_disk_warning(( $this.is(':checked') && $this.val() === 'file' ));
						});

						return true;
					})
					.then( function() {
						w3tc_mark_slide_tests_complete( slideId );
						return true;
					} )
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
			// Present the Browser Cache slide.
			$container.find( '#w3tc-options-menu li' ).removeClass( 'is-active' );
			$container.find( '#w3tc-wizard-step-browsercache' ).addClass( 'is-active' );

			$slide.find( '#w3tc-test-browsercache' ).off('click').on('click', function () {
				var bcEnabled,
					$spinnerParent = $slide.find( '.spinner' ).addClass( 'is-active' ).parent(),
					$this = jQuery( this );

				$this.prop( 'disabled', 'disabled' );
				$slide.find( '.notice-error' ).remove();
				$container.find( '#w3tc-browsercache-table tbody' ).empty();
				$prevButton.prop( 'disabled', 'disabled' );
				$nextButton.prop( 'disabled', 'disabled' );

				$spinnerParent.show();

				/**
				 * Add a Browser Cache test result table row.
				 *
				 * @since 2.0.0
				 *
				 * @param object testResponse An object (success, data) containing a data array of objects
				 * 	                          (url, filename, header, headers).
				 */
				function addResultRow( testResponse ) {
					var label = bcEnabled ? W3TC_SetupGuide.enabled : W3TC_SetupGuide.notEnabled,
						results = '<tr',
						isCurrentSetting = bcEnabled == browsercacheSettings.enabled;

					if ( ! configSuccess ) {
						results += ' class="w3tc-option-disabled"';
					}

					results += '><td><input type="radio" id="browsercache-enable-' +
						label +
						'" name="browsercache_enable" value="' +
						bcEnabled +
						'"';

					if ( ! configSuccess ) {
						results += ' disabled="disabled"';
					}

					if ( isCurrentSetting ) {
						results += ' checked';
					}

					results += '> <label for="browsercache-enable-' +
						label +
						'">' +
						label +
						'</label>';

					if ( isCurrentSetting ) {
						results += ' <span class="dashicons dashicons-admin-settings" title="Original Setting"></span>';
					}

					results += '</td>';

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
								results += '<tr><td></td><td>';
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
				 * @since 2.0.0
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
						addResultRow( { success: false } );
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
						$this.prop( 'disabled', false );
						$prevButton.prop( 'disabled', false );
						$nextButton.prop( 'disabled', false );
						return true;
					}, testFailed )
					.then( function() {
						w3tc_mark_slide_tests_complete( slideId );
						return true;
					} )
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

		case 'w3tc-wizard-slide-io1':
			// Save the object cache engine setting from the previous slide.
			var $objcacheEngine = $container.find( 'input:checked[name="objcache_engine"]' ),
				objcacheEngine;

			if ( $objcacheEngine.length ) {
				objcacheEngine = $objcacheEngine.val();
				configObjcache( ( 'none' === objcacheEngine ? 0 : 1 ), 'none' === objcacheEngine ? '' : objcacheEngine )
					.fail( function() {
						$slide.append(
							'<div class="notice notice-error"><p><strong>' +
							W3TC_SetupGuide.config_error_msg +
							'</strong></p></div>'
						);
					});
			}

			// Present the Image Service slide.
			$container.find( '#w3tc-options-menu li' ).removeClass( 'is-active' );
			$container.find( '#w3tc-wizard-step-imageservice' ).addClass( 'is-active' );
			$dashboardButton.closest( 'span' ).hide();
			$nextButton.closest( 'span' ).show();
			$nextButton.prop( 'disabled', 'disabled' );
			var defaultImageserviceSettings = {
					webp: true,
					avif: true,
					compression: 'lossy',
					auto: 'enabled',
					visibility: 'never'
				},
				$imageserviceEnable = $container.find( 'input#imageservice-enable' ),
				$imageserviceOptions = $container.find( '#imageservice-options' );

			function toggleImageserviceOptions() {
				var enabled = $imageserviceEnable.is( ':checked' );
				$imageserviceOptions.toggleClass( 'hidden', ! enabled );
				$imageserviceOptions.find( 'input[type="checkbox"]' ).prop( 'disabled', ! enabled );
			}

			$imageserviceEnable.off( 'change' ).on( 'change', toggleImageserviceOptions );
			// Update the Image Service enable checkbox from saved config.
			getImageserviceSettings()
				.then( function() {
					var settings = jQuery.extend(
						{},
						defaultImageserviceSettings,
						imageserviceSettings.settings || {}
					);

					$imageserviceEnable.prop( 'checked', imageserviceSettings.enabled );
					$container.find( '#imageservice-webp' ).prop( 'checked', !! settings.webp );
					$container.find( '#imageservice-avif' ).prop( 'checked', !! settings.avif );
					toggleImageserviceOptions();
					$nextButton.prop( 'disabled', false );
				}, configFailed );

			break;

		case 'w3tc-wizard-slide-ll1':
			// Save the image service setting from the previous slide.
			var imageserviceEnabled = $container.find( 'input#imageservice-enable' ).is( ':checked' ),
				imageserviceSettings = getImageserviceFormSettings();
			configImageservice( ( imageserviceEnabled ? 1 : 0 ), imageserviceSettings )
				.fail( function() {
					$slide.append(
						'<div class="notice notice-error"><p><strong>' +
						W3TC_SetupGuide.config_error_msg +
						'</strong></p></div>'
					);
				});

			// Present the Lazy Load slide.
			$container.find( '#w3tc-options-menu li' ).removeClass( 'is-active' );
			$container.find( '#w3tc-wizard-step-lazyload' ).addClass( 'is-active' );
			$dashboardButton.closest( 'span' ).hide();
			$nextButton.closest( 'span' ).show();
			$nextButton.prop( 'disabled', 'disabled' );
			// Update the lazy load enable checkbox from saved config.
			getLazyloadSettings()
				.then( function() {
					$container.find( 'input#lazyload-enable' ).prop( 'checked', lazyloadSettings.enabled );
					$nextButton.prop( 'disabled', false );
				}, configFailed );

			break;

		case 'w3tc-wizard-slide-complete':
			var html,
				$pgcacheSelection = $container.find( 'input:checked[name="pgcache_engine"]' ),
				pgcacheEngine = $pgcacheSelection.val(),
				pgcacheEngineLabel = $pgcacheSelection.closest('td').next('td').text() || W3TC_SetupGuide.none,
				pgcacheDiffPercent = $container.find( '#test-results' )
					.data( 'pgcacheDiffPercent-' + pgcacheEngine ),
				$dbcacheSelection = $container.find( 'input:checked[name="dbcache_engine"]' ),
				dbcacheEngineLabel = $dbcacheSelection.closest('td').next('td').text() || W3TC_SetupGuide.none,
				$objcacheSelection = $container.find( 'input:checked[name="objcache_engine"]' ),
				objcacheEngineLabel = $objcacheSelection.closest('td').next('td').text() || W3TC_SetupGuide.none,
				imageserviceEnabled = $container.find( 'input#imageservice-enable' ).is( ':checked' ),
				lazyloadEnabled = $container.find( 'input#lazyload-enable' ).is( ':checked' );

			// Prevent leave page alert.
			jQuery( window ).off( 'beforeunload' );

			// Present the Setup Complete slide.
			$container.find( '#w3tc-options-menu li' ).removeClass( 'is-active' );
			$container.find( '#w3tc-options-menu li' ).last().addClass( 'is-active' );
			html = pgcacheDiffPercent !== undefined ?
				( pgcacheDiffPercent > 0 ? '+' : '' ) +
				parseFloat( pgcacheDiffPercent ).toFixed( 2 ) +
				'%' : '0.00%';

			$container.find( '#w3tc-ttfb-diff' ).html( html );

			$container.find( '#w3tc-pgcache-engine' ).html( pgcacheEngineLabel );

			$container.find( '#w3tc-dbcache-engine' ).html( dbcacheEngineLabel );

			$container.find( '#w3tc-objcache-engine' ).html( objcacheEngineLabel );

			$container.find( '#w3tc-imageservice-setting' ).html(
				imageserviceEnabled ? W3TC_SetupGuide.enabled : W3TC_SetupGuide.notEnabled
			);

			$container.find( '#w3tc-lazyload-setting' ).html(
				lazyloadEnabled ? W3TC_SetupGuide.enabled : W3TC_SetupGuide.notEnabled
			);

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

			$nextButton.closest( 'span' ).hide();
			$dashboardButton.closest( 'span' ).show();

			break;

		default:
			break;
	}
};
