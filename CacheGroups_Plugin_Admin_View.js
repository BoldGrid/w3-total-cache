/**
 * File: CacheGroups_Plugin_Admin_View.js
 *
 * @since 2.1.0
 *
 * @package W3TC
 */

jQuery(function() {
	const wpadminbar_height = (jQuery(window).width() > 600 && jQuery('#wpadminbar').length) ? jQuery('#wpadminbar').outerHeight() : 0,
		nav_bar_height = (jQuery('#w3tc-top-nav-bar').length) ? jQuery('#w3tc-top-nav-bar').outerHeight() : 0,
		options_menu_height = (jQuery('#w3tc > #w3tc-options-menu').length) ? jQuery('#w3tc > #w3tc-options-menu').outerHeight() : 0,
		form_bar_height = (jQuery('.w3tc_form_bar').length) ? jQuery('.w3tc_form_bar').outerHeight() : 0;

	jQuery(document).on(
		'submit',
		'#cachegroups_form',
		function() {
			var error = [];

			var mobile_groups = jQuery('#mobile_groups li');
    		mobile_groups.each(
				function(index, mobile_group) {
    		    	var $mobile_group = jQuery(mobile_group);
					var name = $mobile_group.find('.mobile_group_name').val();

					if ( '' === name ) {
						error.push('User Agent group "' + index + '" is missing it\'s name!');
					}

    	    		if ($mobile_group.find('.mobile_group_enabled:checked').length) {
    	    		    var theme = $mobile_group.find('.mobile_group_theme').val();
    	    		    var redirect = $mobile_group.find('.mobile_group_redirect').val();
		    		    var agents = $mobile_group.find('.mobile_group_agents').val().split("\n").filter(function(line){return line.trim()!==''}).map(function(line){return line.trim();});

    	        		mobile_groups.not($mobile_group).each(
							function(index, compare_mobile_group) {
    	        	    		var $compare_mobile_group = jQuery(compare_mobile_group);

    	        	   			if ($compare_mobile_group.find('.mobile_group_enabled:checked').length) {
    	        	       			var compare_name = $compare_mobile_group.find('.mobile_group_name').val();
    	        	       			var compare_theme = $compare_mobile_group.find('.mobile_group_theme').val();
    	        	       			var compare_redirect = $compare_mobile_group.find('.mobile_group_redirect').val();
		        	       			var compare_agents = $compare_mobile_group.find('.mobile_group_agents').val().split("\n").filter(function(line){return line.trim()!==''}).map(function(line){return line.trim();});

    	                			var groups = sort_array([name, compare_name]);

									if (compare_name !== '' && compare_name === name) {
    	                			    error.push('Duplicate name "' + compare_name + '" found in the user agent groups "' + groups[0] + '" and "' + groups[1] + '"');
    	                			}

    	                			if (compare_theme !== '' && compare_theme === theme) {
    	                			    error.push('Duplicate theme "' + compare_theme + '" found in the user agent groups "' + groups[0] + '" and "' + groups[1] + '"');
    	                			}

    	                			if (compare_redirect !== '' && compare_redirect === redirect) {
    	                			    error.push('Duplicate redirect "' + compare_redirect + '" found in the user agent groups "' + groups[0] + '" and "' + groups[1] + '"');
    	                			}

    	                			jQuery.each(
										compare_agents,
										function(index, value) {
    	                			    	if (jQuery.inArray(value, agents) !== -1) {
    	                			        	error.push('Duplicate stem "' + value + '" found in the user agent groups "' + groups[0] + '" and "' + groups[1] + '"');
    	                			    	}
    	                				}
									);
    	            			}
    	        			}
						);
    	    		}
    			}
			);

			var referrer_groups = jQuery('#referrer_groups li');
    		referrer_groups.each(
				function(index, referrer_group) {
    	    		var $referrer_group = jQuery(referrer_group);
					var name = $referrer_group.find('.referrer_group_name').val();

					if ( '' === name ) {
						error.push('Referrer group "' + index + '" is missing it\'s name!');
					}

    	    		if ($referrer_group.find('.referrer_group_enabled:checked').length) {
    	        		var theme = $referrer_group.find('.referrer_group_theme').val();
    	        		var redirect = $referrer_group.find('.referrer_group_redirect').val();
		        		var agents = $referrer_group.find('.referrer_group_referrers').val().split("\n").filter(function(line){return line.trim()!==''}).map(function(line){return line.trim();});

    	        		referrer_groups.not($referrer_group).each(
							function(index, compare_referrer_group) {
    	            			var $compare_referrer_group = jQuery(compare_referrer_group);

    	            			if ($compare_referrer_group.find('.referrer_group_enabled:checked').length) {
    	            			    var compare_name = $compare_referrer_group.find('.referrer_group_name').val();
    	            			    var compare_theme = $compare_referrer_group.find('.referrer_group_theme').val();
    	            			    var compare_redirect = $compare_referrer_group.find('.referrer_group_redirect').val();
		            			    var compare_agents = $compare_referrer_group.find('.referrer_group_referrers').val().split("\n").filter(function(line){return line.trim()!==''}).map(function(line){return line.trim();});

    	                			var groups = sort_array([name, compare_name]);

									if (compare_name !== '' && compare_name === name) {
    	                			    error.push('Duplicate name "' + compare_name + '" found in the referrer groups "' + groups[0] + '" and "' + groups[1] + '"');
    	                			}

    	                			if (compare_theme !== '' && compare_theme === theme) {
    	                			    error.push('Duplicate theme "' + compare_theme + '" found in the referrer groups "' + groups[0] + '" and "' + groups[1] + '"');
    	                			}

    	                			if (compare_redirect !== '' && compare_redirect === redirect) {
    	                			    error.push('Duplicate redirect "' + compare_redirect + '" found in the referrer groups "' + groups[0] + '" and "' + groups[1] + '"');
    	                			}

    	                			jQuery.each(
										compare_agents,
										function(index, value) {
    	                			    	if (jQuery.inArray(value, agents) !== -1) {
    	                			  		  	error.push('Duplicate stem "' + value + '" found in the referrer groups "' + groups[0] + '" and "' + groups[1] + '"');
    	                			    	}
    	                				}
									);
    	            			}
    	        			}
						);
    	    		}
    			}
			);

			var cookiegroups = jQuery('#cookiegroups li');
    		cookiegroups.each(
				function(index, cookiegroup) {
    	    		var $cookiegroup = jQuery(cookiegroup);
					var name = $cookiegroup.find('.cookiegroup_name').val();

					if ( '' === name ) {
						error.push('Cookie group "' + index + '" is missing it\'s name!');
					}

    	    		if ($cookiegroup.find('.cookiegroup_enabled:checked').length) {
    	        		var name = $cookiegroup.find('.cookiegroup_name').val();
		        		var agents = $cookiegroup.find('.cookiegroup_cookies').val().split("\n").filter(function(line){return line.trim()!==''}).map(function(line){return line.trim();});

    	        		cookiegroups.not($cookiegroup).each(
							function(index, compare_cookiegroup) {
    	            			var $compare_cookiegroup = jQuery(compare_cookiegroup);

    	            			if ($compare_cookiegroup.find('.cookiegroup_enabled:checked').length) {
    	            			    var compare_name = $compare_cookiegroup.find('.cookiegroup_name').text();
		            			    var compare_agents = $compare_cookiegroup.find('.cookiegroup_cookies').val().split("\n").filter(function(line){return line.trim()!==''}).map(function(line){return line.trim();});

    	                			var groups = sort_array([name, compare_name]);

									if (compare_name !== '' && compare_name === name) {
    	                			    error.push('Duplicate name "' + compare_name + '" found in the cookie groups "' + groups[0] + '" and "' + groups[1] + '"');
    	                			}

    	                			jQuery.each(
										compare_agents,
										function(index, value) {
    	                			    	if (jQuery.inArray(value, agents) !== -1) {
    	                			        	error.push('Duplicate stem "' + value + '" found in the cookie groups "' + groups[0] + '" and "' + groups[1] + '"');
    	                			    	}
    	                				}
									);
    	            			}
    	        			}
						);
    	    		}
    			}
			);

			if (error.length !== 0) {
				alert(unique_array(error).join('\n'));
				return false;
			}

			return true;
		}
	);

	jQuery(document).on(
		'click',
		'#mobile_add',
		function() {
			let maxID = -1;

			jQuery('.mobile_group_name').each(
				function() {
					const currentID = parseInt(jQuery(this).closest('li').attr('id').replace('mobile_group_', ''), 10);

					if (!isNaN(currentID)) {
						maxID = Math.max(maxID, currentID);
					}
				}
			);

			const blockID = maxID + 1;

			var li = jQuery('<li id="mobile_group_' + blockID + '">' +
				'<table class="form-table">' +
				'<tr>' +
				'<th><label for="mobile_groups_' + blockID + '_name">Group Name:</label></th>' +
				'<td>' +
				'<span class="mobile_group_number">' + ( blockID + 1 ) + '.</span> ' +
				'<input id="mobile_groups_' + blockID + '_name" class="mobile_group_name" type="text" name="mobile_groups[' + blockID + '][name]" value="" size="60" />' +
				'<input type="button" class="button mobile_delete" value="Delete group"	/>' +
				'<p class="description">Enter group name (only "0-9", "a-z", "_" symbols are allowed).</p>' +
				'</td>' +
				'</tr>' +
				'<tr>' +
				'<th><label for="mobile_groups_' + blockID + '_enabled">Enabled:</label></th>' +
				'<td>' +
				'<input type="hidden" name="mobile_groups[' + blockID + '][enabled]" value="0" />' +
				'<input id="mobile_groups_' + blockID + '_enabled" class="mobile_group_enabled" type="checkbox" name="mobile_groups[' + blockID + '][enabled]" value="1" checked="checked" />' +
				'</td>' +
				'</tr>' +
				'<tr>' +
				'<th><label for="mobile_groups_' + blockID + '_theme">Theme:</label></th>' +
				'<td>' +
				'<select id="mobile_groups_' + blockID + '_theme" class="mobile_group_theme" name="mobile_groups[' + blockID + '][theme]"><option value="">-- Pass-through --</option></select>' +
				'<p class="description">Assign this group of user agents to a specific them. Leaving this option "Active Theme" allows any plugins you have (e.g. mobile plugins) to properly handle requests for these user agents. If the "redirect users to" field is not empty, this setting is ignored.</p>' +
				'</td>' +
				'</tr>' +
				'<tr>' +
				'<th><label for="mobile_groups_' + blockID + '_redirect">Redirect users to:</label></th>' +
				'<td>' +
				'<input id="mobile_groups_' + blockID + '_redirect" class="mobile_group_redirect" type="text" name="mobile_groups[' + blockID + '][redirect]" value="" size="60" />' +
				'<p class="description">A 302 redirect is used to send this group of users to another hostname (domain); recommended if a 3rd party service provides a mobile version of your site.</p>' +
				'</td>' +
				'</tr>' +
				'<tr>' +
				'<th><label for="mobile_groups_' + blockID + '_agents">User agents:</label></th>' +
				'<td>' +
				'<textarea id="mobile_groups_' + blockID + '_agents" class="mobile_group_agents" name="mobile_groups[' + blockID + '][agents]" rows="10" cols="50"></textarea>' +
				'<p class="description">Specify the user agents for this group.</p>' +
				'</td>' +
				'</tr>' +
				'</table>' +
				'</li>');

			var select = li.find('.mobile_group_theme');

			jQuery.each(
				mobile_themes,
				function(index, value) {
					select.append(jQuery('<option />').val(index).html(value));
				}
			);

			jQuery('#mobile_groups').append(li);
			w3tc_mobile_groups_clear();
			setTimeout(
				function() {
					jQuery('html, body').animate(
						{
							scrollTop: li.offset().top - wpadminbar_height - nav_bar_height - options_menu_height - form_bar_height
						},
						600
					);
				},
				100
			);
			li.find('.mobile_group_name').focus();
		}
	);

	jQuery(document).on(
		'blur',
		'.mobile_group_name',
		function () {
			let $inputField = jQuery(this);
			let name = $inputField.val();
			let originalValue = $inputField.data('originalValue');

			
			if (name && null !== name) {
				name = name.trim();
				name = name.toLowerCase();
				name = name.replace(/[^0-9a-z_]+/g, '_');
				name = name.replace(/^_+/, '');
				name = name.replace(/_+$/, '');

				let exists = false;

				jQuery('.mobile_group_name').not($inputField).each(
					function() {
						if (jQuery(this).val() === name) {
							alert('Mobile Group already exists!');
							exists = true;
							$inputField.val(originalValue);
							// A timeout is needed here as the alert "steals" focus and causes a race condition.
							setTimeout(
								function() {
									$inputField.focus();
								},
								100
							);
							return false;
						}
					}
				);

				if (!exists) {
					$inputField.data('originalValue', name);
				}
			} else {
				alert('Mobile Group name is empty!');
				$inputField.val(originalValue);
				// A timeout is needed here as the alert "steals" focus and causes a race condition.
				setTimeout(
					function() {
						$inputField.focus();
					},
					100
				);
			}
		}
	);

	jQuery(document).on(
		'click',
		'.mobile_delete',
		function () {
			if (confirm('Are you sure want to delete this group?')) {
				jQuery(this).parents('#mobile_groups li').remove();
				w3tc_mobile_groups_clear();
				w3tc_beforeupload_bind();
				setTimeout(
					function() {
						jQuery('html, body').animate(
							{
								scrollTop: jQuery('#mobile_add').offset().top - wpadminbar_height - nav_bar_height - options_menu_height - form_bar_height
							},
							600
						);
					},
					100
				);
			}
		}
	);

	w3tc_mobile_groups_clear();

	// Referrer groups.

	jQuery(document).on(
		'click',
		'#referrer_add',
		function() {
			let maxID = -1;

			jQuery('.referrer_group_name').each(
				function() {
					const currentID = parseInt(jQuery(this).closest('li').attr('id').replace('referrer_group_', ''), 10);

					if (!isNaN(currentID)) {
						maxID = Math.max(maxID, currentID);
					}
				}
			);

			const blockID = maxID + 1;

			var li = jQuery('<li id="referrer_group_' + blockID + '">' +
				'<table class="form-table">' +
				'<tr>' +
				'<th><label for="referrer_groups_' + blockID + '_name">Group Name:</label></th>' +
				'<td>' +
				'<span class="referrer_group_number">' + ( blockID + 1 ) + '.</span> ' +
				'<input id="referrer_groups_' + blockID + '_name" class="referrer_group_name" type="text" name="referrer_groups[' + blockID + '][name]" value="" size="60" />' +
				'<input type="button" class="button referrer_delete" value="Delete group"	/>' +
				'<p class="description">Enter group name (only "0-9", "a-z", "_" symbols are allowed).</p>' +
				'</td>' +
				'</tr>' +
				'<tr>' +
				'<th>' +
				'<label for="referrer_groups_' + blockID + '_enabled">Enabled:</label>' +
				'</th>' +
				'<td>' +
				'<input type="hidden" name="referrer_groups[' + blockID + '][enabled]" value="0" />' +
				'<input id="referrer_groups_' + blockID + '_enabled" class="referrer_group_enabled" type="checkbox" name="referrer_groups[' + blockID + '][enabled]" value="1" checked="checked" />' +
				'</td>' +
				'</tr>' +
				'<tr>' +
				'<th><label for="referrer_groups_' + blockID + '_theme">Theme:</label></th>' +
				'<td>' +
				'<select id="referrer_groups_' + blockID + '_theme" class="referrer_group_theme" name="referrer_groups[' + blockID + '][theme]"><option value="">-- Pass-through --</option></select>' +
				'<p class="description">Assign this group of referrers to a specific them. Leaving this option "Active Theme" allows any plugins you have (e.g. referrer plugins) to properly handle requests for these referrers. If the "redirect users to" field is not empty, this setting is ignored.</p>' +
				'</td>' +
				'</tr>' +
				'<tr>' +
				'<th><label for="referrer_groups_' + blockID + '_redirect">Redirect users to:</label></th>' +
				'<td>' +
				'<input id="referrer_groups_' + blockID + '_redirect" class="referrer_group_redirect" type="text" name="referrer_groups[' + blockID + '][redirect]" value="" size="60" />' +
				'<p class="description">A 302 redirect is used to send this group of users to another hostname (domain); recommended if a 3rd party service provides a referrer version of your site.</p>' +
				'</td>' +
				'</tr>' +
				'<tr>' +
				'<th><label for="referrer_groups_' + blockID + '_referrers">Referrers:</label></th>' +
				'<td>' +
				'<textarea id="referrer_groups_' + blockID + '_referrers" class="referrer_group_referrers" name="referrer_groups[' + blockID + '][referrers]" rows="10" cols="50"></textarea>' +
				'<p class="description">Specify the referrers for this group.</p>' +
				'</td>' +
				'</tr>' +
				'</table>' +
				'</li>');
			
			var select = li.find('.referrer_group_theme');

			jQuery.each(
				referrer_themes,
				function(index, value) {
					select.append(jQuery('<option />').val(index).html(value));
				}
			);

			jQuery('#referrer_groups').append(li);
			w3tc_referrer_groups_clear();
			setTimeout(
				function() {
					jQuery('html, body').animate(
						{
							scrollTop: li.offset().top - wpadminbar_height - nav_bar_height - options_menu_height - form_bar_height
						},
						600
					);
				},
				100
			);
			li.find('.referrer_group_name').focus();
		}
	);

	jQuery(document).on(
		'blur',
		'.referrer_group_name',
		function () {
			let $inputField = jQuery(this);
			let name = $inputField.val();
			let originalValue = $inputField.data('originalValue');

			
			if (name && null !== name) {
				name = name.trim();
				name = name.toLowerCase();
				name = name.replace(/[^0-9a-z_]+/g, '_');
				name = name.replace(/^_+/, '');
				name = name.replace(/_+$/, '');

				let exists = false;

				jQuery('.referrer_group_name').not($inputField).each(
					function() {
						if (jQuery(this).val() === name) {
							alert('Referrer Group already exists!');
							exists = true;
							$inputField.val(originalValue);
							// A timeout is needed here as the alert "steals" focus and causes a race condition.
							setTimeout(
								function() {
									$inputField.focus();
								},
								100
							);
							return false;
						}
					}
				);

				if (!exists) {
					$inputField.data('originalValue', name);
				}
			} else {
				alert('Referrer Group name is empty!');
				$inputField.val(originalValue);
				// A timeout is needed here as the alert "steals" focus and causes a race condition.
				setTimeout(
					function() {
						$inputField.focus();
					},
					100
				);
			}
		}
	);

	jQuery(document).on(
		'click',
		'.referrer_delete',
		function () {
			if (confirm('Are you sure want to delete this group?')) {
				jQuery(this).parents('#referrer_groups li').remove();
				w3tc_referrer_groups_clear();
				w3tc_beforeupload_bind();
				setTimeout(
					function() {
						jQuery('html, body').animate(
							{
								scrollTop: jQuery('#referrer_add').offset().top - wpadminbar_height - nav_bar_height - options_menu_height - form_bar_height
							},
							600
						);
					},
					100
				);
			}
		}
	);

	w3tc_referrer_groups_clear();

	// Cookie groups.

	jQuery(document).on(
		'click',
		'#cookiegroup_add',
		function() {
			let maxID = -1;

			jQuery('.cookiegroup_name').each(
				function() {
					const currentID = parseInt(jQuery(this).closest('li').attr('id').replace('cookiegroup_', ''), 10);
					if (!isNaN(currentID)) {
						maxID = Math.max(maxID, currentID);
					}
				}
			);

			const blockID = maxID + 1;

			var li = jQuery('<li id="cookiegroup_' + blockID + '">' +
				'<table class="form-table">' +
				'<tr>' +
				'<th><label for="cookiegroups_' + blockID + '_name">Group Name:</label></th>' +
				'<td>' +
				'<span class="cookiegroup_number">' + ( blockID + 1 ) + '.</span> ' +
				'<input id="cookiegroups_' + blockID + '_name" class="cookiegroup_name" type="text" name="cookiegroups[' + blockID + '][name]" value="" size="60" />' +
				'<input type="button" class="button cookiegroup_delete" value="Delete group"	/>' +
				'<p class="description">Enter group name (only "0-9", "a-z", "_" symbols are allowed).</p>' +
				'</td>' +
				'</tr>' +
				'<tr>' +
				'<th><label for="cookiegroup_' + blockID + '_enabled">Enabled:</label></th>' +
				'<td>' +
				'<input id="cookiegroup_' + blockID + '_enabled" class="cookiegroup_enabled" type="checkbox" name="cookiegroups[' + blockID + '][enabled]" value="1" checked="checked" />' +
				'</td>' +
				'</tr>' +
				'<tr>' +
				'<th><label for="cookiegroup_' + blockID + '_cache">Cache:</label></th>' +
				'<td>' +
				'<input id="cookiegroup_' + blockID + '_cache" type="checkbox" name="cookiegroups[' + blockID + '][cache]" value="1" checked="checked" /></td>' +
				'</tr>' +
				'<tr>' +
				'<th><label for="cookiegroups_' + blockID + '_cookies">Cookies:</label></th>' +
				'<td>' +
				'<textarea id="cookiegroups_' + blockID + '_cookies" name="cookiegroups[' + blockID + '][cookies]" rows="10" cols="50"></textarea>' +
				'<p class="description">Specify the cookies for this group. Values like \'cookie\', \'cookie=value\', and cookie[a-z]+=value[a-z]+are supported. Remember to escape special characters like spaces, dots or dashes with a backslash. Regular expressions are also supported.</p>' +
				'</td>' +
				'</tr>' +
				'</table>' +
				'</li>');

			jQuery('#cookiegroups').append(li);
			w3tc_cookiegroups_clear();
			setTimeout(
				function() {
					jQuery('html, body').animate(
						{
							scrollTop: li.offset().top - wpadminbar_height - nav_bar_height - options_menu_height - form_bar_height
						},
						600
					);
				},
				100
			);
			li.find('.cookiegroup_name').focus();
		}
	);

	jQuery(document).on(
		'blur',
		'.cookiegroup_name',
		function () {
			let $inputField = jQuery(this);
			let name = $inputField.val();
			let originalValue = $inputField.data('originalValue');

			if (name && null !== name) {
				name = name.trim();
				name = name.toLowerCase();
				name = name.replace(/[^0-9a-z_]+/g, '_');
				name = name.replace(/^_+/, '');
				name = name.replace(/_+$/, '');

				let exists = false;

				jQuery('.cookiegroup_name').not($inputField).each(
					function() {
						if (jQuery(this).val() === name) {
							alert('Cookie Group already exists!');
							exists = true;
							$inputField.val(originalValue);
							// A timeout is needed here as the alert "steals" focus and causes a race condition.
							setTimeout(
								function() {
									$inputField.focus();
								},
								100
							);
							return false;
						}
					}
				);

				if (!exists) {
					$inputField.data('originalValue', name);
				}
			} else {
				alert('Cookie Group name is empty!');
				$inputField.val(originalValue);
				// A timeout is needed here as the alert "steals" focus and causes a race condition.
				setTimeout(
					function() {
						$inputField.focus();
					},
					100
				);
			}
		}
	);

	jQuery(document).on(
		'click',
		'.cookiegroup_delete',
		function () {
			if (confirm('Are you sure want to delete this group?')) {
				jQuery(this).parents('#cookiegroups li').remove();
				w3tc_cookiegroups_clear();
				w3tc_beforeupload_bind();
				setTimeout(
					function() {
						jQuery('html, body').animate(
							{
								scrollTop: jQuery('#cookiegroup_add').offset().top - wpadminbar_height - nav_bar_height - options_menu_height - form_bar_height
							},
							600
						);
					},
					100
				);
			}
		}
	);

	w3tc_cookiegroups_clear();

	// Add sortable.
	if (jQuery.ui && jQuery.ui.sortable) {
		jQuery('#cookiegroups').sortable(
			{
				axis: 'y',
				stop: function() {
					jQuery('#cookiegroups').find('.cookiegroup_number').each(
						function(index) {
							jQuery(this).html((index + 1) + '.');
						}
					);
				}
			}
		);
	}

	setNameValues();
});

