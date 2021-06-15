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
		$buttons = $( 'input.button.w3tc-optimize' );

	/**
	 * Check processing items.
	 *
	 * @since X.X.X
	 */
	function checkItemsProcessing() {
		$buttons.each( function() {
			var $this = $( this );

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
							$this.val( w3tcData.lang.optimized );
							$this.data( 'status', 'optimized' );
							$this.prop( 'disabled', false );
							$this.closest( 'td' ).find( 'span' ).addClass( 'w3tc-optimized' );
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

	$buttons.on( 'click', function( e ) {
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
					$this.parent().append(
						'<div class="notice notice-success inline">' +
						w3tcData.lang.jobid +
						response.data.job_id +
						'</div>'
					);
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
})( jQuery );
