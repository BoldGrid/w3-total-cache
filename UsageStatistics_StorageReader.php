<?php
/**
 * File: UsageStatistics_StorageReader.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class UsageStatistics_StorageReader
 *
 * Manages data statistics.
 */
class UsageStatistics_StorageReader {
	/**
	 * This attribute determines how many historical records are included in the returned history array.
	 * The value can be adjusted to retrieve more or fewer records.
	 *
	 * @var int
	 */
	private $items_to_return = 60;

	/**
	 * Retrieves a summary of the usage statistics history, including server information and time periods.
	 *
	 * This method fetches the usage statistics history from the WordPress site options, decodes it, and builds a summary
	 * of the statistics. The summary includes the start and end timestamps, the time period in minutes, and any available
	 * server information (Memcached, Redis, and APC). It applies relevant filters to allow customization and returns the
	 * summary of the statistics. It also ensures that the history array is returned with a predefined number of items.
	 *
	 * @return array The summary of usage statistics history, including period, timeout time, and history.
	 */
	public function get_history_summary() {
		$w = new UsageStatistics_StorageWriter();
		$w->maybe_flush_hotspot_data();

		$history_encoded = get_site_option( 'w3tc_stats_history' );
		$history         = null;
		if ( ! empty( $history_encoded ) ) {
			$history = json_decode( $history_encoded, true );
		}

		if ( ! is_array( $history ) ) {
			$history = array();
		}

		$summary = array(
			'memcached_servers' => array(),
			'redis_servers'     => array(),
			'apc_servers'       => array(),
		);

		$summary = apply_filters( 'w3tc_usage_statistics_sources', $summary );

		if ( count( $history ) <= 0 ) {
			$summary = array( 'period' => array() );
		} else {
			$timestamp_start = $history[0]['timestamp_start'];
			$timestamp_end   = $history[ count( $history ) - 1 ]['timestamp_end'];

			$period = array(
				'timestamp_start'      => $timestamp_start,
				'timestamp_start_mins' => Util_UsageStatistics::time_mins( $timestamp_start ),
				'timestamp_end'        => $timestamp_end,
				'timestamp_end_mins'   => Util_UsageStatistics::time_mins( $timestamp_end ),
			);

			$period['seconds']       = $timestamp_end - $timestamp_start;
			$summary['period']       = $period;
			$summary['timeout_time'] = time() + 15;

			$summary = apply_filters( 'w3tc_usage_statistics_summary_from_history', $summary, $history );
		}

		$summary['period']['to_update_secs'] = (int) $w->get_hotspot_end() - time() + 1;

		unset( $summary['memcached_servers'] );
		unset( $summary['redis_servers'] );

		$history_count = count( $history );
		while ( $history_count < $this->items_to_return ) {
			array_unshift( $history, array() );
			++$history_count; // Ensure count updates correctly.
		}

		$summary['history'] = $history;

		return $summary;
	}
}
