<?php
namespace W3TC;

/**
 * widget with stats
 */
class ProAd_UsageStatistics_Widget {
	public function init() {
		Util_Widget::add2( 'w3tc_usage_statistics', 1000,
			'<div class="w3tc-widget-w3tc-logo"></div>' .
			'<div class="w3tc-widget-text">' .
			__( 'Caching Statistics', 'w3-total-cache' ) .
			'</div>',
			array( $this, 'widget_form' ),
			Util_Ui::admin_url( 'admin.php?page=w3tc_stats' ),
			'normal',
			 'Detailed' );
	}



	public function widget_form() {
		include  W3TC_DIR . '/ProAd_UsageStatistics_Widget_View.php';
	}
}
