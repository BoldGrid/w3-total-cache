/**
 * File: UserExperience_Remove_CssJs_Page_View.js
 *
 * @since 2.7.0
 *
 * @package W3TC
 *
 * @global W3TCRemoveCssJsData
 */

jQuery(function() {
	jQuery(document).on(
		'click',
		'#w3tc_remove_cssjs_singles_add',
		function() {
			let singlePath = prompt(W3TCRemoveCssJsData.lang.singlesPrompt);

			if (null === singlePath) {
				return;
			}

			singlePath = singlePath.trim();
			if (singlePath) {
				let exists = false;
				let maxID = -1;

				jQuery('.remove_cssjs_singles_path').each(
					function() {
						const currentID = parseInt(jQuery(this).closest('li').attr('id').replace('remove_cssjs_singles_', ''), 10);

						if (!isNaN(currentID)) {
							maxID = Math.max(maxID, currentID);
						}

						if (jQuery(this).val() === singlePath) {
							alert(W3TCRemoveCssJsData.lang.singlesExists);
							exists = true;
							return false;
						}
					}
				);

				if (!exists) {
					const singleID = maxID + 1;

					const li = jQuery(
						'<li id="remove_cssjs_singles_' + singleID + '">' +
						'<table class="form-table">' +
						'<tr>' +
						'<th>' + W3TCRemoveCssJsData.lang.singlesPathLabel + '</th>' +
						'<td>' +
						'<input class="remove_cssjs_singles_path" type="text" name="user-experience-remove-cssjs-singles[' + singleID + '][url_pattern]" value="' + singlePath + '" >' +
						'<input type="button" class="button remove_cssjs_singles_delete" value="' + W3TCRemoveCssJsData.lang.singlesDelete + '"/>' +
						'</td>' +
						'</tr>' +
						'<tr>' +
						'<th><label for="remove_cssjs_singles_' + singleID + '_action">' + W3TCRemoveCssJsData.lang.singlesBehaviorLabel + '</label></th>' +
						'<td>' +
						'<label class="remove_cssjs_singles_behavior"><input class="remove_cssjs_singles_behavior_radio" type="radio" name="user-experience-remove-cssjs-singles[' + singleID + '][action]" value="exclude" checked>' + W3TCRemoveCssJsData.lang.singlesBehaviorExcludeText + '</label>' +
						'<label class="remove_cssjs_singles_behavior"><input class="remove_cssjs_singles_behavior_radio" type="radio" name="user-experience-remove-cssjs-singles[' + singleID + '][action]" value="include">' + W3TCRemoveCssJsData.lang.singlesBehaviorIncludeText + '</label>' +
						'<p class="description">' + W3TCRemoveCssJsData.lang.singlesBehaviorDescription + '</p>' +
						'<p class="description">' + W3TCRemoveCssJsData.lang.singlesBehaviorDescription2 + '</p>' +
						'</td>' +
						'</tr>' +
						'<tr>' +
						'<th><label class="remove_cssjs_singles_' + singleID + '_includes_label" for="remove_cssjs_singles_' + singleID + '_includes">' + W3TCRemoveCssJsData.lang.singlesIncludesLabelExclude + '</label></th>' +
						'<td>' +
						'<textarea id="remove_cssjs_singles_' + singleID + '_includes" name="user-experience-remove-cssjs-singles[' + singleID + '][includes]" rows="5" cols="50" ></textarea>' +
						'<p class="description remove_cssjs_singles_' + singleID + '_includes_description">' + W3TCRemoveCssJsData.lang.singlesIncludesDescriptionExclude + '</p>' +
						'</td>' +
						'</tr>' +
						'</table>' +
						'</li>'
					);

					jQuery('#remove_cssjs_singles_empty').remove();
					jQuery('#remove_cssjs_singles').append(li);
					window.location.hash = '#remove_cssjs_singles_' + singleID;
					li.find('textarea').focus();
				}
			} else {
				alert(W3TCRemoveCssJsData.lang.singlesEmptyUrl);
			}
		}
	);

	jQuery(document).on(
		'change',
		'.remove_cssjs_singles_path',
		function() {
			let $inputField = jQuery(this);
			let singlePath = $inputField.val();
			let originalValue = $inputField.data('originalValue');

			if (null === singlePath) {
				return;
			}

			singlePath = singlePath.trim();
			if (singlePath) {
				let exists = false;

				jQuery('.remove_cssjs_singles_path').not($inputField).each(
					function() {
						if (jQuery(this).val() === singlePath) {
							alert(W3TCRemoveCssJsData.lang.singlesExists);
							exists = true;
							$inputField.val(originalValue);
							return false;
						}
					}
				);

				if (!exists) {
					$inputField.data('originalValue', singlePath);
				}
			} else {
				alert(W3TCRemoveCssJsData.lang.singlesEmptyUrl);
			}
		}
	);

	jQuery(document).on(
		'click',
		'.remove_cssjs_singles_delete',
		function () {
			if (confirm(W3TCRemoveCssJsData.lang.singlesDeleteConfirm)) {
				jQuery(this).parents('#remove_cssjs_singles li').remove();
				if (0 === jQuery('#remove_cssjs_singles li').length) {
					jQuery('#remove_cssjs_singles').append('<li id="remove_cssjs_singles_empty">' + W3TCRemoveCssJsData.lang.singlesNoEntries + '<input type="hidden" name="user-experience-remove-cssjs-singles[]"></li>');
				}
				w3tc_beforeupload_bind();
			}
		}
	);

	jQuery(document).on(
		'change',
		'.remove_cssjs_singles_behavior_radio',
		function () {
			const parentId = jQuery(this).closest('li').attr('id');
			if (this.value === 'exclude') {
				jQuery('.' + parentId + '_includes_label').text(W3TCRemoveCssJsData.lang.singlesIncludesLabelExclude);
				jQuery('.' + parentId + '_includes_description').text(W3TCRemoveCssJsData.lang.singlesIncludesDescriptionExclude);
			} else {
				jQuery('.' + parentId + '_includes_label').text(W3TCRemoveCssJsData.lang.singlesIncludesLabelInclude);
				jQuery('.' + parentId + '_includes_description').text(W3TCRemoveCssJsData.lang.singlesIncludesDescriptionInclude);
			}
		}
	);

	setRemoveCssjsSinglesPathValues();
});

function setRemoveCssjsSinglesPathValues() {
    jQuery('.remove_cssjs_singles_path').each(
		function() {
        	var $inputField = jQuery(this);
        	var originalValue = $inputField.val();
        	$inputField.data('originalValue', originalValue);
    	}
	);
}
