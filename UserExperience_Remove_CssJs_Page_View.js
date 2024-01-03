/**
 * File: UserExperience_Remove_CssJs_Page_View.js
 *
 * @since 2.7.0
 *
 * @package W3TC
 */

jQuery(function() {
	jQuery(document).on(
		'click',
		'#w3tc_remove_cssjs_singles_add',
		function() {
			var single = prompt('Enter CSS/JS URL.');

			if (single !== null) {
				var exists = false;

				jQuery('.remove_cssjs_singles_path').each(
					function() {
						if (jQuery(this).html() == single) {
							alert('Entry already exists!');
							exists = true;
							return false;
						}
					}
				);

				if (!exists) {
					var li = jQuery(
						'<li id="remove_cssjs_singles_' + single + '">' +
						'<table class="form-table">' +
						'<tr>' +
						'<th>CSS/JS path to remove:</th>' +
						'<td>' +
						'<span class="remove_cssjs_singles_path">' + single + '</span> ' +
						'<input type="button" class="button w3tc_remove_cssjs_singles_delete" value="Delete" />' +
						'</td>' +
						'</tr>' +
						'<tr>' +
						'<th><label for="remove_cssjs_singles_' + single + '_includes">Remove on these pages:</label></th>' +
						'<td>' +
						'<textarea id="remove_cssjs_singles_' + single + '_includes" name="user-experience-remove-cssjs-singles[' + single + '][includes]" rows="5" cols="50"></textarea>' +
						'<p class="description">Specify relative/absolute page URLs that the above CSS/JS should be removed from. Include one entry per line.</p>' +
						'</td>' +
						'</tr>' +
						'</table>' +
						'</li>'
					);

					jQuery('#remove_cssjs_singles').append(li);
					w3tc_remove_cssjs_singles_clear();
					window.location.hash = '#remove_cssjs_singles_' + single;
					li.find('textarea').focus();
				}
			} else {
				alert('Empty CSS/JS URL!');
			}
		}
	);

	jQuery(document).on(
		'click',
		'.w3tc_remove_cssjs_singles_delete',
		function () {
			if (confirm('Are you sure want to delete this entry?')) {
				jQuery(this).parents('#remove_cssjs_singles li').remove();
				w3tc_remove_cssjs_singles_clear();
				w3tc_beforeupload_bind();
			}
		}
	);

	w3tc_remove_cssjs_singles_clear();
});

function w3tc_remove_cssjs_singles_clear() {
	if (!jQuery('#remove_cssjs_singles li').length) {
		jQuery('#remove_cssjs_singles_empty').show();
	} else {
		jQuery('#remove_cssjs_singles_empty').hide();
	}
}