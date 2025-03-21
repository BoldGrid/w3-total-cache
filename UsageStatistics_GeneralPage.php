<?php
/**
 * File: UsageStatistics_GeneralPage.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class UsageStatistics_GeneralPage
 */
class UsageStatistics_GeneralPage {
	/**
	 * Initializes the general page settings for Usage Statistics in the W3 Total Cache plugin.
	 *
	 * This method sets up hooks to add custom sections and content to the General Settings page.
	 *
	 * @return void
	 */
	public static function admin_init_w3tc_general() {
		$o = new UsageStatistics_GeneralPage();

		add_filter( 'w3tc_settings_general_anchors', array( $o, 'w3tc_settings_general_anchors' ) );
		add_action( 'w3tc_settings_general_boxarea_stats', array( $o, 'w3tc_settings_general_boxarea_stats' ) );
	}

	/**
	 * Adds a "Statistics" anchor to the General Settings page navigation.
	 *
	 * This method modifies the array of navigation anchors for the General Settings page
	 * by appending an entry for the "Statistics" section.
	 *
	 * @param array $anchors An array of existing anchors for the General Settings page.
	 *
	 * @return array The modified array of anchors with the "Statistics" anchor included.
	 */
	public function w3tc_settings_general_anchors( $anchors ) {
		$anchors[] = array(
			'id'   => 'stats',
			'text' => __( 'Statistics', 'w3-total-cache' ),
		);

		return $anchors;
	}

	/**
	 * Renders the Statistics box area on the General Settings page.
	 *
	 * This method includes a view file that outputs the content for the Statistics section
	 * of the General Settings page.
	 *
	 * @return void
	 */
	public function w3tc_settings_general_boxarea_stats() {
		include W3TC_DIR . '/UsageStatistics_GeneralPage_View.php';
	}
}
