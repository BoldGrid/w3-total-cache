/**
 * File: Extension_AlwaysCached_Page_View.js
 *
 * JavaScript for the Always Cached settings page.
 *
 * @since X.X.X
 *
 * @global w3tcData Localized data.
 */

jQuery(function() {
	jQuery(document).on(
		'click',
		'.w3tc_alwayscached_queue_item',
		function(e) {
			e.preventDefault();

			var row = jQuery(this).closest('tr');
			var item_url = jQuery(this).data('url');

			jQuery.ajax(
				{
					url: ajaxurl,
					method: 'GET',
					headers: {
						w3tcalwayscached: true,
					},
					data: {
						action: 'w3tc_ajax',
						_wpnonce: w3tc_nonce[0],
						w3tc_action: 'extension_alwayscached_process_queue_item',
						item_url: item_url
					},
					success: function(response) {
						if ( response.success && 'ok' === response.data ) {
							alert('Successfully regenerated entry.');
							row.remove();
						} else if ( ! response.success && 'failed' === response.data ) {
							alert('An unknown error occurred.');
						}
					},
					error: function( xhr, status, error ) {
						console.error('AJAX error:', status, error );
					}
				}
			);
		}
	);

	jQuery(document).on(
		'click',
		'.w3tc_alwayscached_queue',
		function(e) {
			e.preventDefault();

			var mode = jQuery(this).data('mode');
			var elContainer = jQuery(this).parent().find('section');

			jQuery.ajax(
				{
					url: ajaxurl,
					method: 'GET',
					data: {
						action: 'w3tc_ajax',
						_wpnonce: w3tc_nonce[0],
						w3tc_action: 'extension_alwayscached_queue',
						mode: mode
					},
					success: function(data) {
						elContainer.html(data);
						elContainer.show();
					}
				}
			);
		}
	);

	jQuery(document).on(
		'click',
		'.w3tc_alwayscached_queue_filter_submit',
		function(e) {
			e.preventDefault();
			var mode = jQuery(this).data('mode');
			var search = jQuery(this).closest('section').find('.w3tc_alwayscached_queue_filter').val();
            loadQueueTable( mode, 1, search );
		}
	);

	jQuery(document).on(
		'click',
		'.w3tc_alwayscached_queue_view_pagination_page',
		function(e) {
			e.preventDefault();
            var mode = jQuery(this).data('mode');
			var page = jQuery(this).data('page');
			var search = jQuery(this).closest('section').find('.w3tc_alwayscached_queue_filter').val();
            loadQueueTable( mode, page, search );
		}
	);

	jQuery(document).on(
		'change',
		'.w3tc_alwayscached_queue_view_pagination_page_input',
		function() {
			var max = parseInt(jQuery(this).attr('max'));
			var min = parseInt(jQuery(this).attr('min'));
			if (jQuery(this).val() > max) {
				jQuery(this).val(max);
			} else if (jQuery(this).val() < min) {
				jQuery(this).val(min);
			}
		}
	);

	jQuery(document).on(
		'click',
		'.w3tc_alwayscached_queue_view_pagination_page_input_submit',
		function(e) {
			e.preventDefault();
			var mode = jQuery(this).data('mode');
			var page = parseInt(jQuery(this).closest('section').find('.w3tc_alwayscached_queue_view_pagination_page_input').val());
			var search = jQuery(this).closest('section').find('.w3tc_alwayscached_queue_filter').val();
			loadQueueTable( mode, page, search );
		}
	);

	jQuery(document).on(
		'change',
		'#alwayscached___flush_all',
		function() {
			let $enabled = jQuery(this).prop('checked');
        
        	jQuery('#alwayscached___flush_all_home').prop('disabled', ! $enabled);
       		jQuery('#alwayscached___flush_all_posts_count').prop('disabled', ! $enabled);
        	jQuery('#alwayscached___flush_all_pages_count').prop('disabled', ! $enabled);
		}
	);

	function loadQueueTable(mode = 'pending', page = 1, search = '') {
		jQuery.ajax(
			{
				url: ajaxurl,
				method: 'GET',
				data: {
					action: 'w3tc_ajax',
					_wpnonce: w3tc_nonce[0],
					w3tc_action: 'extension_alwayscached_queue_filter',
					mode: mode,
					page: page,
					search: search
				},
				success: function (response) {
					var tbody = '';
					jQuery.each(
						response.rows,
						function (index, row) {
							tbody += '<tr>' +
								'<td><span class="w3tc_alwayscached_queue_item dashicons dashicons-update" title="Regenerate" data-url="' + row.url + '"></span></td>' +
								'<td style="white-space: nowrap">' + (':' === row.key.charAt(0) ? 'command ' + row.key : row.url) + '</td>' +
								'<td>' + row.requests_count + '</td>' +
								'</tr>';
						}
					);

					jQuery('.w3tc_alwayscached_queue_view_table[data-mode="' + mode + '"] tbody').html( tbody );

					var pagination = '<span>Pages: </span>';
					var total_pages = response.total_pages;

					if (10 >= total_pages) {
						for (var i = 1; i <= total_pages; i++) {
							pagination += '<a href="#" class="w3tc_alwayscached_queue_view_pagination_page' + (page === i ? ' active' : '') + '" data-mode="' + mode + '" data-page="' + i + '">' + i + '</a>';
						}
					} else {
						var start_mid, end_mid;
						if (9 > page) {
							start_mid = 1;
							end_mid = 9;
						} else if (page > total_pages - 9) {
							start_mid = total_pages - 9;
							end_mid = total_pages;
						} else {
							start_mid = Math.max(1, page - 4);
							end_mid = Math.min(total_pages, page + 4);
						}

						if (start_mid > 1) {
							pagination += '<a href="#" class="w3tc_alwayscached_queue_view_pagination_page" data-mode="' + mode + '" data-page="1">1</a>';
							if (start_mid > 2) {
								pagination += '<span>...</span>';
							}
						}

						for (var i = start_mid; i <= end_mid; i++) {
							pagination += '<a href="#" class="w3tc_alwayscached_queue_view_pagination_page' + (page === i ? ' active' : '') + '" data-mode="' + mode + '" data-page="' + i + '">' + i + '</a>';
						}

						if (end_mid < total_pages) {
							if (end_mid < total_pages - 1) {
								pagination += '<span>...</span>';
							}
							pagination += '<a href="#" class="w3tc_alwayscached_queue_view_pagination_page" data-mode="' + mode + '" data-page="' + total_pages + '">' + total_pages + '</a>';
						}

						pagination += '<br><input type="number" min="1" max="' + total_pages + '" class="w3tc_alwayscached_queue_view_pagination_page_input" data-mode="' + mode + '" name="page-jump" placeholder="Page #"><input class="button w3tc_alwayscached_queue_view_pagination_page_input_submit" data-mode="' + mode + '" type="submit" value="Go">';
					}

					jQuery('.w3tc_alwayscached_queue_view_pagination_container[data-mode="' + mode + '"').html(pagination);
				},
				error: function(jqXHR, textStatus, errorThrown) {
					alert('An unknown error occured!');
					console.error('AJAX Error: ', textStatus, errorThrown);
				}
			}
		);
	}
});
