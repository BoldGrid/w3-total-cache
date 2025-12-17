/**
 * File: wizard.js
 *
 * JavaScript for the wizard.
 *
 * @since 2.0.0
 */

 jQuery(function() {
	var $container = jQuery( '#w3tc-wizard-container'),
		$skipLink = $container.find( '#w3tc-wizard-skip-link '),
		$skipButton = $container.find( '#w3tc-wizard-skip '),
		$nextButton = $container.find( '#w3tc-wizard-next '),
		$previousButton = $container.find( '#w3tc-wizard-previous '),
		stepToSlideMap = {
			welcome: 'welcome',
			pgcache: 'pc1',
			dbcache: 'dbc1',
			objectcache: 'oc1',
			browsercache: 'bc1',
			imageservice: 'io1',
			lazyload: 'll1',
			more: 'complete'
		};

	$skipLink.on( 'click', skipFunction );
	$skipButton.on( 'click', skipFunction );

	jQuery( window ).on( 'beforeunload', function() {
		var $previousSlide = $container.find( '.w3tc-wizard-slides:visible' ).prev( '.w3tc-wizard-slides' );
		if ( $previousSlide.length ) {
			return W3TC_Wizard.beforeunloadText;
		}
	});

	// Listen for clicks to go to the W3TC Dashboard.
	$container.find( '#w3tc-wizard-dashboard' ).on( 'click', function () {
		jQuery( window ).off( 'beforeunload' );
		document.location = W3TC_SetupGuide.dashboardUrl;
	});

	/**
	 * Show the requested slide and adjust navigation state.
	 *
	 * @since 2.0.0
	 *
	 * @param object $targetSlide Slide to display.
	 */
	function showSlide( $targetSlide ) {
		if ( ! $targetSlide.length ) {
			return;
		}

		var $currentSlide = $container.find( '.w3tc-wizard-slides:visible' );

		if ( $currentSlide.length && 'function' === typeof w3tc_mark_step_complete_on_leave ) {
			w3tc_mark_step_complete_on_leave( $currentSlide.prop( 'id' ) );
		}

		$currentSlide.hide();
		$targetSlide.show();

		var hasPrevious = !! $targetSlide.prev( '.w3tc-wizard-slides' ).length,
			hasNext = !! $targetSlide.next( '.w3tc-wizard-slides' ).length;

		$previousButton.closest( 'span' ).toggle( hasPrevious );
		$skipButton.closest( 'span' ).toggle( ! hasPrevious );

		$container.find( '#w3tc-wizard-dashboard-span' ).toggle( ! hasNext );
		$nextButton.closest( 'span' ).toggle( hasNext );

		if ( 'function' === typeof w3tc_wizard_actions ) {
			w3tc_wizard_actions( $targetSlide, $currentSlide );
		}
	}

	/**
	 * Process the skip action.
	 *
	 * Saves and option to mark the wizard completed.
	 *
	 * @since 2.0.0
	 */
	function skipFunction() {
		var $this = jQuery( this ),
			nodeName = $this.prop('nodeName'),
			page = location.href.replace(/^.+page=/, '' );

		jQuery( window ).off( 'beforeunload' );

		if ( 'BUTTON' === nodeName ) {
			$this
				.prop( 'disabled', true )
				.css( 'color', '#000' )
				.text( 'Skipping...' );
		}

		// GA.
		if ( window.w3tc_ga ) {
			w3tc_ga(
				'event',
				'button',
				{
					eventCategory: page,
					eventLabel: 'skip'
				}
			);
		}

		jQuery.ajax({
			method: 'POST',
			url: ajaxurl,
			data: {
				_wpnonce: $container.find( '[name="_wpnonce"]' ).val(),
				action: "w3tc_wizard_skip"
			}
		})
			.done(function( response ) {
				if ( 'BUTTON' === nodeName ) {
					$this.text( 'Redirecting...' );
				}

				window.location.replace( location.href.replace(/page=.+$/, 'page=w3tc_dashboard') );
			})
			.fail(function() {
				if ( 'BUTTON' === nodeName ) {
					$this.text( 'Error with Ajax; reloading page...' );
				}

				location.reload();
			});
	};

	$previousButton.on( 'click', function() {
		var $currentSlide = $container.find( '.w3tc-wizard-slides:visible' ),
			$previousSlide = $currentSlide.prev( '.w3tc-wizard-slides' );

		showSlide( $previousSlide );
	});

	$nextButton.on( 'click', function() {
		var $currentSlide = $container.find( '.w3tc-wizard-slides:visible' ),
			$nextSlide = $currentSlide.next( '.w3tc-wizard-slides' );

		showSlide( $nextSlide );
	});

	$container.find( '#w3tc-options-menu' ).on( 'click', 'li', function() {
		var rawId = jQuery( this ).attr( 'id' );
		var stepId = rawId.replace( /^w3tc-wizard-step-/, '' ).replace( /-text$/, '' );
		var slideId = stepToSlideMap[ stepId ];
		var $targetSlide = slideId ? $container.find( '#w3tc-wizard-slide-' + slideId ) : jQuery();

		showSlide( $targetSlide );
	});
});
