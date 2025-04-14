<?php
/**
 * File: UsageStatistics_Source_DbQueriesLog.php
 *
 * @package W3TC
 */

namespace W3TC;

/**
 * Class UsageStatistics_Source_Wpdb
 *
 * Provides statistics data about requests made to mysql server
 */
class UsageStatistics_Source_Wpdb {
	/**
	 * Total number of WPDB queries executed.
	 *
	 * @var int
	 */
	private $query_total = 0;

	/**
	 * Initializes the usage statistics by registering filters and actions.
	 *
	 * This method sets up the necessary WordPress hooks for gathering usage statistics
	 * related to WPDB calls. It adds filters and actions to track query data and metrics
	 * as well as handle historical statistics.
	 *
	 * @return void
	 */
	public static function init() {
		$o = new UsageStatistics_Source_Wpdb();

		add_filter( 'query', array( $o, 'query' ) );
		add_action( 'w3tc_usage_statistics_of_request', array( $o, 'w3tc_usage_statistics_of_request' ), 10, 1 );
		add_filter( 'w3tc_usage_statistics_metrics', array( $o, 'w3tc_usage_statistics_metrics' ) );
		add_filter( 'w3tc_usage_statistics_summary_from_history', array( $o, 'w3tc_usage_statistics_summary_from_history' ), 10, 2 );
	}

	/**
	 * Adds custom metrics for usage statistics.
	 *
	 * This method extends the default metrics with custom values, such as the total number
	 * of WPDB queries executed. This allows for the gathering of additional statistics related
	 * to database queries during usage reporting.
	 *
	 * @param array $metrics Existing metrics to be extended.
	 *
	 * @return array Updated array of metrics including custom metrics.
	 */
	public function w3tc_usage_statistics_metrics( $metrics ) {
		return array_merge( $metrics, array( 'wpdb_calls_total' ) );
	}

	/**
	 * Summarizes usage statistics from historical data.
	 *
	 * This method calculates the total number of WPDB queries from the historical data
	 * and includes it in the summary. It also calculates the rate of queries per second
	 * over the given history period.
	 *
	 * @param array $summary Existing summary of usage statistics.
	 * @param array $history Historical usage data.
	 *
	 * @return array Updated summary with WPDB statistics.
	 */
	public function w3tc_usage_statistics_summary_from_history( $summary, $history ) {
		// counters.
		$wpdb_calls_total = Util_UsageStatistics::sum( $history, 'wpdb_calls_total' );

		$summary['wpdb'] = array(
			'calls_total'      => Util_UsageStatistics::integer( $wpdb_calls_total ),
			'calls_per_second' => Util_UsageStatistics::value_per_period_seconds( $wpdb_calls_total, $summary ),
		);

		return $summary;
	}

	/**
	 * Tracks usage statistics for the current request.
	 *
	 * This method adds the total number of WPDB queries executed during the current request
	 * to the storage object for reporting purposes. It is triggered at the end of a request
	 * to ensure accurate tracking.
	 *
	 * @param object $storage The storage object where statistics are recorded.
	 *
	 * @return void
	 */
	public function w3tc_usage_statistics_of_request( $storage ) {
		$storage->counter_add( 'wpdb_calls_total', $this->query_total );
	}

	/**
	 * Intercepts a database query and tracks the number of queries.
	 *
	 * This method is called every time a WPDB query is executed. It increments the total
	 * query count (`$query_total`) for the current request and returns the query unchanged.
	 * This allows for tracking the number of WPDB queries executed.
	 *
	 * @param string $q The SQL query being executed.
	 *
	 * @return string The original SQL query.
	 */
	public function query( $q ) {
		++$this->query_total;
		return $q;
	}
}
