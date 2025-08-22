jQuery( function( $ ) {
	var scrollKey = 'w3tc-pagination-scroll';

	var stored = sessionStorage.getItem( scrollKey );
	if ( stored ) {
		var pos = parseInt( stored, 10 );
		if ( ! isNaN( pos ) ) {
			window.scrollTo( 0, pos );
		}
		sessionStorage.removeItem( scrollKey );
	}

	$( document ).on( 'click', '.tablenav-pages a', function() {
		sessionStorage.setItem( scrollKey, $( window ).scrollTop() );
	} );
} );
