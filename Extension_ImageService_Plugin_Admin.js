/**
 * File: Extension_ImageService_Plugin_Admin.js
 *
 * JavaScript for the Media Library list page.
 *
 * @since X.X.X
 *
 * @global w3tcData Localized data.
 */

(function( $ ) {
	var isCheckingItems = false,
		$convertLinks = $( 'a.w3tc-convert' ),
		$unconvertLinks = $( '.w3tc-revert > a' ),
		$convertAllButton = $( 'th.w3tc-imageservice-all' ).parent().find( 'td button' ),
		$revertAllButton = $( 'th.w3tc-imageservice-revertall' ).parent().find( 'td button' ),
		$refreshStatsButton = $( 'input#w3tc-imageservice-refresh.button' );

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

	// Clicked the refresh icon for statistics.
	$refreshStatsButton.on( 'click', refreshStats );

	/* Functions. */

	/**
	 * Toggle buttons based on eligibility.
	 *
	 * @since X.X.X
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
	 * @since X.X.X
	 *
	 * @see checkNotConverted()
	 * @see checkItemsProcessing()
	 */
	function startCheckItems() {
		if ( isCheckingItems ) {
			return;
		}

		isCheckingItems= true;

		// If any processed images were not converted, then print a notice to check settings.
		checkNotConverted();

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
	 * @since X.X.X
	 *
	 * @see checkItemProcessing()
	 */
	function checkItemsProcessing() {
		$convertLinks.each( checkItemProcessing );
	};


	/**
	 * Callback: Check processing item.
	 *
	 * @since X.X.X
	 *
	 * @see checkNotConverted()
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
					var infoClass;

					// Remove any previous optimization information and the revert link.
					$itemTd.find(
						'.w3tc-converted-reduced, .w3tc-converted-increased, .w3tc-notconverted, .w3tc-revert'
					).remove();

					// Add optimization information.
					if ( 'notconverted' !== response.data.status && response.data.download && response.data.download["\u0000*\u0000data"] ) {
						infoClass = response.data.download["\u0000*\u0000data"]['x-filesize-reduced'] > 0 ?
							'w3tc-converted-increased' : 'w3tc-converted-reduced';

						$itemTd.prepend(
							'<div class="' +
							infoClass +
							'">' +
							sizeFormat( response.data.download["\u0000*\u0000data"]['x-filesize-in'] ) +
							' &#8594; ' +
							sizeFormat( response.data.download["\u0000*\u0000data"]['x-filesize-out'] ) +
							' (' +
							response.data.download["\u0000*\u0000data"]['x-filesize-reduced'] +
							')</div>'
						);
					}

					if ( 'converted' === response.data.status ) {
						$this
							.text( w3tcData.lang.converted )
							.data( 'status', 'converted' );

						// Add revert link, if not already present.
						if ( ! $itemTd.find( '.w3tc-revert' ).length ) {
							$itemTd.append(
								'<span class="w3tc-revert"> | <a href="#">' +
								w3tcData.lang.revert +
								'</a></span>'
							);

							// Update global revert link.
							$( '.w3tc-revert > a' ).unbind().on( 'click', revertItem );
						}
					} else if ( 'notconverted' === response.data.status ) {
						$this
							.text( w3tcData.lang.notConverted )
							.data( 'status', 'notconverted' );

						$itemTd.prepend(
							'<div class="w3tc-notconverted">' +
							w3tcData.lang.notConvertedDesc +
							'</div>'
						);
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

		// If any processed images were not converted, then print a notice to check settings.
		checkNotConverted();
	}

	/**
	 * Check for images not converted and print a notice if nay are found.
	 *
	 * @since X.X.X
	 */
	function checkNotConverted() {
		if ( 'lossy' !== w3tcData.settings.compression && ! $( '#w3tc-notconverted-notice' ).length && $( '.w3tc-notconverted' ).length ) {
			$( '#wpbody-content' ).prepend(
				'<div id="w3tc-notconverted-notice" class="notice notice-warning is-dismissible"><p><span class="w3tc-convert"></span> Image Service</p><p>' +
				w3tcData.lang.notConvertedNotice +
				'</p></div>'
			);
		}
	}

	/**
	 * Refresh statistics/counts.
	 *
	 * @since X.X.X
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
	 * Convert number of bytes largest unit bytes will fit into.
	 *
	 * Similar to size_format(), but in JavaScript.
	 *
	 * @since X.X.X
	 *
	 * @param int size     Size in bytes.
	 * @param int decimals Number of decimal places.
	 * @return string
	 */
	function sizeFormat( size, decimals = 0 ) {
		var units = [ 'B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB' ],
			i = 0;

		while ( size >= 1024 ) {
			size /= 1024;
			++i;
		}

		return size.toFixed( decimals ) + ' ' + units[ i ];
	}

	/* Event callback functions */

	/**
	 * Event callback: Convert an item.
	 *
	 * @since X.X.X
	 *
	 * @param event e Event object.
	 */
	 function convertItem() {
		var $this = $( this ),
			$itemTd = $this.closest( 'td' );

		$this
			.text( w3tcData.lang.sending )
			.prop( 'aria-disabled' , 'true')
			.closest( 'span' ).addClass( 'w3tc-disabled' );

		// Remove any previous optimization information, revert link, and error notices.
		$itemTd.find( '.w3tc-converted-reduced, .w3tc-converted-increased, .w3tc-revert, .w3tc-imageservice-error' ).remove();

		$.ajax({
			method: 'POST',
			url: ajaxurl,
			data: {
				_wpnonce: w3tcData.nonces.submit,
				action: 'w3tc_imageservice_submit',
				post_id: $this.data( 'post-id' )
			}
		})
			.done( function( response ) {
				if ( response.success ) {
					$this
						.text( w3tcData.lang.processing )
						.data( 'status', 'processing' );

					startCheckItems();
				} else if ( response.data && response.data.hasOwnProperty( 'error' ) ) {
					$this
						.text( w3tcData.lang.error )
						.data( 'status', 'error' );

					$itemTd.append(
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
			.fail( function( response ) {
				var message;

				$this
					.val( w3tcData.lang.error )
					.data( 'status', 'error' );

				if (
					response && response.hasOwnProperty( 'responseJSON' ) &&
					response.responseJSON.hasOwnProperty( 'data' ) &&
					response.responseJSON.data.hasOwnProperty( 'message' )
				) {
					message = response.responseJSON.data.message;
				} else {
					message = w3tcData.lang.ajaxFail;
				}

				$itemTd.append(
					'<div class="notice notice-error inline w3tc-imageservice-error">' +
					message +
					'</div>'
				);
			});
	}

	/**
	 * Event callback: Revert item.
	 *
	 * @since X.X.X
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
	};

	/**
	 * Event callback: Convert all items.
	 *
	 * @since X.X.X
	 *
	 * @see refreshStats()
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
					$this.text( w3tcData.lang.processing );
					refreshStats();
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
	 * @since X.X.X
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
