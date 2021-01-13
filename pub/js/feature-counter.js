/**
 * File: feature-counter.js
 *
 * JavaScript for feature counters.
 *
 * @since X.X.X
 *
 * @global W3TCFeatureShowcaseData
 */

jQuery(function() {
	var $menuItem = jQuery( '#toplevel_page_w3tc_dashboard.wp-not-current-submenu a[href="admin.php?page=w3tc_dashboard"] .wp-menu-name' ),
		$submenuItem = jQuery( '#toplevel_page_w3tc_dashboard a[href="admin.php?page=w3tc_feature_showcase"]' ),
		markup = ' <span class="awaiting-mod count-' +
			W3TCFeatureShowcaseData.unseenCount +
			'"><span class="feature-count">' +
			W3TCFeatureShowcaseData.unseenCount +
			'</span></span>';

	if ( W3TCFeatureShowcaseData.unseenCount ) {
		if ( $menuItem.length ) {
			$menuItem.append( markup );
		}

		if ( $submenuItem.length ) {
			$submenuItem.append( markup );
		}
	}
});
