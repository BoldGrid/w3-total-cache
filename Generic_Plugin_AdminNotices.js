/**
 * File: Generic_Plugin_AdminNotices.js
 *
 * JavaScript for W3TC Admin Notices.
 *
 * Array W3tcNoticeData {
 *     bool   isW3tcPage Is on a W3TC page.
 *     string w3tc_nonce Nonce.
 * }
 *
 * @since 2.7.5
 */
jQuery(document).ready(function($) {
	$.get(
		ajaxurl,
		{
			action: 'w3tc_ajax',
			_wpnonce: W3tcNoticeData.w3tc_nonce,
			w3tc_action: 'get_notices'
		},
		function(response) {
			if (response.success) {
				const noticeData = response.data.noticeData;
				if (noticeData.length > 0) {
					noticeData.forEach(
						function(notice) {
							// Check if the notice is global or for only W3TC pages.
							if (! W3tcNoticeData.isW3tcPage && ! notice.is_global) {
								return;
							}

							const $noticeContent = $(notice.content);

							if ($('#w3tc-top-nav-bar').length) {
								$('#w3tc-top-nav-bar').after($noticeContent);
							} else {
								$('#wpbody-content').prepend($noticeContent);
							}

							// Manually initialize the dismiss button
							$noticeContent.on(
								'click',
								'.notice-dismiss',
								function() {
									$.post(
										ajaxurl,
										{
											action: 'w3tc_ajax',
											_wpnonce: W3tcNoticeData.w3tc_nonce,
											w3tc_action: 'dismiss_notice',
											notice_id: $noticeContent.data('id')
										}
									);

									$noticeContent.fadeTo(
										100,
										0,
										function() {
											$noticeContent.slideUp(
												100,
												function() {
													$noticeContent.remove();
												}
											);
										}
									);
								}
							);
						}
					);
				}
			} else {
				console.log('Error: ', response.data.message);
			}
		}
	);
});
