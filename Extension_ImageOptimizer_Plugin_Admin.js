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
		currentCompression = $( '[name="w3tc_optimager_compression"]:checked' ).attr( 'value' ),
		$buttonsOptimize = $( 'input.button.w3tc-optimize' ),
		$buttonsUnoptimize = $( 'input.button.w3tc-revert' ),
		$optimizeAllButton = $( 'th.w3tc-optimager-all' ).parent().find( 'td button' ),
		$revertAllButton = $( 'th.w3tc-optimager-revertall' ).parent().find( 'td button' ),
		$refreshStatsIcon = $( '#w3tc-optimager-statistics .dashicons-update' );

	/* On page load. */

	// Start checking items that are in the processing status.
	startCheckItems();

	// Disable ineligible buttons.
	toggleButtons();

	/* Events. */

	// Clicked optimize button.
	$buttonsOptimize.on( 'click', optimizeItem );

	// Clicked revert button.
	$buttonsUnoptimize.on( 'click', revertItem );

	// Clicked setting control: Compression.
	$( '[name="w3tc_optimager_compression"]' ).on( 'click', setCompression );

	// Optimize all images.
	$optimizeAllButton.on( 'click', optimizeItems );

	// Revert all optimized images.
	$revertAllButton.on( 'click', revertItems );

	// Refresh statistics when the update icon is clicked.
	$refreshStatsIcon.on( 'click', refreshStats );

	/* Functions. */

	/**
	 * Toggle buttons based on eligibility.
	 *
	 * @since X.X.X
	 */
	function toggleButtons() {
		if ( $optimizeAllButton.length && $( '#w3tc-optimager-unoptimized' ).text() < 1 ) {
			$optimizeAllButton.prop( 'disabled', true );
		}

		if ( $revertAllButton.length && $( '#w3tc-optimager-optimized' ).text() < 1 ) {
			$revertAllButton.prop( 'disabled', true );
		}
	}

	/**
	 * Start checking items that are in the processing status.
	 *
	 * @since X.X.X
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
	 * @since X.X.X
	 *
	 * @see checkItemProcessing()
	 */
	function checkItemsProcessing() {
		$buttonsOptimize.each( checkItemProcessing );
	};


	/**
	 * Callback: Check processing item.
	 *
	 * @since X.X.X
	 */
	 function checkItemProcessing() {
		var $this = $( this ),
			$itemTd = $this.closest( 'td' );

		// If marked as processing, then check for status change an update status on screen.
		if ( 'processing' === $this.data( 'status' ) ) {
			$itemTd.find( 'span' ).removeClass( 'w3tc-optimized w3tc-notoptimized w3tc-optimize-error' ); // Unmark icon.

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

					// Remove any previous optimization information.
					$itemTd.find( '.w3tc-optimized-reduced' ).remove();
					$itemTd.find( '.w3tc-optimized-increased' ).remove();

					// Remove the revert button.
					$itemTd.find( 'input.w3tc-revert' ).remove();

					if ( 'optimized' === response.data.status ) {
						$this.val( w3tcData.lang.optimized ); // Mark as optimized.
						$this.data( 'status', 'optimized' ); // Update status.
						$this.prop( 'disabled', false ); // Enable button.
						$itemTd.find( 'span' ).addClass( 'w3tc-optimized' ); // Mark icon as optimized.

						// Add revert button, if not already present.
						if ( ! $itemTd.find( '.w3tc-revert' ).length ) {
							$itemTd.append(
								'<input type="submit" class="button w3tc-revert" value="' +
								w3tcData.lang.revert +
								'" \>'
							);

							// Update global revert buttons.
							$buttonsUnoptimize = $( 'input.button.w3tc-revert' );
							$buttonsUnoptimize.unbind().on( 'click', revertItem );
						}
					} else if ( 'notoptimized' === response.data.status ) {
						$this.val( w3tcData.lang.optimize ); // Mark ready to optimize.
						$this.data( 'status', 'notoptimized' ); // Update status.
						$this.prop( 'disabled', false ); // Enable button.
						$itemTd.find( 'span' ).addClass( 'w3tc-notoptimized' ); // Mark icon as not optimized.

						$itemTd.append(
							'<div class="w3tc-optimized-increased">' +
							response.data.download["\u0000*\u0000data"]['x-filesize-out-percent'] +
							' (' +
							w3tcData.lang.notchanged +
							': ' +
							response.data.download["\u0000*\u0000data"]['x-filesize-reduced'] +
							')<br />' +
							w3tcData.lang.notoptimized +
							'</div>'
						);
					}

					// Add optimization information.
					if ( 'notoptimized' !== response.data.status && response.data.download && response.data.download["\u0000*\u0000data"] ) {
						infoClass = response.data.download["\u0000*\u0000data"]['x-filesize-reduced'] > 0 ?
							'w3tc-optimized-increased' : 'w3tc-optimized-reduced';

						$itemTd.append(
							'<div class="' +
							infoClass +
							'">' +
							response.data.download["\u0000*\u0000data"]['x-filesize-out-percent'] +
							' (' +
							w3tcData.lang.changed +
							': ' +
							response.data.download["\u0000*\u0000data"]['x-filesize-reduced'] +
							')</div>'
						);
					}
				})
				.fail( function() {
					$this.val( w3tcData.lang.error );
					$itemTd.find( 'span' ).addClass( 'w3tc-optimize-error' ); // Mark icon as error.
					$itemTd.find( '.w3tc-optimager-error' ).remove();
					$itemTd.append(
						'<div class="notice notice-error inline w3tc-optimager-error">' +
						w3tcData.lang.AjaxFail +
						'</div>'
					);
					$this.data( 'status', null );
				});
		}
	}

	/**
	 * Refresh statistics/counts.
	 *
	 * @since X.X.X
	 */
	function refreshStats() {
		var $countsTable = $( 'table#w3tc-optimager-counts' );

		// Spin the update icon.
		$refreshStatsIcon.addClass( 'w3tc-rotating' );

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
				var $total, $optimized, $sending, $processing, $unoptimized;

				if ( response.data && response.data.hasOwnProperty( 'total' ) ) {
					$total = $countsTable.find( '#w3tc-optimager-total' );
					$optimized = $countsTable.find( '#w3tc-optimager-optimized' );
					$sending = $countsTable.find( '#w3tc-optimager-sending' );
					$processing = $countsTable.find( '#w3tc-optimager-processing' );
					$unoptimized = $countsTable.find( '#w3tc-optimager-unoptimized' );
					$totalBytes = $countsTable.find( '#w3tc-optimager-totalbytes' );
					$optimizedBytes = $countsTable.find( '#w3tc-optimager-optimizedbytes' );

					if ( parseInt( $total.text() ) !== response.data.total ) {
						$total.text( response.data.total ).addClass( 'w3tc-highlight' ).removeClass( 'w3tc-highlight' );
					}

					if ( parseInt( $optimized.text() ) !== response.data.optimized ) {
						$optimized.text( response.data.optimized ).addClass( 'w3tc-highlight' ).removeClass( 'w3tc-highlight' );
					}

					if ( parseInt( $sending.text() ) !== response.data.sending ) {
						$sending.text( response.data.sending ).addClass( 'w3tc-highlight' ).removeClass( 'w3tc-highlight' );
					}

					if ( parseInt( $processing.text() ) !== response.data.processing ) {
						$processing.text( response.data.processing ).addClass( 'w3tc-highlight' ).removeClass( 'w3tc-highlight' );
					}

					if ( parseInt( $unoptimized.text() ) !== response.data.unoptimized ) {
						$unoptimized.text( response.data.unoptimized ).addClass( 'w3tc-highlight' ).removeClass( 'w3tc-highlight' );
					}

					if ( parseInt( $totalBytes.text() ) !== response.data.total_bytes ) {
						$totalBytes.text( response.data.total_bytes ).addClass( 'w3tc-highlight' ).removeClass( 'w3tc-highlight' );
					}

					if ( parseInt( $optimizedBytes.text() ) !== response.data.optimized_bytes ) {
						$optimizedBytes.text( response.data.optimized_bytes ).addClass( 'w3tc-highlight' ).removeClass( 'w3tc-highlight' );
					}
				}

				// Stop spinning the update icon.
				$refreshStatsIcon.removeClass( 'w3tc-rotating' );
			})
			.fail( function() {
				$countsTable.append(
					'<div class="notice notice-error inline w3tc-optimager-error">' +
					w3tcData.lang.AjaxFail +
					'</div>'
				);

				// Stop spinning the update icon.
				$refreshStatsIcon.removeClass( 'w3tc-rotating' );
			});
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

		$this.prop( 'disabled', true );
		$this.val( w3tcData.lang.sending );

		// Remove any previous optimization information.
		$itemTd.find( '.w3tc-optimized-reduced' ).remove();
		$itemTd.find( '.w3tc-optimized-increased' ).remove();

		// Remove the revert button.
		$itemTd.find( 'input.w3tc-revert' ).remove();

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
					$this.val( w3tcData.lang.processing );
					$this.data( 'status', 'processing' );
					startCheckItems();
				} else if ( response.data && response.data.hasOwnProperty( 'error' ) ) {
					$this.val( w3tcData.lang.error );
					$itemTd.append(
						'<div class="notice notice-error inline">' +
						response.data.error +
						'</div>'
					);
					$this.data( 'status', 'error' );
				} else {
					$this.val( w3tcData.lang.error );
					$itemTd.append(
						'<div class="notice notice-error inline w3tc-optimager-error">' +
						w3tcData.lang.ApiError +
						'</div>'
					);
					$this.data( 'status', 'error' );
				}
			})
			.fail( function() {
				$this.val( w3tcData.lang.error );
				$itemTd.find( 'span' ).addClass( 'w3tc-optimize-error' ); // Mark icon as error.
				$itemTd.append(
					'<div class="notice notice-error inline w3tc-optimager-error">' +
					w3tcData.lang.AjaxFail +
					'</div>'
				);
				$this.data( 'status', 'error' );
			});
	}

	/**
	 * Event callback: Set compression setting.
	 *
	 * @since X.X.X
	 */
	 function setCompression() {
		var $this = $( this ),
			value = $this.attr( 'value' );

		// Abort if the value is not changing.
		if ( value === currentCompression ) {
			return;
		}

		// Clear result indicator.
		$( '#w3tc-controls-result' ).remove();

		// Save the new setting.
		$.ajax({
			method: 'POST',
			url: ajaxurl,
			data: {
				_wpnonce: w3tcData.nonces.control,
				action: 'w3tc_optimager_compression',
				value: value
			}
		})
			.done( function( response ) {
				if ( response.success ) {
					// Success.
					currentCompression = value;
					$this.closest( 'div' ).prepend( '<span id="w3tc-controls-result" class="dashicons dashicons-yes"></span>' );
				} else {
					// Reported failure.
					$this.closest( 'div' ).prepend( '<span id="w3tc-controls-result" class="dashicons dashicons-no"></span>' );
				}
			})
			.fail( function( jqXHR ) {
				// Ajax failure.
				$this.closest( 'div' ).prepend( '<span id="w3tc-controls-result" class="dashicons dashicons-no"></span>' );
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
			$optimizeButton = $itemTd.find( 'input.w3tc-optimize' );

		$this.prop( 'disabled', true );
		$optimizeButton.prop( 'disabled', true );
		$this.val( w3tcData.lang.reverting );

		$.ajax({
			method: 'POST',
			url: ajaxurl,
			data: {
				_wpnonce: w3tcData.nonces.revert,
				action: 'w3tc_optimager_revert',
				post_id: $optimizeButton.data( 'post-id' )
			}
		})
			.done( function( response ) {
				if ( response.success ) {
					$this.remove(); // Remove the revert button.
					$itemTd.find( 'div' ).remove(); // Remove optimization info.
					$itemTd.find( 'span' ).removeClass( 'w3tc-optimized w3tc-notoptimized' ); // Unmark icon.
					$optimizeButton.val( w3tcData.lang.optimize );
					$optimizeButton.prop( 'disabled', false );
					$optimizeButton.data( 'status', null );
				} else if ( response.data && response.data.hasOwnProperty( 'error' ) ) {
					$this.val( w3tcData.lang.error );
					$this.parent().append(
						'<div class="notice notice-error inline">' +
						response.data.error +
						'</div>'
					);
					$this.data( 'status', 'error' );
				} else {
					$this.val( w3tcData.lang.error );
					$itemTd.append(
						'<div class="notice notice-error inline w3tc-optimager-error">' +
						w3tcData.lang.ApiError +
						'</div>'
					);
					$this.data( 'status', 'error' );
				}
			})
			.fail( function() {
				$this.val( w3tcData.lang.error );
				$itemTd.find( 'span' ).addClass( 'w3tc-optimize-error' ); // Mark icon as error.
				$itemTd.append(
					'<div class="notice notice-error inline w3tc-optimager-error">' +
					w3tcData.lang.AjaxFail +
					'</div>'
				);
				$this.data( 'status', 'error' );
			});
	};

	/**
	 * Event callback: Optimize items.
	 *
	 * @since X.X.X
	 *
	 * @see refreshStats()
	 */
	 function optimizeItems() {
		var $this = $( this );

		$this.prop( 'disabled', true );
		$this.text( w3tcData.lang.sending );

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
				} else if ( response.data.error ) {
					$this.text( w3tcData.lang.error );
					$this.parent().append(
						'<div class="notice notice-error inline">' +
						response.data.error +
						'</div>'
					);
				} else {
					$this.text( w3tcData.lang.error );
					$this.parent().append(
						'<div class="notice notice-error inline w3tc-optimager-error">' +
						w3tcData.lang.ApiError +
						'</div>'
					);
				}
			})
			.fail( function() {
				$this.text( w3tcData.lang.error );
				$this.parent().append(
					'<div class="notice notice-error inline w3tc-optimager-error">' +
					w3tcData.lang.AjaxFail +
					'</div>'
				);
			});
	}

	/**
	 * Event callback: Revert items.
	 *
	 * @since X.X.X
	 *
	 * @see refreshStats()
	 */
	 function revertItems() {
		var $this = $( this );

		$this.prop( 'disabled', true );
		$this.text( w3tcData.lang.reverting );

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
					refreshStats();
				} else if ( response.data.error ) {
					$this.text( w3tcData.lang.error );
					$this.parent().append(
						'<div class="notice notice-error inline">' +
						response.data.error +
						'</div>'
					);
				} else {
					$this.text( w3tcData.lang.error );
					$this.parent().append(
						'<div class="notice notice-error inline w3tc-optimager-error">' +
						w3tcData.lang.ApiError +
						'</div>'
					);
				}
			})
			.fail( function() {
				$this.text( w3tcData.lang.error );
				$this.parent().append(
					'<div class="notice notice-error inline w3tc-optimager-error">' +
					w3tcData.lang.AjaxFail +
					'</div>'
				);
			});
	}
})( jQuery );
