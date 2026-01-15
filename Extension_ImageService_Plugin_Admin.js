/**
 * File: Extension_ImageService_Plugin_Admin.js
 *
 * JavaScript for the Media Library list page.
 *
 * @since 2.2.0
 *
 * @global w3tcData Localized data.
 */

(function( $ ) {
	var isCheckingItems = false,
		$convertLinks = $( 'a.w3tc-convert, a.w3tc-convert-format' ),
		$unconvertLinks = $( '.w3tc-revert > a' ),
		$convertAllButton = $( 'th.w3tc-imageservice-all' ).parent().find( 'td button' ),
		$revertAllButton = $( 'th.w3tc-imageservice-revertall' ).parent().find( 'td button' ),
		$refreshStatsButton = $( 'input#w3tc-imageservice-refresh-counts.button' ),
		$refreshUsageButton = $( 'input#w3tc-imageservice-refresh-usage.button' );

	/* On page load. */

	// Start checking items that are in the processing status.
	startCheckItems();

	// Disable ineligible buttons.
	toggleButtons();

	/* Events. */

	// Clicked convert link.
	$convertLinks.on( 'click', convertItem );

	// Clicked revert link.
	$unconvertLinks.on( 'click', revertItem );

	// Clicked convert all images button.
	$convertAllButton.on( 'click', convertItems );

	// Clicked revert all converted images button.
	$revertAllButton.on( 'click', revertItems );

	// Clicked the refresh icon for statistics counts.
	$refreshStatsButton.on( 'click', refreshStats );

	// Clicked the refresh icon for API usage statistics.
	$refreshUsageButton.on( 'click', refreshUsage );

	/* Functions. */

	/**
	 * Check if a format is enabled in settings.
	 *
	 * @since 2.9.1
	 *
	 * @param string formatKey Format key (webp or avif).
	 * @return bool
	 */
	function isFormatEnabled( formatKey ) {
		var settings = w3tcData.settings || {};

		if ( 'webp' === formatKey ) {
			return typeof settings.webp === 'undefined' || true === settings.webp || '1' === settings.webp || 1 === settings.webp;
		}

		if ( 'avif' === formatKey ) {
			return ( typeof settings.avif === 'undefined' || true === settings.avif || '1' === settings.avif || 1 === settings.avif ) && w3tcData.isPro;
		}

		return false;
	}

	/**
	 * Ensure a convert link exists for a format.
	 *
	 * @since 2.9.1
	 *
	 * @param jQuery $itemTd Row cell.
	 * @param string formatKey Format key (webp or avif).
	 * @param string postId Attachment ID.
	 */
	function ensureConvertLink( $itemTd, formatKey, postId ) {
		var linkClass = 'avif' === formatKey ? 'w3tc-convert-avif' : 'w3tc-convert-webp',
			linkText = 'avif' === formatKey ? w3tcData.lang.convertToAvif : w3tcData.lang.convertToWebp;

		if ( $itemTd.find( '.' + linkClass + ' a' ).length ) {
			return;
		}

		$itemTd.append(
			'<span class="' + linkClass + '"> | <a class="w3tc-convert-format" data-post-id="' +
			postId + '" data-status="converted" data-format="' + formatKey + '" aria-disabled="false">' +
			linkText + '</a></span>'
		);

		$itemTd.find( 'a.w3tc-convert-format' ).off( 'click' ).on( 'click', convertItem );
	}

	/**
	 * Toggle buttons based on eligibility.
	 *
	 * @since 2.2.0
	 */
	function toggleButtons() {
		if ( $convertAllButton.length && $( '#w3tc-imageservice-unconverted' ).text() < 1 ) {
			$convertAllButton.prop( 'disabled', true ).prop( 'aria-disabled', 'true' ); // Disable button.
		}

		if ( $revertAllButton.length && $( '#w3tc-imageservice-converted' ).text() < 1 ) {
			$revertAllButton.prop( 'disabled', true ).prop( 'aria-disabled', 'true' ); // Disable button.
		}
	}

	/**
	 * Start checking items that are in the processing status.
	 *
	 * @since 2.2.0
	 *
	 * @see checkItemsProcessing()
	 */
	function startCheckItems() {
		if ( isCheckingItems ) {
			return;
		}

		isCheckingItems= true;

		// Check status and update every 5 seconds.
		checkitemsInterval = setInterval( checkItemsProcessing, 5000 );

		// Stop checking after 5 minutes.
		setTimeout(
			function() {
				clearInterval( checkitemsInterval );
				isCheckingItems = false;
			},
			60 * 5 * 1000
		);
	}

	/**
	 * Check processing items.
	 *
	 * @since 2.2.0
	 *
	 * @see checkItemProcessing()
	 */
	function checkItemsProcessing() {
		$convertLinks.each( checkItemProcessing );
	}

	/**
	 * Callback: Check processing item.
	 *
	 * @since 2.2.0
	 */
	 function checkItemProcessing() {
		var $this = $( this ),
			$itemTd = $this.closest( 'td' );

		// If marked as processing, then check for status change an update status on screen.
		if ( 'processing' === $this.data( 'status' ) ) {
			$.ajax({
				method: 'POST',
				url: ajaxurl,
				data: {
					_wpnonce: w3tcData.nonces.postmeta,
					action: 'w3tc_imageservice_postmeta',
					post_id: $this.data( 'post-id' )
				}
			})
				.done( function( response ) {
					var infoClass, downloadData, downloadHeaders, reducedPercent, filesizeIn, filesizeOut, formatKey, i;

					// Remove any previous optimization information and the revert link.
					$itemTd.find(
						'.w3tc-converted-reduced, .w3tc-converted-increased, .w3tc-notconverted, .w3tc-revert, .w3tc-converted-error'
					).remove();

					// Handle multiple formats (new structure).
					if ( 'notconverted' !== response.data.status && response.data.downloads && typeof response.data.downloads === 'object' ) {
						// Sort formats to maintain order: WEBP first, then AVIF.
						var sortedFormats = Object.keys( response.data.downloads ).sort( function( a, b ) {
							var order = { 'webp': 1, 'avif': 2 };
							var aOrder = order[ a ] || 99;
							var bOrder = order[ b ] || 99;
							return aOrder - bOrder;
						});

						// Iterate through all formats in reverse order since prepend() adds to the beginning.
						// This ensures WEBP appears first, then AVIF.
						for ( i = sortedFormats.length - 1; i >= 0; i-- ) {
							formatKey = sortedFormats[ i ];
							downloadData = response.data.downloads[ formatKey ];

							// Skip if it's an error string.
							if ( typeof downloadData === 'string' ) {
								// Show error message for failed formats.
								$itemTd.prepend(
									'<div class="w3tc-converted-error">' +
									formatKey.toUpperCase() + ': ' + downloadData +
									'</div>'
								);
								if ( isFormatEnabled( formatKey ) ) {
									ensureConvertLink( $itemTd, formatKey, $this.data( 'post-id' ) );
								}
								continue;
							}

							if ( typeof downloadData !== 'object' || ! downloadData ) {
								continue;
							}

							// Extract headers - handle both normalized format and old format.
							downloadHeaders = downloadData;
							if ( downloadData["\u0000*\u0000data"] ) {
								downloadHeaders = downloadData["\u0000*\u0000data"];
							}

							// Normalize header keys to lowercase for case-insensitive access.
							var normalizedHeaders = {};
							for ( var key in downloadHeaders ) {
								if ( downloadHeaders.hasOwnProperty( key ) && key.charAt( 0 ) !== '\u0000' ) {
									normalizedHeaders[ key.toLowerCase() ] = downloadHeaders[ key ];
								}
							}

							reducedPercent = normalizedHeaders['x-filesize-reduced'] || null;
							filesizeIn     = normalizedHeaders['x-filesize-in'] || null;
							filesizeOut    = normalizedHeaders['x-filesize-out'] || null;

							// Check if this format was actually converted (exists in post_children).
							var postChildren = response.data.post_children || {};
							var wasConverted = postChildren[ formatKey ] && postChildren[ formatKey ] !== null && postChildren[ formatKey ] !== '';

							// If not converted, show "not converted" message instead of statistics.
							if ( ! wasConverted ) {
								$itemTd.prepend(
									'<div class="w3tc-notconverted">' +
									formatKey.toUpperCase() + ': ' +
									w3tcData.lang.notConverted +
									'</div>'
								);
								continue;
							}

							// Display if we have the necessary data and the format was converted.
							if ( filesizeIn && filesizeOut && reducedPercent ) {
								var reducedNumeric = parseFloat( reducedPercent.toString().replace( '%', '' ) );
								infoClass = reducedNumeric >= 100 ? 'w3tc-converted-increased' : 'w3tc-converted-reduced';

								$itemTd.prepend(
									'<div class="' + infoClass + '">' +
									formatKey.toUpperCase() + ': ' +
									sizeFormat( filesizeIn ) +
									' &#8594; ' +
									sizeFormat( filesizeOut ) +
									' (' + reducedPercent + ')' +
									'</div>'
								);
							}
						}
					} else if ( 'notconverted' !== response.data.status && response.data.download && response.data.download["\u0000*\u0000data"] ) {
						// Handle single format (backward compatibility).
						downloadHeaders = response.data.download["\u0000*\u0000data"];
						reducedPercent  = downloadHeaders['x-filesize-reduced'] || null;
						filesizeIn      = downloadHeaders['x-filesize-in'] || null;
						filesizeOut     = downloadHeaders['x-filesize-out'] || null;

						if ( filesizeIn && filesizeOut && reducedPercent ) {
							var reducedNumeric = parseFloat( reducedPercent.toString().replace( '%', '' ) );
							infoClass = reducedNumeric > 100 ? 'w3tc-converted-increased' : 'w3tc-converted-reduced';

							$itemTd.prepend(
								'<div class="' + infoClass + '">' +
								sizeFormat( filesizeIn ) +
								' &#8594; ' +
								sizeFormat( filesizeOut ) +
								' (' + reducedPercent + ')' +
								'</div>'
							);
						}
					}

					if ( 'converted' === response.data.status ) {
						var clickedFormat = $this.data( 'format' );
						var $primaryLink = $itemTd.find( 'a.w3tc-convert' ).not( '[data-format]' ).first();

						// Determine which format(s) were converted.
						var convertedFormats = [];
						var postChildren = response.data.post_children || {};

						// Check new format (post_children).
						for ( var formatKey in postChildren ) {
							if ( postChildren.hasOwnProperty( formatKey ) && postChildren[ formatKey ] ) {
								convertedFormats.push( formatKey.toUpperCase() );
							}
						}

						// Check old format (post_child).
						if ( convertedFormats.length === 0 && response.data.post_child ) {
							// For old format, we can't determine the format from the response,
							// but typically it's WEBP. We'll show "Converted" as fallback.
							convertedFormats.push( 'WEBP' );
						}

						// Display format(s) or default to "Converted".
						var convertedText = convertedFormats.length > 0 ?
							convertedFormats.join( '/' ) + ' ' + w3tcData.lang.converted :
							w3tcData.lang.converted;

						// Always update the primary status link text.
						if ( $primaryLink.length ) {
							$primaryLink
								.text( convertedText )
								.data( 'status', 'converted' );
						} else {
							// Fallback: update whatever link is being checked.
							$this
								.text( convertedText )
								.data( 'status', 'converted' );
						}

						// If this was a secondary action link (Convert to AVIF/WebP), don't turn it into a status link.
						// Either remove it (if the requested format now exists) or restore its label.
						if ( clickedFormat ) {
							var requestedKey = clickedFormat.toString().toLowerCase();
							var hasRequested = postChildren[ requestedKey ] && postChildren[ requestedKey ] !== null && postChildren[ requestedKey ] !== '';

							if ( hasRequested ) {
								// Remove the secondary action link wrapper.
								$this.closest( 'span' ).remove();
							} else {
								// Restore the action label and clear processing state.
								if ( 'avif' === requestedKey ) {
									$this.text( w3tcData.lang.convertToAvif );
								} else if ( 'webp' === requestedKey ) {
									$this.text( w3tcData.lang.convertToWebp );
								} else {
									$this.text( w3tcData.lang.convert );
								}
								$this.data( 'status', 'converted' ).prop( 'aria-disabled', 'false' );
								$this.closest( 'span' ).removeClass( 'w3tc-disabled' );
							}
						}

						// Add revert link, if not already present.
						if ( ! $itemTd.find( '.w3tc-revert' ).length ) {
							$itemTd.append(
								'<span class="w3tc-revert"> | <a>' +
								w3tcData.lang.revert +
								'</a></span>'
							);

							// Update global revert link.
							$( '.w3tc-revert > a' ).unbind().on( 'click', revertItem );
						}
					} else if ( 'notconverted' === response.data.status ) {
						$this.data( 'status', 'notconverted' );

						$itemTd.prepend(
							'<div class="w3tc-notconverted">' +
							w3tcData.lang.notConvertedDesc +
							'</div>'
						);

						if ( 'lossless' === w3tcData.settings.compression ) {
							$this
								.text( w3tcData.lang.settings )
								.prop( 'aria-disabled' , 'false')
								.closest( 'span' ).removeClass( 'w3tc-disabled' );
						} else {
							$this.text( w3tcData.lang.notConverted );
						}
					} else if ( 'notfound' === response.data.status ) {
						$this
							.text( w3tcData.lang.convert )
							.data( 'status', 'notfound' )
							.prop( 'aria-disabled', 'false' )
							.closest( 'span' ).removeClass( 'w3tc-disabled' );
					}
				})
				.fail( function() {
					$this
						.text( w3tcData.lang.error )
						.data( 'status', null );
					$itemTd.find( '.w3tc-imageservice-error' ).remove();
					$itemTd.append(
						'<div class="notice notice-error inline w3tc-imageservice-error">' +
						w3tcData.lang.ajaxFail +
						'</div>'
					);
				});
		}
	}

	/**
	 * Refresh statistics/counts.
	 *
	 * @since 2.2.0
	 */
	function refreshStats() {
		var $countsTable = $( 'table#w3tc-imageservice-counts' );

		// Update the refresh button text.
		$refreshStatsButton
			.val( w3tcData.lang.refreshing )
			.prop( 'disabled', true )
			.prop( 'aria-disabled', 'true' );

		// Remove any error notices.
		$countsTable.find( '.w3tc-imageservice-error' ).remove();

		$.ajax({
			method: 'POST',
			url: ajaxurl,
			data: {
				_wpnonce: w3tcData.nonces.submit,
				action: 'w3tc_imageservice_counts'
			}
		})
			.done( function( response ) {
				if ( response.data && response.data.hasOwnProperty( 'total' ) ) {
					[ 'total', 'converted', 'sending', 'processing', 'notconverted', 'unconverted' ].forEach( function( className ) {
						var size,
							$size,
							$count = $countsTable.find( '#w3tc-imageservice-' + className );
						if ( parseInt( $count.text() ) !== response.data[ className ] ) {
							$count.text( response.data[ className ] ).closest( 'tr' ).addClass( 'w3tc-highlight' );

							className += 'bytes';
							$size = $countsTable.find( '#w3tc-imageservice-' + className );
							size = sizeFormat( response.data[ className ], 2 );
							$size.text( size );
						}
					} );
				}

				// Update the refresh button text.
				$refreshStatsButton
					.val( w3tcData.lang.refresh )
					.prop( 'disabled', false )
					.prop( 'aria-disabled', 'false' );

				// Remove highlights.
				setTimeout(
					function() {
						$countsTable.find( '.w3tc-highlight' ).removeClass( 'w3tc-highlight' );
					},
					1000
				);
			})
			.fail( function() {
				$countsTable.append(
					'<div class="notice notice-error inline w3tc-imageservice-error">' +
					w3tcData.lang.ajaxFail +
					'</div>'
				);

				// Update the refresh button text.
				$refreshStatsButton
					.val( w3tcData.lang.error )
					.prop( 'disabled', false )
					.prop( 'aria-disabled', 'false' );
			});
	}

	/**
	 * Refresh API usage statistics.
	 *
	 * @since 2.2.0
	 */
	function refreshUsage() {
		var $usageTable = $( 'table#w3tc-imageservice-usage' );

		// Update the refresh button text.
		$refreshUsageButton
			.val( w3tcData.lang.refreshing )
			.prop( 'disabled', true )
			.prop( 'aria-disabled', 'true' );

		// Remove any error notices.
		$usageTable.find( '.w3tc-imageservice-error' ).remove();

		$.ajax({
			method: 'POST',
			url: ajaxurl,
			data: {
				_wpnonce: w3tcData.nonces.submit,
				action: 'w3tc_imageservice_usage'
			}
		})
			.done( function( response ) {
				if ( response.data && response.data.hasOwnProperty( 'usage_hourly' ) ) {
					[ 'usage_hourly', 'usage_monthly', 'limit_hourly', 'limit_monthly' ].forEach( function( keyName ) {
						var className = keyName.replace( '_', '-' ),
							$count = $usageTable.find( '#w3tc-imageservice-' + className );
						if ( $count.text() != response.data[ keyName ] ) {
							$count.text( response.data[ keyName ] ).closest( 'tr' ).addClass( 'w3tc-highlight' );
						}
					});
				}

				// Update the refresh button text.
				$refreshUsageButton
					.val( w3tcData.lang.refresh )
					.prop( 'disabled', false )
					.prop( 'aria-disabled', 'false' );

				// Remove highlights.
				setTimeout(
					function() {
						$usageTable.find( '.w3tc-highlight' ).removeClass( 'w3tc-highlight' );
					},
					1000
				);
			})
			.fail( function() {
				$usageTable.append(
					'<div class="notice notice-error inline w3tc-imageservice-error">' +
					w3tcData.lang.ajaxFail +
					'</div>'
				);

				// Update the refresh button text.
				$refreshUsageButton
					.val( w3tcData.lang.error )
					.prop( 'disabled', false )
					.prop( 'aria-disabled', 'false' );
			});
	}

	/**
	 * Convert number of bytes largest unit bytes will fit into.
	 *
	 * Similar to size_format(), but in JavaScript.
	 *
	 * @since 2.2.0
	 *
	 * @param int size     Size in bytes.
	 * @param int decimals Number of decimal places.
	 * @return string
	 */
	function sizeFormat( size, decimals = 0 ) {
		var bytes = parseFloat( size ),
			units = [ 'B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB' ],
			i = 0;

		if ( isNaN( bytes ) ) {
			return '0 B';
		}

		while ( bytes >= 1024 ) {
			bytes /= 1024;
			++i;
		}

		return bytes.toFixed( decimals ) + ' ' + units[ i ];
	}

	/* Event callback functions */

	/**
	 * Event callback: Convert an item.
	 *
	 * @since 2.2.0
	 *
	 * @param event e Event object.
	 */
	 function convertItem() {
		var $clicked = $( this ),
			$itemTd = $clicked.closest( 'td' ),
			$primaryLink = $itemTd.find( 'a.w3tc-convert' ).first();

		// Fallback: if no primary link found, use clicked link.
		if ( ! $primaryLink.length ) {
			$primaryLink = $clicked;
		}

		// If the conversion was canceled and the compression setting is "lossless", then go to the settings page.
		if ( 'notconverted' === $primaryLink.data( 'status' ) && 'lossless' === w3tcData.settings.compression ) {
			window.location.href = w3tcData.settingsUrl;
			return;
		}

		$primaryLink
			.text( w3tcData.lang.sending )
			.prop( 'aria-disabled' , 'true')
			.closest( 'span' ).addClass( 'w3tc-disabled' );

		// Remove any previous optimization information, revert link, and error notices.
		$itemTd.find( '.w3tc-converted-reduced, .w3tc-converted-increased, .w3tc-notconverted, .w3tc-revert, .w3tc-imageservice-error, .w3tc-converted-error, .w3tc-convert-avif, .w3tc-convert-webp' ).remove();

		// Get format from data-format attribute if specified (for "Convert to AVIF" or "Convert to WebP" links).
		var format = $clicked.data( 'format' );
		var ajaxData = {
			_wpnonce: w3tcData.nonces.submit,
			action: 'w3tc_imageservice_submit',
			post_id: $primaryLink.data( 'post-id' )
		};

		// Add format parameter if specified.
		if ( format ) {
			ajaxData.format = format;
		}

		$.ajax({
			method: 'POST',
			url: ajaxurl,
			data: ajaxData
		})
			.done( function( response ) {
				if ( response.success ) {
					$primaryLink
						.text( w3tcData.lang.processing )
						.data( 'status', 'processing' );

					startCheckItems();
				} else if ( response.data && response.data.hasOwnProperty( 'error' ) ) {
					$primaryLink
						.text( w3tcData.lang.error )
						.data( 'status', 'error' );

					$itemTd.append(
						'<div class="notice notice-error inline">' +
						response.data.error +
						'</div>'
					);
				} else {
					$primaryLink
						.text( w3tcData.lang.error )
						.data( 'status', 'error' );

					$itemTd.append(
						'<div class="notice notice-error inline w3tc-imageservice-error">' +
						w3tcData.lang.apiError +
						'</div>'
					);
				}
			})
			.fail( function( response ) {
				var message,
					rebindBuyClick = false,
					$wrap = $( '.wrap' );

				$primaryLink
					.text( w3tcData.lang.error )
					.data( 'status', 'error' );

				if (
					response && response.hasOwnProperty( 'responseJSON' ) &&
					response.responseJSON.hasOwnProperty( 'data' ) &&
					response.responseJSON.data.hasOwnProperty( 'message' )
				) {
					message = response.responseJSON.data.message;
					rebindBuyClick = true;

					if ( 'accept' === w3tcData.tos_choice && w3tcData.track_usage ) {
						(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
							(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
							m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
							})(window,document,'script','https://api.w3-edge.com/v1/analytics','w3tc_ga');

						if ( window.w3tc_ga ) {
							w3tc_ga( 'create', w3tcData.ga_profile, 'auto' );
							w3tc_ga( 'send', 'event', 'w3tc_error', 'imageservice', response.responseJSON.data.code );
						}
					}
				} else {
					message = w3tcData.lang.ajaxFail;
				}

				$itemTd.append(
					'<div class="notice notice-error inline w3tc-imageservice-error">' +
					message +
					'</div>'
				);

				// Rebind click event handler after adding a new link that may need it.
				if ( rebindBuyClick ) {
					// Ensure overlay.
					if ( 'w3tc' !== $wrap.attr( 'id' ) ) {
						$wrap.attr( 'id', 'w3tc' );
					}

					// Rebind click event.
					$( '.button-buy-plugin' )
					.off( 'click' )
					.on( 'click',  function() {
						if ( ! $( '.w3tc-overlay' ).length ) {
							w3tc_lightbox_upgrade( w3tc_nonce, $( this ).data('src'), null );
						}
					} );
				}
			});
	}

	/**
	 * Event callback: Revert item.
	 *
	 * @since 2.2.0
	 *
	 * @param event e Event object.
	 */
	function revertItem() {
		var $this = $( this ),
			$itemTd = $this.closest( 'td' ),
			$convertLink = $itemTd.find( 'a.w3tc-convert' );

		$this
			.text( w3tcData.lang.reverting )
			.prop( 'aria-disabled', 'true' )
			.closest( 'span' ).addClass( 'w3tc-disabled' );

		$convertLink
			.prop( 'aria-disabled', 'true' )
			.closest( 'span' ).addClass( 'w3tc-disabled' );

		// Remove error notices.
		$itemTd.find( '.w3tc-imageservice-error' ).remove();

		$.ajax({
			method: 'POST',
			url: ajaxurl,
			data: {
				_wpnonce: w3tcData.nonces.revert,
				action: 'w3tc_imageservice_revert',
				post_id: $convertLink.data( 'post-id' )
			}
		})
			.done( function( response ) {
				if ( response.success ) {
					$this.closest( 'span' ).remove(); // Remove the revert link.
					$itemTd.find( 'div' ).remove(); // Remove optimization info.
					$itemTd.find( '.w3tc-convert-avif, .w3tc-convert-webp' ).remove(); // Remove secondary convert links.

					$convertLink
						.text( w3tcData.lang.convert )
						.prop( 'aria-disabled', false )
						.data( 'status', null )
						.closest( 'span' ).removeClass( 'w3tc-disabled' );
				} else if ( response.data && response.data.hasOwnProperty( 'error' ) ) {
					$this
						.text( w3tcData.lang.error )
						.data( 'status', 'error' );

					$itemTd.parent().append(
						'<div class="notice notice-error inline">' +
						response.data.error +
						'</div>'
					);
				} else {
					$this
						.text( w3tcData.lang.error )
						.data( 'status', 'error' );

					$itemTd.append(
						'<div class="notice notice-error inline w3tc-imageservice-error">' +
						w3tcData.lang.apiError +
						'</div>'
					);
				}
			})
			.fail( function() {
				$this
					.text( w3tcData.lang.error )
					.data( 'status', 'error' );

				$itemTd.append(
					'<div class="notice notice-error inline w3tc-imageservice-error">' +
					w3tcData.lang.ajaxFail +
					'</div>'
				);
			});
	}

	/**
	 * Event callback: Convert all items.
	 *
	 * @since 2.2.0
	 *
	 * @see refreshStats()
	 * @see refreshUsage()
	 */
	 function convertItems() {
		var $this = $( this ),
			$parent = $this.parent();

		$this
			.text( w3tcData.lang.sending )
			.prop( 'disabled', true )
			.prop( 'aria-disabled', 'true' );

		// Remove error notices.
		$parent.find( '.w3tc-imageservice-error' ).remove();

		$.ajax({
			method: 'POST',
			url: ajaxurl,
			data: {
				_wpnonce: w3tcData.nonces.submit,
				action: 'w3tc_imageservice_all'
			}
		})
			.done( function( response ) {
				if ( response.success ) {
					$this.text( w3tcData.lang.submitted );
					$parent.append(
						'<div class="notice notice-info inline">' +
						w3tcData.lang.submittedAllDesc +
						'</div>'
					);
					refreshStats();
					refreshUsage();
				} else if ( response.data && response.data.hasOwnProperty( 'error' ) ) {
					$this.text( w3tcData.lang.error );
					$parent.append(
						'<div class="notice notice-error inline">' +
						response.data.error +
						'</div>'
					);
				} else {
					$this.text( w3tcData.lang.error );
					$parent.append(
						'<div class="notice notice-error inline w3tc-imageservice-error">' +
						w3tcData.lang.apiError +
						'</div>'
					);
				}
			})
			.fail( function() {
				$this.text( w3tcData.lang.error );
				$parent.append(
					'<div class="notice notice-error inline w3tc-imageservice-error">' +
					w3tcData.lang.ajaxFail +
					'</div>'
				);
			});
	}

	/**
	 * Event callback: Revert all items.
	 *
	 * @since 2.2.0
	 *
	 * @see refreshStats()
	 */
	 function revertItems() {
		var $this = $( this );

		$this.text( w3tcData.lang.reverting )
			.prop( 'disabled', true )
			.prop( 'aria-disabled', 'true' );

		$.ajax({
			method: 'POST',
			url: ajaxurl,
			data: {
				_wpnonce: w3tcData.nonces.submit,
				action: 'w3tc_imageservice_revertall'
			}
		})
			.done( function( response ) {
				if ( response.success ) {
					$this.text( w3tcData.lang.reverted );
					$convertAllButton
						.prop( 'disabled', false )
						.prop( 'aria-disabled', 'false' );
					refreshStats();
				} else if ( response.data && response.data.hasOwnProperty( 'error' ) ) {
					$this
						.text( w3tcData.lang.error )
						.parent().append(
							'<div class="notice notice-error inline">' +
							response.data.error +
							'</div>'
						);
				} else {
					$this
						.text( w3tcData.lang.error )
						.parent().append(
							'<div class="notice notice-error inline w3tc-imageservice-error">' +
							w3tcData.lang.apiError +
							'</div>'
						);
				}
			})
			.fail( function() {
				$this
					.text( w3tcData.lang.error )
					.parent().append(
						'<div class="notice notice-error inline w3tc-imageservice-error">' +
						w3tcData.lang.ajaxFail +
						'</div>'
					);
			});
	}
})( jQuery );
