/**
 * File: feature-counter.js
 *
 * JavaScript for feature counters.
 *
 * @since 2.1.0
 *
 * @global W3TCFeatureShowcaseData
 */

jQuery(function() {
	var $adminmenuItem = jQuery ( '#wp-admin-bar-w3tc_feature_showcase a' ),
		$menuItem = jQuery( '#toplevel_page_w3tc_dashboard.wp-not-current-submenu a[href="admin.php?page=w3tc_dashboard"] .wp-menu-name' ),
		$submenuItem = jQuery( '#toplevel_page_w3tc_dashboard a[href="admin.php?page=w3tc_feature_showcase"]' ),
		markup = ' <span class="awaiting-mod count-' +
			W3TCFeatureShowcaseData.unseenCount +
			'"><span class="feature-count">' +
			W3TCFeatureShowcaseData.unseenCount +
			'</span></span>';

	if ( W3TCFeatureShowcaseData.unseenCount > 0 ) {
		if ( $adminmenuItem.length ) {
			$adminmenuItem.append( markup );

			$adminmenuItem.find( '.awaiting-mod' ).css(
				{
					padding: "0 5px",
					"min-width": "18px",
					height: "18px",
					"border-radius": "9px",
					"background-color": "#ca4a1f",
					color: "#fff",
					"font-size": "11px"
				}
			);
		}

		if ( $menuItem.length ) {
			$menuItem.append( markup );
		}

		if ( $submenuItem.length ) {
			$submenuItem.append( markup );
		}
	}
});
