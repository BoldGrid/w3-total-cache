function w3tc_show_objectcache_diskpopup( previous ) {
	W3tc_Lightbox.open(
		{
			id: 'w3tc-overlay',
			close: '',
			width: 800,
			height: 300,
			url: ajaxurl + '?action=w3tc_ajax&_wpnonce=' + w3tc_nonce + '&w3tc_action=objectcache_diskpopup',
			callback: function( lightbox ) {
				jQuery( '.btn-primary', lightbox.container ).click(
					function() {
						lightbox.close();
					}
				);
				jQuery( '.btn-secondary, .lightbox-close' ).click(
					function() {
						if ( 'file' === previous ) {
							jQuery( '#objectcache__enabled' ).prop('checked', false);
						}

						jQuery( '#objectcache__engine' ).val( previous );

						lightbox.close();
					}
				);
				lightbox.resize();
			}
		}
	);
}

jQuery(
	function( $ ) {
		$( '#objectcache__enabled' ).click(
			function() {
				var checked = $( this ).is( ':checked' );
				var engine = $( '#objectcache__engine' ).val();
				if ( ! checked ) {
					return;
				}

				if ( 'file' === engine ) {
					w3tc_show_objectcache_diskpopup();
				}
			}
		);
		$( '#objectcache__engine' ).on(
			'focus',
			function () {
				previous = this.value;
			}
		).change(
			function() {
				var checked = $( objectcache__enabled ).is( ':checked' );
				var engine = $( this ).val();
				if ( ! checked ) {
					return;
				}

				if ( 'file' === engine ) {
					w3tc_show_objectcache_diskpopup( previous );
				}

				previous = $engine;
			}
		);
	}
);

