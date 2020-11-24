/**
 * File: wizard.js
 *
 * JavaScript for the wizard.
 *
 * @since 2.0.0
 */

 jQuery(function() {
	var $container = jQuery( '#w3tc-wizard-container'),
		$skipButton = $container.find( '#w3tc-wizard-skip '),
		$nextButton = $container.find( '#w3tc-wizard-next '),
		$previousButton = $container.find( '#w3tc-wizard-previous ');

	jQuery( '.button-buy-plugin' ).parent().remove();

	$skipButton.click(function() {
		var $this = jQuery( this );

		$this.attr( 'disabled', 'disabled' )
			.css( 'color', '#000' )
			.text( 'Skipping...' );

		jQuery.ajax({
			method: 'POST',
			url: ajaxurl,
			data: {
				_wpnonce: $container.find( '[name="_wpnonce"]' ).val(),
				action: "w3tc_wizard_skip"
			}
		})
			.done(function( response ) {
				$this.text( 'Redirecting...' );
				window.location.replace( location.href.replace(/page=.+$/, 'page=w3tc_dashboard') );
			})
			.fail(function() {
				$this.text( 'Error with Ajax; reloading page...' );
				location.reload();
			});
	});

	$previousButton.click(function() {
		var $currentSlide = $container.find( '.w3tc-wizard-slides:visible' ),
			$previousSlide = $currentSlide.prev( '.w3tc-wizard-slides' );

		if ( $previousSlide.length ) {
			$currentSlide.hide();
			$previousSlide.show();
			$nextButton.removeProp( 'disabled' );
		}

		// Hide the previous button and show the skip button on the first slide.
		if ( 0 === $previousSlide.prev( '.w3tc-wizard-slides' ).length ) {
			$previousButton.closest( 'span' ).hide();
			$skipButton.closest( 'span' ).show();
		}

		w3tc_wizard_actions( $previousSlide );
	});

	$nextButton.click(function() {
		var $currentSlide = $container.find( '.w3tc-wizard-slides:visible' ),
			$nextSlide = $currentSlide.next( '.w3tc-wizard-slides' );

		if ( $skipButton.is( ':visible' ) ) {
			$skipButton.closest( 'span' ).hide();
			$previousButton.closest( 'span' ).show();
		}

		if ( $nextSlide.length ) {
			$currentSlide.hide();
			$nextSlide.show();
		}

		// Disable the next button on the last slide.
		if ( 0 === $nextSlide.next( '.w3tc-wizard-slides' ).length ) {
			jQuery( this ).prop( 'disabled', 'disabled' );
		}

		w3tc_wizard_actions( $nextSlide );
	});
});
