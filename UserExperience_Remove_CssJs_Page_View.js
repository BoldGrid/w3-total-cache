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
			var single_path = prompt('Enter CSS/JS URL.');

			if (single_path === null) {
				return;
			}

			single_path = single_path.trim();
			if (single_path !== '') {
				var exists = false;

				jQuery('.remove_cssjs_singles_path').each(
					function() {
						if (jQuery(this).html() == single_path) {
							alert('Entry already exists!');
							exists = true;
							return false;
						}
					}
				);

				if (!exists) {
					var single_id = single_path.replace(/[^\w-]/g, '_');

					var li = jQuery(
						'<li id="remove_cssjs_singles_' + single_id + '">' +
						'<table class="form-table">' +
						'<tr>' +
						'<th>CSS/JS path to remove:</th>' +
						'<td>' +
						'<span class="remove_cssjs_singles_path">' + single_path + '</span>' +
						'<input type="button" class="button remove_cssjs_singles_delete" value="Delete"/>' +
						'</td>' +
						'</tr>' +
						'<tr>' +
						'<th><label for="remove_cssjs_singles_' + single_id + '_action">Behavior:</label></th>' +
						'<td>' +
						'<label class="remove_cssjs_singles_behavior"><input class="remove_cssjs_singles_behavior_radio" type="radio" name="user-experience-remove-cssjs-singles[' + single_path + '][action]" value="exclude" checked>Exclude</label>' +
						'<label class="remove_cssjs_singles_behavior"><input class="remove_cssjs_singles_behavior_radio" type="radio" name="user-experience-remove-cssjs-singles[' + single_path + '][action]" value="include">Include</label>' +
						'<p class="description">Exclude will only remove this file from the specified URLs.</p>' +
						'<p class="description">Include will NOT remove this file from the specified URLs but will remove it everywhere else.</p>' +
						'</td>' +
						'</tr>' +
						'<tr>' +
						'<th><label class="remove_cssjs_singles_' + single_id + '_includes_label" for="remove_cssjs_singles_' + single_id + '_includes">Exclude on these pages:</label></th>' +
						'<td>' +
						'<textarea id="remove_cssjs_singles_' + single_id + '_includes" name="user-experience-remove-cssjs-singles[' + single_path + '][includes]" rows="5" cols="50" ></textarea>' +
						'<p class="description remove_cssjs_singles_' + single_id + '_includes_description">Specify relative/absolute page URLs that the above CSS/JS file should be Excluded from. Include one entry per line.</p>' +
						'</td>' +
						'</tr>' +
						'</table>' +
						'</li>'
					);

					jQuery('#remove_cssjs_singles').append(li);
					w3tc_remove_cssjs_singles_clear();
					window.location.hash = '#remove_cssjs_singles_' + single_id;
					li.find('textarea').focus();
				}
			} else {
				alert('Empty CSS/JS URL!');
			}
		}
	);

	jQuery(document).on(
		'click',
		'.remove_cssjs_singles_delete',
		function () {
			if (confirm('Are you sure want to delete this entry?')) {
				jQuery(this).parents('#remove_cssjs_singles li').remove();
				w3tc_remove_cssjs_singles_clear();
				w3tc_beforeupload_bind();
			}
		}
	);

	jQuery(document).on(
		'change',
		'.remove_cssjs_singles_behavior_radio',
		function () {
			var parent_id = jQuery(this).closest('li').attr('id');
			if (this.value === 'exclude') {
				jQuery('.' + parent_id + '_includes_label').text('Exclude on these pages:');
				jQuery('.' + parent_id + '_includes_description').text('Specify relative/absolute page URLs that the above CSS/JS should be Excluded from. Include one entry per line.');
			} else {
				jQuery('.' + parent_id + '_includes_label').text('Include on these pages:');
				jQuery('.' + parent_id + '_includes_description').text('Specify relative/absolute page URLs that the above CSS/JS should be Included for. Include one entry per line.');
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