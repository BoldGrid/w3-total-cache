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
	var checkitemInterval,
		isCheckingItems = false,
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
						if ( 'optimized' === response.data.status ) {
							$this.val( w3tcData.lang.optimized ); // Mark as optimized.
							$this.data( 'status', 'optimized' ); // Update status.
							$this.prop( 'disabled', false ); // Enable button.
							$itemTd.find( 'span' ).addClass( 'w3tc-optimized' ); // Mark icon as optimized.

							// Add revert button.
							$itemTd.append(
								'&nbsp; <input type="submit" class="button w3tc-unoptimize" value="' +
								w3tcData.lang.revert +
								'" \>'
							);

							// Update global unoptimize buttons.
							$buttonsUnoptimize = $( 'input.button.w3tc-unoptimize' );
							$buttonsUnoptimize.unbind().on( 'click', unoptimize );
						}
					})
					.fail( function( jqXHR ) {
						$this.val( w3tcData.lang.error );
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
		var $this = $( this );

		e.preventDefault();

		$this.prop( 'disabled', true );
		$this.val( w3tcData.lang.sending );

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
					$itemTd.find( 'span' ).removeClass( 'w3tc-optimized' ); // Unmark icon.
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
})( jQuery );
