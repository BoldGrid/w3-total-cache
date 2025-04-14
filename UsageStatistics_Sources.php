<?php
/**
 * File: UsageStatistics_Sources.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class UsageStatistics_Sources
 */
class UsageStatistics_Sources {
	/**
	 * Generates a summary of usage statistics based on historical data.
	 *
	 * This method processes the provided usage history and compiles statistics for various sources, including
	 * PHP, APC, Memcached, Redis, CPU, and access logs.The summary is populated with the relevant metrics for
	 * each source, such as memory usage, request counts, and CPU averages. It is designed to be used for
	 * generating a comprehensive view of the system's resource usage over time.
	 *
	 * @param array $summary   The current summary to be updated with usage statistics.
	 * @param array $history   The historical data to process for generating the summary.
	 *
	 * @return array The updated summary with the compiled usage statistics.
	 */
	public static function w3tc_usage_statistics_summary_from_history( $summary, $history ) {
		// php data.
		$php_memory_100kb = Util_UsageStatistics::sum( $history, 'php_memory_100kb' );
		$php_requests     = Util_UsageStatistics::sum( $history, 'php_requests' );

		if ( $php_requests > 0 ) {
			if ( ! isset( $summary['php'] ) ) {
				$summary['php'] = array();
			}

			$summary['php']['memory']                  = Util_UsageStatistics::bytes_to_size( $php_memory_100kb / $php_requests * 1024 * 10.24 );
			$summary['php']['php_requests_v']          = $php_requests;
			$summary['php']['php_requests']            = Util_UsageStatistics::integer( $php_requests );
			$summary['php']['php_requests_per_second'] = Util_UsageStatistics::value_per_period_seconds( $php_requests, $summary );
		}

		// apc.
		if ( count( $summary['apc_servers'] ) > 0 ) {
			$apc            = new UsageStatistics_Sources_Apc( $summary['apc_servers'] );
			$summary['apc'] = $apc->get_summary();
		}

		// memcached.
		if ( count( $summary['memcached_servers'] ) > 0 ) {
			$memcached            = new UsageStatistics_Sources_Memcached( $summary['memcached_servers'] );
			$summary['memcached'] = $memcached->get_summary();
		}

		// redis.
		if ( count( $summary['redis_servers'] ) > 0 ) {
			$redis            = new UsageStatistics_Sources_Redis( $summary['redis_servers'] );
			$summary['redis'] = $redis->get_summary();
		}

		// cpu snapshot.
		$c = Dispatcher::config();
		if ( $c->get_boolean( 'stats.cpu.enabled' ) ) {
			$summary['cpu'] = array(
				'avg' => round( Util_UsageStatistics::avg( $history, 'cpu' ), 2 ),
			);
		}

		// access log data.
		if ( $c->get_boolean( 'stats.access_log.enabled' ) ) {
			$o = new UsageStatistics_Source_AccessLog(
				array(
					'webserver' => $c->get_string( 'stats.access_log.webserver' ),
					'filename'  => $c->get_string( 'stats.access_log.filename' ),
					'format'    => $c->get_string( 'stats.access_log.format' ),
				)
			);

			$summary = $o->w3tc_usage_statistics_summary_from_history( $summary, $history );
		}

		return $summary;
	}

	/**
	 * Retrieves the current metric values for various usage statistics sources.
	 *
	 * This method fetches real-time data for Memcached, Redis, APC, and CPU usage. It aggregates these
	 * metrics into an array and returns it. This is useful for generating up-to-date metric snapshots.
	 *
	 * @param array $metric_values   The current metric values to be populated with real-time statistics.
	 *
	 * @return array The updated metric values with data from the available sources.
	 */
	public static function w3tc_usage_statistics_metric_values( $metric_values ) {
		$sources = array(
			'memcached_servers' => array(),
			'redis_servers'     => array(),
			'apc_servers'       => array(),
		);

		$sources = apply_filters( 'w3tc_usage_statistics_sources', $sources );

		if ( count( $sources['memcached_servers'] ) > 0 ) {
			$memcached                  = new UsageStatistics_Sources_Memcached( $sources['memcached_servers'] );
			$metric_values['memcached'] = $memcached->get_snapshot();
		}

		if ( count( $sources['apc_servers'] ) > 0 ) {
			$apc                  = new UsageStatistics_Sources_Apc( $sources['apc_servers'] );
			$metric_values['apc'] = $apc->get_snapshot();
		}

		if ( count( $sources['redis_servers'] ) > 0 ) {
			$redis                  = new UsageStatistics_Sources_Redis( $sources['redis_servers'] );
			$metric_values['redis'] = $redis->get_snapshot();
		}

		$c = Dispatcher::config();
		if ( $c->get_boolean( 'stats.cpu.enabled' ) ) {
			// cpu snapshot.
			$cpu = sys_getloadavg();
			if ( isset( $cpu[0] ) ) {
				$metric_values['cpu'] = $cpu[0];
			}
		}

		return $metric_values;
	}

	/**
	 * Sets the usage history based on the provided historical data.
	 *
	 * This method processes access log data if enabled and adds it to the history.
	 * It updates the provided history with the relevant data for future analysis or reporting.
	 *
	 * @param array $history   The history to be updated with the usage statistics.
	 *
	 * @return array The updated history with additional data.
	 */
	public static function w3tc_usage_statistics_history_set( $history ) {
		$c = Dispatcher::config();
		if ( $c->get_boolean( 'stats.access_log.enabled' ) ) {
			// read access log.
			$o = new UsageStatistics_Source_AccessLog(
				array(
					'webserver' => $c->get_string( 'stats.access_log.webserver' ),
					'filename'  => $c->get_string( 'stats.access_log.filename' ),
					'format'    => $c->get_string( 'stats.access_log.format' ),
				)
			);

			$history = $o->w3tc_usage_statistics_history_set( $history );
		}

		return $history;
	}
}
