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
		$buttonsUnoptimize = $( 'input.button.w3tc-unoptimize' );

	/**
	 * Check processing items.
	 *
	 * @since X.X.X
	 */
	function checkItemsProcessing() {
		$buttonsOptimize.each( function() {
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
						$itemTd.find( 'input.w3tc-unoptimize' ).remove();

						if ( 'optimized' === response.data.status ) {
							$this.val( w3tcData.lang.optimized ); // Mark as optimized.
							$this.data( 'status', 'optimized' ); // Update status.
							$this.prop( 'disabled', false ); // Enable button.
							$itemTd.find( 'span' ).addClass( 'w3tc-optimized' ); // Mark icon as optimized.

							// Add revert button, if not already present.
							if ( ! $itemTd.find( '.w3tc-unoptimize' ).length ) {
								$itemTd.append(
									'<input type="submit" class="button w3tc-unoptimize" value="' +
									w3tcData.lang.revert +
									'" \>'
								);

								// Update global unoptimize buttons.
								$buttonsUnoptimize = $( 'input.button.w3tc-unoptimize' );
								$buttonsUnoptimize.unbind().on( 'click', unoptimize );
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
					.fail( function( jqXHR ) {
						$this.val( w3tcData.lang.error );
						$itemTd.find( 'span' ).addClass( 'w3tc-optimize-error' ); // Mark icon as error.
					});
			}
		});
	};

	/**
	 * Start checking processing items.
	 *
	 * @since X.X.X
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

	// Trigger checking items.
	startCheckItems();

	$buttonsOptimize.on( 'click', function( e ) {
		var $this = $( this ),
			$itemTd = $this.closest( 'td' );

		e.preventDefault();

		$this.prop( 'disabled', true );
		$this.val( w3tcData.lang.sending );

		// Remove any previous optimization information.
		$itemTd.find( '.w3tc-optimized-reduced' ).remove();
		$itemTd.find( '.w3tc-optimized-increased' ).remove();

		// Remove the revert button.
		$itemTd.find( 'input.w3tc-unoptimize' ).remove();

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
				} else if ( response.data.error ) {
					$this.val( w3tcData.lang.error );
					$this.parent().append(
						'<div class="notice notice-error inline">' +
						response.data.error +
						'</div>'
					);
					$this.data( 'status', 'error' );
				} else {
					$this.val( w3tcData.lang.error );
					$this.data( 'status', 'error' );
				}
			})
			.fail( function( jqXHR ) {
				$this.val( w3tcData.lang.error );

				if ( 'responseJSON' in jqXHR && 'data' in jqXHR.responseJSON && 'error' in jqXHR.responseJSON.data ) {
					$this.parent().append(
						'<div class="notice notice-error inline">' +
						jqXHR.responseJSON.data.error +
						'</div>'
					);
				}
			});

		return false;
	});

	/**
	 * Unoptimize/revert.
	 *
	 * @since X.X.X
	 *
	 * @param {*} e Event.
	 * @returns false
	 */
	function unoptimize( e ) {
		var $this = $( this ),
			$itemTd = $this.closest( 'td' ),
			$optimizeButton = $itemTd.find( 'input.w3tc-optimize' );

		e.preventDefault();

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
				} else if ( response.data.error ) {
					$this.val( w3tcData.lang.error );
					$this.parent().append(
						'<div class="notice notice-error inline">' +
						response.data.error +
						'</div>'
					);
					$this.data( 'status', 'error' );
				} else {
					$this.val( w3tcData.lang.error );
					$this.data( 'status', 'error' );
				}
			})
			.fail( function( jqXHR ) {
				$this.val( w3tcData.lang.error );

				if ( 'responseJSON' in jqXHR && 'data' in jqXHR.responseJSON && 'error' in jqXHR.responseJSON.data ) {
					$this.parent().append(
						'<div class="notice notice-error inline">' +
						jqXHR.responseJSON.data.error +
						'</div>'
					);
				}
			});

		return false;
	};

	$buttonsUnoptimize.on( 'click', unoptimize );

	// Setting controls: Compression.
	$( '[name="w3tc_optimager_compression"]' ).on( 'click', function() {
		var $this = $( this ),
			value = $this.attr( 'value' );

		// Abort if the value is not changing.
		if ( value === currentCompression ) {
			return false;
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
					return false;
				}
			})
			.fail( function( jqXHR ) {
				// Ajax failure.
				$this.closest( 'div' ).prepend( '<span id="w3tc-controls-result" class="dashicons dashicons-no"></span>' );
				return false;
			});
	});

	// Optimize all images.
	$( 'th.w3tc-optimager-all' ).parent().find( 'td button' ).on( 'click', function( e ) {
		var $this = $( this );

		e.preventDefault();

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
				} else if ( response.data.error ) {
					$this.text( w3tcData.lang.error );
					$this.parent().append(
						'<div class="notice notice-error inline">' +
						response.data.error +
						'</div>'
					);
				} else {
					$this.text( w3tcData.lang.error );
				}
			})
			.fail( function( jqXHR ) {
				$this.text( w3tcData.lang.error );

				if ( 'responseJSON' in jqXHR && 'data' in jqXHR.responseJSON && 'error' in jqXHR.responseJSON.data ) {
					$this.parent().append(
						'<div class="notice notice-error inline">' +
						jqXHR.responseJSON.data.error +
						'</div>'
					);
				}
			});

		return false;
	});

	// Revert all optimized images.
	$( 'th.w3tc-optimager-revertall' ).parent().find( 'td button' ).on( 'click', function( e ) {
		var $this = $( this );

		e.preventDefault();

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
				} else if ( response.data.error ) {
					$this.text( w3tcData.lang.error );
					$this.parent().append(
						'<div class="notice notice-error inline">' +
						response.data.error +
						'</div>'
					);
				} else {
					$this.text( w3tcData.lang.error );
				}
			})
			.fail( function( jqXHR ) {
				$this.text( w3tcData.lang.error );

				if ( 'responseJSON' in jqXHR && 'data' in jqXHR.responseJSON && 'error' in jqXHR.responseJSON.data ) {
					$this.parent().append(
						'<div class="notice notice-error inline">' +
						jqXHR.responseJSON.data.error +
						'</div>'
					);
				}
			});

		return false;
	});
})( jQuery );
