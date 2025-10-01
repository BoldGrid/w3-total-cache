/*!
 * File: Cdnfsd_TotalCdn_Page_View.js
 *
 * @since X.X.X
 *
 * @package W3TC
 */

jQuery(
	function ( $ ) {
		'use strict';

		var config = window.w3tcCdnTotalCdnFsd || {};
		var labels = config.labels || {};
		var symbols = {
			pass: 'dashicons-saved',
			fail: 'dashicons-no-alt',
			untested: 'dashicons-minus'
		};
		var srTemplate = labels.statusTemplate || '%1$s status: %2$s';

		function collectTestElements() {
			var elements = {};

			$( '.w3tc-fsd-status-item' ).each(
				function () {
					var $item = $( this );
					var testId = $item.data( 'test-id' );

					if ( testId != null ) {
						elements[ testId ] = $item;
					}
				}
			);

			return elements;
		}

		function setStatus( $item, status ) {
			var normalizedStatus = symbols.hasOwnProperty( status ) ? status : 'untested';
			var srLabel          = labels[ normalizedStatus ] || normalizedStatus;
			var title            = $.trim( $item.find( '.w3tc-fsd-status-title' ).text() );
			var srText = srTemplate.replace( '%1$s', title ).replace( '%2$s', srLabel );

			$item.removeClass(
				'w3tc-fsd-status-pass w3tc-fsd-status-fail w3tc-fsd-status-untested'
			).addClass( 'w3tc-fsd-status-' + normalizedStatus );

			$item.find('.w3tc-fsd-status-symbol').removeClass(
				function( index, className ) {
					return ( className.match( /(^|\s)dashicons-\S+/g ) || [] ).join(' ');
			  	}
			).addClass( symbols[ normalizedStatus ] || 'dashicons-minus' );
			
			$item.find('.screen-reader-text').text(srText);
		}

		function resetStatuses( testElements ) {
			$.each(
				testElements,
				function ( id, $item ) {
					setStatus( $item, 'untested' );
				}
			);
		}

		function renderNotices( notices, $container ) {
			$container.empty();

			if ( ! Array.isArray( notices ) || ! notices.length ) {
				return;
			}

			notices.forEach(
				function ( notice ) {
					if ( ! notice || ! notice.message ) {
						return;
					}

					var classes = ['notice', 'w3tc-fsd-status-notice'];

					switch ( notice.type ) {
						case 'success':
							classes.push( 'notice-success', 'w3tc-fsd-status-notice-success' );
							break;
						case 'error':
							classes.push( 'notice-error', 'w3tc-fsd-status-notice-error' );
							break;
						case 'warning':
							classes.push( 'notice-warning', 'w3tc-fsd-status-notice-warning' );
							break;
						default:
							classes.push( 'notice-info', 'w3tc-fsd-status-notice-info' );
					}

					var $notice = $(
						'<div/>',
						{
							'class': classes.join(' ')
						}
					);

					$notice.append( $( '<p/>' ).text( notice.message ) );
					$container.append( $notice );
				}
			);
		}

		function showError( $container, message ) {
			var errorMessage = message || ( config.errorMessage || '' );

			if ( ! errorMessage ) {
				return;
			}

			var $notice = $(
				'<div/>',
				{
					'class': 'notice notice-error w3tc-fsd-status-notice'
				}
			).append( $( '<p/>' ).text( errorMessage ) );

			$container.empty().append( $notice );
		}

		$('body').on(
			'click',
			'.w3tc-fsd-status-run',
			function ( event ) {
				var $button = $( this );

				if ( $button.is( ':disabled' ) ) {
					return;
				}

				var $container   = $button.closest( 'tr' );
				var $spinner     = $button.siblings( '.spinner' );
				var $notices     = $container.find( '.w3tc-fsd-status-notices' ).first();
				var defaultText  = $button.data( 'default-text' ) || ( config.button && config.button.default ) || $button.text();
				var testingText  = $button.data( 'testing-text' ) || ( config.button && config.button.testing ) || defaultText;
				var testElements = collectTestElements();
				var requestNonce = '';

				if ( typeof window.w3tc_nonce !== 'undefined' ) {
					if ( Array.isArray( window.w3tc_nonce ) ) {
						requestNonce = window.w3tc_nonce.length ? window.w3tc_nonce[0] : '';
					} else if ( typeof window.w3tc_nonce === 'string' ) {
						requestNonce = window.w3tc_nonce;
					}
				}

				resetStatuses( testElements );
				renderNotices( [], $notices );

				$button.prop( 'disabled', true ).text( testingText );
				$spinner.addClass( 'is-active' );

				$.ajax(
					{
						type: 'POST',
						url: typeof ajaxurl !== 'undefined' ? ajaxurl : '',
						dataType: 'json',
						data: {
							action: 'w3tc_ajax',
							_wpnonce: requestNonce,
							w3tc_action: config.ajaxAction || '',
							fsd_nonce: config.nonce || ''
						}
					}
				).done(
					function ( response ) {
						if ( ! response || ! response.success || ! response.data ) {
							showError( $notices, config.errorMessage );
							return;
						}

						var data = response.data;

						renderNotices( data.notices, $notices );

						if ( data.test_results ) {
							$.each(
								data.test_results,
								function ( testId, status ) {
									if ( ! testElements.hasOwnProperty( testId ) ) {
										return;
									}

									setStatus( testElements[ testId ], status || 'untested' );
								}
							);
						}
					}
				).fail(
					function ( response) {
						showError( $notices, config.errorMessage );
					}
				).always(
					function () {
						$spinner.removeClass( 'is-active' );
						$button.prop( 'disabled', false ).text( defaultText );
					}
				);
			}
		);
	}
);