function setNameValues() {
    jQuery('.mobile_group_name').each(
		function() {
        	var $inputField = jQuery(this);
        	var originalValue = $inputField.val();
        	$inputField.data('originalValue', originalValue);
    	}
	);
	jQuery('.referrer_group_name').each(
		function() {
        	var $inputField = jQuery(this);
        	var originalValue = $inputField.val();
        	$inputField.data('originalValue', originalValue);
    	}
	);
	jQuery('.cookiegroup_name').each(
		function() {
        	var $inputField = jQuery(this);
        	var originalValue = $inputField.val();
        	$inputField.data('originalValue', originalValue);
    	}
	);
}

function w3tc_mobile_groups_clear() {
	if (!jQuery('#mobile_groups li').length) {
		jQuery('#mobile_groups_empty').show();
	} else {
		jQuery('#mobile_groups_empty').hide();
	}
}

function w3tc_referrer_groups_clear() {
	if (!jQuery('#referrer_groups li').length) {
		jQuery('#referrer_groups_empty').show();
	} else {
		jQuery('#referrer_groups_empty').hide();
	}
}

function w3tc_cookiegroups_clear() {
	if (!jQuery('#cookiegroups li').length) {
		jQuery('#cookiegroups_empty').show();
	} else {
		jQuery('#cookiegroups_empty').hide();
	}
}

function unique_array(array) {
	return jQuery.grep(array,function(el,i){return i === jQuery.inArray(el,array)});
}

function sort_array(array) {
	return array.sort();
}