jQuery( document ).ready( function( $ ) {
	window.tb_position = function() {
		var width = $( window ).width(),
			H = $( window ).height() - ( ( 792 < width ) ? 60 : 20 ),
			W = ( 792 < width ) ? 772 : width - 20;

		var tbWindow = $( '#TB_window' );

		if ( tbWindow.length ) {
			tbWindow.width( W ).height( H );
			$( '#TB_iframeContent' ).width( W ).height( H );
			tbWindow.css({
				'margin-left': '-' + parseInt( ( W / 2 ), 10 ) + 'px'
			});
			if ( typeof document.body.style.maxWidth !== 'undefined' ) {
				tbWindow.css({
					'top': '30px',
					'margin-top': '0'
				});
			}
		}

		return $( 'a.thickbox' ).each( function() {
			var href = $( this ).attr( 'href' );
			if ( ! href ) {
				return;
			}
			href = href.replace( /&width=[0-9]+/g, '' );
			href = href.replace( /&height=[0-9]+/g, '' );
			$(this).attr( 'href', href + '&width=' + W + '&height=' + ( H ) );
		});
	};

	$( window ).resize( function() {
		tb_position();
	});

	$( window ).on( 'message', function( event ) {
		var originalEvent  = event.originalEvent,
			expectedOrigin = document.location.protocol + '//' + document.location.hostname,
			message;

		if ( originalEvent.origin !== expectedOrigin ) {
			return;
		}

		try {
			message = $.parseJSON( originalEvent.data );
		} catch ( e ) {
			return;
		}

		if ( ! message || 'undefined' === typeof message.action ) {
			return;
		}

		if (message.action == 'install-plugin') {
			window.location = $('#w3tc-boldgrid-install').attr('href');
		}
	});
});
