<?php
/**
 * File: UsageStatistics_Plugin.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class UsageStatistics_Plugin
 */
class UsageStatistics_Plugin {
	/**
	 * Initializes the Usage Statistics functionality and hooks necessary actions and filters.
	 *
	 * This method sets up the core component for usage statistics and registers the shutdown handler.
	 * It also hooks into various actions and filters for handling usage statistics of requests, metrics,
	 * and metric values. It initializes the `UsageStatistics_Source_Wpdb` component for further handling
	 * of data storage.
	 *
	 * @return void
	 */
	public function run() {
		$core = Dispatcher::component( 'UsageStatistics_Core' );
		$core->add_shutdown_handler();

		// usage default statistics handling.
		add_action( 'w3tc_usage_statistics_of_request', array( $this, 'w3tc_usage_statistics_of_request' ), 10, 1 );
		add_filter( 'w3tc_usage_statistics_metrics', array( $this, 'w3tc_usage_statistics_metrics' ) );
		add_filter( 'w3tc_usage_statistics_metric_values', array( '\W3TC\UsageStatistics_Sources', 'w3tc_usage_statistics_metric_values' ) );
		add_filter( 'w3tc_usage_statistics_history_set', array( '\W3TC\UsageStatistics_Sources', 'w3tc_usage_statistics_history_set' ) );

		UsageStatistics_Source_Wpdb::init();
	}

	/**
	 * Records usage statistics for the current request, including PHP memory usage and request counts.
	 *
	 * This method adds statistics to the provided `$storage` object, such as the amount of PHP memory used
	 * (in 100KB increments) and the number of PHP requests. Additional statistics for specific environments
	 * (e.g., WordPress Admin, AJAX) can be included but are currently commented out.
	 *
	 * @param object $storage The storage object to which the statistics are added.
	 *
	 * @return void
	 */
	public function w3tc_usage_statistics_of_request( $storage ) {
		$used_100kb = memory_get_peak_usage( true ) / 1024 / 10.24;

		$storage->counter_add( 'php_memory_100kb', $used_100kb );
		$storage->counter_add( 'php_requests', 1 );

		/*
		Keep for mode when pagecache not enabled, otherwise it shows own stats similar to that
		if ( defined( 'WP_ADMIN' ) ) {
			$storage->counter_add( 'php_requests_wp_admin', 1 );
		}
		if ( defined( 'DOING_AJAX' ) ) {
			$storage->counter_add( 'php_requests_ajax', 1 );
		}
		*/
	}

	/**
	 * Adds custom usage metrics to the list of existing metrics.
	 *
	 * This method extends the list of metrics by adding custom ones related to PHP memory usage and request counts.
	 * The metrics include 'php_memory_100kb' and 'php_requests', which help track memory usage and the number
	 * of PHP requests made during a session.
	 *
	 * @param array $metrics The existing list of metrics.
	 *
	 * @return array The updated list of metrics with the added custom metrics.
	 */
	public function w3tc_usage_statistics_metrics( $metrics ) {
		return array_merge( $metrics, array( 'php_memory_100kb', 'php_requests' ) );
	}
}
