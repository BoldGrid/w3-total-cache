/**
 * File: Extension_ImageOptimizer_Plugin_Admin.js
 *
 * JavaScript for the Media Library list page.
 *
 * @since X.X.X
 *
 * @global w3tcData Localized data.
 */

(function( $ ) {
	var isCheckingItems = false,
		$optimizeLinks = $( 'a.w3tc-optimize' ),
		$unoptimizeLinks = $( '.w3tc-revert > a' ),
		$optimizeAllButton = $( 'th.w3tc-optimager-all' ).parent().find( 'td button' ),
		$revertAllButton = $( 'th.w3tc-optimager-revertall' ).parent().find( 'td button' ),
		$refreshStatsButton = $( 'input#w3tc-optimager-refresh.button' );

	/* On page load. */

	// Start checking items that are in the processing status.
	startCheckItems();

	// Disable ineligible buttons.
	toggleButtons();

	/* Events. */

	// Clicked optimize link.
	$optimizeLinks.on( 'click', optimizeItem );

	// Clicked revert link.
	$unoptimizeLinks.on( 'click', revertItem );

	// Clicked optimize all images button.
	$optimizeAllButton.on( 'click', optimizeItems );

	// Clicked revert all optimized images button.
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
		if ( $optimizeAllButton.length && $( '#w3tc-optimager-unoptimized' ).text() < 1 ) {
			$optimizeAllButton.prop( 'disabled', true ).prop( 'aria-disabled', 'true' ); // Disable button.
		}

		if ( $revertAllButton.length && $( '#w3tc-optimager-optimized' ).text() < 1 ) {
			$revertAllButton.prop( 'disabled', true ).prop( 'aria-disabled', 'true' ); // Disable button.
		}
	}

	/**
	 * Start checking items that are in the processing status.
	 *
	 * @since X.X.X
	 *
	 * @see checkNotOptimized()
	 * @see checkItemsProcessing()
	 */
	function startCheckItems() {
		if ( isCheckingItems ) {
			return;
		}

		isCheckingItems= true;

		// If any processed images were not optimized, then print a notice to check settings.
		checkNotOptimized();

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
		$optimizeLinks.each( checkItemProcessing );
	};


	/**
	 * Callback: Check processing item.
	 *
	 * @since X.X.X
	 *
	 * @see checkNotOptimized()
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
					action: 'w3tc_optimager_postmeta',
					post_id: $this.data( 'post-id' )
				}
			})
				.done( function( response ) {
					var infoClass;

					// Remove any previous optimization information and the revert link.
					$itemTd.find(
						'.w3tc-optimized-reduced, .w3tc-optimized-increased, .w3tc-notoptimized, .w3tc-revert'
					).remove();

					// Add optimization information.
					if ( 'notoptimized' !== response.data.status && response.data.download && response.data.download["\u0000*\u0000data"] ) {
						infoClass = response.data.download["\u0000*\u0000data"]['x-filesize-reduced'] > 0 ?
							'w3tc-optimized-increased' : 'w3tc-optimized-reduced';

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

					if ( 'optimized' === response.data.status ) {
						$this
							.text( w3tcData.lang.optimized )
							.data( 'status', 'optimized' )
							.prop( 'aria-disabled', false )
							.closest( 'span' ).removeClass( 'w3tc-disabled' );

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
					} else if ( 'notoptimized' === response.data.status ) {
						$this
							.text( w3tcData.lang.optimize )
							.data( 'status', 'notoptimized' )
							.prop( 'aria-disabled', false )
							.closest( 'span' ).removeClass( 'w3tc-disabled' );

						$itemTd.prepend(
							'<div class="w3tc-notoptimized">' +
							w3tcData.lang.notoptimized +
							'</div>'
						);
					}
				})
				.fail( function() {
					$this
						.text( w3tcData.lang.error )
						.data( 'status', null );
					$itemTd.find( '.w3tc-optimager-error' ).remove();
					$itemTd.append(
						'<div class="notice notice-error inline w3tc-optimager-error">' +
						w3tcData.lang.ajaxFail +
						'</div>'
					);
				});
		}

		// If any processed images were not optimized, then print a notice to check settings.
		checkNotOptimized();
	}

	/**
	 * Check for images not optimized and print a notice if nay are found.
	 *
	 * @since X.X.X
	 */
	function checkNotOptimized() {
		if ( 'lossy' !== w3tcData.settings.compression && ! $( '#w3tc-notoptimized-notice' ).length && $( '.w3tc-notoptimized' ).length ) {
			$( '#wpbody-content' ).prepend(
				'<div id="w3tc-notoptimized-notice" class="notice notice-warning is-dismissible"><p><span class="w3tc-optimize"></span> Image Service</p><p>' +
				w3tcData.lang.notoptimizedNotice +
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
		var $countsTable = $( 'table#w3tc-optimager-counts' );

		// Update the refresh button text.
		$refreshStatsButton
			.val( w3tcData.lang.refreshing )
			.prop( 'disabled', true )
			.prop( 'aria-disabled', 'true' );

		// Remove any error notices.
		$countsTable.find( '.w3tc-optimager-error' ).remove();

		$.ajax({
			method: 'POST',
			url: ajaxurl,
			data: {
				_wpnonce: w3tcData.nonces.submit,
				action: 'w3tc_optimager_counts'
			}
		})
			.done( function( response ) {
				if ( response.data && response.data.hasOwnProperty( 'total' ) ) {
					[ 'total', 'optimized', 'sending', 'processing', 'notoptimized', 'unoptimized' ].forEach( function( className ) {
						$count = $countsTable.find( '#w3tc-optimager-' + className );
						if ( parseInt( $count.text() ) !== response.data[ className ] ) {
							$count.text( response.data[ className ] ).closest( 'tr' ).addClass( 'w3tc-highlight' );

							className += 'bytes';
							$size = $countsTable.find( '#w3tc-optimager-' + className );
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
					'<div class="notice notice-error inline w3tc-optimager-error">' +
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
	 * Event callback: Optimize an item.
	 *
	 * @since X.X.X
	 *
	 * @param event e Event object.
	 */
	 function optimizeItem() {
		var $this = $( this ),
			$itemTd = $this.closest( 'td' );

		$this
			.text( w3tcData.lang.sending )
			.prop( 'aria-disabled' , 'true')
			.closest( 'span' ).addClass( 'w3tc-disabled' );

		// Remove any previous optimization information, revert link, and error notices.
		$itemTd.find( '.w3tc-optimized-reduced, .w3tc-optimized-increased, .w3tc-revert, .w3tc-optimager-error' ).remove();

		$.ajax({
			method: 'POST',
			url: ajaxurl,
			data: {
				_wpnonce: w3tcData.nonces.submit,
				action: 'w3tc_optimager_submit',
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
						'<div class="notice notice-error inline w3tc-optimager-error">' +
						w3tcData.lang.apiError +
						'</div>'
					);
				}
			})
			.fail( function() {
				$this
					.val( w3tcData.lang.error )
					.data( 'status', 'error' );
				$itemTd.append(
					'<div class="notice notice-error inline w3tc-optimager-error">' +
					w3tcData.lang.ajaxFail +
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
			$optimizeLink = $itemTd.find( 'a.w3tc-optimize' );

		$this
			.text( w3tcData.lang.reverting )
			.prop( 'aria-disabled', 'true' )
			.closest( 'span' ).addClass( 'w3tc-disabled' );

		$optimizeLink
			.prop( 'aria-disabled', 'true' )
			.closest( 'span' ).addClass( 'w3tc-disabled' );

		// Remove error notices.
		$itemTd.find( '.w3tc-optimager-error' ).remove();

		$.ajax({
			method: 'POST',
			url: ajaxurl,
			data: {
				_wpnonce: w3tcData.nonces.revert,
				action: 'w3tc_optimager_revert',
				post_id: $optimizeLink.data( 'post-id' )
			}
		})
			.done( function( response ) {
				if ( response.success ) {
					$this.closest( 'span' ).remove(); // Remove the revert link.
					$itemTd.find( 'div' ).remove(); // Remove optimization info.

					$optimizeLink
						.text( w3tcData.lang.optimize )
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
						'<div class="notice notice-error inline w3tc-optimager-error">' +
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
					'<div class="notice notice-error inline w3tc-optimager-error">' +
					w3tcData.lang.ajaxFail +
					'</div>'
				);
			});
	};

	/**
	 * Event callback: Optimize all items.
	 *
	 * @since X.X.X
	 *
	 * @see refreshStats()
	 */
	 function optimizeItems() {
		var $this = $( this ),
			$parent = $this.parent();

		$this
			.text( w3tcData.lang.sending )
			.prop( 'disabled', true )
			.prop( 'aria-disabled', 'true' );

		// Remove error notices.
		$parent.find( '.w3tc-optimager-error' ).remove();

		$.ajax({
			method: 'POST',
			url: ajaxurl,
			data: {
				_wpnonce: w3tcData.nonces.submit,
				action: 'w3tc_optimager_all'
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
						'<div class="notice notice-error inline w3tc-optimager-error">' +
						w3tcData.lang.apiError +
						'</div>'
					);
				}
			})
			.fail( function() {
				$this.text( w3tcData.lang.error );
				$parent.append(
					'<div class="notice notice-error inline w3tc-optimager-error">' +
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
				action: 'w3tc_optimager_revertall'
			}
		})
			.done( function( response ) {
				if ( response.success ) {
					$this.text( w3tcData.lang.reverted );
					$optimizeAllButton
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
							'<div class="notice notice-error inline w3tc-optimager-error">' +
							w3tcData.lang.apiError +
							'</div>'
						);
				}
			})
			.fail( function() {
				$this
					.text( w3tcData.lang.error )
					.parent().append(
						'<div class="notice notice-error inline w3tc-optimager-error">' +
						w3tcData.lang.ajaxFail +
						'</div>'
					);
			});
	}
})( jQuery );
