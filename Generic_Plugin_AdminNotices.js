/**
 * File: Generic_Plugin_AdminNotices.js
 *
 * JavaScript for W3TC Admin Notices.
 *
 * @since X.X.X
 */
jQuery(document).ready(function($) {
	$.get(
		ajaxurl,
		{
			action: 'w3tc_ajax',
			_wpnonce: w3tc_nonce[0],
			w3tc_action: 'get_notices'
		},
		function(response) {
			if (response.success) {
				var noticeData = response.data.noticeData;
				if (noticeData.length > 0) {
					noticeData.forEach(
						function(notice) {
							var noticeId = 'notice-' + notice.id;
							var $noticeContent = $(notice.content).attr('id', noticeId);
							
							// Check if the notice is dismissible but lacks the dismiss button.
							if ($noticeContent.hasClass('is-dismissible') && $noticeContent.find('.notice-dismiss').length === 0) {
								$noticeContent.append(
									'<button type="button" class="notice-dismiss">' +
									'<span class="screen-reader-text">Dismiss this notice.</span>' +
									'</button>'
								);
							}

							$('#w3tc-top-nav-bar').after($noticeContent);

							// Manually initialize the dismiss button
							$noticeContent.on(
								'click',
								'.notice-dismiss',
								function() {
									$.post(
										ajaxurl,
										{
											action: 'w3tc_ajax',
											_wpnonce: w3tc_nonce[0],
											w3tc_action: 'dismiss_notice',
											notice_id: noticeId
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
