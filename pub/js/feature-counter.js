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
	function markup(count) {
		return ' <span class="awaiting-mod count-' + count + '">' +
			'<span class="feature-count">' + count + '</span></span>';
	}

	var $adminmenuItem = jQuery ( '#wp-admin-bar-w3tc_feature_showcase a' ),
		$menuItem = jQuery( '#toplevel_page_w3tc_dashboard.wp-not-current-submenu a[href="admin.php?page=w3tc_dashboard"] .wp-menu-name' ),
		$submenuItem = jQuery( '#toplevel_page_w3tc_dashboard a[href="admin.php?page=w3tc_feature_showcase"]' );

	var menuCount = 0;

	if ( W3TCFeatureShowcaseData.unseenCount > 0 ) {
		menuCount += W3TCFeatureShowcaseData.unseenCount;

		if ( $adminmenuItem.length ) {
			$adminmenuItem.append( markup( W3TCFeatureShowcaseData.unseenCount ) );

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

		if ( $submenuItem.length ) {
			$submenuItem.append( markup( W3TCFeatureShowcaseData.unseenCount ) );
		}
	}

	var $submenuUpdate = jQuery( '#toplevel_page_w3tc_dashboard a[href="admin.php?page=w3tc_update"]' );
	if ( $submenuUpdate.length ) {
		$submenuUpdate.append( markup( 1 ) );
		menuCount++;
	}


	if ( menuCount > 0 && $menuItem.length ) {
		$menuItem.append( markup( menuCount ) );
	}
});
